<?php
date_default_timezone_set('Asia/Dhaka');
include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

$message = '';
$error = '';

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/* =========================
   DELETE
========================= */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];

    $q = $conn->prepare("DELETE FROM cctv_tender_bids WHERE id = ?");
    $q->bind_param("i", $deleteId);
    if ($q->execute()) {
        $message = "Tender bid deleted successfully.";
    } else {
        $error = "Delete failed.";
    }
    $q->close();
}

/* =========================
   SAVE / UPDATE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $edit_id         = (int)($_POST['edit_id'] ?? 0);
    $requisition_id  = (int)($_POST['requisition_id'] ?? 0);
    $vendor_id       = !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null;
    $vendor_name     = trim($_POST['vendor_name'] ?? '');
    $quoted_amount   = (float)($_POST['quoted_amount'] ?? 0);
    $delivery_days   = !empty($_POST['delivery_days']) ? (int)$_POST['delivery_days'] : null;
    $warranty_months = !empty($_POST['warranty_months']) ? (int)$_POST['warranty_months'] : null;
    $remarks         = trim($_POST['remarks'] ?? '');
    $bid_date        = $_POST['bid_date'] ?? null;
    $is_selected     = isset($_POST['is_selected']) ? 1 : 0;

    if ($requisition_id <= 0 || $vendor_name === '' || $quoted_amount <= 0) {
        $error = "Requisition, vendor and quoted amount are required.";
    } else {
        if ($is_selected === 1) {
            $reset = $conn->prepare("UPDATE cctv_tender_bids SET is_selected = 0 WHERE requisition_id = ?");
            $reset->bind_param("i", $requisition_id);
            $reset->execute();
            $reset->close();
        }

        if ($edit_id > 0) {
            $sql = "UPDATE cctv_tender_bids
                    SET requisition_id=?, vendor_id=?, vendor_name=?, quoted_amount=?, delivery_days=?, warranty_months=?, remarks=?, bid_date=?, is_selected=?
                    WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "iisdisssii",
                $requisition_id,
                $vendor_id,
                $vendor_name,
                $quoted_amount,
                $delivery_days,
                $warranty_months,
                $remarks,
                $bid_date,
                $is_selected,
                $edit_id
            );
            if ($stmt->execute()) {
                $message = "Tender bid updated successfully.";
            } else {
                $error = "Update failed: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $sql = "INSERT INTO cctv_tender_bids
                    (requisition_id, vendor_id, vendor_name, quoted_amount, delivery_days, warranty_months, remarks, bid_date, is_selected)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "iisdisssi",
                $requisition_id,
                $vendor_id,
                $vendor_name,
                $quoted_amount,
                $delivery_days,
                $warranty_months,
                $remarks,
                $bid_date,
                $is_selected
            );
            if ($stmt->execute()) {
                $message = "Tender bid saved successfully.";
            } else {
                $error = "Save failed: " . $stmt->error;
            }
            $stmt->close();
        }

        if ($is_selected === 1) {
            $up = $conn->prepare("UPDATE cctv_requisition 
                                  SET requisition_status='Tender Completed',
                                      selected_vendor_id=?,
                                      selected_vendor_name=?
                                  WHERE id=?");
            $up->bind_param("isi", $vendor_id, $vendor_name, $requisition_id);
            $up->execute();
            $up->close();
        }
    }
}

/* =========================
   EDIT DATA
========================= */
$editData = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM cctv_tender_bids WHERE id=?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

/* =========================
   REQUISITION LIST
========================= */
$requisitions = [];
$rq = $conn->query("SELECT id, atm_id, branch_name, booth_name, requisition_date 
                    FROM cctv_requisition
                    ORDER BY id DESC");
while ($row = $rq->fetch_assoc()) {
    $requisitions[] = $row;
}

/* =========================
   VENDOR LIST
========================= */
$vendors = [];
$vq = $conn->query("SELECT id, vendor_name FROM vendor_master ORDER BY vendor_name ASC");
if ($vq) {
    while ($row = $vq->fetch_assoc()) {
        $vendors[] = $row;
    }
}

/* =========================
   BID LIST
========================= */
$list = [];
$sqlList = "SELECT b.*, r.atm_id, r.branch_name, r.booth_name
            FROM cctv_tender_bids b
            LEFT JOIN cctv_requisition r ON r.id = b.requisition_id
            ORDER BY b.id DESC";
$resList = $conn->query($sqlList);
while ($row = $resList->fetch_assoc()) {
    $list[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>CCTV Tender Bids</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f6f8fb; }
        .container { max-width: 1300px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; }
        h2 { margin-top: 0; }
        .msg { padding: 10px; margin-bottom: 12px; border-radius: 5px; }
        .success { background: #e7f7e7; color: #1f7a1f; }
        .error { background: #fdeaea; color: #b30000; }
        .row { display: flex; gap: 15px; margin-bottom: 12px; flex-wrap: wrap; }
        .col { flex: 1; min-width: 240px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 8px; box-sizing: border-box; }
        textarea { min-height: 70px; }
        button { padding: 10px 18px; background: #0d6efd; color: white; border: none; cursor: pointer; border-radius: 4px; }
        button:hover { background: #0b5ed7; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        th, td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
        th { background: #f0f3f7; }
        a { text-decoration: none; }
        .actions a { margin-right: 8px; }
    </style>
    <script>
        function fillVendorName() {
            var sel = document.getElementById('vendor_id');
            var vendorName = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].getAttribute('data-name') : '';
            document.getElementById('vendor_name').value = vendorName || '';
        }
    </script>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <h2>CCTV Tender Bids</h2>

    <?php if ($message): ?><div class="msg success"><?= h($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg error"><?= h($error) ?></div><?php endif; ?>

    <form method="post">
        <input type="hidden" name="edit_id" value="<?= h($editData['id'] ?? '') ?>">

        <div class="row">
            <div class="col">
                <label>Requisition</label>
                <select name="requisition_id" required>
                    <option value="">Select Requisition</option>
                    <?php foreach ($requisitions as $r): ?>
                        <option value="<?= (int)$r['id'] ?>" <?= (($editData['requisition_id'] ?? '') == $r['id']) ? 'selected' : '' ?>>
                            <?= h($r['atm_id']) ?> | <?= h($r['branch_name']) ?> | <?= h($r['booth_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col">
                <label>Vendor</label>
                <select name="vendor_id" id="vendor_id" onchange="fillVendorName()">
                    <option value="">Select Vendor</option>
                    <?php foreach ($vendors as $v): ?>
                        <option value="<?= (int)$v['id'] ?>"
                                data-name="<?= h($v['vendor_name']) ?>"
                                <?= (($editData['vendor_id'] ?? '') == $v['id']) ? 'selected' : '' ?>>
                            <?= h($v['vendor_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col">
                <label>Vendor Name</label>
                <input type="text" name="vendor_name" id="vendor_name" value="<?= h($editData['vendor_name'] ?? '') ?>" required>
            </div>
        </div>

        <div class="row">
            <div class="col">
                <label>Quoted Amount</label>
                <input type="number" step="0.01" name="quoted_amount" value="<?= h($editData['quoted_amount'] ?? '') ?>" required>
            </div>
            <div class="col">
                <label>Delivery Days</label>
                <input type="number" name="delivery_days" value="<?= h($editData['delivery_days'] ?? '') ?>">
            </div>
            <div class="col">
                <label>Warranty (Months)</label>
                <input type="number" name="warranty_months" value="<?= h($editData['warranty_months'] ?? '') ?>">
            </div>
            <div class="col">
                <label>Bid Date</label>
                <input type="date" name="bid_date" value="<?= h($editData['bid_date'] ?? date('Y-m-d')) ?>">
            </div>
        </div>

        <div class="row">
            <div class="col">
                <label>Remarks</label>
                <textarea name="remarks"><?= h($editData['remarks'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="row">
            <div class="col">
                <label>
                    <input type="checkbox" name="is_selected" value="1" <?= !empty($editData['is_selected']) ? 'checked' : '' ?>>
                    Mark as Selected Vendor
                </label>
            </div>
        </div>

        <button type="submit"><?= $editData ? 'Update Bid' : 'Save Bid' ?></button>
    </form>

    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>ATM ID</th>
            <th>Branch</th>
            <th>Booth</th>
            <th>Vendor</th>
            <th>Amount</th>
            <th>Delivery</th>
            <th>Warranty</th>
            <th>Bid Date</th>
            <th>Selected</th>
            <th>Remarks</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($list as $row): ?>
            <tr>
                <td><?= (int)$row['id'] ?></td>
                <td><?= h($row['atm_id']) ?></td>
                <td><?= h($row['branch_name']) ?></td>
                <td><?= h($row['booth_name']) ?></td>
                <td><?= h($row['vendor_name']) ?></td>
                <td><?= h($row['quoted_amount']) ?></td>
                <td><?= h($row['delivery_days']) ?></td>
                <td><?= h($row['warranty_months']) ?></td>
                <td><?= h($row['bid_date']) ?></td>
                <td><?= !empty($row['is_selected']) ? 'Yes' : 'No' ?></td>
                <td><?= h($row['remarks']) ?></td>
                <td class="actions">
                    <a href="?edit=<?= (int)$row['id'] ?>">Edit</a>
                    <a href="?delete=<?= (int)$row['id'] ?>" onclick="return confirm('Delete this bid?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?>
            <tr><td colspan="12" style="text-align:center;">No records found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>