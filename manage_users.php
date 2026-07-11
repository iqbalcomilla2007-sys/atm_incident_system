<?php
require_once __DIR__ . '/init.php';

Auth::requirePermission('manage_users');

if (!Auth::isAdmin()) {
    die("Access Denied");
}

$message = '';
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$userObj = new User();
$roleObj = new Role();
$conn = Database::getInstance()->getConnection();

/* ---------- ROLES ---------- */
$roles = [];
$resRoles = $roleObj->getAll();
if ($resRoles) {
    while ($r = $resRoles->fetch_assoc()) {
        if (!Auth::isSuperAdmin() && !in_array($r['role_name'], ['Operator', 'Viewer'], true)) {
            continue;
        }
        $roles[] = $r;
    }
}

/* ---------- ZONES ---------- */
$zones = [];
$resZones = $conn->query("
    SELECT DISTINCT zone_name
    FROM atm_master
    WHERE zone_name IS NOT NULL AND zone_name <> ''
    ORDER BY zone_name ASC
");
if ($resZones) {
    while ($z = $resZones->fetch_assoc()) {
        $zones[] = $z['zone_name'];
    }
}

/* ---------- ADD USER ---------- */
if (isset($_POST['add_user'])) {
    $result = $userObj->create($_POST);
    if ($result['success']) {
        header("Location: manage_users.php?success=1");
        exit;
    } else {
        $message = "Insert failed: " . ($result['error'] ?? 'Unknown error');
    }
}

/* ---------- UPDATE USER ---------- */
if (isset($_POST['update_user'])) {
    $id = (int)($_POST['user_id'] ?? 0);
    $result = $userObj->update($id, $_POST);
    if ($result['success']) {
        header("Location: manage_users.php?updated=1");
        exit;
    } else {
        $message = "Update failed: " . ($result['error'] ?? 'Unknown error');
    }
}

/* ---------- DELETE USER ---------- */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $result = $userObj->delete($id);
    if ($result['success']) {
        header("Location: manage_users.php?deleted=1");
        exit;
    } else {
        $message = "Delete failed: " . ($result['error'] ?? 'Unknown error');
    }
}

/* ---------- USERS ---------- */
$users = $userObj->getUsersList();
if (!$users) {
    die("Users query failed.");
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Manage Users</title>
<link rel="stylesheet" href="style.css?v=4">
<style>
.card {background:#fff;padding:20px;margin-bottom:20px;border-radius:10px}
.grid {display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.table {width:100%;border-collapse:collapse}
.table th,.table td {border:1px solid #ddd;padding:6px;vertical-align:top}
.table th {background:#f4f4f4}
input,select {width:100%;padding:6px;box-sizing:border-box}
.muted-text {color:#777;font-size:12px}
@media (max-width: 900px) {
  .grid {grid-template-columns:1fr}
}
</style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container">

    <div class="hero-header">
        <div>
            <h1>Manage Users</h1>
            <p>Create users, assign roles and reset password</p>
        </div>

        <div class="hero-actions">
            <span style="color:#fff; font-weight:bold; align-self:center;">
                Welcome, <?php echo h($_SESSION['full_name'] ?? 'User'); ?>
                <?php if (!empty($_SESSION['role_name'])) { ?>
                    (<?php echo h($_SESSION['role_name']); ?>)
                <?php } ?>
                <?php if (!empty($_SESSION['assigned_zone'])) { ?>
                    - <?php echo h($_SESSION['assigned_zone']); ?>
                <?php } ?>
            </span>

            <?php if (Auth::hasPermission('view_history')) { ?>

            <?php } ?>

            <?php if (Auth::hasPermission('add_incident')) { ?>

            <?php } ?>

            <?php if (Auth::hasPermission('manage_problem_master')) { ?>

            <?php } ?>

            <?php if (Auth::hasPermission('manage_atm_master')) { ?>

            <?php } ?>

            <?php if (Auth::hasPermission('manage_users')) { ?>

            <?php } ?>

            <?php if (Auth::isSuperAdmin()) { ?>

            <?php } ?>

            <?php if (Auth::hasPermission('manage_penalty')) { ?>

            <?php } ?>

<a href="backup_database.php" class="btn btn-success btn-sm"
   onclick="return confirm('Do you want to download full database backup?');">
    Full Database Backup
</a>

            <?php if (Auth::hasPermission('export_all_php')) { ?>
                <a class="btn btn-secondary" href="backup_project_zip.php">Full PHP Backup</a>
            <?php } ?>

<?php if (Auth::hasPermission('emergency_file_replace')) { ?>
                <a class="btn btn-secondary" href="emergency_file_replace.php">PHP file Edit</a>
            <?php } ?>

<?php if (Auth::hasPermission('db_emergency_sql')) { ?>
                <a class="btn btn-secondary" href="db_emergency_sql.php">Database Edit</a>
            <?php } ?>
</div>
    </div>

    <?php if ($message !== '') { ?>
        <div class="card"><b><?php echo h($message); ?></b></div>
    <?php } ?>

    <?php if (isset($_GET['success'])) { ?>
        <div class="card"><b style="color:green;">User added successfully.</b></div>
    <?php } ?>

    <?php if (isset($_GET['updated'])) { ?>
        <div class="card"><b style="color:green;">User updated successfully.</b></div>
    <?php } ?>

    <?php if (isset($_GET['deleted'])) { ?>
        <div class="card"><b style="color:green;">User deleted successfully.</b></div>
    <?php } ?>

    <div class="card">
        <h3>Add User</h3>

        <form method="POST">
            <input type="hidden" name="add_user" value="1">

            <div class="grid">
                <div>
                    <label>Username</label>
                    <input name="username" placeholder="Username" required>
                </div>

                <div>
                    <label>Full Name</label>
                    <input name="full_name" placeholder="Full Name" required>
                </div>

                <div>
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Password" required>
                </div>

                <div>
                    <label>Role</label>
                    <select name="role_id" required>
                        <option value="">Role</option>
                        <?php foreach ($roles as $r) { ?>
                            <option value="<?php echo (int)$r['id']; ?>"><?php echo h($r['role_name']); ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div>
                    <label>Assigned Zone</label>
                    <select name="assigned_zone">
                        <option value="">All Zone</option>
                        <?php foreach ($zones as $z) { ?>
                            <option value="<?php echo h($z); ?>"><?php echo h($z); ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div>
                    <label>User Type</label>
                    <select name="user_type">
                        <option value="">Default/Shared</option>
                        <option value="Bank">Bank</option>
                        <option value="CMS">CMS</option>
                        <option value="Vendor">Vendor</option>
                    </select>
                </div>
            </div>

            <br>
            <button class="btn" type="submit">Add User</button>
        </form>
    </div>

    <div class="card">
        <h3>User List</h3>

        <table class="table">
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Name</th>
                <th>Role</th>
                <th>Zone</th>
                <th>Type</th>
                <th>Action</th>
            </tr>

            <?php while ($u = $users->fetch_assoc()) { ?>
                <?php
                $targetRoleName = $u['role_name'] ?? '';
                $canManageThisUser = Auth::canManageRoleName($targetRoleName);
                $isCurrentLoggedInUser = ((int)$u['id'] === $currentUserId);
                ?>
                <tr>
                    <form method="POST">
                        <input type="hidden" name="update_user" value="1">
                        <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">

                        <td><?php echo (int)$u['id']; ?></td>

                        <td>
                            <input name="username" value="<?php echo h($u['username']); ?>" <?php if (!$canManageThisUser) echo 'readonly'; ?>>
                        </td>

                        <td>
                            <input name="full_name" value="<?php echo h($u['full_name']); ?>" <?php if (!$canManageThisUser) echo 'readonly'; ?>>
                        </td>

                        <td>
                            <?php if ($canManageThisUser) { ?>
                                <select name="role_id">
                                    <?php foreach ($roles as $r) { ?>
                                        <option value="<?php echo (int)$r['id']; ?>" <?php if ((int)$u['role_id'] === (int)$r['id']) echo 'selected'; ?>>
                                            <?php echo h($r['role_name']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            <?php } else { ?>
                                <input value="<?php echo h($targetRoleName); ?>" readonly>
                                <input type="hidden" name="role_id" value="<?php echo (int)$u['role_id']; ?>">
                            <?php } ?>
                        </td>

                        <td>
                            <?php if ($canManageThisUser) { ?>
                                <select name="assigned_zone">
                                    <option value="">All</option>
                                    <?php foreach ($zones as $z) { ?>
                                        <option value="<?php echo h($z); ?>" <?php if (($u['assigned_zone'] ?? '') === $z) echo 'selected'; ?>>
                                            <?php echo h($z); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            <?php } else { ?>
                                <input value="<?php echo h($u['assigned_zone']); ?>" readonly>
                                <input type="hidden" name="assigned_zone" value="<?php echo h($u['assigned_zone']); ?>">
                            <?php } ?>
                        </td>

                        <td>
                            <?php if ($canManageThisUser) { ?>
                                <select name="user_type">
                                    <option value="" <?php echo ($u['user_type'] ?? '') === '' ? 'selected' : ''; ?>>Default</option>
                                    <option value="Bank" <?php echo ($u['user_type'] ?? '') === 'Bank' ? 'selected' : ''; ?>>Bank</option>
                                    <option value="CMS" <?php echo ($u['user_type'] ?? '') === 'CMS' ? 'selected' : ''; ?>>CMS</option>
                                    <option value="Vendor" <?php echo ($u['user_type'] ?? '') === 'Vendor' ? 'selected' : ''; ?>>Vendor</option>
                                </select>
                            <?php } else { ?>
                                <input value="<?php echo h($u['user_type'] ?? ''); ?>" readonly>
                                <input type="hidden" name="user_type" value="<?php echo h($u['user_type'] ?? ''); ?>">
                            <?php } ?>
                        </td>

                        <td>
                            <?php if ($canManageThisUser) { ?>
                                <button class="btn" type="submit">Update</button>
                                <a class="btn btn-secondary" href="reset_user_password.php?id=<?php echo (int)$u['id']; ?>">Reset</a>
                                <a class="btn btn-blue" style="background:#17a2b8" href="manage_user_permissions.php?id=<?php echo (int)$u['id']; ?>">Permissions</a>
                            <?php } else { ?>
                                <span class="muted-text">Protected</span>
                            <?php } ?>

                            <?php if ($canManageThisUser && !$isCurrentLoggedInUser) { ?>
                                <a class="btn btn-dark"
                                   href="manage_users.php?delete=<?php echo (int)$u['id']; ?>"
                                   onclick="return confirm('Do you want to delete user: <?php echo addslashes($u['username']); ?>?')">
                                   Delete
                                </a>
                            <?php } elseif ($isCurrentLoggedInUser) { ?>
                                <span class="muted-text">Current User</span>
                            <?php } ?>
                        </td>
                    </form>
                </tr>
            <?php } ?>

        </table>
    </div>

</div>

</body>
</html>
