<?php

require_once(__DIR__ . '/../../config.php');
require_once("$CFG->dirroot/enrol/locallib.php");
require_once("$CFG->dirroot/enrol/renderer.php");

$filter  = optional_param('ifilter', 0, PARAM_INT);
$courseId = $_REQUEST['id'];

$dbName = $CFG->dbname;
$chkTableExists = "SELECT count(*) AS istableexists FROM information_schema.TABLES WHERE (TABLE_SCHEMA = '".$dbName."') AND (TABLE_NAME = 'mdl_user_enrolments_reserve')";
$chkTableExists = $DB->get_records_sql($chkTableExists);

if( ( isset($chkTableExists[0]) ) && ( isset( $chkTableExists[0]->istableexists ) ) && ( $chkTableExists[0]->istableexists == 0 ) ){
	$DB->execute("CREATE TABLE mdl_user_enrolments_reserve ( id int PRIMARY KEY NOT NULL AUTO_INCREMENT, mue_id bigint(20) NOT NULL, status bigint(20) NOT NULL, enrolid bigint(20) NOT NULL, userid bigint(20) NOT NULL, timestart bigint(20) NOT NULL, timeend bigint(20) NOT NULL, modifierid bigint(20) NOT NULL, timecreated bigint(20) NOT NULL, timemodified bigint(20) NOT NULL, reservedat bigint(20) NOT NULL, flagused boolean DEFAULT false ) ");
}

$objuserenrolments = $DB->get_record_sql('SELECT ue.* FROM {user_enrolments} ue WHERE ue.userid = ? AND ue.enrolid = ( SELECT e.id FROM {enrol} e  WHERE enrol = ? AND courseid = ? )', [$USER->id, 'self', $courseId]);
$ueid = $objuserenrolments->id; // user enrolment id

if( ( isset($objuserenrolments) ) && ( isset($objuserenrolments->id) ) ){
	$enrolRecordReserve = $DB->get_record_sql('SELECT uer.* FROM {user_enrolments_reserve} uer WHERE uer.mue_id = ?', [$objuserenrolments->id]);
	if( ( isset($enrolRecordReserve) ) && ( isset($enrolRecordReserve->id) ) ){
		$DB->execute("UPDATE {user_enrolments_reserve} SET reservedat = :reservedat WHERE mue_id = :mue_id", array( 'reservedat' => time(), "mue_id" => $objuserenrolments->id ) );
	}
	else{
		$DB->execute("INSERT INTO mdl_user_enrolments_reserve ( mue_id, status, enrolid, userid, timestart, timeend, modifierid, timecreated, timemodified, reservedat, flagused ) values ( $objuserenrolments->id, $objuserenrolments->status, $objuserenrolments->enrolid, $objuserenrolments->userid, $objuserenrolments->timestart, $objuserenrolments->timeend, $objuserenrolments->modifierid, $objuserenrolments->timecreated, $objuserenrolments->timemodified, ".time().", 0 ) ");	
	}
}

$ue = $DB->get_record('user_enrolments', array('id' => $ueid), '*', MUST_EXIST);
$user = $DB->get_record('user', array('id'=>$ue->userid), '*', MUST_EXIST);
$instance = $DB->get_record('enrol', array('id'=>$ue->enrolid), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$instance->courseid), '*', MUST_EXIST);

$context = context_course::instance($course->id);

// set up PAGE url first!
$PAGE->set_url('/enrol/unenroluser.php', array('ue'=>$ueid, 'ifilter'=>$filter));

require_login($course);

$plugin = enrol_get_plugin($instance->enrol);

$manager = new course_enrolment_manager($PAGE, $course, $filter);
$table = new course_enrolment_users_table($manager, $PAGE);

$courseurl = new moodle_url('/course/enrol.php', array('id' => $courseId));


// we are not going to unenrol the self enrolment
// $plugin->unenrol_user($instance, $ue->userid);
redirect($courseurl);
