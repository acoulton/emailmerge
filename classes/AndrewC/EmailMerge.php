<?php
use Michelf\MarkdownExtra;

defined('SYSPATH') or die('No direct script access.');
/**
 * Implements a system for creating, previewing and sending a merged
 * email based on a given template and dataset.
 *
 *     $data = $model_collection->toArray();
 *     $merge = EmailMerge::factory()
 *              ->set_data($data)
 *              ->template_namespace('competition')
 *              ->template('winner')
 *              ->on_complete(Route::get('default')
 *                              ->uri(array('controller'=>'competition',
 *                                          'action'=>'merge_done')),
 *                            EmailMerge::COMPLETION_HMVC_POST);
 *
 *     // Show a form allowing the user to customise the merge and proceed to
 *     // preview, edit, and send
 *     $this->request->redirect($merge->action_uri(EmailMerge::ACTION_CUSTOMISE));
 *
 * @package    EmailMerge
 * @category   Core
 * @author     Andrew Coulton
 * @copyright  (c) 2011 Andrew Coulton
 * @license    http://kohanaphp.com/license
 */
class AndrewC_EmailMerge
{
    /**
     * The EmailMerge::COMPLETION_* constants define how a completion callback
     * should be executed - as an external redirect in the user's browser, or by
     * HMVC.
     */
    const COMPLETION_REDIRECT = 1;
    const COMPLETION_HMVC_GET = 2;
    const COMPLETION_HMVC_POST = 3;

    /**
     * The EmailMerge::ACTION_* constants are used for easy generation of redirects
     * and links to actions within Controller_EmailMerge
     */
    const ACTION_CUSTOMISE = 'customise';
    const ACTION_PREVIEW = 'preview';
    const ACTION_EDIT = 'edit';
    const ACTION_SEND = 'send';
    const ACTION_COMPLETE = 'complete';
    const ACTION_PROCESS = 'process';

    /**
     * @var string A UUID identifying this EmailMerge instance
     */
    protected $_merge_id = null;

    /**
     * @var array An array of data arrays (generally from a model) used to build the merge
     */
    protected $_data = null;

    /**
     * @var string The key of the field within the data containing email addresses
     */
    protected $_email_field = 'email';

    /**
     * @var string The (optional) key of the field containing real names
     */
    protected $_email_name_field = null;

    /**
     * @var string The name of the layout view used in the EmailMerge controller as a template
     */
    protected $_controller_layout = null;

    /**
     * @var string A base template for an HTML email, which should contain the overall structure and any styles
     */
    protected $_email_layout = 'templates/emailBase';

    /**
     * @var EmailMerge_Template The template object
     */
    protected $_template = null;

    /**
     * @var string Email address of the sender
     */
    protected $_sender_email = null;

    /**
     * @var string Name of the sender
     */
    protected $_sender_name = null;

    /**
     * @var array Details of the route, parameters and method to execute when the merge has been sent
     */
    protected $_on_complete = null;

    /**
     * @var array A configuration array, loaded initially from config and then persisted with the instance
     */
    protected $_config = array();

    /**
     * @var string Stores the merged mails ready for sending - reset whenever a template changes
     */
    protected $_merged_mails = array();

    /**
     * @var MarkdownExtra The parser used to render the emails
     */
    protected $_markdown = null;

    /**
     * Reloads an existing merge, or creates a new one.
     *
     *     $merge = EmailMerge::factory();
     *
     *     // If you want to prevent a new merge being created, pass an invalid ID
     *     $existing_merge = EmailMerge::factory($this->request->query('merge_id',-1));
     *
     * @param string $merge_id The UUID of the merge to load, or null to create new
     * @param array $config A set of config values to merge with existing config
     * @return EmailMerge
     * @throws InvalidArgumentException if merge_id is not valid
     */
    public static function factory($merge_id = null, $config = array())
    {
        // Merge the passed config with the config file settings
        $config = Arr::merge($config, Kohana::$config->load('emailmerge.instance'));

        if ($merge_id)
        {
            // Check the UUID is valid
            if ( ! UUID::valid($merge_id))
            {
                throw new InvalidArgumentException($merge_id . " is not a valid emailmerge UUID");
            }

            // Get the path to the file and check it exists
            $file = EmailMerge::persistence_file($merge_id);
            if ( ! file_exists($file))
            {
                throw new InvalidArgumentException($file . " is not a valid emailmerge file");
            }

            // Unserialize the merge file
            return unserialize(file_get_contents($file));
        }
        else
        {
            // Create a new EmailMerge
            return new EmailMerge($config);
        }
    }

    /**
     * Generic set/get method for use by combined setter/getter methods.
     * @param string $property Name of the property
     * @param mixed $value Value to set, or null to get the value
     * @return mixed If called as getter
     * @return AndrewC_EmailMerge If called as setter
     */
    protected  function _set_get($property, $value)
    {
        if ($value === null)
        {
            return $this->$property;
        }
        $this->$property = $value;
        return $this;
    }


    /**
     * Gets (and instantiates, if required) the template
     *
     *     $merge = EmailMerge::factory($merge_id);
     *     $merge->template()->save('custom_template');
     *
     * @return EmailMerge_Template
     */
    public function template()
    {
        if ( ! $this->_template instanceof EmailMerge_Template)
        {
            // Try to load
            $this->_template = EmailMerge_Template::factory($this);
        }
        return $this->_template;
    }

    /**
     * Event handler called by the template when the body or subject has changed
     * to reset the merged mails array.
     *
     * [!!] This is not part of the public API, and does not need to be called
     * by userland code
     */
    public function template_changed()
    {
        $this->_merged_mails = array();
    }

	/**
	 * Internal method to convert a merge UUID into a path to the [persistence
	 * file](emailmerge/persistence).
	 *
	 * @param string $merge_id The UUID of the merge
	 * @return string The expected path to the merge file
	 */
    public static function persistence_file($merge_id)
    {
        $base = Kohana::$config->load('emailmerge.persistence_path');
        $path = $base . str_replace('-', '/', $merge_id) . ".merge";
        return $path;
    }

    /**
     * Cleans up empty nested persistence paths left over from previous merges.
     *
     *     $merge->garbage_collect()
     *
     */
    public function garbage_collect()
    {
        $base = Kohana::$config->load('emailmerge.persistence_path');
		if ( ! is_dir($base))
		{
			return;
		}

        // Get a DirectoryIterator and recurse into it
        $iterator = new DirectoryIterator($base);
        $this->_gc_find_empty($iterator, $base, $empty_paths);

        // Check for empty paths
        if (! $empty_paths)
        {
            return;
        }

        // Reverse, so that we are working up the tree rather than down it
        array_reverse($empty_paths);
        $problems = false;
        foreach ($empty_paths as $path)
        {
            try
            {
                unlink($path);
            }
            catch(ErrorException $e)
            {
                $problems[] = $path;
            }
        }

        // Some issues on Windows (still to be investigated) where empty paths cannot be unlinked
        if ($problems)
        {
            Kohana::$log->add(Log::ERROR, "Couldn't unlink some paths: " . implode(', ', $problems));
        }
    }

    /**
     * Internal method to recurse into a persistence tree and detect any branches
     * that only contain empty folders.
     *
     * @param DirectoryIterator $iterator An iterator for the current level of the tree
     * @param string $base_path The full path corresponding to the iterator's base
     * @param array $empty_paths An array of empty folders (including folders containing empty folders)
     * @return boolean Whether the given tree branch is empty (only contains folders)
     */
    protected function _gc_find_empty(DirectoryIterator $iterator, $base_path, &$empty_paths)
    {
        $empty = true;
        foreach ($iterator as $file)
        {
			/** @var DirectoryIterator $file */
            if ($file->isDot())
            {
                continue;
            }

            if ($file->isFile())
            {
                return false;
            }

            // If it's less than 1 min old in case we're persisting right now
            if ($file->getMTime() > (time()-60))
            {
                return false;
            }

            // Recurse into any folders found
            if ($file->isDir())
            {
                $sub_empty = $this->_gc_find_empty(new DirectoryIterator($file->getPathname()), $file->getPathname(), $empty_paths);
                $empty = $empty && $sub_empty;
            }
        }
        if ($empty)
        {
            $empty_paths[] = $base_path;
        }
        return $empty;
    }

    /**
     * Constructs a new instance and sets a unique id for later use.
     * Call [EmailMerge::factory()] from the application level
     * @param array $config An array of config settings
     */
    public function __construct($config = array())
    {
        $this->_config = $config;
        $this->_merge_id = UUID::v4();
        $this->garbage_collect();
    }


    /**
     * Sets a layout view for use by [Controller_EmailMerge] when presenting the
     * various user screens for editing, previewing and sending the merge.
     * [!!] Remember, Controller_EmailMerge does not extend any standard base controller
     * you use, so you may need to consider how to set scripts, styles, top level variables etc.
     *
     *     $merge = EmailMerge::factory()
     *              ->controller_layout('templates/site');
     *
     * @param string $layout The layout view
	 *
     * @return AndrewC_EmailMerge|string
     */
    public function controller_layout($layout = null)
    {
        if ($layout === null)
        {
            return $this->_controller_layout;
        }
        $this->_controller_layout = $layout;
        return $this;
    }

    /**
     * Loads an array of arrays containing the data, and sets the keys that identify
     * the email address and real name of the recipients.
     *
     *     $data = array(array(
     *                      'use_email'=>'test@test.com',
     *                      'name'=>'Mr Test',
     *                      'favourite_colour'=>'Red'));
     *
     *     $merge = EmailMerge::factory()
     *              ->set_data($data, 'use_email', 'name');
     *
     * @param array $data   An array of data arrays
     * @param string $email_field The key containing the email addresses (defaults to email)
     * @param string $real_name The key containing the real name (defaults to null)
     * @return EmailMerge
     */
    public function set_data($data, $email_field = 'email', $real_name = null)
    {
        $this->_data = $data;
        $this->_email_field = $email_field;
        $this->_email_name_field = $real_name;
        $this->_merged_mails = null;
        return $this;
    }

    /**
     * Returns the current data.
     * @return array
     */
    public function get_data()
    {
        return $this->_data;
    }

    /**
     * Returns the email address from a given record.
     * @param array $data_row The array representing this row of data
     * @return string
     */
    public function get_email_address($data_row)
    {
        return $data_row[$this->_email_field];
    }

    /**
     * Returns the merge ID.
     * @return string
     */
    public function id()
    {
        return $this->_merge_id;
    }

    /**
     * Setter/Getter for the template namespace. The namespace cannot be set by
     * the user at runtime, and can therefore be used to limit the choices of template
     * available. See [template documentation](emailmerge/templates).
     *
     *     $merge = EmailMerge::factory()
     *              ->template_namespace('competition');
     *
     * @param string $namespace
     *
     * @return EmailMerge|string If used as setter
     * @throws BadMethodCallException if the template has already been loaded
     */
    public function template_namespace($namespace = null)
    {

        if ($namespace === null)
        {
            return $this->template()->get_namespace();
        }

        $this->template()->set_namespace($namespace);
        return $this;
    }

    /**
     * Proxy to [EmailMerge_Template::load()] to allow chaining of calls with the
     * merge object in scope.
     *
     * @param string $name
     * @return EmailMerge If used as setter
     */
    public function template_load($name)
    {
        $this->template()->load($name);
        return $this;
    }

    /**
     * Sets or gets the sender's email address.
     *
     * @param string $email
     * @return AndrewC_EmailMerge|string
     */
    public function sender_email($email = null)
    {
        return $this->_set_get('_sender_email',$email);
    }

    /**
     * Sets or gets the sender's name.
     * @param string $name
     *
     * @return AndrewC_EmailMerge|string If used as a setter
     */
    public function sender_name($name = null)
    {
        return $this->_set_get('_sender_name',$name);
    }

    /**
     * [Persists the merge](emailmerge/persistence) to permanent storage so that it is available in subsequent
     * requests to the wizard.
     *
     * @return EmailMerge
     */
    public function persist()
    {
        // Get the path for the persistence file
        $file = self::persistence_file($this->_merge_id);

        // Create the path if required
        $path = dirname($file);
        if ( ! file_exists($path))
        {
            mkdir($path, 0777, TRUE);
        }

        // Persist the class
        file_put_contents($file, serialize($this));
        return $this;
    }

    /**
     * Disposes of the storage for this merge.
     * [!!] If you implement an on_complete handler, you must call dispose() to
     * clean up your persistence files once you're done.
     *
     *     $merge = EmailMerge::factory($merge_id);
     *     $data = $merge->get_data();
     *     // Do something with the data
     *     $merge->dispose();
     *
     * @return EmailMerge
     */
    public function dispose()
    {
        $file = self::persistence_file($this->_merge_id);
        unlink($file);
        $this->garbage_collect();
        return $this;
    }

    /**
     * Builds the merged mails by processing the template with each row's data
     * and creating an array of markdown and HTML formatted data ready for sending.
     *
     * @param boolean $force If set, then will force re-generation of the merge even if it has already been built
     * @return boolean If the merge was built
     */
    public function build_merge($force = false)
    {
        // Don't rebuild if we don't have to
        if ($this->_merged_mails AND ! $force)
        {
            return false;
        }

        // Get a markdown instance
        $markdown = $this->get_markdown();

        // Build the merge
        $this->_merged_mails = array();
        foreach ($this->_data as $email)
        {
            $mail = array();
            $mail['email'] = Arr::get($email,$this->_email_field);
            $mail['name'] = Arr::get($email, $this->_email_name_field, $mail['email']);
            $mail = $mail + $this->_template->merge_mail($email);
            $mail['html_body'] = $markdown->transform($mail['body']);
            $this->_merged_mails[] = $mail;
        }
        return true;
    }

    /**
     * Returns the array of merged mails, building it if necessary.
     * @return array
     */
    public function get_mails()
    {
        if ( ! $this->_merged_mails)
        {
            $this->build_merge();
        }
        return $this->_merged_mails;
    }

    /**
     * Completely replaces the array of merged mails - this is used when editing
     * individual emails within the merge to replace the default with the
     * customised texts.
     * [!!] This method will rebuild the HTML body in the passed array - only the
     * markdown body should be passed in.
     *
     *     // Structure of a merged mail array
     *     array( array(
     *         'email' => 'test@test.com',
     *         'name' => 'Mr Test',
     *         'subject' => 'You won the competition',
     *         'body' => 'Dear **Mr Test**, you won - [claim your prize](http://prize.com)'));
     *
     * @param array $mails
     * @return AndrewC_EmailMerge
     */
    public function replace_mails($mails)
    {
        $this->_merged_mails = array();

        // Re-parse the markdown to generate the HTML body
        $markdown = $this->get_markdown();

        foreach ($mails as $mail)
        {
            $mail['html_body'] = $markdown->transform($mail['body']);
            $this->_merged_mails[] = $mail;
        }
        return $this;
    }

    /**
     * Generates a Swift_Mail ready for sending from a set of mail_data. Currently,
     * the mail is BCC to the sender, and contains an X-EmailMerge header that can be
     * used to build mail filters that file these incoming copies as sent items.
     *
     * @param array $mail_data
     * @return Swift_Message
     */
    public function swift_mail($mail_data)
    {
        /* @var $mail Swift_Message */
        $mail = Swift_Message::newInstance();

        // Set the sender and recipient
        $real_name = $mail_data['name'] == $mail_data['email'] ? null : $mail_data['name'];
        $mail->setTo($mail_data['email'], $real_name);
        $mail->setSubject($mail_data['subject']);
        $mail->setFrom($this->_sender_email, $this->_sender_name);
        $mail->setBcc($this->_sender_email);

        // Set the identifying custom header
        $headers = $mail->getHeaders();
        $headers->addTextHeader('X-EmailMerge-'.$this->template()->get_namespace(), 'automail');

        // Get a View class if required
        if ( ! $this->_email_layout instanceof View)
        {
            $this->_email_layout = View::factory($this->_email_layout);
        }

        // Render the HTML view
        $this->_email_layout->set('bodyText',$mail_data['html_body']);

        $richMessage = $this->_email_layout->render();

        // Add the HTML and text views
        $mail->setBody(preg_replace('/[ \t]+/', ' ', strip_tags($richMessage)));
        $mail->addPart($richMessage,'text/html');
        return $mail;

    }

	/**
	 * Loads the markdown parser (requires the composer autoloader)
	 *
	 * @return \Michelf\MarkdownExtra
	 */
    protected function get_markdown()
    {
        static $markdown = null;

        if ($markdown)
        {
            return $markdown;
        }

		$markdown = new Michelf\MarkdownExtra;
        $markdown->no_markup = true;
        return $markdown;
    }

    /**
     * Sets an on complete handler to call once the mail merge is done. This can be
     * executed either as a redirect or as an HMVC request, and custom data can be
     * added to the GET string.
     *
     *     $merge = Email_Merge::factory()
     *              ->set_on_complete('competition/winners', EmailMerge::COMPLETION_REDIRECT);
     *
     * @param string $uri The uri that should be called
     * @param int $method One of the EmailMerge::COMPLETION_* constants
     * @param array $data Data to include in GET with the request
     * @return AndrewC_EmailMerge
     */
    public function set_on_complete($uri, $method = self::COMPLETION_REDIRECT, $data=array())
    {
        $this->_on_complete = array(
            'uri' => $uri,
            'method' => $method,
            'data' => array('merge_id'=>$this->_merge_id) + $data
        );

        return $this;
    }

    /**
     * Executes the on complete handler within the passed in parent request. If
     * called as HMVC, the response will be returned as the response of the main
     * request - this often means that the user's browser will not get a
     * post-redirect-get sequence. Alternatively, the passed request will be
     * redirected to the handler complete with appropriate GET data.
     *
     * [!!] Whether HMVC or GET, the handler is responsible for calling [EmailMerge::dispose()]
     * to clean up the storage once the merge has completed and the handler has done
     * what it needs to.
     *
     * @param Request  $request  The parent request
     * @param Response $response The parent response
     * @return void
     * @throws InvalidArgumentException if the method is not valid
     */
    public function merge_complete(Request $request, Response $response)
    {
        // Get the handler, or the default
        $action = $this->_on_complete ? $this->_on_complete
                                      : array('uri'=>Route::get('emailmerge')
                                                ->uri(array('action'=>'complete',
                                                            'merge_id'=>$this->_merge_id)),
                                              'method'=>self::COMPLETION_REDIRECT,
                                              'data'=>array('merge_id'=>$this->_merge_id));
        extract($action);

        switch ($method)
        {
            // Redirect to the handler
            case self::COMPLETION_REDIRECT:
                $uri = $uri . "?" . http_build_query($data);
				HTTP::redirect($uri);
            break;

            // Execute the handler by HMVC and return the result
            case self::COMPLETION_HMVC_GET:
            //@todo: Can't do POST with KO3.0
            case self::COMPLETION_HMVC_POST:
                $sub_request_response = Request::factory($uri)
								->query($data)
                                ->execute();
                $response->body($sub_request_response->body());
            return;
            default:
                // Unknown method type
                throw new InvalidArgumentException("Invalid method $method for on-complete callback");
        }
    }

    /**
     * Gets the URI to one of the EmailMerge controller actions
     * @param string $action The action - one of the EmailMerge::ACTION_* constants
     * @return string The uri
     */
    public function action_uri($action)
    {
        return Route::get('emailmerge')
                ->uri(array('merge_id'=>$this->_merge_id, 'action'=>$action));
    }

} // End AndrewC_EmailMerge
