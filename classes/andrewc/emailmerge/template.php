<?php
defined('SYSPATH') or die('No direct script access.');
class AndrewC_EmailMerge_Template
{
    protected $_subject = null;
    protected $_body = null;
    protected $_name = null;
    /**
     *
     * @var EmailMerge
     */
    protected $_merge = null;

    public static function factory($namespace, $name, $merge)
    {
        return new EmailMerge_Template($namespace, $name, $merge);
    }

    public function __construct($namespace, $name, $merge)
    {
        $this->_merge = $merge;
        $this->_namespace = $namespace ? $namespace : 'generic';
        $this->load($name);
    }

    public function body($body = null)
    {
        if ($body === null)
        {
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

    public function load($name)
    {
        if ($name === null)
        {
            $name = 'default';
        }

        $path = "emailmerge/templates/" . $this->_namespace;

        // Look in the user template store
        // Then in the application template store
        $file = Kohana::find_file($path, $name);
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