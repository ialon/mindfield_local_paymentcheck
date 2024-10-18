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

namespace enrol_self;

use context_course;
use enrol_self_plugin;
use enrol_paypal_plugin;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/enrol/self/lib.php');
require_once($CFG->dirroot.'/enrol/self/locallib.php');

/**
 * Self enrolment plugin tests.
 *
 * @package    enrol_self
 * @category   phpunit
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \enrol_self_plugin
 */
class expirefreetrial_test extends \advanced_testcase {

    public function test_paypal_payment() {
        global $DB;

        $this->resetAfterTest();

        /** @var enrol_self_plugin $selfplugin  */
        $selfplugin = enrol_get_plugin('self');

        /** @var enrol_paypal_plugin $paypalplugin  */
        $paypalplugin = enrol_get_plugin('paypal');

        $now = time();
        $trace = new \null_progress_trace();

        $studentrole = $DB->get_record('role', array('shortname'=>'student'));
        $this->assertNotEmpty($studentrole);

        $user1 = $this->getDataGenerator()->create_user();
        $businessuser1 = $this->getDataGenerator()->create_user(['email' => 'business1@domain.invalid']);
        $receiveruser1 = $this->getDataGenerator()->create_user(['email' => 'receiveruser1@domain.invalid']);
        $course1 = $this->getDataGenerator()->create_course();

        // Enable self enrolment
        $instance1 = $DB->get_record('enrol', array('enrol' => 'self', 'roleid'=>$studentrole->id), '*', MUST_EXIST);
        $instance1->status = ENROL_INSTANCE_ENABLED;
        $DB->update_record('enrol', $instance1);
        $this->assertEquals(ENROL_INSTANCE_ENABLED, $instance1->status);

        // Add paypal enrolment instance
        $data = array('roleid'=>$studentrole->id, 'courseid'=>$course1->id);
        $id = $paypalplugin->add_instance($course1, $data);
        $instance2 = $DB->get_record('enrol', array('id'=>$id));

        // Enrol user
        $selfplugin->enrol_user($instance1, $user1->id, $studentrole->id, $now-7*DAYSECS, $now+7*DAYSECS);

        // Learner is assigned the student role
        $this->assertEquals(ENROL_EXT_REMOVED_KEEP, $selfplugin->get_config('expiredaction'));
        $selfplugin->sync($trace, null);
        $this->assertEquals(1, $DB->count_records('user_enrolments', array('userid'=>$user1->id, 'enrolid'=>$instance1->id)));
        $this->assertEquals(1, $DB->count_records('enrol', array('enrol'=>'self', 'courseid'=>$course1->id)));
        $this->assertEquals(1, $DB->count_records('enrol', array('enrol'=>'paypal', 'courseid'=>$course1->id)));
        $this->assertEquals(1, $DB->count_records('user_enrolments'));
        $this->assertEquals(1, $DB->count_records('role_assignments'));

        // Configure expire trial settings
        $start_semester = date('yyyy-mm-dd', strtotime('first day of this month'));
        $end_trial = date('yyyy-mm-dd', strtotime('last day of this month'));
        set_config('s_local_expirefreetrial_startsemester', $start_semester, 'local_expirefreetrial');
        set_config('s_local_expirefreetrial_endtrial', $end_trial, 'local_expirefreetrial');




        // Nisarg Patel code begin
        global $CFG, $PAGE;

        $filter  = optional_param('ifilter', 0, PARAM_INT);
        $courseId = $course1->id;

        $dbman = $DB->get_manager();
        $this->assertTrue($dbman->table_exists('user_enrolments_reserve'));

        $objuserenrolments = $DB->get_record_sql('SELECT ue.* FROM {user_enrolments} ue WHERE ue.userid = ? AND ue.enrolid = (
            SELECT e.id FROM {enrol} e  WHERE enrol = ? AND courseid = ? ORDER BY timecreated DESC LIMIT 1 )', [$user1->id, 'self', $courseId]);
        //echo "<pre>";
        //print_r($objuserenrolments); die;
        $ueid = $objuserenrolments->id; // user enrolment id

        if( ( isset($objuserenrolments) ) && ( isset($objuserenrolments->id) ) ){
                $enrolRecordReserve = $DB->get_record_sql('SELECT uer.* FROM {user_enrolments_reserve} uer WHERE uer.mue_id = ?', [$objuserenrolments->id]);
                if( ( isset($enrolRecordReserve) ) && ( isset($enrolRecordReserve->id) ) ){
                        $DB->execute("UPDATE {user_enrolments_reserve} SET reservedat = :reservedat WHERE mue_id = :mue_id", array( 'reservedat' => time(), "mue_id" => $objuserenrolments->id ) );
                }
                else{
                        $timeEndToUpdate = strtotime("-1 day");
                        $DB->execute("INSERT INTO {user_enrolments_reserve} ( mue_id, status, enrolid, userid, timestart, timeend, modifierid, timecreated, timemodified, reservedat, flagused ) values ( $objuserenrolments->id, $objuserenrolments->status, $objuserenrolments->enrolid, $objuserenrolments->userid, $objuserenrolments->timestart, ".$timeEndToUpdate.", $objuserenrolments->modifierid, $objuserenrolments->timecreated, $objuserenrolments->timemodified, ".time().", 0 ) ");
                }
        }

        $ue = $DB->get_record('user_enrolments', array('id' => $ueid), '*', MUST_EXIST);
        $user = $DB->get_record('user', array('id'=>$ue->userid), '*', MUST_EXIST);
        $instance = $DB->get_record('enrol', array('id'=>$ue->enrolid), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id'=>$instance->courseid), '*', MUST_EXIST);

        $context = context_course::instance($course->id);

        // set up PAGE url first!
        $PAGE->set_url('/enrol/unenroluser.php', array('ue'=>$ueid, 'ifilter'=>$filter));

        $plugin = enrol_get_plugin($instance->enrol);

        $manager = new \course_enrolment_manager($PAGE, $course, $filter);

        $courseurl = new \moodle_url('/course/enrol.php', array('id' => $courseId));
        // Nisarg Patel code end



        // Learner is still enrolled. Nothing happened
        $this->assertEquals(ENROL_EXT_REMOVED_KEEP, $selfplugin->get_config('expiredaction'));
        $selfplugin->sync($trace, null);
        $this->assertEquals(1, $DB->count_records('user_enrolments'));
        $this->assertEquals(1, $DB->count_records('role_assignments'));

        // Reserve table has one record
        $this->assertEquals(1, $DB->count_records('user_enrolments_reserve'));

        ob_start();

        $payment_task = new \local_paymentcheck\task\paymentcheck();
        $expire_task = new \local_expirefreetrial\task\expirefreetrial();

        // Run payment_task and expire_task
        $payment_task->execute();
        $expire_task->execute();

        // Expiration date changed to $end_trial
        $userenrolment = $DB->get_record('user_enrolments', array('id'=>$ueid));
        $end_trial = strtotime($end_trial." 23:59:00");
        $this->assertEquals($end_trial, $userenrolment->timeend);

        // Learner is still enrolled. Nothing happened
        $selfplugin->sync($trace, null);
        $this->assertEquals(1, $DB->count_records('user_enrolments'));
        $this->assertEquals(1, $DB->count_records('user_enrolments_reserve'));
        $this->assertEquals(1, $DB->count_records('role_assignments'));

        // Change the reservedat to 5 minutes ago
        $reserve = $DB->get_record('user_enrolments_reserve', array('mue_id'=>$ueid));
        $reserve->reservedat = $now - 5*MINSECS;
        $DB->update_record('user_enrolments_reserve', $reserve);

        // Run payment_task and expire_task
        $payment_task->execute();
        $expire_task->execute();

        // Learner is still enrolled. Nothing happened
        $selfplugin->sync($trace, null);
        $this->assertEquals(1, $DB->count_records('user_enrolments'));
        $this->assertEquals(1, $DB->count_records('user_enrolments_reserve'));
        $this->assertEquals(1, $DB->count_records('role_assignments'));

        // Make a paypal payment
        $paypalplugin->enrol_user($instance2, $user1->id, $studentrole->id, $now, $now+7*DAYSECS);
        
        $this->create_enrol_paypal_record(
            $businessuser1,
            $receiveruser1,
            $course1,
            $user1,
            $instance2,
            'STUDENT1-IN-COURSE1-00',
            time()
        );

        // Run payment_task and expire_task
        $payment_task->execute();
        $expire_task->execute();

        // Learner is still enrolled. Nothing happened
        $selfplugin->sync($trace, null);
        $paypalplugin->sync($trace);
        $this->assertEquals(2, $DB->count_records('user_enrolments'));
        $this->assertEquals(0, $DB->count_records('user_enrolments_reserve'));
        $this->assertEquals(1, $DB->count_records('role_assignments'));

        // Only one enrolment should be active
        $this->assertEquals(1, $DB->count_records('user_enrolments', array('status'=>ENROL_USER_ACTIVE)));
        // It should be the paypal enrolment
        $this->assertEquals(1, $DB->count_records('user_enrolments', array('status'=>ENROL_USER_ACTIVE, 'enrolid'=>$instance2->id)));



        ob_end_clean();
    }


    /**
     * Helper function to create an enrol_paypal record.
     *
     * @param   \stdClass   $business The user associated with the business
     * @param   \stdClass   $receiver The user associated with the receiver
     * @param   \stdClass   $course The course to associate with
     * @param   \stdClass   $user The user associated with the student
     * @param   \stdClass   $enrol The enrolment instance
     * @param   String      $txnid The Paypal txnid to use
     * @param   int         $time The txn time
     */
    protected function create_enrol_paypal_record($business, $receiver, $course, $user, $enrol, $txnid, $time) {
        global $DB;

        $paypaldata = [
            'business'       => $business->email,
            'receiver_email' => $receiver->email,
            'receiver_id'    => 'SELLERSID',
            'item_name'      => $course->fullname,
            'courseid'       => $course->id,
            'userid'         => $user->id,
            'instanceid'     => $enrol->id,
            'payment_status' => 'Completed',
            'txn_id'         => $txnid,
            'payment_type'   => 'instant',
            'timeupdated'    => $time,
        ];
        $DB->insert_record('enrol_paypal', $paypaldata);
    }
}
