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
 * kent_course_overview block rendrer
 *
 * @package    block_kent_course_overview
 * @copyright  2015 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

/**
 * Kent Course Overview block rendrer
 *
 * @copyright  2015 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_kent_course_overview_renderer extends plugin_renderer_base
{
    /**
     * Prints an overview of the categories.
     */
    public function render_categories($categories) {
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
            $content = \html_writer::tag('ul', $content, array(
                'id' => 'kent_category_list_overview',
                'class' => 'list-unstyled'
            ));
        }

        return $content;

    }

    /**
     * Returns search box.
     */
    public function render_search_box() {
        global $CFG;

        return <<<HTML5
            <div class="form_container">
                <form id="module_search" action="{$CFG->wwwroot}/course/search.php" method="GET">
                    <div class="input-group input-group-sm">
                        <input class="form-control" type="text" name="search" placeholder="Search modules" />
                        <span class="input-group-btn">
                            <button class="btn btn-default" type="button"><i class="fa fa-search"></i></button>
                        </span>
                    </div>
                </form>
            </div>
HTML5;
    }

    /**
     * Print teachers.
     */
    public function render_teachers($teachers) {
        static $tid = 0;

        $id = 'teacherscollapse' . ($tid++);

        $stafftoggle = '<i class="fa fa-chevron-down"></i> ' . get_string('staff_toggle', 'block_kent_course_overview');
        $showhide = \html_writer::tag('a', $stafftoggle, array(
            'data-toggle' => 'collapse',
            'href' => '#' . $id,
            'aria-expanded' => 'false',
            'aria-controls' => $id,
        ));

        $staff = '';
        foreach ($teachers as $teacher) {
            $staff .= \html_writer::tag('span', $teacher);
        }

        $staffwell = \html_writer::tag('div', $staff, array(
            'class' => 'well'
        ));

        return $showhide . \html_writer::tag('div', $staffwell, array(
            'id' => $id,
            'class' => 'collapse'
        ));
    }

    /**
     * Print courses.
     */
    public function render_courses($courses, $baseurl) {
        global $CFG;

        if (empty($courses)) {
            $nocourses = get_string('nocourses', 'block_kent_course_overview');
            return '<div class="co_no_crs">' . $nocourses . '</div>';
        }

        $content = '';

        foreach ($courses as $course) {
            $course->visible = (int)$course->visible == 1 ? true : false;
            $context = \context_course::instance($course->id);
            $fullname = format_string($course->fullname, true, array('context' => $context));
            $shortname = format_string($course->shortname, true, array('context' => $context));

            $activities = $course->get_activities();

            // Add unavailable link.
            $listclass = '';
            if (!$course->visible) {
                $listclass .= 'course_unavailable';
            }

            // Construct link.
            $content .= '<li class="' . $listclass . '">';
            $content .= '<div class="course_details_ovrv">';

            $name = $fullname;
            if (isset($CFG->courselistshortnames) && $CFG->courselistshortnames === '1') {
                $name = $shortname . ': ' . $fullname;
            }

            $viewurl = new \moodle_url('/course/view.php', array(
                'id' => $course->id
            ));
            $content .= '<h4 class="title">' . \html_writer::link($viewurl, $name, array(
                'title' => s($fullname),
                'class' => 'course_list'
            ));

            // Check if there are any actionable notifications and show badge.
            $actions = count($activities);
            if (\has_capability('moodle/course:update', $context)) {
                $actions += \local_notifications\core::count_actions($course->id);
            }

            // Add actions badge.
            if ($actions >= 1) {
                $plural = ($actions > 1) ? "s" : "";
                $content .= '<span class="badge">' . $actions . ' action' . $plural . ' required</span>';
            }

            $content .= '</h4>';

            // Render the activities block.
            if (!empty($activities)) {
                $content .= '<div class="activity-overview">';
                foreach ($activities as $module => $activity) {
                    $activity = $this->render_activity($course, $module, $activity);
                    $content .= \html_writer::div($activity, 'alert alert-warning');
                }
                $content .= '</div>';
            }

            // Render the summary.
            $summary = $course->summary;
            if (!empty($summary)) {
                if (strlen($summary) > 250) {
                    $summary = \core_text::substr($summary, 0, 252) . '...';
                    $summary = strip_tags($summary);
                }
                $content .= '<p class="course_description">' . $summary . '</p>';
            }

            // Render the teacher block.
            $teachers = $course->get_teachers();
            if (!empty($teachers)) {
                $content .= $this->render_teachers($teachers);
            }

            $content .= '</div>';
            $content .= '</li>';
        }

        if (!empty($content)) {
            $content = \html_writer::tag('ul', $content, array(
                'id' => 'kent_course_list_overview',
                'class' => 'list-unstyled'
            ));
        }

        return $content;
    }

    /**
     * Render activity content.
     */
    public function render_activity($course, $module, $activity) {
        global $OUTPUT;

        $modulename = get_string('modulenameplural', $module);

        $url = new \moodle_url("/mod/$module/index.php", array('id' => $course->id));
        $icontext = html_writer::link($url, $OUTPUT->pix_icon('icon', $modulename, 'mod_' . $module, array(
            'class' => 'iconsmall'
        )));

        if (get_string_manager()->string_exists("activityoverview", $module)) {
            $icontext .= get_string("activityoverview", $module);
        } else {
            $icontext .= get_string("activityoverview", 'block_kent_course_overview', $modulename);
        }

        return $icontext;
    }
}
