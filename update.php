<?php
date_default_timezone_set('Asia/Dhaka');
include 'auth_check.php';
include 'db.php';

$incident_id = (int)$_POST['incident_id'];
$atm_id = $_POST['atm_id'];
$atm_name = $_POST['atm_name'];
$down_time = $_POST['down_time'];
$problem = $_POST['problem'];
$status = $_POST['incident_status'];
$group_no = (int)$_POST['group_no'];
$user_id = $_SESSION['user_id'];

/* FILTER VALUES */
$search = $_POST['search'] ?? '';
$group_filter = $_POST['group_no'] ?? '';
$problem_filter = $_POST['problem_filter'] ?? '';

$stmt = $conn->prepare("
UPDATE atm_update SET
    atm_id=?,
    atm_name=?,
    down_time=?,
    problem=?,
    incident_status=?,
    group_no=?,
    last_modified_by=?
WHERE incident_id=?
");

$stmt->bind_param(
    "ssssssii",
    $atm_id,
    $atm_name,
    $down_time,
    $problem,
    $status,
    $group_no,
    $user_id,
    $incident_id
);

$stmt->execute();

/* REDIRECT BACK WITH FILTER */
$redirect = "dashboard.php?updated=1";

if ($search !== '') $redirect .= "&search=" . urlencode($search);
if ($group_filter !== '') $redirect .= "&group_no=" . urlencode($group_filter);
if ($problem_filter !== '') $redirect .= "&problem=" . urlencode($problem_filter);

header("Location: $redirect");
exit;