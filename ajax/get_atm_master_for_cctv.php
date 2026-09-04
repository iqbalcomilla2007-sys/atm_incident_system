<?php
date_default_timezone_set('Asia/Dhaka');

header('Content-Type: application/json; charset=UTF-8');

require_once '../db.php';

$response = [
    'success' => false,
    'message' => 'ATM ID not found',
    'data' => null
];

$atm_id = trim($_GET['atm_id'] ?? '');

if ($atm_id === '') {
    $response['message'] = 'ATM ID required';
    echo json_encode($response);
    exit;
}

$sql = "
    SELECT
        id,
        atm_id,
        atm_name,
        branch_name,
        zone_name,
        group_no,
        machine_type
    FROM atm_master
    WHERE atm_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    $response['message'] = 'Prepare failed: ' . $conn->error;
    echo json_encode($response);
    exit;
}

$stmt->bind_param("s", $atm_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($row) {
    $response['success'] = true;
    $response['message'] = 'ATM found';
    $response['data'] = [
        'atm_master_id' => $row['id'] ?? '',
        'atm_id'        => $row['atm_id'] ?? '',
        'atm_name'      => $row['atm_name'] ?? '',
        'booth_name'    => $row['atm_name'] ?? '',
        'branch_name'   => $row['branch_name'] ?? '',
        'zone_name'     => $row['zone_name'] ?? '',
        'group_no'      => $row['group_no'] ?? '',
        'machine_type'  => $row['machine_type'] ?: 'ATM'
    ];
}

echo json_encode($response);
exit;