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
 *              ->on_complete('default',array('controller'=>''));
 *
 *     // Show a form allowing the user to customise the merge and proceed to
 *     // preview, edit, and send
 *     $this->request->response = $merge->customise();
 *
 */
class AndrewC_EmailMerge
{
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
     * @var string By default, the template to use is user configurable, but the namespace is not
     */
    protected $_template_namespace = null;

    protected $_template_name = null;


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
        if ( ! $this->_template)
        {
            // Try to load
            $this->_template = EmailMerge_Template::factory($this->_template_namespace, $this->_template, $this);
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

    /**
     * Constructs a new instance and sets a unique id for later use
     * @param array $config An array of config settings
     */
    public function __construct($config = array())
    {
        $this->_config = $config;
        $this->_merge_id = UUID::v4();
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
            return $this->_template_namespace;
        }

        if ($this->_template)
        {
            throw new BadMethodCallException("Cannot set namespace once template is created");
        }

        $this->_template_namespace = $namespace;
        return $this;
    }

    /**
     * Setter/Getter for the template name. By default, the user can select a
     * different template at runtime at the customise() stage.
     *
     * @param string $name
     * @return string If used as getter ($namespace left blank)
     * @return EmailMerge If used as setter
     */
    public function template_name($name = null)
    {
        if ($name === null)
        {
            return $this->_template_name;
        }

        $this->_template_name = $name;

        // If a template is already loaded, reset it
        if ($this->_template)
        {
            $this->_template = EmailMerge::factory($this->_template_namespace,$name, $this);
            $this->_merged_mails = array();
        }
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
    }

    /**
     * Disposes of the storage for this merge
     */
    public function dispose()
    {
        $file = self::persistence_file($this->_merge_id);
        unlink($file);
    }

    public function customise()
    {
        $this->persist();
        return View::factory('emailmerge/customise')
                    ->set('merge',$this);
    }

    public function build_merge($force = false)
    {
        if ($this->_merged_mails AND ! $force)
        {
            return false;
        }

        $this->_merged_mails = array();
        foreach ($this->_data as $email)
        {
            $mail['email'] = Arr::get($email,$this->_email_field);
            $mail['name'] = Arr::get($email, $this->_email_name_field, $mail['email']);
            $mail = $mail + $this->_template->merge_mail($email);
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
        $this->_merged_mails = $mails;
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

        $this->_email_layout->set('bodyText',"<p>" . nl2br(HTML::chars($mail_data['body'])) . "</p>");

        $richMessage = $this->_email_layout->render();
        $mail->setBody(preg_replace('/[ \t]+/', ' ', strip_tags($richMessage)));
        $mail->addPart($richMessage,'text/html');
        return $mail;

    }

} // End AndrewC_EmailMerge
