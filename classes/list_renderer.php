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
 * Kent Course Overview Block
 *
 * @package    blocks_kent_course_overview
 * @copyright  2014 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_kent_course_overview;

defined('MOODLE_INTERNAL') || die();

/**
 * Kent Course Overview List Renderer
 */
class list_renderer
{
    /**
     * Prints an overview of the categories.
     */
    public function print_categories($categories) {
        $content = '';

        foreach ($categories as $category) {
            $attributes = array(
                'title' => s($category->name),
                'class' => 'course_list'
            );

            // Construct link.
            $url = new \moodle_url('/course/index.php', array(
                'categoryid' => $category->id
            ));
            $link = \html_writer::link($url, $category->name, $attributes);

            $content .= <<<HTML
            <li class="course">
                <div class="course_details_ovrv">
                    <span class="title">
                        $link
                    </span>
                </div>
                <div style="clear: both"></div>
            </li>
HTML;
        }

        if (!empty($content)) {
            $content = '<ul id="kent_category_list_overview">' . $content . '</ul>';
        }

        return $content;

    }

    /**
     * Returns search box.
     */
    public function print_search_box() {
        global $CFG;

        return <<<HTML
            <div class="form_container">
                <form id="module_search" action="{$CFG->wwwroot}/course/search.php" method="get">
                    <div class="left">
                        <input type="text" id="coursesearchbox" size="30" name="search" placeholder="Module search" />
                    </div>
                    <div class="right">
                        <input class="courseoverview_search_sub" type="submit" value="go" />
                    </div>
                </form>
            </div>
HTML;
    }

    /**
     * Print teachers.
     */
    public function print_teachers($teachers) {
        $stafftoggle = get_string('staff_toggle', 'block_kent_course_overview');
        $showhide = \html_writer::tag('div', $stafftoggle, array(
            'class' => 'teachers_show_hide'
        ));

        $staff = '';
        foreach ($teachers as $teacher) {
            $staff .= \html_writer::tag('span', $teacher);
        }

        return $showhide . \html_writer::tag('div', $staff, array(
            'class' => 'teachers'
        ));
    }

    /**
     * Print courses.
     */
    public function print_courses($courses, $baseurl) {
        global $CFG, $USER, $DB, $OUTPUT;

        $content = '';

        foreach ($courses as $course) {
            $rollover = new \local_rollover\Course($course->id);

            $context = \context_course::instance($course->id);
            $fullname = format_string($course->fullname, true, array('context' => $context));
            $shortname = format_string($course->shortname, true, array('context' => $context));

            $width = '';
            $listclass = '';
            $adminhide = 'admin_hide';
            $attributes = array('title' => s($fullname), 'class' => 'course_list');

            $permstorollover = has_capability('moodle/course:update', $context);

            // Ensure Rollover is installed before we do anything and that the course doesn't have content.
            $rolloverinstalled = \local_kent\util\sharedb::available();

            if ($rolloverinstalled) {
                $rolloverstatus = $rollover->get_status();
                $rolloverable = $rollover->can_rollover();

                if ($rolloverable && $permstorollover) {
                    $width = 'admin_width';
                    $adminhide = '';
                    $listclass = 'rollover_'.$rolloverstatus.' ';
                }
            }

            // Construct link.
            $content .= '<li class="' . $listclass . (!$course->visible ? 'course_unavailable' : '').'">';
            if (has_capability('moodle/course:visibility', $context) && has_capability('moodle/course:viewhiddencourses', $context)) {
                // If user can view hidden and can adjust visibility, we'll let them change it from here.
                if (!empty($course->visible)) {
                    $url = new \moodle_url($baseurl, array('hide' => $course->id));
                    $img = $OUTPUT->action_icon($url, new \pix_icon('t/hide', get_string('hide')));
                } else {
                    $url = new \moodle_url($baseurl, array('show' => $course->id));
                    $img = $OUTPUT->action_icon($url, new \pix_icon('t/show', get_string('show')));
                }
                $content .= "<div class='visibility_tri'></div><div class='course_adjust_visibility'>" . $img . "</div>";
            }
            $content .= '<div class="course_details_ovrv '.$width. '" >';

            $name = $fullname;
            if (isset($CFG->courselistshortnames)) {
                if ($CFG->courselistshortnames === '1') {
                    $name = $shortname . ': ' . $fullname;
                }
            }

            $viewurl = new \moodle_url('/course/view.php', array(
                'id' => $course->id
            ));
            $content .= '<span class="title">'.\html_writer::link($viewurl, $name, $attributes) . '</span>';

            if (!empty($course->summary)) {
                $summary = $course->summary;
                if (strlen($summary) > 250) {
                    $summary = \core_text::substr($summary, 0, 252) . '...';
                    $summary = strip_tags($summary);
                }
                $content .= ' <span class="course_description">' . $summary . '</span>';
            }

            $teachers = $course->get_teachers();
            if (!empty($teachers)) {
                $content .= $this->print_teachers($teachers);
            }

            $content .= '</div>';

            // If user has ability to update the course and the course is empty to signify a rollover.
            if ($rolloverinstalled && $permstorollover) {

                $clearmodule = get_config('block_kent_course_overview', 'clearmodule');
                $clearmodulebutton = get_string('clearmodulebutton', 'block_kent_course_overview');

                $rolloverpath = $CFG->wwwroot.'/local/rollover/index.php?srch='.$course->shortname;

                if ($rolloverstatus == \local_rollover\Rollover::STATUS_NONE && !($rolloverable) && $clearmodule) {
                    $adminhide = '';
                }

                $content .= ' <div class="course_admin_options '.$adminhide.'">';
                switch ($rolloverstatus) {
                    case $rolloverable && \local_rollover\Rollover::STATUS_NONE:
                    case $rolloverable && \local_rollover\Rollover::STATUS_DELETED:
                        $content .= '<a class="course_rollover_optns new" href="'.$rolloverpath.'">Empty module. <br / > ';
                        $content .= 'Click here to <br /><strong>Rollover module</strong></a>';
                    break;

                    case \local_rollover\Rollover::STATUS_WAITING_SCHEDULE:
                        $content .= '<div class="course_rollover_optns pending">Rollover pending</div>';
                    break;

                    case \local_rollover\Rollover::STATUS_BACKED_UP:
                    case \local_rollover\Rollover::STATUS_IN_PROGRESS:
                    case \local_rollover\Rollover::STATUS_SCHEDULED:
                        $content .= '<div class="course_rollover_optns pending">Rollover in process</div>';
                    break;

                    case \local_rollover\Rollover::STATUS_COMPLETE:
                    case \local_rollover\Rollover::STATUS_ERROR:
                    default:
                        if ($clearmodule) {
                            $adminhide = '';
                            $content .= '<a class="course_clear_optns new" href="#'.$course->id.'">'.$clearmodulebutton.'</a>';
                        }
                    break;
                }

                $content .= '</div><div style="clear: both"></div>';
            }

            $content .= '</li>';

        }

        if (!empty($content)) {
            $content = '<ul id="kent_course_list_overview">' . $content . '</ul>';
        }

        return $content;
    }
}