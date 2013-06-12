<?php

defined('MOODLE_INTERNAL') || die;

$settings->add(new admin_setting_configcheckbox('block_kent_course_overview_clearmodule', 
	get_string('clearmoduleoption', 'block_kent_course_overview'), get_string('configclearmoduleoption', 'block_kent_course_overview'), 0));