<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';
include 'includes/cctv_helpers.php';

Auth::requirePermission('cctv_spare_requisition');

if (!function_exists('h')) {
    function h($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

/* =========================================
   LAZY MIGRATION: কলাম না থাকলে তৈরি করবে
========================================= */
// 1. Check & Add branch_contact
$checkBC = $conn->query("SHOW COLUMNS FROM cctv_spare_requisition LIKE 'branch_contact'");
if ($checkBC && $checkBC->num_rows === 0) {
    $conn->query("ALTER TABLE cctv_spare_requisition ADD COLUMN branch_contact TEXT NULL AFTER cctv_location_id");
}

// 2. Check & Add ip_details
$checkIP = $conn->query("SHOW COLUMNS FROM cctv_spare_requisition LIKE 'ip_details'");
if ($checkIP && $checkIP->num_rows === 0) {
    $conn->query("ALTER TABLE cctv_spare_requisition ADD COLUMN ip_details TEXT NULL AFTER branch_contact");
}

/* =========================================
   AJAX HANDLERS
========================================= */

// Fetch Branch Contact
if (isset($_GET['ajax']) && $_GET['ajax'] === 'fetch_branch_contact') {
    header('Content-Type: application/json');
    $branchName = trim($_GET['branch_name'] ?? '');
    $branchContact = '';
    if ($branchName !== '') {
        $stmt = $conn->prepare("SELECT custodian1_name, custodian1_mobile, custodian2_name, custodian2_mobile FROM atm_contact WHERE LOWER(TRIM(branch_name)) = LOWER(TRIM(?)) ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("s", $branchName);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $contacts = [];
            if (!empty($row['custodian1_name'])) $contacts[] = 'C1: '.$row['custodian1_name'].($row['custodian1_mobile']?' ('.$row['custodian1_mobile'].')':'');
            if (!empty($row['custodian2_name'])) $contacts[] = 'C2: '.$row['custodian2_name'].($row['custodian2_mobile']?' ('.$row['custodian2_mobile'].')':'');
            $branchContact = implode('; ', $contacts);
        }
    }
    echo json_encode(['branch_contact' => $branchContact]);
    exit;
}

// Fetch ATM Details & IP
if (isset($_GET['ajax']) && $_GET['ajax'] === 'fetch_atm_full') {
    header('Content-Type: application/json');
    $atm_id = trim($_GET['atm_id'] ?? '');
    $stmt = $conn->prepare("SELECT * FROM cctv_list WHERE TRIM(atm_id) = ? LIMIT 1");
    $stmt->bind_param("s", $atm_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($data) {
        $data['success'] = true;
        $branchName = $data['branch_name'];
        $stmtC = $conn->prepare("SELECT custodian1_name, custodian1_mobile, custodian2_name, custodian2_mobile FROM atm_contact WHERE LOWER(TRIM(branch_name)) = LOWER(TRIM(?)) ORDER BY id DESC LIMIT 1");
        $stmtC->bind_param("s", $branchName);
        $stmtC->execute();
        $rowC = $stmtC->get_result()->fetch_assoc();
        $stmtC->close();
        if ($rowC) {
            $contacts = [];
            if (!empty($rowC['custodian1_name'])) $contacts[] = $rowC['custodian1_name'].($rowC['custodian1_mobile']?' ('.$rowC['custodian1_mobile'].')':'');
            if (!empty($rowC['custodian2_name'])) $contacts[] = $rowC['custodian2_name'].($rowC['custodian2_mobile']?' ('.$rowC['custodian2_mobile'].')':'');
            $data['fetched_contact'] = implode('; ', $contacts);
        } else { $data['fetched_contact'] = ''; }
        echo json_encode($data);
    } else { echo json_encode(['success' => false]); }
    exit;
}

// Stock Check
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_available_stock') {
    header('Content-Type: application/json');
    $item_id = (int)($_GET['item_id'] ?? 0);
    $source = $_GET['source'] ?? '';
    $stockType = ($source === 'OLD_REPAIRED') ? 'OLD_REPAIRED' : 'NEW_UNUSED';
    $sql = "SELECT id, serial_no, brand, model, qty FROM cctv_stock WHERE item_id = ? AND stock_type = ? AND status = 'In_Stock' AND qty > 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $item_id, $stockType);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    $stmt->close();
    exit;
}

/* =========================================
   SAVE / UPDATE LOGIC
========================================= */
$message = '';
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $editId > 0;

$itemsMaster = cctv_fetch_item_options($conn, 'SPARE_PART');
$vendors = cctv_fetch_vendor_options($conn);

$form = [
    'requisition_no' => '', 'application_date' => date('Y-m-d'), 'received_date' => '',
    'problem_details' => '', 'source_type' => 'BRANCH', 'action_type' => 'VENDOR_REPLACEMENT',
    'assigned_vendor_id' => '', 'service_charge' => '', 'service_charge_type' => 'NONE',
    'status' => 'Draft', 'notes' => '', 'atm_id' => '', 'branch_name' => '', 
    'branch_contact' => '', 'ip_details' => '', 'booth_name' => '', 'zone_name' => '', 'group_no' => '', 
    'service_type' => 'ATM', 'machine_type' => 'ATM', 'is_new_booth' => 0, 'location_remarks' => ''
];

$selectedItems = [];

if ($isEdit) {
    $sql = "SELECT s.*, l.atm_id, l.branch_name, l.booth_name, l.zone_name, l.group_no,
                   l.service_type, l.machine_type, l.is_new_booth, l.remarks AS location_remarks
            FROM cctv_spare_requisition s
            INNER JOIN cctv_locations l ON l.id = s.cctv_location_id
            WHERE s.id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    if ($data) {
        foreach ($form as $key => $val) { if (isset($data[$key])) $form[$key] = $data[$key]; }
        $stmtI = $conn->prepare("SELECT * FROM cctv_spare_requisition_items WHERE spare_requisition_id = ? ORDER BY id ASC");
        $stmtI->bind_param("i", $editId);
        $stmtI->execute();
        $selectedItems = $stmtI->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

if (!$isEdit) $form['requisition_no'] = cctv_generate_spare_req_no($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();
        
        $locationId = cctv_get_or_create_location($conn, [
            'atm_id'        => $_POST['atm_id'] ?? '',
            'branch_name'   => $_POST['branch_name'] ?? '',
            'booth_name'    => $_POST['booth_name'] ?? '',
            'zone_name'     => $_POST['zone_name'] ?? '',
            'group_no'      => $_POST['group_no'] ?? '',
            'service_type'  => $_POST['service_type'] ?? 'ATM',
            'machine_type'  => $_POST['machine_type'] ?? 'ATM',
            'is_new_booth'  => isset($_POST['is_new_booth']) ? 1 : 0,
            'remarks'       => $_POST['location_remarks'] ?? ''
        ]);

        if ($isEdit) {
            $stmt = $conn->prepare("UPDATE cctv_spare_requisition SET requisition_no=?, cctv_location_id=?, application_date=?, received_date=?, problem_details=?, source_type=?, action_type=?, assigned_vendor_id=?, service_charge=?, service_charge_type=?, status=?, notes=?, branch_contact=?, ip_details=? WHERE id=?");
            $stmt->bind_param("sisssssidsssssi", $_POST['requisition_no'], $locationId, $_POST['application_date'], $_POST['received_date'], $_POST['problem_details'], $_POST['source_type'], $_POST['action_type'], $_POST['assigned_vendor_id'], $_POST['service_charge'], $_POST['service_charge_type'], $_POST['status'], $_POST['notes'], $_POST['branch_contact'], $_POST['ip_details'], $editId);
        } else {
            $stmt = $conn->prepare("INSERT INTO cctv_spare_requisition (requisition_no, cctv_location_id, application_date, received_date, problem_details, source_type, action_type, assigned_vendor_id, service_charge, service_charge_type, status, notes, branch_contact, ip_details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sisssssidsssss", $_POST['requisition_no'], $locationId, $_POST['application_date'], $_POST['received_date'], $_POST['problem_details'], $_POST['source_type'], $_POST['action_type'], $_POST['assigned_vendor_id'], $_POST['service_charge'], $_POST['service_charge_type'], $_POST['status'], $_POST['notes'], $_POST['branch_contact'], $_POST['ip_details']);
        }
        $stmt->execute();
        $spareReqId = $isEdit ? $editId : $stmt->insert_id;

        $conn->query("DELETE FROM cctv_spare_requisition_items WHERE spare_requisition_id = $spareReqId");
        if (isset($_POST['item_id'])) {
            foreach ($_POST['item_id'] as $i => $itemId) {
                if (!$itemId) continue;
                $stmtI = $conn->prepare("INSERT INTO cctv_spare_requisition_items (spare_requisition_id, item_id, stock_id, qty, source_from, remarks) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtI->bind_param("iiiiss", $spareReqId, $itemId, $_POST['stock_id'][$i], $_POST['qty'][$i], $_POST['source_from'][$i], $_POST['item_remarks'][$i]);
                $stmtI->execute();
            }
        }
        $conn->commit();
        header("Location: cctv_spare_requisition_list.php?msg=success");
        exit;
    } catch (Exception $e) { $conn->rollback(); $message = $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $isEdit ? 'Edit' : 'New' ?> CCTV Spare Requisition</title>
    <style>
        body{font-family:Arial, sans-serif;background:#f4f7fa;margin:0;padding:20px; font-size: 14px;}
        .container{max-width:1200px;margin:auto;background:#fff;padding:25px;border-radius:12px;box-shadow:0 5px 20px rgba(0,0,0,0.1);}
        .section-header{ background:#f8f9fa; padding:10px 15px; border-left:4px solid #0d6efd; margin:20px 0 15px; font-weight:bold; }
        .row{display:flex;flex-wrap:wrap;gap:15px;margin-bottom:15px;}
        .col{flex:1;min-width:230px;}
        label{display:block;font-weight:600;margin-bottom:6px; color:#555;}
        input, select, textarea{width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;box-sizing:border-box;}
        .btn{padding:10px 18px;border:none;border-radius:6px;cursor:pointer;color:#fff;font-weight:bold;text-decoration:none;font-size:13px;}
        .btn-blue{background:#0d6efd;} .btn-success{background:#198754;} .btn-danger{background:#dc3545;}
        table{width:100%;border-collapse:collapse;margin-top:10px;}
        th, td{border:1px solid #dee2e6;padding:12px;text-align:left;}
        th{background:#f8f9fa;}
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <h2><?= $isEdit ? 'Edit' : 'New' ?> CCTV Spare Requisition</h2>
    <?php if($message): ?><div style="color:red; margin-bottom:20px;"><?= h($message) ?></div><?php endif; ?>

    <form method="post">
        <input type="hidden" name="id" value="<?= (int)$editId ?>">

        <div class="section-header">Basic Information</div>
        <div class="row">
            <div class="col"><label>Requisition No</label><input type="text" name="requisition_no" value="<?= h($form['requisition_no']) ?>" readonly style="background:#eee;"></div>
            <div class="col"><label>Application Date</label><input type="date" name="application_date" value="<?= h($form['application_date']) ?>" required></div>
            <div class="col">
                <label>Source Type</label>
                <select name="source_type">
                    <option value="BRANCH" <?= $form['source_type']=='BRANCH'?'selected':'' ?>>Branch Request</option>
                    <option value="VENDOR" <?= $form['source_type']=='VENDOR'?'selected':'' ?>>Vendor Report</option>
                </select>
            </div>
            <div class="col">
                <label>Status</label>
                <select name="status">
                    <?php $statuses = ['Draft', 'Waiting for Approval', 'Vendor Assigned', 'Installed', 'Cancelled'];
                    foreach($statuses as $st): ?>
                        <option value="<?= $st ?>" <?= $form['status']==$st?'selected':'' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="section-header">Location Details</div>
        <div class="row">
            <div class="col" style="flex:0.3;"><label><input type="checkbox" name="is_new_booth" id="is_new" <?= $form['is_new_booth']?'checked':'' ?>> New Booth?</label></div>
            <div class="col"><label>ATM ID</label><input type="text" name="atm_id" id="atm_id" value="<?= h($form['atm_id']) ?>"></div>
            <div class="col"><label>Booth Name</label><input type="text" name="booth_name" id="booth_name" value="<?= h($form['booth_name']) ?>"></div>
        </div>
        <div class="row">
            <div class="col"><label>Branch Name</label><input type="text" name="branch_name" id="branch_name" value="<?= h($form['branch_name']) ?>"></div>
            <div class="col"><label>Branch Contact</label><input type="text" name="branch_contact" id="branch_contact" value="<?= h($form['branch_contact']) ?>" placeholder="Custodian data"></div>
        </div>
        <div class="row">
            <div class="col">
                <label>IP Details</label>
                <input type="text" name="ip_details" id="ip_details" value="<?= h($form['ip_details']) ?>" readonly style="background:#f9f9f9;" placeholder="Auto-filled from CCTV List">
            </div>
            <div class="col"><label>Zone</label><input type="text" name="zone_name" id="zone_name" value="<?= h($form['zone_name']) ?>"></div>
            <div class="col"><label>Group No</label><input type="text" name="group_no" id="group_no" value="<?= h($form['group_no']) ?>"></div>
        </div>
        <div class="row">
            <div class="col">
                <label>Service Type</label>
                <select name="service_type">
                    <option value="ATM" <?= $form['service_type']=='ATM'?'selected':'' ?>>ATM</option>
                    <option value="CRM" <?= $form['service_type']=='CRM'?'selected':'' ?>>CRM</option>
                    <option value="BRANCH" <?= $form['service_type']=='BRANCH'?'selected':'' ?>>BRANCH</option>
                </select>
            </div>
            <div class="col">
                <label>Machine Type</label>
                <select name="machine_type">
                    <option value="ATM" <?= $form['machine_type']=='ATM'?'selected':'' ?>>ATM</option>
                    <option value="CRM" <?= $form['machine_type']=='CRM'?'selected':'' ?>>CRM</option>
                    <option value="RCM" <?= $form['machine_type']=='RCM'?'selected':'' ?>>RCM</option>
                </select>
            </div>
            <div class="col"><label>Location Remarks</label><input type="text" name="location_remarks" value="<?= h($form['location_remarks']) ?>"></div>
        </div>

        <div class="section-header">Spare Items</div>
        <table id="itemTable">
            <thead>
                <tr>
                    <th>Item</th>
                    <th style="width:80px;">Qty</th>
                    <th>Source</th>
                    <th>Stock/Serial</th>
                    <th>Remarks</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($selectedItems)) $selectedItems = [[]]; 
                foreach($selectedItems as $item): ?>
                <tr class="item-row">
                    <td>
                        <select name="item_id[]" class="item-select" onchange="handleItemChange(this)">
                            <option value="">-- Select Item --</option>
                            <?php foreach($itemsMaster as $im): ?>
                                <option value="<?= $im['id'] ?>" <?= ($item['item_id']??0)==$im['id']?'selected':'' ?>><?= h($im['item_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="number" name="qty[]" value="<?= $item['qty']??1 ?>"></td>
                    <td>
                        <select name="source_from[]" class="source-select" onchange="handleItemChange(this)">
                            <option value="VENDOR" <?= ($item['source_from']??'')=='VENDOR'?'selected':'' ?>>Vendor</option>
                            <option value="NEW_STOCK" <?= ($item['source_from']??'')=='NEW_STOCK'?'selected':'' ?>>New Stock</option>
                            <option value="OLD_REPAIRED" <?= ($item['source_from']??'')=='OLD_REPAIRED'?'selected':'' ?>>Old Repaired</option>
                        </select>
                    </td>
                    <td><select name="stock_id[]" class="stock-id-select" data-selected="<?= $item['stock_id']??'' ?>"></select></td>
                    <td><input type="text" name="item_remarks[]" value="<?= h($item['remarks']??'') ?>"></td>
                    <td><button type="button" class="btn btn-danger" onclick="this.closest('tr').remove()">X</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="button" class="btn btn-success" style="margin-top:10px;" onclick="addItemRow()">+ Add Item</button>

        <div class="section-header">Problem & Action Details</div>
        <div class="row">
            <div class="col"><label>Problem Details *</label><textarea name="problem_details" required><?= h($form['problem_details']) ?></textarea></div>
            <div class="col">
                <label>Action Type</label>
                <select name="action_type">
                    <option value="VENDOR_REPLACEMENT" <?= $form['action_type']=='VENDOR_REPLACEMENT'?'selected':'' ?>>Vendor Replacement</option>
                    <option value="STOCK_INSTALL" <?= $form['action_type']=='STOCK_INSTALL'?'selected':'' ?>>Stock Install</option>
                </select>
            </div>
            <div class="col">
                <label>Assigned Vendor</label>
                <select name="assigned_vendor_id">
                    <option value="">-- Select --</option>
                    <?php foreach($vendors as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= $form['assigned_vendor_id']==$v['id']?'selected':'' ?>><?= h($v['vendor_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="margin-top:30px; text-align:right;">
            <a href="cctv_spare_requisition_list.php" class="btn" style="background:#6c757d;">Cancel</a>
            <button type="submit" class="btn btn-blue">Save Requisition</button>
        </div>
    </form>
</div>

<script>
document.getElementById('atm_id').addEventListener('blur', function() {
    const atmId = this.value.trim();
    if (!atmId || document.getElementById('is_new').checked) return;
    fetch(`cctv_spare_requisition.php?ajax=fetch_atm_full&atm_id=${encodeURIComponent(atmId)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('booth_name').value = data.atm_name || '';
                document.getElementById('branch_name').value = data.branch_name || '';
                document.getElementById('zone_name').value = data.zone_name || '';
                let ipArr = [];
                if (data.m_ip) ipArr.push("M-IP: " + data.m_ip);
                if (data.ip_address) ipArr.push("IP: " + data.ip_address);
                if (data.subnet) ipArr.push("Subnet: " + data.subnet);
                if (data.gateway) ipArr.push("GW: " + data.gateway);
                document.getElementById('ip_details').value = ipArr.join(' | ');
                if (data.fetched_contact) {
                    document.getElementById('branch_contact').value = data.fetched_contact;
                } else if (data.branch_name) {
                    fetchBranchContact(data.branch_name);
                }
            }
        });
});

function fetchBranchContact(branchName) {
    if (!branchName) return;
    fetch(`cctv_spare_requisition.php?ajax=fetch_branch_contact&branch_name=${encodeURIComponent(branchName)}`)
        .then(res => res.json())
        .then(data => {
            if (data.branch_contact) document.getElementById('branch_contact').value = data.branch_contact;
        });
}

function handleItemChange(el) {
    const row = el.closest('tr');
    const itemId = row.querySelector('.item-select').value;
    const source = row.querySelector('.source-select').value;
    const stockSelect = row.querySelector('.stock-id-select');
    if (!itemId || source === 'VENDOR') {
        stockSelect.innerHTML = '<option value="">N/A</option>';
        return;
    }
    fetch(`cctv_spare_requisition.php?ajax=get_available_stock&item_id=${itemId}&source=${source}`)
        .then(res => res.json())
        .then(data => {
            let html = '<option value="">-- Select Serial --</option>';
            const selected = stockSelect.getAttribute('data-selected');
            data.forEach(s => {
                html += `<option value="${s.id}" ${s.id == selected ? 'selected' : ''}>${s.serial_no} (${s.brand})</option>`;
            });
            stockSelect.innerHTML = html;
        });
}

function addItemRow() {
    const tbody = document.querySelector('#itemTable tbody');
    if (tbody.rows.length > 0) {
        const newRow = tbody.rows[0].cloneNode(true);
        newRow.querySelectorAll('input').forEach(i => i.value = '');
        newRow.querySelector('input[type="number"]').value = 1;
        newRow.querySelector('.stock-id-select').innerHTML = '';
        tbody.appendChild(newRow);
    }
}
document.querySelectorAll('.item-row').forEach(row => {
    if (row.querySelector('.item-select').value) handleItemChange(row.querySelector('.item-select'));
});
</script>
</body>
</html>