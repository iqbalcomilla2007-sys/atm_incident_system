<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';

header('Content-Type: application/json; charset=utf-8');

$zone_name = trim($_GET['zone_name'] ?? '');

$response = [
    'success' => false,
    'branches' => [],
    'message' => ''
];

if ($zone_name === '') {
    $response['message'] = 'Zone name is required.';
    echo json_encode($response);
    exit;
}

$stmt = $conn->prepare("
    SELECT branch_name, branch_code
    FROM zone_branch_map
    WHERE zone_name = ?
    ORDER BY branch_name ASC
");

if (!$stmt) {
    $response['message'] = 'Prepare failed: ' . $conn->error;
    echo json_encode($response);
    exit;
}

$stmt->bind_param("s", $zone_name);
$stmt->execute();
$res = $stmt->get_result();

$branches = [];
while ($row = $res->fetch_assoc()) {
    $branch_name = trim((string)($row['branch_name'] ?? ''));
    $branch_code = trim((string)($row['branch_code'] ?? ''));
    if ($branch_name !== '') {
        $branches[] = [
            'branch_name' => $branch_name,
            'branch_code' => $branch_code
        ];
    }
}

$response['success'] = true;
$response['branches'] = $branches;

echo json_encode($response);
exit;