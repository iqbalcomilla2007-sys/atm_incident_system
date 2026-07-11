<?php
date_default_timezone_set('Asia/Dhaka');
include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('atm_device_movement');

mysqli_set_charset($conn, "utf8");

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$msg = '';
$error = '';

function generateMovementNo($conn) {
    $prefix = 'MOV-' . date('Y') . '-';
    $res = $conn->query("SELECT id FROM atm_device_movement ORDER BY id DESC LIMIT 1");
    $next = ($res && $row = $res->fetch_assoc()) ? ((int)$row['id']) + 1 : 1;
    return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
}

function getVendorName($conn, $id) {
    $id = (int)$id;
    if ($id <= 0) return '';
    $stmt = $conn->prepare("SELECT vendor_name FROM vendor_master WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return trim($row['vendor_name'] ?? '');
}

/* AJAX: fetch ATM */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'fetch_atm') {
    header('Content-Type: application/json; charset=utf-8');
    $atm_id = trim($_GET['atm_id'] ?? '');
    $stmt = $conn->prepare("SELECT * FROM atm_master WHERE TRIM(atm_id) = TRIM(?) LIMIT 1");
    $stmt->bind_param("s", $atm_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    echo json_encode($row ? ['success' => true, 'data' => $row] : ['success' => false]);
    exit;
}

/* Vendor options */
$atmVendors = [];
$res = $conn->query("SELECT id, vendor_name FROM vendor_master WHERE status=1 AND UPPER(TRIM(vendor_type))='ATM' ORDER BY vendor_name");
while ($res && $r = $res->fetch_assoc()) $atmVendors[] = $r;

$upsVendors = [];
$res = $conn->query("SELECT id, vendor_name FROM vendor_master WHERE status=1 AND UPPER(TRIM(vendor_type))='UPS' ORDER BY vendor_name");
while ($res && $r = $res->fetch_assoc()) $upsVendors[] = $r;

$editId = (int)($_GET['edit_id'] ?? 0);
if (isset($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    if ($deleteId > 0) {
        $stmt = $conn->prepare("DELETE FROM atm_device_movement WHERE id=?");
        $stmt->bind_param("i", $deleteId);
        $stmt->execute();
        $stmt->close();
        header("Location: atm_device_movement.php?msg=deleted"); exit;
    }
}
$editData = [];
if ($editId > 0) {
    $stmt = $conn->prepare("SELECT * FROM atm_device_movement WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
}

/* SAVE LOGIC */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();
        $editId = (int)($_POST['edit_id'] ?? 0);
        $historyOnly = isset($_POST['history_only']) ? 1 : 0;
        $m_type = trim($_POST['movement_type']);
        $d_type = trim($_POST['device_type']);
        $old_id = trim($_POST['old_atm_id']);

        $masterRes = $conn->query("SELECT id FROM atm_master WHERE atm_id='$old_id'");
        if ($masterRes->num_rows === 0) throw new Exception("Current ATM ID not found in Master.");
        $master = $masterRes->fetch_assoc();
        $master_id = $master['id'];

        if ($editId > 0) {
            $stmt = $conn->prepare("UPDATE atm_device_movement SET atm_master_id=?, old_atm_id=?, new_atm_id=?, device_type=?, movement_type=?, old_atm_name=?, old_branch_name=?, old_zone_name=?, old_group_no=?, new_atm_name=?, new_branch_name=?, new_zone_name=?, new_group_no=?, old_atm_vendor_id=?, new_atm_vendor_id=?, old_ups_vendor_id=?, new_ups_vendor_id=?, movement_date=?, reference_no=?, reason=? WHERE id=?");
            $stmt->bind_param("issssssssssssiiiisssi", $master_id, $old_id, $_POST['new_atm_id'], $d_type, $m_type, $_POST['old_atm_name'], $_POST['old_branch_name'], $_POST['old_zone_name'], $_POST['old_group_no'], $_POST['new_atm_name'], $_POST['new_branch_name'], $_POST['new_zone_name'], $_POST['new_group_no'], $_POST['old_atm_vendor_id'], $_POST['new_atm_vendor_id'], $_POST['old_ups_vendor_id'], $_POST['new_ups_vendor_id'], $_POST['movement_date'], $_POST['reference_no'], $_POST['reason'], $editId);
            $stmt->execute();
            $stmt->close();
            $conn->commit();
            header("Location: atm_device_movement.php?msg=updated"); exit;
        }

        if (!$historyOnly) {
            if ($m_type === 'TRANSFER') {
                $stmt = $conn->prepare("UPDATE atm_master SET atm_name=?, branch_name=?, zone_name=?, group_no=? WHERE id=?");
                $stmt->bind_param("ssssi", $_POST['new_atm_name'], $_POST['new_branch_name'], $_POST['new_zone_name'], $_POST['new_group_no'], $master_id);
                $stmt->execute();
            } elseif ($m_type === 'ATM_ID_CHANGE') {
                $new_id = trim($_POST['new_atm_id']);
                $conn->query("UPDATE atm_master SET atm_id='$new_id' WHERE id=$master_id");
                $conn->query("UPDATE atm_update SET atm_id='$new_id' WHERE atm_id='$old_id'");
            } elseif ($m_type === 'VENDOR_CHANGE') {
                if ($d_type === 'UPS') {
                    $vId = (int)$_POST['new_ups_vendor_id']; $vN = getVendorName($conn, $vId);
                    $conn->query("UPDATE atm_master SET ups_vendor_id=$vId, ups_vendor='$vN' WHERE id=$master_id");
                } else {
                    $vId = (int)$_POST['new_atm_vendor_id']; $vN = getVendorName($conn, $vId);
                    $conn->query("UPDATE atm_master SET atm_vendor_id=$vId, atm_vendor='$vN' WHERE id=$master_id");
                }
            } elseif ($m_type === 'OBSOLETE') {
                $conn->query("UPDATE atm_master SET zone_name='OBSOLETE', atm_id = CONCAT(atm_id, '-OBS') WHERE id=$master_id");
            }
        }

        // History Log
        $stmt = $conn->prepare("INSERT INTO atm_device_movement (movement_no, atm_master_id, old_atm_id, new_atm_id, device_type, movement_type, old_atm_name, old_branch_name, old_zone_name, old_group_no, new_atm_name, new_branch_name, new_zone_name, new_group_no, old_atm_vendor_id, new_atm_vendor_id, old_ups_vendor_id, new_ups_vendor_id, movement_date, reference_no, reason, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $uid = $_SESSION['user_id'] ?? null;
        $stmt->bind_param("sissssssssssssiiiisssi", generateMovementNo($conn), $master_id, $old_id, $_POST['new_atm_id'], $d_type, $m_type, $_POST['old_atm_name'], $_POST['old_branch_name'], $_POST['old_zone_name'], $_POST['old_group_no'], $_POST['new_atm_name'], $_POST['new_branch_name'], $_POST['new_zone_name'], $_POST['new_group_no'], $_POST['old_atm_vendor_id'], $_POST['new_atm_vendor_id'], $_POST['old_ups_vendor_id'], $_POST['new_ups_vendor_id'], $_POST['movement_date'], $_POST['reference_no'], $_POST['reason'], $uid);
        $stmt->execute();
        $conn->commit();
        header("Location: atm_device_movement.php?msg=success"); exit;
    } catch (Exception $e) { $conn->rollback(); $error = $e->getMessage(); }
}

$historySearch = trim($_GET['history_search'] ?? '');
$historyType = trim($_GET['history_type'] ?? '');
$where = [];
$params = [];
$types = '';
if ($historySearch !== '') {
    $searchParam = '%' . $historySearch . '%';
    $where[] = "(m.movement_no LIKE ? OR m.old_atm_id LIKE ? OR m.new_atm_id LIKE ? OR m.old_atm_name LIKE ? OR m.old_branch_name LIKE ? OR m.new_atm_name LIKE ? OR m.new_branch_name LIKE ? OR m.reference_no LIKE ? OR m.reason LIKE ? )";
    $types .= str_repeat('s', 9);
    for ($i = 0; $i < 9; $i++) {
        $params[] = $searchParam;
    }
}
if ($historyType !== '') {
    $where[] = "m.movement_type = ?";
    $types .= 's';
    $params[] = $historyType;
}
$sql = "SELECT m.*, u.username FROM atm_device_movement m LEFT JOIN users u ON m.created_by = u.id";
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY m.id DESC LIMIT 15";
$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$history = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>ATM Device Movement</title>
<style>
    :root { --primary: #1d4ed8; --success: #15803d; --warning: #b45309; --danger: #b91c1c; --slate: #4b5563; }
    body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f1f5f9; margin: 0; padding: 20px; color: #1e293b; font-size: 13px; }
    .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; margin-bottom: 20px; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 25px; }
    .section-title { font-weight: 800; font-size: 11px; color: var(--primary); text-transform: uppercase; margin-bottom: 15px; border-bottom: 2px solid #f1f5f9; padding-bottom: 5px; letter-spacing: 0.5px; }
    label { font-weight: 700; font-size: 11px; color: #475569; text-transform: uppercase; margin-bottom: 4px; display: block; }
    input, select, textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; margin-bottom: 12px; box-sizing: border-box; }
    input:focus, select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.1); }
    .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; color: #fff; text-decoration: none; font-weight: 600; transition: 0.2s; gap: 8px; }
    .btn:hover { filter: brightness(1.1); transform: translateY(-1px); }
    .btn-sm { padding: 6px 10px; font-size: 12px; border-radius: 6px; }
    .btn-danger { background: var(--danger); }
    .btn-blue { background: var(--primary); } .btn-gray { background: var(--slate); }
    .message-success { background: #dcfce7; color: #166534; padding: 14px 18px; border-radius: 10px; border: 1px solid #bbf7d0; margin-bottom: 20px; }
    .message-error { background: #fee2e2; color: #991b1b; padding: 14px 18px; border-radius: 10px; border: 1px solid #fecaca; margin-bottom: 20px; }
    .target-box { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 20px; border-radius: 12px; }
    .old-box { background: #fff; border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; }
    table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 10px; overflow: hidden; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f1f5f9; }
    th { background: #f8fafc; font-size: 11px; color: #64748b; text-transform: uppercase; }
</style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="card" style="display:flex; justify-content:space-between; align-items:center;">
    <h2 style="margin:0; font-weight:800;">Device Movement Panel</h2>
    <div style="display:flex; gap:10px;">
        <a href="manage_atm_master.php" class="btn btn-gray">ATM Master</a>
        <a href="dashboard_ajax_v2.php" class="btn btn-gray">Dashboard</a>
    </div>
</div>

<?php if ($msg === 'success'): ?>
<div class="message-success">New log entry saved successfully.</div>
<?php elseif ($msg === 'updated'): ?>
<div class="message-success">Log entry updated successfully.</div>
<?php elseif ($msg === 'deleted'): ?>
<div class="message-success">Log entry deleted successfully.</div>
<?php elseif ($error): ?>
<div class="message-error"><?= h($error) ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" id="movementForm">
        <input type="hidden" name="edit_id" value="<?= h($editData['id'] ?? '') ?>">
        <div class="grid">
            <!-- 1. Selection -->
            <div class="form-group old-box">
                <div class="section-title">1. Movement Type</div>
                <label>Movement Type</label>
                <select name="movement_type" id="m_type" required onchange="updateUI()">
                    <option value="">-- Select --</option>
                    <option value="TRANSFER" <?= ($editData['movement_type'] ?? '') === 'TRANSFER' ? 'selected' : '' ?>>Transfer Booth/Branch</option>
                    <option value="ATM_ID_CHANGE" <?= ($editData['movement_type'] ?? '') === 'ATM_ID_CHANGE' ? 'selected' : '' ?>>ATM ID Change</option>
                    <option value="VENDOR_CHANGE" <?= ($editData['movement_type'] ?? '') === 'VENDOR_CHANGE' ? 'selected' : '' ?>>Vendor Change</option>
                    <option value="OBSOLETE" <?= ($editData['movement_type'] ?? '') === 'OBSOLETE' ? 'selected' : '' ?>>Obsolete / Removed</option>
                </select>
                <label>Device Type</label>
                <select name="device_type" id="d_type" required onchange="updateUI()">
                    <option value="ATM" <?= ($editData['device_type'] ?? '') === 'ATM' ? 'selected' : '' ?>>ATM</option>
                    <option value="CRM" <?= ($editData['device_type'] ?? '') === 'CRM' ? 'selected' : '' ?>>CRM</option>
                    <option value="UPS" <?= ($editData['device_type'] ?? '') === 'UPS' ? 'selected' : '' ?>>UPS</option>
                </select>
                <label>Target ATM ID (Current)</label>
                <input type="text" name="old_atm_id" id="target_id" required placeholder="Type ATM ID and click outside" onblur="fetchAtm()" value="<?= h($editData['old_atm_id'] ?? '') ?>">
            </div>

            <!-- 2. Current (Old) Info -->
            <div class="form-group old-box">
                <div class="section-title">2. Current Info (Old)</div>
                <label>Booth Name</label><input type="text" name="old_atm_name" id="o_name" value="<?= h($editData['old_atm_name'] ?? '') ?>">
                <label>Branch Name</label><input type="text" name="old_branch_name" id="o_branch" value="<?= h($editData['old_branch_name'] ?? '') ?>">
                <label>Zone Name</label><input type="text" name="old_zone_name" id="o_zone" value="<?= h($editData['old_zone_name'] ?? '') ?>">
                <label>Group No</label><input type="text" name="old_group_no" id="o_group" value="<?= h($editData['old_group_no'] ?? '') ?>">
                <div style="display:flex; gap:10px;">
                    <div style="flex:1;"><label>ATM Vendor</label>
                        <select name="old_atm_vendor_id" id="o_atm_v">
                            <option value="0">--Select--</option>
                            <?php foreach($atmVendors as $v): ?>
                                <option value="<?= (int)$v['id'] ?>" <?= ($editData['old_atm_vendor_id'] ?? '') == $v['id'] ? 'selected' : '' ?>><?= h($v['vendor_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex:1;"><label>UPS Vendor</label>
                        <select name="old_ups_vendor_id" id="o_ups_v">
                            <option value="0">--Select--</option>
                            <?php foreach($upsVendors as $v): ?>
                                <option value="<?= (int)$v['id'] ?>" <?= ($editData['old_ups_vendor_id'] ?? '') == $v['id'] ? 'selected' : '' ?>><?= h($v['vendor_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 3. Target (New) Info -->
            <div class="form-group target-box">
                <div class="section-title" style="color:var(--success);">3. Target Config (New)</div>
                
                <div id="box_id" style="display:none;"><label>New ATM ID</label><input type="text" name="new_atm_id" value="<?= h($editData['new_atm_id'] ?? '') ?>"></div>
                
                <div id="box_loc" style="display:none;">
                    <label>New Booth Name</label><input type="text" name="new_atm_name" value="<?= h($editData['new_atm_name'] ?? '') ?>">
                    <label>New Branch Name</label><input type="text" name="new_branch_name" value="<?= h($editData['new_branch_name'] ?? '') ?>">
                    <label>New Zone Name</label><input type="text" name="new_zone_name" value="<?= h($editData['new_zone_name'] ?? '') ?>">
                    <label>New Group No</label><input type="text" name="new_group_no" value="<?= h($editData['new_group_no'] ?? '') ?>">
                </div>
                
                <div id="box_vendor" style="display:none;">
                    <div id="box_atm_v"><label>New ATM Vendor</label>
                        <select name="new_atm_vendor_id">
                            <option value="0">Select Vendor</option>
                            <?php foreach($atmVendors as $v): ?>
                                <option value="<?= (int)$v['id'] ?>" <?= ($editData['new_atm_vendor_id'] ?? '') == $v['id'] ? 'selected' : '' ?>><?= h($v['vendor_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="box_ups_v"><label>New UPS Vendor</label>
                        <select name="new_ups_vendor_id">
                            <option value="0">Select Vendor</option>
                            <?php foreach($upsVendors as $v): ?>
                                <option value="<?= (int)$v['id'] ?>" <?= ($editData['new_ups_vendor_id'] ?? '') == $v['id'] ? 'selected' : '' ?>><?= h($v['vendor_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div id="box_obsolete" style="display:none;"><p style="color:var(--danger); font-weight:bold;">This device will be marked as OBSOLETE.</p></div>
                <div id="placeholder_text" style="color:var(--slate); font-style:italic;">Please select a Movement Type first.</div>
            </div>
        </div>

        <!-- 4. Footer Details -->
        <div class="section-title" style="margin-top:20px;">4. Transaction Reference</div>
        <div class="grid" style="grid-template-columns: 1fr 1fr 2fr;">
            <div><label>Date</label><input type="date" name="movement_date" value="<?= h($editData['movement_date'] ?? date('Y-m-d')) ?>"></div>
            <div><label>Letter Ref No</label><input type="text" name="reference_no" value="<?= h($editData['reference_no'] ?? '') ?>"></div>
            <div><label>Reason / Remarks</label><textarea name="reason"><?= h($editData['reason'] ?? '') ?></textarea></div>
        </div>

        <div style="background:#fff7ed; padding:15px; border-radius:10px; border:1px solid #fdba74; margin:15px 0;">
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; color:#9a3412;">
                <input type="checkbox" name="history_only" value="1" style="width:18px; height:18px;">
                <strong>HISTORY ONLY</strong> (Tick if you already updated the Master DB and only want a log entry)
            </label>
        </div>

        <button type="submit" class="btn btn-blue" style="width:100%; height:50px; font-size:16px;" onclick="return confirm('<?= $editId ? 'Update this log entry?' : 'Execute this transaction?' ?>')"><?= $editId ? 'Update Log Entry' : 'Save & Sync Master Database' ?></button>
        <?php if ($editId): ?>
            <a href="atm_device_movement.php" class="btn btn-gray" style="margin-top:12px; width:100%;">Cancel Edit</a>
            <p style="margin-top:12px; color:#475569; font-size:13px;">Editing a log entry updates only the history record; ATM Master changes are not applied.</p>
        <?php endif; ?>
    </form>
</div>

<!-- History -->
<div class="card">
    <h3 style="margin:0 0 15px 0;">Recent Logs</h3>
    <form method="get" action="atm_device_movement.php" style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; margin-bottom:18px;">
        <div style="flex:1 1 320px; min-width:220px;">
            <label style="display:block; margin-bottom:6px; font-weight:700; font-size:11px; color:#475569; text-transform:uppercase;">Search</label>
            <input type="search" name="history_search" value="<?= h($historySearch) ?>" placeholder="movement no, ATM id, booth, ref no" style="width:100%;">
        </div>
        <div style="width:210px;">
            <label style="display:block; margin-bottom:6px; font-weight:700; font-size:11px; color:#475569; text-transform:uppercase;">Type</label>
            <select name="history_type" style="width:100%;">
                <option value="">-- All Types --</option>
                <option value="TRANSFER" <?= $historyType === 'TRANSFER' ? 'selected' : '' ?>>Transfer</option>
                <option value="ATM_ID_CHANGE" <?= $historyType === 'ATM_ID_CHANGE' ? 'selected' : '' ?>>ATM ID Change</option>
                <option value="VENDOR_CHANGE" <?= $historyType === 'VENDOR_CHANGE' ? 'selected' : '' ?>>Vendor Change</option>
                <option value="OBSOLETE" <?= $historyType === 'OBSOLETE' ? 'selected' : '' ?>>Obsolete</option>
            </select>
        </div>
        <div style="display:flex; gap:10px;">
            <button type="submit" class="btn btn-blue btn-sm">Filter</button>
            <a href="atm_device_movement.php" class="btn btn-gray btn-sm">Reset</a>
        </div>
    </form>
    <div style="overflow-x:auto;">
        <table>
            <thead><tr><th>SL</th><th>No</th><th>Type</th><th>Old ID</th><th>New ID</th><th>Old Booth</th><th>New Booth</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
                <?php $sl=1; while($r = $history->fetch_assoc()): ?>
                <tr>
                    <td><?=$sl++?></td>
                    <td><?=h($r['movement_no'])?></td>
                    <td><?=h($r['movement_type'])?></td>
                    <td><?=h($r['old_atm_id'])?></td>
                    <td><?=h($r['new_atm_id']?:'-')?></td>
                    <td><?=h($r['old_atm_name'])?> <br><small><?=h($r['old_branch_name'])?></small></td>
                    <td><?=h($r['new_atm_name']?:'-')?> <br><small><?=h($r['new_branch_name']?:'-')?></small></td>
                    <td><?=h($r['movement_date'])?></td>
                    <td style="white-space:nowrap;">
                        <a href="atm_device_movement.php?edit_id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-blue">Edit</a>
                        <a href="atm_device_movement.php?delete_id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this log entry?')">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// ১. নতুন ফিল্ড দেখানোর লজিক (স্মার্ট সুইচ)
function updateUI() {
    const m = document.getElementById('m_type').value;
    const d = document.getElementById('d_type').value;
    
    document.getElementById('box_id').style.display = (m === 'ATM_ID_CHANGE' ? 'block' : 'none');
    document.getElementById('box_loc').style.display = (m === 'TRANSFER' ? 'block' : 'none');
    document.getElementById('box_obsolete').style.display = (m === 'OBSOLETE' ? 'block' : 'none');
    document.getElementById('box_vendor').style.display = (m === 'VENDOR_CHANGE' ? 'block' : 'none');
    
    if (m === 'VENDOR_CHANGE') {
        document.getElementById('box_atm_v').style.display = (d === 'UPS' ? 'none' : 'block');
        document.getElementById('box_ups_v').style.display = (d === 'UPS' ? 'block' : 'none');
    }
    
    document.getElementById('placeholder_text').style.display = (m ? 'none' : 'block');
}

// ২. ড্রপডাউন সিলেক্ট করার স্মার্ট ফাংশন (ID অথবা Text দিয়ে)
function selectDropdownOption(elementId, idValue, textName) {
    const el = document.getElementById(elementId);
    if (!el) return;
    
    // প্রথমে ID দিয়ে চেষ্টা করা
    if (idValue && idValue > 0) {
        el.value = idValue;
    } 
    
    // যদি ID দিয়ে না মেলে (পুরানো ডাটা), তবে টেক্সট দিয়ে চেষ্টা করা
    if (el.selectedIndex <= 0 && textName) {
        const searchText = textName.trim().toLowerCase();
        for (let i = 0; i < el.options.length; i++) {
            if (el.options[i].text.trim().toLowerCase() === searchText) {
                el.selectedIndex = i;
                break;
            }
        }
    }
}

function fetchAtm() {
    const atmId = document.getElementById('target_id').value.trim();
    if (!atmId) return;
    
    fetch('atm_device_movement.php?ajax=fetch_atm&atm_id=' + encodeURIComponent(atmId))
        .then(r => r.json())
        .then(res => {
            if (!res.success) { alert('Error: ATM ID not found!'); return; }
            
            const d = res.data;
            document.getElementById('o_name').value = d.atm_name || '';
            document.getElementById('o_branch').value = d.branch_name || '';
            document.getElementById('o_zone').value = d.zone_name || '';
            document.getElementById('o_group').value = d.group_no || '';
            
            // ভেন্ডর ড্রপডাউন সিলেক্ট করা (Fix)
            selectDropdownOption('o_atm_v', d.atm_vendor_id, d.atm_vendor);
            selectDropdownOption('o_ups_v', d.ups_vendor_id, d.ups_vendor);
        });
}
updateUI();
</script>
</body>
</html>