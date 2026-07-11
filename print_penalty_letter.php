<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('manage_penalty');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { die('Invalid ID'); }

// Fetch the penalty record with joined data
$hasCreatedBy = false;
$res = $conn->query("SHOW COLUMNS FROM penalty_reports LIKE 'created_by'");
if ($res && $res->num_rows > 0) { $hasCreatedBy = true; }

$createdBySelect = $hasCreatedBy ? "u.username AS created_by_name," : "'' AS created_by_name,";
$createdByJoin = $hasCreatedBy ? "LEFT JOIN users u ON p.created_by = u.id" : "";

$sql = "
    SELECT 
        p.*, 
        a.atm_name, 
        a.problem,
        v.amc_amount,
        $createdBySelect
        p.penalty_amount
    FROM penalty_reports p
    LEFT JOIN atm_update a ON p.incident_id = a.incident_id
    LEFT JOIN vendor_amc_rates v ON LOWER(TRIM(p.vendor_name)) = LOWER(TRIM(v.vendor_name)) 
         AND LOWER(TRIM(p.service_type)) = LOWER(TRIM(v.service_type))
    $createdByJoin
    WHERE p.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) { die('Record not found'); }

if (!function_exists('h')) {
    function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('formatMinutesToHM')) {
    function formatMinutesToHM($minutes) {
        $minutes = (int)$minutes;
        if ($minutes <= 0) return '0 min';
        $hours = floor($minutes / 60);
        $mins  = $minutes % 60;
        if ($hours > 0 && $mins > 0) return $hours . ' hr ' . $mins . ' min';
        elseif ($hours > 0) return $hours . ' hr';
        else return $mins . ' min';
    }
}

// Deduction rate logic
$displayRate = (float)($row['deduction_rate'] ?? 0);
$amcAmount = (float)($row['amc_amount'] ?? 0);
if ($displayRate <= 0 && $amcAmount > 0) {
    $displayRate = ($row['penalty_amount'] / $amcAmount) * 100;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Penalty Letter - <?=h($row['penalty_id'])?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; padding: 40px; color: #000; }
        .letter-content { width: 100%; max-width: 850px; margin: auto; }
        .date { margin-bottom: 30px; font-weight: bold; }
        .salutation { margin-bottom: 20px; font-weight: bold; }
        .subject { font-weight: bold; margin-bottom: 20px; text-decoration: underline; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        table, th, td { border: 1px solid #000; }
        th, td { padding: 10px; text-align: left; vertical-align: top; }
        th { background-color: #f9f9f9; width: 35%; }
        p { margin-bottom: 15px; }
        .btn-print {
            padding: 10px 20px;
            background: #007bff;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .letter-content { width: 100%; max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">Print Letter</button>
    </div>

    <div class="letter-content">
        <div class="salutation">
            Muhtaram,<br>
            Assalamu Alaikum,
        </div>

        <div class="subject">Subject: Execution of Penalty for ATM Service Downtime.</div>

        <p>With reference to the above-mentioned subject, we would like to inform you that a penalty has been imposed for the downtime of the following ATM machine as per the Service Level Agreement (SLA).</p>

        <p>The details of the penalty are given below:</p>

        <table>
            <tr>
                <th>Penalty ID</th>
                <td><?=h($row['penalty_id'])?></td>
            </tr>
            <tr>
                <th>ATM ID & Name</th>
                <td><?=h($row['atm_id'])?> - <?=h($row['atm_name'] ?? '')?></td>
            </tr>
            <tr>
                <th>Problem</th>
                <td><?=h($row['problem'] ?? $row['incident_name'] ?? '')?></td>
            </tr>
            <tr>
                <th>Vendor Name</th>
                <td><?=h($row['vendor_name'])?></td>
            </tr>
            <tr>
                <th>Penalty From</th>
                <td><?=h($row['penalty_from'])?></td>
            </tr>
            <tr>
                <th>Penalty Impose Date</th>
                <td><?= !empty($row['updated_at']) ? date('d-M-Y h:i A', strtotime($row['updated_at'])) : '-' ?></td>
            </tr>
            <tr>
                <th>Down Time</th>
                <td><?=formatMinutesToHM($row['down_time_minutes'])?></td>
            </tr>
            <tr>
                <th>Deduction Rate</th>
                <td><?php
                    if ($displayRate > 0) {
                        echo number_format($displayRate, 2) . '%';
                    } elseif ((float)$row['penalty_amount'] > 0 && $amcAmount <= 0) {
                        echo '<span style="color:red;" title="AMC Rate missing in database for this Vendor/Machine/Service combination.">?</span>';
                    } else {
                        echo '-';
                    }
                ?></td>
            </tr>
            <tr>
                <th>Penalty Amount</th>
                <td>BD Taka <?=number_format((float)$row['penalty_amount'], 2)?></td>
            </tr>
            <?php if (!empty($row['created_by_name'])): ?>
            <tr>
                <th>Created By</th>
                <td><?=h($row['created_by_name'])?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th>Remarks</th>
                <td><?=nl2br(h($row['remarks'] ?? ''))?></td>
            </tr>
        </table>

        <p>You are requested to take necessary action regarding the penalty mentioned above.</p>
    </div>
</body>
</html>
