<?php
require_once __DIR__ . '/init.php';

Auth::requirePermission('manage_atm_master');

$zoneObj = new Zone();
$error = '';

/* ADD / UPDATE */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    
    $result = $zoneObj->saveZonalHead($action, $_POST);
    
    if ($result['success']) {
        header("Location: manage_zonal_heads.php");
        exit;
    } else {
        $error = $result['error'];
    }
}

/* DELETE */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $zoneObj->deleteZonalHead($id);
    header("Location: manage_zonal_heads.php");
    exit;
}

/* FETCH */
$result = $zoneObj->getAllZonalHeads();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manage Zonal Heads</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">

    <div class="hero-header">
        <div>
            <h1>Zonal Head Contact Details</h1>
            <p>Manage zonal head contact information</p>
        </div>
        <div class="hero-actions"></div>
    </div>

    <div class="form-card">
        <h2>Add Zonal Head Contact</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add">

            <div class="form-grid">
                <div>
                    <label>Name of Zone</label>
                    <input type="text" name="zone_name" required>
                </div>

                <div>
                    <label>Name of Zonal Head</label>
                    <input type="text" name="zonal_head_name">
                </div>

                <div>
                    <label>Mobile</label>
                    <input type="text" name="mobile">
                </div>

                <div>
                    <label>IP Phone</label>
                    <input type="text" name="ip_phone">
                </div>

                <div class="full-width">
                    <button type="submit" class="btn">Add Contact</button>
                </div>
            </div>
        </form>
    </div>

    <div class="table-card">
        <h2>Zonal Head Contact List</h2>
        <div class="table-wrap">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Zone</th>
                        <th>Zonal Head</th>
                        <th>Mobile</th>
                        <th>IP Phone</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0) { ?>
                        <?php while ($row = $result->fetch_assoc()) { ?>
                            <tr>
                                <form method="POST">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">

                                    <td><?php echo $row['id']; ?></td>
                                    <td><input type="text" name="zone_name" value="<?php echo htmlspecialchars($row['zone_name']); ?>" required></td>
                                    <td><input type="text" name="zonal_head_name" value="<?php echo htmlspecialchars($row['zonal_head_name']); ?>"></td>
                                    <td><input type="text" name="mobile" value="<?php echo htmlspecialchars($row['mobile']); ?>"></td>
                                    <td><input type="text" name="ip_phone" value="<?php echo htmlspecialchars($row['ip_phone']); ?>"></td>
                                    <td class="action-cell">
                                        <button type="submit" class="btn">Update</button>
                                        <a class="link-btn delete-btn"
                                           href="manage_zonal_heads.php?delete=<?php echo $row['id']; ?>"
                                           onclick="return confirm('Delete this zonal head contact?')">Delete</a>
                                    </td>
                                </form>
                            </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="6" class="text-center">No zonal head contact found.</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>