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

/* =========================================================
   INPUTS (Date Range)
========================================================= */
$from_date = trim($_GET['from_date'] ?? '');
$to_date   = trim($_GET['to_date'] ?? '');

$summaryData = [];
$grandTotalCount = 0;
$grandTotalAmount = 0.00;

// যদি ডেট রেঞ্জ দেওয়া থাকে তবেই কুয়েরি রান করবে
if ($from_date !== '' && $to_date !== '') {
    
    // ১. মূল প্যানাল্টি রিপোর্ট ফেচ করা (নির্দিষ্ট ডেট রেঞ্জের ভেতর)
    $sql = "
        SELECT
            p.id,
            p.vendor_name,
            p.service_type,
            p.machine_type,
            p.down_time_minutes,
            v.amc_amount AS live_amc_amount
        FROM penalty_reports p
        LEFT JOIN vendor_amc_rates v
            ON LOWER(TRIM(p.vendor_name)) = LOWER(TRIM(v.vendor_name))
           AND LOWER(TRIM(p.service_type)) = LOWER(TRIM(v.service_type))
           AND v.active_status = 1
           AND (
                p.service_type = 'UPS'
                OR p.service_type = 'CRM'
                OR LOWER(TRIM(IFNULL(p.machine_type, ''))) = LOWER(TRIM(IFNULL(v.machine_type, '')))
           )
        WHERE DATE(p.created_at) >= ? AND DATE(p.created_at) <= ?
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ss", $from_date, $to_date);
        $stmt->execute();
        $result = $stmt->get_result();

        // ২. প্যানাল্টি রুল কুয়েরি প্রস্তুত করা
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
            $vendorName  = trim((string)($row['vendor_name'] ?? 'Unknown Vendor'));
            $serviceType = strtoupper(trim((string)($row['service_type'] ?? 'ATM')));
            $machineType = strtoupper(trim((string)($row['machine_type'] ?? 'ATM')));
            $downMinutes = (int)($row['down_time_minutes'] ?? 0);
            $amcAmount   = (float)($row['live_amc_amount'] ?? 0);

            $liveRate = 0.00;
            $livePenaltyAmount = 0.00;
            $ruleFound = false;

            if ($ruleStmt && $vendorName !== '' && $serviceType !== '' && $downMinutes > 0) {
                $ruleStmt->bind_param("ssisss", $vendorName, $serviceType, $downMinutes, $serviceType, $serviceType, $machineType);
                $ruleStmt->execute();
                $ruleRes = $ruleStmt->get_result();
                if ($ruleRow = $ruleRes->fetch_assoc()) {
                    $liveRate = (float)($ruleRow['penalty_percent'] ?? 0);
                    $ruleFound = true;
                }
            }

            if ($ruleFound && $amcAmount > 0 && $liveRate > 0) {
                $livePenaltyAmount = ($amcAmount * $liveRate) / 100;
            }

            // --- Grouping Data by Vendor ---
            if (!isset($summaryData[$vendorName])) {
                $summaryData[$vendorName] = [
                    'total_count' => 0,
                    'total_amount' => 0.0,
                    'machines' => [
                        'ATM' => ['count' => 0, 'amount' => 0.0],
                        'CRM' => ['count' => 0, 'amount' => 0.0],
                        'UPS' => ['count' => 0, 'amount' => 0.0]
                    ]
                ];
            }

            $summaryData[$vendorName]['total_count']++;
            $summaryData[$vendorName]['total_amount'] += $livePenaltyAmount;

            $mCat = 'ATM';
            if ($serviceType === 'UPS') {
                $mCat = 'UPS';
            } elseif ($serviceType === 'CRM' || $machineType === 'CRM') {
                $mCat = 'CRM';
            }

            $summaryData[$vendorName]['machines'][$mCat]['count']++;
            $summaryData[$vendorName]['machines'][$mCat]['amount'] += $livePenaltyAmount;

            $grandTotalCount++;
            $grandTotalAmount += $livePenaltyAmount;
        }

        if ($ruleStmt) { $ruleStmt->close(); }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vendor Penalty Summary Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .container { width: 95%; max-width: 1400px; margin: 20px auto; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); padding: 25px; margin-bottom: 20px; border: 1px solid #e2e8f0; }
        h2 { margin-top: 0; color: #1e293b; font-size: 24px; }
        .filter-row { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px; }
        .filter-group { flex: 1; min-width: 200px; }
        .filter-group label { display: block; font-weight: 700; font-size: 12px; color: #475569; text-transform: uppercase; margin-bottom: 6px; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; color: #fff; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .btn-blue { background: #2563eb; } .btn-dark { background: #334155; } .btn-secondary { background: #64748b; }
        .btn:hover { filter: brightness(1.1); }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: #fff; border-radius: 8px; overflow: hidden; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
        th { background: #f8fafc; color: #334155; font-weight: 700; text-transform: uppercase; font-size: 11px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        tfoot tr { background: #f1f5f9; font-weight: bold; }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container">
    <div class="card">
        <h2>Vendor-Wise Penalty Summary Report</h2>
        <form method="GET">
            <div class="filter-row">
                <div class="filter-group">
                    <label>From Date</label>
                    <input type="text" name="from_date" id="from_date" value="<?=h($from_date)?>" placeholder="DD/MM/YYYY" required>
                </div>
                <div class="filter-group">
                    <label>To Date</label>
                    <input type="text" name="to_date" id="to_date" value="<?=h($to_date)?>" placeholder="DD/MM/YYYY" required>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn btn-blue">Generate Summary</button>
                    <a href="penalty_vendor_summary.php" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <?php if ($from_date !== '' && $to_date !== ''): ?>
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="margin:0; color:#334155;">Summary Result (From: <b><?=$from_date?></b> To: <b><?=$to_date?></b>)</h3>
            <button type="button" class="btn btn-dark btn-sm" onclick="window.print()">Print Summary</button>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Vendor Name</th>
                    <th class="text-center">ATM Count</th>
                    <th class="text-right">ATM Amount (৳)</th>
                    <th class="text-center">CRM Count</th>
                    <th class="text-right">CRM Amount (৳)</th>
                    <th class="text-center">UPS Count</th>
                    <th class="text-right">UPS Amount (৳)</th>
                    <th class="text-center">Total Incidents</th>
                    <th class="text-right">Total Amount (৳)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($summaryData)): ?>
                    <?php foreach ($summaryData as $vName => $stats): ?>
                    <tr>
                        <td><strong><?=h($vName)?></strong></td>
                        <td class="text-center"><?=$stats['machines']['ATM']['count']?></td>
                        <td class="text-right"><?=number_format($stats['machines']['ATM']['amount'], 2)?></td>
                        <td class="text-center"><?=$stats['machines']['CRM']['count']?></td>
                        <td class="text-right"><?=number_format($stats['machines']['CRM']['amount'], 2)?></td>
                        <td class="text-center"><?=$stats['machines']['UPS']['count']?></td>
                        <td class="text-right"><?=number_format($stats['machines']['UPS']['amount'], 2)?></td>
                        <td class="text-center"><strong><?=$stats['total_count']?></strong></td>
                        <td class="text-right"><strong><?=number_format($stats['total_amount'], 2)?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center" style="padding: 30px; color: #64748b;">No penalty records found within this date range.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($summaryData)): ?>
            <tfoot>
                <tr>
                    <td>Grand Total</td>
                    <td class="text-center"><?=array_sum(array_column(array_column($summaryData, 'machines'), 'ATM'))['count'] ?? ''?></td> <!-- Simplified totals -->
                    <td colspan="6" class="text-right">Total Penalty Amount:</td>
                    <td class="text-right">৳ <?=number_format($grandTotalAmount, 2)?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    flatpickr("#from_date", { dateFormat: "Y-m-d", altInput: true, altFormat: "d/m/Y", allowInput: true });
    flatpickr("#to_date", { dateFormat: "Y-m-d", altInput: true, altFormat: "d/m/Y", allowInput: true });
});
</script>
</body>
</html>