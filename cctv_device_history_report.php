<?php
date_default_timezone_set('Asia/Dhaka');
include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('cctv_device_history_report');

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$serial = trim($_GET['serial_no'] ?? '');

$results = [];
if ($serial) {
    // We join stock_transactions with stock to identify by serial
    $sql = "
        SELECT 
            t.transaction_date, 
            t.transaction_type, 
            t.qty, 
            t.branch_name, 
            t.booth_name, 
            t.remarks,
            s.serial_no, 
            s.brand, 
            s.model, 
            im.item_name
        FROM cctv_stock_transactions t
        JOIN cctv_stock s ON s.id = t.stock_id
        JOIN cctv_item_master im ON im.id = s.item_id
        WHERE s.serial_no = ?
        ORDER BY t.transaction_date DESC, t.id DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $serial);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $results[] = $row;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCTV Device History Report</title>
    <style>
        body{font-family:Arial, sans-serif;background:#f4f6f9;padding:20px; font-size:13px;}
        .container{max-width:1100px;margin:auto;background:#fff;padding:25px;border-radius:12px;box-shadow:0 5px 15px rgba(0,0,0,0.08);}
        h2{color:#0d6efd; margin-top:0;}
        .btn{padding:8px 15px; border-radius:6px; cursor:pointer; text-decoration:none; color:#fff; font-weight:bold; font-size:13px; display:inline-block;}
        .btn-blue{background:#0d6efd;} .btn-secondary{background:#6c757d;}
        .search-box { background:#f8f9fa; padding:20px; border-radius:8px; margin-bottom:20px; border:1px solid #eee; display:flex; gap:15px; align-items:flex-end;}
        .search-box div { flex:1; }
        .search-box label { display:block; font-weight:bold; margin-bottom:5px; }
        .search-box input { width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box;}
        table{width:100%;border-collapse:collapse; margin-top:20px;}
        th,td{border:1px solid #ddd;padding:12px;text-align:left;}
        th{background:#f8f9fa; font-weight:bold; color:#333;}
        .badge { padding:4px 8px; border-radius:4px; font-size:11px; font-weight:bold; color:#fff; }
        .badge-in { background:#198754; }
        .badge-out { background:#dc3545; }
        .badge-ack { background:#0dcaf0; color:#000; }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="margin:0;">Device Lifecycle History</h2>
        <div style="display:flex; gap:10px;">
            <a href="cctv_dashboard.php" class="btn btn-secondary">CCTV Dashboard</a>
            <a href="dashboard_ajax_v2.php" class="btn btn-secondary">Main Dashboard</a>
        </div>
    </div>

    <form method="GET" class="search-box">
        <div>
            <label>Serial Number</label>
            <input type="text" name="serial_no" value="<?= h($serial) ?>" placeholder="Enter Device Serial (e.g. S/N, Tag)..." required>
        </div>
        <div style="flex:0; min-width:150px;">
            <button type="submit" class="btn btn-blue" style="width:100%;">View History</button>
        </div>
    </form>

    <?php if ($serial): ?>
        <h3>History for: <?= h($serial) ?></h3>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Event</th>
                        <th>Qty</th>
                        <th>Location Detail</th>
                        <th>Item Information</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($results): ?>
                    <?php foreach($results as $r): 
                        $typeClass = '';
                        if ($r['transaction_type'] == 'IN') $typeClass = 'badge-in';
                        elseif ($r['transaction_type'] == 'DISPATCH') $typeClass = 'badge-out';
                        elseif ($r['transaction_type'] == 'ACKNOWLEDGED') $typeClass = 'badge-ack';
                    ?>
                    <tr>
                        <td><?= date('d-M-Y', strtotime($r['transaction_date'])) ?></td>
                        <td><span class="badge <?= $typeClass ?>"><?= h($r['transaction_type']) ?></span></td>
                        <td><?= (int)$r['qty'] ?></td>
                        <td>
                            <?php if($r['branch_name']): ?>
                                <strong><?= h($r['branch_name']) ?></strong><br>
                                <small><?= h($r['booth_name']) ?></small>
                            <?php else: ?>
                                <span style="color:#999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= h($r['item_name']) ?></strong><br>
                            <small><?= h($r['brand']) ?> / <?= h($r['model']) ?></small>
                        </td>
                        <td><small><?= h($r['remarks']) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; padding:30px; color:#999;">No history found for this serial number.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div style="margin-top:20px;">
        <a href="cctv_dashboard.php" style="color:#0d6efd; font-weight:bold; text-decoration:none;">&larr; Back to Dashboard</a>
    </div>
</div>
</body>
</html>
