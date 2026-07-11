<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';
include 'includes/cctv_helpers.php';

Auth::requirePermission('cctv_qc_entry');

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

/* =========================
   DELETE
========================= */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];

    // আগে requisition_id বের করি, যাতে চাইলে পরে status re-evaluate করা যায়
    $stmtFind = $conn->prepare("SELECT requisition_id FROM cctv_qc_entries WHERE id = ? LIMIT 1");
    $stmtFind->bind_param("i", $deleteId);
    $stmtFind->execute();
    $oldRow = $stmtFind->get_result()->fetch_assoc();
    $stmtFind->close();

    $q = $conn->prepare("DELETE FROM cctv_qc_entries WHERE id = ?");
    $q->bind_param("i", $deleteId);

    if ($q->execute()) {
        $message = "QC entry deleted successfully.";

        // delete করার পর optional fallback status
        if (!empty($oldRow['requisition_id'])) {
            $reqId = (int)$oldRow['requisition_id'];

            $checkStmt = $conn->prepare("SELECT COUNT(*) AS total FROM cctv_qc_entries WHERE requisition_id = ?");
            $checkStmt->bind_param("i", $reqId);
            $checkStmt->execute();
            $countRow = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if ((int)($countRow['total'] ?? 0) === 0) {
                $up = $conn->prepare("
                    UPDATE cctv_set_requisition
                    SET qc_date = NULL,
                        status = 'Product_Delivered'
                    WHERE id = ?
                ");
                $up->bind_param("i", $reqId);
                $up->execute();
                $up->close();
            }
        }
    } else {
        $error = "Delete failed: " . $q->error;
    }
    $q->close();
}

/* =========================
   SAVE / UPDATE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $edit_id        = (int)($_POST['edit_id'] ?? 0);
    $requisition_id = (int)($_POST['requisition_id'] ?? 0);
    $vendor_id      = !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null;
    $vendor_name    = trim($_POST['vendor_name'] ?? '');
    $qc_date        = trim($_POST['qc_date'] ?? '');
    $qc_status      = trim($_POST['qc_status'] ?? 'Pending');
    $checked_by     = trim($_POST['checked_by'] ?? '');
    $dvr_ok         = isset($_POST['dvr_ok']) ? 1 : 0;
    $camera_ok      = isset($_POST['camera_ok']) ? 1 : 0;
    $hdd_ok         = isset($_POST['hdd_ok']) ? 1 : 0;
    $smps_ok        = isset($_POST['smps_ok']) ? 1 : 0;
    $adapter_ok     = isset($_POST['adapter_ok']) ? 1 : 0;
    $accessories_ok = isset($_POST['accessories_ok']) ? 1 : 0;
    $remarks        = trim($_POST['remarks'] ?? '');

    if ($requisition_id <= 0) {
        $error = "Requisition is required.";
    } elseif ($qc_date === '') {
        $error = "QC date is required.";
    } elseif ($qc_status === '') {
        $error = "QC status is required.";
    } else {

        // allowed status
        $allowedStatuses = ['Pending', 'Pass', 'Fail', 'Partial'];
        if (!in_array($qc_status, $allowedStatuses, true)) {
            $qc_status = 'Pending';
        }

        if ($edit_id > 0) {
            $sql = "UPDATE cctv_qc_entries
                    SET requisition_id = ?,
                        vendor_id = ?,
                        vendor_name = ?,
                        qc_date = ?,
                        qc_status = ?,
                        checked_by = ?,
                        dvr_ok = ?,
                        camera_ok = ?,
                        hdd_ok = ?,
                        smps_ok = ?,
                        adapter_ok = ?,
                        accessories_ok = ?,
                        remarks = ?
                    WHERE id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "iissssiiiiiisi",
                $requisition_id,
                $vendor_id,
                $vendor_name,
                $qc_date,
                $qc_status,
                $checked_by,
                $dvr_ok,
                $camera_ok,
                $hdd_ok,
                $smps_ok,
                $adapter_ok,
                $accessories_ok,
                $remarks,
                $edit_id
            );

            if ($stmt->execute()) {
                $message = "QC entry updated successfully.";
            } else {
                $error = "Update failed: " . $stmt->error;
            }
            $stmt->close();

        } else {
            $sql = "INSERT INTO cctv_qc_entries
                    (
                        requisition_id,
                        vendor_id,
                        vendor_name,
                        qc_date,
                        qc_status,
                        checked_by,
                        dvr_ok,
                        camera_ok,
                        hdd_ok,
                        smps_ok,
                        adapter_ok,
                        accessories_ok,
                        remarks
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "iissssiiiiiss",
                $requisition_id,
                $vendor_id,
                $vendor_name,
                $qc_date,
                $qc_status,
                $checked_by,
                $dvr_ok,
                $camera_ok,
                $hdd_ok,
                $smps_ok,
                $adapter_ok,
                $accessories_ok,
                $remarks
            );

            if ($stmt->execute()) {
                $message = "QC entry saved successfully.";
            } else {
                $error = "Save failed: " . $stmt->error;
            }
            $stmt->close();
        }

        /* =========================
           Requisition Status Update
        ========================= */
        if ($error === '') {
            $newReqStatus = 'Product_Delivered';

            if ($qc_status === 'Pass') {
                $newReqStatus = 'QC_Passed';
            } elseif ($qc_status === 'Fail') {
                $newReqStatus = 'Product_Delivered';
            } elseif ($qc_status === 'Partial') {
                $newReqStatus = 'Product_Delivered';
            } elseif ($qc_status === 'Pending') {
                $newReqStatus = 'Product_Delivered';
            }

            $up = $conn->prepare("
                UPDATE cctv_set_requisition
                SET qc_date = ?,
                    status = ?
                WHERE id = ?
            ");
            $up->bind_param("ssi", $qc_date, $newReqStatus, $requisition_id);
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
    $stmt = $conn->prepare("SELECT * FROM cctv_qc_entries WHERE id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

/* =========================
   REQUISITION LIST (FIXED)
========================= */
$requisitions = [];

$sqlReq = "
    SELECT 
        r.id,
        l.atm_id,
        l.branch_name,
        l.booth_name,
        r.selected_vendor_id,
        v.vendor_name AS selected_vendor_name
    FROM cctv_set_requisition r
    INNER JOIN cctv_locations l ON l.id = r.cctv_location_id
    LEFT JOIN cctv_vendors v ON v.id = r.selected_vendor_id
    WHERE r.selected_vendor_id IS NOT NULL
    ORDER BY r.id DESC
";

$resReq = $conn->query($sqlReq);
if ($resReq) {
    while ($row = $resReq->fetch_assoc()) {
        $requisitions[] = $row;
    }
}

/* =========================
   QC LIST (FIXED)
========================= */
$list = [];

$sqlList = "
    SELECT 
        q.*,
        l.atm_id,
        l.branch_name,
        l.booth_name
    FROM cctv_qc_entries q
    LEFT JOIN cctv_set_requisition r ON r.id = q.requisition_id
    LEFT JOIN cctv_locations l ON l.id = r.cctv_location_id
    ORDER BY q.id DESC
";

$resList = $conn->query($sqlList);
if ($resList) {
    while ($row = $resList->fetch_assoc()) {
        $list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CCTV QC Entry</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f6f8fb; }
        .container { max-width: 1300px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; }
        .msg { padding: 10px; margin-bottom: 12px; border-radius: 5px; }
        .success { background: #e7f7e7; color: #1f7a1f; }
        .error { background: #fdeaea; color: #b30000; }
        .row { display: flex; gap: 15px; margin-bottom: 12px; flex-wrap: wrap; }
        .col { flex: 1; min-width: 240px; }
        label { display:block; margin-bottom:5px; font-weight:bold; }
        input, select, textarea { width:100%; padding:8px; box-sizing:border-box; }
        textarea { min-height:70px; }
        button { padding: 10px 18px; background:#198754; color:#fff; border:none; border-radius:4px; cursor:pointer; }
        table { width:100%; border-collapse: collapse; margin-top:20px; font-size:14px; }
        th, td { border:1px solid #ddd; padding:8px; vertical-align:top; }
        th { background:#f0f3f7; }
        .check-grid { display:flex; gap:15px; flex-wrap:wrap; }
        .check-grid .col { min-width:180px; }
    </style>
    <script>
        function fillVendorInfo() {
            var sel = document.getElementById('requisition_id');
            if (!sel || sel.selectedIndex < 0) return;
            var opt = sel.options[sel.selectedIndex];
            document.getElementById('vendor_id').value = opt.getAttribute('data-vendor-id') || '';
            document.getElementById('vendor_name').value = opt.getAttribute('data-vendor-name') || '';
        }

        window.addEventListener('load', function () {
            fillVendorInfo();
        });
    </script>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <h2>CCTV QC Entry</h2>

    <?php if ($message): ?><div class="msg success"><?= h($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg error"><?= h($error) ?></div><?php endif; ?>

    <form method="post">
        <input type="hidden" name="edit_id" value="<?= h($editData['id'] ?? '') ?>">

        <div class="row">
            <div class="col">
                <label>Requisition</label>
                <select name="requisition_id" id="requisition_id" onchange="fillVendorInfo()" required>
                    <option value="">Select Requisition</option>
                    <?php foreach ($requisitions as $r): ?>
                        <option value="<?= (int)$r['id'] ?>"
                                data-vendor-id="<?= h($r['selected_vendor_id']) ?>"
                                data-vendor-name="<?= h($r['selected_vendor_name']) ?>"
                                <?= (($editData['requisition_id'] ?? '') == $r['id']) ? 'selected' : '' ?>>
                            <?= h(($r['atm_id'] ?: 'N/A') . ' | ' . $r['branch_name'] . ' | ' . $r['booth_name']) ?>
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
                <label>QC Date</label>
                <input type="date" name="qc_date" value="<?= h($editData['qc_date'] ?? date('Y-m-d')) ?>" required>
            </div>
        </div>

        <div class="row">
            <div class="col">
                <label>QC Status</label>
                <select name="qc_status" required>
                    <?php
                    $statuses = ['Pending','Pass','Fail','Partial'];
                    $selectedStatus = $editData['qc_status'] ?? 'Pending';
                    foreach ($statuses as $s):
                    ?>
                    <option value="<?= h($s) ?>" <?= $selectedStatus === $s ? 'selected' : '' ?>><?= h($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col">
                <label>Checked By</label>
                <input type="text" name="checked_by" value="<?= h($editData['checked_by'] ?? '') ?>">
            </div>
        </div>

        <div class="row check-grid">
            <div class="col"><label><input type="checkbox" name="dvr_ok" <?= !empty($editData['dvr_ok']) ? 'checked' : '' ?>> DVR OK</label></div>
            <div class="col"><label><input type="checkbox" name="camera_ok" <?= !empty($editData['camera_ok']) ? 'checked' : '' ?>> Camera OK</label></div>
            <div class="col"><label><input type="checkbox" name="hdd_ok" <?= !empty($editData['hdd_ok']) ? 'checked' : '' ?>> HDD OK</label></div>
            <div class="col"><label><input type="checkbox" name="smps_ok" <?= !empty($editData['smps_ok']) ? 'checked' : '' ?>> SMPS OK</label></div>
            <div class="col"><label><input type="checkbox" name="adapter_ok" <?= !empty($editData['adapter_ok']) ? 'checked' : '' ?>> Adapter OK</label></div>
            <div class="col"><label><input type="checkbox" name="accessories_ok" <?= !empty($editData['accessories_ok']) ? 'checked' : '' ?>> Accessories OK</label></div>
        </div>

        <div class="row">
            <div class="col">
                <label>Remarks</label>
                <textarea name="remarks"><?= h($editData['remarks'] ?? '') ?></textarea>
            </div>
        </div>

        <button type="submit"><?= $editData ? 'Update QC' : 'Save QC' ?></button>
    </form>

    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>ATM ID</th>
            <th>Branch</th>
            <th>Booth</th>
            <th>Vendor</th>
            <th>QC Date</th>
            <th>Status</th>
            <th>Checked By</th>
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
                <td><?= h($row['qc_date']) ?></td>
                <td><?= h($row['qc_status']) ?></td>
                <td><?= h($row['checked_by']) ?></td>
                <td><?= h($row['remarks']) ?></td>
                <td>
                    <a href="?edit=<?= (int)$row['id'] ?>">Edit</a> |
                    <a href="?delete=<?= (int)$row['id'] ?>" onclick="return confirm('Delete this QC entry?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (!$list): ?>
            <tr><td colspan="10" style="text-align:center;">No records found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>