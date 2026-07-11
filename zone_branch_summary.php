<?php
require_once __DIR__ . '/init.php';
Auth::requireLogin(); // General login required, maybe manage_atm permission? We can just requireLogin.

$conn = Database::getInstance()->getConnection();

$sql = "
    SELECT 
        TRIM(zone_name) as zone, 
        TRIM(branch_name) as branch, 
        SUM(CASE WHEN atm_id LIKE 'IBBL%' OR atm_id LIKE 'IBOA%' THEN 1 ELSE 0 END) as atm_cnt,
        SUM(CASE WHEN atm_id LIKE 'IBCR%' THEN 1 ELSE 0 END) as crm_cnt,
        COUNT(*) as total_cnt
    FROM atm_master 
    WHERE zone_name IS NOT NULL AND TRIM(zone_name) != ''
    GROUP BY TRIM(zone_name), TRIM(branch_name)
    ORDER BY TRIM(zone_name) ASC, TRIM(branch_name) ASC
";
$result = $conn->query($sql);

$zonesData = [];
$total_atm = 0;
$total_crm = 0;
$total_all = 0;

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $zone = $row['zone'] ?: 'Unknown Zone';
        $branch = $row['branch'] ?: 'Unknown Branch';
        if (!isset($zonesData[$zone])) {
            $zonesData[$zone] = [];
        }
        $zonesData[$zone][] = [
            'branch' => $branch,
            'atm' => (int)$row['atm_cnt'],
            'crm' => (int)$row['crm_cnt'],
            'total' => (int)$row['total_cnt']
        ];
        $total_atm += (int)$row['atm_cnt'];
        $total_crm += (int)$row['crm_cnt'];
        $total_all += (int)$row['total_cnt'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Zone & Branch Summary Report</title>
    <style>
        :root { --primary: #2563eb; --dark: #0f172a; --bg: #f8fafc; }
        body { font-family: 'Inter', Arial, sans-serif; background: var(--bg); margin: 20px; color: #334155; font-size: 14px; }
        .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 20px; max-width: 900px; margin-left: auto; margin-right: auto; }
        
        .btn { display: inline-block; padding: 9px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; cursor: pointer; border: none; font-size: 13.5px; transition: 0.2s; }
        .btn-blue { background: var(--primary); color: #fff; }
        .btn-outline { border: 1px solid #cbd5e1; color: var(--dark); background: transparent; }
        .btn:hover { opacity: 0.9; }

        .header-title { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 20px; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; border: 1px solid #cbd5e1; text-align: center; }
        th { background: #f1f5f9; font-weight: 700; color: var(--dark); }
        .total-row { font-weight: bold; background: #e2e8f0; color: #000; }
        .zone-total { font-weight: bold; background: #f8fafc; }

        /* Report Specific Styles */
        .report-header { text-align: center; margin-bottom: 20px; line-height: 1.5; }
        .report-header h2, .report-header h3, .report-header h4 { margin: 0; color: #000; }
        
        @media print {
            body { background: #fff; margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .card { box-shadow: none; max-width: 100%; padding: 0; border: none; margin: 0; }
            table { font-size: 13px; }
            th, td { border: 1px solid #000; padding: 8px; color: #000; }
            th { background: #e5e7eb !important; }
            @page { margin: 15mm; }
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="card no-print">
    <div class="header-title">
        <h2 style="margin:0;">Zone & Branch ATM/CRM Summary</h2>
        <div>
            <a href="manage_atm_master.php" class="btn btn-outline">Back to ATM Master</a>
            <button onclick="window.print()" class="btn btn-blue">Print Report</button>
        </div>
    </div>
</div>

<div class="card" id="printArea">
    <div class="report-header">
        <h2>Islami Bank Bangladesh PLC</h2>
        <h3>ATM Management Division, DBW, HO</h3>
        <h4>Monitoring & Support Management Department</h4>
        <h4 style="margin-top: 10px; text-decoration: underline;">Zone & Branch Wise ATM/CRM Summary</h4>
    </div>

    <table>
        <thead>
            <tr>
                <th style="text-align: left;">Zone Name</th>
                <th style="text-align: left;">Branch Name</th>
                <th>Total ATM</th>
                <th>Total CRM</th>
                <th>Total Machines</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            foreach ($zonesData as $zone => $branches): 
                $rowspan = count($branches);
                
                $z_atm = 0; $z_crm = 0; $z_total = 0;
                foreach ($branches as $idx => $b) {
                    $z_atm += $b['atm'];
                    $z_crm += $b['crm'];
                    $z_total += $b['total'];
                }
                
                // Print branches
                foreach ($branches as $idx => $b):
            ?>
                <tr>
                    <?php if ($idx === 0): ?>
                        <td rowspan="<?= $rowspan ?>" style="vertical-align: middle; font-weight: bold; background: #fff; text-align: left;"><?= htmlspecialchars($zone) ?></td>
                    <?php endif; ?>
                    <td style="text-align: left;"><?= htmlspecialchars($b['branch']) ?></td>
                    <td><?= $b['atm'] ?></td>
                    <td><?= $b['crm'] ?></td>
                    <td style="font-weight: bold;"><?= $b['total'] ?></td>
                </tr>
            <?php 
                endforeach; 
                // Print Zone Subtotal
            ?>
                <tr class="zone-total">
                    <td colspan="2" style="text-align: right;">Total for <?= htmlspecialchars($zone) ?>:</td>
                    <td><?= $z_atm ?></td>
                    <td><?= $z_crm ?></td>
                    <td><?= $z_total ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2" style="text-align: right; font-size: 15px;">GRAND TOTAL:</td>
                <td style="font-size: 15px;"><?= $total_atm ?></td>
                <td style="font-size: 15px;"><?= $total_crm ?></td>
                <td style="font-size: 15px;"><?= $total_all ?></td>
            </tr>
        </tfoot>
    </table>
</div>

</body>
</html>
