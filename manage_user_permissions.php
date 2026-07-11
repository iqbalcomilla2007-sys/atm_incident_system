<?php
date_default_timezone_set('Asia/Dhaka');
include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('manage_users');
if (!Auth::isAdmin()) { die("Access Denied"); }

$targetUserId = (int)($_GET['id'] ?? 0);
if ($targetUserId <= 0) { die("Invalid User ID"); }

// Fetch User Info
$userRes = $conn->query("SELECT * FROM users WHERE id = $targetUserId");
$userData = $userRes->fetch_assoc();
if (!$userData) { die("User not found"); }

$message = '';
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Save Individual Permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user_permissions'])) {
    $permissionIds = $_POST['permission_ids'] ?? [];
    $blockedIds = $_POST['blocked_ids'] ?? [];
    
    // Save Allow Overrides
    $conn->query("DELETE FROM user_permissions WHERE user_id = $targetUserId");
    if (!empty($permissionIds)) {
        $stmt = $conn->prepare("INSERT INTO user_permissions (user_id, permission_id) VALUES (?, ?)");
        foreach ($permissionIds as $pId) {
            $pId = (int)$pId;
            $stmt->bind_param("ii", $targetUserId, $pId);
            $stmt->execute();
        }
    }

    // Save Block Overrides
    $conn->query("DELETE FROM user_excluded_permissions WHERE user_id = $targetUserId");
    if (!empty($blockedIds)) {
        $stmtEx = $conn->prepare("INSERT INTO user_excluded_permissions (user_id, permission_id) VALUES (?, ?)");
        foreach ($blockedIds as $pbId) {
            $pbId = (int)$pbId;
            $stmtEx->bind_param("ii", $targetUserId, $pbId);
            $stmtEx->execute();
        }
    }
    $message = "User permissions updated successfully.";
}

// Fetch all permissions
$allPerms = [];
$resP = $conn->query("SELECT id, permission_name FROM permissions ORDER BY permission_name ASC");
while($p = $resP->fetch_assoc()) { $allPerms[] = $p; }

// Fetch assigned permissions for this user (Individual Allow)
$assigned = [];
$resA = $conn->query("SELECT permission_id FROM user_permissions WHERE user_id = $targetUserId");
while($a = $resA->fetch_assoc()) { $assigned[] = (int)$a['permission_id']; }

// Fetch blocked permissions for this user (Individual Block)
$blocked = [];
$resB = $conn->query("SELECT permission_id FROM user_excluded_permissions WHERE user_id = $targetUserId");
while($b = $resB->fetch_assoc()) { $blocked[] = (int)$b['permission_id']; }

// Fetch Role Permissions for display/reference
$rolePerms = [];
$roleId = (int)$userData['role_id'];
$resR = $conn->query("SELECT permission_id FROM role_permissions WHERE role_id = $roleId");
while($r = $resR->fetch_assoc()) { $rolePerms[] = (int)$r['permission_id']; }

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manage User Permissions</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 1000px; margin: auto; padding: 20px; }
        .card { background:#fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .perm-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .perm-table th, .perm-table td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
        .perm-table tr:hover { background: #f8f9fa; }
        .role-perm { color: #28a745; font-weight: bold; font-size: 11px; }
        .block-label { color: #dc3545; font-weight: bold; }
        .allow-label { color: #007bff; font-weight: bold; }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2>Individual Overrides for <?= h($userData['full_name']) ?></h2>
        <a href="manage_users.php" class="btn btn-secondary">Back to Users</a>
    </div>
    
    <div class="card">
        <h3>User: <?= h($userData['full_name']) ?> (<?= h($userData['username']) ?>)</h3>
        <p>Current Role: <b><?= h(getRoleNameById($conn, $userData['role_id'])) ?></b></p>
        <p class="muted-text">
            <b>Allow:</b> Manually grant a permission not in the role.<br>
            <b>Block:</b> Manually <b>REMOVE</b> a permission granted by the role or global settings. (Highest Priority)
        </p>

        <?php if ($message): ?>
            <div style="padding:10px; background:#e8fff0; color:#155724; border:1px solid #c3e6cb; margin-bottom:15px;"><?= $message ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="save_user_permissions" value="1">
            <table class="perm-table">
                <thead>
                    <tr>
                        <th>Permission Name</th>
                        <th style="text-align:center;">Action: Allow</th>
                        <th style="text-align:center;">Action: Block</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($allPerms as $perm): ?>
                    <?php 
                    $pId = (int)$perm['id'];
                    $isFromRole = in_array($pId, $rolePerms);
                    $isIndividualAllow = in_array($pId, $assigned);
                    $isIndividualBlock = in_array($pId, $blocked);
                    ?>
                    <tr>
                        <td><?= h($perm['permission_name']) ?></td>
                        <td style="text-align:center;">
                            <input type="checkbox" name="permission_ids[]" value="<?= $pId ?>" <?= $isIndividualAllow ? 'checked' : '' ?>>
                        </td>
                        <td style="text-align:center;">
                            <input type="checkbox" name="blocked_ids[]" value="<?= $pId ?>" <?= $isIndividualBlock ? 'checked' : '' ?>>
                        </td>
                        <td>
                            <?php if ($isFromRole): ?>
                                <span class="role-perm">From Role</span>
                            <?php endif; ?>
                            <?php if ($isIndividualBlock): ?>
                                <div class="block-label">Blocked Individually</div>
                            <?php elseif ($isIndividualAllow): ?>
                                <div class="allow-label">Allowed Individually</div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-blue">Update Overrides</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
