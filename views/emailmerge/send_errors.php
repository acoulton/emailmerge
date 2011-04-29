<?php
defined('SYSPATH') or die('No direct script access.');
/* @var $merge EmailMerge */
?>
<div class="formerror"><p>
        There were problems sending some of the emails.</p></div>
<h2>Sent OK</h2>
<ul>
    <?php foreach ($ok as $mail):?>
        <li><?=HTML::chars($mail['email'])?></li>
    <?php endforeach;?>
</ul>

<h2>Failures</h2>
<ul>
    <?php foreach ($merge->get_mails() as $mail):?>
        <li><?=HTML::chars($mail['email'])?></li>
    <?php endforeach;?>
</ul>
