<?php
session_start();
date_default_timezone_set('Asia/Dhaka');

/*
|--------------------------------------------------------------------------
| EMERGENCY PHP FILE REPLACER
| Use only in local/LAN emergency maintenance.
|--------------------------------------------------------------------------
*/

$PASSWORD = 'Jannatmay@2026'; // change this password

$PROJECT_ROOT = realpath(__DIR__);
$BACKUP_DIR   = $PROJECT_ROOT . DIRECTORY_SEPARATOR . '_file_replace_backups';

if (!is_dir($BACKUP_DIR)) {
    mkdir($BACKUP_DIR, 0777, true);
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function get_client_ip() {
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function is_allowed_ip() {
    $ip = get_client_ip();

    // Localhost
    if ($ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost') {
        return true;
    }

    // IPv6 mapped IPv4, example: ::ffff:192.168.1.10
    if (strpos($ip, '::ffff:') === 0) {
        $ip = substr($ip, 7);
    }

    // Private LAN ranges
    if (preg_match('/^192\.168\.\d+\.\d+$/', $ip)) {
        return true;
    }

    if (preg_match('/^10\.\d+\.\d+\.\d+$/', $ip)) {
        return true;
    }

    if (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\.\d+\.\d+$/', $ip)) {
        return true;
    }

    return false;
}

function safe_relative_path($path) {
    $path = str_replace('\\', '/', trim($path));
    $path = ltrim($path, '/');

    if ($path === '') return false;
    if (strpos($path, '..') !== false) return false;
    if (!preg_match('/\.php$/i', $path)) return false;

    return $path;
}

$message = '';
$error = '';

if (!is_allowed_ip()) {
    die('Access denied. This tool is allowed only from localhost/LAN. Your IP: ' . h(get_client_ip()));
}

/* Logout */
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: emergency_file_replace.php');
    exit;
}

/* Login */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_password'])) {
    if ($_POST['login_password'] === $PASSWORD) {
        $_SESSION['file_replace_auth'] = true;
        header('Location: emergency_file_replace.php');
        exit;
    } else {
        $error = 'Wrong password.';
    }
}

/* Replace file */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['replace_file']) && !empty($_SESSION['file_replace_auth'])) {
    $relative = safe_relative_path($_POST['target_file'] ?? '');

    if (!$relative) {
        $error = 'Invalid file path. Only .php files inside project folder are allowed.';
    } elseif (!isset($_FILES['new_file']) || $_FILES['new_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a valid PHP file.';
    } else {
        $targetPath = realpath($PROJECT_ROOT . DIRECTORY_SEPARATOR . $relative);

        if ($targetPath === false || strpos($targetPath, $PROJECT_ROOT) !== 0) {
            $error = 'Target file not found inside project folder.';
        } elseif (!is_file($targetPath)) {
            $error = 'Target is not a file.';
        } elseif (strtolower(pathinfo($targetPath, PATHINFO_EXTENSION)) !== 'php') {
            $error = 'Only PHP files can be replaced.';
        } else {
            $uploadedName = $_FILES['new_file']['name'];
            $uploadedTmp  = $_FILES['new_file']['tmp_name'];

            if (strtolower(pathinfo($uploadedName, PATHINFO_EXTENSION)) !== 'php') {
                $error = 'Uploaded file must be a .php file.';
            } else {
                $backupName = str_replace(['/', '\\', ':'], '_', $relative);
                $backupName = date('Ymd_His') . '_' . $backupName . '.bak';
                $backupPath = $BACKUP_DIR . DIRECTORY_SEPARATOR . $backupName;

                if (!copy($targetPath, $backupPath)) {
                    $error = 'Backup failed. File was not replaced.';
                } elseif (!move_uploaded_file($uploadedTmp, $targetPath)) {
                    copy($backupPath, $targetPath);
                    $error = 'Upload failed. Original file restored.';
                } else {
                    $message = 'File replaced successfully. Backup created: _file_replace_backups/' . $backupName;
                }
            }
        }
    }
}

/* Load file content */
$fileContent = '';
$currentFile = '';

if (!empty($_SESSION['file_replace_auth']) && isset($_GET['file'])) {
    $relative = safe_relative_path($_GET['file']);

    if ($relative) {
        $targetPath = realpath($PROJECT_ROOT . DIRECTORY_SEPARATOR . $relative);

        if ($targetPath && strpos($targetPath, $PROJECT_ROOT) === 0 && is_file($targetPath)) {
            $currentFile = $relative;
            $fileContent = file_get_contents($targetPath);
        }
    }
}

/* Build PHP file list */
$phpFiles = [];

if (!empty($_SESSION['file_replace_auth'])) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($PROJECT_ROOT, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $fullPath = $file->getPathname();

            if (strpos($fullPath, $BACKUP_DIR) === 0) {
                continue;
            }

            $relativePath = str_replace($PROJECT_ROOT . DIRECTORY_SEPARATOR, '', $fullPath);
            $relativePath = str_replace('\\', '/', $relativePath);
            $phpFiles[] = $relativePath;
        }
    }

    sort($phpFiles);
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Emergency PHP File Replacer</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 25px;
        }
        .box {
            max-width: 1100px;
            margin: auto;
            background: #fff;
            padding: 22px;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.12);
        }
        h2 {
            margin-top: 0;
            color: #222;
        }
        label {
            font-weight: bold;
            display: block;
            margin-top: 12px;
        }
        input[type="password"],
        input[type="file"],
        select {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            box-sizing: border-box;
        }
        button {
            margin-top: 15px;
            padding: 10px 18px;
            background: #0b5ed7;
            border: none;
            color: #fff;
            cursor: pointer;
            border-radius: 5px;
            font-weight: bold;
        }
        button:hover {
            background: #084298;
        }
        .danger {
            background: #dc3545;
        }
        .danger:hover {
            background: #b02a37;
        }
        .msg {
            padding: 12px;
            background: #d1e7dd;
            color: #0f5132;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .err {
            padding: 12px;
            background: #f8d7da;
            color: #842029;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        textarea {
            width: 100%;
            height: 420px;
            margin-top: 10px;
            font-family: Consolas, monospace;
            font-size: 13px;
            padding: 10px;
            box-sizing: border-box;
            background: #111;
            color: #eee;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .warning {
            background: #fff3cd;
            color: #664d03;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .ipbox {
            font-size: 12px;
            color: #555;
            margin-bottom: 12px;
        }
        a {
            color: #0b5ed7;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="box">

<div class="ipbox">
    Your IP: <b><?= h(get_client_ip()) ?></b>
</div>

<?php if (empty($_SESSION['file_replace_auth'])): ?>

    <h2>Emergency PHP File Replacer Login</h2>

    <?php if ($error): ?>
        <div class="err"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <label>Password</label>
        <input type="password" name="login_password" required>
        <button type="submit">Login</button>
    </form>

<?php else: ?>

    <div class="topbar">
        <h2>Emergency PHP File Replacer</h2>
        <a href="?logout=1">Logout</a>
    </div>

    <div class="warning">
        Use this only for emergency LAN maintenance. Before replacing, the current file will be backed up automatically.
    </div>

    <?php if ($message): ?>
        <div class="msg"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="err"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" onsubmit="return confirm('Are you sure you want to replace this PHP file? Backup will be created first.');">
        <input type="hidden" name="replace_file" value="1">

        <label>Select Existing PHP File to Replace</label>
        <select name="target_file" required onchange="if(this.value){window.location='?file=' + encodeURIComponent(this.value);}">
            <option value="">-- Select PHP File --</option>
            <?php foreach ($phpFiles as $file): ?>
                <option value="<?= h($file) ?>" <?= ($file === $currentFile ? 'selected' : '') ?>>
                    <?= h($file) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Upload New PHP File</label>
        <input type="file" name="new_file" accept=".php" required>

        <button type="submit" class="danger">Replace Selected File</button>
    </form>

    <?php if ($currentFile): ?>
        <hr>
        <h3>Current File Preview: <?= h($currentFile) ?></h3>
        <textarea readonly><?= h($fileContent) ?></textarea>
    <?php endif; ?>

<?php endif; ?>

</div>

</body>
</html>