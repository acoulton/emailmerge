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

    protected function get_storage_path()
    {

        if ( ! $base_path = Kohana::$config->load('emailmerge.client_template_path')) {
            throw new \InvalidArgumentException('No default custom template path is configured');
        };
        $path      = rtrim($base_path, '/').'/'.$this->_namespace.'/';
        return $path;
    }

    public function available_templates()
    {
        $templates = array();
        foreach (glob($this->get_storage_path().'/*.json') as $file) {
            $file = basename($file, '.json');
            $templates[$file] = str_replace('-', ' ', ucwords($file));
        }

        if ( ! $templates) {
            $templates[null] = 'Default';
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

        $file = $this->get_storage_path().'/'.$this->_name.'.json';
        if (is_file($file)) {
            if ( ! $data = json_decode(file_get_contents($file), TRUE)) {
                throw new \UnexpectedValueException('Corrupt JSON template data in '.$file);
            }

            $this->_subject = $data['subject'];
            $this->_body    = $data['body'];
        } else {
            $this->_subject = NULL;
            $this->_body    = NULL;
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

        $path = $this->get_storage_path();
        if ( ! (is_dir($path) OR mkdir($path, 0777, TRUE))) {
            throw new \RuntimeException('Could not create template storage path in '.$path);
        }

        $file = $path.'/'.$this->_name.'.json';
        $data = array(
            'subject' => $this->_subject,
            'body'    => $this->_body
        );

        if ( ! file_put_contents($file, json_encode($data))) {
            throw new \RuntimeException('Could not write template to file '.$file);
        }

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
