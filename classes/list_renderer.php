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

        // Ensure Rollover is installed before we do anything and that the course doesn't have content.
        $rolloverinstalled = \local_kent\util\sharedb::available();

        $content = '';

        foreach ($courses as $course) {
            $course->visible = (int)$course->visible == 1 ? true : false;
            $context = \context_course::instance($course->id);
            $fullname = format_string($course->fullname, true, array('context' => $context));
            $shortname = format_string($course->shortname, true, array('context' => $context));

            $adminhide = 'admin_hide';
            $listclass = array('container');
            $cdclass = array(
                'course_details_ovrv',
                'row'
            );
            $attributes = array(
                'title' => s($fullname),
                'class' => 'course_list'
            );

            if ($rolloverinstalled) {
                $permstorollover = has_capability('moodle/course:update', $context);

                $rollover = new \local_rollover\Course($course->id);
                $rolloverstatus = $rollover->get_status();
                $rolloverable = $rollover->can_rollover();

                if ($rolloverable && $permstorollover) {
                    $adminhide = '';
                    $cdclass[] = 'admin_width';
                    $listclass[] = "rollover_{$rolloverstatus}";
                }
            }

            // Add unavailable link.
            if (!$course->visible) {
                $listclass[] = 'course_unavailable';
            }

            // Construct link.
            $listclass = implode(' ', $listclass);
            $content .= '<li class="' . $listclass . '">';

            $cdclass = implode(' ', $cdclass);
            $content .= '<div class="' . $cdclass. '">';

            $name = $fullname;
            if (isset($CFG->courselistshortnames) && $CFG->courselistshortnames === '1') {
                $name = $shortname . ': ' . $fullname;
            }

            $viewurl = new \moodle_url('/course/view.php', array(
                'id' => $course->id
            ));
            $content .= '<span class="title">' . \html_writer::link($viewurl, $name, $attributes);

            // If user can view hidden and can adjust visibility, we'll let them change it from here.
            if (has_capability('moodle/course:visibility', $context) &&
                has_capability('moodle/course:viewhiddencourses', $context)) {
                
                $img = '<i class="fa fa-eye-slash" data-action="show" data-id="'.$course->id.'"></i>';
                if ($course->visible) {
                    $img = '<i class="fa fa-eye" data-action="hide" data-id="'.$course->id.'"></i>';
                }

                $content .= "<div class='visibility_tri'></div><div class='course_adjust_visibility'>" . $img . "</div>";
            }

            $content .= '</span>';

            $summary = $course->summary;
            if (!empty($summary)) {
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
                $rolloverpath = new \moodle_url('/local/rollover/index.php', array(
                    'srch' => $course->shortname
                ));

                if ($rolloverstatus == \local_rollover\Rollover::STATUS_NONE && !($rolloverable)) {
                    $adminhide = '';
                }

                $classes = array('course_admin_options', 'row');
                if (!empty($adminhide)) {
                    $classes[] = $adminhide;
                }
                $classes = implode(' ', $classes);

                $content .= ' <div class="'.$classes.'">';
                switch ($rolloverstatus) {
                    case $rolloverable && \local_rollover\Rollover::STATUS_NONE:
                    case $rolloverable && \local_rollover\Rollover::STATUS_DELETED:
                        $content .= '<a class="course_rollover_optns new" href="'.$rolloverpath.'">Empty module. ';
                        $content .= 'Click here to Rollover</a>';
                    break;

                    case \local_rollover\Rollover::STATUS_WAITING_SCHEDULE:
                        $content .= '<div class="course_rollover_optns pending">Rollover pending</div>';
                    break;

                    case \local_rollover\Rollover::STATUS_BACKED_UP:
                    case \local_rollover\Rollover::STATUS_IN_PROGRESS:
                    case \local_rollover\Rollover::STATUS_SCHEDULED:
                        $content .= '<div class="course_rollover_optns pending">Rollover in process</div>';
                    break;

                    case \local_rollover\Rollover::STATUS_ERROR:
                        $url = new \moodle_url("/local/rollover/clear.php", array(
                            'id' => $course->id
                        ));
                        $content .= '<a class="course_clear_optns error" href="'.$url.'">There was an error rolling over. Reset Module?</a>';
                    break;

                    case \local_rollover\Rollover::STATUS_COMPLETE:
                    default:
                        // Do nothing.
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


    /**
     * Print courses.
     */
    public function print_admin_links() {
        global $DB, $USER;

        $ctx = \context_system::instance();
        $boxtext = "";

        // Are we an admin?
        $isadmin = has_capability('moodle/site:config', $ctx);
        $rolloveradmin = $isadmin || \local_kent\User::has_course_update_role($USER->id);
        $claadmin = $isadmin || has_capability('mod/cla:manage', $ctx);
        $depadmin = $isadmin ||  \local_kent\User::is_dep_admin($USER->id);

        // Add the rollover links.
        if ($rolloveradmin) {
            $rolloveradminpath = new \moodle_url("/local/rollover/");
            $boxtext .= '<p><a href="' . $rolloveradminpath . '">Rollover admin page</a></p>';
        }

        // Add dep admin links.
        if ($depadmin) {
            $connectadminpath = new \moodle_url("/local/connect/");
            $boxtext .= '<p><a href="' . $connectadminpath . '">Departmental administrator pages</a></p>';

            $metaadminpath = new \moodle_url("/admin/tool/meta");
            $boxtext .= '<p><a href="' . $metaadminpath . '">Kent meta enrolment pages</a></p>';
        }

        // Add CLA links.
        if ($claadmin) {
            $clapath = new \moodle_url('/mod/cla/admin.php');
            $boxtext .= '<p><a href="' . $clapath . '">CLA administration</a></p>';
        }

        return $boxtext;
    }
}