<?php
require_once __DIR__ . '/init.php';

header('Content-Type: application/json; charset=UTF-8');

function sendJson(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$atm_id = strtoupper(trim($_GET['atm_id'] ?? ''));

if ($atm_id === '') {
    sendJson([
        'success' => false,
        'message' => 'ATM ID is required.'
    ]);
}

$incident = new Incident();
$info = $incident->getAtmInfo($atm_id);

if (!$info) {
    sendJson([
        'success' => false,
        'message' => 'ATM not found.'
    ]);
}

sendJson(array_merge(['success' => true], $info));
?>