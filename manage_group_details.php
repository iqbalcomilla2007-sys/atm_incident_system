<?php
require_once __DIR__ . '/init.php';

Auth::requirePermission('manage_atm_master');

$masterObj = new MasterData();
$error = '';

/* ADD / UPDATE */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    
    $result = $masterObj->saveGroup($action, $_POST);
    
    if ($result['success']) {
        header("Location: manage_group_details.php");
        exit;
    } else {
        $error = $result['error'];
    }
}

/* DELETE */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $masterObj->deleteGroup($id);
    header("Location: manage_group_details.php");
    exit;
}

/* FETCH */
$result = $masterObj->getAllGroups();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manage Group Details</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">

    <div class="hero-header">
        <div>
            <h1>Manage Group Details</h1>
            <p>Manage group leader, zones and members</p>
        </div>
        <div class="hero-actions">
            <a class="btn btn-secondary" href="manage_zonal_heads.php">Zonal Heads</a>
</div>
    </div>

    <div class="form-card">
        <h2>Add Group Details</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add">

            <div class="form-grid">
                <div>
                    <label>Group No</label>
                    <input type="number" name="group_no" required>
                </div>

                <div>
                    <label>Zones</label>
                    <input type="text" name="zones" placeholder="Example: Dhaka North, Dhaka Central">
                </div>

                <div>
                    <label>Group Leader Name</label>
                    <input type="text" name="group_leader_name">
                </div>

                <div class="full-width">
                    <label>Group Members</label>
                    <textarea name="group_members" rows="3" placeholder="Example: Rahim, Karim, Selim"></textarea>
                </div>

                <div class="full-width">
                    <button type="submit" class="btn">Add Group Details</button>
                </div>
            </div>
        </form>
    </div>

    <div class="table-card">
        <h2>Group Details List</h2>
        <div class="table-wrap">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Group No</th>
                        <th>Zones</th>
                        <th>Group Leader</th>
                        <th>Group Members</th>
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
                                    <td><input type="number" name="group_no" value="<?php echo $row['group_no']; ?>" required></td>
                                    <td><input type="text" name="zones" value="<?php echo htmlspecialchars($row['zones']); ?>"></td>
                                    <td><input type="text" name="group_leader_name" value="<?php echo htmlspecialchars($row['group_leader_name']); ?>"></td>
                                    <td><textarea name="group_members" rows="2"><?php echo htmlspecialchars($row['group_members']); ?></textarea></td>
                                    <td class="action-cell">
                                        <button type="submit" class="btn">Update</button>
                                        <a class="link-btn delete-btn"
                                           href="manage_group_details.php?delete=<?php echo $row['id']; ?>"
                                           onclick="return confirm('Delete this group detail?')">Delete</a>
                                    </td>
                                </form>
                            </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="6" class="text-center">No group details found.</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>