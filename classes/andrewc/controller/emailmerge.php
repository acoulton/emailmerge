<?php
defined('SYSPATH') or die('No direct script access.');
abstract class AndrewC_Controller_EmailMerge extends Controller_Template
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
        if ( ! $_POST)
        {
            throw new BadMethodCallException('Can only do Process with a POST!');
        }

        // Update the fields
        switch(Arr::get($_POST,'formtype'))
        {
            case 'customise':
                $data = Arr::extract($_POST,array('sender_email','sender_name','template_subject','template_body'));
                $this->merge->sender_email($data['sender_email']);
                $this->merge->sender_name($data['sender_email']);
                $this->merge->template()->subject($data['template_subject']);
                $this->merge->template()->body($data['template_body']);
            break;

            case 'edit_mails':
                $this->merge->replace_mails(Arr::get($_POST,'mail'));
                break;
            break;
        }

        $this->merge->persist();

        if (isset($_POST['preview_merge']))
        {
            return $this->preview();
        }
        elseif(isset($_POST['edit_mails']))
        {
            return $this->edit();
        }
        elseif(isset($_POST['send_merge']))
        {
           return  $this->send();
        }

        $this->template->body = $this->merge->customise();
    }

    public function preview()
    {
        // Build the merge
        $this->merge->build_merge();
        // Persist
        $this->merge->persist();
        // Preview
        $this->template->body = View::factory('emailmerge/preview')
                                ->set('merge',$this->merge);
    }

    public function edit()
    {

        $this->template->body = View::factory('emailmerge/edit')
                                ->set('merge',$this->merge);
    }

    public function send()
    {
        set_time_limit(0);
        ignore_user_abort(true);

        $mails = $this->merge->get_mails();

        $mailer = Email::connect();
        /* @var $mailer Swift_Mailer */
        $mailer->registerPlugin(new Swift_Plugins_AntiFloodPlugin(15, 30));
        $failures = false;
        $ok = array();
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
            $this->request->redirect(Route::get('emailmerge')
                                        ->uri(array('action'=>'complete',
                                                    'merge_id'=>$this->merge->id())));
        }

        // Replace with just the failures
        $this->merge->replace_mails($failures);
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
}