<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('cctv_location_master');

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

/* =========================
   EDIT LOAD
========================= */
$editData = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];

    $stmt = $conn->prepare("SELECT * FROM cctv_locations WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$editData) {
        $error = "Location not found.";
    }
}

/* =========================
   SAVE / UPDATE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $edit_id       = (int)($_POST['edit_id'] ?? 0);
    $atm_master_id = trim($_POST['atm_master_id'] ?? '');
    $atm_id        = trim($_POST['atm_id'] ?? '');
    $branch_name   = trim($_POST['branch_name'] ?? '');
    $booth_name    = trim($_POST['booth_name'] ?? '');
    $zone_name     = trim($_POST['zone_name'] ?? '');
    $group_no      = trim($_POST['group_no'] ?? '');
    $service_type  = trim($_POST['service_type'] ?? 'ATM');
    $machine_type  = trim($_POST['machine_type'] ?? 'ATM');
    $is_new_booth  = isset($_POST['is_new_booth']) ? 1 : 0;
    $remarks       = trim($_POST['remarks'] ?? '');

    $allowedServiceTypes = ['ATM','CRM','UPS','NEW_BOOTH'];
    $allowedMachineTypes = ['ATM','CRM','OTHER'];

    if ($branch_name === '' || $booth_name === '') {
        $error = "Branch Name and Booth Name are required.";
    } elseif (!in_array($service_type, $allowedServiceTypes, true)) {
        $error = "Invalid service type.";
    } elseif (!in_array($machine_type, $allowedMachineTypes, true)) {
        $error = "Invalid machine type.";
    } else {
        $atmMasterIdVal = ($atm_master_id !== '') ? (int)$atm_master_id : null;
        $groupNoVal = ($group_no !== '') ? (int)$group_no : null;

        if ($edit_id > 0) {
            // duplicate ATM ID check except current
            if ($atm_id !== '') {
                $stmt = $conn->prepare("SELECT id FROM cctv_locations WHERE atm_id = ? AND id <> ? LIMIT 1");
                $stmt->bind_param("si", $atm_id, $edit_id);
                $stmt->execute();
                $dup = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($dup) {
                    $error = "This ATM ID already exists in another location.";
                }
            }

            if ($error === '') {
                $stmt = $conn->prepare("
                    UPDATE cctv_locations
                    SET atm_master_id = ?,
                        atm_id = ?,
                        branch_name = ?,
                        booth_name = ?,
                        zone_name = ?,
                        group_no = ?,
                        service_type = ?,
                        machine_type = ?,
                        is_new_booth = ?,
                        remarks = ?
                    WHERE id = ?
                ");
                $stmt->bind_param(
                    "issssissisi",
                    $atmMasterIdVal,
                    $atm_id,
                    $branch_name,
                    $booth_name,
                    $zone_name,
                    $groupNoVal,
                    $service_type,
                    $machine_type,
                    $is_new_booth,
                    $remarks,
                    $edit_id
                );

                if ($stmt->execute()) {
                    $message = "Location updated successfully.";
                    $editData = null;
                } else {
                    $error = "Update failed: " . $stmt->error;
                }
                $stmt->close();
            }

        } else {
            // duplicate ATM ID check
            if ($atm_id !== '') {
                $stmt = $conn->prepare("SELECT id FROM cctv_locations WHERE atm_id = ? LIMIT 1");
                $stmt->bind_param("s", $atm_id);
                $stmt->execute();
                $dup = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($dup) {
                    $error = "This ATM ID already exists.";
                }
            }

            // duplicate Branch + Booth check
            if ($error === '') {
                $stmt = $conn->prepare("SELECT id FROM cctv_locations WHERE branch_name = ? AND booth_name = ? LIMIT 1");
                $stmt->bind_param("ss", $branch_name, $booth_name);
                $stmt->execute();
                $dup2 = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($dup2) {
                    $error = "This Branch + Booth already exists.";
                }
            }

            if ($error === '') {
                $stmt = $conn->prepare("
                    INSERT INTO cctv_locations
                    (
                        atm_master_id,
                        atm_id,
                        branch_name,
                        booth_name,
                        zone_name,
                        group_no,
                        service_type,
                        machine_type,
                        is_new_booth,
                        remarks
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "issssissis",
                    $atmMasterIdVal,
                    $atm_id,
                    $branch_name,
                    $booth_name,
                    $zone_name,
                    $groupNoVal,
                    $service_type,
                    $machine_type,
                    $is_new_booth,
                    $remarks
                );

                if ($stmt->execute()) {
                    $message = "Location added successfully.";
                } else {
                    $error = "Insert failed: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

/* =========================
   SEARCH
========================= */
$search = trim($_GET['search'] ?? '');

$locations = [];

if ($search !== '') {
    $stmt = $conn->prepare("
        SELECT *
        FROM cctv_locations
        WHERE atm_id LIKE ?
           OR branch_name LIKE ?
           OR booth_name LIKE ?
           OR zone_name LIKE ?
        ORDER BY branch_name ASC, booth_name ASC
    ");
    $like = '%' . $search . '%';
    $stmt->bind_param("ssss", $like, $like, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $locations[] = $row;
    }
    $stmt->close();
} else {
    $res = $conn->query("
        SELECT *
        FROM cctv_locations
        ORDER BY branch_name ASC, booth_name ASC
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $locations[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CCTV Location Master</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; padding:20px; }
        .container { max-width:1400px; margin:auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.08); }
        h2 { margin-top:0; }
        .msg { padding:10px; margin-bottom:12px; border-radius:5px; }
        .success { background:#e7f7e7; color:#1f7a1f; }
        .error { background:#fdeaea; color:#b30000; }
        .row { display:flex; gap:15px; flex-wrap:wrap; margin-bottom:12px; }
        .col { flex:1; min-width:220px; }
        label { display:block; margin-bottom:5px; font-weight:bold; }
        input[type="text"], input[type="number"], select, textarea {
            width:100%;
            padding:9px;
            box-sizing:border-box;
        }
        textarea { min-height:70px; }
        .checks {
            display:flex;
            gap:20px;
            flex-wrap:wrap;
            margin-top:8px;
            margin-bottom:15px;
        }
        .checks label { font-weight:normal; }
        button, .btn {
            display:inline-block;
            padding:9px 14px;
            border:none;
            border-radius:5px;
            cursor:pointer;
            text-decoration:none;
            color:#fff;
            background:#0d6efd;
        }
        .btn-secondary { background:#6c757d; }
        .btn-warning { background:#fd7e14; }
        table {
            width:100%;
            border-collapse:collapse;
            margin-top:18px;
        }
        th, td {
            border:1px solid #ddd;
            padding:8px;
            font-size:13px;
            vertical-align:top;
        }
        th { background:#f1f1f1; }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">

    <h2>CCTV Location Master</h2>

    <div style="margin-bottom:15px;">
        <a class="btn btn-secondary" href="cctv_dashboard.php">Dashboard</a>
        <a class="btn btn-secondary" href="cctv_set_requisition.php">Requisition Entry</a>
    </div>

    <?php if ($message): ?>
        <div class="msg success"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="msg error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="edit_id" value="<?= h($editData['id'] ?? '') ?>">

        <div class="row">
            <div class="col">
                <label>ATM Master ID</label>
                <input type="number" name="atm_master_id" value="<?= h($editData['atm_master_id'] ?? '') ?>">
            </div>

            <div class="col">
                <label>ATM ID</label>
                <input type="text" name="atm_id" value="<?= h($editData['atm_id'] ?? '') ?>">
            </div>

            <div class="col">
                <label>Branch Name</label>
                <input type="text" name="branch_name" value="<?= h($editData['branch_name'] ?? '') ?>" required>
            </div>

            <div class="col">
                <label>Booth Name</label>
                <input type="text" name="booth_name" value="<?= h($editData['booth_name'] ?? '') ?>" required>
            </div>
        </div>

        <div class="row">
            <div class="col">
                <label>Zone Name</label>
                <input type="text" name="zone_name" value="<?= h($editData['zone_name'] ?? '') ?>">
            </div>

            <div class="col">
                <label>Group No</label>
                <input type="number" name="group_no" value="<?= h($editData['group_no'] ?? '') ?>">
            </div>

            <div class="col">
                <label>Service Type</label>
                <select name="service_type">
                    <?php
                    $serviceType = $editData['service_type'] ?? 'ATM';
                    $serviceTypes = ['ATM','CRM','UPS','NEW_BOOTH'];
                    foreach ($serviceTypes as $st):
                    ?>
                        <option value="<?= h($st) ?>" <?= $serviceType === $st ? 'selected' : '' ?>>
                            <?= h($st) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col">
                <label>Machine Type</label>
                <select name="machine_type">
                    <?php
                    $machineType = $editData['machine_type'] ?? 'ATM';
                    $machineTypes = ['ATM','CRM','OTHER'];
                    foreach ($machineTypes as $mt):
                    ?>
                        <option value="<?= h($mt) ?>" <?= $machineType === $mt ? 'selected' : '' ?>>
                            <?= h($mt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="checks">
            <label>
                <input type="checkbox" name="is_new_booth" <?= !empty($editData['is_new_booth']) ? 'checked' : '' ?>>
                Is New Booth
            </label>
        </div>

        <div class="row">
            <div class="col">
                <label>Remarks</label>
                <textarea name="remarks"><?= h($editData['remarks'] ?? '') ?></textarea>
            </div>
        </div>

        <button type="submit"><?= $editData ? 'Update Location' : 'Add Location' ?></button>
    </form>

    <hr>

    <form method="get">
        <div class="row">
            <div class="col">
                <label>Search</label>
                <input type="text" name="search" value="<?= h($search) ?>" placeholder="ATM ID / Branch / Booth / Zone">
            </div>
            <div class="col" style="display:flex;align-items:end;">
                <button type="submit">Search</button>
            </div>
        </div>
    </form>

    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>ATM Master ID</th>
            <th>ATM ID</th>
            <th>Branch</th>
            <th>Booth</th>
            <th>Zone</th>
            <th>Group</th>
            <th>Service Type</th>
            <th>Machine Type</th>
            <th>New Booth</th>
            <th>Remarks</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($locations)): ?>
            <tr>
                <td colspan="12" style="text-align:center;">No location found.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($locations as $loc): ?>
                <tr>
                    <td><?= (int)$loc['id'] ?></td>
                    <td><?= h($loc['atm_master_id']) ?></td>
                    <td><?= h($loc['atm_id']) ?></td>
                    <td><?= h($loc['branch_name']) ?></td>
                    <td><?= h($loc['booth_name']) ?></td>
                    <td><?= h($loc['zone_name']) ?></td>
                    <td><?= h($loc['group_no']) ?></td>
                    <td><?= h($loc['service_type']) ?></td>
                    <td><?= h($loc['machine_type']) ?></td>
                    <td><?= !empty($loc['is_new_booth']) ? 'Yes' : 'No' ?></td>
                    <td><?= h($loc['remarks']) ?></td>
                    <td>
                        <a class="btn btn-warning" href="?edit=<?= (int)$loc['id'] ?>">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

</div>
</body>
</html>