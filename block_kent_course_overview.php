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
     * Required JS
     */
    public function get_required_javascript() {
        parent::get_required_javascript();

        // We need jQuery
        $this->page->requires->jquery();
        $this->page->requires->jquery_plugin('migrate');
        $this->page->requires->jquery_plugin('ui');
        $this->page->requires->jquery_plugin('ui-css');
        $this->page->requires->jquery_plugin('blockui', 'theme_kent');

        // And some custom things
        $this->page->requires->js('/blocks/kent_course_overview/js/showhide.js');
        $this->page->requires->js('/blocks/kent_course_overview/js/clear-course.js');
    }

    /**
     * block contents
     *
     * @return object
     */
    public function get_content() {
        global $USER, $CFG, $OUTPUT, $DB, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $cancache = true;

        // Get hide/show params (for quick visbility changes)
        $hide = optional_param('hide', 0, PARAM_INT);
        $show = optional_param('show', 0, PARAM_INT);
        $page = optional_param('page', 0, PARAM_INT);
        $perpage = optional_param('perpage', 20, PARAM_INT);

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
                $cancache = false;
            }
        }

        // If a user enrolment has changed, we cannot use the cache.

        // MUC - Can we grab from cache?
        $cache = cache::make('block_kent_course_overview', 'data');
        $cachekey = 'full_' . $USER->id;
        $cachekey2 = $page . '_' . $perpage;

        $cachecontent = $cache->get($cachekey);

        if ($cancache && $cachecontent !== false) {
            if (isset($cachecontent[$cachekey2])) {
                $this->content = $cachecontent[$cachekey2];
                return $this->content;
            }
        }

        // Generate page url for page actions from current params.
        $params = array();
        if ($page) {
            $params['page'] = $page;
        }

        if ($perpage) {
            $params['perpage'] = $perpage;
        }

        $baseactionurl = new moodle_url($PAGE->URL, $params);

        // Fetch the Categories that user is enrolled in.
        $categories = kent_enrol_get_my_categories('*','');
        $offset = isset($categories['totalcategories']) ? $categories['totalcategories'] : 0;

        // Calculate courses to add after category records
        if ($offset > $perpage && $page == 0) {
            $pagelength = 0;
            $pagestart  = 0;
        } else if ($offset > 0 && $page == 0) {
            $pagelength = $perpage - $offset;
            $pagestart  = 0;
        } else if ($offset > 0 && $page > 0) {
            $pagelength = $perpage;
            if ($offset <= $perpage) {
                $pagestart = $page * $perpage - $offset;
            } else {
                $pagestart = ($page - 1) * $perpage;
            }
        } else {
            $pagelength = $perpage;
            $pagestart  = $page * $perpage;
        }

        // Get the courses for the current page

        if ($pagelength > 0) {
            $courses = kent_enrol_get_my_courses('id, shortname, summary, visible', 'shortname ASC', $pagestart, $pagelength);
        } else {
            $courses = kent_enrol_get_my_courses('id, shortname, summary, visible', 'shortname ASC', 0, 1);
            $courses['courses'] = array();
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Build the search box
        $searchform = '';
        $searchform .= '<div class="form_container"><form id="module_search" action="'.$CFG->wwwroot.'/course/search.php" method="get">';
        $searchform .= '<div class="left"><input type="text" id="coursesearchbox" size="30" name="search" placeholder="Module search" /></div>';
        $searchform .= '<div class="right"><input class="courseoverview_search_sub" type="submit" value="go" /></div>';
        $searchform .= '</form></div>';
        $this->content->text .= $searchform;

        // Are we an admin?
        $isSiteAdmin = has_capability('moodle/site:config', context_system::instance());

        // Can we rollover any module?
        $can_rollover = $isSiteAdmin;
        if (!$can_rollover) {
            $sql = "SELECT 'user', COUNT(ra.id) as count
                    FROM {role_assignments} ra 
                    WHERE userid = :userid AND roleid IN (
                        SELECT DISTINCT roleid
                        FROM {role_capabilities} rc
                        WHERE rc.capability = :capability AND rc.permission = 1 ORDER BY rc.roleid ASC
                    )";
            $query = $DB->get_records_sql($sql, array(
                'capability' => 'moodle/course:update',
                'userid' => $USER->id
            ));
            $can_rollover = $query['user']->count > 0;
        }

        // Can we see the DA pages?
        $dep_admin = $isSiteAdmin;
        if (!$dep_admin) {
            $sql = "SELECT 'user', COUNT(ra.id) as count 
                    FROM {role_assignments} ra
                    WHERE userid = :userid AND roleid = (
                        SELECT id FROM {role} WHERE shortname = :shortname LIMIT 1
                    )";
            $query = $DB->get_records_sql($sql, array(
                'userid' => $USER->id,
                'shortname' => 'dep_admin'
            ));
            $dep_admin = $query['user']->count > 0;
        }

        // ----------------------------------------------------------------------------------------------------------------------
        // Main admin box

        // Build the main admin box
        $box_text = "";
        if ($can_rollover) {
            $box_text .= '<p>'.get_string('admin_course_text', 'block_kent_course_overview').'</p>';

            $rollover_admin_path = "$CFG->wwwroot/local/rollover/";
            $box_text .= '<p>'.'<a href="'.$rollover_admin_path.'">Rollover admin page</a></p>';

            if ($dep_admin) {
                $connect_admin_path = $CFG->wwwroot . '/local/connect/';
                $box_text .= '<p><a href="'.$connect_admin_path.'">Departmental administrator pages</a></p>';

                $meta_admin_path = $CFG->wwwroot . '/local/kentmetacourse';
                $box_text .= '<p><a href="'.$meta_admin_path.'">Kent meta enrollment pages</a></p>';
            }
        }

        if ($isSiteAdmin || has_capability('mod/cla:manage', context_system::instance())) {
           $cla_path = $CFG->wwwroot . '/mod/cla/admin.php';
           $box_text .= '<p><a href="'.$cla_path.'">CLA administration</a></p>';
        }

        // Finalise the main admin block
        if ($box_text != "") {
            $this->content->text .= $OUTPUT->box_start('generalbox rollover_admin_notification') . $box_text . $OUTPUT->box_end();
        }

        // ----------------------------------------------------------------------------------------------------------------------

        $baseurl = new moodle_url($PAGE->URL, array('perpage' => $perpage));
        $coursecount = $courses['totalcourses'] + $categories['totalcategories'];

        $paging = $OUTPUT->paging_bar($coursecount, $page, $perpage, $baseurl);
        if ($paging != '<div class="paging"></div>') {
            $this->content->text .= $paging;
        }

        // Remove main site course
        $site = get_site();
        if (array_key_exists($site->id, $courses['courses'])) {
            unset($courses['courses'][$site->id]);
        }

        // Update access times
        foreach ($courses['courses'] as $c) {
            if (isset($USER->lastcourseaccess[$c->id])) {
                $courses['courses'][$c->id]->lastaccess = $USER->lastcourseaccess[$c->id];
            } else {
                $courses['courses'][$c->id]->lastaccess = 0;
            }
        }

        // Provide link back to Current Moodle, if I am the archive moodle!
        if ($CFG->kent->distribution === "2012" || $CFG->kent->distribution === "archive") {
            $this->content->text .= '<div class="archive_link">'.get_string('current_text', 'block_kent_course_overview').'</div>';
        }

        // Print the category enrollment information
        if (!empty($categories['categories']) && ($page == 0)) {
            $this->content->text .= kent_category_print_overview($categories['categories'], $baseactionurl);
        }

        // Print the course enrollment information
        if (empty($courses['courses'])) {
            $this->content->text .= '<div class="co_no_crs">' . get_string('nocourses', 'block_kent_course_overview') . '</div>';
        } else {
            $this->content->text .= kent_course_print_overview($courses['courses'], $baseactionurl);
        }

        if ($paging != '<div class="paging"></div>') {
            $this->content->text .= $paging;
        }

        // Provide link back to Archive Moodle if switched on
        if ($CFG->kent->distribution === LIVE_MOODLE) {
            $this->content->text .= '<div class="archive_link">'.get_string('archives_text', 'block_kent_course_overview').'</div>';
        }

        $this->content->text .= '<div id="dialog_sure">'.get_string('areyousure', 'block_kent_course_overview').'</div>';
        $this->content->text .= '<div id="dialog_clear_error">'.get_string('clearerror', 'block_kent_course_overview').'</div>';

        $cachecontent[$cachekey2] = $this->content;

        $cache->set($cachekey, $cachecontent);

        return $this->content;
    }

    /**
     * allow the block to have a configuration page
     * Moodle override.
     *
     * @return boolean
     */
    public function has_config() {
        return true;
    }

    /**
     * locations where block can be displayed
     * Moodle override.
     *
     * @return array
     */
    public function applicable_formats() {
        return array(
            'my-index' => true
        );
    }
}
