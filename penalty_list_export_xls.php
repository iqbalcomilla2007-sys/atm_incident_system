<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('manage_penalty');

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function formatMinutesToHM($minutes) {
    $minutes = (int)$minutes;
    if ($minutes <= 0) return '0 min';
    $hours = floor($minutes / 60);
    $mins  = $minutes % 60;
    if ($hours > 0 && $mins > 0) return $hours . ' hr ' . $mins . ' min';
    if ($hours > 0) return $hours . ' hr';
    return $mins . ' min';
}

function formatRateText($rate) {
    $rate = (float)$rate;
    if ($rate <= 0) return '-';
    return rtrim(rtrim(number_format($rate, 2), '0'), '.');
}

function formatMonthYearFromDate($dateValue) {
    if (empty($dateValue)) return '-';
    $ts = strtotime($dateValue);
    if (!$ts) return '-';
    return date("M'Y", $ts);
}

function calculateLiveDownMinutes($penaltyFrom, $updatedAt = null) {
    if (empty($penaltyFrom) || !strtotime($penaltyFrom)) return 0;

    $fromTs = strtotime($penaltyFrom);
    $toTs = (!empty($updatedAt) && strtotime($updatedAt)) ? strtotime($updatedAt) : time();

    if ($toTs <= $fromTs) return 0;
    return (int)floor(($toTs - $fromTs) / 60);
}

function columnExists($conn, $table, $column) {
    $table  = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);

    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($res && $res->num_rows > 0);
}

/* =========================================================
   COLUMN CHECK
========================================================= */
$hasCreatedBy = columnExists($conn, 'penalty_reports', 'created_by');
$hasUpdatedBy = columnExists($conn, 'penalty_reports', 'updated_by');
$hasUpdatedAt = columnExists($conn, 'penalty_reports', 'updated_at');

/* =========================================================
   INPUTS
========================================================= */
$from_date   = trim($_GET['from_date'] ?? '');
$to_date     = trim($_GET['to_date'] ?? '');
$vendor_name = trim($_GET['vendor_name'] ?? '');
$search      = trim($_GET['search'] ?? '');

/* =========================================================
   DYNAMIC SELECT
========================================================= */
$createdBySelect = $hasCreatedBy ? "
    p.created_by,
    uc.username AS created_by_name,
" : "
    NULL AS created_by,
    '' AS created_by_name,
";

$updatedBySelect = $hasUpdatedBy ? "
    p.updated_by,
    uu.username AS updated_by_name,
" : "
    NULL AS updated_by,
    '' AS updated_by_name,
";

$updatedAtSelect = $hasUpdatedAt ? "
    p.updated_at,
" : "
    NULL AS updated_at,
";

/* =========================================================
   MAIN QUERY
========================================================= */
$sql = "
SELECT
    p.id,
    p.penalty_id,
    p.incident_id,
    p.atm_id,
    p.incident_name,
    p.vendor_name,
    p.service_type,
    p.machine_type,
    p.penalty_from,
    p.created_at,
    $updatedAtSelect
    p.down_time_minutes,
    p.remarks,
    $createdBySelect
    $updatedBySelect
    a.atm_name,
    a.problem,
    m.zone_name,
    m.branch_name,
    v.amc_amount AS live_amc_amount
FROM penalty_reports p
LEFT JOIN atm_update a 
    ON p.incident_id = a.incident_id
LEFT JOIN atm_master m 
    ON TRIM(p.atm_id) = TRIM(m.atm_id)
";

if ($hasCreatedBy) {
    $sql .= " LEFT JOIN users uc ON p.created_by = uc.id ";
}

if ($hasUpdatedBy) {
    $sql .= " LEFT JOIN users uu ON p.updated_by = uu.id ";
}

$sql .= "
LEFT JOIN vendor_amc_rates v
    ON LOWER(TRIM(p.vendor_name)) = LOWER(TRIM(v.vendor_name))
   AND LOWER(TRIM(p.service_type)) = LOWER(TRIM(v.service_type))
   AND v.active_status = 1
   AND (
        UPPER(TRIM(p.service_type)) = 'UPS'
        OR UPPER(TRIM(p.service_type)) = 'CRM'
        OR LOWER(TRIM(IFNULL(p.machine_type, ''))) = LOWER(TRIM(IFNULL(v.machine_type, '')))
   )
WHERE 1=1
";

$params = [];
$types = '';

if ($from_date !== '') {
    $sql .= " AND DATE(p.created_at) >= ? ";
    $params[] = $from_date;
    $types .= 's';
}

if ($to_date !== '') {
    $sql .= " AND DATE(p.created_at) <= ? ";
    $params[] = $to_date;
    $types .= 's';
}

if ($vendor_name !== '') {
    $sql .= " AND p.vendor_name = ? ";
    $params[] = $vendor_name;
    $types .= 's';
}

if ($search !== '') {
    $like = '%' . $search . '%';

    $sql .= " AND (
        p.penalty_id LIKE ?
        OR CAST(p.incident_id AS CHAR) LIKE ?
        OR p.atm_id LIKE ?
        OR p.incident_name LIKE ?
        OR p.vendor_name LIKE ?
        OR p.service_type LIKE ?
        OR p.machine_type LIKE ?
        OR p.remarks LIKE ?
        OR a.atm_name LIKE ?
        OR a.problem LIKE ?
        OR m.zone_name LIKE ?
        OR m.branch_name LIKE ?
    ";

    for ($i = 0; $i < 12; $i++) {
        $params[] = $like;
        $types .= 's';
    }

    if ($hasCreatedBy) {
        $sql .= " OR uc.username LIKE ? ";
        $params[] = $like;
        $types .= 's';
    }

    if ($hasUpdatedBy) {
        $sql .= " OR uu.username LIKE ? ";
        $params[] = $like;
        $types .= 's';
    }

    $sql .= " ) ";
}

$sql .= " ORDER BY p.id DESC ";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Prepare failed: ' . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

/* =========================================================
   LIVE RULE QUERY
========================================================= */
$ruleSql = "
SELECT penalty_percent
FROM vendor_penalty_rules
WHERE active_status = 1
  AND LOWER(TRIM(vendor_name)) = LOWER(TRIM(?))
  AND LOWER(TRIM(service_type)) = LOWER(TRIM(?))
  AND ? BETWEEN from_minute AND to_minute
  AND (
        ? = 'UPS'
        OR ? = 'CRM'
        OR LOWER(TRIM(machine_type)) = LOWER(TRIM(?))
  )
ORDER BY from_minute DESC, id DESC
LIMIT 1
";

$ruleStmt = $conn->prepare($ruleSql);
if (!$ruleStmt) {
    die('Rule prepare failed: ' . $conn->error);
}

$rows = [];
$totalAmount = 0;

while ($row = $result->fetch_assoc()) {
    $vendorName  = trim((string)($row['vendor_name'] ?? ''));
    $serviceType = strtoupper(trim((string)($row['service_type'] ?? 'ATM')));
    $machineType = strtoupper(trim((string)($row['machine_type'] ?? 'ATM')));

    $downMinutes = (int)($row['down_time_minutes'] ?? 0);

    if ($downMinutes <= 0) {
        $downMinutes = calculateLiveDownMinutes(
            $row['penalty_from'] ?? '',
            $row['updated_at'] ?? null
        );
    }

    $amcAmount = (float)($row['live_amc_amount'] ?? 0);
    $ruleRate = 0.00;
    $penaltyAmount = 0.00;

    if ($vendorName !== '' && $serviceType !== '' && $downMinutes > 0) {
        $ruleStmt->bind_param(
            "ssisss",
            $vendorName,
            $serviceType,
            $downMinutes,
            $serviceType,
            $serviceType,
            $machineType
        );

        $ruleStmt->execute();
        $ruleRes = $ruleStmt->get_result();

        if ($ruleRow = $ruleRes->fetch_assoc()) {
            $ruleRate = (float)($ruleRow['penalty_percent'] ?? 0);
        }
    }

    if ($amcAmount > 0 && $ruleRate > 0) {
        $penaltyAmount = ($amcAmount * $ruleRate) / 100;
    }

    $cBy = trim((string)($row['created_by_name'] ?? ''));
    $uBy = trim((string)($row['updated_by_name'] ?? ''));

    $userDisplay = $cBy;

    if ($uBy !== '' && $uBy !== $cBy) {
        $userDisplay .= ($userDisplay !== '' ? ' / ' : '') . $uBy;
    }

    $row['display_down_time_minutes'] = $downMinutes;
    $row['display_deduction_rate'] = $ruleRate;
    $row['display_penalty_amount'] = $penaltyAmount;
    $row['month_year'] = formatMonthYearFromDate($row['penalty_from'] ?? '');
    $row['created_updated_by'] = $userDisplay !== '' ? $userDisplay : '-';

    $rows[] = $row;
    $totalAmount += $penaltyAmount;
}

$ruleStmt->close();
$stmt->close();

$filename = "penalty_report_" . date('Ymd_His') . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF";
?>
<table border="1">
    <tr>
        <th colspan="12" style="font-size:16px;">Islami Bank Bangladesh PLC</th>
    </tr>
    <tr>
        <th colspan="12">ATM Management Division, DBW</th>
    </tr>
    <tr>
        <th colspan="12">Penalty Summary Report as on <?= date('d/m/Y h:i A') ?></th>
    </tr>

    <tr>
        <th>Serial</th>
        <th>ATM ID & Name</th>
        <th>Problem</th>
        <th>Vendor Name</th>
        <th>Service Type</th>
        <th>Penalty From</th>
        <th>Updated At</th>
        <th>Down Time</th>
        <th>Deduction Rate (%)</th>
        <th>Penalty Amount</th>
        <th>Month'Year</th>
        <th>Created/Updated By</th>
    </tr>

    <?php if (empty($rows)): ?>
        <tr>
            <td colspan="12">No records found.</td>
        </tr>
    <?php else: ?>
        <?php foreach ($rows as $i => $row): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= h($row['atm_id']) ?> - <?= h($row['atm_name'] ?? '') ?></td>
                <td><?= h($row['problem'] ?? $row['incident_name'] ?? '') ?></td>
                <td><?= h($row['vendor_name']) ?></td>
                <td><?= h($row['service_type']) ?></td>
                
                <td><?= !empty($row['penalty_from']) ? h(date('d/m/Y h:i A', strtotime($row['penalty_from']))) : '-' ?></td>
                
                <td><?= !empty($row['updated_at']) ? h(date('d/m/Y h:i A', strtotime($row['updated_at']))) : '-' ?></td>
                
                <td><?= h(formatMinutesToHM($row['display_down_time_minutes'])) ?></td>
                <td><?= h(formatRateText($row['display_deduction_rate'])) ?></td>
                <td style="text-align:right;"><?= number_format((float)$row['display_penalty_amount'], 2) ?></td>
                <td><?= h($row['month_year']) ?></td>
                <td><?= h($row['created_updated_by']) ?></td>
            </tr>
        <?php endforeach; ?>

        <tr>
            <th colspan="9" style="text-align:right;">Grand Total</th>
            <th style="text-align:right;"><?= number_format($totalAmount, 2) ?></th>
            <th colspan="2"></th>
        </tr>
    <?php endif; ?>
</table>