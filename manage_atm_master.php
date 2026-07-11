<?php
require_once __DIR__ . '/init.php';

Auth::requirePermission('manage_atm_master');

$message = '';
mysqli_set_charset(Database::getInstance()->getConnection(), "utf8");

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$isAdmin = Auth::isAdmin();
$assignedZone = $_SESSION['assigned_zone'] ?? '';

$atmObj = new AtmMaster();
$conn = Database::getInstance()->getConnection();

// --- Inputs ---
$search = trim($_GET['search'] ?? '');
$f_atm_v_id = (int)($_GET['f_atm_v_id'] ?? 0);
$f_ups_v_id = (int)($_GET['f_ups_v_id'] ?? 0);
$f_zone = trim($_GET['f_zone'] ?? '');
$f_group = trim($_GET['f_group'] ?? '');
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

// Preserve search parameters for Edit/Delete/Cancel links
$searchParams = [];
if ($search !== '') $searchParams[] = 'search=' . urlencode($search);
if ($f_atm_v_id > 0) $searchParams[] = 'f_atm_v_id=' . $f_atm_v_id;
if ($f_ups_v_id > 0) $searchParams[] = 'f_ups_v_id=' . $f_ups_v_id;
if ($f_zone !== '') $searchParams[] = 'f_zone=' . urlencode($f_zone);
if ($f_group !== '') $searchParams[] = 'f_group=' . urlencode($f_group);

$searchQueryString = !empty($searchParams) ? '&' . implode('&', $searchParams) : '';
$cancelQueryString = !empty($searchParams) ? '?' . implode('&', $searchParams) : '';

/* -----------------------------------
   Dropdown Data Load
----------------------------------- */
$zones = [];
$resZ = $conn->query("SELECT zone_name FROM zone_master ORDER BY zone_name ASC");
if ($resZ) { while ($r = $resZ->fetch_assoc()) $zones[] = $r['zone_name']; }

$atmVendors = [];
$resAV = $conn->query("SELECT id, vendor_name FROM vendor_master WHERE status = 1 AND UPPER(TRIM(vendor_type)) = 'ATM' ORDER BY vendor_name ASC");
if ($resAV) { while ($r = $resAV->fetch_assoc()) $atmVendors[] = $r; }

$upsVendors = [];
$resUV = $conn->query("SELECT id, vendor_name FROM vendor_master WHERE status = 1 AND UPPER(TRIM(vendor_type)) = 'UPS' ORDER BY vendor_name ASC");
if ($resUV) { while ($r = $resUV->fetch_assoc()) $upsVendors[] = $r; }

$groups = [];
$resGroup = $conn->query("SELECT group_no FROM group_details ORDER BY group_no ASC");
if ($resGroup) { while ($r = $resGroup->fetch_assoc()) $groups[] = $r['group_no']; }

/* -----------------------------------
   Add / Update ATM Master
----------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_atm']) || isset($_POST['update_atm']))) {
    $result = $atmObj->save($_POST);
    if ($result['success']) {
        $message = $result['msg'];
        if (isset($_POST['update_atm'])) {
            $editId = 0; // reset edit state
        }
    } else {
        $message = "Error: " . $result['error'];
    }
}

if (isset($_GET['delete']) && Auth::isSuperAdmin()) {
    $atmObj->delete((int)$_GET['delete']);
    header("Location: manage_atm_master.php?msg=deleted" . $searchQueryString); 
    exit;
} else if (isset($_GET['delete'])) {
    $message = "Error: You do not have permission to delete records.";
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = "Record deleted successfully.";
}

// Vendor Summary Table Logic
$vendorSummary = $atmObj->getVendorSummary();
$totalCount = $atmObj->getTotalCount($isAdmin, $assignedZone);

/* -----------------------------------
   SEARCH & VENDOR WISE LIST (FINAL QUERY)
----------------------------------- */
$listResult = false;
$showList = ($search !== '' || $f_atm_v_id > 0 || $f_ups_v_id > 0 || $f_zone !== '' || $f_group !== '');

if ($showList) {
    $listResult = $atmObj->getList($search, $f_atm_v_id, $f_ups_v_id, $f_zone, $f_group, $isAdmin, $assignedZone);
}

// Edit Mode Load
$editData = null;
if ($editId > 0) { 
    $editData = $atmObj->getById($editId); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage ATM Master</title>
    <style>
        :root { --primary: #2563eb; --success: #059669; --warning: #f59e0b; --danger: #dc2626; --secondary: #64748b; --info: #0ea5e9; --dark: #1e293b; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f1f5f9; margin: 0; padding: 20px; color: #334155; }
        .container { max-width: 1650px; margin: auto; }
        .card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 20px; border: 1px solid #e2e8f0; }
        
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 9px 16px; border-radius: 8px; border: none; cursor: pointer; color: #fff; text-decoration: none; font-size: 13px; font-weight: 600; transition: 0.2s; gap: 6px; }
        .btn:hover { filter: brightness(1.1); transform: translateY(-1px); }
        .btn-blue { background: var(--primary); } .btn-green { background: var(--success); } .btn-warning { background: var(--warning); color: #000; } .btn-danger { background: var(--danger); } .btn-secondary { background: var(--secondary); } .btn-info { background: var(--info); } .btn-dark { background: var(--dark); }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 15px; }
        label { font-weight: 700; font-size: 11px; color: #64748b; text-transform: uppercase; margin-bottom: 5px; display: block; }
        input, select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; }
        input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        
        table { width: 100%; border-collapse: collapse; background: #fff; margin-top: 10px; border-radius: 8px; overflow: hidden; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: top; }
        th { background: #f8fafc; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 11px; }
        .badge { background: #f1f5f9; padding: 3px 6px; border-radius: 4px; font-weight: bold; border: 1px solid #e2e8f0; display: block; margin-top: 3px; }
        .summary-box { font-size: 20px; font-weight: 800; color: var(--primary); }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">

    <div class="card" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div class="summary-box">ATM MASTER DATABASE</div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            
        </div>
    </div>

    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <span style="font-weight:bold;">Total Records: <?= $totalCount ?></span>
            <div style="display:flex; gap:10px;">
                <a href="zone_branch_summary.php" class="btn btn-secondary btn-sm" style="font-size: 13px; font-weight: normal; padding: 5px 10px;" title="Zone & Branch Summary">Zone/Branch Summary Report</a>
                <button class="btn btn-dark btn-sm" style="font-size: 13px; font-weight: normal; padding: 5px 10px;" onclick="document.getElementById('vTable').style.display = (document.getElementById('vTable').style.display==='none'?'block':'none')">Toggle Vendor Summary</button>
            </div>
        </div>
        <div id="vTable" style="display:none; margin-top:15px;">
            <table>
                <thead><tr><th>Vendor Name</th><th>ATM</th><th>CRM</th><th>UPS</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach($vendorSummary as $vs): ?>
                    <tr><td><?=h($vs['vendor_name'])?></td><td><?=$vs['atm_cnt']?></td><td><?=$vs['crm_cnt']?></td><td><?=$vs['ups_cnt']?></td><td><strong><?=($vs['atm_cnt']+$vs['crm_cnt']+$vs['ups_cnt'])?></strong></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($message): ?>
    <div style="padding:10px; background:#dcfce7; color:#166534; border:1px solid #bbf7d0; border-radius:8px; margin-bottom:15px;">
        <strong><?= h($message) ?></strong>
    </div>
    <?php endif; ?>

    <div class="card" style="border-top: 4px solid var(--primary);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="margin: 0;">Search & Filter ATM Records</h3>
            <a href="#atmForm" class="btn btn-info" style="background:#0ea5e9;" onclick="document.getElementById('atmForm').style.display='block';">+ Add New</a>
        </div>
        <form method="get">
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:15px; align-items:flex-end;">
                <div><label>General Search</label><input type="text" name="search" value="<?=h($search)?>" placeholder="ATM ID / Name / Branch"></div>
                
                <div><label>Filter Zone</label>
                    <select name="f_zone">
                        <option value="">-- All Zones --</option>
                        <?php foreach($zones as $z) echo "<option value='$z' ".($f_zone==$z?'selected':'').">$z</option>"; ?>
                    </select>
                </div>
                
                <div><label>Filter Group</label>
                    <select name="f_group">
                        <option value="">-- All Groups --</option>
                        <?php foreach($groups as $g) echo "<option value='$g' ".($f_group==$g?'selected':'').">$g</option>"; ?>
                    </select>
                </div>

                <div><label>Filter ATM Vendor</label>
                    <select name="f_atm_v_id">
                        <option value="0">-- All Vendors --</option>
                        <?php foreach($atmVendors as $v) echo "<option value='{$v['id']}' ".($f_atm_v_id==$v['id']?'selected':'').">{$v['vendor_name']}</option>"; ?>
                    </select>
                </div>
                <div><label>Filter UPS Vendor</label>
                    <select name="f_ups_v_id">
                        <option value="0">-- All Vendors --</option>
                        <?php foreach($upsVendors as $v) echo "<option value='{$v['id']}' ".($f_ups_v_id==$v['id']?'selected':'').">{$v['vendor_name']}</option>"; ?>
                    </select>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-blue">Filter Results</button>
                    <a href="export_atm_master.php?search=<?=urlencode($search)?>&f_zone=<?=urlencode($f_zone)?>&f_group=<?=urlencode($f_group)?>&f_atm_v_id=<?=$f_atm_v_id?>&f_ups_v_id=<?=$f_ups_v_id?>" class="btn btn-green" title="Export Excel">Export</a>
                </div>
            </div>
        </form>

        <?php if ($showList): ?>
        <div style="overflow-x:auto; margin-top:20px;">
            <div style="margin-bottom:10px; font-weight:bold; color:var(--primary);">Found: <?= $listResult->num_rows ?> Records</div>
            <table>
                <thead>
                    <tr>
                        <th>ATM Details</th>
                        <th>Location</th>
                        <th>Vendor Details</th>
                        <th>IP Information</th>
                        <th>Custodian & Management</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($row = $listResult->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= h($row['atm_id']) ?></strong><br><small><?= h($row['atm_name']) ?></small></td>
                    <td><?= h($row['zone_name']) ?><br><small><?= h($row['branch_name']) ?><?= !empty($row['branch_code']) ? ' (' . h($row['branch_code']) . ')' : '' ?></small><br><span class="badge">Group: <?= h($row['group_no']) ?></span></td>
                    <td>
                        <span class="badge">ATM: <?= h($row['atm_vendor'] ?: '-') ?></span>
                        <span class="badge" style="color:var(--danger);">UPS: <?= h($row['ups_vendor'] ?: '-') ?></span>
                    </td>
                    <td>
                        <small>
                            <b>M:</b> <?=h($row['monitoring_ip'])?><br>
                            <b>I:</b> <?=h($row['internal_ip'])?><br>
                            <b>S:</b> <?=h($row['subnet_mask'])?><br>
                            <b>G:</b> <?=h($row['gateway'])?>
                        </small>
                    </td>
                    <td style="font-size:11px; line-height: 1.4;">
                        <b>MGR:</b> <?=h($row['manager'] ?: '-')?> <?=!empty($row['manager_mobile']) ? '('.h($row['manager_mobile']).')' : ''?><br>
                        <b>CUST 1:</b> <?=h($row['cust1'] ?: '-')?> <?=!empty($row['cust1_mobile']) ? '('.h($row['cust1_mobile']).')' : ''?><br>
                        <?php if(!empty($row['cust2'])): ?>
                            <b>CUST 2:</b> <?=h($row['cust2'])?> <?=!empty($row['cust2_mobile']) ? '('.h($row['cust2_mobile']).')' : ''?><br>
                        <?php endif; ?>
                        
                        <b>GUARD 1:</b> <?=h($row['sg1'] ?: '-')?> <?=!empty($row['sg1_mob']) ? '('.h($row['sg1_mob']).')' : ''?><br>
                        <?php if(!empty($row['sg2'])): ?>
                            <b>GUARD 2:</b> <?=h($row['sg2'])?> <?=!empty($row['sg2_mob']) ? '('.h($row['sg2_mob']).')' : ''?><br>
                        <?php endif; ?>
                        <?php if(!empty($row['sg3'])): ?>
                            <b>GUARD 3:</b> <?=h($row['sg3'])?> <?=!empty($row['sg3_mob']) ? '('.h($row['sg3_mob']).')' : ''?><br>
                        <?php endif; ?>
                        <?php if(!empty($row['sg_company'])): ?>
                            <b>CO:</b> <?=h($row['sg_company'])?><br>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex; gap:5px;">
                            <a href="?edit=<?=$row['id']?><?= $searchQueryString ?>#atmForm" class="btn btn-sm btn-warning">Edit</a>
                            <?php if (Auth::isSuperAdmin()): ?>
                                <a href="?delete=<?=$row['id']?><?= $searchQueryString ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Del</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div id="atmForm" class="card" <?= !$editData ? 'style="display:none;"' : '' ?>>
        <h3><?= $editData ? 'Edit Record' : 'Create New ATM Entry' ?></h3>
        <form method="post">
            <?php if ($editData): ?><input type="hidden" name="id" value="<?=$editData['id']?>"><?php endif; ?>
            <div class="form-grid">
                <div><label>ATM ID</label><input type="text" name="atm_id" required value="<?= h($editData['atm_id'] ?? '') ?>"></div>
                <div><label>Booth Name</label><input type="text" name="atm_name" required value="<?= h($editData['atm_name'] ?? '') ?>"></div>
                <div><label>Zone Name</label>
                    <select name="zone_name" id="zone_name" required>
                        <option value="">Select Zone</option>
                        <?php foreach($zones as $z) echo "<option value='$z' ".((($editData['zone_name'] ?? $assignedZone)==$z)?'selected':'').">$z</option>"; ?>
                    </select>
                </div>
                <div><label>Branch Name</label><select name="branch_name" id="branch_name" required><option value="">Select Branch</option></select></div>
                <div><label>Branch Code</label><input type="text" name="branch_code" id="branch_code" value="<?= h($editData['branch_code'] ?? '') ?>"></div>
                
                <div><label>ATM Vendor</label>
                    <select name="atm_vendor_id">
                        <option value="0">--Select--</option>
                        <?php foreach($atmVendors as $v): 
                            $sel = ( (isset($editData['atm_vendor_id']) && (int)$editData['atm_vendor_id'] == $v['id']) || (isset($editData['atm_vendor']) && trim($editData['atm_vendor']) === trim($v['vendor_name'])) );
                        ?>
                        <option value="<?=$v['id']?>" <?= $sel ? 'selected':'' ?>><?= h($v['vendor_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>UPS Vendor</label>
                    <select name="ups_vendor_id">
                        <option value="0">--Select--</option>
                        <?php foreach($upsVendors as $v): 
                            $sel = ( (isset($editData['ups_vendor_id']) && (int)$editData['ups_vendor_id'] == $v['id']) || (isset($editData['ups_vendor']) && trim($editData['ups_vendor']) === trim($v['vendor_name'])) );
                        ?>
                        <option value="<?=$v['id']?>" <?= $sel ? 'selected':'' ?>><?= h($v['vendor_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>Group No</label>
                    <select name="group_no">
                        <option value="">--Select--</option>
                        <?php foreach($groups as $g) echo "<option value='$g' ".((($editData['group_no']??'')==$g)?'selected':'').">$g</option>"; ?>
                    </select>
                </div>
                <div><label>Monitoring IP</label><input type="text" name="monitoring_ip" value="<?= h($editData['monitoring_ip'] ?? '') ?>"></div>
                <div><label>Internal IP</label><input type="text" name="internal_ip" value="<?= h($editData['internal_ip'] ?? '') ?>"></div>
                <div><label>Subnet Mask</label><input type="text" name="subnet_mask" value="<?= h($editData['subnet_mask'] ?? '') ?>"></div>
                <div><label>Gateway</label><input type="text" name="gateway" value="<?= h($editData['gateway'] ?? '') ?>"></div>
            </div>
            <div style="margin-top:20px;">
                <button type="submit" name="<?= $editData ? 'update_atm' : 'add_atm' ?>" class="btn btn-green"><?= $editData ? 'Update Record' : 'Save Record' ?></button>
                <?php if($editId): ?>
                    <a href="manage_atm_master.php<?= $cancelQueryString ?>" class="btn btn-secondary">Cancel</a>
                <?php else: ?>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('atmForm').style.display='none'">Cancel</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

</div>

<script>
let branchDataMap = {};
function loadBranches(selectedZone, selectedBranch = '') {
    const branchSelect = document.getElementById('branch_name');
    const branchCodeInput = document.getElementById('branch_code');
    if (!selectedZone) { 
        branchSelect.innerHTML = '<option value="">Select Branch</option>'; 
        return; 
    }
    fetch('get_branches_by_zone.php?zone_name=' + encodeURIComponent(selectedZone))
        .then(r => r.json()).then(data => {
            let html = '<option value="">Select Branch</option>';
            branchDataMap = {};
            if (data.success) {
                data.branches.forEach(b => {
                    branchDataMap[b.branch_name] = b.branch_code;
                    html += `<option value="${b.branch_name}" data-code="${b.branch_code}" ${b.branch_name===selectedBranch?'selected':''}>${b.branch_name}</option>`; 
                });
            }
            branchSelect.innerHTML = html;
            
            if (selectedBranch && branchDataMap[selectedBranch]) {
                if (branchCodeInput.value === '') {
                    branchCodeInput.value = branchDataMap[selectedBranch];
                }
            }
        });
}
document.addEventListener('DOMContentLoaded', function () {
    const zoneSelect = document.getElementById('zone_name');
    const branchSelect = document.getElementById('branch_name');
    const branchCodeInput = document.getElementById('branch_code');
    
    if (zoneSelect) {
        zoneSelect.addEventListener('change', function () { 
            loadBranches(this.value, ''); 
            branchCodeInput.value = ''; 
        });
        if (zoneSelect.value !== '') {
            loadBranches(zoneSelect.value, "<?= h($editData['branch_name'] ?? '') ?>");
        }
    }
    
    if (branchSelect) {
        branchSelect.addEventListener('change', function () {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption) {
                const code = selectedOption.getAttribute('data-code') || '';
                branchCodeInput.value = code;
            }
        });
    }
});
</script>
</body>
</html>