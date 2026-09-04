<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('cctv_installation_report');

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* =========================
   FILTERS
========================= */
$search   = trim($_GET['search'] ?? '');
$status   = trim($_GET['status'] ?? '');
$zone     = trim($_GET['zone'] ?? '');
$itemId   = trim($_GET['item_id'] ?? '');
$vendorId = trim($_GET['vendor_id'] ?? '');

/* =========================
   ITEM LIST
========================= */
$items = [];
$res = $conn->query("
    SELECT id, item_name
    FROM cctv_item_master
    ORDER BY item_name ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
}

/* =========================
   VENDOR LIST
========================= */
$vendors = [];
$res = $conn->query("
    SELECT id, vendor_name
    FROM cctv_vendors
    WHERE is_active = 1
    ORDER BY vendor_name ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $vendors[] = $row;
    }
}

/* =========================
   MAIN QUERY
========================= */
$sql = "
SELECT
    i.id,
    i.source_type,
    i.source_reference_id,
    i.brand,
    i.model,
    i.serial_no,
    i.installation_date,
    i.warranty_start_date,
    i.warranty_end_date,
    i.status AS device_status,
    i.remarks,

    l.atm_id,
    l.branch_name,
    l.booth_name,
    l.zone_name,

    im.item_name,
    v.vendor_name,

    r.requisition_no
FROM cctv_installed_devices i
LEFT JOIN cctv_locations l
    ON l.id = i.cctv_location_id
LEFT JOIN cctv_item_master im
    ON im.id = i.item_id
LEFT JOIN cctv_vendors v
    ON v.id = i.vendor_id
LEFT JOIN cctv_set_requisition r
    ON r.id = i.source_reference_id
WHERE 1=1
";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (
        COALESCE(l.atm_id, '') LIKE ?
        OR COALESCE(l.branch_name, '') LIKE ?
        OR COALESCE(l.booth_name, '') LIKE ?
        OR COALESCE(im.item_name, '') LIKE ?
        OR COALESCE(i.serial_no, '') LIKE ?
        OR COALESCE(i.brand, '') LIKE ?
        OR COALESCE(i.model, '') LIKE ?
        OR COALESCE(r.requisition_no, '') LIKE ?
    )";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'ssssssss';
}

if ($status !== '') {
    $sql .= " AND i.status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($zone !== '') {
    $sql .= " AND l.zone_name = ?";
    $params[] = $zone;
    $types .= 's';
}

if ($itemId !== '') {
    $sql .= " AND i.item_id = ?";
    $params[] = (int)$itemId;
    $types .= 'i';
}

if ($vendorId !== '') {
    $sql .= " AND i.vendor_id = ?";
    $params[] = (int)$vendorId;
    $types .= 'i';
}

$sql .= " ORDER BY i.id DESC";

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

$res = $stmt->get_result();
$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CCTV Installation Report</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; padding:20px; }
        .container { max-width:1450px; margin:auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.08); }
        h2 { margin-top:0; }
        .top-bar { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:15px; }
        .btn { display:inline-block; padding:8px 14px; border:none; border-radius:6px; text-decoration:none; cursor:pointer; }
        .btn-primary { background:#0d6efd; color:#fff; }
        .btn-secondary { background:#6c757d; color:#fff; }
        .filter-box { background:#f8f9fa; padding:15px; border-radius:8px; margin-bottom:15px; }
        .row { display:flex; flex-wrap:wrap; gap:12px; }
        .col { flex:1; min-width:180px; }
        label { display:block; margin-bottom:5px; font-weight:bold; }
        input, select { width:100%; padding:8px; border:1px solid #ccc; border-radius:6px; box-sizing:border-box; }
        table { width:100%; border-collapse:collapse; margin-top:10px; }
        th, td { border:1px solid #ddd; padding:8px; font-size:13px; vertical-align:top; }
        th { background:#f1f1f1; }
        .text-center { text-align:center; }
        .badge {
            display:inline-block;
            padding:4px 8px;
            border-radius:4px;
            color:#fff;
            font-size:12px;
        }
        .Active { background:#198754; }
        .Removed { background:#6c757d; }
        .Faulty { background:#dc3545; }
        .In_Warranty_Claim { background:#0d6efd; }
        .Replaced { background:#fd7e14; }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">

    <h2>CCTV Installation Report</h2>

    <div class="top-bar">
        <div>
            <a class="btn btn-secondary" href="cctv_dashboard.php">Dashboard</a>
        </div>
    </div>

    <form method="get" class="filter-box">
        <div class="row">
            <div class="col">
                <label>Search</label>
                <input type="text" name="search" value="<?php echo h($search); ?>" placeholder="Req/ATM/Branch/Item/Serial">
            </div>

            <div class="col">
                <label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    <?php
                    $statusList = ['Active','Removed','Faulty','In_Warranty_Claim','Replaced'];
                    foreach ($statusList as $st):
                    ?>
                        <option value="<?php echo h($st); ?>" <?php echo ($status === $st ? 'selected' : ''); ?>>
                            <?php echo h($st); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col">
                <label>Zone</label>
                <input type="text" name="zone" value="<?php echo h($zone); ?>" placeholder="Zone name">
            </div>

            <div class="col">
                <label>Item</label>
                <select name="item_id">
                    <option value="">All</option>
                    <?php foreach ($items as $it): ?>
                        <option value="<?php echo (int)$it['id']; ?>" <?php echo ((string)$itemId === (string)$it['id'] ? 'selected' : ''); ?>>
                            <?php echo h($it['item_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col">
                <label>Vendor</label>
                <select name="vendor_id">
                    <option value="">All</option>
                    <?php foreach ($vendors as $v): ?>
                        <option value="<?php echo (int)$v['id']; ?>" <?php echo ((string)$vendorId === (string)$v['id'] ? 'selected' : ''); ?>>
                            <?php echo h($v['vendor_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary" style="width:100%;">Filter</button>
            </div>
        </div>
    </form>

    <div style="overflow:auto;">
        <table>
            <thead>
                <tr>
                    <th>SL</th>
                    <th>Requisition No</th>
                    <th>ATM ID</th>
                    <th>Branch</th>
                    <th>Booth</th>
                    <th>Zone</th>
                    <th>Item</th>
                    <th>Vendor</th>
                    <th>Brand</th>
                    <th>Model</th>
                    <th>Serial No</th>
                    <th>Install Date</th>
                    <th>Warranty Start</th>
                    <th>Warranty End</th>
                    <th>Status</th>
                    <th>Source Type</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="17" class="text-center">No installation record found.</td>
                </tr>
            <?php else: ?>
                <?php $sl = 1; foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo $sl++; ?></td>
                        <td><?php echo h($r['requisition_no']); ?></td>
                        <td><?php echo h($r['atm_id']); ?></td>
                        <td><?php echo h($r['branch_name']); ?></td>
                        <td><?php echo h($r['booth_name']); ?></td>
                        <td><?php echo h($r['zone_name']); ?></td>
                        <td><?php echo h($r['item_name']); ?></td>
                        <td><?php echo h($r['vendor_name']); ?></td>
                        <td><?php echo h($r['brand']); ?></td>
                        <td><?php echo h($r['model']); ?></td>
                        <td><?php echo h($r['serial_no']); ?></td>
                        <td><?php echo h($r['installation_date']); ?></td>
                        <td><?php echo h($r['warranty_start_date']); ?></td>
                        <td><?php echo h($r['warranty_end_date']); ?></td>
                        <td>
                            <span class="badge <?php echo h($r['device_status']); ?>">
                                <?php echo h($r['device_status']); ?>
                            </span>
                        </td>
                        <td><?php echo h($r['source_type']); ?></td>
                        <td><?php echo h($r['remarks']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>