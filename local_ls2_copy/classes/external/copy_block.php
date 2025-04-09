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
 * External function to copy block from course to another course.
 *
 * @package   local_ls2_copy
 * @copyright 2025 ls2.io
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @link      https://ls2.io
 */
class copy_block extends \external_api {
    /**
     * Returns description of method parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters() {
        return new \external_function_parameters(
            [
                'fromblockid' => new \external_value(PARAM_INT, 'The id of the block we are importing from'),
                'tocourseid' => new \external_value(PARAM_INT, 'The id of the course we are importing to'),
            ]
        );
    }

    /**
     * Copy block from course to another course.
     *
     * @param int $fromblockid The id of the block we are importing from
     * @param int $tocourseid The id of the course we are importing to
     * @return array
     */
    public static function execute($fromblockid, $tocourseid) {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/course/lib.php');

        // Parameter validation.
        $params = self::validate_parameters(
            self::execute_parameters(),
            [
                'fromblockid' => $fromblockid,
                'tocourseid' => $tocourseid,
            ]
        );

        // Context validation.

        $tocourse = $DB->get_record('course', ['id' => $params['tocourseid']], '*', MUST_EXIST);

        $fromblock = $DB->get_record('block_instances', ['id' => $params['fromblockid']], '*', MUST_EXIST);
        $fromblockcontext = \context_block::instance($fromblock->id);
        self::validate_context($fromblockcontext);
        $parents = $fromblockcontext->get_parent_contexts(true);
        $fromcourseid = null;
        foreach ($parents as $context) {
            if ($context->contextlevel == CONTEXT_COURSE) {
                $fromcourseid = $context->instanceid;
                break;
            }
        }

        $fromcourse = $DB->get_record('course', ['id' => $fromcourseid], '*', MUST_EXIST);

        $fromcoursecontext = \context_course::instance($fromcourse->id);
        self::validate_context($fromcoursecontext);

        $tocoursecontext = \context_course::instance($tocourse->id);
        self::validate_context($tocoursecontext);

        // Capability checking.

        require_capability('moodle/backup:backuptargetimport', $fromblockcontext);
        require_capability('moodle/restore:restoretargetimport', $tocoursecontext);

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $fromcourse->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );

        $settings = $bc->get_plan()->get_settings();
        foreach ($settings as $setting) {
            if ($setting->get_name() != 'blocks' && $setting->get_value() == 1) {
                $setting->set_value(0);
            }
        }

        $backupid = $bc->get_backupid();
        $backupbasepath = $bc->get_plan()->get_basepath();

        $bc->execute_plan();
        $bc->destroy();

        // Remove all blocks folders from the backup except the one we are importing.
        $fromblockfoldername = $fromblock->blockname . '_' . $fromblock->id;
        $blockfolders = glob($backupbasepath . '/course/blocks/*');
        foreach ($blockfolders as $blockfolder) {
            if (basename($blockfolder) != $fromblockfoldername) {
                fulldelete($blockfolder);
            }
        }

        // Restore the backup immediately.

        $rc = new \restore_controller(
            $backupid,
            $tocourse->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
            \backup::TARGET_EXISTING_ADDING
        );

        $rc->get_plan()->get_setting("activities")->set_value(false);
        $rc->get_plan()->get_setting("filters")->set_value(false);

        if (!$rc->execute_precheck()) {
            $precheckresults = $rc->get_precheck_results();
            if (is_array($precheckresults) && !empty($precheckresults['errors'])) {
                if (empty($CFG->keeptempdirectoriesonbackup)) {
                    fulldelete($backupbasepath);
                }

                $errorinfo = '';

                foreach ($precheckresults['errors'] as $error) {
                    $errorinfo .= $error;
                }

                if (array_key_exists('warnings', $precheckresults)) {
                    foreach ($precheckresults['warnings'] as $warning) {
                        $errorinfo .= $warning;
                    }
                }

                throw new \moodle_exception('backupprecheckerrors', 'webservice', '', $errorinfo);
            }
        }

        $rc->execute_plan();

        $newblockid = null;
        $tasks = $rc->get_plan()->get_tasks();
        foreach ($tasks as $task) {
            if (is_subclass_of($task, "restore_block_task")) {
                $newblockid = $task->get_blockid();
                break;
            }
        }

        $rc->destroy();

        if (empty($CFG->keeptempdirectoriesonbackup)) {
            fulldelete($backupbasepath);
        }

        return [
            'newblockid' => $newblockid,
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return \external_single_structure
     */
    public static function execute_returns() {
        return new \external_single_structure(
            [
                'newblockid' => new \external_value(PARAM_INT, 'The id of the block we are importing to'),
            ]
        );
    }
}
