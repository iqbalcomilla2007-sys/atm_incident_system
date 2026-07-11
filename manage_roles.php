<?php
require_once __DIR__ . '/init.php';

Auth::requirePermission('manage_users');
if (!Auth::isAdmin()) { die("Access Denied"); }

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$roleObj = new Role();

/* Save New Role */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_role'])) {
    $roleName = $_POST['role_name'] ?? '';
    if ($roleObj->create($roleName)) {
        header("Location: manage_roles.php?success=1");
        exit;
    }
}

/* Delete Role */
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    $roleObj->delete($delId);
    header("Location: manage_roles.php?deleted=1");
    exit;
}

/* Save Permissions for a Role */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_role_permissions'])) {
    $roleId = (int)$_POST['target_role_id'];
    $permissionIds = $_POST['permission_ids'] ?? [];
    
    if ($roleObj->saveRolePermissions($roleId, $permissionIds)) {
        header("Location: manage_roles.php?permission_saved=1");
        exit;
    }
}

/* Save Global Exclusions */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_global_exclusions'])) {
    $userType = $_POST['target_user_type'] ?? '';
    $excludedPermissionIds = $_POST['excluded_permission_ids'] ?? [];

    if ($roleObj->saveGlobalExclusions($userType, $excludedPermissionIds)) {
        header("Location: manage_roles.php?global_saved=1");
        exit;
    }
}

$roles = $roleObj->getAll();
$permissions = $roleObj->getPermissions();

$allPermissions = [];
while ($p = $permissions->fetch_assoc()) { $allPermissions[] = $p; }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manage Roles & Permissions</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 1000px; margin: auto; padding: 20px; }
        .table-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); margin-top:20px; }
        .form-card { background:#f1f3f5; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .btn-sm { padding: 4px 8px; font-size: 12px; }
        .muted-text { font-size: 13px; color: #666; }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2>System Roles & Permissions</h2>
        <a href="manage_users.php" class="btn btn-secondary">Manage Users</a>
    </div>

    <?php if (isset($_GET['success'])) { ?><div class="form-card"><strong style="color:green;">Role added successfully.</strong></div><?php } ?>
    <?php if (isset($_GET['updated'])) { ?><div class="form-card"><strong style="color:green;">Role updated successfully.</strong></div><?php } ?>
    <?php if (isset($_GET['deleted'])) { ?><div class="form-card"><strong style="color:green;">Role deleted successfully.</strong></div><?php } ?>
    <?php if (isset($_GET['global_saved'])) { ?><div class="form-card"><strong style="color:green;">Global exclusions saved successfully.</strong></div><?php } ?>
    <?php if (isset($_GET['permission_saved'])) { ?><div class="form-card"><strong style="color:green;">Permissions saved successfully.</strong></div><?php } ?>

    <div class="form-card">
        <h3>Add New Role</h3>
        <form method="POST">
            <input type="hidden" name="add_role" value="1">
            <div style="display:flex; gap:10px;">
                <input type="text" name="role_name" placeholder="Role Name (e.g. Sales Manager)" required style="flex:1;">
                <button type="submit" class="btn btn-blue">Create Role</button>
            </div>
        </form>
    </div>

    <div class="table-card">
        <h3>Existing Roles & Role-based Permissions</h3>
        <?php while ($role = $roles->fetch_assoc()) { 
            $roleId = (int)$role['id'];
            $currentPerms = $roleObj->getRolePermissionIds($roleId);
        ?>
            <div class="form-card" style="margin-bottom:20px; border-left: 5px solid #007bff;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <h4>Role: <?php echo h($role['role_name']); ?></h4>
                    <?php if ($role['role_name'] !== 'Super Administrator') { ?>
                        <a href="?delete_id=<?php echo $roleId; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this role?')">Delete Role</a>
                    <?php } ?>
                </div>

                <form method="POST">
                    <input type="hidden" name="save_role_permissions" value="1">
                    <input type="hidden" name="target_role_id" value="<?php echo $roleId; ?>">
                    
                    <div class="form-grid" style="margin-top:10px;">
                        <?php foreach ($allPermissions as $perm) { ?>
                            <div>
                                <label style="display:flex; gap:8px; align-items:center;">
                                    <input type="checkbox" 
                                           name="permission_ids[]" 
                                           value="<?php echo (int)$perm['id']; ?>"
                                           <?php if (in_array((int)$perm['id'], $currentPerms, true)) echo 'checked'; ?>
                                           <?php if ($role['role_name'] === 'Super Administrator') echo 'disabled'; ?>>
                                    <span style="font-size:13px;"><?php echo h($perm['permission_name']); ?></span>
                                </label>
                            </div>
                        <?php } ?>
                    </div>
                    <?php if ($role['role_name'] !== 'Super Administrator') { ?>
                        <div style="margin-top:10px;">
                            <button type="submit" class="btn btn-blue btn-sm">Update Permissions for <?php echo h($role['role_name']); ?></button>
                        </div>
                    <?php } else { ?>
                        <p class="muted-text"><i>Super Administrators possess all system privileges by default.</i></p>
                    <?php } ?>
                </form>
            </div>
        <?php } ?>
    </div>

    <!-- Global Exclusion Section -->
    <div class="table-card">
        <h2>Global Exclusions (Block permissions for specific User types)</h2>
        <p class="muted-text" style="margin-bottom:15px;">Any permission selected below will be <b>BLOCKED</b> globally for all users of that type, regardless of their role or specific permissions.</p>
        
        <?php 
        $userTypes = ['Bank', 'CMS', 'Vendor'];
        foreach($userTypes as $ut):
            $excluded = $roleObj->getExcludedPermissionIds($ut);
        ?>
            <div class="form-card" style="margin-bottom:15px; border-left: 5px solid #dc3545;">
                <form method="POST">
                    <input type="hidden" name="save_global_exclusions" value="1">
                    <input type="hidden" name="target_user_type" value="<?= $ut ?>">
                    
                    <label><strong>Blocked Permissions for Type: <span style="color:#dc3545;"><?= $ut ?></span></strong></label>
                    
                    <div class="form-grid" style="margin-top:10px;">
                        <?php foreach ($allPermissions as $perm) { ?>
                            <div>
                                <label style="display:flex; gap:8px; align-items:center;">
                                    <input type="checkbox"
                                           name="excluded_permission_ids[]"
                                           value="<?php echo (int)$perm['id']; ?>"
                                           <?php if (in_array((int)$perm['id'], $excluded, true)) echo 'checked'; ?>>
                                    <span style="color: #444; font-size:13px;"><?php echo h($perm['permission_name']); ?></span>
                                </label>
                            </div>
                        <?php } ?>
                    </div>
                    
                    <div style="margin-top:10px;">
                        <button type="submit" class="btn btn-dark" style="background:#dc3545; border:none; padding: 5px 15px;">Save Blocked Permissions for <?= $ut ?></button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

</div>
</body>
</html>