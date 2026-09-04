<?php
include 'db.php';
session_start();

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);

    if ($full_name === '' || $username === '' || $password === '') {
        $message = "All fields are required.";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (full_name, username, password, role) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("ssss", $full_name, $username, $hashedPassword, $role);

        if ($stmt->execute()) {
            $message = "User created successfully.";
        } else {
            $message = "Error: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Create User</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <div class="hero-header">
        <div>
            <h1>Create User</h1>
            <p>Create a login account for the system</p>
        </div>
        <div class="hero-actions">
            <a class="btn btn-light" href="login.php">Login</a>
</div>
    </div>

    <div class="form-card">
        <?php if ($message !== '') { ?>
            <p><strong><?php echo htmlspecialchars($message); ?></strong></p>
        <?php } ?>

        <form method="POST">
            <div class="form-grid">
                <div>
                    <label>Full Name</label>
                    <input type="text" name="full_name" required>
                </div>

                <div>
                    <label>Username</label>
                    <input type="text" name="username" required>
                </div>

                <div>
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>

                <div>
                    <label>Role</label>
                    <select name="role">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="full-width">
                    <button type="submit" class="btn">Create User</button>
                </div>
            </div>
        </form>
    </div>
</div>
</body>
</html>