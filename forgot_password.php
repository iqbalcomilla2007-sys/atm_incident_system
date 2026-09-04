<?php
include 'db.php';

$message = "";
$reset_link = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);

    if ($email === '') {
        $message = "Email is required.";
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime("+30 minutes"));

            $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $stmt->bind_param("ssi", $token, $expires, $user['id']);

            if ($stmt->execute()) {
                $reset_link = "http://localhost/atm_incident/reset_password.php?token=" . $token;
                $message = "Password recovery link generated successfully.";
            } else {
                $message = "Failed to generate recovery link.";
            }
        } else {
            $message = "No user found with this email.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <div class="hero-header">
        <div>
            <h1>Forgot Password</h1>
            <p>Enter your email to recover your password</p>
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
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>

                <div class="full-width">
                    <button type="submit" class="btn">Generate Recovery Link</button>
                </div>
            </div>
        </form>

        <?php if ($reset_link !== '') { ?>
            <hr>
            <p><strong>Test Recovery Link:</strong></p>
            <p><a href="<?php echo htmlspecialchars($reset_link); ?>"><?php echo htmlspecialchars($reset_link); ?></a></p>
        <?php } ?>
    </div>
</div>
</body>
</html>