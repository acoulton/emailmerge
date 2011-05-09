<?php
defined('SYSPATH') or die('No direct script access.');
/**
 * Implements a system for creating, previewing and sending a merged
 * email based on a given template and dataset.
 *
 *     $data = $model_collection->toArray();
 *     $merge = EmailMerge::factory()
 *              ->data($data)
 *              ->email_field('custom_email')
 *              ->template_namespace('competition')
 *              ->template('winner')
 *              ->on_complete(Route::get('default')
 *                              ->uri(array('controller'=>'thing',
 *                                          'action'=>'merge_done')),
 *                            EmailMerge::COMPLETION_HMVC_POST);
 *
 *     // Show a form allowing the user to customise the merge and proceed to
 *     // preview, edit, and send
 *     $this->request->response = $merge->customise();
 *
 */
class AndrewC_EmailMerge
{
    const COMPLETION_REDIRECT = 1;
    const COMPLETION_HMVC_GET = 2;
    const COMPLETION_HMVC_POST = 3;

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


    protected $_controller_layout = null;
    protected $_email_layout = 'templates/emailBase';

    /**
     *
     * @var EmailMerge_Template
     */
    protected $_template = null;

    protected $_sender_email = null;

    protected $_sender_name = null;

    /**
     * @var array A route and parameters that will be redirected to once the merge is complete
     */
    protected $_on_complete = null;

    /**
     * @var array A configuration array, loaded initially from config and then persisted
     */
    protected $_config = array();

    protected $_merged_mails = array();

    /**
     * The markdown parser
     * @var Markdown_Parser
     */
    protected $_markdown = null;

    /**
     * Reloads an existing merge, or creates a new one
     * @param string $merge_id The UUID of the merge to load, or null to create new
     * @param array $config A set of config values to merge with existing config
     * @return EmailMerge
     * @throws InvalidArgumentException if merge_id is not valid
     */
    public static function factory($merge_id = null, $config = array())
    {
        // Merge the passed config with the config file settings
        $config = Arr::merge($config, Kohana::config('emailmerge.instance'));

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
     * Gets (and instantiates, if required) the template
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

    public function template_changed()
    {
        $this->_merged_mails = array();
    }

    /**
     * Converts a merge UUID to a path to a persistence file
     * @param string $merge_id The UUID of the merge
     * @param array $config A set of EmailMerge config settings
     * @return string The expected path to the merge file
     */
    public static function persistence_file($merge_id)
    {
        $base = Kohana::config('emailmerge.persistence_path');
        $path = $base . str_replace('-', '/', $merge_id) . ".merge";
        return $path;
    }

    public function garbage_collect()
    {
        $base = Kohana::config('emailmerge.persistence_path');
        $iterator = new DirectoryIterator($base);
        $this->_gc_find_empty($iterator, $base, $empty_paths);
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
        if ($problems)
        {
            Kohana::$log->add(Kohana::ERROR, "Couldn't unlink some paths: " . implode(', ', $problems));
        }
    }

    protected function _gc_find_empty(DirectoryIterator $iterator, $base_path, &$empty_paths)
    {
        $empty = true;
        foreach ($iterator as $file)
        {
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
     * Constructs a new instance and sets a unique id for later use
     * @param array $config An array of config settings
     */
    public function __construct($config = array())
    {
        $this->_config = $config;
        $this->_merge_id = UUID::v4();
        $this->garbage_collect();
    }

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
     * Loads data and sets the email and real name fields that will be used for sending
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
     * Returns the current data
     * @return array
     */
    public function get_data()
    {
        return $this->_data;
    }

    /**
     * Returns the email address from a given data array
     * @param array $data
     * @return string
     */
    public function get_email_address($data)
    {
        return $data[$this->_email_field];
    }

    /**
     * Returns the merge ID
     * @return string
     */
    public function id()
    {
        return $this->_merge_id;
    }

    /**
     * Setter/Getter for the template namespace. The namespace cannot be set by
     * the user at runtime, and can therefore be used to limit the choices of template
     * available.
     *
     * @param string $namespace
     * @return string If used as getter ($namespace left blank)
     * @return EmailMerge If used as setter
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

    public function sender_email($email = null)
    {
        if ($email === null)
        {
            return $this->_sender_email;
        }
        $this->_sender_email = $email;
        return $this;
    }

    public function sender_name($name = null)
    {
        if ($name === null)
        {
            return $this->_sender_name;
        }
        $this->_sender_name = $name;
        return $this;
    }

    /**
     * Persists the merge to permanent storage at various stages of the wizard
     * @return EmailMerge
     */
    public function persist()
    {
        $file = self::persistence_file($this->_merge_id);
        $path = dirname($file);
        if ( ! file_exists($path))
        {
            mkdir($path, 0777, TRUE);
        }
        file_put_contents($file, serialize($this));
        return $this;
    }

    /**
     * Disposes of the storage for this merge
     */
    public function dispose()
    {
        $file = self::persistence_file($this->_merge_id);
        unlink($file);
        $this->garbage_collect();
    }

    public function build_merge($force = false)
    {
        if ($this->_merged_mails AND ! $force)
        {
            return false;
        }

        $markdown = $this->get_markdown();

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
    }

    public function get_mails()
    {
        if ( ! $this->_merged_mails)
        {
            $this->build_merge();
        }
        return $this->_merged_mails;
    }

    public function replace_mails($mails)
    {
        $this->_merged_mails = array();
        $markdown = $this->get_markdown();

        foreach ($mails as $mail)
        {
            $mail['html_body'] = $markdown->transform($mail['body']);
            $this->_merged_mails[] = $mail;
        }
        return $this;
    }

    /**
     * Generates a Swift_Mail ready for sending from a set of mail_data
     * @param array $mail_data
     * @return Swift_Message
     */
    public function swift_mail($mail_data)
    {
        /* @var $mail Swift_Message */
        $mail = Swift_Message::newInstance();

        $real_name = $mail_data['name'] == $mail_data['email'] ? null : $mail_data['name'];
        $mail->setTo($mail_data['email'], $real_name);
        $mail->setSubject($mail_data['subject']);
        $mail->setFrom($this->_sender_email, $this->_sender_name);
        $mail->setBcc($this->_sender_email);

        $headers = $mail->getHeaders();
        $headers->addTextHeader('X-EIBF-Staff-'.$this->_template_namespace, 'automail');

        if ( ! $this->_email_layout instanceof View)
        {
            $this->_email_layout = View::factory($this->_email_layout);
        }

        $this->_email_layout->set('bodyText',$mail_data['html_body']);

        $richMessage = $this->_email_layout->render();
        $mail->setBody(preg_replace('/[ \t]+/', ' ', strip_tags($richMessage)));
        $mail->addPart($richMessage,'text/html');
        return $mail;

    }

    /**
     * Tries to load the Markdown parser, first from registered modules and then
     * from userguide directly if not found
     * @return Markdown_Parser
     */
    protected function get_markdown()
    {
        static $markdown = null;

        if ($markdown)
        {
            return $markdown;
        }

        $markdown = Kohana::find_file('vendor', 'markdown/markdown');
        if ($markdown)
        {
            require_once($markdown);
        }
        else
        {
            require_once(MODPATH . 'userguide/vendor/markdown');
        }


        $markdown = new Markdown_Parser();
        $markdown->no_markup = true;
        return $markdown;
    }

    /**
     * Sets an on complete handler to call once the mail merge is done
     * @param string $uri
     * @param int $method
     * @param array $data
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
     * Executes the on complete handler with the given main request. NOTE that the
     * completion handler is responsible for disposing of the merge!
     */
    public function merge_complete(Request $request)
    {
        $action = $this->_on_complete ? $this->_on_complete
                                      : array('uri'=>Route::get('emailmerge')
                                                ->uri(array('action'=>'complete',
                                                            'merge_id'=>$this->_merge_id)),
                                              'method'=>self::COMPLETION_REDIRECT,
                                              'data'=>array('merge_id'=>$this->_merge_id));
        extract($action);

        switch ($method)
        {
            case self::COMPLETION_REDIRECT:
                $uri = $uri . "?" . http_build_query($data);
                $request->redirect($uri);
            break;

            case self::COMPLETION_HMVC_GET:
            //@todo: Can't do POST with KO3.0
            case self::COMPLETION_HMVC_POST:
                $_GET = $data;
                $sub_request = Request::factory($uri)
                                ->execute();
                $request->response = $sub_request->response;
            return;
            default:
                throw new InvalidArgumentException("Invalid method $method for on-complete callback");
        }
    }

    /**
     * Gets the URI to one of the actions
     * @param string $action
     * @return string
     */
    public function action_uri($action)
    {
        return Route::get('emailmerge')
                ->uri(array('merge_id'=>$this->_merge_id, 'action'=>$action));
    }


} // End AndrewC_EmailMerge
