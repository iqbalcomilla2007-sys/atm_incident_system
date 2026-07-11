<?php
date_default_timezone_set('Asia/Dhaka');
include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('cctv_complete_report');

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Summary Counts
$summary = [
    'installed' => 0,
    'in_stock'  => 0,
    'total'     => 0
];

$res = $conn->query("SELECT COUNT(*) as total FROM cctv_installed_devices WHERE status='Active'");
if ($res) $summary['installed'] = (int)$res->fetch_assoc()['total'];

$res = $conn->query("SELECT SUM(qty) as total FROM cctv_stock WHERE status='In_Stock'");
if ($res) $summary['in_stock'] = (int)$res->fetch_assoc()['total'];

$summary['total'] = $summary['installed'] + $summary['in_stock'];

// Detailed Report Query - combining installed and stock for context
$installedRes = $conn->query("
    SELECT 'INSTALLED' as source, l.atm_id, l.branch_name, im.item_name, i.serial_no, i.installation_date as date
    FROM cctv_installed_devices i
    JOIN cctv_locations l ON l.id = i.cctv_location_id
    JOIN cctv_item_master im ON im.id = i.item_id
    WHERE i.status = 'Active'
    ORDER BY l.atm_id ASC
");

$stockRes = $conn->query("
    SELECT 'STOCK' as source, '' as atm_id, 'Central Warehouse' as branch_name, im.item_name, s.serial_no, s.received_date as date
    FROM cctv_stock s
    JOIN cctv_item_master im ON im.id = s.item_id
    WHERE s.status = 'In_Stock'
    ORDER BY s.received_date DESC
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCTV Complete Assets Report</title>
    <style>
        body{font-family:Arial, sans-serif;background:#f4f6f9;padding:20px; font-size:13px;}
        .container{max-width:1400px;margin:auto;background:#fff;padding:25px;border-radius:12px;box-shadow:0 5px 15px rgba(0,0,0,0.08);}
        h2{color:#0d6efd; margin-top:0;}
        .btn{padding:8px 15px; border-radius:6px; cursor:pointer; text-decoration:none; color:#fff; font-weight:bold; font-size:13px; display:inline-block;}
        .btn-blue{background:#0d6efd;} .btn-secondary{background:#6c757d;}
        .summary-cards{display:flex; gap:20px; margin-bottom:30px;}
        .card{flex:1; padding:20px; border-radius:10px; color:#fff; text-align:center;}
        .card-blue{background:#0d6efd;} .card-green{background:#198754;} .card-orange{background:#fd7e14;}
        .card h4{margin:0; font-size:14px; text-transform:uppercase; opacity:0.8;}
        .card .val{font-size:32px; font-weight:bold; margin-top:5px;}
        table{width:100%;border-collapse:collapse; margin-top:20px;}
        th,td{border:1px solid #ddd;padding:10px;text-align:left;}
        th{background:#f8f9fa; font-weight:bold; color:#333;}
        .source-badge { padding:3px 7px; border-radius:4px; font-size:10px; font-weight:bold; color:#fff; }
        .bg-inst { background:#198754; } .bg-stock { background:#0dcaf0; color:#000; }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
        <h2 style="margin:0;">Complete Assets Report</h2>
        <div style="display:flex; gap:10px;">
            <a href="cctv_dashboard.php" class="btn btn-blue">CCTV Dashboard</a>
            <a href="dashboard_ajax_v2.php" class="btn btn-secondary">Main Dashboard</a>
            <button onclick="window.print()" class="btn btn-secondary">Print Report</button>
        </div>
    </div>

    <div class="summary-cards">
        <div class="card card-green">
            <h4>Installed Devices</h4>
            <div class="val"><?= $summary['installed'] ?></div>
        </div>
        <div class="card card-blue">
            <h4>In Stock (Available)</h4>
            <div class="val"><?= $summary['in_stock'] ?></div>
        </div>
        <div class="card card-orange">
            <h4>Total Asset Count</h4>
            <div class="val"><?= $summary['total'] ?></div>
        </div>
    </div>

    <h3>Detailed Asset Listing</h3>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>ATM ID</th>
                    <th>Location / Branch</th>
                    <th>Item Name</th>
                    <th>Serial No</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                while($r = $installedRes->fetch_assoc()) {
                    echo "<tr>
                        <td><span class='source-badge bg-inst'>INSTALLED</span></td>
                        <td>".h($r['atm_id'])."</td>
                        <td>".h($r['branch_name'])."</td>
                        <td>".h($r['item_name'])."</td>
                        <td><code>".h($r['serial_no'])."</code></td>
                        <td>".date('d-M-Y', strtotime($r['date']))."</td>
                    </tr>";
                }
                while($r = $stockRes->fetch_assoc()) {
                    echo "<tr>
                        <td><span class='source-badge bg-stock'>STOCK</span></td>
                        <td>-</td>
                        <td>".h($r['branch_name'])."</td>
                        <td>".h($r['item_name'])."</td>
                        <td><code>".h($r['serial_no'])."</code></td>
                        <td>".date('d-M-Y', strtotime($r['date']))."</td>
                    </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top:20px;" class="no-print">
        <a href="cctv_dashboard.php" style="color:#0d6efd; font-weight:bold; text-decoration:none;">&larr; Back to Dashboard</a>
    </div>
</div>
</body>
</html>
