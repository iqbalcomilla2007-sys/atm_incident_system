<?php
date_default_timezone_set('Asia/Dhaka');
include 'auth_check.php';
include 'db.php';
include 'penalty_helper.php';

if (!isset($_GET['incident_id']) || !is_numeric($_GET['incident_id'])) {
    die("Invalid incident ID");
}

$incidentId = (int)$_GET['incident_id'];
$userId = (int)($_SESSION['user_id'] ?? 0);

$stmt = $conn->prepare("
    SELECT incident_id, atm_id, atm_name, atm_vendor, ups_vendor, problem, group_no, down_time
    FROM atm_update
    WHERE incident_id = ?
    LIMIT 1
");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $incidentId);
$stmt->execute();
$result = $stmt->get_result();
$incident = $result->fetch_assoc();

if (!$incident) {
    die("Incident not found");
}

/* existing penalty create/update */
if (!upsertFinalPenalty($conn, $incident, $userId)) {
    die("Failed to create/open penalty record");
}

/* find created penalty id */
$calc = calculatePenaltyData($conn, $incident);

if ($calc['vendor_name'] === '' || $calc['vendor_type'] === 'NONE') {
    die("No responsible vendor found for this incident");
}

$findStmt = $conn->prepare("
    SELECT penalty_id
    FROM incident_penalties
    WHERE incident_id = ?
      AND vendor_name = ?
      AND vendor_type = ?
    LIMIT 1
");
if (!$findStmt) {
    die("Prepare failed: " . $conn->error);
}

$findStmt->bind_param("iss", $incidentId, $calc['vendor_name'], $calc['vendor_type']);
$findStmt->execute();
$findResult = $findStmt->get_result();
$row = $findResult->fetch_assoc();

if (!$row) {
    die("Penalty record not found after create/update");
}

header("Location: edit_penalty.php?id=" . (int)$row['penalty_id']);
exit;
?>