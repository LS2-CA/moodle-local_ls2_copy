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
 * External function to copy activity from course to another course.
 *
 * @package   local_ls2_copy
 * @copyright 2025 ls2.io
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @link      https://ls2.io
 */
class copy_activity extends \external_api {
    /**
     * Returns description of method parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters() {
        return new \external_function_parameters(
            [
                'fromactivityid' => new \external_value(PARAM_INT, 'The id of the activity we are importing from'),
                'tocourseid' => new \external_value(PARAM_INT, 'The id of the course we are importing to'),
                'tosectionid' => new \external_value(PARAM_INT, 'The id of the section we are importing to', VALUE_OPTIONAL),
                'tobeforemoduleid' => new \external_value(
                    PARAM_INT,
                    'The id of the module we are importing before',
                    VALUE_OPTIONAL
                ),
            ]
        );
    }

    /**
     * Copy activity from course to another course.
     *
     * @param int $fromactivityid The id of the activity we are importing from
     * @param int $tocourseid The id of the course we are importing to
     * @param null|int $tosectionid The id of the section we are importing to
     * @param null|int $tobeforemoduleid The id of the module we are importing before
     * @return array
     */
    public static function execute($fromactivityid, $tocourseid, $tosectionid = null, $tobeforemoduleid = null) {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/course/lib.php');

        // Parameter validation.
        $params = self::validate_parameters(
            self::execute_parameters(),
            [
                'tocourseid' => $tocourseid,
                'fromactivityid' => $fromactivityid,
                'tosectionid' => $tosectionid,
                'tobeforemoduleid' => $tobeforemoduleid,
            ]
        );

        // Context validation.

        $tocourse = $DB->get_record('course', ['id' => $params['tocourseid']], '*', MUST_EXIST);

        $fromactivity = $DB->get_record('course_modules', ['id' => $params['fromactivityid']], '*', MUST_EXIST);

        $tosection = null;
        if ($params['tosectionid']) {
            $tosection = $DB->get_record('course_sections', ['id' => $params['tosectionid']], '*', MUST_EXIST);

            if ($tocourse->id != $tosection->course) {
                throw new \moodle_exception('invalidcourseid', 'error');
            }
        }

        $fromactivitycontext = \context_module::instance($fromactivity->id);
        self::validate_context($fromactivitycontext);

        $tocoursecontext = \context_course::instance($tocourse->id);
        self::validate_context($tocoursecontext);

        // Capability checking.

        require_capability('moodle/backup:backuptargetimport', $fromactivitycontext);
        require_capability('moodle/restore:restoretargetimport', $tocoursecontext);

        $bc = new \backup_controller(
            \backup::TYPE_1ACTIVITY,
            $fromactivity->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );

        $backupid = $bc->get_backupid();
        $backupbasepath = $bc->get_plan()->get_basepath();

        $bc->execute_plan();
        $bc->destroy();

        // Restore the backup immediately.

        $rc = new \restore_controller(
            $backupid,
            $tocourse->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
            \backup::TARGET_EXISTING_ADDING
        );

        // Make sure that the restore_general_groups setting is always enabled when duplicating an activity.
        $plan = $rc->get_plan();
        $groupsetting = $plan->get_setting("groups");
        if (empty($groupsetting->get_value())) {
            $groupsetting->set_value(true);
        }

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

        $newactivityid = null;
        $tasks = $rc->get_plan()->get_tasks();
        foreach ($tasks as $task) {
            if (is_subclass_of($task, "restore_activity_task")) {
                if ($task->get_old_contextid() == $fromactivitycontext->id) {
                    $newactivityid = $task->get_moduleid();
                    break;
                }
            }
        }

        $rc->destroy();

        if (empty($CFG->keeptempdirectoriesonbackup)) {
            fulldelete($backupbasepath);
        }

        if ($tosection && $newactivityid) {
            $newactivity = get_coursemodule_from_id(null, $newactivityid, $tocourse->id);
            moveto_module($newactivity, $tosection, $params['tobeforemoduleid']);
        }

        return [
            'newactivityid' => $newactivityid,
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
                'newactivityid' => new \external_value(PARAM_INT, 'The id of the activity we are importing to'),
            ]
        );
    }
}
