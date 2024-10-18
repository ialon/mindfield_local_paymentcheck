<?php
namespace local_paymentcheck\task;
require_once(__DIR__ . '../../../../../config.php');
// require_once($CFG->dirroot . '/mod/quiz/locallib.php');
// require_once($CFG->dirroot . '/question/editlib.php');  
require_once($CFG->libdir . '/adminlib.php');
include_once($CFG->libdir . '/dml/moodle_database.php');
// require_once("$CFG->dirroot/enrol/locallib.php");
// require_once("$CFG->dirroot/enrol/renderer.php");
defined('MOODLE_INTERNAL') || die();

class paymentcheck extends \core\task\scheduled_task {
    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'local_paymentcheck');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;
        $objUserEnrolmentReserve = $DB->get_records_sql( '
            SELECT uer.*, e.courseid 
            FROM {user_enrolments_reserve} uer
            INNER JOIN {enrol} e ON e.id = uer.enrolid 
            WHERE uer.reservedat < UNIX_TIMESTAMP( CURRENT_TIMESTAMP - INTERVAL 2 MINUTE )
        ' );

        foreach( $objUserEnrolmentReserve as $keyUER => $valUER ){
            $courseId = $valUER->courseid;

            // look for successful payments from this student for this course within the past 30 minutes
            $objPaypalEnrol = $DB->get_record_sql( 'SELECT ep.* FROM {enrol_paypal} ep WHERE ep.courseid = ? AND ep.userid = ? AND ep.timeupdated >= UNIX_TIMESTAMP( CURRENT_TIMESTAMP - INTERVAL 30 MINUTE )', [ $courseId, $valUER->userid ] );
            $payment_successful = isset($objPaypalEnrol->payment_status) && ($objPaypalEnrol->payment_status == "Completed");

            if ($payment_successful) {
                // if payment was successful, we actually have to expire the self enrolment
                $DB->execute("UPDATE {user_enrolments} SET timeend = UNIX_TIMESTAMP( CURRENT_TIMESTAMP - INTERVAL 1 DAY ) WHERE id = :mue_id", array( "mue_id" => $valUER->mue_id ) );

                // since we already processed this payment, we can remove the reserve
                $DB->execute("DELETE FROM {user_enrolments_reserve} WHERE id = :id", [ "id" => $valUER->id ] );
            }
        }

        // if student roles were deleted while we did this transfer, we will reinstate student roles
        echo 'reinstating student role assignments for orphaned paypal enrolment records in the last 3 days';
        $DB->execute("
            INSERT INTO {role_assignments} (roleid, contextid, userid, timemodified, modifierid, `component`, itemid, sortorder)
            SELECT 5 roleid, cx.id contextid, ue.userid userid, UNIX_TIMESTAMP() timemodified, 0 modifierid, '' `component`, 0 itemid, 0 sortorder
            FROM {user_enrolments} ue 
            INNER JOIN {enrol} e ON ue.enrolid = e.id AND  e.enrol = 'paypal'
            INNER JOIN {context} cx ON cx.contextlevel = 50 AND cx.instanceid = e.courseid
            LEFT JOIN {role_assignments} ra ON ra.contextid = cx.id AND ue.userid = ra.userid
            WHERE ra.id IS NULL
            AND (ue.timeend = 0 OR ue.timeend > UNIX_TIMESTAMP())
            AND (ue.timemodified >= UNIX_TIMESTAMP(CURRENT_TIMESTAMP - INTERVAL 3 DAY))
        ");
    }
}
?>
