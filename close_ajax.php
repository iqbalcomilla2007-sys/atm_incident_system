<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

Auth::requirePermission('close_incident');

$id = (int)($_POST['id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid incident ID.'
    ]);
    exit;
}

$incidentObj = new Incident();

if ($incidentObj->close($id)) {
    echo json_encode([
        'success' => true,
        'message' => 'Incident closed successfully.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Incident not found or already closed.'
    ]);
}
exit;
?>