<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('manage_penalty');

$message = '';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* ADD */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_rule'])) {
    $vendor_type = trim($_POST['vendor_type'] ?? '');
    $from_minute = (int)($_POST['from_minute'] ?? 0);
    $to_minute = (int)($_POST['to_minute'] ?? 0);
    $penalty_percent = (float)($_POST['penalty_percent'] ?? 0);

    $stmt = $conn->prepare("
        INSERT INTO vendor_penalty_rules (vendor_type, from_minute, to_minute, penalty_percent)
        VALUES (?, ?, ?, ?)
    ");
    if (!$stmt) die("Prepare failed: " . $conn->error);

    $stmt->bind_param("siid", $vendor_type, $from_minute, $to_minute, $penalty_percent);

    if ($stmt->execute()) {
        header("Location: vendor_penalty_rules.php?success=1");
        exit;
    } else {
        $message = "Insert failed: " . $stmt->error;
    }
}

/* UPDATE */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_rule'])) {
    $id = (int)($_POST['id'] ?? 0);
    $vendor_type = trim($_POST['vendor_type'] ?? '');
    $from_minute = (int)($_POST['from_minute'] ?? 0);
    $to_minute = (int)($_POST['to_minute'] ?? 0);
    $penalty_percent = (float)($_POST['penalty_percent'] ?? 0);
    $active_status = isset($_POST['active_status']) ? 1 : 0;

    $stmt = $conn->prepare("
        UPDATE vendor_penalty_rules
        SET vendor_type = ?, from_minute = ?, to_minute = ?, penalty_percent = ?, active_status = ?
        WHERE id = ?
    ");
    if (!$stmt) die("Prepare failed: " . $conn->error);

    $stmt->bind_param("siidii", $vendor_type, $from_minute, $to_minute, $penalty_percent, $active_status, $id);

    if ($stmt->execute()) {
        header("Location: vendor_penalty_rules.php?updated=1");
        exit;
    } else {
        $message = "Update failed: " . $stmt->error;
    }
}

/* DELETE */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM vendor_penalty_rules WHERE id = ?");
    if (!$stmt) die("Prepare failed: " . $conn->error);

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: vendor_penalty_rules.php?deleted=1");
        exit;
    } else {
        $message = "Delete failed: " . $stmt->error;
    }
}

$result = $conn->query("
    SELECT *
    FROM vendor_penalty_rules
    ORDER BY vendor_type ASC, from_minute ASC
");
if (!$result) die("Query failed: " . $conn->error);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Vendor Penalty Rules</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">

    <div class="hero-header">
        <div>
            <h1>Vendor Penalty Rules</h1>
            <p>Manage running penalty slab rules</p>
        </div>
        <div class="hero-actions">
            <a class="btn btn-secondary" href="ignored_penalties.php">Ignored Penalties</a>

            <?php if (Auth::hasPermission('manage_problem_master')) { ?>

            <?php } ?>
</div>
    </div>

    <?php if ($message !== '') { ?>
        <div class="form-card"><strong><?php echo h($message); ?></strong></div>
    <?php } ?>
    <?php if (isset($_GET['success'])) { ?>
        <div class="form-card"><strong style="color:green;">Penalty rule added successfully.</strong></div>
    <?php } ?>
    <?php if (isset($_GET['updated'])) { ?>
        <div class="form-card"><strong style="color:green;">Penalty rule updated successfully.</strong></div>
    <?php } ?>
    <?php if (isset($_GET['deleted'])) { ?>
        <div class="form-card"><strong style="color:green;">Penalty rule deleted successfully.</strong></div>
    <?php } ?>

    <div class="form-card">
        <h2>Add Penalty Rule</h2>
        <form method="POST">
            <input type="hidden" name="add_rule" value="1">
            <div class="form-grid">
                <div>
                    <label>Vendor Type</label>
                    <select name="vendor_type" required>
                        <option value="ATM">ATM</option>
                        <option value="UPS">UPS</option>
                    </select>
                </div>
                <div>
                    <label>From Minute</label>
                    <input type="number" name="from_minute" required>
                </div>
                <div>
                    <label>To Minute</label>
                    <input type="number" name="to_minute" required>
                </div>
                <div>
                    <label>Penalty Percent</label>
                    <input type="number" step="0.01" name="penalty_percent" required>
                </div>
                <div class="full-width">
                    <button type="submit" class="btn">Add Rule</button>
                </div>
            </div>
        </form>
    </div>

    <div class="table-card">
        <h2>Penalty Rule List</h2>
        <div class="table-wrap">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Vendor Type</th>
                        <th>From Minute</th>
                        <th>To Minute</th>
                        <th>Penalty %</th>
                        <th>Active</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()) { ?>
                        <tr>
                            <form method="POST">
                                <input type="hidden" name="update_rule" value="1">
                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">

                                <td><?php echo (int)$row['id']; ?></td>
                                <td>
                                    <select name="vendor_type">
                                        <option value="ATM" <?php if ($row['vendor_type'] === 'ATM') echo 'selected'; ?>>ATM</option>
                                        <option value="UPS" <?php if ($row['vendor_type'] === 'UPS') echo 'selected'; ?>>UPS</option>
                                    </select>
                                </td>
                                <td><input type="number" name="from_minute" value="<?php echo h($row['from_minute']); ?>" required></td>
                                <td><input type="number" name="to_minute" value="<?php echo h($row['to_minute']); ?>" required></td>
                                <td><input type="number" step="0.01" name="penalty_percent" value="<?php echo h($row['penalty_percent']); ?>" required></td>
                                <td style="text-align:center;">
                                    <input type="checkbox" name="active_status" <?php if ((int)$row['active_status'] === 1) echo 'checked'; ?>>
                                </td>
                                <td class="action-cell">
                                    <button type="submit" class="btn">Update</button>
                                    <a class="link-btn delete-btn" href="vendor_penalty_rules.php?delete=<?php echo (int)$row['id']; ?>" onclick="return confirm('Delete this penalty rule?')">Delete</a>
                                </td>
                            </form>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
