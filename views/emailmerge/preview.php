<?php
defined('SYSPATH') or die('No direct script access.');
/* @var $merge EmailMerge */
$mails = $merge->get_mails();?>
<h1>Preview Email Merge</h1>
<?php foreach ($mails as $mail):?>
<div class="mail">
    <h3><?=HTML::chars($mail['subject'])?></h3>
    <p>To: <?=HTML::chars($mail['email'])?>
        <?php if ($mail['name']!=$mail['email']):?>
            (<?=HTML::chars($mail['name'])?>)
        <?php endif;?></p>
    <p><?=nl2br(HTML::chars($mail['body']))?></p>
</div>
<?php endforeach;?>
<?=Form::open(Route::get('emailmerge')
                ->uri(array(
                    'merge_id'=>$merge->id(),
                    'action'=>'process')));?>
<p class="submitstrip">
<?=Form::submit('customise','Customise Merge')?>
<?=Form::submit('edit_mails','Edit Individual Emails')?>
<?=Form::submit('send_merge','Send Merge')?></p>
<?=Form::close();?>