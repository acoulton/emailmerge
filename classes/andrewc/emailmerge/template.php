<?php
defined('SYSPATH') or die('No direct script access.');
class AndrewC_EmailMerge_Template
{
    protected $_subject = null;
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
    }

    public function get_namespace()
    {
        return $this->_namespace;
    }

    public function available_files()
    {
        $paths = Kohana::include_paths();
        // Add the instance store to this path list

        return Kohana::list_files('emailmerge/templates', $paths);
    }

    public function load($name=null)
    {
        if ($name !== null)
        {
            $this->_name = $name;
        }

        $path = "emailmerge/templates/" . $this->_namespace;

        // Look in the user template store
        // Then in the application template store
        $file = Kohana::find_file($path, $this->_name);
        if (file_exists($file))
        {
            $data = include($file);
            $this->_subject = $data['subject'];
            $this->_body = $data['body'];
        }
        else
        {
            $this->_subject = null;
            $this->_body = null;
        }
        $this->_loaded = true;
        $this->_merge->template_changed();
    }

    public function save($name)
    {
        // Store it in the user template store
        // Or the application template store
        // Write it out as if it was a config file
        // Change the internal template name
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