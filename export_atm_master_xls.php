<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('manage_atm_master');

function cleanValue($value) {
    $value = (string)$value;
    $value = str_replace(["\r", "\n", "\t"], ' ', $value);
    return trim($value);
}

$isAdmin = Auth::isAdmin();
$assignedZone = getUserAssignedZone();
$search = trim($_GET['search'] ?? '');

$filename = "atm_master_list_" . date('Ymd_His') . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

$sql = "
    SELECT
        atm_id,
        atm_name,
        ups_vendor,
        atm_model,
        atm_vendor,
        branch_name,
        group_no,
        zone_name
    FROM atm_master
    WHERE 1 = 1
";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= "
        AND (
            atm_id LIKE ?
            OR atm_name LIKE ?
            OR branch_name LIKE ?
            OR zone_name LIKE ?
            OR atm_vendor LIKE ?
            OR ups_vendor LIKE ?
        )
    ";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'ssssss';
}

if (!$isAdmin) {
    if ($assignedZone === '') {
        $sql .= " AND 1 = 0 ";
    } else {
        $sql .= " AND zone_name = ? ";
        $params[] = $assignedZone;
        $types .= 's';
    }
}

$sql .= "
    ORDER BY
        CASE WHEN group_no IS NULL OR group_no = 0 THEN 1 ELSE 0 END,
        group_no ASC,
        zone_name ASC,
        branch_name ASC,
        atm_name ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();

echo "ATM ID\tBooth Name\tUPS Vendor\tATM Model\tATM Vendor\tBranch Name\tGroup No\tZone Name\n";

while ($row = $res->fetch_assoc()) {
    echo cleanValue($row['atm_id']) . "\t";
    echo cleanValue($row['atm_name']) . "\t";
    echo cleanValue($row['ups_vendor']) . "\t";
    echo cleanValue($row['atm_model']) . "\t";
    echo cleanValue($row['atm_vendor']) . "\t";
    echo cleanValue($row['branch_name']) . "\t";
    echo cleanValue($row['group_no']) . "\t";
    echo cleanValue($row['zone_name']) . "\n";
}
exit;
?>