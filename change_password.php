<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    header("Location: login.php");
    exit;
}

$message = '';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    $user_id = (int)($_SESSION['user_id'] ?? 0);

    if ($current_password === '' || $new_password === '' || $confirm_password === '') {
        $message = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $message = "New password and confirm password do not match.";
    } elseif (strlen($new_password) < 4) {
        $message = "New password must be at least 4 characters.";
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();

        if (!$user) {
            $message = "User not found.";
        } else {
            $storedPassword = $user['password'] ?? '';

            $currentOk = false;

            if (password_verify($current_password, $storedPassword)) {
                $currentOk = true;
            } elseif ($current_password === $storedPassword) {
                /* legacy plain-text support */
                $currentOk = true;
            }

            if (!$currentOk) {
                $message = "Current password is incorrect.";
            } else {
                $newHash = password_hash($new_password, PASSWORD_DEFAULT);

                $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                if (!$update) {
                    die("Prepare failed: " . $conn->error);
                }

                $update->bind_param("si", $newHash, $user_id);

                if ($update->execute()) {
                    $message = "Password changed successfully.";
                } else {
                    $message = "Password update failed.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Change Password</title>
    <link rel="stylesheet" href="style.css?v=4">
</head>
<body>

<div class="container">

    <div class="hero-header">
        <div>
            <h1>Change Password</h1>
            <p>Update your login password</p>
        </div>

        <div class="hero-actions"></div>
    </div>

    <?php if ($message !== '') { ?>
        <div class="form-card">
            <strong><?php echo h($message); ?></strong>
        </div>
    <?php } ?>

    <div class="form-card">
        <form method="POST">
            <div class="form-grid">
                <div class="full-width">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>

                <div class="full-width">
                    <label>New Password</label>
                    <input type="password" name="new_password" required>
                </div>

                <div class="full-width">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>
                </div>

                <div class="full-width">
                    <button class="btn" type="submit">Change Password</button>
                </div>
            </div>
        </form>
    </div>

</div>

</body>
</html>