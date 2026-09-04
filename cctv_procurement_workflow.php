<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Dhaka');

require_once __DIR__ . '/init.php';
Auth::requirePermission('manage_atm_master'); // অথবা আপনার CCTV পারমিশন

$conn = Database::getInstance()->getConnection();
mysqli_set_charset($conn, "utf8");

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$msg = '';
$error = '';

// Form Submit / Update Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $tender_notice_no = trim($_POST['tender_notice_no']);
    $notice_date = trim($_POST['notice_date']);
    $vendor_name = trim($_POST['vendor_name']);
    $quotation_status = trim($_POST['quotation_status']);
    $process_status = trim($_POST['process_status']);
    $work_order_no = trim($_POST['work_order_no']);
    $work_order_date = trim($_POST['work_order_date']);
    $delivery_date = trim($_POST['delivery_date']);
    $qc_status = trim($_POST['qc_status']);
    $atm_id = trim($_POST['atm_id']);
    $branch_name = trim($_POST['branch_name']);
    $bill_no = trim($_POST['bill_no']);
    $bill_amount = (float)$_POST['bill_amount'];
    $fad_forward_date = trim($_POST['fad_forward_date']);
    $remarks = trim($_POST['remarks']);

    if ($tender_notice_no === '') {
        $error = "Tender Notice No is required!";
    } else {
        if ($id > 0) {
            // Update
            $stmt = $conn->prepare("UPDATE cctv_hard_disk_procurement SET tender_notice_no=?, notice_date=?, vendor_name=?, quotation_status=?, process_status=?, work_order_no=?, work_order_date=?, delivery_date=?, qc_status=?, atm_id=?, branch_name=?, bill_no=?, bill_amount=?, fad_forward_date=?, remarks=? WHERE id=?");
            $stmt->bind_param("sssssssssssssssi", $tender_notice_no, $notice_date, $vendor_name, $quotation_status, $process_status, $work_order_no, $work_order_date, $delivery_date, $qc_status, $atm_id, $branch_name, $bill_no, $bill_amount, $fad_forward_date, $remarks, $id);
        } else {
            // Insert (Notice Published First)
            $stmt = $conn->prepare("INSERT INTO cctv_hard_disk_procurement (tender_notice_no, notice_date, vendor_name, quotation_status, process_status, work_order_no, work_order_date, delivery_date, qc_status, atm_id, branch_name, bill_no, bill_amount, fad_forward_date, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssssssssss", $tender_notice_no, $notice_date, $vendor_name, $quotation_status, $process_status, $work_order_no, $work_order_date, $delivery_date, $qc_status, $atm_id, $branch_name, $bill_no, $bill_amount, $fad_forward_date, $remarks);
        }

        if ($stmt->execute()) {
            header("Location: cctv_procurement_workflow.php?msg=success");
            exit;
        } else {
            $error = "Database Error: " . $conn->error;
        }
        $stmt->close();
    }
}

// Edit fetch
$editData = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $res = $conn->query("SELECT * FROM cctv_hard_disk_procurement WHERE id = $editId");
    if ($res) $editData = $res->fetch_assoc();
}

// Delete
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $conn->query("DELETE FROM cctv_hard_disk_procurement WHERE id = $delId");
    header("Location: cctv_procurement_workflow.php?msg=deleted");
    exit;
}

// List fetch
$records = $conn->query("SELECT * FROM cctv_hard_disk_procurement ORDER BY id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CCTV Hard Disk Procurement Workflow</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container-fluid py-4">
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">CCTV Hard Disk Procurement & Installation Workflow</h5>
        </div>
        <div class="card-body">
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
                <div class="alert alert-success">Process updated successfully!</div>
            <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
                <div class="alert alert-success">Record deleted successfully!</div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?=h($error)?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="id" value="<?=h($editData['id'] ?? 0)?>">
                
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label><b>1. Tender Notice No</b></label>
                        <input type="text" name="tender_notice_no" class="form-control" value="<?=h($editData['tender_notice_no'] ?? '')?>" placeholder="e.g. Notice-01/2026" required>
                    </div>
                    <div class="col-md-3 form-group">
                        <label><b>Notice Publish Date</b></label>
                        <input type="date" name="notice_date" class="form-control" value="<?=h($editData['notice_date'] ?? '')?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label><b>2. Quotation Status</b></label>
                        <select name="quotation_status" class="form-control">
                            <?php 
                            $qStatuses = ['Awaiting Quotation', 'Single Quotation (Invalid)', 'Multiple Quotations Received', 'No Quotation Received (Failed)'];
                            $currQ = $editData['quotation_status'] ?? 'Awaiting Quotation';
                            foreach($qStatuses as $qs) {
                                $sel = ($currQ === $qs) ? 'selected' : '';
                                echo "<option value='$qs' $sel>$qs</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label><b>Vendor Name (If Submitted)</b></label>
                        <input type="text" name="vendor_name" class="form-control" value="<?=h($editData['vendor_name'] ?? '')?>" placeholder="Enlisted Vendor Name">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 form-group">
                        <label><b>3. Overall Process Status</b></label>
                        <select name="process_status" class="form-control">
                            <?php 
                            $statuses = [
                                'Notice Published', 
                                'Tender Cancelled / Invalid (Re-Notice Needed)', 
                                'Quotations Received', 
                                'Technical Evaluation Done', 
                                'Work Order Issued', 
                                'Hard Disk Delivered', 
                                'QC Passed', 
                                'Installed at Booth', 
                                'Bill Submitted', 
                                'Forwarded to FAD'
                            ];
                            $currStatus = $editData['process_status'] ?? 'Notice Published';
                            foreach($statuses as $st) {
                                $sel = ($currStatus === $st) ? 'selected' : '';
                                echo "<option value='$st' $sel>$st</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label><b>Work Order No</b></label>
                        <input type="text" name="work_order_no" class="form-control" value="<?=h($editData['work_order_no'] ?? '')?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label><b>Work Order Date</b></label>
                        <input type="date" name="work_order_date" class="form-control" value="<?=h($editData['work_order_date'] ?? '')?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label><b>Delivery Date</b></label>
                        <input type="date" name="delivery_date" class="form-control" value="<?=h($editData['delivery_date'] ?? '')?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 form-group">
                        <label><b>QC Status (Committee)</b></label>
                        <select name="qc_status" class="form-control">
                            <?php foreach(['Pending', 'Passed', 'Failed'] as $qc): ?>
                                <option value="<?=$qc?>" <?=($editData['qc_status'] ?? '') === $qc ? 'selected' : ''?>><?=$qc?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label><b>Installed ATM ID</b></label>
                        <input type="text" name="atm_id" class="form-control" value="<?=h($editData['atm_id'] ?? '')?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label><b>Branch Name</b></label>
                        <input type="text" name="branch_name" class="form-control" value="<?=h($editData['branch_name'] ?? '')?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label><b>Bill No & Amount</b></label>
                        <div class="input-group">
                            <input type="text" name="bill_no" class="form-control" placeholder="Bill No" value="<?=h($editData['bill_no'] ?? '')?>">
                            <input type="number" step="0.01" name="bill_amount" class="form-control" placeholder="Amount" value="<?=h($editData['bill_amount'] ?? '')?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 form-group">
                        <label><b>FAD Forward Date</b></label>
                        <input type="date" name="fad_forward_date" class="form-control" value="<?=h($editData['fad_forward_date'] ?? '')?>">
                    </div>
                    <div class="col-md-9 form-group">
                        <label><b>Remarks / Notes (e.g. Why tender became invalid / Re-tender reason)</b></label>
                        <input type="text" name="remarks" class="form-control" value="<?=h($editData['remarks'] ?? '')?>" placeholder="Write notes here...">
                    </div>
                </div>

                <button type="submit" class="btn btn-success"><?=$editData ? 'Update Workflow Status' : 'Publish Notice & Save'?></button>
                <?php if($editData): ?>
                    <a href="cctv_procurement_workflow.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Workflow List Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Procurement & Installation Tracking List</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tender / Notice No</th>
                            <th>Quotation Status</th>
                            <th>Current Status</th>
                            <th>Work Order</th>
                            <th>QC Status</th>
                            <th>Installation (ATM/Branch)</th>
                            <th>Bill & FAD</th>
                            <th>Remarks</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($records && $records->num_rows > 0): ?>
                            <?php while($row = $records->fetch_assoc()): ?>
                            <tr>
                                <td><?=$row['id']?></td>
                                <td><b><?=h($row['tender_notice_no'])?></b><br><small><?=h($row['notice_date'])?></small></td>
                                <td><span class="badge badge-secondary"><?=h($row['quotation_status'])?></span><br><small><?=h($row['vendor_name'])?></small></td>
                                <td>
                                    <?php 
                                    $stCls = (strpos($row['process_status'], 'Cancelled') !== false || strpos($row['process_status'], 'Invalid') !== false) ? 'badge-danger' : 'badge-info';
                                    echo "<span class='badge $stCls'>".h($row['process_status'])."</span>";
                                    ?>
                                </td>
                                <td><?=h($row['work_order_no'] ?? '-')?><br><small><?=h($row['work_order_date'])?></small></td>
                                <td>
                                    <?php 
                                    $qcCls = $row['qc_status'] == 'Passed' ? 'badge-success' : ($row['qc_status'] == 'Failed' ? 'badge-danger' : 'badge-warning');
                                    echo "<span class='badge $qcCls'>".h($row['qc_status'])."</span>";
                                    ?>
                                </td>
                                <td><?=h($row['atm_id'] ?: '-')?><br><small><?=h($row['branch_name'])?></small></td>
                                <td>Bill: <?=h($row['bill_no'] ?: '-')?><br><small>FAD: <?=h($row['fad_forward_date'] ?: '-')?></small></td>
                                <td><small><?=h($row['remarks'])?></small></td>
                                <td>
                                    <a href="cctv_procurement_workflow.php?edit=<?=$row['id']?>" class="btn btn-sm btn-primary">Edit</a>
                                    <a href="cctv_procurement_workflow.php?delete=<?=$row['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this record?')">Delete</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="10" class="text-center">No records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>