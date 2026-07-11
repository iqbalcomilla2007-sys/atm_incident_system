<?php
date_default_timezone_set('Asia/Dhaka');
include 'auth_check.php';
include 'db.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$group_filter = isset($_GET['group_no']) ? trim($_GET['group_no']) : '';
$problem_filter = isset($_GET['problem']) ? trim($_GET['problem']) : '';
$username_filter = isset($_GET['username']) ? trim($_GET['username']) : '';
$vendor_filter = isset($_GET['vendor']) ? trim($_GET['vendor']) : '';
$from_date = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

$sql = "SELECT a.problem, COUNT(*) AS total_count
        FROM atm_update a
        LEFT JOIN atm_master m ON TRIM(a.atm_id) = TRIM(m.atm_id)
        LEFT JOIN users u ON a.last_modified_by = u.id
        LEFT JOIN (
            SELECT r1.incident_id, r1.remark AS latest_remark
            FROM incident_remarks r1
            INNER JOIN (SELECT incident_id, MAX(id) AS max_id FROM incident_remarks GROUP BY incident_id) r2 ON r1.incident_id = r2.incident_id AND r1.id = r2.max_id
        ) lr ON a.incident_id = lr.incident_id
        WHERE a.incident_status = 'Open'";

$params = [];
$types = "";

if ($search !== '') {
    $sql .= " AND (
        a.atm_id LIKE ? OR
        a.atm_name LIKE ? OR
        a.atm_vendor LIKE ? OR
        a.ups_vendor LIKE ? OR
        a.problem LIKE ? OR
        m.zone_name LIKE ? OR
        u.username LIKE ? OR
        lr.latest_remark LIKE ?
    )";
    $searchLike = "%" . $search . "%";
    $params = array_merge($params, [$searchLike,$searchLike,$searchLike,$searchLike,$searchLike,$searchLike,$searchLike,$searchLike]);
    $types .= "ssssssss";
}

if ($group_filter !== '') {
    $sql .= " AND a.group_no = ?";
    $params[] = $group_filter;
    $types .= "s";
}

if ($problem_filter !== '') {
    $sql .= " AND a.problem = ?";
    $params[] = $problem_filter;
    $types .= "s";
}

if ($username_filter !== '') {
    $sql .= " AND u.username = ?";
    $params[] = $username_filter;
    $types .= "s";
}

if ($vendor_filter !== '') {
    $sql .= " AND a.responsible_vendor_name = ?";
    $params[] = $vendor_filter;
    $types .= "s";
}

if ($from_date !== '') {
    $sql .= " AND DATE(a.created_at) >= ?";
    $params[] = $from_date;
    $types .= "s";
}

if ($to_date !== '') {
    $sql .= " AND DATE(a.created_at) <= ?";
    $params[] = $to_date;
    $types .= "s";
}

$sql .= " GROUP BY a.problem ORDER BY total_count DESC, a.problem ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) die("Prepare failed: " . $conn->error);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$grandTotal = 0;
$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
    $grandTotal += (int)$row['total_count'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Problem Wise Summary</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 16px; color:#000; }
        .report-header { text-align:center; margin-bottom:8px; }
        .report-header h1, .report-header h2, .report-header p { margin:2px 0; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { border:1px solid #000; padding:6px 8px; text-align:left; }
        th { background:#f2f2f2; }
        .center { text-align:center; }
        .bold { font-weight:bold; }
        @media print {
            @page { margin: 10mm; }
            body { margin:0; }
        }
    </style>
</head>
<body>
<div class="report-header">
    <h1>Islami Bank Bangladesh PLC</h1>
    <h2>ATM Management Division, DBW</h2>
    <p>Problem Wise Summary as on <?php echo date('d-M-Y'); ?></p>
</div>

<div style="margin-bottom:8px;">
    <button onclick="window.print()">Print</button>
</div>

<table>
    <thead>
        <tr>
            <th class="center">S/L</th>
            <th>Problem</th>
            <th class="center">Total Open Incidents</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($rows) > 0) { ?>
            <?php foreach ($rows as $index => $row) { ?>
                <tr>
                    <td class="center"><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($row['problem']); ?></td>
                    <td class="center"><?php echo (int)$row['total_count']; ?></td>
                </tr>
            <?php } ?>
            <tr class="bold">
                <td colspan="2" class="center">Grand Total</td>
                <td class="center"><?php echo $grandTotal; ?></td>
            </tr>
        <?php } else { ?>
            <tr>
                <td colspan="3" class="center">No data found.</td>
            </tr>
        <?php } ?>
    </tbody>
</table>
</body>
</html>