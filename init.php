<?php
defined('SYSPATH') or die('No direct script access.');

Route::set('emailmerge','emailmerge/<merge_id>/<action>', array('merge_id'=>'[0-9a-f\-]+'))
    ->defaults(array('controller'=>'emailmerge'));