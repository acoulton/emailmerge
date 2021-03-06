<?php
defined('SYSPATH') or die('No direct script access.');
/* @var $merge EmailMerge */
$dataset = $merge->get_data();?>
<h1>Customise Email Merge</h1>
<p><?=count($dataset)?> Recipients:</p>
<ul>
    <?php foreach ($dataset as $data):?>
        <li><?=HTML::chars($merge->get_email_address($data))?></li>
    <?php endforeach;?>
</ul>
<?=Form::open($merge->action_uri(EmailMerge::ACTION_PROCESS));?>
<?=Form::hidden('formtype','customise');?>
<fieldset>
    <legend>Setup</legend>
    <h3>Template</h3>
    <label>
        <span class="caption">Available templates:</span>
        <?=Form::select('template_file',$merge->template()->available_templates(), $merge->template()->name());?>
    </label>
    <p><?=Form::submit('load_template',"Load new template");?></p>
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
            <li><a href="#" class="insert-param">{{<?=HTML::chars($key)?>}}</a></li>
        <?php endforeach;?>
    </ul>
    <h3>Formatting</h3>
    <ul>
        <li>[link text](link address)</li>
        <li>**bold**</li>
    </ul>
</div>
<div style="margin-left: 15em; padding: 1em;">
        <?=Form::textarea('template_body',
                          $merge->template()->body(),
                          array('rows'=>15,
                                'cols'=>40,
                                'style' =>'width: 100%;',
                                'id'=>'body-text'))?>
</div>
<div class="clear"></div>
<h3>Template changes</h3>
    <div style="margin-left: 15em; padding:1em;">
        <label class="modelblock">
                <?=Form::radio('template_changes', 'no_save', true, array('class'=>'check'));?> Don't save changes.</label>
        <label class="modelblock">
                <?=Form::radio('template_changes', 'this_template', false, array('class'=>'check'));?> Save changes to the current template.</label>
        <label class="modelblock" style="display: inline-block; width:auto;">
                <?=Form::radio('template_changes', 'new_template', false, array('class'=>'check'));?> Create a new template</label>
        <label class="modelblock" style="display: inline-block; width:auto;">
            - name
                <?=Form::input('new_template_name')?></label>
    </div>
</fieldset>
<p class="submitstrip">
<?=Form::submit('preview_merge','Preview Merge')?>
<?=Form::submit('edit_mails','Edit Individual Emails')?>
<?=Form::submit('send_merge','Send Merge')?></p>
<?=Form::close();?>
<script type="text/javascript">
    var body_text = $('body-text');

    function insertAtCursor(myField, myValue) {
        //IE support
        if (document.selection) {
            myField.focus();
            sel = document.selection.createRange();
            sel.text = myValue;
        }
        //MOZILLA/NETSCAPE support
        else if (myField.selectionStart || myField.selectionStart == '0') {
            var startPos = myField.selectionStart;
            var endPos = myField.selectionEnd;
            myField.value = myField.value.substring(0, startPos)
                + myValue
                + myField.value.substring(endPos, myField.value.length);
            myField.selectionStart = startPos + myValue.length;
            myField.selectionEnd = myField.selectionStart;
        } else {
            myField.value += myValue;
        }
    }

    $(document).observe('dom:loaded', function()
        {
            param_links = $$('a.insert-param');
            param_links.each(function(link)
            {
                link.observe('click', function(event)
                {
                    event.stop();
                    insertAtCursor(body_text, this.innerHTML);
                    body_text.focus();
                });
            });
        }
    );

</script>