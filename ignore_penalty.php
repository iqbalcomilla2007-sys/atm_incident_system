<?php
date_default_timezone_set('Asia/Dhaka');
include 'auth_check.php';
include 'db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid penalty ID");
}

$penaltyId = (int)$_GET['id'];
$userId = (int)($_SESSION['user_id'] ?? 0);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$stmt = $conn->prepare("SELECT * FROM incident_penalties WHERE penalty_id = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $penaltyId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    die("Penalty not found");
}

$message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $ignoreReason = trim($_POST['ignore_reason'] ?? '');
    $instructionBy = trim($_POST['ignore_instruction_by'] ?? '');
    $ignoreRemarks = trim($_POST['ignore_remarks'] ?? '');

    if ($ignoreReason === '' || $instructionBy === '') {
        $message = "Ignore reason and instruction by are required.";
    } else {
        $insert = $conn->prepare("
            INSERT INTO ignored_penalties
            (
                original_penalty_id, incident_id, atm_id, atm_name, vendor_name, vendor_type,
                incident_name, original_down_time, original_down_time_minutes,
                final_down_time, final_down_time_minutes, penalty_percent, penalty_amount,
                vendor_ticket_no, vendor_ticket_date_time,
                ignore_reason, ignore_instruction_by, ignore_remarks, ignored_by
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$insert) {
            die("Prepare failed: " . $conn->error);
        }

        $insert->bind_param(
            "iissssssissddssssi",
            $row['penalty_id'],
            $row['incident_id'],
            $row['atm_id'],
            $row['atm_name'],
            $row['vendor_name'],
            $row['vendor_type'],
            $row['incident_name'],
            $row['original_down_time'],
            $row['original_down_time_minutes'],
            $row['final_down_time'],
            $row['final_down_time_minutes'],
            $row['penalty_percent'],
            $row['penalty_amount'],
            $row['vendor_ticket_no'],
            $row['vendor_ticket_date_time'],
            $ignoreReason,
            $instructionBy,
            $ignoreRemarks,
            $userId
        );

        if (!$insert->execute()) {
            die("Insert failed: " . $insert->error);
        }

        $update = $conn->prepare("
            UPDATE incident_penalties
            SET penalty_status = 'IGNORED',
                updated_by = ?
            WHERE penalty_id = ?
        ");
        if (!$update) {
            die("Prepare failed: " . $conn->error);
        }

        $update->bind_param("ii", $userId, $penaltyId);

        if ($update->execute()) {
            header("Location: penalty_report.php?ignored=1");
            exit;
        } else {
            $message = "Update failed: " . $update->error;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ignore Penalty</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <div class="hero-header">
        <div>
            <h1>Ignore Penalty</h1>
            <p>Penalty ID: <?php echo h($row['penalty_id']); ?></p>
        </div>
        <div class="hero-actions">
            <a class="btn btn-secondary" href="ignored_penalties.php">Ignored Penalties</a>
</div>
    </div>

    <?php if ($message !== '') { ?>
        <div class="form-card">
            <strong><?php echo h($message); ?></strong>
        </div>
    <?php } ?>

    <form method="POST" class="form-card">
        <div class="form-grid">
            <div>
                <label>Penalty ID</label>
                <input type="text" value="<?php echo h($row['penalty_id']); ?>" readonly>
            </div>

            <div>
                <label>Vendor Name</label>
                <input type="text" value="<?php echo h($row['vendor_name']); ?>" readonly>
            </div>

            <div>
                <label>Vendor Type</label>
                <input type="text" value="<?php echo h($row['vendor_type']); ?>" readonly>
            </div>

            <div>
                <label>Incident ID</label>
                <input type="text" value="<?php echo h($row['incident_id']); ?>" readonly>
            </div>

            <div>
                <label>Incident Name</label>
                <input type="text" value="<?php echo h($row['incident_name']); ?>" readonly>
            </div>

            <div>
                <label>Penalty Amount</label>
                <input type="text" value="<?php echo number_format((float)$row['penalty_amount'], 2); ?>" readonly>
            </div>

            <div class="full-width">
                <label>Ignore Reason</label>
                <textarea name="ignore_reason" rows="3" required></textarea>
            </div>

            <div>
                <label>Instruction By</label>
                <input type="text" name="ignore_instruction_by" required>
            </div>

            <div class="full-width">
                <label>Remarks</label>
                <textarea name="ignore_remarks" rows="3"></textarea>
            </div>

            <div class="full-width">
                <button type="submit" class="btn">Ignore Penalty</button>
            </div>
        </div>
    </form>

</div>
</body>
</html>