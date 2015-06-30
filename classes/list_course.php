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
 * Kent Course Overview Course
 */
class list_course
{
    /** Reference to course object. */
    private $_course;

    /** Reference to context object. */
    private $_context;

    /** Reference to array(modname => data). */
    private $_overview_data;

    /**
     * Constructor
     */
    public function __construct($course) {
        global $USER;

        // Update access times.
        $course->lastaccess = 0;
        if (isset($USER->lastcourseaccess[$course->id])) {
            $course->lastaccess = $USER->lastcourseaccess[$course->id];
        }

        $this->_course = $course;
        $this->_context = \context_course::instance($course->id);
        $this->_overview_data = array();
    }

    /**
     * Magic set override.
     */
    public function __set($name, $value) {
        $this->_course->$name = $value;
    }

    /**
     * Magic get override.
     */
    public function __get($name) {
        if ($name == "context") {
            return $this->_context;
        }

        return $this->_course->$name;
    }

    /**
     * Magic isset override.
     */
    public function __isset($name) {
        return isset($this->_course->$name);
    }

    /*
     * Function to pull in teachers linked on a course.
     */
    public function get_teachers() {
        global $CFG, $DB;

        // First find all roles that are supposed to be displayed.
        if (empty($CFG->coursecontact)) {
            return array();
        }

        $managerroles = explode(',', $CFG->coursecontact);
        $namesarray = array();

        $userfields = get_all_user_name_fields(true, 'u');
        $rusers = get_role_users($managerroles, $this->_context, true,
            'ra.id AS id, u.id AS userid, u.username, '.$userfields.', r.name AS rolename, rn.name AS rolecoursealias, r.shortname as roleshortname, r.sortorder, r.id AS roleid',
            'r.sortorder ASC, u.lastname ASC',
            'u.lastname, u.firstname');

        $canviewfullnames = has_capability('moodle/site:viewfullnames', $this->_context);
        foreach ($rusers as $ra) {
            if (isset($namesarray[$ra->userid])) {
                // Only display a user once with the higest sortorder role.
                continue;
            }

            $fullname = fullname($ra, $canviewfullnames);
            $rolename = !empty($ra->rolename) ? $ra->rolename : $ra->roleshortname;
            if (!empty($ra->rolecoursealias)) {
                $rolename = $ra->rolecoursealias;
            }

            switch ($rolename) {
                case 'editingteacher':
                    $rolename = 'Teacher';
                break;
            }

            $nameurl = new \moodle_url('/user/view.php', array(
                'id' => $ra->userid,
                'course' => SITEID
            ));

            $namesarray[$ra->userid] = s(ucwords($rolename)) . ': ' . \html_writer::link($nameurl, $fullname);
        }

        return $namesarray;
    }

    /**
     * Returns activities.
     */
    public function get_activities() {
        return $this->_overview_data;
    }

    /**
     * Set overview data.
     */
    public function set_overview_data($data) {
        $this->_overview_data = $data;
    }
}