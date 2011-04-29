<?php
defined('SYSPATH') or die('No direct script access.');
/* @var $merge EmailMerge */
$dataset = $merge->get_data();?>
<h1>Customise Email Merge</h1>
<p>Type: <?=HTML::chars($merge->template_namespace())?></p>
<p><?=count($dataset)?> Recipients:</p>
<ul>
    <?php foreach ($dataset as $data):?>
        <li><?=HTML::chars($merge->get_email_address($data))?></li>
    <?php endforeach;?>
</ul>
<?=Form::open(Route::get('emailmerge')
                ->uri(array(
                    'merge_id'=>$merge->id(),
                    'action'=>'process')));?>
<?=Form::hidden('formtype','customise');?>
<fieldset>
    <legend>Setup</legend>
    <h3>Template</h3>
    <label class="modelblock">

    </label>
    <h3>Sender</h3>
    <label class="modelblock">
        <span class="caption">Sender Email:</span>
        <?=Form::input('sender_email',$merge->sender_email())?>
    </label>
    <label class="modelblock">
        <span class="caption">Sender Name:</span>
        <?=Form::input('sender_name',$merge->sender_name())?>
    </label>
</fieldset>
<fieldset>
    <legend>Email Content</legend>
    <label class="modelblock">
        <span class="caption">Subject:</span>
        <?=Form::input('template_subject',$merge->template()->subject())?>
    </label>

<div class="left-hint">
    <h3>Available Fields</h3>
    <ul>
        <?php foreach ($data as $key=>$value):?>
            <li>{{<?=HTML::chars($key)?>}}</li>
        <?php endforeach;?>
    </ul>
</div>
<div style="margin-left: 15em; padding: 1em;">
        <?=Form::textarea('template_body',
                          $merge->template()->body(),
                          array('rows'=>15,
                                'cols'=>40,
                                'style' =>'width: 100%;'))?>
</div>
<div class="clear"></div>
</fieldset>
<p class="submitstrip">
<?=Form::submit('preview_merge','Preview Merge')?>
<?=Form::submit('edit_mails','Edit Individual Emails')?>
<?=Form::submit('send_merge','Send Merge')?></p>
<?=Form::close();?>