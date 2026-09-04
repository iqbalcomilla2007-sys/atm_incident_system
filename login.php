<?php
require_once __DIR__ . '/init.php';

$error = '';

if (Auth::isLoggedIn()) {
    header("Location: dashboard_ajax_v2.php");
    exit;
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = "Username and password are required.";
    } else {
        $result = Auth::login($username, $password);
        if ($result['success']) {
            header("Location: dashboard_ajax_v2.php");
            exit;
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ATM Incident System</title>
    <style>
        /* আধুনিক সিএসএস ডিজাইন */
        * { box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        body {
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); /* সুন্দর ব্লু গ্রেডিয়েন্ট */
        }

        .login-wrap {
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }

        .form-card {
            background: #ffffff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        .login-title h1 {
            margin: 0;
            font-size: 24px;
            color: #1e3a8a;
            letter-spacing: -1px;
        }

        .login-title p {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .error-msg {
            background: #fee2e2;
            color: #dc2626;
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            border: 1px solid #fecaca;
        }

        .form-group {
            text-align: left;
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            outline: none;
            transition: all 0.3s;
            font-size: 15px;
        }

        .form-group input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: #1e3a8a;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 10px;
        }

        .btn-login:hover {
            background: #1e40af;
        }

        .footer-text {
            margin-top: 25px;
            font-size: 12px;
            color: #9ca3af;
        }
    </style>
</head>
<body>

<div class="login-wrap">
    <div class="form-card">
        <div class="login-title">
            <h1>ATM Incident System</h1>
            <p>Please enter your credentials</p>
        </div>

        <?php if ($error !== '') : ?>
            <div class="error-msg">
                <?php echo h($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter username" required autofocus>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter password" required>
            </div>

            <button type="submit" class="btn-login">Login to Dashboard</button>
        </form>

        <div class="footer-text">
            &copy; <?php echo date('Y'); ?> ATM Management Division. All rights reserved.
        </div>
    </div>
</div>

</body>
</html>