<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('generate_letter');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid incident ID");
}

$incidentId = (int)$_GET['id'];

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

$stmt = $conn->prepare("
    SELECT 
        a.incident_id,
        a.atm_id,
        a.atm_name,
        a.problem,
        a.down_time,
        a.created_at,
        a.atm_vendor,
        a.ups_vendor,
        a.group_no,
        m.zone_name,
        pm.responsible_vendor_type
    FROM atm_update a
    LEFT JOIN atm_master m ON a.atm_id = m.atm_id
    LEFT JOIN problem_master pm ON a.problem = pm.problem_name
    WHERE a.incident_id = ?
    LIMIT 1
");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $incidentId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    die("Incident not found");
}

/* ---------- ZONE ACCESS CHECK ---------- */
if (!Auth::isAdmin()) {
    $assignedZone = getUserAssignedZone();
    if ($assignedZone === '' || ($row['zone_name'] ?? '') !== $assignedZone) {
        die("Access Denied");
    }
}

$responsibleType = strtoupper(trim($row['responsible_vendor_type'] ?? 'ATM'));
$responsibleVendor = '';

if ($responsibleType === 'UPS') {
    $responsibleVendor = trim($row['ups_vendor'] ?? '');
} elseif ($responsibleType === 'ATM') {
    $responsibleVendor = trim($row['atm_vendor'] ?? '');
}

if ($responsibleType === 'NONE' || $responsibleVendor === '') {
    die("No responsible vendor found for this incident.");
}

$runningMinutes = getGrowingDownTimeMinutes($row['down_time'] ?? '', $row['created_at'] ?? date('Y-m-d H:i:s'));
$runningDownTime = formatMinutesToText($runningMinutes);

$amcAmount = 0;
$penaltyPercent = 0;
$penaltyAmount = 0;

$amcStmt = $conn->prepare("
    SELECT amc_amount
    FROM vendor_amc_rates
    WHERE vendor_name = ?
      AND vendor_type = ?
      AND active_status = 1
    LIMIT 1
");
if ($amcStmt) {
    $amcStmt->bind_param("ss", $responsibleVendor, $responsibleType);
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
if ($ruleStmt) {
    $ruleStmt->bind_param("si", $responsibleType, $runningMinutes);
    $ruleStmt->execute();
    $ruleResult = $ruleStmt->get_result();
    if ($ruleResult && $ruleResult->num_rows > 0) {
        $ruleRow = $ruleResult->fetch_assoc();
        $penaltyPercent = (float)$ruleRow['penalty_percent'];
    }
}

$penaltyAmount = ($amcAmount * $penaltyPercent) / 100;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Incident Vendor Letter</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .report-header { text-align: center; line-height: 1.4; margin-bottom: 10px; }
        .report-header h1, .report-header h2, .report-header p { margin: 0; }
        .letter-body { margin: 15px 0; line-height: 1.7; font-size: 14px; }
        .signature-box { margin-top: 40px; width: 260px; text-align: center; }
        @media print {
            .no-print { display: none !important; }
            @page { margin: 12mm 10mm; }
            .modern-table th, .modern-table td {
                border: 1px solid #000 !important;
                font-size: 12px !important;
                padding: 5px !important;
            }
        }
    </style>
</head>
<body>
<div class="container">

    <div class="hero-header no-print">
        <div>
            <h1>Incident Vendor Letter</h1>
        </div>
        <div class="hero-actions">
            <button class="btn btn-dark" onclick="window.print()">Print</button>
</div>
    </div>

    <div class="report-header">
        <h1>Islami Bank Bangladesh PLC</h1>
        <h2>ATM Management Division (DBW)</h2>
        <p>Monitoring & Support Management Department</p>
        <p><strong>Incident Notification Letter</strong></p>
    </div>

    <div class="letter-body">
        <p><strong>Date:</strong> <?php echo date('d-M-Y'); ?></p>

        <p><strong>To</strong><br>
        <?php echo h($responsibleVendor); ?></p>

        <p><strong>Subject:</strong> Regarding incident of ATM/CRM service support.</p>

        <p>
            Dear Sir,<br> Assalamu Alaikum,<br>
            You are requested to take necessary action regarding the following incident under your service responsibility.
        </p>
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Incident ID</th>
                        <th>ATM ID</th>
                        <th>Booth Name</th>
                        <th>Problem</th>
                        <th>Responsible Vendor Type</th>
                        <th>Running Down Time</th>
                        <th>Penalty %</th>
                        <th>Running Penalty</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo h($row['incident_id']); ?></td>
                        <td><?php echo h($row['atm_id']); ?></td>
                        <td><?php echo h($row['atm_name']); ?></td>
                        <td><?php echo h($row['problem']); ?></td>
                        <td><?php echo h($responsibleType); ?></td>
                        <td><?php echo h($runningDownTime); ?></td>
                        <td><?php echo number_format($penaltyPercent, 2); ?>%</td>
                        <td><?php echo number_format($penaltyAmount, 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="letter-body">
        <p>
            Please resolve the incident urgently and confirm the status to ATM Management Division.
        </p>

<p>
            Ma-assalam, <br> With Best Regards, <br>
        </p>

    </div>

    <div class="signature-box">
        <p style="margin-bottom:40px;">&nbsp;</p>
        <p style="border-top:1px solid #000;">Authorized Signature</p>
    </div>

</div>
</body>
</html>