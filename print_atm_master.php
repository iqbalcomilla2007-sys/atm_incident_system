<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('manage_atm_master');

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$isAdmin = Auth::isAdmin();
$assignedZone = getUserAssignedZone();
$search = trim($_GET['search'] ?? '');

if ($search === '') {
    die("Search text is required for print.");
}

$sql = "
    SELECT atm_id, atm_name, ups_vendor, atm_model, atm_vendor, branch_name, group_no, zone_name
    FROM atm_master
    WHERE (
        atm_id LIKE ?
        OR atm_name LIKE ?
        OR branch_name LIKE ?
        OR zone_name LIKE ?
        OR atm_vendor LIKE ?
        OR ups_vendor LIKE ?
    )
";

$params = [];
$types = '';

$like = '%' . $search . '%';
$params[] = $like;
$params[] = $like;
$params[] = $like;
$params[] = $like;
$params[] = $like;
$params[] = $like;
$types .= 'ssssss';

if (!$isAdmin) {
    if ($assignedZone === '') {
        $sql .= " AND 1 = 0 ";
    } else {
        $sql .= " AND zone_name = ? ";
        $params[] = $assignedZone;
        $types .= 's';
    }
}

$sql .= "
    ORDER BY
        CASE WHEN group_no IS NULL OR group_no = 0 THEN 1 ELSE 0 END,
        group_no ASC,
        zone_name ASC,
        branch_name ASC,
        atm_name ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Print ATM Master</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .report-header {
            text-align: center;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        .report-header h1, .report-header h2, .report-header p {
            margin: 0;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            @page {
                margin: 10mm;
            }
            .modern-table th,
            .modern-table td {
                border: 1px solid #000 !important;
                font-size: 11px !important;
                padding: 4px !important;
            }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="hero-header no-print">
        <div>
            <h1>ATM Master Print</h1>
        </div>
        <div class="hero-actions">
            <button class="btn btn-dark" onclick="window.print()">Print</button>
</div>
    </div>

    <div class="report-header">
        <h1>Islami Bank Bangladesh PLC</h1>
        <h2>ATM Management Division (DBW)</h2>
        <p><strong>ATM Master Search Result</strong></p>
        <p>Search: <?php echo h($search); ?></p>
        <p>Date: <?php echo date('d-M-Y h:i A'); ?></p>
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>S/L</th>
                        <th>ATM ID</th>
                        <th>Booth Name</th>
                        <th>UPS Vendor</th>
                        <th>ATM Model</th>
                        <th>ATM Vendor</th>
                        <th>Branch Name</th>
                        <th>Group No</th>
                        <th>Zone Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0) { ?>
                        <?php $sl = 1; while ($row = $result->fetch_assoc()) { ?>
                            <tr>
                                <td><?php echo $sl++; ?></td>
                                <td><?php echo h($row['atm_id']); ?></td>
                                <td><?php echo h($row['atm_name']); ?></td>
                                <td><?php echo h($row['ups_vendor']); ?></td>
                                <td><?php echo h($row['atm_model']); ?></td>
                                <td><?php echo h($row['atm_vendor']); ?></td>
                                <td><?php echo h($row['branch_name']); ?></td>
                                <td><?php echo h($row['group_no']); ?></td>
                                <td><?php echo h($row['zone_name']); ?></td>
                            </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="9" style="text-align:center;">No ATM found.</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

</body>
</html>