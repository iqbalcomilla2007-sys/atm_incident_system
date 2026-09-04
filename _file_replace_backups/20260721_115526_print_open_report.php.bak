<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('view_dashboard');

$search = trim($_GET['search'] ?? '');
$group_filter = trim($_GET['group_no'] ?? '');
$problem_filter = trim($_GET['problem'] ?? '');
$username_filter = trim($_GET['username'] ?? '');
$vendor_filter = trim($_GET['vendor'] ?? '');
$from_date = trim($_GET['from_date'] ?? '');
$to_date = trim($_GET['to_date'] ?? '');

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$sql = "
SELECT 
    a.incident_id,
    a.atm_id,
    a.atm_name,
    a.problem,
    a.down_time,
    a.group_no,
    a.created_at,
    a.responsible_vendor_name,
    a.atm_vendor,
    a.ups_vendor,
    a.last_modified_by,
    m.zone_name,
    u.username AS modified_by_username,
    lr.remark AS latest_remark
FROM atm_update a
LEFT JOIN atm_master m ON a.atm_id = m.atm_id
LEFT JOIN users u ON a.last_modified_by = u.id
LEFT JOIN (
    SELECT r1.incident_id, r1.remark
    FROM incident_remarks r1
    INNER JOIN (
        SELECT incident_id, MAX(id) AS max_id
        FROM incident_remarks
        GROUP BY incident_id
    ) r2
    ON r1.incident_id = r2.incident_id
   AND r1.id = r2.max_id
) lr ON a.incident_id = lr.incident_id
WHERE a.incident_status = 'Open'
";

$params = [];
$types = '';

$zoneRestrict = buildZoneRestrictionClause('m');
$sql .= $zoneRestrict['sql'];

if (!empty($zoneRestrict['params'])) {
    $params = array_merge($params, $zoneRestrict['params']);
    $types .= $zoneRestrict['types'];
}

if ($search !== '') {
    $sql .= " AND (
        a.atm_id LIKE ? OR
        a.atm_name LIKE ? OR
        a.problem LIKE ? OR
        a.responsible_vendor_name LIKE ? OR
        a.atm_vendor LIKE ? OR
        a.ups_vendor LIKE ? OR
        m.zone_name LIKE ? OR
        lr.remark LIKE ? OR
        u.username LIKE ?
    )";

    for ($i = 0; $i < 9; $i++) {
        $params[] = '%' . $search . '%';
    }
    $types .= 'sssssssss';
}

if ($group_filter !== '') {
    $sql .= " AND a.group_no = ?";
    $params[] = $group_filter;
    $types .= 's';
}

if ($problem_filter !== '') {
    $sql .= " AND a.problem = ?";
    $params[] = $problem_filter;
    $types .= 's';
}

if ($username_filter !== '') {
    $sql .= " AND u.username = ?";
    $params[] = $username_filter;
    $types .= 's';
}

if ($vendor_filter !== '') {
    $sql .= " AND a.responsible_vendor_name = ?";
    $params[] = $vendor_filter;
    $types .= 's';
}

if ($from_date !== '') {
    $sql .= " AND DATE(a.created_at) >= ?";
    $params[] = $from_date;
    $types .= 's';
}

if ($to_date !== '') {
    $sql .= " AND DATE(a.created_at) <= ?";
    $params[] = $to_date;
    $types .= 's';
}

$sql .= " ORDER BY a.group_no ASC, a.created_at ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$groups = [];
while ($row = $result->fetch_assoc()) {
    $groupKey = ($row['group_no'] !== null && $row['group_no'] !== '') ? $row['group_no'] : 'No Group';
    $groups[$groupKey][] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Open Incident Report</title>
    <link rel="stylesheet" href="style.css?v=26">
    <style>
        .report-header {
            text-align: center;
            line-height: 1.4;
            margin-bottom: 14px;
        }
        .report-header h1,
        .report-header h2,
        .report-header p {
            margin: 0;
        }
        .group-print-title {
            margin: 18px 0 8px;
            font-size: 16px;
            font-weight: 700;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .container { padding: 0; }
            .form-card, .table-card {
                box-shadow: none;
                border: none;
                padding: 0;
            }
            .modern-table th,
            .modern-table td {
                border: 1px solid #000 !important;
                font-size: 11px !important;
                padding: 4px !important;
                color: #000 !important;
                background: #fff !important;
            }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="hero-header no-print">
        <div>
            <h1>Open Incident Report</h1>
        </div>
        <div class="hero-actions">
            <button class="btn btn-dark" onclick="window.print()">Print</button>
        </div>
    </div>

    <div class="report-header">
        <h1>Islami Bank Bangladesh PLC</h1>
        <h2>ATM Management Division (DBW)</h2>
        <p><strong>Open Incident Report</strong></p>
        <p>Date: <?php echo date('d-M-Y h:i A'); ?></p>
    </div>

    <?php if (empty($groups)) { ?>
        <div class="form-card">
            <strong>No open incidents found.</strong>
        </div>
    <?php } ?>

    <?php foreach ($groups as $groupNo => $rows) { ?>
        <div class="table-card">
            <div class="group-print-title">
                Group <?php echo h($groupNo); ?> (<?php echo count($rows); ?>)
            </div>

            <div class="table-wrap">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>SL</th>
                            <th>ATM ID</th>
                            <th>ATM Name</th>
                            <th>Zone</th>
                            <th>Problem</th>
                            <th>Down Time</th>
                            <th>Responsible Vendor</th>
                            <th>Created At</th>
                            <th>Modified By</th>
                            <th>Latest Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sl = 1; ?>
                        <?php foreach ($rows as $row) { ?>
                            <?php
                            $responsibleVendor = $row['responsible_vendor_name'] ?? '';
                            if ($responsibleVendor === '' || $responsibleVendor === null) {
                                $responsibleVendor = $row['atm_vendor'] ?? '';
                            }
                            if ($responsibleVendor === '' || $responsibleVendor === null) {
                                $responsibleVendor = $row['ups_vendor'] ?? '';
                            }
                            ?>
                            <tr>
                                <td><?php echo $sl++; ?></td>
                                <td><?php echo h($row['atm_id']); ?></td>
                                <td><?php echo h($row['atm_name']); ?></td>
                                <td><?php echo h($row['zone_name']); ?></td>
                                <td><?php echo h($row['problem']); ?></td>
                                <td><?php echo h($row['down_time']); ?></td>
                                <td><?php echo h($responsibleVendor ?: '-'); ?></td>
                                <td><?php echo !empty($row['created_at']) ? date('d-M-Y h:i A', strtotime($row['created_at'])) : ''; ?></td>
                                <td><?php echo h($row['modified_by_username'] ?? '-'); ?></td>
                                <td><?php echo !empty($row['latest_remark']) ? nl2br(h($row['latest_remark'])) : '-'; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php } ?>

</div>

</body>
</html>