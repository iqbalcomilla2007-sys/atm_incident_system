<?php
date_default_timezone_set('Asia/Dhaka');
include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

function getCount($conn, $sql) {
    try {
        $res = $conn->query($sql);
        if ($res) {
            $row = $res->fetch_row();
            return $row[0];
        }
    } catch (mysqli_sql_exception $e) {
        // Handle missing tables gracefully
        if (strpos($e->getMessage(), "doesn't exist") !== false) {
            return "N/A";
        }
        throw $e;
    }
    return 0;
}

// Counts
$totalList = getCount($conn, "SELECT COUNT(*) FROM cctv_list");
$activeList = getCount($conn, "SELECT COUNT(*) FROM cctv_list WHERE status='Active'");
$pendingUpgrade = getCount($conn, "SELECT COUNT(*) FROM cctv_list WHERE status='Pending Upgrade'");

$totalSetReq = getCount($conn, "SELECT COUNT(*) FROM cctv_set_requisition");
$pendingSetReq = getCount($conn, "SELECT COUNT(*) FROM cctv_set_requisition WHERE status='Draft'");

$totalSpareReq = getCount($conn, "SELECT COUNT(*) FROM cctv_spare_requisition");
$pendingSpareReq = getCount($conn, "SELECT COUNT(*) FROM cctv_spare_requisition WHERE status='Draft'");

$totalBills = getCount($conn, "SELECT COUNT(*) FROM cctv_vendor_bills");
$pendingBills = getCount($conn, "SELECT COUNT(*) FROM cctv_vendor_bills WHERE payment_status='Pending'");

// Status Wise Summary for CCTV List
$statusSummary = [];
$resS = $conn->query("SELECT status, COUNT(*) as total FROM cctv_list GROUP BY status ORDER BY total DESC");
while($resS && $rowS = $resS->fetch_assoc()) {
    $statusSummary[] = $rowS;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCTV Management Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { color: #0d6efd; margin: 0; }
        
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-top: 5px solid #0d6efd; }
        .card.spare { border-top-color: #198754; }
        .card.bills { border-top-color: #ffc107; }
        .card h3 { margin-top: 0; color: #555; font-size: 16px; text-transform: uppercase; letter-spacing: 1px; }
        
        .stat { display: flex; justify-content: space-between; align-items: baseline; margin: 10px 0; }
        .stat span { font-size: 24px; font-weight: bold; color: #333; }
        .stat-label { color: #888; font-size: 0.9rem; }
        
        .nav-links { display: flex; flex-wrap: wrap; gap: 15px; margin-top: 20px; }
        .btn { padding: 12px 20px; border-radius: 8px; text-decoration: none; color: #fff; font-weight: 600; font-size: 14px; transition: 0.3s; }
        .btn-blue { background: #0d6efd; } .btn-blue:hover { background: #0b5ed7; }
        .btn-green { background: #198754; } .btn-green:hover { background: #157347; }
        .btn-orange { background: #fd7e14; } .btn-orange:hover { background: #bb4e03; }
        .btn-secondary { background: #6c757d; } .btn-secondary:hover { background: #5c636a; }
        .btn-info { background: #0dcaf0; color: #000; } .btn-info:hover { background: #31d2f2; }
        .btn-dark { background: #212529; } .btn-dark:hover { background: #1c1f23; }
        
        @media print {
            .no-print { display: none !important; }
            .container { width: 100% !important; max-width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .card { box-shadow: none !important; border: 1px solid #ddd !important; }
            body { background: #fff !important; }
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container">
    <div class="header no-print">
        <h1>CCTV Dashboard</h1>
        <div style="display:flex; gap:10px; align-items:center;">
            <button onclick="window.print()" class="btn btn-info">Print Dashboard</button>
            <a href="dashboard_ajax_v2.php" class="btn btn-secondary">Main Dashboard</a>
            <a href="logout.php" class="btn btn-secondary" style="background:#dc3545;">Logout</a>
        </div>
    </div>

    <div class="grid">
        <!-- CCTV Inventory Summary Table -->
        <div class="card status-summary">
            <h3>CCTV Status Summary</h3>
            <table style="width:100%; border-collapse: collapse; margin-top:10px;">
                <thead>
                    <tr style="border-bottom: 2px solid #eee; text-align: left;">
                        <th style="padding:8px;">Status</th>
                        <th style="padding:8px; text-align: right;">Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $totalSt = 0; foreach($statusSummary as $st): $totalSt += $st['total']; ?>
                        <tr style="border-bottom: 1px solid #f9f9f9;">
                            <td style="padding:8px;"><?= htmlspecialchars($st['status'] ?: 'Unknown') ?></td>
                            <td style="padding:8px; text-align: right; font-weight: bold;"><?= $st['total'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f8f9fa; font-weight: bold;">
                        <td style="padding:8px;">Grand Total</td>
                        <td style="padding:8px; text-align: right;"><?= $totalSt ?></td>
                    </tr>
                </tfoot>
            </table>
            <div class="no-print" style="margin-top:15px; text-align: right;">
                <a href="cctv_list.php?print_summary=1" target="_blank" style="font-size: 12px; color: #0d6efd; text-decoration: none;">Full Page Print &rarr;</a>
            </div>
        </div>

        <!-- Monitoring Status (Brief) -->
        <div class="card no-print">
            <h3>Key Metrics</h3>
            <div class="stat">
                <div class="stat-label">Total ATMs</div>
                <span><?= $totalList ?></span>
            </div>
            <hr style="border: 0; border-top: 1px solid #eee;">
            <div class="stat">
                <div class="stat-label">Active Sites</div>
                <span style="color: #198754;"><?= $activeList ?></span>
            </div>
        </div>

        <!-- Set Requisitions -->
        <div class="card">
            <h3>Set Requisitions</h3>
            <div class="stat">
                <div class="stat-label">Total Requests</div>
                <span><?= $totalSetReq ?></span>
            </div>
            <hr style="border: 0; border-top: 1px solid #eee;">
            <div class="stat">
                <div class="stat-label">Pending Approval</div>
                <span style="color: #0d6efd;"><?= $pendingSetReq ?></span>
            </div>
            <a href="cctv_set_requisition_list.php" style="font-size: 12px; color: #0d6efd; text-decoration: none;">View All &rarr;</a>
        </div>

        <!-- Spare Requisitions -->
        <div class="card spare">
            <h3>Spare Parts</h3>
            <div class="stat">
                <div class="stat-label">Total Requests</div>
                <span><?= $totalSpareReq ?></span>
            </div>
            <hr style="border: 0; border-top: 1px solid #eee;">
            <div class="stat">
                <div class="stat-label">Pending Action</div>
                <span style="color: #198754;"><?= $pendingSpareReq ?></span>
            </div>
            <a href="cctv_spare_requisition_list.php" style="font-size: 12px; color: #198754; text-decoration: none;">View All &rarr;</a>
        </div>

        <!-- Vendor Billing -->
        <div class="card bills">
            <h3>Vendor Billing</h3>
            <div class="stat">
                <div class="stat-label">Total Bills</div>
                <span><?= $totalBills ?></span>
            </div>
            <hr style="border: 0; border-top: 1px solid #eee;">
            <div class="stat">
                <div class="stat-label">Pending Payment</div>
                <span style="color: #bb4e03;"><?= $pendingBills ?></span>
            </div>
            <a href="#" style="font-size: 12px; color: #bb4e03; text-decoration: none;">Manage Bills &rarr;</a>
        </div>
    </div>

    <div class="nav-links no-print">
        <a href="cctv_list.php" class="btn btn-blue" style="flex: 1; text-align: center; min-width: 200px;">CCTV Sites Inventory List</a>
    </div>

    <div style="margin-top:20px;" class="no-print">
        <h3 style="color:#555; font-size:14px; text-transform:uppercase; margin-bottom:15px; border-bottom:1px solid #ddd; padding-bottom:10px;">Requisition Management</h3>
        <div class="nav-links">
            <a href="cctv_set_requisition.php" class="btn btn-green">New Set Requisition</a>
            <a href="cctv_set_requisition_list.php" class="btn btn-green" style="background:#0a58ca;">Set Requisition List</a>
            <a href="cctv_spare_requisition.php" class="btn btn-orange">New Spare Requisition</a>
            <a href="cctv_spare_requisition_list.php" class="btn btn-orange" style="background:#ca6510;">Spare Requisition List</a>
        </div>
    </div>

    <div style="margin-top:40px;" class="no-print">
        <h3 style="color:#555; font-size:14px; text-transform:uppercase; margin-bottom:15px; border-bottom:1px solid #ddd; padding-bottom:10px;">Stock, Dispatch & Reports</h3>
        <div class="nav-links">
            <a href="cctv_stock_receive.php" class="btn btn-secondary" style="background:#555;">Stock Receive</a>
            <a href="cctv_stock_ledger.php" class="btn btn-secondary" style="background:#555;">Stock Ledger</a>
            <a href="cctv_branch_dispatch.php" class="btn btn-secondary" style="background:#198754;">Dispatch to Branch</a>
            <a href="cctv_dispatch_acknowledgement.php" class="btn btn-secondary" style="background:#0dcaf0; color:#000;">Dispatch Ack.</a>
            <a href="cctv_warranty_report.php" class="btn btn-secondary">Warranty Report</a>
            <a href="cctv_device_history_report.php" class="btn btn-secondary">Device History</a>
            <a href="cctv_complete_report.php" class="btn btn-secondary">Complete Assets</a>
        </div>
    </div>

    <div style="margin-top:40px;" class="no-print">
        <h3 style="color:#555; font-size:14px; text-transform:uppercase; margin-bottom:15px; border-bottom:1px solid #ddd; padding-bottom:10px;">Master Data Settings</h3>
        <div class="nav-links">
            <a href="cctv_item_master.php" class="btn btn-secondary">Item Master Settings</a>
            <a href="cctv_vendor_master.php" class="btn btn-secondary">Vendor Master</a>
        </div>
    </div>
</div>

</body>
</html>
