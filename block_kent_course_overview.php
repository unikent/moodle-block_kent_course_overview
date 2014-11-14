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
 * @package   blocks
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/lib/weblib.php');
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

        // We need jQuery.
        $this->page->requires->jquery();
        $this->page->requires->jquery_plugin('migrate');
        $this->page->requires->jquery_plugin('ui');
        $this->page->requires->jquery_plugin('ui-css');
        $this->page->requires->jquery_plugin('blockui', 'theme_kent');

        // And some custom things.
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

        // Guest account does not have anything.
        if (isguestuser() or !isloggedin()) {
            $this->content = "";
            return "";
        }

        $listgen = new \block_kent_course_overview\list_generator();
        $listrender = new \block_kent_course_overview\list_renderer();

        $cancache = false;

        // Get hide/show params (for quick visbility changes).
        $hide = optional_param('hide', 0, PARAM_INT);
        $show = optional_param('show', 0, PARAM_INT);
        $page = optional_param('page', 0, PARAM_INT);
        $perpage = optional_param('perpage', 20, PARAM_INT);

        // Process show/hide if there is one.
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
                $DB->update_record('course', array(
                    'id' => $course->id,
                    'visible' => $visible,
                    'visibleold' => $visible,
                    'timemodified' => time()
                ));

                $cancache = false;
            }
        }

        // Cache block data.
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
        $categories = $listgen->get_categories($USER->id);
        $offset = count($categories);

        // Calculate courses to add after category records.
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

        // Get the courses for the current page.
        $courses = $listgen->get_courses($USER->id);
        if ($pagelength > 0) {
            $courses = array_slice($courses, $pagestart, $pagelength, true);
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Build the search box.
        $this->content->text .= $listrender->print_search_box();

        // Are we an admin?
        $isadmin = has_capability('moodle/site:config', context_system::instance());

        // Can we rollover any module?
        $canrollover = $isadmin;
        if (!$canrollover) {
            $canrollover = \local_kent\User::has_course_update_role($USER->id);
        }

        // Build the main admin box.
        $boxtext = "";

        // Add the rollover links.
        if ($canrollover) {
            $boxtext .= '<p>'.get_string('admin_course_text', 'block_kent_course_overview').'</p>';

            $rolloveradminpath = "$CFG->wwwroot/local/rollover/";
            $boxtext .= '<p>'.'<a href="'.$rolloveradminpath.'">Rollover admin page</a></p>';

            // Can we see the DA pages?
            $depadmin = $isadmin;
            if (!$depadmin) {
                $sql = "SELECT COUNT(ra.id) as count
                        FROM {role_assignments} ra
                        WHERE userid = :userid AND roleid = (
                            SELECT id FROM {role} WHERE shortname = :shortname LIMIT 1
                        )";
                $count = $DB->count_records_sql($sql, array(
                    'userid' => $USER->id,
                    'shortname' => 'dep_admin'
                ));
                $depadmin = \local_kent\User::is_dep_admin($USER->id);
            }

            if ($depadmin) {
                $connectadminpath = "$CFG->wwwroot/local/connect/";
                $boxtext .= '<p><a href="' . $connectadminpath . '">Departmental administrator pages</a></p>';

                $metaadminpath = "$CFG->wwwroot/admin/tool/meta";
                $boxtext .= '<p><a href="' . $metaadminpath . '">Kent meta enrolment pages</a></p>';
            }
        }

        if ($isadmin || has_capability('mod/cla:manage', context_system::instance())) {
            $clapath = $CFG->wwwroot . '/mod/cla/admin.php';
            $boxtext .= '<p><a href="' . $clapath . '">CLA administration</a></p>';
        }

        // Finalise the main admin block.
        if (!empty($boxtext)) {
            $this->content->text .= $OUTPUT->box($boxtext, 'generalbox rollover_admin_notification');
        }

        // ----------------------------------------------------------------------------------------------------------------------

        $baseurl = new moodle_url($PAGE->URL, array('perpage' => $perpage));
        $coursecount = count($courses) + count($categories);

        $paging = $OUTPUT->paging_bar($coursecount, $page, $perpage, $baseurl);
        if ($paging != '<div class="paging"></div>') {
            $this->content->text .= $paging;
        }

        // Remove main site course.
        $site = get_site();
        if (array_key_exists($site->id, $courses)) {
            unset($courses[$site->id]);
        }

        // Print the category enrollment information.
        if (!empty($categories) && ($page == 0)) {
            $this->content->text .= $listrender->print_categories($categories);
        }

        // Print the course enrollment information.
        if (empty($courses)) {
            $this->content->text .= '<div class="co_no_crs">' . get_string('nocourses', 'block_kent_course_overview') . '</div>';
        } else {
            $this->content->text .= $listrender->print_courses($courses, $baseactionurl);
        }

        if ($paging != '<div class="paging"></div>') {
            $this->content->text .= $paging;
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
