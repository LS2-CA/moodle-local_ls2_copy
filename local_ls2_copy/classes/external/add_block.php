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
 * LS2 copy external functions.
 *
 * @package   local_ls2_copy
 * @copyright 2025 ls2.io
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @link      https://ls2.io
 */

namespace local_ls2_copy\external;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once("{$CFG->libdir}/externallib.php");

/**
 * External function to add a block to a course.
 *
 * @package   local_ls2_copy
 * @copyright 2025 ls2.io
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @link      https://ls2.io
 */
class add_block extends \external_api {
    /**
     * Returns description of method parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters() {
        return new \external_function_parameters(
            [
                'courseid' => new \external_value(PARAM_INT, 'The id of the course'),
                'blockname' => new \external_value(PARAM_ALPHANUMEXT, 'Name of the block to add'),
                'blockregion' => new \external_value(
                    PARAM_ALPHANUMEXT,
                    'If defined add the new block to the specified region',
                    VALUE_OPTIONAL
                ),
            ]
        );
    }

    /**
     * Add a block to a course.
     *
     * @param int $courseid The id of the course
     * @param string $blockname Name of the block to add
     * @param null|string $blockregion If defined add the new block to the specified region
     * @return null
     */
    public static function execute($courseid, $blockname, $blockregion = null) {
        global $CFG, $DB;
        require_once($CFG->libdir . '/blocklib.php');

        // Parameter validation.
        $params = self::validate_parameters(
            self::execute_parameters(),
            [
                'courseid' => $courseid,
                'blockname' => $blockname,
                'blockregion' => $blockregion,
            ]
        );

        // Check if the course exists.
        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);

        // Check user capability.
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/site:manageblocks', $context);

        $page = new \moodle_page();
        $page->set_context($context);
        $page->set_course($course);
        $page->set_pagelayout('standard');
        $page->set_pagetype('course-view');

        $page->blocks->load_blocks(false);
        $page->blocks->create_all_block_instances();

        $addableblocks = $page->blocks->get_addable_blocks();

        if (!array_key_exists($params['blockname'], $addableblocks)) {
            throw new \moodle_exception('blocknotaddable');
        }

        $page->blocks->add_block_at_end_of_default_region($params['blockname'], $params['blockregion']);

        return null;
    }

    /**
     * Returns description of method result value.
     *
     * @return null
     */
    public static function execute_returns() {
        return null;
    }
}
