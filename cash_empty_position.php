<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

// Check permissions if applicable, e.g., Auth::requirePermission('manage_atm');

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$action = $_GET['action'] ?? 'entry';
$entry_date = $_GET['date'] ?? date('Y-m-d');

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_positions'])) {
    $post_date = $_POST['entry_date'] ?? date('Y-m-d');
    
    $stmt = $conn->prepare("
        INSERT INTO cash_empty_positions (entry_date, zone_name, branch_empty, third_party_empty) 
        VALUES (?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        branch_empty = VALUES(branch_empty), 
        third_party_empty = VALUES(third_party_empty)
    ");

    foreach ($_POST['zones'] as $zone => $data) {
        $branch_empty = (int)($data['branch'] ?? 0);
        $third_party_empty = (int)($data['third_party'] ?? 0);
        
        $stmt->bind_param("ssii", $post_date, $zone, $branch_empty, $third_party_empty);
        $stmt->execute();
    }
    $stmt->close();
    
    header("Location: cash_empty_position.php?action=report&date=" . urlencode($post_date) . "&msg=saved");
    exit;
}

// Fetch Zone-wise Total ATMs and Group info
$groups = [];
$zoneStats = []; // Keep it for backwards compatibility

$res = $conn->query("
    SELECT 
        z.clean_zone, 
        z.total_atm,
        t.group_no,
        g.group_leader_name
    FROM (
        SELECT TRIM(zone_name) as clean_zone, COUNT(*) as total_atm
        FROM atm_master
        WHERE zone_name IS NOT NULL AND TRIM(zone_name) != ''
        GROUP BY clean_zone
    ) z
    LEFT JOIN (
        SELECT TRIM(zone_name) as clean_zone, group_no,
               ROW_NUMBER() OVER(PARTITION BY TRIM(zone_name) ORDER BY COUNT(*) DESC) as rn
        FROM atm_master
        WHERE zone_name IS NOT NULL AND TRIM(zone_name) != ''
        GROUP BY TRIM(zone_name), group_no
    ) t ON z.clean_zone = t.clean_zone AND t.rn = 1
    LEFT JOIN group_details g ON t.group_no = g.group_no
    ORDER BY t.group_no ASC, z.clean_zone ASC
");

while ($r = $res->fetch_assoc()) {
    $gNo = $r['group_no'] ?: '999';
    $gLeaderRaw = $r['group_leader_name'] ?? '';
    $gLeader = trim(preg_replace('/(SO|PO|\(|,).*$/i', '', $gLeaderRaw));
    
    $gName = ($gNo != '999') ? "{$gNo}-{$gLeader}" : "Others";
    $zone = $r['clean_zone'];
    
    if (!isset($groups[$gName])) {
        $groups[$gName] = [];
    }
    $groups[$gName][] = [
        'zone' => $zone,
        'total_atm' => (int)$r['total_atm']
    ];
    $zoneStats[$zone] = (int)$r['total_atm'];
}

// Fetch existing data for the selected date
$existingData = [];
$stmt = $conn->prepare("SELECT zone_name, branch_empty, third_party_empty FROM cash_empty_positions WHERE entry_date = ?");
$stmt->bind_param("s", $entry_date);
$stmt->execute();
$resExisting = $stmt->get_result();
while ($row = $resExisting->fetch_assoc()) {
    $existingData[$row['zone_name']] = [
        'branch' => (int)$row['branch_empty'],
        'third_party' => (int)$row['third_party_empty']
    ];
}
$stmt->close();

$displayDate = date('d-m-Y', strtotime($entry_date));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cash Empty Position</title>
    <style>
        :root { --primary: #2563eb; --dark: #0f172a; --success: #059669; --bg: #f8fafc; }
        body { font-family: 'Inter', Arial, sans-serif; background: var(--bg); margin: 20px; color: #334155; font-size: 14px; }
        .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 20px; max-width: 900px; margin-left: auto; margin-right: auto; }
        
        .btn { display: inline-block; padding: 9px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; cursor: pointer; border: none; font-size: 13.5px; transition: 0.2s; }
        .btn-blue { background: var(--primary); color: #fff; }
        .btn-success { background: var(--success); color: #fff; }
        .btn-dark { background: var(--dark); color: #fff; }
        .btn-outline { border: 1px solid #cbd5e1; color: var(--dark); background: transparent; }
        .btn:hover { opacity: 0.9; }

        .form-control { padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; width: 100px; text-align: center; }
        .date-picker { padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; border: 1px solid #cbd5e1; text-align: center; }
        th { background: #f1f5f9; font-weight: 700; color: var(--dark); }
        th { background: #f1f5f9; font-weight: 700; color: var(--dark); }
        
        .total-row { font-weight: bold; background: #f8fafc; }
        .header-title { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 20px; }

        /* Report Specific Styles */
        .report-header { text-align: center; margin-bottom: 20px; line-height: 1.5; }
        .report-header h2, .report-header h3, .report-header h4 { margin: 0; color: #000; }
        .report-table th, .report-table td { border: 1px solid #000; padding: 8px; color: #000; }
        .report-table th { background: #e5e7eb !important; }
        
        @media print {
            body { background: #fff; margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .card { box-shadow: none; max-width: 100%; padding: 0; }
            table { font-size: 13px; }
            @page { margin: 15mm; }
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="card no-print">
    <div class="header-title">
        <h2 style="margin:0;">Cash Empty Position Management</h2>
        <div>
            <a href="manage_atm_master.php" class="btn btn-outline">Back to ATM Master</a>
            <?php if($action === 'entry'): ?>
                <a href="cash_empty_position.php?action=report&date=<?= h($entry_date) ?>" class="btn btn-dark">View Report</a>
            <?php else: ?>
                <button onclick="window.print()" class="btn btn-blue">Print Report</button>
                <a href="cash_empty_position.php?action=entry&date=<?= h($entry_date) ?>" class="btn btn-success">Edit Data</a>
            <?php endif; ?>
        </div>
    </div>

    <form method="GET" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center;">
        <input type="hidden" name="action" value="<?= h($action) ?>">
        <label style="font-weight: bold;">Select Date:</label>
        <input type="date" name="date" value="<?= h($entry_date) ?>" class="date-picker" onchange="this.form.submit()">
    </form>
    
    <?php if(isset($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
        <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-weight: bold;">
            Data saved successfully!
        </div>
    <?php endif; ?>
</div>

<?php if ($action === 'entry'): ?>
<div class="card">
    <form method="POST">
        <input type="hidden" name="entry_date" value="<?= h($entry_date) ?>">
        <table>
            <thead>
                <tr>
                    <th style="text-align: left;">Group</th>
                    <th style="text-align: left;">Zone Name</th>
                    <th>Total Number of ATM</th>
                    <th>Branch (Empty Count)</th>
                    <th>3rd Party (Empty Count)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $g_total_atm = 0;
                foreach ($groups as $groupName => $zones): 
                    $rowspan = count($zones);
                    foreach ($zones as $idx => $z):
                        $zone = $z['zone'];
                        $total_atm = $z['total_atm'];
                        $g_total_atm += $total_atm;
                        $b_val = $existingData[$zone]['branch'] ?? 0;
                        $t_val = $existingData[$zone]['third_party'] ?? 0;
                ?>
                <tr>
                    <?php if ($idx === 0): ?>
                    <td rowspan="<?= $rowspan ?>" style="vertical-align: middle; font-weight: bold; background: #fff; text-align: left;"><?= h($groupName) ?></td>
                    <?php endif; ?>
                    <td style="text-align: left;"><?= h($zone) ?></td>
                    <td><?= $total_atm ?></td>
                    <td><input type="number" name="zones[<?= h($zone) ?>][branch]" value="<?= $b_val ?>" min="0" class="form-control"></td>
                    <td><input type="number" name="zones[<?= h($zone) ?>][third_party]" value="<?= $t_val ?>" min="0" class="form-control"></td>
                </tr>
                <?php 
                    endforeach;
                endforeach; 
                ?>
                <tr class="total-row">
                    <td colspan="2">Total System ATMs</td>
                    <td><?= $g_total_atm ?></td>
                    <td colspan="2" style="text-align:right;">
                        <button type="submit" name="save_positions" class="btn btn-success">Save Cash Position Data</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </form>
</div>

<?php else: ?>
<div class="card">
    <div class="report-header">
        <h2>Islami Bank Bangladesh PLC</h2>
        <h3>ATM Management Division, DBW, HO</h3>
        <h4>Monitoring & Support Management Department</h4>
        <h4 style="margin-top: 10px; text-decoration: underline;">Cash Empty Position as on <?= h($displayDate) ?></h4>
    </div>

    <table class="report-table">
        <thead>
            <tr>
                <th style="text-align: center;">Group</th>
                <th style="text-align: left;">Zone Name</th>
                <th>Branch</th>
                <th>3rd Party</th>
                <th>Total Cash Nil</th>
                <th>Total Number of ATM/CRM</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $t_branch = 0; $t_third = 0; $t_empty = 0; $t_atm = 0;
            foreach ($groups as $groupName => $zones): 
                $rowspan = count($zones);
                foreach ($zones as $idx => $z):
                    $zone = $z['zone'];
                    $total_atm = $z['total_atm'];
                    
                    $b_val = $existingData[$zone]['branch'] ?? 0;
                    $t_val = $existingData[$zone]['third_party'] ?? 0;
                    $row_empty_total = $b_val + $t_val;
                    
                    $t_branch += $b_val;
                    $t_third += $t_val;
                    $t_empty += $row_empty_total;
                    $t_atm += $total_atm;
            ?>
            <tr>
                <?php if ($idx === 0): ?>
                <td rowspan="<?= $rowspan ?>" style="vertical-align: middle; font-weight: bold; background: #fff; text-align: center;"><?= h($groupName) ?></td>
                <?php endif; ?>
                <td style="text-align: left;"><?= h($zone) ?></td>
                <td style="text-align: center;"><?= $b_val ?: '0' ?></td>
                <td style="text-align: center;"><?= $t_val ?: '0' ?></td>
                <td style="font-weight:bold; text-align: center;"><?= $row_empty_total ?></td>
                <td style="text-align: center;"><?= $total_atm ?></td>
            </tr>
            <?php 
                endforeach;
            endforeach; 
            ?>
            <tr class="total-row" style="font-weight:bold; background:#f8fafc;">
                <td colspan="2" style="text-align:left;">Grand Total</td>
                <td style="text-align: center;"><?= $t_branch ?></td>
                <td style="text-align: center;"><?= $t_third ?></td>
                <td style="text-align: center;"><?= $t_empty ?></td>
                <td style="text-align: center;"><?= $t_atm ?></td>
            </tr>
        </tbody>
    </table>
</div>
<?php endif; ?>

</body>
</html>