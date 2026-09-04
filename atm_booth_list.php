<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

// এনকোডিং ঠিক করা
mysqli_set_charset($conn, "utf8");

Auth::requirePermission('manage_atm_master');

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* =========================================================
   AJAX AUTO-UPDATE LOGIC
========================================================= */
if (isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    $atm_id = trim($_POST['atm_id'] ?? '');
    $field = trim($_POST['field'] ?? '');
    $value = trim($_POST['value'] ?? '');

    // Security check for allowed fields
    if ($atm_id !== '' && in_array($field, ['number_of_atm', 'other_atm_id'])) {
        // If number_of_atm is empty, set as NULL
        if ($field === 'number_of_atm' && $value === '') {
            $value = null;
        }

        $stmt = $conn->prepare("UPDATE cctv_list SET $field = ? WHERE atm_id = ?");
        $stmt->bind_param("ss", $value, $atm_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    }
    exit;
}

/* =========================================================
   FILTER LOGIC & DATA FETCH
========================================================= */
$f_zone = trim($_GET['f_zone'] ?? '');
$f_branch = trim($_GET['f_branch'] ?? '');
$isFiltered = isset($_GET['filter']);

// Fetch distinct Zones and Branches for dropdowns
$zones = [];
$resZ = $conn->query("SELECT DISTINCT zone_name FROM cctv_list WHERE zone_name IS NOT NULL AND zone_name != '' ORDER BY zone_name");
if ($resZ) { while ($r = $resZ->fetch_assoc()) $zones[] = $r['zone_name']; }

$branches = [];
$resB = $conn->query("SELECT DISTINCT branch_name FROM cctv_list WHERE branch_name IS NOT NULL AND branch_name != '' ORDER BY branch_name");
if ($resB) { while ($r = $resB->fetch_assoc()) $branches[] = $r['branch_name']; }

$list = false;
$total_booths = 0;

if ($isFiltered) {
    $sql = "
        SELECT 
            c.zone_name, 
            c.br_code, 
            c.branch_name, 
            c.atm_name AS booth_name, 
            c.atm_id, 
            c.number_of_atm, 
            c.other_atm_id,
            a.monitoring_ip, 
            a.internal_ip, 
            a.subnet_mask, 
            a.gateway
        FROM cctv_list c
        LEFT JOIN atm_master a ON TRIM(c.atm_id) COLLATE utf8mb4_general_ci = TRIM(a.atm_id) COLLATE utf8mb4_general_ci
        WHERE 1=1
    ";

    if ($f_zone !== '') {
        $sql .= " AND c.zone_name = '" . $conn->real_escape_string($f_zone) . "'";
    }
    if ($f_branch !== '') {
        $sql .= " AND c.branch_name = '" . $conn->real_escape_string($f_branch) . "'";
    }

    $sql .= " ORDER BY c.zone_name ASC, c.branch_name ASC, c.atm_id ASC";
    $list = $conn->query($sql);
    
    if ($list) {
        $total_booths = $list->num_rows;
    }

    /* =========================================================
       EXPORT EXCEL LOGIC 
    ========================================================= */
    if (isset($_GET['export']) && $_GET['export'] === 'excel') {
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=ATM_Booth_List_" . date('Y-m-d') . ".xls");
        
        echo "<table border='1'>";
        echo "<tr style='background-color:#f2f2f2;'>
                <th>SL</th>
                <th>Zone</th>
                <th>Branch Code</th>
                <th>Branch Name</th>
                <th>Booth Name</th>
                <th>ATM ID</th>
                <th>Number of ATM</th>
                <th>Other ATM ID</th>
                <th>Monitoring IP</th>
                <th>Internal IP</th>
                <th>Subnet Mask</th>
                <th>Gateway</th>
              </tr>";
        
        $sl = 1;
        if ($total_booths > 0) {
            $list->data_seek(0);
            while($row = $list->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $sl++ . "</td>";
                echo "<td>" . h($row['zone_name']) . "</td>";
                echo "<td>" . h($row['br_code']) . "</td>";
                echo "<td>" . h($row['branch_name']) . "</td>";
                echo "<td>" . h($row['booth_name']) . "</td>";
                echo "<td>" . h($row['atm_id']) . "</td>";
                echo "<td>" . h($row['number_of_atm']) . "</td>";
                echo "<td>" . h($row['other_atm_id']) . "</td>";
                echo "<td>" . h($row['monitoring_ip']) . "</td>";
                echo "<td>" . h($row['internal_ip']) . "</td>";
                echo "<td>" . h($row['subnet_mask']) . "</td>";
                echo "<td>" . h($row['gateway']) . "</td>";
                echo "</tr>";
            }
        }
        echo "</table>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ATM Booth List & Configuration</title>
    <style>
        :root { --primary: #2563eb; --secondary: #64748b; --dark: #0f172a; --info: #06b6d4; --success: #16a34a; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f1f5f9; margin: 0; padding: 20px; color: #334155; font-size: 13.5px; }
        .container { max-width: 1600px; margin: auto; }
        .card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 20px; border: 1px solid #e2e8f0; }
        
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 9px 16px; border-radius: 6px; border: none; cursor: pointer; color: #fff; text-decoration: none; font-size: 13px; font-weight: 600; gap: 6px; transition: 0.2s; }
        .btn:hover { filter: brightness(1.1); transform: translateY(-1px); }
        .btn-blue { background: var(--primary); } 
        .btn-secondary { background: var(--secondary); } 
        .btn-info { background: var(--info); }
        .btn-dark { background: var(--dark); }
        .btn-success { background: var(--success); }
        
        table { width: 100%; border-collapse: collapse; background: #fff; margin-top: 15px; border-radius: 8px; overflow: hidden; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: middle; }
        th { background: #f8fafc; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 11px; }
        tr:hover td { background: #f1f5f9; }

        select, input[type="text"], input[type="number"] { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none; }
        select:focus, input:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(37,99,235,0.1); }
        
        /* Inline Edit inputs */
        .inline-edit { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px; width: 100%; box-sizing: border-box; transition: background 0.3s ease; }
        .inline-edit:focus { background: #fff; border-color: var(--primary); }

        .stat-box { display: inline-block; background: #eff6ff; color: #1e3a8a; padding: 6px 12px; border-radius: 6px; font-weight: bold; border: 1px solid #bfdbfe; font-size: 14px; margin-left: 15px; }

        @media print {
            body { background: #fff; margin: 0; padding: 0; }
            .no-print, nav { display: none !important; }
            .card { border: none !important; box-shadow: none !important; margin: 0 !important; padding: 0 !important; }
            table { width: 100% !important; border-collapse: collapse !important; margin-top: 15px; }
            th, td { border: 1px solid #000 !important; padding: 6px !important; font-size: 11px !important; color: #000 !important; }
            th { background: #f0f0f0 !important; color: #000 !important; }
            .inline-edit { border: none !important; background: transparent !important; padding: 0 !important; font-size: 11px !important; }
            .print-header { display: block !important; text-align: center; margin-bottom: 20px; }
        }
        .print-header { display: none; }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container">
    <div class="card no-print">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <h2 style="margin:0; font-weight: 700; color: var(--dark);">
                ATM Booth List & Configurations
                <?php if ($isFiltered): ?>
                    <span class="stat-box">Total Booths: <?= $total_booths ?></span>
                <?php endif; ?>
            </h2>
            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                <a href="manage_atm_master.php" class="btn btn-secondary">Back to ATM Master</a>
                <?php if ($isFiltered): ?>
                    <a href="atm_booth_list.php?export=excel&filter=1&f_zone=<?= urlencode($f_zone) ?>&f_branch=<?= urlencode($f_branch) ?>" class="btn btn-info">Export Excel</a>
                    <button type="button" onclick="window.print()" class="btn btn-dark">Print / PDF</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="card no-print">
        <form method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <div>
                <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px; color:var(--secondary);">Zone Name</label>
                <select name="f_zone" style="min-width: 200px;">
                    <option value="">-- All Zones --</option>
                    <?php foreach ($zones as $z): ?>
                        <option value="<?= h($z) ?>" <?= $f_zone === $z ? 'selected' : '' ?>><?= h($z) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px; color:var(--secondary);">Branch Name</label>
                <select name="f_branch" style="min-width: 250px;">
                    <option value="">-- All Branches --</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= h($b) ?>" <?= $f_branch === $b ? 'selected' : '' ?>><?= h($b) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" name="filter" value="1" class="btn btn-blue">Show Data</button>
                <?php if ($isFiltered): ?>
                    <a href="atm_booth_list.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($isFiltered): ?>
        <div class="card" style="padding:0; overflow-x:auto;">
            
            <div class="print-header">
                <h2>Islami Bank Bangladesh PLC</h2>
                <h3>ATM Booth Configuration List</h3>
                <p>Date: <?= date('d-M-Y') ?> | Zone: <?= $f_zone ?: 'All' ?> | Branch: <?= $f_branch ?: 'All' ?> | Total: <?= $total_booths ?></p>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>SL</th>
                        <th>Zone</th>
                        <th>Branch</th>
                        <th>Booth Name</th>
                        <th>ATM ID</th>
                        <th style="width: 100px;">No. of ATM</th>
                        <th style="width: 160px;">Other ATM ID</th>
                        <th>Monitoring IP</th>
                        <th>Internal IP</th>
                        <th>Subnet Mask</th>
                        <th>Gateway</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($list && $total_booths > 0): 
                        $list->data_seek(0); 
                        $sl = 1; 
                        while($row = $list->fetch_assoc()): 
                    ?>
                    <tr>
                        <td><?= $sl++ ?></td>
                        <td><?= h($row['zone_name']) ?></td>
                        <td><?= h($row['branch_name']) ?> <br><small style="color:#94a3b8;">(<?= h($row['br_code']) ?>)</small></td>
                        <td><?= h($row['booth_name']) ?></td>
                        <td style="font-weight:bold; color:var(--primary);"><?= h($row['atm_id']) ?></td>
                        
                        <!-- Editable Fields -->
                        <td>
                            <input type="number" 
                                   class="inline-edit" 
                                   value="<?= h($row['number_of_atm'] ?? '') ?>" 
                                   onchange="autoSave('<?= h($row['atm_id']) ?>', 'number_of_atm', this.value, this)"
                                   placeholder="Qty">
                        </td>
                        <td>
                            <input type="text" 
                                   class="inline-edit" 
                                   value="<?= h($row['other_atm_id'] ?? '') ?>" 
                                   onchange="autoSave('<?= h($row['atm_id']) ?>', 'other_atm_id', this.value, this)"
                                   placeholder="Other IDs">
                        </td>
                        
                        <td><?= h($row['monitoring_ip']) ?></td>
                        <td><?= h($row['internal_ip']) ?></td>
                        <td><?= h($row['subnet_mask']) ?></td>
                        <td><?= h($row['gateway']) ?></td>
                    </tr>
                    <?php 
                        endwhile; 
                    else: 
                    ?>
                    <tr><td colspan="11" style="text-align: center; color: var(--secondary); padding: 30px;">No records found for the selected filters.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="card no-print" style="text-align: center; padding: 60px 20px;">
            <div style="font-size: 50px; color: #cbd5e1; margin-bottom: 15px;">📊</div>
            <h3 style="color: var(--dark); font-weight: 700;">No Data Loaded</h3>
            <p style="color: var(--secondary); max-width: 400px; margin: 0 auto;">Please use the filter options above and click <strong>"Show Data"</strong> to load the ATM Booth List. Leave dropdowns empty to load all data.</p>
        </div>
    <?php endif; ?>
</div>

<script>
// Auto-Save AJAX Function
function autoSave(atmId, fieldName, fieldValue, inputElement) {
    // Show a loading indicator (Yellow background)
    const originalBg = inputElement.style.backgroundColor;
    inputElement.style.backgroundColor = '#fef08a';

    const formData = new FormData();
    formData.append('ajax_update', '1');
    formData.append('atm_id', atmId);
    formData.append('field', fieldName);
    formData.append('value', fieldValue);

    fetch('atm_booth_list.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Success indicator (Green background briefly, then normal)
            inputElement.style.backgroundColor = '#dcfce7'; 
            setTimeout(() => {
                inputElement.style.backgroundColor = originalBg;
            }, 1000);
        } else {
            // Error indicator (Red background)
            alert('Failed to save data: ' + data.error);
            inputElement.style.backgroundColor = '#fee2e2';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred.');
        inputElement.style.backgroundColor = '#fee2e2';
    });
}
</script>

</body>
</html>