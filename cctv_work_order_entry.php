<?php
date_default_timezone_set('Asia/Dhaka');
include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM cctv_work_orders WHERE id = ?");
    $stmt->bind_param("i", $deleteId);
    if ($stmt->execute()) {
        $message = "Work order deleted successfully.";
    } else {
        $error = "Delete failed.";
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $edit_id           = (int)($_POST['edit_id'] ?? 0);
    $requisition_id    = (int)($_POST['requisition_id'] ?? 0);
    $vendor_id         = !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null;
    $vendor_name       = trim($_POST['vendor_name'] ?? '');
    $work_order_no     = trim($_POST['work_order_no'] ?? '');
    $work_order_date   = trim($_POST['work_order_date'] ?? '');
    $delivery_deadline = trim($_POST['delivery_deadline'] ?? '');
    $work_order_amount = (float)($_POST['work_order_amount'] ?? 0);
    $remarks           = trim($_POST['remarks'] ?? '');

    if ($requisition_id <= 0 || $vendor_name === '' || $work_order_no === '') {
        $error = "Requisition, vendor and work order no are required.";
    } else {
        if ($edit_id > 0) {
            $stmt = $conn->prepare("
                UPDATE cctv_work_orders
                SET requisition_id=?, vendor_id=?, vendor_name=?, work_order_no=?, work_order_date=?, delivery_deadline=?, work_order_amount=?, remarks=?
                WHERE id=?
            ");
            $stmt->bind_param(
                "iissssdsi",
                $requisition_id,
                $vendor_id,
                $vendor_name,
                $work_order_no,
                $work_order_date,
                $delivery_deadline,
                $work_order_amount,
                $remarks,
                $edit_id
            );
            if ($stmt->execute()) {
                $message = "Work order updated successfully.";
            } else {
                $error = "Update failed: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("
                INSERT INTO cctv_work_orders
                (requisition_id, vendor_id, vendor_name, work_order_no, work_order_date, delivery_deadline, work_order_amount, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "iissssds",
                $requisition_id,
                $vendor_id,
                $vendor_name,
                $work_order_no,
                $work_order_date,
                $delivery_deadline,
                $work_order_amount,
                $remarks
            );
            if ($stmt->execute()) {
                $message = "Work order saved successfully.";
            } else {
                $error = "Save failed: " . $stmt->error;
            }
            $stmt->close();
        }

        $stmt = $conn->prepare("
            UPDATE cctv_requisition
            SET work_order_date = ?, requisition_status = 'Work Order Issued'
            WHERE id = ?
        ");
        $stmt->bind_param("si", $work_order_date, $requisition_id);
        $stmt->execute();
        $stmt->close();
    }
}

$editData = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM cctv_work_orders WHERE id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$prefill_requisition_id = isset($_GET['requisition_id']) ? (int)$_GET['requisition_id'] : 0;

$requisitions = [];
$res = $conn->query("
    SELECT id, atm_id, branch_name, booth_name, selected_vendor_id, selected_vendor_name
    FROM cctv_requisition
    WHERE selected_vendor_name IS NOT NULL AND selected_vendor_name <> ''
    ORDER BY id DESC
");
while ($row = $res->fetch_assoc()) {
    $requisitions[] = $row;
}

$list = [];
$res = $conn->query("
    SELECT w.*, r.atm_id, r.branch_name, r.booth_name
    FROM cctv_work_orders w
    LEFT JOIN cctv_requisition r ON r.id = w.requisition_id
    ORDER BY w.id DESC
");
while ($row = $res->fetch_assoc()) {
    $list[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CCTV Work Order Entry</title>
    <style>
        body { font-family: Arial; background:#f6f8fb; margin:20px; }
        .container { max-width:1300px; margin:auto; background:#fff; padding:20px; border-radius:8px; }
        .msg { padding:10px; border-radius:5px; margin-bottom:12px; }
        .success { background:#e7f7e7; color:#1f7a1f; }
        .error { background:#fdeaea; color:#b30000; }
        .row { display:flex; gap:15px; flex-wrap:wrap; margin-bottom:12px; }
        .col { flex:1; min-width:220px; }
        label { display:block; margin-bottom:5px; font-weight:bold; }
        input, select, textarea { width:100%; padding:8px; box-sizing:border-box; }
        textarea { min-height:70px; }
        button { padding:10px 18px; background:#0d6efd; color:#fff; border:none; border-radius:4px; cursor:pointer; }
        table { width:100%; border-collapse:collapse; margin-top:20px; font-size:14px; }
        th, td { border:1px solid #ddd; padding:8px; vertical-align:top; }
        th { background:#f0f3f7; }
    </style>
    <script>
        function fillReqInfo() {
            var sel = document.getElementById('requisition_id');
            if (!sel || sel.selectedIndex < 0) return;
            var opt = sel.options[sel.selectedIndex];
            document.getElementById('vendor_id').value = opt.getAttribute('data-vendor-id') || '';
            document.getElementById('vendor_name').value = opt.getAttribute('data-vendor-name') || '';
        }
    </script>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <h2>CCTV Work Order Entry</h2>

    <?php if ($message): ?><div class="msg success"><?= h($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg error"><?= h($error) ?></div><?php endif; ?>

    <form method="post">
        <input type="hidden" name="edit_id" value="<?= h($editData['id'] ?? '') ?>">

        <div class="row">
            <div class="col">
                <label>Requisition</label>
                <select name="requisition_id" id="requisition_id" onchange="fillReqInfo()" required>
                    <option value="">Select Requisition</option>
                    <?php foreach ($requisitions as $r): ?>
                        <option value="<?= (int)$r['id'] ?>"
                                data-vendor-id="<?= h($r['selected_vendor_id']) ?>"
                                data-vendor-name="<?= h($r['selected_vendor_name']) ?>"
                                <?= (($editData['requisition_id'] ?? $prefill_requisition_id) == $r['id']) ? 'selected' : '' ?>>
                            <?= h($r['atm_id']) ?> | <?= h($r['branch_name']) ?> | <?= h($r['booth_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col">
                <label>Vendor ID</label>
                <input type="text" name="vendor_id" id="vendor_id" value="<?= h($editData['vendor_id'] ?? '') ?>" readonly>
            </div>
            <div class="col">
                <label>Vendor Name</label>
                <input type="text" name="vendor_name" id="vendor_name" value="<?= h($editData['vendor_name'] ?? '') ?>" readonly>
            </div>
            <div class="col">
                <label>Work Order No</label>
                <input type="text" name="work_order_no" value="<?= h($editData['work_order_no'] ?? '') ?>" required>
            </div>
        </div>

        <div class="row">
            <div class="col">
                <label>Work Order Date</label>
                <input type="date" name="work_order_date" value="<?= h($editData['work_order_date'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="col">
                <label>Delivery Deadline</label>
                <input type="date" name="delivery_deadline" value="<?= h($editData['delivery_deadline'] ?? '') ?>">
            </div>
            <div class="col">
                <label>Work Order Amount</label>
                <input type="number" step="0.01" name="work_order_amount" value="<?= h($editData['work_order_amount'] ?? '') ?>">
            </div>
        </div>

        <div class="row">
            <div class="col">
                <label>Remarks</label>
                <textarea name="remarks"><?= h($editData['remarks'] ?? '') ?></textarea>
            </div>
        </div>

        <button type="submit"><?= $editData ? 'Update Work Order' : 'Save Work Order' ?></button>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>ATM ID</th>
                <th>Branch</th>
                <th>Booth</th>
                <th>Vendor</th>
                <th>WO No</th>
                <th>WO Date</th>
                <th>Deadline</th>
                <th>Amount</th>
                <th>Remarks</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($list): ?>
            <?php foreach ($list as $row): ?>
                <tr>
                    <td><?= (int)$row['id'] ?></td>
                    <td><?= h($row['atm_id']) ?></td>
                    <td><?= h($row['branch_name']) ?></td>
                    <td><?= h($row['booth_name']) ?></td>
                    <td><?= h($row['vendor_name']) ?></td>
                    <td><?= h($row['work_order_no']) ?></td>
                    <td><?= h($row['work_order_date']) ?></td>
                    <td><?= h($row['delivery_deadline']) ?></td>
                    <td><?= h($row['work_order_amount']) ?></td>
                    <td><?= h($row['remarks']) ?></td>
                    <td>
                        <a href="?edit=<?= (int)$row['id'] ?>">Edit</a> |
                        <a href="?delete=<?= (int)$row['id'] ?>" onclick="return confirm('Delete this work order?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="11" style="text-align:center;">No records found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<script>fillReqInfo();</script>
</body>
</html>