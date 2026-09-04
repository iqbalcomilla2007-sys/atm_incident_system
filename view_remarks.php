<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('view_history');

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$incident_id = (int)($_GET['incident_id'] ?? 0);
if ($incident_id <= 0) {
    die("Invalid incident.");
}

$incidentObj = new Incident();
$remarks = $incidentObj->getRemarks($incident_id);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Remarks History</title>
    <link rel="stylesheet" href="style.css?v=16">
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <div class="hero-header">
        <div>
            <h1>Remarks History</h1>
            <p>Incident ID: <?php echo (int)$incident_id; ?></p>
        </div>
        <div class="hero-actions"></div>
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table class="modern-table">
                <tr>
                    <th>Date</th>
                    <th>Remark</th>
                    <th>User</th>
                </tr>
                <?php if ($remarks->num_rows > 0) { ?>
                    <?php while ($r = $remarks->fetch_assoc()) { ?>
                        <tr>
                            <td><?php echo h($r['created_at']); ?></td>
                            <td><?php echo nl2br(h($r['remark'])); ?></td>
                            <td><?php echo h($r['full_name'] ?? ''); ?></td>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr><td colspan="3">No remarks found.</td></tr>
                <?php } ?>
            </table>
        </div>
    </div>
</div>
</body>
</html>