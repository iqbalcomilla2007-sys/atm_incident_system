<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';
include 'penalty_helper.php';

Auth::requirePermission('close_incident');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid incident ID");
}

$incident_id = (int)$_GET['id'];

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$group_filter = isset($_GET['group_no']) ? trim($_GET['group_no']) : '';
$problem_filter = isset($_GET['problem']) ? trim($_GET['problem']) : '';

$last_modified_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

$incidentObj = new Incident();
$row = $incidentObj->getById($incident_id);

if (!$row) {
    die("Incident not found");
}

/* ---------- ZONE ACCESS CHECK ---------- */
if (!Auth::isAdmin()) {
    $assignedZone = getUserAssignedZone();
    if ($assignedZone === '' || ($row['zone_name'] ?? '') !== $assignedZone) {
        die("Access Denied");
    }
}

/* ---------- FILTER MATCH CHECK ---------- */
$matchesFilter = true;

if ($group_filter !== '' && (string)$row['group_no'] !== (string)$group_filter) {
    $matchesFilter = false;
}

if ($problem_filter !== '' && $row['problem'] !== $problem_filter) {
    $matchesFilter = false;
}

if ($search !== '') {
    $searchLower = strtolower($search);

    $fieldMatch =
        (strpos(strtolower($row['atm_id'] ?? ''), $searchLower) !== false) ||
        (strpos(strtolower($row['atm_name'] ?? ''), $searchLower) !== false) ||
        (strpos(strtolower($row['atm_vendor'] ?? ''), $searchLower) !== false) ||
        (strpos(strtolower($row['ups_vendor'] ?? ''), $searchLower) !== false) ||
        (strpos(strtolower($row['problem'] ?? ''), $searchLower) !== false);

    $remarkMatch = false;

    $remarkStmt = $conn->prepare("
        SELECT 1
        FROM incident_remarks
        WHERE incident_id = ?
          AND remark LIKE ?
        LIMIT 1
    ");

    if ($remarkStmt) {
        $searchLike = '%' . $search . '%';
        $remarkStmt->bind_param("is", $incident_id, $searchLike);
        $remarkStmt->execute();
        $remarkResult = $remarkStmt->get_result();
        $remarkMatch = ($remarkResult && $remarkResult->num_rows > 0);
    }

    if (!$fieldMatch && !$remarkMatch) {
        $matchesFilter = false;
    }
}

if (!$incidentObj->close($incident_id)) {
    die("Close failed");
}

/* ---------- FINAL PENALTY CREATE / UPDATE ---------- */
upsertFinalPenalty($conn, $row, $last_modified_by);

/* ---------- REDIRECT ---------- */
if ($matchesFilter && ($search !== '' || $group_filter !== '' || $problem_filter !== '')) {
    $redirect = "dashboard.php?closed=1";

    if ($search !== '') $redirect .= "&search=" . urlencode($search);
    if ($group_filter !== '') $redirect .= "&group_no=" . urlencode($group_filter);
    if ($problem_filter !== '') $redirect .= "&problem=" . urlencode($problem_filter);

    header("Location: " . $redirect);
    exit;
}

header("Location: dashboard.php?closed=1");
exit;
?>