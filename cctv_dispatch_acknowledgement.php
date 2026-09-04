<?php
date_default_timezone_set('Asia/Dhaka');
include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('cctv_dispatch_acknowledgement');

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$message = '';
$error = '';
$view = $_GET['view'] ?? 'Pending'; // ডিফল্টভাবে পেন্ডিং দেখাবে

// --- ACKNOWLEDGEMENT LOGIC ---
if (isset($_GET['ack_id'])) {
    try {
        $dispatchId = (int)$_GET['ack_id'];
        if ($dispatchId <= 0) throw new Exception("Invalid dispatch ID.");

        $conn->begin_transaction();
        $today = date('Y-m-d');

        $stmt = $conn->prepare("
            UPDATE cctv_branch_dispatch
            SET acknowledgement_status='Received', acknowledgement_date=?
            WHERE id=?
        ");
        $stmt->bind_param("si", $today, $dispatchId);
        $stmt->execute();
        $stmt->close();

        $items = [];
        $res = $conn->query("
            SELECT di.stock_id, di.qty, l.branch_name, l.booth_name
            FROM cctv_branch_dispatch_items di
            JOIN cctv_branch_dispatch d ON d.id = di.dispatch_id
            JOIN cctv_locations l ON l.id = d.cctv_location_id
            WHERE di.dispatch_id = $dispatchId
        ");
        while ($res && $row = $res->fetch_assoc()) $items[] = $row;

        foreach ($items as $it) {
            $remarks = 'Dispatch acknowledged by branch';
            $stmt = $conn->prepare("
                INSERT INTO cctv_stock_transactions
                (stock_id, transaction_type, transaction_date, branch_name, booth_name, qty, remarks)
                VALUES (?, 'ACKNOWLEDGED', ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isssis", $it['stock_id'], $today, $it['branch_name'], $it['booth_name'], $it['qty'], $remarks);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
        $message = "Acknowledgement saved successfully.";
    } catch (Throwable $e) {
        if ($conn->in_transaction) $conn->rollback();
        $error = $e->getMessage();
    }
}

// --- FETCH DATA BASED ON VIEW ---
$status_to_fetch = ($view === 'Received') ? 'Received' : 'Pending';
$rows = [];
$res = $conn->query("
    SELECT d.id, d.dispatch_no, d.dispatch_date, d.dispatch_type, d.letter_no, d.acknowledgement_date,
           l.atm_id, l.branch_name, l.booth_name
    FROM cctv_branch_dispatch d
    JOIN cctv_locations l ON l.id = d.cctv_location_id
    WHERE d.acknowledgement_status='$status_to_fetch'
    ORDER BY d.id DESC
");
while ($res && $row = $res->fetch_assoc()) $rows[] = $row;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dispatch Acknowledgement</title>
    <style>
        body{font-family:Arial;background:#f4f6f9;padding:20px;}
        .container{max-width:1200px;margin:auto;background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}
        table{width:100%;border-collapse:collapse; margin-top:15px;}
        th,td{border:1px solid #ddd;padding:12px;font-size:13px; text-align:left;}
        th{background:#f8f9fa; font-weight:bold;}
        .btn{padding:8px 12px;background:#198754;color:#fff;border-radius:6px;text-decoration:none; display:inline-block; font-size:12px; font-weight:bold; border:none; cursor:pointer;}
        .btn-secondary{background:#6c757d;}
        .btn-blue{background:#0d6efd;}
        .btn-toggle{padding:10px 20px; margin-right:5px;}
        .active-tab{background:#333 !important; color:#fff;}
        h2{margin-top:0; color:#333;}
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="margin:0;">Dispatch Acknowledgement</h2>
        <div style="display:flex; gap:10px;">
            <a href="cctv_dashboard.php" class="btn btn-secondary">CCTV Dashboard</a>
            <a href="dashboard_ajax_v2.php" class="btn btn-secondary">Main Dashboard</a>
        </div>
    </div>

    <!-- টগল বাটন: লিস্ট পরিবর্তন করার জন্য -->
    <div style="margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px;">
        <a href="?view=Pending" class="btn btn-blue btn-toggle <?= ($view=='Pending')?'active-tab':'' ?>">Pending List</a>
        <a href="?view=Received" class="btn btn-blue btn-toggle <?= ($view=='Received')?'active-tab':'' ?>">Received History</a>
    </div>

    <?php if($message): ?><p style="color:green; background:#d4edda; padding:10px; border-radius:4px;"><?= h($message) ?></p><?php endif; ?>
    <?php if($error): ?><p style="color:red; background:#f8d7da; padding:10px; border-radius:4px;"><?= h($error) ?></p><?php endif; ?>

    <h4 style="margin:10px 0; color:#555;"><?= $view ?> Acknowledgements</h4>
    <table>
        <thead>
            <tr>
                <th>SL</th>
                <th>Dispatch No</th>
                <th>Dispatch Date</th>
                <?php if($view == 'Received'): ?><th>Received Date</th><?php endif; ?>
                <th>Type</th>
                <th>ATM</th>
                <th>Branch</th>
                <th>Booth</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if(!$rows): ?>
            <tr><td colspan="10" style="text-align:center; padding:30px; color:#999;">No <?= strtolower($view) ?> record found.</td></tr>
        <?php else: $sl=1; foreach($rows as $r): ?>
            <tr>
                <td><?= $sl++ ?></td>
                <td><strong><?= h($r['dispatch_no']) ?></strong></td>
                <td><?= h($r['dispatch_date']) ?></td>
                <?php if($view == 'Received'): ?><td><?= h($r['acknowledgement_date']) ?></td><?php endif; ?>
                <td><?= h($r['dispatch_type']) ?></td>
                <td><?= h($r['atm_id']) ?></td>
                <td><?= h($r['branch_name']) ?></td>
                <td><?= h($r['booth_name']) ?></td>
                <td>
                    <?php if($view == 'Pending'): ?>
                        <a class="btn" href="?ack_id=<?= (int)$r['id'] ?>&view=Pending" onclick="return confirm('Confirm acknowledgement received?')">Mark Received</a>
                    <?php else: ?>
                        <span style="color:green; font-weight:bold;">✓ Received</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>