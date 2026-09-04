<?php
session_start();
date_default_timezone_set('Asia/Dhaka');

/*
|--------------------------------------------------------------------------
| Emergency SQL Runner
| Use only in local/LAN emergency maintenance.
|--------------------------------------------------------------------------
*/

$PASSWORD = 'Jannatmay@2026'; // change this

require_once 'db.php';

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

    // Allow private LAN ranges
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

if (!is_allowed_ip()) {
    die('Access denied. This tool is allowed only from localhost/LAN. Your IP: ' . h(get_client_ip()));
}

$message = '';
$error = '';
$resultRows = [];
$resultFields = [];
$sqlInput = '';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: db_emergency_sql.php');
    exit;
}

/* Login */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_password'])) {
    if ($_POST['login_password'] === $PASSWORD) {
        $_SESSION['db_sql_auth'] = true;
        header('Location: db_emergency_sql.php');
        exit;
    } else {
        $error = 'Wrong password.';
    }
}

/* Run SQL */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_sql']) && !empty($_SESSION['db_sql_auth'])) {
    $sqlInput = trim($_POST['sql'] ?? '');

    if ($sqlInput === '') {
        $error = 'SQL command is empty.';
    } else {
        $dangerous = [
            'DROP DATABASE',
            'DROP TABLE',
            'TRUNCATE',
            'DELETE FROM users',
            'UPDATE users SET',
            'GRANT ',
            'REVOKE ',
        ];

        $upperSql = strtoupper($sqlInput);

        foreach ($dangerous as $bad) {
            if (strpos($upperSql, $bad) !== false) {
                $error = 'Blocked dangerous SQL command: ' . $bad;
                break;
            }
        }

        if (!$error) {
            try {
                $res = $conn->query($sqlInput);

                if ($res === true) {
                    $message = 'SQL executed successfully. Affected rows: ' . $conn->affected_rows;
                } elseif ($res instanceof mysqli_result) {
                    while ($field = $res->fetch_field()) {
                        $resultFields[] = $field->name;
                    }

                    while ($row = $res->fetch_assoc()) {
                        $resultRows[] = $row;
                    }

                    $message = 'SELECT query executed. Total rows: ' . count($resultRows);
                    $res->free();
                } else {
                    $error = 'Unknown SQL result.';
                }

                $logFile = __DIR__ . '/db_emergency_sql_log.txt';
                $logText = "[" . date('Y-m-d H:i:s') . "] IP: " . get_client_ip() . "\n";
                $logText .= $sqlInput . "\n\n";
                file_put_contents($logFile, $logText, FILE_APPEND);

            } catch (mysqli_sql_exception $e) {
                $error = 'SQL Error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Emergency SQL Runner</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            padding: 25px;
        }
        .box {
            max-width: 1200px;
            margin: auto;
            background: #fff;
            padding: 22px;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.12);
        }
        h2 {
            margin-top: 0;
        }
        textarea {
            width: 100%;
            height: 180px;
            font-family: Consolas, monospace;
            font-size: 14px;
            padding: 10px;
            box-sizing: border-box;
        }
        input[type="password"] {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
        }
        button {
            margin-top: 12px;
            padding: 10px 18px;
            border: none;
            background: #0b5ed7;
            color: #fff;
            border-radius: 5px;
            cursor: pointer;
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
            background: #d1e7dd;
            color: #0f5132;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .err {
            background: #f8d7da;
            color: #842029;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .warning {
            background: #fff3cd;
            color: #664d03;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
            font-size: 13px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 7px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #e9ecef;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        code {
            background: #eee;
            padding: 2px 5px;
            border-radius: 4px;
        }
        a {
            text-decoration: none;
            color: #0b5ed7;
        }
        .ipbox {
            font-size: 12px;
            color: #555;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="box">

<div class="ipbox">
    Your IP: <b><?= h(get_client_ip()) ?></b>
</div>

<?php if (empty($_SESSION['db_sql_auth'])): ?>

    <h2>Emergency SQL Runner Login</h2>

    <?php if ($error): ?>
        <div class="err"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <label>Password</label><br><br>
        <input type="password" name="login_password" required>
        <button type="submit">Login</button>
    </form>

<?php else: ?>

    <div class="topbar">
        <h2>Emergency SQL Runner</h2>
        <a href="?logout=1">Logout</a>
    </div>

    <div class="warning">
        Use carefully. This tool can change your database. Take backup before running ALTER/UPDATE/DELETE queries.
    </div>

    <?php if ($message): ?>
        <div class="msg"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="err"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" onsubmit="return confirm('Are you sure you want to run this SQL?');">
        <input type="hidden" name="run_sql" value="1">

        <label><b>SQL Command</b></label>
        <textarea name="sql" required><?= h($sqlInput) ?></textarea>

        <button type="submit" class="danger">Run SQL</button>
    </form>

    <h3>Example Commands</h3>

    <p><b>Show tables:</b></p>
    <code>SHOW TABLES;</code>

    <p><b>Check table structure:</b></p>
    <code>DESCRIBE atm_update;</code>

    <p><b>Add missing column:</b></p>
    <code>ALTER TABLE atm_update ADD COLUMN last_modified_at DATETIME NULL;</code>

    <p><b>Update permission:</b></p>
    <code>INSERT INTO permissions (permission_name) SELECT 'cctv_dashboard' WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_name='cctv_dashboard');</code>

    <?php if (!empty($resultFields)): ?>
        <h3>Query Result</h3>

        <table>
            <thead>
                <tr>
                    <?php foreach ($resultFields as $field): ?>
                        <th><?= h($field) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resultRows as $row): ?>
                    <tr>
                        <?php foreach ($resultFields as $field): ?>
                            <td><?= h($row[$field] ?? '') ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($resultRows)): ?>
                    <tr>
                        <td colspan="<?= count($resultFields) ?>">No rows found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

<?php endif; ?>

</div>
</body>
</html>