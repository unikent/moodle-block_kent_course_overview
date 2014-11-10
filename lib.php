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
 * Print course overview.
 */
function kent_course_print_overview($courses, $baseurl) {
    global $CFG, $USER, $DB, $OUTPUT;

    $content = '';

    foreach ($courses as $course) {
        $rollover = new \local_rollover\Course($course->id);

        $context = context_course::instance($course->id);
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
                $url = new moodle_url($baseurl, array('hide' => $course->id));
                $img = $OUTPUT->action_icon($url, new pix_icon('t/hide', get_string('hide')));
            } else {
                $url = new moodle_url($baseurl, array('show' => $course->id));
                $img = $OUTPUT->action_icon($url, new pix_icon('t/show', get_string('show')));
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

        $viewurl = new moodle_url('/course/view.php', array(
            'id' => $course->id
        ));
        $content .= '<span class="title">'.html_writer::link($viewurl, $name, $attributes) . '</span>';

        if (!empty($course->summary)) {
            $content .= ' <span class="course_description">' . $course->summary . '</span>';
        }

        $content .= kent_add_teachers($course, $context);
        $content .= '</div>';

        // If user has ability to update the course and the course is empty to signify a rollover.
        if ($rolloverinstalled && $permstorollover) {

            $clearmodule = get_config('block_kent_course_overview', 'clearmodule');
            $clearmodulebutton = $clearmodulebutton;

            $rolloverpath = $CFG->wwwroot.'/local/rollover/index.php?srch='.$course->shortname;

            if ($rolloverstatus == \local_rollover\Rollover::STATUS_NONE && !($rolloverable) && $clearmodule) {
                $adminhide = '';
            }

            $content .= ' <div class="course_admin_options '.$adminhide.'">';
            switch ($rolloverstatus) {
                case \local_rollover\Rollover::STATUS_NONE:
                    if ($rolloverable) {
                        $content .= '<a class="course_rollover_optns new" href="'.$rolloverpath.'">Empty module. <br / > ';
                        $content .= 'Click here to <br /><strong>Rollover module</strong></a>';
                    } else if ($clearmodule) {
                        $adminhide = '';
                        $content .= '<a class="course_clear_optns new" href="#'.$course->id.'">'.$clearmodulebutton.'</a>';
                    }
                break;

                case \local_rollover\Rollover::STATUS_COMPLETE:
                    if ($clearmodule) {
                        $adminhide = '';
                        $content .= '<a class="course_clear_optns new" href="#'.$course->id.'">'.$clearmodulebutton.'</a>';
                    }
                break;

                case \local_rollover\Rollover::STATUS_SCHEDULED:
                    $content .= '<div class="course_rollover_optns pending">Rollover pending</div>';
                break;

                case \local_rollover\Rollover::STATUS_BACKED_UP:
                case \local_rollover\Rollover::STATUS_IN_PROGRESS:
                    $content .= '<div class="course_rollover_optns pending">Rollover in process</div>';
                break;

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


/*
 * Function to pull in teachers linked on a course.
 */
function kent_add_teachers($course, $context) {

    global $CFG, $DB;

    $string = '';

    // First find all roles that are supposed to be displayed.
    if (!empty($CFG->coursecontact)) {
        $managerroles = explode(',', $CFG->coursecontact);
        $namesarray = array();
        $rusers = get_role_users($managerroles, $context, true);

        $namesarray = array();
        $canviewfullnames = has_capability('moodle/site:viewfullnames', $context);
        foreach ($rusers as $ra) {
            if (isset($namesarray[$ra->id])) {
                // Only display a user once with the higest sortorder role.
                continue;
            }

            $fullname = fullname($ra, $canviewfullnames);
            $rolename = !empty($ra->rolename) ? $ra->rolename : $ra->roleshortname;

            $nameurl = new moodle_url('/user/view.php', array(
                'id' => $ra->id,
                'course' => SITEID
            ));

            $namesarray[$ra->id] = s($rolename) . ': ' . html_writer::link($nameurl, $fullname);
        }

        if (!empty($namesarray)) {
            $string .= '<div class="teachers_show_hide">';
            $string .= get_string('staff_toggle', 'block_kent_course_overview');
            $string .= '</div>';
            $string .= html_writer::start_tag('div', array(
                'class' => 'teachers'
            ));
            foreach ($namesarray as $name) {
                $string .= html_writer::tag('span', $name);
            }
            $string .= html_writer::end_tag('div');
        }
    }

    return $string;
}

/**
 * Returns list of courses current $USER is enrolled in and can access
 *
 * - $fields is an array of field names to ADD
 *   so name the fields you really need, which will
 *   be added and uniq'd
 *
 * @param string|array $fields
 * @param string $sort
 * @param int $limit max number of courses
 * @return array
 */
function kent_enrol_get_my_courses($fields = null, $sort = 'sortorder ASC', $page, $perpage) {
    global $USER;

    $courses = enrol_get_users_courses($USER->id, false, $fields, $sort);
    $courseset = array_slice($courses, $page, $perpage, true);
    return array('totalcourses' => count($courses), 'courses' => $courseset);
}

function kent_enrol_get_my_categories() {
    global $DB, $USER;

    // Guest account does not have any courses.
    if (isguestuser() or !isloggedin()) {
        return array();
    }

    $sql = "SELECT cc.id, cc.name, cc.sortorder
            FROM {course_categories} cc
            INNER JOIN {context} c
                ON cc.id=c.instanceid
                    AND c.contextlevel=:ctxlevel
            INNER JOIN {role_assignments} ra
                ON ra.contextid=c.id
            INNER JOIN {user} u
                ON ra.userid=u.id
            WHERE u.id = :userid
            GROUP BY cc.id";

    $categories = $DB->get_records_sql($sql, array(
        'userid' => $USER->id,
        'ctxlevel' => \CONTEXT_COURSECAT
    ));

    $totalcategories = count($categories);

    return array('totalcategories' => $totalcategories, 'categories' => $categories);
}

function kent_category_print_overview($categories, $baseurl) {
    $content = '';

    foreach ($categories as $category) {
        $attributes = array(
            'title' => s($category->name),
            'class' => 'course_list'
        );

        // Construct link.
        $url = new moodle_url('/course/category.php', array(
            'id' => $category->id
        ));
        $link = html_writer::link($url, $category->name, $attributes);

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
