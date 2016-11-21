<?php
defined('SYSPATH') or die('No direct script access.');

/**
 * @package EmailMerge
 */
abstract class AndrewC_Controller_Emailmerge extends Controller_Template
{
    /**
     * @var EmailMerge The merge we're dealing with
     */
    protected $merge = null;

    public function before()
    {
        $this->merge = EmailMerge::factory($this->request->param('merge_id'));
        $this->template = $this->merge->controller_layout();
        parent::before();
    }

    public function action_process()
    {
        if ($this->request->method() !== \Request::POST)
        {
            throw new HTTP_Exception_400('Can only do Process with a POST!');
        }

        // Update the fields
        switch($this->request->post('formtype'))
        {
            case 'customise':
                if ($this->request->post('load_template'))
                {
                    $new_template = $this->request->post('template_file');
                    $this->merge->template()->load($new_template);
                    break;
                }
                $data = Arr::extract($this->request->post(), array('sender_email','sender_name',
                            'template_subject','template_body', 'template_changes',
                            'new_template_name'));
                $this->merge->sender_email($data['sender_email']);
                $this->merge->sender_name($data['sender_name']);
                $this->merge->template()->subject($data['template_subject']);
                $this->merge->template()->body($data['template_body']);

                switch ($data['template_changes'])
                {
                    case 'this_template':
                        $this->merge->template()->save();
                    break;
                    case 'new_template':
                        $this->merge->template()->save($data['new_template_name']);
                    break;
                }
            break;

            case 'edit_mails':
                $this->merge->replace_mails($this->request->post('mail'));
                break;
            break;
        }

        $this->merge->persist();

        if ($this->request->post('preview_merge'))
        {
            $action = 'preview';
        }
        elseif($this->request->post('edit_mails'))
        {
            $action = 'edit';
        }
        elseif($this->request->post('send_merge'))
        {
           $action = 'send';
        }
        else
        {
            $action = 'customise';
        }

        $this->redirect($this->merge->action_uri($action));
    }

    public function action_customise()
    {
        $this->template->body = View::factory('emailmerge/customise')
                                    ->set('merge',$this->merge);
    }

    public function action_preview()
    {
        // Build the merge
        $this->merge->build_merge();
        // Persist
        $this->merge->persist();
        // Preview
        $this->template->body = View::factory('emailmerge/preview')
                                ->set('merge',$this->merge);
    }

    public function action_edit()
    {

        $this->template->body = View::factory('emailmerge/edit')
                                ->set('merge',$this->merge);
    }

    public function action_send()
    {
        set_time_limit(0);
        ignore_user_abort(true);

        $mails    = $this->merge->get_mails();
        $mailer   = $this->get_mailer();
        $failures = FALSE;
        $ok       = [];
        foreach ($mails as $key=>$mail)
        {
            if ($mailer->send($this->merge->swift_mail($mail)))
            {
                $mails[$key]['sent'] = true;
                $ok[] = $mail;
            }
            else
            {
                $mails[$key]['sent'] = false;
                $failures[] = $mail;
            }
        }


        if ( ! $failures)
        {
            $this->merge->persist();
            $this->auto_render = false;
            return $this->merge->merge_complete($this->request, $this->response);
        }

        // Replace with just the failures
        //@todo: Ignore mails that have already been sent
        $this->merge->persist();

        $this->template->body = View::factory('emailmerge/send_errors')
                                ->set('merge',$this->merge)
                                ->set('ok',$ok);
    }

    public function action_complete()
    {
        $this->template->body = View::factory('emailmerge/complete')
                                ->set('merge',$this->merge);
        $this->merge->dispose();
    }

    /**
     * @return Swift_Mailer
     */
    abstract protected function get_mailer();
}
