<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('manage_penalty');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$vendor_filter = isset($_GET['vendor_name']) ? trim($_GET['vendor_name']) : '';
$vendor_type_filter = isset($_GET['vendor_type']) ? trim($_GET['vendor_type']) : '';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function parseManualMinutes($text) {
    $text = strtolower(trim((string)$text));
    if ($text === '') return 0;

    $days = 0;
    $hours = 0;
    $mins = 0;

    if (preg_match('/(\d+)\s*day/', $text, $m)) $days = (int)$m[1];
    if (preg_match('/(\d+)\s*hour/', $text, $m)) $hours = (int)$m[1];
    if (preg_match('/(\d+)\s*min/', $text, $m)) $mins = (int)$m[1];

    return ($days * 1440) + ($hours * 60) + $mins;
}

function formatMinutesToText($minutes) {
    $minutes = max(0, (int)$minutes);

    $days = intdiv($minutes, 1440);
    $minutes %= 1440;
    $hours = intdiv($minutes, 60);
    $mins = $minutes % 60;

    $parts = [];
    if ($days > 0) $parts[] = $days . ' day';
    if ($hours > 0) $parts[] = $hours . ' hour';
    $parts[] = $mins . ' min';

    return implode(' ', $parts);
}

function getGrowingDownTimeMinutes($manualDownTime, $createdAt) {
    $baseMinutes = parseManualMinutes($manualDownTime);

    try {
        $created = new DateTime($createdAt, new DateTimeZone('Asia/Dhaka'));
        $now = new DateTime('now', new DateTimeZone('Asia/Dhaka'));
        $diffSeconds = max(0, $now->getTimestamp() - $created->getTimestamp());
        $extraMinutes = (int)floor($diffSeconds / 60);
    } catch (Exception $e) {
        $extraMinutes = 0;
    }

    return $baseMinutes + $extraMinutes;
}

$sql = "
    SELECT 
        a.incident_id,
        a.atm_id,
        a.atm_name,
        a.problem,
        a.down_time,
        a.created_at,
        a.atm_vendor,
        a.ups_vendor,
        m.zone_name,
        pm.responsible_vendor_type
    FROM atm_update a
    LEFT JOIN atm_master m ON a.atm_id = m.atm_id
    LEFT JOIN problem_master pm ON a.problem = pm.problem_name
    WHERE a.incident_status = 'Open'
";

$params = [];
$types = '';

/* ---------- ZONE RESTRICTION ---------- */
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
        a.atm_vendor LIKE ? OR
        a.ups_vendor LIKE ? OR
        m.zone_name LIKE ?
    )";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'ssssss';
}

$sql .= " ORDER BY a.created_at ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

$result = $stmt->get_result();

$rows = [];
$grandTotal = 0;

while ($row = $result->fetch_assoc()) {
    $vendorType = strtoupper(trim($row['responsible_vendor_type'] ?? 'ATM'));

    if ($vendorType === 'UPS') {
        $vendorName = trim($row['ups_vendor'] ?? '');
    } elseif ($vendorType === 'ATM') {
        $vendorName = trim($row['atm_vendor'] ?? '');
    } else {
        $vendorName = '';
    }

    if ($vendorName === '' || $vendorType === 'NONE') {
        continue;
    }

    if ($vendor_filter !== '' && stripos($vendorName, $vendor_filter) === false) {
        continue;
    }

    if ($vendor_type_filter !== '' && strcasecmp($vendor_type_filter, $vendorType) !== 0) {
        continue;
    }

    $runningMinutes = getGrowingDownTimeMinutes($row['down_time'] ?? '', $row['created_at'] ?? date('Y-m-d H:i:s'));
    $runningDownTimeText = formatMinutesToText($runningMinutes);

    $amcStmt = $conn->prepare("
        SELECT amc_amount
        FROM vendor_amc_rates
        WHERE vendor_name = ?
          AND vendor_type = ?
          AND active_status = 1
        LIMIT 1
    ");

    $amcAmount = 0;
    if ($amcStmt) {
        $amcStmt->bind_param("ss", $vendorName, $vendorType);
        $amcStmt->execute();
        $amcResult = $amcStmt->get_result();
        if ($amcResult && $amcResult->num_rows > 0) {
            $amcRow = $amcResult->fetch_assoc();
            $amcAmount = (float)$amcRow['amc_amount'];
        }
    }

    $ruleStmt = $conn->prepare("
        SELECT penalty_percent
        FROM vendor_penalty_rules
        WHERE vendor_type = ?
          AND active_status = 1
          AND ? BETWEEN from_minute AND to_minute
        ORDER BY from_minute DESC
        LIMIT 1
    ");

    $penaltyPercent = 0;
    if ($ruleStmt) {
        $ruleStmt->bind_param("si", $vendorType, $runningMinutes);
        $ruleStmt->execute();
        $ruleResult = $ruleStmt->get_result();
        if ($ruleResult && $ruleResult->num_rows > 0) {
            $ruleRow = $ruleResult->fetch_assoc();
            $penaltyPercent = (float)$ruleRow['penalty_percent'];
        }
    }

    $penaltyAmount = ($amcAmount * $penaltyPercent) / 100;
    $grandTotal += $penaltyAmount;

    $rows[] = [
        'vendor_name' => $vendorName,
        'vendor_type' => $vendorType,
        'incident_id' => $row['incident_id'],
        'incident_name' => $row['problem'],
        'atm_id' => $row['atm_id'],
        'atm_name' => $row['atm_name'],
        'zone_name' => $row['zone_name'],
        'down_time' => $runningDownTimeText,
        'amc_amount' => $amcAmount,
        'penalty_percent' => $penaltyPercent,
        'penalty_amount' => $penaltyAmount
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Running Penalty Report</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">

    <div class="hero-header">
        <div>
            <h1>Running Penalty Report</h1>
            <p>Open incidents based live vendor penalty calculation</p>
        </div>
        <div class="hero-actions">
            <a class="btn btn-secondary" href="ignored_penalties.php">Ignored Penalties</a>

            <?php if (Auth::hasPermission('manage_problem_master')) { ?>

            <?php } ?>
</div>
    </div>

    <div class="form-card">
        <form method="GET" class="modern-filter">
            <input type="text" name="search" placeholder="Search ATM ID / Booth / Problem / Vendor / Zone" value="<?php echo h($search); ?>">
            <input type="text" name="vendor_name" placeholder="Vendor Name" value="<?php echo h($vendor_filter); ?>">

            <select name="vendor_type">
                <option value="">All Vendor Types</option>
                <option value="ATM" <?php if ($vendor_type_filter === 'ATM') echo 'selected'; ?>>ATM</option>
                <option value="UPS" <?php if ($vendor_type_filter === 'UPS') echo 'selected'; ?>>UPS</option>
            </select>

            <button type="submit" class="btn">Search</button>
            <a class="btn btn-secondary" href="running_penalty_report.php">Reset</a>
        </form>
    </div>

    <div class="form-card">
        <strong>Total Running Penalty: <?php echo number_format($grandTotal, 2); ?></strong>
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>S/L</th>
                        <th>Vendor Name</th>
                        <th>Vendor Type</th>
                        <th>Incident ID</th>
                        <th>Incident Name</th>
                        <th>ATM ID</th>
                        <th>Booth Name</th>
                        <th>Zone</th>
                        <th>Down Time</th>
                        <th>AMC Amount</th>
                        <th>Penalty %</th>
                        <th>Penalty Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($rows) > 0) { ?>
                        <?php foreach ($rows as $index => $row) { ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo h($row['vendor_name']); ?></td>
                                <td><?php echo h($row['vendor_type']); ?></td>
                                <td><?php echo h($row['incident_id']); ?></td>
                                <td><?php echo h($row['incident_name']); ?></td>
                                <td><?php echo h($row['atm_id']); ?></td>
                                <td><?php echo h($row['atm_name']); ?></td>
                                <td><?php echo h($row['zone_name']); ?></td>
                                <td><?php echo h($row['down_time']); ?></td>
                                <td><?php echo number_format((float)$row['amc_amount'], 2); ?></td>
                                <td><?php echo number_format((float)$row['penalty_percent'], 2); ?>%</td>
                                <td><?php echo number_format((float)$row['penalty_amount'], 2); ?></td>
                                <td class="action-cell">
                                    <a class="link-btn edit-btn"
                                       href="open_running_penalty_edit.php?incident_id=<?php echo (int)$row['incident_id']; ?>">
                                       Edit
                                    </a>

                                    <a class="link-btn"
                                       href="print_running_penalty_letter.php?vendor_name=<?php echo urlencode($row['vendor_name']); ?>&vendor_type=<?php echo urlencode($row['vendor_type']); ?>"
                                       target="_blank">
                                       Letter
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="13" class="text-center">No running penalty data found.</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>