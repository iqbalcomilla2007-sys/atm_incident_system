<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('manage_users');

if (!Auth::isAdmin()) {
    die("Access Denied");
}

$message = '';
$user_id = (int)($_GET['id'] ?? 0);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getTargetUserBasicInfo($conn, $userId) {
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.full_name, r.role_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->fetch_assoc();
}


$targetUser = getTargetUserBasicInfo($conn, $user_id);

if (!$targetUser) {
    die("User not found.");
}

if (!Auth::canManageRoleName($targetUser['role_name'] ?? '')) {
    die("Access Denied");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if ($new_password === '' || $confirm_password === '') {
        $message = "Both password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $message = "New password and confirm password do not match.";
    } elseif (strlen($new_password) < 4) {
        $message = "New password must be at least 4 characters.";
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("si", $hash, $user_id);

        if ($stmt->execute()) {
            $message = "Password reset successful for user: " . ($targetUser['username'] ?? '');
        } else {
            $message = "Password reset failed.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reset User Password</title>
    <link rel="stylesheet" href="style.css?v=4">
</head>
<body>

<div class="container">

    <div class="hero-header">
        <div>
            <h1>Reset User Password</h1>
            <p>Set a new password for selected user</p>
        </div>

        <div class="hero-actions"></div>
    </div>

    <?php if ($message !== '') { ?>
        <div class="form-card">
            <strong><?php echo h($message); ?></strong>
        </div>
    <?php } ?>

    <div class="form-card">
        <p><strong>Username:</strong> <?php echo h($targetUser['username'] ?? ''); ?></p>
        <p><strong>Full Name:</strong> <?php echo h($targetUser['full_name'] ?? ''); ?></p>
        <p><strong>Role:</strong> <?php echo h($targetUser['role_name'] ?? ''); ?></p>
    </div>

    <div class="form-card">
        <form method="POST">
            <div class="form-grid">
                <div class="full-width">
                    <label>New Password</label>
                    <input type="password" name="new_password" required>
                </div>

                <div class="full-width">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>
                </div>

                <div class="full-width">
                    <button class="btn" type="submit">Reset Password</button>
                </div>
            </div>
        </form>
    </div>

</div>

</body>
</html>