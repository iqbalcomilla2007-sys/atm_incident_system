<?php
date_default_timezone_set('Asia/Dhaka');
include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

// Assuming manage_atm_master permission is sufficient or change as needed
Auth::requirePermission('manage_atm_master');

$message = '';
function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$search = trim($_GET['search'] ?? '');

// --- Form Submissions ---

// Add / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sg'])) {
    $id = (int)($_POST['id'] ?? 0);
    $atm_id      = trim($_POST['atm_id'] ?? '');
    $branch_code = trim($_POST['branch_code'] ?? '');
    $branch_name = trim($_POST['branch_name'] ?? '');
    $booth_address = trim($_POST['booth_address'] ?? '');
    $sg1_name    = trim($_POST['sg1_name'] ?? '');
    $sg1_mobile  = trim($_POST['sg1_mobile'] ?? '');
    $sg2_name    = trim($_POST['sg2_name'] ?? '');
    $sg2_mobile  = trim($_POST['sg2_mobile'] ?? '');
    $sg3_name    = trim($_POST['sg3_name'] ?? '');
    $sg3_mobile  = trim($_POST['sg3_mobile'] ?? '');
    $supervisor_details = trim($_POST['supervisor_details'] ?? '');
    $company_details    = trim($_POST['company_details'] ?? '');

    if ($branch_name === '') {
        $message = "Error: Branch Name is required.";
    } else {
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE atm_sg SET atm_id=?, branch_code=?, branch_name=?, booth_address=?, sg1_name=?, sg1_mobile=?, sg2_name=?, sg2_mobile=?, sg3_name=?, sg3_mobile=?, supervisor_details=?, company_details=? WHERE id=?");
            $stmt->bind_param("ssssssssssssi", $atm_id, $branch_code, $branch_name, $booth_address, $sg1_name, $sg1_mobile, $sg2_name, $sg2_mobile, $sg3_name, $sg3_mobile, $supervisor_details, $company_details, $id);
            if ($stmt->execute()) { 
                $message = "Security Guard info updated."; 
                AuditLog::log("UPDATE_SECURITY_GUARD", "Updated guard info for ATM ID: " . $atm_id . ", Branch Name: " . $branch_name);
                $editId = 0; 
            }
            else { $message = "Error updating info: " . $stmt->error; }
        } else {
            $stmt = $conn->prepare("INSERT INTO atm_sg (atm_id, branch_code, branch_name, booth_address, sg1_name, sg1_mobile, sg2_name, sg2_mobile, sg3_name, sg3_mobile, supervisor_details, company_details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssssss", $atm_id, $branch_code, $branch_name, $booth_address, $sg1_name, $sg1_mobile, $sg2_name, $sg2_mobile, $sg3_name, $sg3_mobile, $supervisor_details, $company_details);
            if ($stmt->execute()) { 
                $message = "Security Guard info added."; 
                AuditLog::log("CREATE_SECURITY_GUARD", "Added guard info for ATM ID: " . $atm_id . ", Branch Name: " . $branch_name);
            }
            else { $message = "Error adding info: " . $stmt->error; }
        }
    }
}

// Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM atm_sg WHERE id = ?");
    $stmt->bind_param("i", $delId);
    if ($stmt->execute()) { 
        AuditLog::log("DELETE_SECURITY_GUARD", "Deleted guard info record ID: " . $delId);
        header("Location: manage_atm_security.php?msg=deleted"); 
        exit; 
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') $message = "Record deleted successfully.";

// --- Load Data ---

$editData = [];
if ($editId > 0) {
    $resEdit = $conn->query("SELECT * FROM atm_sg WHERE id = $editId");
    if ($resEdit) $editData = $resEdit->fetch_assoc();
}

$listSql = "SELECT * FROM atm_sg";
if ($search !== '') {
    $s = "%$search%";
    $listSql .= " WHERE atm_id LIKE '$s' OR branch_code LIKE '$s' OR branch_name LIKE '$s' OR sg1_name LIKE '$s' OR sg1_mobile LIKE '$s' OR company_details LIKE '$s'";
}
$listSql .= " ORDER BY id DESC";
$listRes = $conn->query($listSql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ATM Security Management</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f6f9; }
        .container { max-width: 1200px; margin: auto; }
        .card { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .form-grid { display: grid; grid-template-columns: 150px 1fr 150px 1fr; gap: 10px; align-items: center; }
        input[type="text"] { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 14px; color: #fff; }
        .btn-blue { background: #007bff; } .btn-green { background: #28a745; }
        .btn-red { background: #dc3545; } .btn-secondary { background: #6c757d; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table th, table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        table th { background: #f8f9fa; }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2>ATM Security Guard Management</h2>
        <a href="manage_atm_master.php" class="btn btn-secondary">Back to ATM Master</a>
    </div>

    <?php if ($message): ?>
        <div style="padding:10px; background:#e8fff0; border:1px solid #b7ebc6; color:#155724; margin-bottom:15px; border-radius:5px;"><?= h($message) ?></div>
    <?php endif; ?>

    <div class="card">
        <h3><?= $editId > 0 ? 'Edit Guard Info' : 'Add New Guard Info' ?></h3>
        <form method="post">
            <input type="hidden" name="id" value="<?= (int)$editId ?>">
            <div class="form-grid">
                <label>ATM ID</label><input type="text" name="atm_id" value="<?= h($editData['atm_id'] ?? '') ?>">
                <label>Branch Code</label><input type="text" name="branch_code" value="<?= h($editData['branch_code'] ?? '') ?>">
                
                <label>Branch Name</label><input type="text" name="branch_name" required value="<?= h($editData['branch_name'] ?? '') ?>">
                <label>Booth Address</label><input type="text" name="booth_address" value="<?= h($editData['booth_address'] ?? '') ?>">
                
                <label>SG 1 Name</label><input type="text" name="sg1_name" value="<?= h($editData['sg1_name'] ?? '') ?>">
                <label>SG 1 Mobile</label><input type="text" name="sg1_mobile" value="<?= h($editData['sg1_mobile'] ?? '') ?>">
                
                <label>SG 2 Name</label><input type="text" name="sg2_name" value="<?= h($editData['sg2_name'] ?? '') ?>">
                <label>SG 2 Mobile</label><input type="text" name="sg2_mobile" value="<?= h($editData['sg2_mobile'] ?? '') ?>">
                
                <label>SG 3 Name</label><input type="text" name="sg3_name" value="<?= h($editData['sg3_name'] ?? '') ?>">
                <label>SG 3 Mobile</label><input type="text" name="sg3_mobile" value="<?= h($editData['sg3_mobile'] ?? '') ?>">

                <label>Supervisor Details</label><input type="text" name="supervisor_details" value="<?= h($editData['supervisor_details'] ?? '') ?>">
                <label>Company Details</label><input type="text" name="company_details" value="<?= h($editData['company_details'] ?? '') ?>">
            </div>
            <div style="margin-top:15px;">
                <button type="submit" name="save_sg" class="btn btn-green"><?= $editId > 0 ? 'Update Info' : 'Save Info' ?></button>
                <?php if($editId > 0): ?><a href="manage_atm_security.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>Security Guard List</h3>
        <form method="get" style="margin-bottom:15px; display:flex; gap:10px;">
            <input type="text" name="search" placeholder="Search branch, ATM, guard or mobile..." value="<?= h($search) ?>" style="flex:1;">
            <button type="submit" class="btn btn-blue">Search</button>
            <a href="manage_atm_security.php" class="btn btn-secondary">Reset</a>
        </form>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">SL</th>
                        <th>ATM ID</th>
                        <th>Branch Code</th>
                        <th>Branch/ATM</th>
                        <th>Address</th>
                        <th>Guard Info (1, 2, 3)</th>
                        <th>Company/Supervisor</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($listRes && $listRes->num_rows > 0): ?>
                        <?php $sl = 1; ?>
                        <?php while($row = $listRes->fetch_assoc()): ?>
                        <tr>
                            <td><?= $sl++ ?></td>
                            <td><?= h($row['atm_id']) ?></td>
                            <td><?= h($row['branch_code']) ?></td>
                            <td>
                                <?= h($row['branch_name']) ?>
                            </td>
                            <td style="font-size:12px;"><?= h($row['booth_address']) ?></td>
                            <td>
                                <small>
                                    1. <?= h($row['sg1_name']) ?> (<?= h($row['sg1_mobile']) ?>)<br>
                                    2. <?= h($row['sg2_name']) ?> (<?= h($row['sg2_mobile']) ?>)<br>
                                    3. <?= h($row['sg3_name']) ?> (<?= h($row['sg3_mobile']) ?>)
                                </small>
                            </td>
                            <td>
                                <small>
                                    <b>Co:</b> <?= h($row['company_details']) ?><br>
                                    <b>Sup:</b> <?= h($row['supervisor_details']) ?>
                                </small>
                            </td>
                            <td>
                                <a href="manage_atm_security.php?edit=<?= $row['id'] ?>" class="btn btn-secondary" style="background:#ffc107; color:#000; padding:5px 10px;">Edit</a>
                                <a href="manage_atm_security.php?delete=<?= $row['id'] ?>" class="btn btn-red" style="padding:5px 10px;" onclick="return confirm('Delete this record?')">Delete</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center;">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
