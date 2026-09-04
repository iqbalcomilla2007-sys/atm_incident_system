<?php
date_default_timezone_set('Asia/Dhaka');
include 'auth_check.php';
include 'db.php';

$result = $conn->query("
    SELECT *
    FROM ignored_penalties
    ORDER BY ignored_at DESC, id DESC
");
if (!$result) die("Query failed: " . $conn->error);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ignored Penalties</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">

    <div class="hero-header">
        <div>
            <h1>Ignored Penalties</h1>
            <p>Ignored penalty history</p>
        </div>
        <div class="hero-actions"></div>
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Original Penalty ID</th>
                        <th>Vendor Name</th>
                        <th>Incident ID</th>
                        <th>Incident Name</th>
                        <th>Penalty Amount</th>
                        <th>Ignore Reason</th>
                        <th>Instruction By</th>
                        <th>Ignored At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()) { ?>
                        <tr>
                            <td><?php echo h($row['id']); ?></td>
                            <td><?php echo h($row['original_penalty_id']); ?></td>
                            <td><?php echo h($row['vendor_name']); ?></td>
                            <td><?php echo h($row['incident_id']); ?></td>
                            <td><?php echo h($row['incident_name']); ?></td>
                            <td><?php echo number_format((float)$row['penalty_amount'], 2); ?></td>
                            <td><?php echo h($row['ignore_reason']); ?></td>
                            <td><?php echo h($row['ignore_instruction_by']); ?></td>
                            <td><?php echo h($row['ignored_at']); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>