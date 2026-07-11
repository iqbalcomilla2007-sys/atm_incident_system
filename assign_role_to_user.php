<?php
require_once __DIR__ . '/init.php';

Auth::requirePermission('manage_users');

$message = '';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$userObj = new User();
$roleObj = new Role();
$conn = Database::getInstance()->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_role'])) {
    $userId = (int)($_POST['user_id'] ?? 0);
    $roleId = (int)($_POST['role_id'] ?? 0);
    $assignedZone = trim($_POST['assigned_zone'] ?? '');

    $result = $userObj->assignRoleAndZone($userId, $roleId, $assignedZone);
    if ($result['success']) {
        header("Location: assign_role_to_user.php?success=1");
        exit;
    } else {
        $message = "Update failed: " . ($result['error'] ?? 'Unknown error');
    }
}

$roles = $roleObj->getAll();
$roleList = [];
if ($roles) {
    while ($r = $roles->fetch_assoc()) {
        $roleList[] = $r;
    }
}

$zones = $conn->query("
    SELECT DISTINCT zone_name
    FROM atm_master
    WHERE zone_name IS NOT NULL AND zone_name <> ''
    ORDER BY zone_name ASC
");
if (!$zones) die("Zones query failed: " . $conn->error);

$zoneList = [];
while ($z = $zones->fetch_assoc()) {
    $zoneList[] = $z['zone_name'];
}

$users = $userObj->getUsersList();
if (!$users) die("Users query failed: " . $conn->error);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Assign Role to User</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">

    <div class="hero-header">
        <div>
            <h1>Assign Role to User</h1>
            <p>Assign role and zone restriction to users</p>
        </div>
        <div class="hero-actions"></div>
    </div>

    <?php if ($message !== '') { ?>
        <div class="form-card"><strong><?php echo h($message); ?></strong></div>
    <?php } ?>
    <?php if (isset($_GET['success'])) { ?>
        <div class="form-card"><strong style="color:green;">Role assigned successfully.</strong></div>
    <?php } ?>

    <div class="table-card">
        <h2>User Role Assignment</h2>
        <div class="table-wrap">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Current Role</th>
                        <th>Assigned Zone</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users->fetch_assoc()) { ?>
                        <tr>
                            <form method="POST">
                                <input type="hidden" name="assign_role" value="1">
                                <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">

                                <td><?php echo (int)$user['id']; ?></td>
                                <td><?php echo h($user['username']); ?></td>
                                <td><?php echo h($user['full_name']); ?></td>

                                <td>
                                    <select name="role_id" required>
                                        <option value="">Select Role</option>
                                        <?php foreach ($roleList as $role) { ?>
                                            <option value="<?php echo (int)$role['id']; ?>"
                                                <?php if ((int)$user['role_id'] === (int)$role['id']) echo 'selected'; ?>>
                                                <?php echo h($role['role_name']); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </td>

                                <td>
                                    <select name="assigned_zone">
                                        <option value="">All Zones / No Restriction</option>
                                        <?php foreach ($zoneList as $zone) { ?>
                                            <option value="<?php echo h($zone); ?>"
                                                <?php if (($user['assigned_zone'] ?? '') === $zone) echo 'selected'; ?>>
                                                <?php echo h($zone); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </td>

                                <td>
                                    <button type="submit" class="btn">Save</button>
                                </td>
                            </form>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>