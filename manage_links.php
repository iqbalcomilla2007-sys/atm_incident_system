<?php
date_default_timezone_set('Asia/Dhaka');
require_once __DIR__ . '/init.php';

Auth::requirePermission('manage_atm_master');

$conn = Database::getInstance()->getConnection();
mysqli_set_charset($conn, "utf8");

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$msg = '';
$error = '';
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

// Delete Logic
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $conn->query("DELETE FROM important_links WHERE id = $delId");
    header("Location: manage_links.php?msg=deleted");
    exit;
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'saved') $msg = "Link saved successfully!";
    if ($_GET['msg'] === 'deleted') $msg = "Link deleted successfully!";
}

// Insert / Update Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $serial_no = (int)$_POST['serial_no'];
    $link_name = trim($_POST['link_name']);
    $url = trim($_POST['url']);

    if ($serial_no <= 0 || $link_name === '' || $url === '') {
        $error = "All fields are required!";
    } else {
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE important_links SET serial_no=?, link_name=?, url=? WHERE id=?");
            $stmt->bind_param("issi", $serial_no, $link_name, $url, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO important_links (serial_no, link_name, url) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $serial_no, $link_name, $url);
        }
        
        if ($stmt->execute()) {
            header("Location: manage_links.php?msg=saved");
            exit;
        } else {
            $error = "Database Error: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch Edit Data
$editData = null;
if ($editId > 0) {
    $res = $conn->query("SELECT * FROM important_links WHERE id = $editId");
    if ($res) $editData = $res->fetch_assoc();
}

// Auto-calculate next Serial No for new entry
$nextSerial = 1;
if (!$editData) {
    $maxRes = $conn->query("SELECT MAX(serial_no) AS max_serial FROM important_links");
    if ($maxRes && $rowMax = $maxRes->fetch_assoc()) {
        $nextSerial = ((int)$rowMax['max_serial']) + 1;
    }
}

// Fetch All Links
$links = $conn->query("SELECT * FROM important_links ORDER BY serial_no ASC");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manage Important Links</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f1f5f9; margin: 0; padding: 20px; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; max-width: 800px; margin-left: auto; margin-right: auto; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 13px; }
        .form-group input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .form-group input[readonly] { background: #f8fafc; }
        .btn { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; color: #fff; text-decoration: none; font-size: 14px; }
        .btn-blue { background: #2563eb; } .btn-red { background: #dc2626; } .btn-gray { background: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; font-size: 14px; }
        th { background: #f8fafc; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="card">
    <h2><?= $editData ? 'Edit Link' : 'Add New Link' ?></h2>
    
    <?php if ($msg): ?><div class="alert success"><?= $msg ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= $error ?></div><?php endif; ?>

    <form method="POST">
        <input type="hidden" name="id" value="<?= h($editData['id'] ?? 0) ?>">
        <div style="display: flex; gap: 15px;">
            <div class="form-group" style="width: 20%;">
                <label>Serial No</label>
                <!-- Serial No auto-fills, but remains editable -->
                <input type="number" name="serial_no" value="<?= h($editData['serial_no'] ?? $nextSerial) ?>" required>
            </div>
            <div class="form-group" style="width: 80%;">
                <label>Link Name</label>
                <input type="text" name="link_name" value="<?= h($editData['link_name'] ?? '') ?>" required>
            </div>
        </div>
        <div class="form-group">
            <label>URL (Full Web Address)</label>
            <input type="url" name="url" value="<?= h($editData['url'] ?? '') ?>" placeholder="https://example.com" required>
        </div>
        <button type="submit" class="btn btn-blue"><?= $editData ? 'Update Link' : 'Save Link' ?></button>
        <?php if($editData): ?>
            <a href="manage_links.php" class="btn btn-gray">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h2>All Important Links</h2>
    <table>
        <tr>
            <th>Serial</th>
            <th>Link Name</th>
            <th>URL</th>
            <th>Action</th>
        </tr>
        <?php if ($links && $links->num_rows > 0): ?>
            <?php while($row = $links->fetch_assoc()): ?>
                <tr>
                    <td style="text-align: center;"><b><?= $row['serial_no'] ?></b></td>
                    <td><?= h($row['link_name']) ?></td>
                    <td><a href="<?= h($row['url']) ?>" target="_blank"><?= h($row['url']) ?></a></td>
                    <td>
                        <a href="manage_links.php?edit=<?= $row['id'] ?>" class="btn btn-blue" style="padding: 4px 8px; font-size: 12px;">Edit</a>
                        <a href="manage_links.php?delete=<?= $row['id'] ?>" class="btn btn-red" style="padding: 4px 8px; font-size: 12px;" onclick="return confirm('Delete this link?')">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4" style="text-align:center;">No links found.</td></tr>
        <?php endif; ?>
    </table>
</div>

</body>
</html>