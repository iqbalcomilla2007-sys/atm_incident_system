<?php
date_default_timezone_set('Asia/Dhaka');
include 'auth_check.php';
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request");
}

$incident_id = isset($_POST['incident_id']) ? (int)$_POST['incident_id'] : 0;
$remark = isset($_POST['remark']) ? trim($_POST['remark']) : '';
$user_id = (int)$_SESSION['user_id'];

if ($incident_id <= 0) {
    die("Invalid incident ID");
}

if ($remark === '') {
    die("Remark cannot be empty");
}

$incidentObj = new Incident();
if ($incidentObj->addRemark($incident_id, $remark)) {
    header("Location: edit.php?id=" . $incident_id);
    exit;
} else {
    die("Insert failed");
}
?>