<?php
date_default_timezone_set('Asia/Dhaka');
include 'auth_check.php';
include 'db.php';

$vendorName = isset($_GET['vendor_name']) ? trim($_GET['vendor_name']) : '';
$vendorTypeFilter = isset($_GET['vendor_type']) ? trim($_GET['vendor_type']) : '';

if ($vendorName === '') {
    die("Vendor name is required");
}

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
        pm.responsible_vendor_type
    FROM atm_update a
    LEFT JOIN problem_master pm ON a.problem = pm.problem_name
    WHERE a.incident_status = 'Open'
    ORDER BY a.created_at ASC
";

$result = $conn->query($sql);
if (!$result) {
    die("Query failed: " . $conn->error);
}

$rows = [];
$total = 0;

while ($row = $result->fetch_assoc()) {
    $vendorType = strtoupper(trim($row['responsible_vendor_type'] ?? 'ATM'));

    if ($vendorType === 'UPS') {
        $rowVendor = trim($row['ups_vendor'] ?? '');
    } elseif ($vendorType === 'ATM') {
        $rowVendor = trim($row['atm_vendor'] ?? '');
    } else {
        $rowVendor = '';
    }

    if ($rowVendor === '' || $vendorType === 'NONE') {
        continue;
    }

    if (strcasecmp($rowVendor, $vendorName) !== 0) {
        continue;
    }

    if ($vendorTypeFilter !== '' && strcasecmp($vendorTypeFilter, $vendorType) !== 0) {
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
        $amcStmt->bind_param("ss", $rowVendor, $vendorType);
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
    $total += $penaltyAmount;

    $rows[] = [
        'incident_id' => $row['incident_id'],
        'incident_name' => $row['problem'],
        'atm_id' => $row['atm_id'],
        'atm_name' => $row['atm_name'],
        'running_down_time' => $runningDownTimeText,
        'penalty_percent' => $penaltyPercent,
        'penalty_amount' => $penaltyAmount
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Running Penalty Letter</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .report-header { text-align:center; line-height:1.4; margin-bottom:10px; }
        .report-header h1, .report-header h2, .report-header p { margin:0; }
        .letter-body { margin:15px 0; line-height:1.6; font-size:14px; }
        .signature-box { margin-top:40px; width:260px; text-align:center; }
        @media print {
            .no-print { display:none !important; }
            @page { margin:10mm; }
            .modern-table th, .modern-table td {
                border:1px solid #000 !important;
                font-size:11px !important;
                padding:4px !important;
            }
        }
    </style>
</head>
<body>
<div class="container">

    <div class="hero-header no-print">
        <div>
            <h1>Running Penalty Letter</h1>
        </div>
        <div class="hero-actions">
            <button class="btn btn-dark" onclick="window.print()">Print</button>
</div>
    </div>

    <div class="report-header">
        <h1>Islami Bank Bangladesh PLC</h1>
        <h2>ATM Management Division (DBW)</h2>
        <p>Monitoring & Support Management Department</p>
        <p><strong>Running Penalty Statement</strong></p>
    </div>

    <div class="letter-body">
        <p><strong>Date:</strong> <?php echo date('d-M-Y'); ?></p>
        <p><strong>To</strong><br><?php echo h($vendorName); ?></p>
        <p><strong>Subject:</strong> Running penalty statement based on current open incidents.</p>
        <p>Dear Sir,<br><br>
        Please find below the running penalty statement calculated on the basis of currently open incidents and present downtime.</p>
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>S/L</th>
                        <th>Incident ID</th>
                        <th>Incident</th>
                        <th>ATM ID</th>
                        <th>Booth</th>
                        <th>Running Down Time</th>
                        <th>Penalty %</th>
                        <th>Penalty Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($rows) > 0) { ?>
                        <?php foreach ($rows as $i => $r) { ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><?php echo h($r['incident_id']); ?></td>
                                <td><?php echo h($r['incident_name']); ?></td>
                                <td><?php echo h($r['atm_id']); ?></td>
                                <td><?php echo h($r['atm_name']); ?></td>
                                <td><?php echo h($r['running_down_time']); ?></td>
                                <td><?php echo number_format((float)$r['penalty_percent'], 2); ?>%</td>
                                <td><?php echo number_format((float)$r['penalty_amount'], 2); ?></td>
                            </tr>
                        <?php } ?>
                        <tr>
                            <td colspan="7" style="text-align:right;"><strong>Total</strong></td>
                            <td><strong><?php echo number_format($total, 2); ?></strong></td>
                        </tr>
                    <?php } else { ?>
                        <tr>
                            <td colspan="8" style="text-align:center;">No running penalty found for this vendor.</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="letter-body">
        <p>This is for your information and necessary action.</p>
    </div>

    <div class="signature-box">
        <p style="margin-bottom:40px;">&nbsp;</p>
        <p style="border-top:1px solid #000;">Authorized Signature</p>
    </div>

</div>
</body>
</html>