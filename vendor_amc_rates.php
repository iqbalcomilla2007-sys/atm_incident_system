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
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_amc'])) {
    $vendor_name = trim($_POST['vendor_name'] ?? '');
    $vendor_type = trim($_POST['vendor_type'] ?? '');
    $amc_amount = (float)($_POST['amc_amount'] ?? 0);

    if ($vendor_name === '' || $vendor_type === '') {
        $message = "Vendor Name and Vendor Type are required.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO vendor_amc_rates (vendor_name, vendor_type, amc_amount)
            VALUES (?, ?, ?)
        ");
        if (!$stmt) die("Prepare failed: " . $conn->error);

        $stmt->bind_param("ssd", $vendor_name, $vendor_type, $amc_amount);

        if ($stmt->execute()) {
            header("Location: vendor_amc_rates.php?success=1");
            exit;
        } else {
            $message = "Insert failed: " . $stmt->error;
        }
    }
}

/* UPDATE */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_amc'])) {
    $id = (int)($_POST['id'] ?? 0);
    $vendor_name = trim($_POST['vendor_name'] ?? '');
    $vendor_type = trim($_POST['vendor_type'] ?? '');
    $amc_amount = (float)($_POST['amc_amount'] ?? 0);
    $active_status = isset($_POST['active_status']) ? 1 : 0;

    $stmt = $conn->prepare("
        UPDATE vendor_amc_rates
        SET vendor_name = ?, vendor_type = ?, amc_amount = ?, active_status = ?
        WHERE id = ?
    ");
    if (!$stmt) die("Prepare failed: " . $conn->error);

    $stmt->bind_param("ssdii", $vendor_name, $vendor_type, $amc_amount, $active_status, $id);

    if ($stmt->execute()) {
        header("Location: vendor_amc_rates.php?updated=1");
        exit;
    } else {
        $message = "Update failed: " . $stmt->error;
    }
}

/* DELETE */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM vendor_amc_rates WHERE id = ?");
    if (!$stmt) die("Prepare failed: " . $conn->error);

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: vendor_amc_rates.php?deleted=1");
        exit;
    } else {
        $message = "Delete failed: " . $stmt->error;
    }
}

$result = $conn->query("
    SELECT *
    FROM vendor_amc_rates
    ORDER BY vendor_type ASC, vendor_name ASC
");
if (!$result) die("Query failed: " . $conn->error);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Vendor AMC Rates</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">

    <div class="hero-header">
        <div>
            <h1>Vendor AMC Rates</h1>
            <p>Manage vendor-wise AMC amount</p>
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
        <div class="form-card"><strong style="color:green;">AMC rate added successfully.</strong></div>
    <?php } ?>
    <?php if (isset($_GET['updated'])) { ?>
        <div class="form-card"><strong style="color:green;">AMC rate updated successfully.</strong></div>
    <?php } ?>
    <?php if (isset($_GET['deleted'])) { ?>
        <div class="form-card"><strong style="color:green;">AMC rate deleted successfully.</strong></div>
    <?php } ?>

    <div class="form-card">
        <h2>Add AMC Rate</h2>
        <form method="POST">
            <input type="hidden" name="add_amc" value="1">
            <div class="form-grid">
                <div>
                    <label>Vendor Name</label>
                    <input type="text" name="vendor_name" required>
                </div>
                <div>
                    <label>Vendor Type</label>
                    <select name="vendor_type" required>
                        <option value="ATM">ATM</option>
                        <option value="UPS">UPS</option>
                    </select>
                </div>
                <div>
                    <label>AMC Amount</label>
                    <input type="number" step="0.01" name="amc_amount" required>
                </div>
                <div class="full-width">
                    <button type="submit" class="btn">Add AMC Rate</button>
                </div>
            </div>
        </form>
    </div>

    <div class="table-card">
        <h2>AMC Rate List</h2>
        <div class="table-wrap">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Vendor Name</th>
                        <th>Vendor Type</th>
                        <th>AMC Amount</th>
                        <th>Active</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()) { ?>
                        <tr>
                            <form method="POST">
                                <input type="hidden" name="update_amc" value="1">
                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">

                                <td><?php echo (int)$row['id']; ?></td>
                                <td><input type="text" name="vendor_name" value="<?php echo h($row['vendor_name']); ?>" required></td>
                                <td>
                                    <select name="vendor_type">
                                        <option value="ATM" <?php if ($row['vendor_type'] === 'ATM') echo 'selected'; ?>>ATM</option>
                                        <option value="UPS" <?php if ($row['vendor_type'] === 'UPS') echo 'selected'; ?>>UPS</option>
                                    </select>
                                </td>
                                <td><input type="number" step="0.01" name="amc_amount" value="<?php echo h($row['amc_amount']); ?>" required></td>
                                <td style="text-align:center;">
                                    <input type="checkbox" name="active_status" <?php if ((int)$row['active_status'] === 1) echo 'checked'; ?>>
                                </td>
                                <td class="action-cell">
                                    <button type="submit" class="btn">Update</button>
                                    <a class="link-btn delete-btn" href="vendor_amc_rates.php?delete=<?php echo (int)$row['id']; ?>" onclick="return confirm('Delete this AMC rate?')">Delete</a>
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