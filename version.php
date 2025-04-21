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
 * LS2 copy plugin version specification.
 *
 * @package   local_ls2_copy
 * @copyright 2025 ls2.io
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @link      https://ls2.io
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_ls2_copy';    // Full name of the plugin (used for diagnostics).
$plugin->version   = 2025040900;        // The current plugin version (Date: YYYYMMDDXX).
$plugin->release   = 'v0.1.0';
$plugin->maturity  = MATURITY_STABLE;
$plugin->requires  = 2020061500;        // Requires this Moodle version.
