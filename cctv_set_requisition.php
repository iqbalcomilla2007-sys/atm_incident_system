<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';
include 'includes/cctv_helpers.php';

Auth::requirePermission('cctv_set_requisition');

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function cctv_h($str) {
    return h($str);
}

$message = '';
$messageType = 'success';
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $editId > 0;

/* =========================================================
   FETCH MASTER DATA (Active Items)
========================================================= */
$itemsMaster = [];
$resItems = $conn->query("SELECT * FROM cctv_item_master WHERE is_active = 1 AND item_category = 'SET_ITEM' ORDER BY item_name ASC");
while ($row = $resItems->fetch_assoc()) {
    $itemsMaster[] = $row;
}

$vendors = cctv_fetch_vendor_options($conn);

$statusOptions = [
    'Waiting_for_Approval' => 'Waiting for approval',
    'Sent_to_CPD' => 'Sent to CPD',
    'Waiting_for_Technical_Evaluation' => 'Waiting for technical evaluation',
    'Waiting_for_Work_Order' => 'Waiting for work order',
    'Waiting_for_Delivery' => 'Waiting for delivery',
    'Waiting_for_QC' => 'Waiting for QC',
    'Technical_Evaluation_Passed' => 'Technical eve passed',
    'Technical_Evaluation_Failed' => 'Technical eve failed',
    'QC_Passed' => 'QC passed',
    'QC_Failed' => 'QC failed',
    'Waiting_for_Installation' => 'Waiting for Installation',
    'Installed' => 'Installed',
];

$legacyStatusMap = [
    'Draft' => 'Waiting_for_Approval', 'Approved' => 'Sent_to_CPD', 'Received' => 'Waiting_for_Approval',
    'Forwarded_to_CPD' => 'Sent_to_CPD', 'Tender_Received' => 'Waiting_for_Technical_Evaluation',
    'Under_Technical_Evaluation' => 'Waiting_for_Technical_Evaluation', 'Technically_Qualified' => 'Technical_Evaluation_Passed',
    'Work_Order_Issued' => 'Waiting_for_Delivery', 'Product_Delivered' => 'Waiting_for_QC',
    'Installation_Permission_Given' => 'Waiting_for_Installation',
];

$form = [
    'requisition_no' => '', 'source_type' => 'BRANCH', 'requisition_date' => date('Y-m-d'), 'received_date' => '',
    'cause' => '', 'forwarded_to_cpd_date' => '', 'tender_received_date' => '', 'technical_evaluation_date' => '',
    'qc_date' => '', 'installation_permission_date' => '', 'installation_date' => '', 'status' => 'Waiting_for_Approval',
    'work_order_no' => '', 'work_order_date' => '', 'selected_vendor_id' => '', 'branch_contact' => '',
    'monitoring_ip' => '', 'internal_ip' => '', 'subnet_mask' => '', 'gateway' => '', 'notes' => '',
    'atm_master_id' => '', 'atm_id' => '', 'branch_name' => '', 'booth_name' => '', 'zone_name' => '',
    'group_no' => '', 'service_type' => 'ATM', 'machine_type' => 'ATM', 'is_new_booth' => 0, 'location_remarks' => ''
];

$selectedItems = [];
$installedDeviceMap = [];
$workOrder = ['vendor_id' => '', 'vendor_name' => '', 'work_order_no' => '', 'work_order_date' => '', 'delivery_deadline' => '', 'work_order_amount' => '', 'remarks' => ''];

/* =========================================================
   LOAD DATA FOR EDIT
========================================================= */
if ($isEdit) {
    $sql = "SELECT r.*, l.atm_master_id, l.atm_id, l.branch_name, l.booth_name, l.zone_name, l.group_no,
                   l.service_type, l.machine_type, l.is_new_booth, l.remarks AS location_remarks
            FROM cctv_set_requisition r LEFT JOIN cctv_locations l ON l.id = r.cctv_location_id
            WHERE r.id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($data) {
        foreach ($form as $key => $val) { if (isset($data[$key])) $form[$key] = $data[$key]; }
        if (isset($legacyStatusMap[$form['status']])) { $form['status'] = $legacyStatusMap[$form['status']]; }
        
        $sqlItems = "SELECT ri.*, m.item_name, m.brand, m.model FROM cctv_set_requisition_items ri LEFT JOIN cctv_item_master m ON ri.item_id = m.id WHERE ri.requisition_id = ? ORDER BY ri.id ASC";
        $stmtItems = $conn->prepare($sqlItems); $stmtItems->bind_param("i", $editId); $stmtItems->execute();
        $selectedItems = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC); $stmtItems->close();
    }
}
if (!$isEdit && empty($form['requisition_no'])) { $form['requisition_no'] = cctv_generate_requisition_no($conn); }

/* =========================================================
   SAVE / UPDATE LOGIC
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();
        $editIdQuery = (int)($_POST['id'] ?? 0);
        $isEditNow = $editIdQuery > 0;
        $is_new_booth = isset($_POST['is_new_booth']) ? 1 : 0;
        $status = $_POST['status'] ?? 'Waiting_for_Approval';

        $locationId = cctv_get_or_create_location($conn, [
            'atm_master_id' => $_POST['atm_master_id'] ?: null,
            'atm_id' => trim($_POST['atm_id']),
            'branch_name' => trim($_POST['branch_name']),
            'booth_name' => trim($_POST['booth_name']),
            'zone_name' => $_POST['zone_name'],
            'group_no' => $_POST['group_no'],
            'service_type' => $_POST['service_type'],
            'machine_type' => $_POST['machine_type'],
            'is_new_booth' => $is_new_booth,
            'remarks' => $_POST['location_remarks']
        ]);

        $vId = (!empty($_POST['selected_vendor_id'])) ? (int)$_POST['selected_vendor_id'] : null;

        if ($isEditNow) {
            $sql = "UPDATE cctv_set_requisition SET requisition_no=?, cctv_location_id=?, source_type=?, requisition_date=?, status=?, branch_contact=?, monitoring_ip=?, internal_ip=?, subnet_mask=?, gateway=?, notes=?, installation_date=?, selected_vendor_id=?, work_order_no=?, work_order_date=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sissssssssssissi", $_POST['requisition_no'], $locationId, $_POST['source_type'], $_POST['requisition_date'], $status, $_POST['branch_contact'], $_POST['monitoring_ip'], $_POST['internal_ip'], $_POST['subnet_mask'], $_POST['gateway'], $_POST['notes'], $_POST['installation_date'], $vId, $_POST['work_order_no'], $_POST['work_order_date'], $editIdQuery);
            $stmt->execute(); $reqId = $editIdQuery;
        } else {
            $sql = "INSERT INTO cctv_set_requisition (requisition_no, cctv_location_id, source_type, requisition_date, status, branch_contact, monitoring_ip, internal_ip, subnet_mask, gateway, notes, installation_date, selected_vendor_id, work_order_no, work_order_date, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmt = $conn->prepare($sql);
            $createdBy = $_SESSION['user_id'] ?? null;
            $stmt->bind_param("sissssssssssissi", $_POST['requisition_no'], $locationId, $_POST['source_type'], $_POST['requisition_date'], $status, $_POST['branch_contact'], $_POST['monitoring_ip'], $_POST['internal_ip'], $_POST['subnet_mask'], $_POST['gateway'], $_POST['notes'], $_POST['installation_date'], $vId, $_POST['work_order_no'], $_POST['work_order_date'], $createdBy);
            $stmt->execute(); $reqId = $stmt->insert_id;
        }

        // SAVE ITEMS
        $conn->query("DELETE FROM cctv_set_requisition_items WHERE requisition_id = $reqId");
        $item_ids = $_POST['item_id'] ?? []; $qtys = $_POST['qty'] ?? [];
        for ($i=0; $i<count($item_ids); $i++) {
            $tid = (int)$item_ids[$i]; $tqty = (int)$qtys[$i];
            if ($tid > 0 && $tqty > 0) {
                $stmt = $conn->prepare("INSERT INTO cctv_set_requisition_items (requisition_id, item_id, qty) VALUES (?,?,?)");
                $stmt->bind_param("iii", $reqId, $tid, $tqty); $stmt->execute();
            }
        }

        // REMARKS HISTORY
        $newRemark = trim($_POST['new_history_remark'] ?? '');
        if ($newRemark !== '') {
            $stmtRem = $conn->prepare("INSERT INTO cctv_set_requisition_remarks (set_requisition_id, remark, created_by, created_at) VALUES (?, ?, ?, NOW())");
            $userId = $_SESSION['user_id'] ?? null;
            $stmtRem->bind_param("isi", $reqId, $newRemark, $userId); $stmtRem->execute();
        }

        $conn->commit();
        header("Location: cctv_set_requisition_list.php?msg=success"); exit;
    } catch (Exception $e) { if ($conn->in_transaction) $conn->rollback(); $message = $e->getMessage(); }
}

$history = [];
if ($isEdit) {
    $res = $conn->query("SELECT r.*, u.username FROM cctv_set_requisition_remarks r LEFT JOIN users u ON r.created_by = u.id WHERE r.set_requisition_id = $editId ORDER BY r.created_at DESC");
    if ($res) { while ($row = $res->fetch_assoc()) { $history[] = $row; } }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCTV Set Requisition</title>
    <style>
        body{font-family:Arial, sans-serif;background:#f4f7fa;margin:0;padding:20px; font-size: 13px;}
        .container{max-width:1250px;margin:auto;background:#fff;padding:25px;border-radius:12px;box-shadow:0 5px 20px rgba(0,0,0,0.1);}
        h2{margin:0 0 20px; color:#0d6efd;}
        .row{display:flex;flex-wrap:wrap;gap:15px;margin-bottom:15px;}
        .col{flex:1;min-width:230px;}
        label{display:block;font-weight:600;margin-bottom:6px; color:#555;}
        input, select, textarea{width:100%;padding:9px;border:1px solid #ccc;border-radius:6px;box-sizing:border-box;}
        .btn{padding:8px 16px;border:none;border-radius:6px;cursor:pointer;color:#fff;text-decoration:none;font-weight:bold;}
        .btn-blue{background:#0d6efd;} .btn-success{background:#198754;} .btn-secondary{background:#6c757d;}
        .section-header{ background:#f1f3f5; padding:8px 12px; margin-bottom:15px; border-left:4px solid #0d6efd; font-weight:bold; }
        .conditional-section{display:none;} .install-col{display:none;}
        table{width:100%;border-collapse:collapse;margin-top:10px;} th, td{border:1px solid #dee2e6;padding:10px;text-align:left;}
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="margin:0;">Requisition Entry</h2>
        <div style="display:flex; gap:10px;">
            <a href="cctv_dashboard.php" class="btn btn-blue">Dashboard</a>
            <a href="cctv_set_requisition_list.php" class="btn btn-secondary">Back to List</a>
        </div>
    </div>

    <?php if($message): ?><div style="padding:10px; background:#f8d7da; color:#721c24; margin-bottom:15px; border-radius:5px;"><?=h($message)?></div><?php endif; ?>

    <form method="post">
        <input type="hidden" name="id" value="<?= (int)$editId ?>">
        <input type="hidden" name="atm_master_id" id="atm_master_id" value="<?= h($form['atm_master_id']) ?>">

        <div class="section-header">Basic Info & Schedule</div>
        <div class="row">
            <div class="col"><label>Req. No</label><input type="text" name="requisition_no" value="<?= h($form['requisition_no']) ?>" readonly style="background:#eee;"></div>
            <div class="col"><label>Req. Date</label><input type="date" name="requisition_date" value="<?= h($form['requisition_date']) ?>" required></div>
            <div class="col"><label>Status</label>
                <select name="status" id="status">
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= $form['status']===$value?'selected':'' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col"><label>Installation Date</label><input type="date" name="installation_date" value="<?= h($form['installation_date']) ?>"></div>
        </div>

        <div class="section-header">Location & Custodian Details</div>
        <div class="row">
            <div class="col" style="flex:0.3;"><label><input type="checkbox" name="is_new_booth" id="is_new" <?= $form['is_new_booth']?'checked':'' ?>> New Booth?</label></div>
            <div class="col"><label>ATM ID</label><input type="text" name="atm_id" id="atm_id" value="<?= h($form['atm_id']) ?>" placeholder="Type ATM ID and click outside"></div>
            <div class="col"><label>Booth Name</label><input type="text" name="booth_name" id="booth_name" value="<?= h($form['booth_name']) ?>" required></div>
        </div>
        <div class="row">
            <div class="col"><label>Branch Name</label><input type="text" name="branch_name" id="branch_name" value="<?= h($form['branch_name']) ?>" required></div>
            <div class="col"><label>Zone Name</label><input type="text" name="zone_name" id="zone_name" value="<?= h($form['zone_name']) ?>"></div>
            <div class="col"><label>Custodian & Mobile No (Branch Contact)</label><input type="text" name="branch_contact" id="branch_contact" value="<?= h($form['branch_contact']) ?>" placeholder="e.g. Mr. Rahim, 01711XXXXXX"></div>
        </div>

        <div class="section-header">Networking Details (Auto-fetched)</div>
        <div class="row">
            <div class="col"><label>Monitoring IP</label><input type="text" name="monitoring_ip" id="monitoring_ip" value="<?= h($form['monitoring_ip']) ?>"></div>
            <div class="col"><label>Internal IP</label><input type="text" name="internal_ip" id="internal_ip" value="<?= h($form['internal_ip']) ?>"></div>
            <div class="col"><label>Subnet Mask</label><input type="text" name="subnet_mask" id="subnet_mask" value="<?= h($form['subnet_mask']) ?>"></div>
            <div class="col"><label>Gateway</label><input type="text" name="gateway" id="gateway" value="<?= h($form['gateway']) ?>"></div>
        </div>

        <div class="section-header">Items Selection</div>
        <table id="itemTable">
            <thead>
                <tr>
                    <th style="width:50px;">SL</th>
                    <th>Item Description</th>
                    <th style="width:100px;">Qty</th>
                    <th class="install-col">Serial No / Brand</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($selectedItems)): ?>
                <tr class="item-row">
                    <td>1</td>
                    <td><select name="item_id[]"><option value="">--Select Item--</option><?php foreach($itemsMaster as $im): ?><option value="<?= $im['id'] ?>"><?= h($im['item_name']) ?></option><?php endforeach; ?></select></td>
                    <td><input type="number" name="qty[]" value="1"></td>
                    <td class="install-col"><input type="text" name="install_serial[]"></td>
                    <td>-</td>
                </tr>
                <?php else: $sl=1; foreach($selectedItems as $s): ?>
                <tr class="item-row">
                    <td><?=$sl++?></td>
                    <td><select name="item_id[]"><?php foreach($itemsMaster as $im): ?><option value="<?= $im['id'] ?>" <?= $s['item_id']==$im['id']?'selected':''?>><?= h($im['item_name']) ?></option><?php endforeach; ?></select></td>
                    <td><input type="number" name="qty[]" value="<?= $s['qty'] ?>"></td>
                    <td class="install-col"><input type="text" name="install_serial[]"></td>
                    <td><button type="button" class="btn" style="background:red;" onclick="this.closest('tr').remove()">X</button></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <button type="button" class="btn btn-success" style="margin-top:10px;" onclick="addR()">+ Add Row</button>

        <!-- এই সেকশনটি এখন Waiting_for_Installation স্ট্যাটাসেও দেখা যাবে -->
        <div id="deliveryDetails" class="conditional-section">
            <div class="section-header" style="margin-top:20px;">Work Order & Vendor Details</div>
            <div class="row">
                <div class="col"><label>Selected Vendor</label>
                    <select name="selected_vendor_id">
                        <option value="">--Select--</option>
                        <?php foreach($vendors as $v): ?>
                            <option value="<?=$v['id']?>" <?=($form['selected_vendor_id']==$v['id']?'selected':'')?>><?=$v['vendor_name']?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col"><label>Work Order No</label><input type="text" name="work_order_no" value="<?=h($form['work_order_no'])?>"></div>
                <div class="col"><label>Work Order Date</label><input type="date" name="work_order_date" value="<?=h($form['work_order_date'])?>"></div>
            </div>
        </div>

        <div class="section-header" style="margin-top:20px;">Remarks & History</div>
        <div class="row">
            <div class="col"><label>Notes/Cause</label><textarea name="notes" rows="2"><?= h($form['notes']) ?></textarea></div>
            <div class="col"><label>Add New Remark (Update)</label><textarea name="new_history_remark" rows="2"></textarea></div>
        </div>

        <?php if($isEdit && !empty($history)): ?>
            <div style="max-height:150px; overflow-y:auto; background:#f9f9f9; padding:10px; border:1px solid #ddd; font-size:12px;">
                <?php foreach($history as $h): ?>
                    <div style="border-bottom:1px solid #eee; margin-bottom:5px;"><strong><?=h($h['username'])?></strong> (<?=date('d-M-y H:i', strtotime($h['created_at']))?>): <?=nl2br(h($h['remark']))?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div style="margin-top:20px; text-align:right;">
            <button type="submit" class="btn btn-blue">Save Requisition</button>
        </div>
    </form>
</div>

<script>
function addR() {
    const t = document.getElementById('itemTable').querySelector('tbody');
    const r = t.insertRow(); r.innerHTML = t.rows[0].innerHTML; r.cells[0].innerText = t.rows.length;
    r.querySelectorAll('input').forEach(i => i.value = (i.name==='qty[]'?'1':''));
    r.lastElementChild.innerHTML = '<button type="button" class="btn" style="background:red;" onclick="this.closest(\'tr\').remove()">X</button>';
}

function toggleSections() {
    const s = document.getElementById('status').value;
    
    // সংশোধিত কন্ডিশন: 'Waiting_for_Installation' অন্তর্ভুক্ত করা হয়েছে
    const showWO = [
        'Waiting_for_Work_Order', 
        'Waiting_for_Delivery', 
        'Waiting_for_QC', 
        'QC_Passed', 
        'Waiting_for_Installation', 
        'Installed'
    ].includes(s);
    
    document.getElementById('deliveryDetails').style.display = showWO ? 'block' : 'none';
    
    // ইনস্টলেশন কলাম শুধুমাত্র 'Installed' স্ট্যাটাসে দেখাবে
    document.querySelectorAll('.install-col').forEach(el => el.style.display = (s === 'Installed' ? 'table-cell' : 'none'));
}

document.getElementById('status').addEventListener('change', toggleSections);
toggleSections();

// AJAX AUTO FETCH FROM cctv_list TABLE
document.getElementById('atm_id').addEventListener('blur', function() {
    if (document.getElementById('is_new').checked) return;

    const atmId = this.value.trim();
    if (!atmId) return;

    fetch('cctv_list.php?ajax=fetch_atm&source=cctv_list&atm_id=' + encodeURIComponent(atmId) + '&t=' + Date.now())
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('booth_name').value = data.atm_name || data.booth_address || '';
                document.getElementById('branch_name').value = data.branch_name || '';
                document.getElementById('zone_name').value = data.zone_name || '';
                document.getElementById('monitoring_ip').value = data.m_ip || data.monitoring_ip || '';
                document.getElementById('internal_ip').value = data.ip_address || data.internal_ip || '';
                document.getElementById('subnet_mask').value = data.subnet || data.subnet_mask || '';
                document.getElementById('gateway').value = data.gateway || '';
            } else {
                alert(data.message || 'ATM ID not found in CCTV List.');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Failed to fetch CCTV list data.');
        });
});
</script>
</body>
</html>