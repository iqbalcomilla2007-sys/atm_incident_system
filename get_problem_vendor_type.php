<?php
require_once __DIR__ . '/init.php';

header('Content-Type: application/json; charset=UTF-8');

$problem_name = trim($_GET['problem_name'] ?? '');

if ($problem_name === '') {
    echo json_encode([
        'success' => false,
        'responsible_vendor_type' => ''
    ]);
    exit;
}

$incident = new Incident();
$type = $incident->getProblemVendorMapping($problem_name);

if ($type !== '') {
    echo json_encode([
        'success' => true,
        'responsible_vendor_type' => $type
    ]);
} else {
    echo json_encode([
        'success' => false,
        'responsible_vendor_type' => ''
    ]);
}
exit;
?>