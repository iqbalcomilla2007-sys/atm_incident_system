<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('cctv_vendor_master');

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

/* =========================
   TOGGLE ACTIVE/INACTIVE
========================= */
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];

    $stmt = $conn->prepare("SELECT is_active FROM cctv_vendors WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $newStatus = ((int)$row['is_active'] === 1) ? 0 : 1;

        $stmt = $conn->prepare("UPDATE cctv_vendors SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $newStatus, $id);

        if ($stmt->execute()) {
            $message = "Vendor status updated successfully.";
        } else {
            $error = "Status update failed: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Vendor not found.";
    }
}

/* =========================
   EDIT LOAD
========================= */
$editData = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];

    $stmt = $conn->prepare("SELECT * FROM cctv_vendors WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$editData) {
        $error = "Vendor not found.";
    }
}

/* =========================
   SAVE / UPDATE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $edit_id        = (int)($_POST['edit_id'] ?? 0);
    $vendor_name    = trim($_POST['vendor_name'] ?? '');
    $vendor_type    = trim($_POST['vendor_type'] ?? 'BOTH');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $mobile         = trim($_POST['mobile'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $is_active      = isset($_POST['is_active']) ? 1 : 0;

    $allowedTypes = ['ENLISTED', 'PROCUREMENT', 'SERVICE', 'BOTH'];

    if ($vendor_name === '') {
        $error = "Vendor name is required.";
    } elseif (!in_array($vendor_type, $allowedTypes, true)) {
        $error = "Invalid vendor type.";
    } else {
        if ($edit_id > 0) {
            $stmt = $conn->prepare("
                UPDATE cctv_vendors
                SET vendor_name = ?,
                    vendor_type = ?,
                    contact_person = ?,
                    mobile = ?,
                    email = ?,
                    address = ?,
                    is_active = ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                "ssssssii",
                $vendor_name,
                $vendor_type,
                $contact_person,
                $mobile,
                $email,
                $address,
                $is_active,
                $edit_id
            );

            if ($stmt->execute()) {
                $message = "Vendor updated successfully.";
                $editData = null;
            } else {
                $error = "Update failed: " . $stmt->error;
            }
            $stmt->close();

        } else {
            $stmt = $conn->prepare("SELECT id FROM cctv_vendors WHERE vendor_name = ? LIMIT 1");
            $stmt->bind_param("s", $vendor_name);
            $stmt->execute();
            $dup = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($dup) {
                $error = "This vendor already exists.";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO cctv_vendors
                    (
                        vendor_name,
                        vendor_type,
                        contact_person,
                        mobile,
                        email,
                        address,
                        is_active
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "ssssssi",
                    $vendor_name,
                    $vendor_type,
                    $contact_person,
                    $mobile,
                    $email,
                    $address,
                    $is_active
                );

                if ($stmt->execute()) {
                    $message = "Vendor added successfully.";
                } else {
                    $error = "Insert failed: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

/* =========================
   LIST
========================= */
$vendors = [];
$res = $conn->query("
    SELECT *
    FROM cctv_vendors
    ORDER BY vendor_name ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $vendors[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCTV Vendor Master</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; padding:20px; }
        .container { max-width:1250px; margin:auto; background:#fff; padding:25px; border-radius:12px; box-shadow:0 5px 15px rgba(0,0,0,.08); }
        h2 { margin-top:0; color: #0d6efd; }
        .msg { padding:10px; margin-bottom:12px; border-radius:5px; font-weight:bold; }
        .success { background:#e7f7e7; color:#1f7a1f; border: 1px solid #d4edda; }
        .error { background:#fdeaea; color:#b30000; border: 1px solid #f5c6cb; }
        .row { display:flex; gap:15px; flex-wrap:wrap; margin-bottom:12px; }
        .col { flex:1; min-width:220px; }
        label { display:block; margin-bottom:5px; font-weight:bold; color: #555; }
        input[type="text"], input[type="email"], select, textarea {
            width:100%;
            padding:10px;
            box-sizing:border-box;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        textarea {
            min-height:70px;
        }
        .checks {
            display:flex;
            gap:20px;
            flex-wrap:wrap;
            margin-top:8px;
            margin-bottom:15px;
        }
        .checks label {
            font-weight:normal;
        }
        button, .btn {
            display:inline-block;
            padding:10px 18px;
            border:none;
            border-radius:6px;
            cursor:pointer;
            text-decoration:none;
            color:#fff;
            background:#0d6efd;
            font-weight: bold;
            font-size: 13px;
        }
        .btn-secondary { background:#6c757d; }
        .btn-warning { background:#fd7e14; }
        .btn-success { background:#198754; }
        .btn-danger { background:#dc3545; }
        table {
            width:100%;
            border-collapse:collapse;
            margin-top:25px;
        }
        th, td {
            border:1px solid #ddd;
            padding:12px;
            font-size:13px;
            vertical-align:top;
            text-align: left;
        }
        th {
            background:#f8f9fa;
            color: #333;
        }
        .badge {
            display:inline-block;
            padding:4px 8px;
            border-radius:4px;
            color:#fff;
            font-size:11px;
            font-weight: bold;
        }
        .active-badge { background:#198754; }
        .inactive-badge { background:#6c757d; }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
        <h2 style="margin:0;">CCTV Vendor Master</h2>
        <div style="display:flex; gap:10px;">
            <a class="btn btn-secondary" style="background:#0d6efd;" href="cctv_dashboard.php">CCTV Dashboard</a>
            <a class="btn btn-secondary" href="dashboard_ajax_v2.php">Main Dashboard</a>
            <a class="btn btn-secondary" style="background:#198754;" href="cctv_set_requisition.php">Requisition Entry</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="msg success"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="msg error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" style="background:#f9f9f9; padding:20px; border-radius:8px; border:1px solid #eee;">
        <input type="hidden" name="edit_id" value="<?= h($editData['id'] ?? '') ?>">

        <div class="row">
            <div class="col">
                <label>Vendor Name</label>
                <input type="text" name="vendor_name" value="<?= h($editData['vendor_name'] ?? '') ?>" required placeholder="Company Name">
            </div>

            <div class="col">
                <label>Vendor Type</label>
                <select name="vendor_type" required>
                    <?php
                    $selectedType = $editData['vendor_type'] ?? 'BOTH';
                    $types = ['ENLISTED', 'PROCUREMENT', 'SERVICE', 'BOTH'];
                    foreach ($types as $type):
                    ?>
                        <option value="<?= h($type) ?>" <?= $selectedType === $type ? 'selected' : '' ?>>
                            <?= h($type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col">
                <label>Contact Person</label>
                <input type="text" name="contact_person" value="<?= h($editData['contact_person'] ?? '') ?>" placeholder="Name">
            </div>
        </div>

        <div class="row">
            <div class="col">
                <label>Mobile</label>
                <input type="text" name="mobile" value="<?= h($editData['mobile'] ?? '') ?>" placeholder="Phone Number">
            </div>

            <div class="col">
                <label>Email</label>
                <input type="email" name="email" value="<?= h($editData['email'] ?? '') ?>" placeholder="example@mail.com">
            </div>
        </div>

        <div class="row">
            <div class="col">
                <label>Address</label>
                <textarea name="address" placeholder="Office Address"><?= h($editData['address'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="checks">
            <label>
                <input type="checkbox" name="is_active" <?= !isset($editData) || !empty($editData['is_active']) ? 'checked' : '' ?>>
                <strong>Active Status</strong>
            </label>
        </div>

        <button type="submit" class="btn"><?= $editData ? 'Update Vendor' : 'Add New Vendor' ?></button>
        <?php if($editData): ?>
            <a href="cctv_vendor_master.php" class="btn btn-secondary">Cancel Edit</a>
        <?php endif; ?>
    </form>

    <div style="overflow-x:auto;">
        <table>
            <thead>
            <tr>
                <th style="width:50px;">ID</th>
                <th>Vendor Name</th>
                <th>Type</th>
                <th>Contact Person</th>
                <th>Mobile</th>
                <th>Email</th>
                <th>Status</th>
                <th style="width:160px;">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($vendors)): ?>
                <tr>
                    <td colspan="8" style="text-align:center; padding:30px; color:#999;">No vendor records found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($vendors as $vendor): ?>
                    <tr>
                        <td><?= (int)$vendor['id'] ?></td>
                        <td><strong><?= h($vendor['vendor_name']) ?></strong></td>
                        <td><small><?= h($vendor['vendor_type']) ?></small></td>
                        <td><?= h($vendor['contact_person']) ?></td>
                        <td><?= h($vendor['mobile']) ?></td>
                        <td><?= h($vendor['email']) ?></td>
                        <td>
                            <?php if (!empty($vendor['is_active'])): ?>
                                <span class="badge active-badge">Active</span>
                            <?php else: ?>
                                <span class="badge inactive-badge">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="btn btn-warning" style="padding:5px 10px; font-size:11px;" href="?edit=<?= (int)$vendor['id'] ?>">Edit</a>
                            <a class="btn <?= !empty($vendor['is_active']) ? 'btn-danger' : 'btn-success' ?>"
                            style="padding:5px 10px; font-size:11px;"
                            href="?toggle=<?= (int)$vendor['id'] ?>"
                            onclick="return confirm('Change vendor status?')">
                                <?= !empty($vendor['is_active']) ? 'Deact' : 'Act' ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>
