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
 * Form for editing global kent_course_overview instances.
 *
 * @package   block_kent_course_overview
 * @category  kent_course_overview
 * @copyright 2014 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_kent_course_overview_edit_form extends block_edit_form {
    /**
     * @param MoodleQuickForm $mform
     */
    protected function specific_definition($mform) {
        global $CFG;

        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('select', 'config_admin_links', 'Show Admin Links', array(
            'yes' => get_string('yes'),
            'no' => get_string('no')
        ));
        $mform->setDefault('config_admin_links', 'yes');
    }
}