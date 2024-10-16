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
 * Plugin settings for the local_quiz_scrambler_plugin plugin.
 *
 * @package   local_quiz_scrambler_plugin
 * Nisarg Patel
 * 18th April 2024
 */

defined('MOODLE_INTERNAL') || die();
$tasks = [
	[
		'classname' => 'local_paymentcheck\task\paymentcheck',
		'blocking' => 0,
		'minute' => '*/2',
		'hour' => '*',
		'day' => '*',
		'month' => '*',
		'dayofweek' => '*',
	],
];
?>
