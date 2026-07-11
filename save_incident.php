<?php
require_once __DIR__ . '/init.php';
Auth::requirePermission('add_incident');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request");
}

$atm_id    = strtoupper(trim($_POST['atm_id'] ?? ''));
$problem   = trim($_POST['problem'] ?? '');
$down_time = trim($_POST['down_time'] ?? '');
$responsible_vendor_name = trim($_POST['responsible_vendor_name'] ?? '');

$incidentObj = new Incident();
$result = $incidentObj->create([
    'atm_id' => $atm_id,
    'problem' => $problem,
    'down_time' => $down_time,
    'responsible_vendor_name' => $responsible_vendor_name
]);

if ($result['success']) {
    header("Location: dashboard_ajax_v2.php?saved=1");
    exit;
} else {
    if (isset($result['error']) && $result['error'] === 'duplicate') {
        header("Location: index.php?duplicate=1&atm_id=" . urlencode($result['atm_id']));
        exit;
    } else {
        die("Error saving incident: " . ($result['error'] ?? 'Unknown error'));
    }
}
?>