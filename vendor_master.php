<?php
require_once __DIR__ . '/init.php';

// এনকোডিং ঠিক রাখা
mysqli_set_charset(Database::getInstance()->getConnection(), "utf8");

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$message = '';
$messageType = 'success';
$editData = null;
$editContacts = ['mobile' => [], 'email' => [], 'address' => []];
$search = trim($_GET['search'] ?? '');

$vendorTypeOptions = ['ATM', 'UPS', 'CCTV', 'MULTI', 'NETWORK', 'OTHER'];

$vendorObj = new Vendor();

/* ===============================
   ADD / UPDATE LOGIC
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $vendorObj->save($_POST);
    if ($result['success']) {
        header("Location: vendor_master.php?msg=saved");
        exit;
    } else {
        $message = "Error: " . ($result['error'] ?? 'Unknown error');
        $messageType = 'danger';
    }
}

/* ===============================
   EDIT LOAD
================================ */
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editData = $vendorObj->getById($editId);
    if ($editData) {
        $editContacts = $vendorObj->getContacts($editId);
    }
}

/* ===============================
   DELETE & LIST LOAD
================================ */
if (isset($_GET['delete'])) {
    $vendorObj->delete((int)$_GET['delete']);
    header("Location: vendor_master.php?msg=deleted"); 
    exit;
}

if (isset($_GET['msg'])) { 
    $message = $_GET['msg'] == 'saved' ? "Saved successfully." : "Deleted successfully."; 
}

$list = $vendorObj->getAll($search);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vendor Master - Advanced</title>
    <style>
        /* আপনার সিএসএস বহাল রেখে নিচের নতুন অংশ যোগ করুন */
        body { font-family: Arial, sans-serif; padding: 20px; background: #f4f7fb; }
        .container { max-width: 1350px; margin: 0 auto; }
        .card { background: #fff; border-radius: 14px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .contact-group { background: #f9fafb; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 10px; }
        .contact-group h4 { margin: 0 0 10px; font-size: 14px; color: #374151; }
        .dynamic-row { display: flex; gap: 5px; margin-bottom: 5px; }
        input[type="text"], input[type="email"], select, textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; }
        .btn { padding: 8px 15px; border-radius: 6px; cursor: pointer; border: none; font-weight: bold; }
        .btn-add { background: #dcfce7; color: #166534; font-size: 12px; }
        .btn-rem { background: #fee2e2; color: #991b1b; }
        .btn-primary { background: #2563eb; color: #fff; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; }
        th, td { border: 1px solid #e5e7eb; padding: 12px; text-align: left; vertical-align: top; font-size: 13px; }
        th { background: #f8fafc; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .badge-cctv { background: #fef3c7; color: #b45309; }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container">
    <div class="card" style="background: linear-gradient(135deg, #0f172a, #1d4ed8); color: #fff; display: flex; justify-content: space-between; align-items: center;">
        <h2>Vendor Management Master</h2>
        <a href="dashboard_ajax_v2.php" style="color: #fff; text-decoration: none; font-weight: bold;">← Dashboard</a>
    </div>

    <?php if ($message != ''): ?>
        <div style="padding:15px; margin-bottom:20px; border-radius:8px; background:<?= $messageType=='danger'?'#fff1f2':'#e8fff0' ?>; color:<?= $messageType=='danger'?'#b91c1c':'#166534' ?>;">
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3><?= $editData ? 'Edit Vendor' : 'Add New Vendor' ?></h3>
        <form method="post">
            <?php if ($editData): ?><input type="hidden" name="id" value="<?= $editData['id'] ?>"><?php endif; ?>
            
            <div class="form-grid">
                <div>
                    <label>Vendor Name</label>
                    <input type="text" name="vendor_name" required value="<?= h($editData['vendor_name'] ?? '') ?>">
                    <br><br>
                    <label>Vendor Type</label>
                    <select name="vendor_type">
                        <?php foreach ($vendorTypeOptions as $type): ?>
                            <option value="<?= $type ?>" <?= (($editData['vendor_type'] ?? '') == $type) ? 'selected' : '' ?>><?= $type ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <!-- Multiple Mobiles -->
                    <div class="contact-group">
                        <h4>Mobile Numbers <button type="button" class="btn btn-add" onclick="addRow('mobile-container', 'mobiles[]')">+ Add</button></h4>
                        <div id="mobile-container">
                            <?php 
                            $m_list = $editContacts['mobile'] ?: [''];
                            foreach($m_list as $m): ?>
                                <div class="dynamic-row">
                                    <input type="text" name="mobiles[]" value="<?= h($m) ?>" placeholder="Mobile number">
                                    <button type="button" class="btn btn-rem" onclick="this.parentElement.remove()">×</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Multiple Emails -->
                    <div class="contact-group">
                        <h4>Email Addresses <button type="button" class="btn btn-add" onclick="addRow('email-container', 'emails[]')">+ Add</button></h4>
                        <div id="email-container">
                            <?php 
                            $e_list = $editContacts['email'] ?: [''];
                            foreach($e_list as $e): ?>
                                <div class="dynamic-row">
                                    <input type="email" name="emails[]" value="<?= h($e) ?>" placeholder="Email address">
                                    <button type="button" class="btn btn-rem" onclick="this.parentElement.remove()">×</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Multiple Addresses -->
                    <div class="contact-group">
                        <h4>Offices/Addresses <button type="button" class="btn btn-add" onclick="addRow('address-container', 'addresses[]')">+ Add</button></h4>
                        <div id="address-container">
                            <?php 
                            $a_list = $editContacts['address'] ?: [''];
                            foreach($a_list as $a): ?>
                                <div class="dynamic-row">
                                    <textarea name="addresses[]" rows="2" placeholder="Office address"><?= h($a) ?></textarea>
                                    <button type="button" class="btn btn-rem" onclick="this.parentElement.remove()">×</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-primary"><?= $editData ? 'Update Vendor' : 'Save Vendor' ?></button>
                <a href="vendor_master.php" class="btn" style="background:#eee;">Reset</a>
            </div>
        </form>
    </div>

    <!-- Table List -->
    <div class="card">
        <form method="get" style="margin-bottom: 20px; display: flex; gap: 10px;">
            <input type="text" name="search" value="<?= h($search) ?>" placeholder="Search by name, mobile, email or address...">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>

        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Vendor Name</th>
                        <th>Type</th>
                        <th>Mobiles</th>
                        <th>Emails</th>
                        <th>Addresses</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $list->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= h($row['vendor_name']) ?></strong></td>
                        <td><span class="badge badge-cctv"><?= h($row['vendor_type']) ?></span></td>
                        <td><?= ($row['mobiles']) ?></td>
                        <td><?= ($row['emails']) ?></td>
                        <td><small><?= ($row['addresses']) ?></small></td>
                        <td>
                            <a href="vendor_master.php?edit=<?= $row['id'] ?>" class="btn" style="background: #f59e0b; color: #fff;">Edit</a>
                            <a href="vendor_master.php?delete=<?= $row['id'] ?>" class="btn btn-rem" onclick="return confirm('Delete this vendor?')">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function addRow(containerId, name) {
    const container = document.getElementById(containerId);
    const div = document.createElement('div');
    div.className = 'dynamic-row';
    
    if(name === 'addresses[]') {
        div.innerHTML = `<textarea name="${name}" rows="2" placeholder="Enter details"></textarea>
                         <button type="button" class="btn btn-rem" onclick="this.parentElement.remove()">×</button>`;
    } else {
        const type = name === 'emails[]' ? 'email' : 'text';
        div.innerHTML = `<input type="${type}" name="${name}" placeholder="Enter details">
                         <button type="button" class="btn btn-rem" onclick="this.parentElement.remove()">×</button>`;
    }
    container.appendChild(div);
}
</script>

</body>
</html>