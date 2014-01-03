<?php

defined('MOODLE_INTERNAL') || die;

$settings->add(new admin_setting_configcheckbox('block_kent_course_overview/clearmodule', 
	get_string('clearmoduleoption', 'block_kent_course_overview'), get_string('configclearmoduleoption', 'block_kent_course_overview'), 0));

$settings->add(new admin_setting_configcheckbox('block_kent_course_overview/showmissingmoduleslink', 
	get_string('showmissingmoduleslinkoption', 'block_kent_course_overview'), get_string('configshowmissingmoduleslinkoption', 'block_kent_course_overview'), 0));
