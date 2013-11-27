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
        $this->title = get_string('pluginname', 'block_kent_course_overview');
    }

    /**
     * block contents
     *
     * @return object
     */
    public function get_content() {
        global $USER, $CFG, $OUTPUT, $DB, $PAGE;

        if ($this->content !== NULL) {
            return $this->content;
        }

        // Get hide/show params (for quick visbility changes)
        $hide = optional_param('hide', 0, PARAM_INT);
        $show = optional_param('show', 0, PARAM_INT);
        $page = optional_param('page', 0, PARAM_INT);
        $perpage = optional_param('perpage', 20, PARAM_INT);

        // Generate page url for page actions from current params
        $params = array();
        if ($page) {
            $params['page'] = $page;
        }
        if ($perpage) {
            $params['perpage'] = $perpage;
        }
        $baseactionurl = new moodle_url($PAGE->URL, $params);

        // Process show/hide if there is one
        if (!empty($hide) or !empty($show)) {
            if (!empty($hide)) {
                $course = $DB->get_record('course', array('id' => $hide));
                $visible = 0;
            } else {
                $course = $DB->get_record('course', array('id' => $show));
                $visible = 1;
            }

            if ($course) {
                $coursecontext = context_course::instance($course->id);
                require_capability('moodle/course:visibility', $coursecontext);
                // Set the visibility of the course. we set the old flag when user manually changes visibility of course.
                $DB->update_record('course', array('id' => $course->id, 'visible' => $visible, 'visibleold' => $visible, 'timemodified' => time()));
            }
        }

        // Fetch the Categories that user is enrolled in 
        $categories = kent_enrol_get_my_categories('*','');
        //echo var_dump($categories);
        $offset=isset($categories['totalcategories'])?$categories['totalcategories']:0;

        // Calculate courses to add after category records
        // TODO: Clean up so if more that perpage categories ($offset) are available then pagination works?

        if ($offset>$perpage && $page==0) {
            $pagelength=0;
            $pagestart=0;
        } elseif ($offset>0 && $page==0) {
            $pagelength = $perpage-$offset;
            $pagestart=0;
        } elseif ($offset>0 && $page>0) {
            // TODO: check logic for page 1+ 
            $pagelength = $perpage;
            if ($offset<=$perpage) {
                $pagestart=$page*$perpage-$offset;
            } else {
                $pagestart=($page-1)*$perpage;
            }
        } else {
            $pagelength = $perpage;
            $pagestart=$page*$perpage;
        }

        // Get the courses for the current page

        if ($pagelength>0) {
            $courses = kent_enrol_get_my_courses('id, shortname, summary, visible', 'shortname ASC', $pagestart, $pagelength);
        } else {
            $courses = kent_enrol_get_my_courses('id, shortname, summary, visible', 'shortname ASC', 0, 1);
            $courses['courses']=array();
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $context = context_system::instance();

        //Firstly... lets check if the user is an admin, and direct accordingly.

        //$sql = 'SELECT "user", userid, COUNT(*) as count FROM mdl_role_assignments ra WHERE userid ='. $USER->id.' AND roleid = (SELECT id FROM mdl_role WHERE name = "Teacher (sds)" OR name = "Convenor (sds)" LIMIT 1)';
        $params['capability'] = 'moodle/course:update';
        $params['userid'] = $USER->id;
        $sql = "SELECT 'user', userid, COUNT(ra.id) as count
                FROM {role_assignments} ra 
                WHERE userid = :userid AND roleid IN (
                    SELECT DISTINCT roleid
                    FROM {role_capabilities} rc
                    WHERE rc.capability = :capability AND rc.permission = 1 ORDER BY rc.roleid ASC
                )";

        $can_rollover = $DB->get_records_sql($sql, $params);

        $sql = 'SELECT "user", userid, COUNT(ra.id) as count FROM mdl_role_assignments ra WHERE userid ='. $USER->id.' AND roleid = (SELECT id FROM mdl_role WHERE shortname = "dep_admin" LIMIT 1)';
        $dep_admin = $DB->get_records_sql($sql);

        if(isset($CFG->kent_course_overview_search) && $CFG->kent_course_overview_search === true) {
          $searchform = '';
          $searchform .= '<div class="form_container"><form id="module_search" action="'.$CFG->wwwroot.'/course/search.php" method="get">';
          $searchform .= '<input type="text" id="coursesearchbox" size="30" name="search" placeholder="Module search" />';
          $searchform .= '<input class="courseoverview_search_sub" type="submit" value="go" />';
          $searchform .= '</form></div>';
          $this->content->text .= $searchform;
        }

        $box_text = "";
        if ($can_rollover['user']->count > 0 || has_capability('moodle/site:config',context_system::instance())){

            $rollover_admin_path = "$CFG->wwwroot/local/rollover/";
            $connect_admin_path = $CFG->wwwroot . '/local/connect/';
            $meta_admin_path = $CFG->wwwroot . '/local/kentmetacourse';
            $box_text .= '<p>'.get_string('admin_course_text', 'block_kent_course_overview').'</p>';
            $box_text .= '<p>'.'<a href="'.$rollover_admin_path.'">Rollover admin page</a></p>';

            if($dep_admin['user']->count > 0 || has_capability('moodle/site:config',context_system::instance())) {
                $box_text .= '<p><a href="'.$connect_admin_path.'">Departmental administrator pages</a></p>';
                $box_text .= '<p><a href="'.$meta_admin_path.'">Kent meta enrollment pages</a></p>';
            }

            //$this->content->text .= '<br/>';

        }

        if(has_capability('moodle/site:config',context_system::instance()) || has_capability('mod/cla:manage',context_system::instance())){
           $cla_path = $CFG->wwwroot . '/mod/cla/admin.php';
           $box_text .= '<p><a href="'.$cla_path.'">CLA administration</a></p>';
        }

        if ($box_text != ""){
            $this->content->text .= $OUTPUT->box_start('generalbox rollover_admin_notification') . $box_text . $OUTPUT->box_end();
        }


        $baseurl = new moodle_url($PAGE->URL, array('perpage' => $perpage));
        $coursecount = $courses['totalcourses']+$categories['totalcategories'];

        $paging = $OUTPUT->paging_bar($coursecount, $page, $perpage, $baseurl);
        if($paging != '<div class="paging"></div>') {
            $this->content->text .= $paging;
        }
        


        $site = get_site();
        $course = $site; //just in case we need the old global $course hack

        if (array_key_exists($site->id,$courses['courses'])) {
            unset($courses['courses'][$site->id]);
        }

        foreach ($courses['courses'] as $c) {
            if (isset($USER->lastcourseaccess[$c->id])) {
                $courses['courses'][$c->id]->lastaccess = $USER->lastcourseaccess[$c->id];
            } else {
                $courses['courses'][$c->id]->lastaccess = 0;
            }
        }

        //Provide link back to Current Moodle, if I am the archive moodle!
        if (isset($CFG->archive_moodle) && ($CFG->archive_moodle == TRUE) && kent_is_archive_moodle()){
            $this->content->text .= kent_archive_moodle_link();
        }


        //Print the category enrollment information
        if (!empty($categories['categories']) && ($page == 0)) {
            $this->content->text .= kent_category_print_overview($categories['categories'], $baseactionurl);
        }

        //Print the course enrollment information
        if (empty($courses['courses'])) {
            $this->content->text .= '<div class="co_no_crs">' . get_string('nocourses', 'block_kent_course_overview') . '</div>';
        } else {
            $this->content->text .= kent_course_print_overview($courses['courses'], $baseactionurl);
        }

        if($paging != '<div class="paging"></div>') {
            $this->content->text .= $paging;
        }

        //Provide link back to Archive Moodle if switched on
        if (isset($CFG->archive_moodle) && ($CFG->archive_moodle == TRUE) && !kent_is_archive_moodle()){
            $this->content->text .= kent_archive_moodle_link();
        }

        $this->content->text .= '<script src="' . $CFG->wwwroot . '/lib/jquery/jquery-1.7.1.min.js" type="text/javascript"></script>';
        $this->content->text .= '<script src="' . $CFG->wwwroot . '/blocks/kent_course_overview/js/showhide.js" type="text/javascript"></script>';
        $this->content->text .= '<script type="text/javascript"> window.clearCourseUrl = "'.$CFG->wwwroot.'/local/rollover/clear.php";</script>';
        $this->content->text .= '<script src="' . $CFG->wwwroot . '/local/rollover/scripts/js/jquery.blockUI.js" type="text/javascript"></script>';
        $this->content->text .= '<script src="' . $CFG->wwwroot . '/local/rollover/scripts/js/jquery-ui-1.8.17.custom.min.js" type="text/javascript"></script>';
        $this->content->text .= '<script src="' . $CFG->wwwroot . '/blocks/kent_course_overview/js/clear-course.js" type="text/javascript"></script>'; 
        $this->content->text .= '<link rel="stylesheet" href="' . $CFG->wwwroot . '/local/rollover/scripts/css/ui-lightness/jquery-ui-1.8.17.custom.css" type="text/css" />';
        $this->content->text .= '<div id="dialog_sure">'.get_string('areyousure', 'block_kent_course_overview').'</div>';
        $this->content->text .= '<div id="dialog_clear_error">'.get_string('clearerror', 'block_kent_course_overview').'</div>';

        return $this->content;
    }

    /**
     * allow the block to have a configuration page
     *
     * @return boolean
     */
    public function has_config() {
        return true;
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
