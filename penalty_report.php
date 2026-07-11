<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('manage_penalty');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$vendorName = isset($_GET['vendor_name']) ? trim($_GET['vendor_name']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$sql = "
    SELECT *
    FROM incident_penalties
    WHERE 1=1
";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (
        atm_id LIKE ? OR
        atm_name LIKE ? OR
        vendor_name LIKE ? OR
        incident_name LIKE ? OR
        CAST(incident_id AS CHAR) LIKE ? OR
        CAST(penalty_id AS CHAR) LIKE ?
    )";
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
    $types .= 'ssssss';
}

if ($vendorName !== '') {
    $sql .= " AND vendor_name = ?";
    $params[] = $vendorName;
    $types .= 's';
}

if ($status !== '') {
    $sql .= " AND penalty_status = ?";
    $params[] = $status;
    $types .= 's';
}

$sql .= " ORDER BY created_at DESC, penalty_id DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

$result = $stmt->get_result();

$totalPenalty = 0;
$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
    $totalPenalty += (float)$row['penalty_amount'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Penalty Report</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">

    <div class="hero-header">
        <div>
            <h1>Penalty Report</h1>
            <p>Final penalty list with edit, ignore and forwarding options</p>
        </div>
        <div class="hero-actions">
            <a class="btn btn-secondary" href="ignored_penalties.php">Ignored Penalties</a>
</div>
    </div>

    <?php if (isset($_GET['updated']) && $_GET['updated'] == '1') { ?>
        <div class="form-card">
            <strong style="color:green;">Penalty updated successfully.</strong>
        </div>
    <?php } ?>

    <?php if (isset($_GET['ignored']) && $_GET['ignored'] == '1') { ?>
        <div class="form-card">
            <strong style="color:green;">Penalty ignored successfully.</strong>
        </div>
    <?php } ?>

    <div class="form-card">
        <form method="GET" class="modern-filter">
            <input type="text" name="search" placeholder="Search penalty / vendor / incident / ATM" value="<?php echo h($search); ?>">
            <input type="text" name="vendor_name" placeholder="Exact Vendor Name" value="<?php echo h($vendorName); ?>">

            <select name="status">
                <option value="">All Status</option>
                <option value="ACTIVE" <?php if ($status === 'ACTIVE') echo 'selected'; ?>>ACTIVE</option>
                <option value="IGNORED" <?php if ($status === 'IGNORED') echo 'selected'; ?>>IGNORED</option>
                <option value="FORWARDED" <?php if ($status === 'FORWARDED') echo 'selected'; ?>>FORWARDED</option>
            </select>

            <button type="submit" class="btn">Search</button>
            <a class="btn btn-secondary" href="penalty_report.php">Reset</a>
        </form>
    </div>

    <div class="form-card">
        <strong>Total Penalty Amount: <?php echo number_format($totalPenalty, 2); ?></strong>
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Penalty ID</th>
                        <th>Vendor Name</th>
                        <th>Vendor Type</th>
                        <th>Vendor Ticket No</th>
                        <th>Vendor Ticket Date Time</th>
                        <th>Incident ID</th>
                        <th>Incident Name</th>
                        <th>ATM ID</th>
                        <th>Original Down Time</th>
                        <th>Final Down Time</th>
                        <th>Penalty %</th>
                        <th>Penalty Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($data) > 0) { ?>
                        <?php foreach ($data as $row) { ?>
                            <tr>
                                <td><?php echo h($row['penalty_id']); ?></td>
                                <td><?php echo h($row['vendor_name']); ?></td>
                                <td><?php echo h($row['vendor_type']); ?></td>
                                <td><?php echo h($row['vendor_ticket_no']); ?></td>
                                <td><?php echo h($row['vendor_ticket_date_time']); ?></td>
                                <td><?php echo h($row['incident_id']); ?></td>
                                <td><?php echo h($row['incident_name']); ?></td>
                                <td><?php echo h($row['atm_id']); ?></td>
                                <td><?php echo h($row['original_down_time']); ?></td>
                                <td><?php echo h($row['final_down_time']); ?></td>
                                <td><?php echo number_format((float)$row['penalty_percent'], 2); ?>%</td>
                                <td><?php echo number_format((float)$row['penalty_amount'], 2); ?></td>
                                <td><?php echo h($row['penalty_status']); ?></td>
                                <td class="action-cell">
                                    <a class="link-btn edit-btn" href="edit_penalty.php?id=<?php echo (int)$row['penalty_id']; ?>">Edit</a>

                                    <?php if (($row['penalty_status'] ?? '') === 'ACTIVE') { ?>
                                        <a class="link-btn close-btn"
                                           href="ignore_penalty.php?id=<?php echo (int)$row['penalty_id']; ?>"
                                           onclick="return confirm('Do you want to ignore penalty ID: <?php echo addslashes($row['penalty_id']); ?>?')">
                                           Ignore
                                        </a>
                                    <?php } ?>

                                    <a class="link-btn"
                                       href="print_penalty_letter.php?vendor_name=<?php echo urlencode($row['vendor_name']); ?>"
                                       target="_blank">
                                       Letter
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="14" class="text-center">No penalty found.</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
