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

    public function merge_mail($data)
    {
        $subject = $this->_subject;
        $body = $this->_body;
        if (preg_match_all('/{{[a-zA-Z0-9:_]+}}/', $subject,$matches))
        {
            foreach (Arr::get($matches,0) as $marker)
            {
                $field = trim($marker, '{}');
                $subject = str_replace($marker, $data[$field], $subject);
            }
        }

        if (preg_match_all('/{{[a-zA-Z0-9:_]+}}/', $body,$matches))
        {
            foreach (Arr::get($matches,0) as $marker)
            {
                $field = trim($marker, '{}');
                $body = str_replace($marker, $data[$field], $body);
            }
        };
        return array('body'=>$body,'subject'=>$subject);
    }
}