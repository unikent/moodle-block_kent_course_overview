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

define('AJAX_SCRIPT', true);

require(dirname(__FILE__) . '/../../../config.php');
require($CFG->dirroot . '/course/lib.php');

require_sesskey();

$id = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);

$ctx = context_course::instance($id);
require_capability('moodle/course:visibility', $ctx);

$course = $DB->get_record('course', array(
	'id' => $id
), '*', MUST_EXIST);

if ($action == 'hide') {
	$course->visible = 0;
} else {
	$course->visible = 1;
}

// Set the visibility of the course. we set the old flag when user manually changes visibility of course.
update_course($course);

echo json_encode(array(
	'result' => 'success'
));