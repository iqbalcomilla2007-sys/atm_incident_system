<?php
date_default_timezone_set('Asia/Dhaka');
include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('manage_atm_master');

$message = '';
function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$search = trim($_GET['search'] ?? '');

// --- Form Submissions ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_contact'])) {
    $id = (int)($_POST['id'] ?? 0);
    $search_param     = trim($_POST['search_param'] ?? ''); 
    $branch_code      = trim($_POST['branch_code'] ?? '');
    $branch_name      = trim($_POST['branch_name'] ?? '');

<<<<<<< HEAD
=======
    // NOTE: branch_name এবং branch_code এখন dropdown থেকে সরাসরি (JS দিয়ে) আসে,
    // তাই এখানে আর branch_name দিয়ে branch_code অনুমান/override করার দরকার নেই।
    // (আগে এখানে LIMIT 1 দিয়ে auto-fetch করা হতো, যেটা একই branch_name এর একাধিক
    // ভিন্ন branch_code থাকলে ভুল code বসিয়ে দিত এবং false duplicate error দিতো।)

>>>>>>> c6a99dc9be510c188a6889613b6cd33eb079cdb1
    $custodian1_name  = trim($_POST['custodian1_name'] ?? '');
    $custodian1_mobile = trim($_POST['custodian1_mobile'] ?? '');
    $custodian2_name  = trim($_POST['custodian2_name'] ?? '');
    $custodian2_mobile = trim($_POST['custodian2_mobile'] ?? '');
    $ip_phone_no      = trim($_POST['ip_phone_no'] ?? '');
    $manager_name     = trim($_POST['manager_name'] ?? '');
    $manager_mobile    = trim($_POST['manager_mobile'] ?? '');

    if ($branch_name === '') {
        $message = "Error: Branch Name is required.";
    } else {
        // --- Duplicate Branch Code Check ---
        $isDuplicate = false;
        if ($branch_code !== '') {
            $chkStmt = $conn->prepare("SELECT id FROM atm_contact WHERE branch_code = ? AND id != ?");
            $chkStmt->bind_param("si", $branch_code, $id);
            $chkStmt->execute();
            $chkRes = $chkStmt->get_result();
            if ($chkRes->num_rows > 0) {
                $isDuplicate = true;
            }
            $chkStmt->close();
        }

        if ($isDuplicate) {
            $message = "Error: Branch Code '$branch_code' already exists in another contact.";
        } else {
            // Proceed with Insert / Update
            $s_url = $search_param !== '' ? '&search=' . urlencode($search_param) : '';

            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE atm_contact SET branch_code=?, branch_name=?, custodian1_name=?, custodian1_mobile=?, custodian2_name=?, custodian2_mobile=?, ip_phone_no=?, manager_name=?, manager_mobile=? WHERE id=?");
                $stmt->bind_param("sssssssssi", $branch_code, $branch_name, $custodian1_name, $custodian1_mobile, $custodian2_name, $custodian2_mobile, $ip_phone_no, $manager_name, $manager_mobile, $id);
                if ($stmt->execute()) { 
                    header("Location: manage_atm_contact.php?msg=updated{$s_url}#row-{$id}");
                    exit;
                } else { 
                    $message = "Error updating info: " . $stmt->error; 
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO atm_contact (branch_code, branch_name, custodian1_name, custodian1_mobile, custodian2_name, custodian2_mobile, ip_phone_no, manager_name, manager_mobile) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssssss", $branch_code, $branch_name, $custodian1_name, $custodian1_mobile, $custodian2_name, $custodian2_mobile, $ip_phone_no, $manager_name, $manager_mobile);
                if ($stmt->execute()) { 
                    $newId = $conn->insert_id;
                    header("Location: manage_atm_contact.php?msg=added{$s_url}#row-{$newId}");
                    exit;
                } else { 
                    $message = "Error adding info: " . $stmt->error; 
                }
            }
        }
    }
}

// Search URL persistence for links
$searchQueryStr = $search !== '' ? '&search=' . urlencode($search) : '';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM atm_contact WHERE id = ?");
    $stmt->bind_param("i", $delId);
    if ($stmt->execute()) { 
        header("Location: manage_atm_contact.php?msg=deleted{$searchQueryStr}"); 
        exit; 
    }
}

// Status Messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted') $message = "Record deleted successfully.";
    if ($_GET['msg'] === 'updated') $message = "Contact info updated successfully.";
    if ($_GET['msg'] === 'added') $message = "Contact info added successfully.";
}

// --- Load Data ---

$editData = [];
if ($editId > 0) {
    $resEdit = $conn->query("SELECT * FROM atm_contact WHERE id = $editId");
    if ($resEdit) $editData = $resEdit->fetch_assoc();
}

$listSql = "SELECT * FROM atm_contact";
if ($search !== '') {
    $s = "%$search%";
    $listSql .= " WHERE branch_code LIKE '$s' OR branch_name LIKE '$s' OR custodian1_name LIKE '$s' OR manager_name LIKE '$s'";
}
$listSql .= " ORDER BY id DESC";
$listRes = $conn->query($listSql);

<<<<<<< HEAD
// --- Branch dropdown data (প্রথমে branch_name অনুযায়ী সর্ট করা হয়েছে) ---
$branchMapRes = $conn->query("SELECT id, zone_name, branch_name, branch_code FROM zone_branch_map ORDER BY branch_name ASC, zone_name ASC");
=======
// --- Branch dropdown data (zone_branch_map থেকে সব branch, নতুন) ---
$branchMapRes = $conn->query("SELECT id, zone_name, branch_name, branch_code FROM zone_branch_map ORDER BY zone_name ASC, branch_name ASC");
>>>>>>> c6a99dc9be510c188a6889613b6cd33eb079cdb1
$branchMapRows = [];
if ($branchMapRes) {
    while ($bm = $branchMapRes->fetch_assoc()) {
        $branchMapRows[] = $bm;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ATM Contact Management</title>
    <style>
        html { scroll-behavior: smooth; }
        body { font-family: Arial, sans-serif; margin: 20px; background: #fef8f8; }
        .container { max-width: 1200px; margin: auto; }
        .card { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .form-grid { display: grid; grid-template-columns: 150px 1fr 150px 1fr; gap: 10px; align-items: center; }
        input[type="text"], select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        input[readonly] { background: #f1f5f9; color: #475569; }
        .btn { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 14px; color: #fff; }
        .btn-pink { background: #e83e8c; } .btn-green { background: #28a745; }
        .btn-red { background: #dc3545; } .btn-secondary { background: #6c757d; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px;}
        table th, table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        table th { background: #f8f9fa; }
        
        tr:target {
            animation: highlight-row 2s ease-out;
        }
        @keyframes highlight-row {
            0% { background-color: #fce4ec; }
            100% { background-color: transparent; }
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2>ATM contact / Custodian Management</h2>
        <a href="manage_atm_master.php" class="btn btn-secondary">Back to ATM Master</a>
    </div>

    <?php if ($message): ?>
        <div style="padding:10px; background:#fff3f3; color:#9b1c1c; border:1px solid #f5c2c2; margin-bottom:15px; border-radius:5px; font-weight:bold;"><?= h($message) ?></div>
    <?php endif; ?>

    <div id="form-section" class="card" style="border-top:5px solid #e83e8c;">
        <h3><?= $editId > 0 ? 'Edit contact Info' : 'Add New contact Info' ?></h3>
        <form method="post" id="contactForm">
            <input type="hidden" name="id" value="<?= (int)$editId ?>">
            <input type="hidden" name="search_param" value="<?= h($search) ?>">
            <input type="hidden" name="branch_name" id="branch_name_hidden" value="<?= h($editData['branch_name'] ?? '') ?>">

            <div class="form-grid">
                <label>Branch <span style="color:red;">*</span></label>
                <select id="branch_select" required>
                    <option value="">-- Select Branch --</option>
                    <?php
                    $currentName = $editData['branch_name'] ?? '';
                    $currentCode = $editData['branch_code'] ?? '';
                    $matchedExisting = false;
                    foreach ($branchMapRows as $bm):
                        $isSelected = ($editId > 0 && $bm['branch_name'] === $currentName && $bm['branch_code'] === $currentCode);
                        if ($isSelected) $matchedExisting = true;
                    ?>
                        <option value="<?= h($bm['id']) ?>"
                            data-name="<?= h($bm['branch_name']) ?>"
                            data-code="<?= h($bm['branch_code']) ?>"
                            <?= $isSelected ? 'selected' : '' ?>>
<<<<<<< HEAD
                            <?= h($bm['branch_name']) ?> (Zone: <?= h($bm['zone_name']) ?>) [<?= h($bm['branch_code']) ?>]
=======
                            <?= h($bm['zone_name']) ?> - <?= h($bm['branch_name']) ?> (<?= h($bm['branch_code']) ?>)
>>>>>>> c6a99dc9be510c188a6889613b6cd33eb079cdb1
                        </option>
                    <?php endforeach; ?>
                    <?php if ($editId > 0 && !$matchedExisting && $currentName !== ''): ?>
                        <option value="__current__" data-name="<?= h($currentName) ?>" data-code="<?= h($currentCode) ?>" selected>
                            <?= h($currentName) ?> (<?= h($currentCode) ?: '-' ?>) [current]
                        </option>
                    <?php endif; ?>
                </select>

                <label>Branch Code</label><input type="text" id="branch_code_display" readonly value="<?= h($editData['branch_code'] ?? '') ?>">
                <input type="hidden" name="branch_code" id="branch_code_hidden" value="<?= h($editData['branch_code'] ?? '') ?>">

                <label>IP Phone No</label><input type="text" name="ip_phone_no" value="<?= h($editData['ip_phone_no'] ?? '') ?>">
                <label></label><div></div>
                
                <label>Custodian 1 Name</label><input type="text" name="custodian1_name" value="<?= h($editData['custodian1_name'] ?? '') ?>">
                <label>Custodian 1 Mobile</label><input type="text" name="custodian1_mobile" value="<?= h($editData['custodian1_mobile'] ?? '') ?>">
                
                <label>Custodian 2 Name</label><input type="text" name="custodian2_name" value="<?= h($editData['custodian2_name'] ?? '') ?>">
                <label>Custodian 2 Mobile</label><input type="text" name="custodian2_mobile" value="<?= h($editData['custodian2_mobile'] ?? '') ?>">
                
                <label>Manager Name</label><input type="text" name="manager_name" value="<?= h($editData['manager_name'] ?? '') ?>">
                <label>Manager Mobile</label><input type="text" name="manager_mobile" value="<?= h($editData['manager_mobile'] ?? '') ?>">
            </div>
            <div style="margin-top:15px;">
                <button type="submit" name="save_contact" class="btn btn-pink"><?= $editId > 0 ? 'Update contact' : 'Save contact' ?></button>
                <?php if($editId > 0): ?><a href="manage_atm_contact.php<?= $search !== '' ? '?search='.urlencode($search) : '' ?>" class="btn btn-secondary">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>Custodian List</h3>
        <form method="get" style="margin-bottom:15px; display:flex; gap:10px;">
            <input type="text" name="search" placeholder="Search branch, custodian, manager..." value="<?= h($search) ?>" style="flex:1;">
            <button type="submit" class="btn btn-pink">Search</button>
            <a href="manage_atm_contact.php" class="btn btn-secondary">Reset</a>
        </form>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>SL</th>
                        <th>Branch Code</th>
                        <th>Branch</th>
                        <th>Custodians</th>
                        <th>Manager</th>
                        <th>IP Phone</th>
                        <th>Last Modified</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($listRes && $listRes->num_rows > 0): ?>
                        <?php $sl = 1; while($row = $listRes->fetch_assoc()): ?>
                        <tr id="row-<?= $row['id'] ?>"> 
                            <td><?= $sl++ ?></td>
                            <td><?= h($row['branch_code']) ?></td>
                            <td><?= h($row['branch_name']) ?></td>
                            <td>
                                1. <?= h($row['custodian1_name']) ?> (<?= h($row['custodian1_mobile']) ?>)<br>
                                2. <?= h($row['custodian2_name']) ?> (<?= h($row['custodian2_mobile']) ?>)
                            </td>
                            <td><?= h($row['manager_name']) ?> (<?= h($row['manager_mobile']) ?>)</td>
                            <td><?= h($row['ip_phone_no']) ?></td>
                            
                            <td>
                                <?php 
<<<<<<< HEAD
=======
                                    // Handles fallback safely for both column name variations
>>>>>>> c6a99dc9be510c188a6889613b6cd33eb079cdb1
                                    $modTime = trim($row['last_modified_time'] ?? $row['created_at'] ?? '');
                                    if ($modTime !== '' && $modTime !== '0000-00-00 00:00:00') {
                                        echo h(date('d/m/Y h:i A', strtotime($modTime)));
                                    } else {
                                        echo '-';
                                    }
                                ?>
                            </td>
                            
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <a href="manage_atm_contact.php?edit=<?= $row['id'] ?><?= $searchQueryStr ?>#form-section"
                                       class="btn btn-secondary"
                                       style="background:#ffc107; color:#000; padding:5px 10px;">
                                       Edit
                                    </a>

                                    <a href="manage_atm_contact.php?delete=<?= $row['id'] ?><?= $searchQueryStr ?>"
                                       class="btn btn-red"
                                       style="padding:5px 10px;"
                                       onclick="return confirm('Delete this contact?')">
                                       Delete
                                    </a>
                                </div>
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

<script>
<<<<<<< HEAD
=======
// Branch select করলে branch_name ও branch_code auto fill হবে (hidden fields এর মাধ্যমে ফর্মে যাবে)
>>>>>>> c6a99dc9be510c188a6889613b6cd33eb079cdb1
document.getElementById('branch_select').addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    const name = opt.getAttribute('data-name') || '';
    const code = opt.getAttribute('data-code') || '';
    document.getElementById('branch_name_hidden').value = name;
    document.getElementById('branch_code_hidden').value = code;
    document.getElementById('branch_code_display').value = code;
});

<<<<<<< HEAD
=======
// Submit করার আগে check করুন যে branch select করা আছে কিনা
>>>>>>> c6a99dc9be510c188a6889613b6cd33eb079cdb1
document.getElementById('contactForm').addEventListener('submit', function (e) {
    const nameVal = document.getElementById('branch_name_hidden').value.trim();
    if (nameVal === '') {
        e.preventDefault();
        alert('Please select a Branch.');
    }
});
</script>

</body>
</html>