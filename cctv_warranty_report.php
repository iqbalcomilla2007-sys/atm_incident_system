<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';

Auth::requirePermission('cctv_warranty_report');

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function selected($a, $b) {
    return (string)$a === (string)$b ? 'selected' : '';
}

$filter = $_GET['filter'] ?? 'expiring30';
$itemId = (int)($_GET['item_id'] ?? 0);
$vendorId = (int)($_GET['vendor_id'] ?? 0);
$sourceType = trim($_GET['source_type'] ?? '');
$search = trim($_GET['search'] ?? '');
$export = ($_GET['export'] ?? '') === 'excel';

$filterOptions = [
    'expiring30' => 'Expiring 30 days',
    'expiring60' => 'Expiring 60 days',
    'expiring90' => 'Expiring 90 days',
    'expired' => 'Expired',
    'active' => 'Active warranty',
    'no_warranty' => 'No warranty date',
    'all' => 'All active devices',
];

$dvrItemIdSql = "(SELECT id FROM cctv_item_master WHERE item_name = 'DVR' ORDER BY id ASC LIMIT 1)";
$cameraItemIdSql = "(SELECT id FROM cctv_item_master WHERE item_name = 'Camera' ORDER BY id ASC LIMIT 1)";
$hddItemIdSql = "(SELECT id FROM cctv_item_master WHERE item_name = 'Hard Disk' ORDER BY id ASC LIMIT 1)";
$dvrWarrantyYearsSql = "COALESCE((SELECT MAX(NULLIF(warranty_years,0)) FROM cctv_item_master WHERE warranty_applicable=1 AND (item_name='DVR' OR item_name LIKE 'DVR:%' OR item_name LIKE 'DVR %')),0)";
$cameraWarrantyYearsSql = "COALESCE((SELECT MAX(NULLIF(warranty_years,0)) FROM cctv_item_master WHERE warranty_applicable=1 AND item_name LIKE '%Camera%' AND item_name NOT LIKE '%Adapter%'),0)";
$hddWarrantyYearsSql = "COALESCE((SELECT MAX(NULLIF(warranty_years,0)) FROM cctv_item_master WHERE warranty_applicable=1 AND (item_name='Hard Disk' OR item_name LIKE '%Hard Disc%' OR item_name LIKE '%Hard Disk%' OR item_name LIKE '%HDD%')),0)";

$baseRowsSql = "
    SELECT
        CONVERT(CAST(i.id AS CHAR) USING utf8mb4) AS id,
        i.item_id,
        i.vendor_id,
        CONVERT(i.source_type USING utf8mb4) AS source_type,
        CONVERT(l.atm_id USING utf8mb4) AS atm_id,
        CONVERT(l.branch_name USING utf8mb4) AS branch_name,
        CONVERT(l.booth_name USING utf8mb4) AS booth_name,
        CONVERT(im.item_name USING utf8mb4) AS item_name,
        CONVERT(v.vendor_name USING utf8mb4) AS vendor_name,
        CONVERT(i.brand USING utf8mb4) AS brand,
        CONVERT(i.model USING utf8mb4) AS model,
        CONVERT(i.serial_no USING utf8mb4) AS serial_no,
        i.installation_date,
        i.warranty_start_date,
        i.warranty_end_date,
        CONVERT(i.status USING utf8mb4) AS status,
        CONVERT(i.remarks USING utf8mb4) AS remarks
    FROM cctv_installed_devices i
    LEFT JOIN cctv_locations l ON l.id = i.cctv_location_id
    LEFT JOIN cctv_item_master im ON im.id = i.item_id
    LEFT JOIN cctv_vendors v ON v.id = i.vendor_id

    UNION ALL

    SELECT
        CONVERT(CONCAT('LIST-DVR-', cl.id) USING utf8mb4) AS id,
        $dvrItemIdSql AS item_id,
        NULL AS vendor_id,
        CONVERT('LEGACY_CCTV_LIST' USING utf8mb4) AS source_type,
        CONVERT(cl.atm_id USING utf8mb4) AS atm_id,
        CONVERT(cl.branch_name USING utf8mb4) AS branch_name,
        CONVERT(cl.atm_name USING utf8mb4) AS booth_name,
        CONVERT('DVR' USING utf8mb4) AS item_name,
        CONVERT(cl.dvr_vendor USING utf8mb4) AS vendor_name,
        CONVERT(cl.dvr_brand USING utf8mb4) AS brand,
        CONVERT(cl.dvr_model USING utf8mb4) AS model,
        CONVERT(cl.dvr_serial USING utf8mb4) AS serial_no,
        NULLIF(cl.dvr_inst_date, '0000-00-00') AS installation_date,
        CASE WHEN NULLIF(cl.dvr_inst_date, '0000-00-00') IS NOT NULL AND $dvrWarrantyYearsSql > 0 THEN NULLIF(cl.dvr_inst_date, '0000-00-00') ELSE NULL END AS warranty_start_date,
        CASE WHEN NULLIF(cl.dvr_inst_date, '0000-00-00') IS NOT NULL AND $dvrWarrantyYearsSql > 0 THEN DATE_ADD(NULLIF(cl.dvr_inst_date, '0000-00-00'), INTERVAL $dvrWarrantyYearsSql YEAR) ELSE NULL END AS warranty_end_date,
        CONVERT('Active' USING utf8mb4) AS status,
        CONVERT('Imported from cctv_list legacy DVR data' USING utf8mb4) AS remarks
    FROM cctv_list cl
    WHERE COALESCE(cl.dvr_vendor, cl.dvr_brand, cl.dvr_model, cl.dvr_serial, cl.dvr_inst_date, '') <> ''

    UNION ALL

    SELECT
        CONVERT(CONCAT('LIST-CAMERA-', cl.id) USING utf8mb4) AS id,
        $cameraItemIdSql AS item_id,
        NULL AS vendor_id,
        CONVERT('LEGACY_CCTV_LIST' USING utf8mb4) AS source_type,
        CONVERT(cl.atm_id USING utf8mb4) AS atm_id,
        CONVERT(cl.branch_name USING utf8mb4) AS branch_name,
        CONVERT(cl.atm_name USING utf8mb4) AS booth_name,
        CONVERT('Camera' USING utf8mb4) AS item_name,
        CONVERT(cl.camera_vendor USING utf8mb4) AS vendor_name,
        CONVERT('' USING utf8mb4) AS brand,
        CONVERT(cl.camera USING utf8mb4) AS model,
        CONVERT('' USING utf8mb4) AS serial_no,
        NULLIF(cl.camera_inst_date, '0000-00-00') AS installation_date,
        CASE WHEN NULLIF(cl.camera_inst_date, '0000-00-00') IS NOT NULL AND $cameraWarrantyYearsSql > 0 THEN NULLIF(cl.camera_inst_date, '0000-00-00') ELSE NULL END AS warranty_start_date,
        CASE WHEN NULLIF(cl.camera_inst_date, '0000-00-00') IS NOT NULL AND $cameraWarrantyYearsSql > 0 THEN DATE_ADD(NULLIF(cl.camera_inst_date, '0000-00-00'), INTERVAL $cameraWarrantyYearsSql YEAR) ELSE NULL END AS warranty_end_date,
        CONVERT('Active' USING utf8mb4) AS status,
        CONVERT('Imported from cctv_list legacy camera data' USING utf8mb4) AS remarks
    FROM cctv_list cl
    WHERE COALESCE(cl.camera_vendor, cl.camera, cl.camera_inst_date, '') <> ''

    UNION ALL

    SELECT
        CONVERT(CONCAT('LIST-HDD-', cl.id) USING utf8mb4) AS id,
        $hddItemIdSql AS item_id,
        NULL AS vendor_id,
        CONVERT('LEGACY_CCTV_LIST' USING utf8mb4) AS source_type,
        CONVERT(cl.atm_id USING utf8mb4) AS atm_id,
        CONVERT(cl.branch_name USING utf8mb4) AS branch_name,
        CONVERT(cl.atm_name USING utf8mb4) AS booth_name,
        CONVERT('Hard Disk' USING utf8mb4) AS item_name,
        CONVERT(cl.hdd_vendor USING utf8mb4) AS vendor_name,
        CONVERT('' USING utf8mb4) AS brand,
        CONVERT(cl.hdd_size_tb USING utf8mb4) AS model,
        CONVERT(cl.hdd_serial USING utf8mb4) AS serial_no,
        NULLIF(cl.hdd_inst_date, '0000-00-00') AS installation_date,
        CASE WHEN NULLIF(cl.hdd_inst_date, '0000-00-00') IS NOT NULL AND $hddWarrantyYearsSql > 0 THEN NULLIF(cl.hdd_inst_date, '0000-00-00') ELSE NULL END AS warranty_start_date,
        CASE WHEN NULLIF(cl.hdd_inst_date, '0000-00-00') IS NOT NULL AND $hddWarrantyYearsSql > 0 THEN DATE_ADD(NULLIF(cl.hdd_inst_date, '0000-00-00'), INTERVAL $hddWarrantyYearsSql YEAR) ELSE NULL END AS warranty_end_date,
        CONVERT('Active' USING utf8mb4) AS status,
        CONVERT('Imported from cctv_list legacy HDD data' USING utf8mb4) AS remarks
    FROM cctv_list cl
    WHERE COALESCE(cl.hdd_vendor, cl.hdd_size_tb, cl.hdd_serial, cl.hdd_inst_date, '') <> ''
";

$where = ["r.status = 'Active'"];
$params = [];
$types = '';

if ($filter === 'expired') {
    $where[] = "r.warranty_end_date IS NOT NULL AND r.warranty_end_date < CURDATE()";
} elseif ($filter === 'expiring60') {
    $where[] = "r.warranty_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)";
} elseif ($filter === 'expiring90') {
    $where[] = "r.warranty_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)";
} elseif ($filter === 'active') {
    $where[] = "r.warranty_end_date >= CURDATE()";
} elseif ($filter === 'no_warranty') {
    $where[] = "r.warranty_end_date IS NULL";
} elseif ($filter !== 'all') {
    $filter = 'expiring30';
    $where[] = "r.warranty_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
}

if ($itemId > 0) {
    $where[] = "r.item_id = ?";
    $params[] = $itemId;
    $types .= 'i';
}

if ($vendorId > 0) {
    $where[] = "r.vendor_id = ?";
    $params[] = $vendorId;
    $types .= 'i';
}

if ($sourceType !== '') {
    $where[] = "r.source_type = ?";
    $params[] = $sourceType;
    $types .= 's';
}

if ($search !== '') {
    $where[] = "(r.atm_id LIKE ? OR r.branch_name LIKE ? OR r.booth_name LIKE ? OR r.item_name LIKE ? OR r.serial_no LIKE ?)";
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term);
    $types .= 'sssss';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$summary = [
    'active_warranty' => 0,
    'expiring_30' => 0,
    'expired' => 0,
    'no_warranty' => 0,
];

$resSummary = $conn->query("
    SELECT
        SUM(CASE WHEN status='Active' AND warranty_end_date >= CURDATE() THEN 1 ELSE 0 END) AS active_warranty,
        SUM(CASE WHEN status='Active' AND warranty_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS expiring_30,
        SUM(CASE WHEN status='Active' AND warranty_end_date < CURDATE() THEN 1 ELSE 0 END) AS expired,
        SUM(CASE WHEN status='Active' AND warranty_end_date IS NULL THEN 1 ELSE 0 END) AS no_warranty
    FROM ($baseRowsSql) r
");
if ($resSummary && $row = $resSummary->fetch_assoc()) {
    foreach ($summary as $key => $value) {
        $summary[$key] = (int)($row[$key] ?? 0);
    }
}

$items = [];
$resItems = $conn->query("SELECT id, item_name FROM cctv_item_master ORDER BY item_name ASC");
while ($resItems && $row = $resItems->fetch_assoc()) {
    $items[] = $row;
}

$vendors = [];
$resVendors = $conn->query("SELECT id, vendor_name FROM cctv_vendors WHERE is_active=1 ORDER BY vendor_name ASC");
while ($resVendors && $row = $resVendors->fetch_assoc()) {
    $vendors[] = $row;
}

$sql = "
SELECT
    r.id,
    r.source_type,
    r.atm_id,
    r.branch_name,
    r.booth_name,
    r.item_name,
    r.vendor_name,
    r.brand,
    r.model,
    r.serial_no,
    r.installation_date,
    r.warranty_start_date,
    r.warranty_end_date,
    DATEDIFF(r.warranty_end_date, CURDATE()) AS days_left,
    r.remarks
FROM ($baseRowsSql) r
$whereSql
ORDER BY
    CASE WHEN r.warranty_end_date IS NULL THEN 1 ELSE 0 END,
    r.warranty_end_date ASC,
    r.branch_name ASC,
    r.item_name ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Report query failed: ' . h($conn->error));
}
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

if ($export) {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=CCTV_Warranty_Report_" . date('Y-m-d_His') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CCTV Warranty Report</title>
    <style>
        body{font-family:Arial, sans-serif;padding:20px;background:#f4f6f9;color:#222;}
        .container{max-width:1450px;margin:auto;background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}
        h2{margin:0;color:#243b53;}
        .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:10px;flex-wrap:wrap;}
        .btn{padding:8px 12px;background:#6c757d;color:#fff;border-radius:6px;text-decoration:none;display:inline-block;font-size:13px;font-weight:bold;border:none;cursor:pointer;}
        .btn-blue{background:#0d6efd;}
        .btn-green{background:#198754;}
        .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:18px 0;}
        .card{border:1px solid #e5e7eb;border-radius:8px;padding:14px;background:#f8fafc;}
        .card .label{font-size:12px;color:#6b7280;text-transform:uppercase;font-weight:bold;}
        .card .num{font-size:28px;font-weight:bold;margin-top:5px;color:#111827;}
        .filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;padding:15px;background:#f8f9fa;border:1px solid #e9ecef;border-radius:8px;margin-bottom:18px;}
        label{font-weight:bold;font-size:12px;color:#555;display:block;margin-bottom:5px;}
        input,select{width:100%;padding:9px;border:1px solid #cbd5e1;border-radius:6px;box-sizing:border-box;}
        table{width:100%;border-collapse:collapse;margin-top:10px;}
        th,td{border:1px solid #ddd;padding:10px;text-align:left;font-size:13px;vertical-align:top;}
        th{background:#f1f5f9;font-weight:bold;}
        .expired{background:#f8d7da;}
        .warning{background:#fff3cd;}
        .ok{background:#e8f5e9;}
        .muted{color:#777;}
        .badge{display:inline-block;padding:3px 8px;border-radius:12px;font-size:11px;font-weight:bold;background:#e5e7eb;}
        .badge.expired-b{background:#dc3545;color:#fff;}
        .badge.warning-b{background:#ffc107;color:#111;}
        .badge.ok-b{background:#198754;color:#fff;}
        .badge.none-b{background:#6c757d;color:#fff;}
        @media print {
            .no-print{display:none!important;}
            body{background:#fff;padding:0;}
            .container{box-shadow:none;max-width:100%;border-radius:0;}
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <?php if (!$export): ?>
    <div class="topbar no-print">
        <h2>CCTV Warranty Report</h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="cctv_dashboard.php" class="btn">CCTV Dashboard</a>
            <a href="dashboard_ajax_v2.php" class="btn">Main Dashboard</a>
            <button onclick="window.print()" class="btn btn-blue">Print</button>
            <a href="?<?= h(http_build_query(array_merge($_GET, ['export' => 'excel']))) ?>" class="btn btn-green">Export Excel</a>
        </div>
    </div>

    <div class="cards no-print">
        <div class="card"><div class="label">Active Warranty</div><div class="num"><?= $summary['active_warranty'] ?></div></div>
        <div class="card"><div class="label">Expiring 30 Days</div><div class="num"><?= $summary['expiring_30'] ?></div></div>
        <div class="card"><div class="label">Expired</div><div class="num"><?= $summary['expired'] ?></div></div>
        <div class="card"><div class="label">No Warranty Date</div><div class="num"><?= $summary['no_warranty'] ?></div></div>
    </div>

    <form method="get" class="filters no-print">
        <div>
            <label>Warranty Filter</label>
            <select name="filter">
                <?php foreach ($filterOptions as $value => $label): ?>
                    <option value="<?= h($value) ?>" <?= selected($filter, $value) ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Item</label>
            <select name="item_id">
                <option value="0">All Items</option>
                <?php foreach ($items as $item): ?>
                    <option value="<?= (int)$item['id'] ?>" <?= selected($itemId, $item['id']) ?>><?= h($item['item_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Vendor</label>
            <select name="vendor_id">
                <option value="0">All Vendors</option>
                <?php foreach ($vendors as $vendor): ?>
                    <option value="<?= (int)$vendor['id'] ?>" <?= selected($vendorId, $vendor['id']) ?>><?= h($vendor['vendor_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Source</label>
            <select name="source_type">
                <option value="">All Sources</option>
                <option value="NEW_SET" <?= selected($sourceType, 'NEW_SET') ?>>New Set</option>
                <option value="SPARE_REPLACEMENT" <?= selected($sourceType, 'SPARE_REPLACEMENT') ?>>Spare Replacement</option>
                <option value="ADVANCE_STOCK" <?= selected($sourceType, 'ADVANCE_STOCK') ?>>Advance Stock</option>
                <option value="OLD_REPAIRED_STOCK" <?= selected($sourceType, 'OLD_REPAIRED_STOCK') ?>>Old Repaired Stock</option>
                <option value="LEGACY_CCTV_LIST" <?= selected($sourceType, 'LEGACY_CCTV_LIST') ?>>CCTV List Existing Data</option>
            </select>
        </div>
        <div>
            <label>Search</label>
            <input type="text" name="search" value="<?= h($search) ?>" placeholder="ATM, branch, item, serial">
        </div>
        <div style="display:flex;align-items:end;gap:8px;">
            <button type="submit" class="btn btn-blue">Apply</button>
            <a href="cctv_warranty_report.php" class="btn">Reset</a>
        </div>
    </form>
    <?php else: ?>
        <h3>CCTV Warranty Report</h3>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ATM ID</th>
                <th>Branch / Booth</th>
                <th>Item</th>
                <th>Vendor</th>
                <th>Brand / Model</th>
                <th>Serial</th>
                <th>Source</th>
                <th>Install Date</th>
                <th>Warranty Start</th>
                <th>Warranty End</th>
                <th>Days Left</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
            <?php while($r = $res->fetch_assoc()):
                $days = $r['days_left'];
                $rowClass = '';
                $badgeClass = 'none-b';
                $statusText = 'No Warranty';
                if ($r['warranty_end_date'] !== null && $r['warranty_end_date'] !== '') {
                    if ((int)$days < 0) {
                        $rowClass = 'expired';
                        $badgeClass = 'expired-b';
                        $statusText = 'Expired';
                    } elseif ((int)$days <= 30) {
                        $rowClass = 'warning';
                        $badgeClass = 'warning-b';
                        $statusText = 'Expiring';
                    } else {
                        $rowClass = 'ok';
                        $badgeClass = 'ok-b';
                        $statusText = 'Active';
                    }
                }
            ?>
            <tr class="<?= h($rowClass) ?>">
                <td><?= h($r['atm_id']) ?></td>
                <td>
                    <strong><?= h($r['branch_name']) ?></strong><br>
                    <span class="muted"><?= h($r['booth_name']) ?></span>
                </td>
                <td><?= h($r['item_name']) ?></td>
                <td><?= h($r['vendor_name']) ?></td>
                <td><?= h(trim(($r['brand'] ?? '') . ' ' . ($r['model'] ?? ''))) ?></td>
                <td><code><?= h($r['serial_no']) ?></code></td>
                <td><?= h(str_replace('_', ' ', $r['source_type'])) ?></td>
                <td><?= h($r['installation_date']) ?></td>
                <td><?= h($r['warranty_start_date']) ?></td>
                <td><?= h($r['warranty_end_date']) ?></td>
                <td><?= $r['warranty_end_date'] ? '<strong>' . (int)$days . '</strong>' : '<span class="muted">N/A</span>' ?></td>
                <td><span class="badge <?= h($badgeClass) ?>"><?= h($statusText) ?></span></td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="12" style="text-align:center;padding:30px;color:#999;">No warranty records found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
