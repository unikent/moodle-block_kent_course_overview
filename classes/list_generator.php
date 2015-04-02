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
    public function get_categories($userid) {
        global $DB;

        $cache = \cache::make('block_kent_course_overview', 'data');
        $content = $cache->get('categories_' . $userid);
        if ($content !== false) {
            return $content;
        }

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
            'userid' => $userid,
            'ctxlevel' => \CONTEXT_COURSECAT
        ));

        $cache->set('categories_' . $userid, $objs);

        return $objs;
    }

    /**
     * Returns list of courses userid is enrolled in and can access.
     */
    public function get_courses($userid) {
        $cache = \cache::make('block_kent_course_overview', 'data');
        $content = $cache->get('courses_' . $userid);
        if ($content !== false) {
            return $content;
        }

        $site = get_site();
        $courses = enrol_get_users_courses($userid, false, 'id, shortname, summary, visible', 'shortname ASC');

        $objs = array();
        foreach ($courses as $course) {
            if ($course->id !== $site->id) {
                $objs[$course->id] = new list_course($course);
            }
        }

        $cache->set('courses_' . $userid, $objs);

        return $objs;
    }

    /**
     * Returns admin links.
     */
    public function get_admin_links() {
        global $USER;

        $links = array();

        $ctx = \context_system::instance();

        // Are we an admin?
        $isadmin = has_capability('moodle/site:config', $ctx);
        $rolloveradmin = $isadmin || \local_kent\User::has_course_update_role($USER->id);
        $claadmin = $isadmin || has_capability('mod/cla:manage', $ctx);
        $depadmin = $isadmin ||  \local_kent\User::is_dep_admin($USER->id);

        // Add the rollover links.
        if ($rolloveradmin) {
            $rolloveradminpath = new \moodle_url("/local/rollover/");
            $links[$rolloveradminpath . ''] = 'Rollover admin page';
        }

        // Add dep admin links.
        if ($depadmin) {
            $connectadminpath = new \moodle_url("/local/connect/");
            $links[$connectadminpath . ''] = 'Departmental administrator pages';

            $metaadminpath = new \moodle_url("/admin/tool/meta");
            $links[$metaadminpath . ''] = 'Kent meta enrolment pages';
        }

        // Add CLA links.
        if ($claadmin) {
            $clapath = new \moodle_url('/mod/cla/admin.php');
            $links[$clapath . ''] = 'CLA administration';
        }

        return $links;
    }
}