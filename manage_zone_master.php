<?php
require_once __DIR__ . '/init.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

Auth::requirePermission('manage_atm_master');

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

$edit_id = (int)($_GET['edit_id'] ?? 0);
$delete_id = (int)($_GET['delete_id'] ?? 0);

$zoneObj = new Zone();
$conn = Database::getInstance()->getConnection();

if ($delete_id > 0) {
    if ($zoneObj->deleteZone($delete_id)) {
        header("Location: manage_zone_master.php?msg=" . urlencode("Zone deleted successfully."));
        exit;
    } else {
        $error = "Delete failed.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_zone'])) {
    $result = $zoneObj->saveZone($_POST);
    if ($result['success']) {
        header("Location: manage_zone_master.php?msg=" . urlencode($result['msg']));
        exit;
    } else {
        $error = "Error: " . $result['error'];
    }
}

$editRow = [
    'id' => 0,
    'zone_name' => '',
    'active_status' => 1
];

if ($edit_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM zone_master WHERE id = ? LIMIT 1");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    if ($row) {
        $editRow = $row;
    }
}

$list = $conn->query("SELECT * FROM zone_master ORDER BY zone_name ASC");
if (!$list) {
    die("Query failed: " . $conn->error);
}

$message = $_GET['msg'] ?? $message;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manage Zone Master</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        @media (max-width: 768px) {
            .form-grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">

    <div class="hero-header">
        <div>
            <h1>Manage Zone Master</h1>
            <p>Add, update and control zone list</p>
        </div>
        <div class="hero-actions"></div>
    </div>

    <?php if ($message !== '') { ?>
        <div class="form-card"><strong style="color:green;"><?php echo h($message); ?></strong></div>
    <?php } ?>

    <?php if ($error !== '') { ?>
        <div class="form-card"><strong style="color:red;"><?php echo h($error); ?></strong></div>
    <?php } ?>

    <div class="form-card">
        <h2><?php echo $editRow['id'] > 0 ? 'Edit Zone' : 'Add New Zone'; ?></h2>
        <form method="POST">
            <input type="hidden" name="id" value="<?php echo (int)$editRow['id']; ?>">
            <div class="form-grid-2">
                <div>
                    <label>Zone Name</label>
                    <input type="text" name="zone_name" value="<?php echo h($editRow['zone_name']); ?>" required>
                </div>
                <div>
                    <label>Active Status</label>
                    <select name="active_status">
                        <option value="1" <?php echo ((int)$editRow['active_status'] === 1 ? 'selected' : ''); ?>>Active</option>
                        <option value="0" <?php echo ((int)$editRow['active_status'] === 0 ? 'selected' : ''); ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div style="margin-top:14px;">
                <button type="submit" name="save_zone" class="btn">Save Zone</button>
                <a class="btn btn-secondary" href="manage_zone_master.php">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-card">
        <h2>Zone List</h2>
        <div class="table-wrap">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Zone Name</th>
                        <th>Active Status</th>
                        <th>Created At</th>
                        <th>Updated At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $list->fetch_assoc()) { ?>
                        <tr>
                            <td><?php echo h($row['id']); ?></td>
                            <td><?php echo h($row['zone_name']); ?></td>
                            <td><?php echo ((int)$row['active_status'] === 1 ? 'Active' : 'Inactive'); ?></td>
                            <td><?php echo h($row['created_at']); ?></td>
                            <td><?php echo h($row['updated_at']); ?></td>
                            <td class="action-cell">
                                <a class="btn btn-summary" href="manage_zone_master.php?edit_id=<?php echo (int)$row['id']; ?>">Edit</a>
                                <a class="link-btn delete-btn"
                                   href="manage_zone_master.php?delete_id=<?php echo (int)$row['id']; ?>"
                                   onclick="return confirm('Do you want to delete this zone?');">
                                   Delete
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
<?php include 'includes/auto_logout.php'; ?>
</body>
</html>