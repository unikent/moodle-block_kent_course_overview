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
 * Kent Course Overview List Generator
 */
class list_generator
{
    /**
     * Returns a list of categories userid has an RA in.
     */
    public function get_categories() {
        global $DB, $USER;

        $sql = "SELECT cc.id, cc.name, cc.sortorder
                FROM {course_categories} cc
                INNER JOIN {context} c
                    ON cc.id=c.instanceid
                        AND c.contextlevel=:ctxlevel
                INNER JOIN {role_assignments} ra
                    ON ra.contextid=c.id
                WHERE ra.userid = :userid
                GROUP BY cc.id";

        $objs = $DB->get_records_sql($sql, array(
            'userid' => $USER->id,
            'ctxlevel' => \CONTEXT_COURSECAT
        ));

        return $objs;
    }

    /**
     * Returns list of courses userid is enrolled in and can access.
     */
    public function get_courses() {
        global $USER;

        // Grab courses.
        $courses = enrol_get_my_courses('*');

        // Remove $site.
        $site = get_site();
        if (array_key_exists($site->id, $courses)) {
            unset($courses[$site->id]);
        }

        // Add lastaccess.
        foreach ($courses as $c) {
            if (isset($USER->lastcourseaccess[$c->id])) {
                $courses[$c->id]->lastaccess = $USER->lastcourseaccess[$c->id];
            } else {
                $courses[$c->id]->lastaccess = 0;
            }
        }

        // Fetch mod data.
        $overviews = block_course_overview_get_overviews($courses);

        $objs = array();
        foreach ($courses as $course) {
            $lc = new list_course($course);
            if (isset($overviews[$course->id])) {
                $lc->set_overview_data($overviews[$course->id]);
            }

            $objs[$course->id] = $lc;
        }

        return $objs;
    }
}