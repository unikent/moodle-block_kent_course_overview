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

require_once("{$CFG->dirroot}/blocks/course_overview/locallib.php");

/**
 * Course overview block
 *
 * @package   blocks
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_kent_course_overview extends block_base {
    /**
     * block initializations
     */
    public function init() {
        $this->title = get_string('blocktitle', 'block_kent_course_overview');
    }

    /**
     * block contents
     *
     * @return object
     */
    public function get_content() {
        global $USER, $OUTPUT, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        // Guest account does not have anything.
        if (isguestuser() or !isloggedin()) {
            $this->content = "";
            return "";
        }

        $page = optional_param('page', 0, PARAM_INT);
        $perpage = optional_param('perpage', 20, PARAM_INT);

        $listgen = new \block_kent_course_overview\list_generator();
        $renderer = $this->page->get_renderer('block_kent_course_overview');

        $this->content = new \stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Generate page url for page actions from current params.
        // Don't move this further down!
        $params = array(
            'page' => $page,
            'perpage' => $perpage
        );

        // Get the courses for the current page.
        $courses = $listgen->get_courses($USER->id);

        // Fetch the Categories that user is enrolled in.
        $categories = $listgen->get_categories($USER->id);

        // Count now.
        $total = count($courses) + count($categories);

        // Calculate courses to add after category records.
        $offset = count($categories);
        if ($offset >= $perpage && $page == 0) {
            $pagelength = 0;
            $pagestart  = 0;
        } else if ($offset > 0 && $page == 0) {
            $pagelength = $perpage - $offset;
            $pagestart  = 0;
        } else if ($offset > 0 && $page > 0) {
            $pagelength = $perpage;
            if ($offset <= $perpage) {
                $pagestart = ($page * $perpage) - $offset;
            } else {
                $pagestart = ($page - 1) * $perpage;
            }
        } else {
            $pagelength = $perpage;
            $pagestart  = $page * $perpage;
        }

        if ($pagelength > 0) {
            $courses = array_slice($courses, $pagestart, $pagelength, true);
        }

        // Build the search box.
        $this->content->text .= $renderer->render_search_box();

        $baseurl = new moodle_url($PAGE->url, $params);

        // Print the category enrollment information.
        if (!empty($categories) && ($page == 0)) {
            $this->content->text .= $renderer->render_categories($categories);
        }

        // Print the course enrollment information.
        if ($pagelength > 0) {
            $this->content->text .= $renderer->render_courses($courses, $baseurl);
        }

        if ($total > $perpage) {
            $this->content->text .= $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
        }

        return $this->content;
    }

    /**
     * allow the block to have a configuration page
     * Moodle override.
     *
     * @return boolean
     */
    public function has_config() {
        return false;
    }

    /**
     * Allow the user to configure a block instance
     * @return bool Returns true
     */
    public function instance_allow_config() {
        return false;
    }

    /**
     * Returns the role that best describes the navigation block... 'navigation'
     *
     * @return string 'navigation'
     */
    public function get_aria_role() {
        return 'navigation';
    }

    /**
     * locations where block can be displayed
     * Moodle override.
     *
     * @return array
     */
    public function applicable_formats() {
        return array(
            'my' => true
        );
    }
}
