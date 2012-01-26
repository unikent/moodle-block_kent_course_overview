<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Course overview block
 *
 * Currently, just a copy-and-paste from the old My Moodle.
 *
 * @package   blocks
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/blocks/kent_course_overview/lib.php');
require_once($CFG->dirroot.'/lib/weblib.php');
require_once($CFG->dirroot . '/lib/formslib.php');

class block_kent_course_overview extends block_base {
    /**
     * block initializations
     */
    public function init() {
        $this->title   = get_string('pluginname', 'block_kent_course_overview');
    }

    /**
     * block contents
     *
     * @return object
     */
    public function get_content() {
        global $USER, $CFG, $OUTPUT, $DB;
        if($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $context = get_context_instance(CONTEXT_SYSTEM);

        //Firstly... lets check if the user is an admin, and direct accordingly.

        $installed = $DB->get_records('config_plugins', array('plugin'=>'local_rollover'), '', 'plugin');
        //Fetch the rollover lib if its installed
        if($installed){
            require_once($CFG->dirroot.'/local/rollover/lib.php');
        }
        
        if (has_capability('moodle/site:config', $context) && $installed != FALSE){

            $rollover_admin_path = "$CFG->wwwroot/local/rollover/index.php";

            $this->content->text .= $OUTPUT->box_start('generalbox rollover_admin_notification');
            $this->content->text .= '<p>'.get_string('admin_course_text', 'block_kent_course_overview').'</p>';
            $this->content->text .= '<p>&raquo; '.'<a href="'.$rollover_admin_path.'">Rollover admin page</a></p>';
            $this->content->text .= $OUTPUT->box_end();
            $this->content->text .= '<br/>';

        }

        // limits the number of courses showing up
        $courses_limit = 21;
        // FIXME: this should be a block setting, rather than a global setting
        if (isset($CFG->mycoursesperpage)) {
            $courses_limit = $CFG->mycoursesperpage;
        }

        $morecourses = false;
        if ($courses_limit > 0) {
            $courses_limit = $courses_limit + 1;
        }

        $courses = enrol_get_my_courses('id, shortname, modinfo, summary', 'visible DESC,sortorder ASC', $courses_limit);
        $site = get_site();
        $course = $site; //just in case we need the old global $course hack

        if (array_key_exists($site->id,$courses)) {
            unset($courses[$site->id]);
        }

        foreach ($courses as $c) {
            if (isset($USER->lastcourseaccess[$c->id])) {
                $courses[$c->id]->lastaccess = $USER->lastcourseaccess[$c->id];
            } else {
                $courses[$c->id]->lastaccess = 0;
            }
        }

        //Provide link back to Current Moodle, if I am the archive moodle!
        if (isset($CFG->archive_moodle) && ($CFG->archive_moodle == TRUE) && kent_is_archive_moodle()){
            $this->content->text .= kent_archive_moodle_link();
        }

        if (empty($courses)) {
            $this->content->text .= get_string('nocourses', 'block_kent_course_overview');
        } else {
            $this->content->text .= kent_course_print_overview($courses);
        }

        //Provide link back to Archive Moodle if switched on
        if (isset($CFG->archive_moodle) && ($CFG->archive_moodle == TRUE) && !kent_is_archive_moodle()){
            $this->content->text .= kent_archive_moodle_link();
        }



        // if more than 20 courses
        if ($morecourses) {
            $this->content->text .= '<br />...';
        }

        return $this->content;
    }

    /**
     * allow the block to have a configuration page
     *
     * @return boolean
     */
    public function has_config() {
        return false;
    }

    /**
     * locations where block can be displayed
     *
     * @return array
     */
    public function applicable_formats() {
        return array('my-index'=>true);
    }
}
?>
