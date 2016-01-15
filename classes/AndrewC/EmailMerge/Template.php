<?php
defined('SYSPATH') or die('No direct script access.');
/**
 * Class to handle loading, saving and managing templates for an [EmailMerge]
 *
 * @package    EmailMerge
 * @category   Core
 * @author     Andrew Coulton
 * @copyright  (c) 2011 Andrew Coulton
 * @license    http://kohanaphp.com/license
 */
class AndrewC_EmailMerge_Template
{
   /**
    * @var string Email subject template
    */
    protected $_subject = null;

    /**
     * @var string Email body template
     */
    protected $_body = null;
    protected $_name = 'default';
    protected $_namespace = 'generic';
    protected $_loaded = false;
    protected static $_client_store = null;

    /**
     *
     * @var EmailMerge
     */
    protected $_merge = null;

    public static function factory($merge)
    {
        return new EmailMerge_Template($merge);
    }

    public function __construct($merge)
    {
        $this->_merge = $merge;
    }

    public function body($body = null)
    {
        if ($body === null)
        {
            if ( ! $this->_loaded)
            {
                $this->load();
            }
            return $this->_body;
        }

        if ($this->_body != $body)
        {
            $this->_body = $body;
            $this->_merge->template_changed();
        }
        return $this;
    }

    public function subject($subject = null)
    {
        if ($subject === null)
        {
            if ( ! $this->_loaded)
            {
                $this->load();
            }
            return $this->_subject;
        }
        if ($this->_subject != $subject)
        {
            $this->_subject = $subject;
            $this->_merge->template_changed();
        }
        return $this;
    }

    public function name()
    {
        return $this->_name;
    }

    public function set_namespace($namespace)
    {
        if ($this->_loaded)
        {
            throw new BadMethodCallException("Can't set namespace once loaded");
        }
        $this->_namespace = $namespace;
        // Load the default template for the namespace to reinitialise
        $this->load();
    }

    public function get_namespace()
    {
        return $this->_namespace;
    }

    protected function _paths()
    {
        static $paths = null;
        if ($paths)
        {
            return $paths;
        }

        if ( ! self::$_client_store)
        {
            self::$_client_store = Kohana::$config->load('emailmerge.client_template_path');
        }

        // The top path is the client's template store
        $paths = array(self::$_client_store . $this->_namespace . '/');

        // Then the Kohana paths
        foreach (Kohana::include_paths() as $path)
        {
            $paths[] = $path . 'emailmerge/templates/' . $this->_namespace . '/';
        }
        return $paths;
    }

    public function available_templates()
    {
        $files = Kohana::list_files(null, $this->_paths());
        $templates = array(null=>'Default');
        foreach ($files as $file)
        {
            $file = basename($file, '.php');
            $templates[$file] = str_replace('-', ' ', ucwords($file));
        }
        return $templates;
    }

    public function load($name=null)
    {
        // Set the template name if loading a new template
        if ($name !== null)
        {
            $this->_name = URL::title($name,'-',true);
        }

        // Search for a template file in our path tree
        $data = null;
        foreach ($this->_paths() as $path)
        {
            $file = $path . $this->_name . '.php';

            // Found a template - load it
            if (is_file($file))
            {
                $data = include($file);
                $this->_subject = $data['subject'];
                $this->_body = $data['body'];
                break;
            }
        }

        // Nothing found - reset to empty
        if ($data === null)
        {
            $this->_subject = null;
            $this->_body = null;
        }

        // Mark loaded, tell the merge the template has changed
        $this->_loaded = true;
        $this->_merge->template_changed();
    }

    public function save($name = null)
    {
        // Set the name if provided
        if ($name !== null)
        {
            $this->_name = URL::title($name,'-',true);
        }

        // Store it in the top level of our paths
        $path = Arr::get($this->_paths(),0,'!No Paths Found!');

        if ( ! is_dir($path))
        {
            mkdir($path, 0777, true);
        }

        // Create a formatted php file
        $template = "<?php\r\n return " . var_export(array(
                            'subject' => $this->_subject,
                            'body'    => $this->_body),
                        true) . ";";
        file_put_contents($path . $this->_name . '.php', $template);
        return $this;
    }

    public function merge_mail($data)
    {
        if ( ! $this->_loaded)
        {
            $this->load();
        }
        return array('body' => $this->_merge_text($this->_body,$data),
                     'subject' => $this->_merge_text($this->_subject, $data));
    }

    protected function _merge_text($template, $data)
    {
        if (preg_match_all('/{{[a-zA-Z0-9:_]+}}/', $template,$matches))
        {
            foreach (Arr::get($matches,0) as $marker)
            {
                $field = trim($marker, '{}');
                $template = str_replace($marker, HTML::chars($data[$field]), $template);
            }
        }
        return $template;
    }

}
