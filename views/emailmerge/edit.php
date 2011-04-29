<?php
defined('SYSPATH') or die('No direct script access.');
/* @var $merge EmailMerge */
$mails = $merge->get_mails();?>
<h1>Edit Messages</h1>
<?=Form::open(Route::get('emailmerge')
                ->uri(array(
                    'merge_id'=>$merge->id(),
                    'action'=>'process')));?>
<?=Form::hidden('formtype','edit_mails');?>
<?php foreach ($mails as $index=>$mail):?>
<fieldset>
    <legend>To: <?=HTML::chars($mail['email'])?>
        <?php if ($mail['name']!=$mail['email']):?>
            (<?=HTML::chars($mail['name'])?>)
        <?php endif;?></legend>
        <?=Form::hidden("mail[$index][email]",$mail['email'])?>
        <?=Form::hidden("mail[$index][name]",$mail['name'])?>
        <label class="modelblock">
            <span class="caption">Subject</span>
            <?=Form::input("mail[$index][subject]",$mail['subject'])?>
        </label>
        <label class="modelblock">
            <span class="caption">Message</span>
            <?=Form::textarea("mail[$index][body]",$mail['body'])?>
        </label>
</fieldset>
<?php endforeach;?>

<p class="submitstrip">
<?=Form::submit('customise','Customise Merge')?>
<?=Form::submit('preview_merge','Preview Merge')?>
<?=Form::submit('send_merge','Send Merge')?></p>
<?=Form::close();?>