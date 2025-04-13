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
 * LS2 copy plugin external functions and service definitions.
 *
 * @package   local_ls2_copy
 * @copyright 2025 ls2.io
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @link      https://ls2.io
 */

defined('MOODLE_INTERNAL') || die();

// LS2 copy related functions.
$functions = [
    'local_ls2_copy_section' => [
        'classname'   => 'local_ls2_copy\external\copy_section',
        'methodname'  => 'execute',
        'description'  => 'Copy section from course to another course',
        'capabilities' => 'moodle/backup:backuptargetimport, moodle/restore:restoretargetimport',
        'type'         => 'write',
        'services'     => ['ls2_copy_ws'],
    ],
    'local_ls2_copy_activity' => [
        'classname'   => 'local_ls2_copy\external\copy_activity',
        'methodname'  => 'execute',
        'description'  => 'Copy activity from course to another course',
        'capabilities' => 'moodle/backup:backuptargetimport, moodle/restore:restoretargetimport',
        'type'         => 'write',
        'services'     => ['ls2_copy_ws'],
    ],
    'local_ls2_copy_block' => [
        'classname'   => 'local_ls2_copy\external\copy_block',
        'methodname'  => 'execute',
        'description'  => 'Copy block from course to another course',
        'capabilities' => 'moodle/backup:backuptargetimport, moodle/restore:restoretargetimport',
        'type'         => 'write',
        'services'     => ['ls2_copy_ws'],
    ],
    'local_ls2_copy_filters' => [
        'classname'   => 'local_ls2_copy\external\copy_filters',
        'methodname'  => 'execute',
        'description'  => 'Copy filters from course to another course',
        'capabilities' => 'moodle/backup:backuptargetimport, moodle/restore:restoretargetimport',
        'type'         => 'write',
        'services'     => ['ls2_copy_ws'],
    ],
    'local_ls2_copy_add_block' => [
        'classname'   => 'local_ls2_copy\external\add_block',
        'methodname'  => 'execute',
        'description'  => 'Add a block to course',
        'capabilities' => 'moodle/site:manageblocks',
        'type'         => 'write',
        'services'     => ['ls2_copy_ws'],
    ],
];

$services = [
    'LS2 Copy Integration'  => [
        'functions' => [
            'core_webservice_get_site_info',
            'core_course_get_contents',
            'core_block_get_course_blocks',

            // Custom functions.
            'local_ls2_copy_section',
            'local_ls2_copy_activity',
            'local_ls2_copy_block',
            'local_ls2_copy_filters',
            'local_ls2_copy_add_block',
        ],
        'enabled' => 1,
        'restrictedusers' => 1,
        'shortname' => 'ls2_copy_ws',
    ],
];
