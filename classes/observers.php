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
 * Kent Course List Block
 *
 * @package    blocks_kent_course_overview
 * @copyright  2015 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_kent_course_overview;

defined('MOODLE_INTERNAL') || die();

/**
 * Kent Course Overview observers
 */
class observers {
    /**
     * Triggered when an enrolment is updated.
     *
     * @param object $event
     */
    public static function clear_cache($event) {
        $cache = \cache::make('block_kent_course_overview', 'data');
        $cache->delete("full_" . $event->relateduserid);
        $cache->delete("categories_" . $event->relateduserid);
        $cache->delete("courses_" . $event->relateduserid);

        return true;
    }

    /**
     * Triggered when a course is updated.
     *
     * @param object $event
     */
    public static function clear_course_cache($event) {
        global $DB;

        $cache = \cache::make('block_kent_course_overview', 'data');

        // Delete cache for everyone who is related to this course (roughly).
        $rs = $DB->get_recordset_sql("
            SELECT ra.userid
            FROM {role_assignments} ra
            INNER JOIN {context} ctx
                ON ctx.id=ra.contextid
            WHERE ctx.contextlevel=:level AND ctx.instanceid=:courseid
            GROUP BY ra.userid
        ", array(
            "level" => \CONTEXT_COURSE,
            "courseid" => $event->objectid
        ));

        foreach ($rs as $user) {
            $cache->delete("full_" . $user->userid);
            $cache->delete("categories_" . $user->userid);
            $cache->delete("courses_" . $user->userid);
        }

        $rs->close();

        return true;
    }
}