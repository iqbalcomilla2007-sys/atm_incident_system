<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('manage_penalty');

if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('formatMinutesToHM')) {
    function formatMinutesToHM($minutes) {
        $minutes = (int)$minutes;
        if ($minutes <= 0) return '0 min';

        $hours = floor($minutes / 60);
        $mins  = $minutes % 60;

        if ($hours > 0 && $mins > 0) return $hours . ' hr ' . $mins . ' min';
        if ($hours > 0) return $hours . ' hr';

        return $mins . ' min';
    }
}

if (!function_exists('formatMonthYearFromDate')) {
    function formatMonthYearFromDate($dateValue) {
        if (empty($dateValue)) return '-';

        $ts = strtotime($dateValue);
        if (!$ts) return '-';

        return date("M'Y", $ts);
    }
}

if (!function_exists('formatRateText')) {
    function formatRateText($rate) {
        $rate = (float)$rate;

        if ($rate <= 0) {
            return '-';
        }

        return rtrim(rtrim(number_format($rate, 2), '0'), '.');
    }
}

function columnExists($conn, $table, $column) {
    $table  = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);

    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($res && $res->num_rows > 0);
}

/* =========================================================
   AUTO MIGRATION
========================================================= */
$columnsToAdd = [
    'updated_at'     => "DATETIME DEFAULT NULL AFTER created_at",
    'updated_by'     => "INT(11) DEFAULT NULL AFTER created_by",
    'deduction_rate' => "DECIMAL(5,2) DEFAULT 0.00 AFTER down_time_minutes"
];

foreach ($columnsToAdd as $col => $definition) {
    $res = $conn->query("SHOW COLUMNS FROM penalty_reports LIKE '$col'");
    if ($res && $res->num_rows == 0) {
        $conn->query("ALTER TABLE penalty_reports ADD COLUMN $col $definition");
    }
}

$hasCreatedBy     = columnExists($conn, 'penalty_reports', 'created_by');
$hasUpdatedBy     = columnExists($conn, 'penalty_reports', 'updated_by');
$hasUpdatedAt     = columnExists($conn, 'penalty_reports', 'updated_at');
$hasDeductionRate = columnExists($conn, 'penalty_reports', 'deduction_rate');

/* =========================================================
   INPUTS
========================================================= */
$from_date   = trim($_GET['from_date'] ?? '');
$to_date     = trim($_GET['to_date'] ?? '');
$vendor_name = trim($_GET['vendor_name'] ?? '');
$search      = trim($_GET['search'] ?? '');

/* =========================================================
   VENDOR LIST
========================================================= */
$vendorList = [];

$vRes = $conn->query("
    SELECT DISTINCT vendor_name
    FROM penalty_reports
    WHERE vendor_name IS NOT NULL
      AND vendor_name <> ''
    ORDER BY vendor_name ASC
");

if ($vRes) {
    while ($vr = $vRes->fetch_assoc()) {
        $vendorList[] = $vr['vendor_name'];
    }
}

/* =========================================================
   SELECT PARTS
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

$deductionRateSelect = $hasDeductionRate ? "
    p.deduction_rate AS saved_deduction_rate,
" : "
    0 AS saved_deduction_rate,
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
        $deductionRateSelect
        p.penalty_amount AS saved_penalty_amount,
        p.remarks,
        $createdBySelect
        $updatedBySelect
        a.atm_name,
        a.problem,
        a.incident_status,
        m.zone_name,
        m.branch_name,

        v.amc_amount AS live_amc_amount

    FROM penalty_reports p

    LEFT JOIN atm_update a 
        ON p.incident_id = a.incident_id

    LEFT JOIN atm_master m 
        ON TRIM(p.atm_id) = TRIM(m.atm_id)

    LEFT JOIN vendor_amc_rates v
        ON LOWER(TRIM(p.vendor_name)) = LOWER(TRIM(v.vendor_name))
       AND LOWER(TRIM(p.service_type)) = LOWER(TRIM(v.service_type))
       AND v.active_status = 1
       AND (
            p.service_type = 'UPS'
            OR p.service_type = 'CRM'
            OR LOWER(TRIM(IFNULL(p.machine_type, ''))) = LOWER(TRIM(IFNULL(v.machine_type, '')))
       )
";

if ($hasCreatedBy) {
    $sql .= " LEFT JOIN users uc ON p.created_by = uc.id ";
}

if ($hasUpdatedBy) {
    $sql .= " LEFT JOIN users uu ON p.updated_by = uu.id ";
}

$sql .= " WHERE 1 = 1 ";

$params = [];
$types  = '';

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

$isFiltered = ($from_date !== '' || $to_date !== '' || $vendor_name !== '' || $search !== '');
$rows = [];
$totalAmount = 0;

if ($isFiltered) {
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

while ($row = $result->fetch_assoc()) {
    $vendorName  = trim((string)($row['vendor_name'] ?? ''));
    $serviceType = strtoupper(trim((string)($row['service_type'] ?? 'ATM')));
    $machineType = strtoupper(trim((string)($row['machine_type'] ?? 'ATM')));
    $downMinutes = (int)($row['down_time_minutes'] ?? 0);
    $amcAmount   = (float)($row['live_amc_amount'] ?? 0);

    $liveRate = 0.00;
    $livePenaltyAmount = 0.00;
    $ruleFound = false;

    if ($ruleStmt && $vendorName !== '' && $serviceType !== '' && $downMinutes > 0) {
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
            $liveRate = (float)($ruleRow['penalty_percent'] ?? 0);
            $ruleFound = true;
        }
    }

    if ($ruleFound && $amcAmount > 0 && $liveRate > 0) {
        $livePenaltyAmount = ($amcAmount * $liveRate) / 100;
    } else {
        $liveRate = 0.00;
        $livePenaltyAmount = 0.00;
    }

    $row['display_deduction_rate'] = $liveRate;
    $row['display_penalty_amount'] = $livePenaltyAmount;
    $row['month_year'] = formatMonthYearFromDate($row['penalty_from'] ?? '');
    $row['rule_found'] = $ruleFound ? 1 : 0;
    $row['live_amc_amount'] = $amcAmount;

    $rows[] = $row;
    $totalAmount += $livePenaltyAmount;
} // end while

if ($ruleStmt) {
    $ruleStmt->close();
}

$stmt->close();
} // end if isFiltered
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Penalty Summary Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="style.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        .container {
            width: 98%;
            max-width: 1800px;
            margin: 20px auto;
        }

        .card {
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border-radius: 20px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
            padding: 22px;
            margin-bottom: 22px;
            border: 1px solid rgba(148, 163, 184, 0.12);
        }

        h2 {
            margin: 0 0 10px;
            font-size: 28px;
            letter-spacing: -0.025em;
            color: #0f172a;
        }

        .top-buttons {
            margin: 16px 0 22px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            padding: 12px 18px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid transparent;
            color: #fff;
            cursor: pointer;
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.14);
        }

        .btn-blue { background: linear-gradient(135deg, #2563eb, #3b82f6); }
        .btn-green { background: linear-gradient(135deg, #16a34a, #4ade80); }
        .btn-dark { background: linear-gradient(135deg, #111827, #334155); }
        .btn-secondary { background: linear-gradient(135deg, #64748b, #94a3b8); }

        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-radius: 10px;
            border: 1px solid transparent;
            font-size: 12px;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
            transition: transform .18s ease, opacity .18s ease, background .18s ease;
        }

        .btn-action:hover { transform: translateY(-1px); opacity: .95; }
        .btn-action.btn-details { background: linear-gradient(135deg, #2563eb, #3b82f6); }
        .btn-action.btn-edit { background: #0f63ff; }
        .btn-action.btn-letter { background: #14b8a6; }
        .btn-action.btn-delete { background: #dc2626; }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            align-items: end;
        }

        .filter-grid > div {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px;
        }

        .filter-grid label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
        }

        input[type="text"],
        select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            box-sizing: border-box;
            background: #f8fafc;
            font-size: 14px;
            color: #0f172a;
            transition: border-color .18s ease, box-shadow .18s ease;
        }

        input[type="text"]:focus,
        select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
            background: #fff;
        }

        .summary-box {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-top: 18px;
        }

        .summary-item {
            background: linear-gradient(180deg, #ffffff 0%, #f1f5f9 100%);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 18px;
            padding: 20px;
            min-height: 110px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .summary-item h4 {
            margin: 0 0 10px;
            color: #475569;
            font-size: 14px;
            font-weight: 700;
        }

        .summary-item .value {
            font-size: 32px;
            font-weight: 800;
            color: #0f172a;
        }

        .sticky-scroll-container {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(255, 255, 255, 0.92);
            padding: 10px 0;
            width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            border-bottom: 1px solid rgba(148, 163, 184, 0.18);
            backdrop-filter: blur(6px);
        }

        .table-scroll {
            overflow-x: auto;
            width: 100%;
        }

        .table-scroll::-webkit-scrollbar,
        .sticky-scroll-container::-webkit-scrollbar {
            height: 10px;
        }

        .table-scroll::-webkit-scrollbar-track,
        .sticky-scroll-container::-webkit-scrollbar-track {
            background: #e2e8f0;
            border-radius: 999px;
        }

        .table-scroll::-webkit-scrollbar-thumb,
        .sticky-scroll-container::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 999px;
        }

        table {
            width: 100%;
            min-width: 1200px;
            border-collapse: collapse;
            font-size: 13px;
            background: #fff;
        }

        th, td {
            border: none;
            padding: 12px 10px;
            vertical-align: top;
            text-align: left;
            min-height: 44px;
        }

        th {
            background: #eef2f7;
            color: #334155;
            font-weight: 700;
            letter-spacing: 0.01em;
            text-transform: uppercase;
            font-size: 12px;
            border-bottom: 2px solid #cbd5e1;
            white-space: nowrap;
        }

        tbody tr {
            border-bottom: 1px solid #e2e8f0;
            transition: background .2s ease;
        }

        tbody tr:hover {
            background: #f8fbff;
        }
        
        /* Highlight row when returning from Edit Page */
        tbody tr:target {
            background-color: #fffbeb !important;
            border-left: 4px solid #f59e0b;
            transition: background-color 1.5s ease-out;
        }

        td {
            color: #475569;
            background: transparent;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .nowrap {
            white-space: nowrap;
        }

        .penalty-action-cell {
            min-width: 160px;
            padding: 8px 6px !important;
        }

        .action-buttons {
            display: flex !important;
            flex-wrap: nowrap !important;
            gap: 6px !important;
            justify-content: flex-start !important;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.5);
            z-index: 1100;
            padding: 20px;
        }

        .modal-card {
            width: min(900px, 100%);
            background: #ffffff;
            border-radius: 22px;
            box-shadow: 0 32px 80px rgba(15, 23, 42, 0.18);
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.18);
        }

        .modal-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24px 24px 0;
            gap: 16px;
        }

        .modal-card-header h3 {
            margin: 0;
            font-size: 20px;
            color: #0f172a;
        }

        .modal-close {
            width: 38px;
            height: 38px;
            border: none;
            border-radius: 50%;
            background: #e2e8f0;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
            color: #0f172a;
        }

        .modal-card-body {
            padding: 0 24px 24px;
            display: grid;
            gap: 12px;
        }

        .detail-row {
            display: grid;
            grid-template-columns: 170px 1fr;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
        }

        .detail-row strong {
            color: #0f172a;
            font-weight: 700;
        }

        .modal-card-footer {
            display: flex;
            justify-content: flex-end;
            padding: 0 24px 24px;
        }

        .print-head {
            display: none;
            text-align: center;
            margin-bottom: 20px;
        }

        .print-only {
            display: none;
        }

        .warn-text {
            color: #b02a37;
            font-weight: bold;
        }

        @page {
            size: landscape;
            margin: 6mm;
        }

        @media print {
            body {
                background: #fff !important;
                zoom: 90%;
            }

            .no-print,
            .hide-print-col {
                display: none !important;
            }

            .print-only {
                display: table-cell !important;
            }

            .container {
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .card {
                box-shadow: none !important;
                padding: 0 !important;
                border: none !important;
                background: transparent !important;
            }

            .print-head {
                display: block !important;
                margin-bottom: 10px !important;
            }

            .table-scroll {
                overflow: visible !important;
            }

            table {
                font-size: 9pt !important;
                width: 100% !important;
                min-width: auto !important;
                table-layout: fixed !important;
                border-collapse: collapse !important;
                margin: 0 !important;
            }

            th, td {
                padding: 4px 3px !important;
                word-wrap: break-word !important;
                word-break: normal !important;
                white-space: normal !important;
                line-height: 1.25 !important;
                border: 1px solid #000 !important;
                vertical-align: middle !important;
            }

            th {
                background: #e9ecef !important;
                font-weight: bold !important;
                text-align: center !important;
            }

            .nowrap {
                white-space: normal !important;
            }

            .text-right {
                text-align: right !important;
            }

            .text-center {
                text-align: center !important;
            }

            tr {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            .col-sl      { width: 5% !important; }
            .col-atm     { width: 18% !important; }
            .col-prob    { width: 15% !important; }
            .col-from    { width: 11% !important; }
            .col-uat     { width: 11% !important; }
            .col-dt      { width: 10% !important; }
            .col-rate    { width: 9% !important; }
            .col-amount  { width: 11% !important; }
            .col-month   { width: 10% !important; }

            tr.screen-total {
                display: none !important;
            }

            tr.print-total {
                display: table-row !important;
            }

            .warn-text {
                color: #000 !important;
                font-weight: normal !important;
            }
        }
    </style>
</head>

<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">

    <div class="card no-print">
        <h2>Penalty Summary Report</h2>

        <?php if (($_GET['msg'] ?? '') === 'deleted'): ?>
            <div style="background:#d4edda; color:#155724; padding:10px; border-radius:6px; margin-bottom:15px; border:1px solid #c3e6cb;">
                Record deleted successfully.
            </div>
        <?php endif; ?>

        <div class="top-buttons">
            <a class="btn btn-blue" href="dashboard_ajax_v2.php">Dashboard</a>
            <a class="btn btn-blue" href="manage_vendor_amc_rates.php">Vendor AMC Rates</a>
            <a class="btn btn-blue" href="manage_vendor_penalty_rules.php">Penalty Rules</a>
            <a class="btn btn-green" href="penalty_list_export_xls.php?from_date=<?=urlencode($from_date)?>&to_date=<?=urlencode($to_date)?>&vendor_name=<?=urlencode($vendor_name)?>&search=<?=urlencode($search)?>">Export Excel</a>
            <button type="button" class="btn btn-dark" onclick="window.print()">Print</button>
        </div>

        <form method="GET">
            <div class="filter-grid">
                <div>
                    <label>From Date</label>
                    <input type="text" name="from_date" id="from_date" value="<?=h($from_date)?>" placeholder="DD/MM/YYYY">
                </div>

                <div>
                    <label>To Date</label>
                    <input type="text" name="to_date" id="to_date" value="<?=h($to_date)?>" placeholder="DD/MM/YYYY">
                </div>

                <div>
                    <label>Vendor</label>
                    <select name="vendor_name">
                        <option value="">All Vendors</option>
                        <?php foreach($vendorList as $v): ?>
                            <option value="<?=h($v)?>" <?=$vendor_name===$v?'selected':''?>>
                                <?=h($v)?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Search</label>
                    <input type="text" name="search" value="<?=h($search)?>" placeholder="ID/ATM/Vendor/User">
                </div>

                <div class="nowrap">
                    <button type="submit" class="btn btn-blue">Filter</button>
                    <a href="penalty_summary_report.php" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="summary-box">
            <div class="summary-item">
                <h4>Total Records</h4>
                <div class="value"><?=count($rows)?></div>
            </div>

            <div class="summary-item">
                <h4>Total Penalty</h4>
                <div class="value">৳ <?=number_format($totalAmount, 2)?></div>
            </div>
        </div>
    </div>

    <div class="print-head">
        <div style="font-size:22px; font-weight:bold;">Islami Bank Bangladesh PLC</div>
        <div style="font-size:16px; font-weight:bold;">ATM Management Division, DBW</div>
        <div style="font-size:14px;">Penalty Summary Report as on <?=date('d/m/Y h:i A')?></div>
    </div>

    <div class="sticky-scroll-container no-print" id="top-sticky-scroll">
        <div id="sticky-scroll-content"></div>
    </div>

    <div class="card">
        <div class="table-scroll" id="main-table-scroll">
            
            <?php
            // Prepare Return URL base for Edit links
            $queryParams = $_GET;
            unset($queryParams['msg']); // Ignore msg parameter if any
            $queryString = http_build_query($queryParams);
            $returnUrlBase = 'penalty_summary_report.php' . ($queryString ? '?' . $queryString : '');
            ?>

            <table id="penalty-table">
                <thead>
                    <tr>
                        <th class="col-sl">Serial</th>
                        <th class="col-atm">ATM ID & Name</th>
                        <th class="col-prob">Problem</th>
                        <th class="col-from">Penalty From</th>
                        <th class="col-uat">Updated At</th>
                        <th class="col-dt">Down Time</th>
                        <th class="col-rate">Deduction Rate (%)</th>
                        <th class="col-amount text-right">Penalty Amount</th>
                        <th class="col-month print-only">Month'Year</th>
                        <th class="no-print">Action</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (!$isFiltered): ?>
                    <tr>
                        <td colspan="10" class="text-center" style="padding: 40px; color: #64748b; font-size: 15px;">
                            <svg style="width: 48px; height: 48px; margin: 0 auto 10px; color: #94a3b8; display: block;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            Please apply a search or filter to view the penalty summary.
                        </td>
                    </tr>
                <?php elseif (empty($rows)): ?>
                    <tr>
                        <td colspan="10" class="text-center">No records found.</td>
                    </tr>
                <?php else: ?>

                    <?php foreach ($rows as $index => $row):

                        $cBy = trim((string)($row['created_by_name'] ?? ''));
                        $uBy = trim((string)($row['updated_by_name'] ?? ''));

                        $userDisplay = $cBy !== '' ? $cBy : '';

                        if ($uBy !== '' && $uBy !== $cBy) {
                            $userDisplay .= ($userDisplay !== '' ? '<br>' : '') . $uBy;
                        }

                        $penaltyAmount = (float)($row['display_penalty_amount'] ?? 0);
                        $displayRate   = (float)($row['display_deduction_rate'] ?? 0);

                        $rateText = formatRateText($displayRate);

                        if ((int)($row['rule_found'] ?? 0) === 0) {
                            $rateText = '<span class="warn-text" title="No active penalty rule matched this vendor/service/downtime">-</span>';
                        }

                        if ((float)($row['live_amc_amount'] ?? 0) <= 0) {
                            $penaltyAmount = 0;
                        }

                        // Date Formatting for Display
                        $penaltyFromDisplay = !empty($row['penalty_from']) ? date('d/m/Y h:i A', strtotime($row['penalty_from'])) : '-';
                        $updatedAtDisplay   = !empty($row['updated_at']) ? date('d/m/Y h:i A', strtotime($row['updated_at'])) : '-';
                        
                        // Injecting formatted dates into the JSON object for Modal use
                        $row['penalty_from_formatted'] = $penaltyFromDisplay;
                        $row['updated_at_formatted']   = $updatedAtDisplay;
                        
                        // Prepare exact URL with Anchor ID for this row
                        $rowAnchor = '#row-' . (int)$row['id'];
                        $editReturnUrl = urlencode($returnUrlBase . $rowAnchor);
                    ?>

                        <tr id="row-<?=(int)$row['id']?>">
                            <td class="col-sl text-center"><?=$index + 1?></td>

                            <td class="col-atm">
                                <?=h($row['atm_id'])?> - <?=h($row['atm_name'] ?? '')?>
                            </td>

                            <td class="col-prob">
                                <?=h($row['problem'] ?? $row['incident_name'] ?? '')?>
                            </td>

                            <td class="col-from nowrap"><?=h($penaltyFromDisplay)?></td>

                            <td class="col-uat nowrap"><?=h($updatedAtDisplay)?></td>

                            <td class="col-dt nowrap">
                                <?=formatMinutesToHM($row['down_time_minutes'])?>
                            </td>

                            <td class="col-rate text-center"><?=$rateText?></td>

                            <td class="col-amount text-right nowrap">
                                <?=number_format($penaltyAmount, 2)?>
                            </td>

                            <td class="col-month print-only text-center">
                                <?=h($row['month_year'])?>
                            </td>

                            <td class="no-print nowrap penalty-action-cell">
                                <div class="action-buttons">
                                    <button type="button" class="btn-action btn-details" data-row="<?=h(json_encode($row), ENT_QUOTES, 'UTF-8')?>" onclick="showPenaltyDetails(this)">Details</button>
                                    <a href="edit_penalty.php?id=<?=(int)$row['id']?>&return_url=<?=$editReturnUrl?>" class="btn-action btn-edit">Edit</a>
                                    <a href="print_penalty_letter.php?id=<?=(int)$row['id']?>" class="btn-action btn-letter" target="_blank">Letter</a>
                                    <a href="delete_penalty.php?id=<?=(int)$row['id']?>" class="btn-action btn-delete" onclick="return confirm('Are you sure you want to delete this record?')">Delete</a>
                                </div>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    <tr class="screen-total">
                        <th colspan="7" class="text-right">Grand Total</th>
                        <th class="text-right"><?=number_format($totalAmount, 2)?></th>
                        <th colspan="2"></th>
                    </tr>

                    <tr class="print-total" style="display:none;">
                        <th colspan="7" class="text-right">Grand Total</th>
                        <th class="text-right"><?=number_format($totalAmount, 2)?></th>
                        <th></th>
                    </tr>

                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="detailModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-card-header">
                <h3>Penalty Details</h3>
                <button type="button" class="modal-close" onclick="closePenaltyDetails()">×</button>
            </div>
            <div class="modal-card-body" id="detailModalBody"></div>
            <div class="modal-card-footer">
                <button type="button" class="btn btn-secondary" onclick="closePenaltyDetails()">Close</button>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
function formatDetailRow(label, value) {
    return '<div class="detail-row"><strong>' + label + '</strong><span>' + (value !== '' ? value : '-') + '</span></div>';
}

function formatMinutesToHM(minutes) {
    minutes = parseInt(minutes, 10) || 0;
    if (minutes <= 0) return '0 min';
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return (hours > 0 ? hours + ' hr ' : '') + mins + ' min';
}

function showPenaltyDetails(button) {
    const rowData = button.getAttribute('data-row');
    if (!rowData) return;

    let data;
    try {
        data = JSON.parse(rowData);
    } catch (e) {
        return;
    }

    const body = document.getElementById('detailModalBody');
    if (!body) return;

    const details = [];
    details.push(formatDetailRow('Penalty ID', data.penalty_id || ''));
    details.push(formatDetailRow('Incident ID', data.incident_id || ''));
    details.push(formatDetailRow('ATM ID', data.atm_id || ''));
    details.push(formatDetailRow('ATM Name', data.atm_name || ''));
    details.push(formatDetailRow('Problem', data.problem || data.incident_name || ''));
    details.push(formatDetailRow('Vendor Name', data.vendor_name || ''));
    details.push(formatDetailRow('Service Type', data.service_type || ''));
    details.push(formatDetailRow('Machine Type', data.machine_type || ''));
    
    // Using formatted dates for Modal Output
    details.push(formatDetailRow('Penalty From', data.penalty_from_formatted || ''));
    details.push(formatDetailRow('Created At', data.created_at || ''));
    details.push(formatDetailRow('Updated At', data.updated_at_formatted || ''));
    
    details.push(formatDetailRow('Zone', data.zone_name || ''));
    details.push(formatDetailRow('Branch', data.branch_name || ''));
    details.push(formatDetailRow('Down Time', data.down_time_minutes ? formatMinutesToHM(data.down_time_minutes) : ''));
    details.push(formatDetailRow('Deduction Rate (%)', data.display_deduction_rate != null ? data.display_deduction_rate : ''));
    details.push(formatDetailRow('Live AMC Amount', data.live_amc_amount != null ? data.live_amc_amount : ''));
    details.push(formatDetailRow('Penalty Amount', data.display_penalty_amount != null ? data.display_penalty_amount : ''));
    details.push(formatDetailRow('Remarks', data.remarks || ''));
    details.push(formatDetailRow('Created By', data.created_by_name || ''));
    details.push(formatDetailRow('Updated By', data.updated_by_name || ''));

    body.innerHTML = details.join('');
    document.getElementById('detailModal').style.display = 'flex';
}

function closePenaltyDetails() {
    const modal = document.getElementById('detailModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    
    // Flatpickr Initialization for Filter Dates (DD/MM/YYYY)
    flatpickr("#from_date", {
        dateFormat: "Y-m-d",      // Database format
        altInput: true,
        altFormat: "d/m/Y",       // User Display format
        allowInput: true
    });
    
    flatpickr("#to_date", {
        dateFormat: "Y-m-d",      // Database format
        altInput: true,
        altFormat: "d/m/Y",       // User Display format
        allowInput: true
    });

    // Sticky Scroll Logic
    const topScroll = document.getElementById('top-sticky-scroll');
    const scrollContent = document.getElementById('sticky-scroll-content');
    const bottomScroll = document.getElementById('main-table-scroll');
    const table = document.getElementById('penalty-table');

    function syncWidth() {
        if (scrollContent && table) {
            scrollContent.style.width = table.scrollWidth + 'px';
        }
    }

    syncWidth();
    window.addEventListener('resize', syncWidth);

    if (topScroll && bottomScroll) {
        topScroll.onscroll = function() {
            bottomScroll.scrollLeft = topScroll.scrollLeft;
        };

        bottomScroll.onscroll = function() {
            topScroll.scrollLeft = bottomScroll.scrollLeft;
        };
    }

    const modal = document.getElementById('detailModal');
    modal?.addEventListener('click', function(event) {
        if (event.target === modal) {
            closePenaltyDetails();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closePenaltyDetails();
        }
    });
});
</script>

</body>
</html>