<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function columnExists($conn, $table, $column) {
    $table  = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($res && $res->num_rows > 0);
}

$vendorMobileColumn = columnExists($conn, 'cctv_vendors', 'mobile') ? 'mobile' : 'mobile_no';

/* =========================================================
   FETCH SINGLE REQUISITION (For Reports)
========================================================= */
function fetchReq($conn, $id, $vendorMobileColumn) {
    $mobileSelect = ($vendorMobileColumn === 'mobile_no') ? "v.mobile_no AS mobile" : "v.mobile AS mobile";

    // COLLATE utf8mb4_general_ci added to avoid collation mix error
    $stmt = $conn->prepare("
        SELECT 
            r.*, 
            l.atm_id, 
            l.booth_name, 
            l.branch_name,
            l.zone_name,
            r.branch_contact,
            r.ip_details AS saved_ip_details,
            cl.m_ip AS monitoring_ip,
            cl.ip_address AS internal_ip,
            cl.subnet AS subnet_mask,
            cl.gateway,
            v.vendor_name AS assigned_vendor_name,
            v.contact_person,
            $mobileSelect
        FROM cctv_spare_requisition r
        LEFT JOIN cctv_locations l ON r.cctv_location_id = l.id
        LEFT JOIN cctv_list cl ON TRIM(l.atm_id) COLLATE utf8mb4_general_ci = TRIM(cl.atm_id) COLLATE utf8mb4_general_ci
        LEFT JOIN cctv_vendors v ON r.assigned_vendor_id = v.id
        WHERE r.id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row;
}

function fetchItems($conn, $reqId) {
    $stmt = $conn->prepare("
        SELECT i.*, m.item_name
        FROM cctv_spare_requisition_items i
        LEFT JOIN cctv_item_master m ON i.item_id = m.id
        WHERE i.spare_requisition_id = ?
        ORDER BY i.id ASC
    ");
    $stmt->bind_param("i", $reqId);
    $stmt->execute();
    return $stmt->get_result();
}

/* =========================================================
   VENDOR FORWARDING FORMAT
========================================================= */
if (isset($_GET['forwarding']) && $_GET['forwarding'] === 'vendor' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $r = fetchReq($conn, $id, $vendorMobileColumn);
    if (!$r) die('Requisition not found.');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Vendor Forwarding</title>
<style>
body{font-family:Arial,sans-serif;margin:25px;line-height:1.5;background:#f4f7fa;}
.box{max-width:950px;margin:auto;background:#fff;padding:25px;border:1px solid #ddd;border-radius:8px;}
.btn{padding:8px 12px;border:0;border-radius:5px;background:#0d6efd;color:#fff;cursor:pointer;text-decoration:none;display:inline-block;}
@media print{.no-print{display:none;} body{background:#fff;}}
</style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="box">
    <div class="no-print" style="margin-bottom:15px;">
        <button class="btn" onclick="copyText()">Copy Text</button>
        <button class="btn" onclick="window.print()">Print</button>
        <a class="btn" style="background:#6c757d;" href="cctv_spare_requisition_list.php">Back</a>
    </div>

    Attention: <?= h($r['contact_person'] ?? '-') ?>, Mobile: <?= h($r['mobile'] ?? '-') ?><br><br>
    Subject: Request for installation/replacement of CCTV spare item(s) against Requisition No. <?= h($r['requisition_no'] ?? '-') ?>
    <br><br>
    You are requested to arrange necessary installation/replacement of the following CCTV spare item(s) at the mentioned ATM Booth on urgent basis.
    <br>
    <strong>ATM ID:</strong> <?= h($r['atm_id'] ?? '-') ?><br>
    <strong>Booth Name:</strong> <?= h($r['booth_name'] ?? '-') ?><br>
    <strong>Branch Name:</strong> <?= h($r['branch_name'] ?? '-') ?><br>
    <strong>Branch Contact:</strong> <?= h($r['branch_contact'] ?? '-') ?><br><br>
    <strong>IP Details:</strong> <?php
        if (!empty($r['saved_ip_details'])) {
            echo h($r['saved_ip_details']);
        } else {
            $ipParts = [];
            if (!empty($r['monitoring_ip'])) $ipParts[] = 'Mon: ' . $r['monitoring_ip'];
            if (!empty($r['internal_ip'])) $ipParts[] = 'Int: ' . $r['internal_ip'];
            echo h(!empty($ipParts) ? implode(' | ', $ipParts) : '-');
        }
    ?><br><br>
    Please reply with technician details.
</div>
<script>
function copyText(){
    const text = document.body.innerText.replace(/Copy Text|Print|Back/g, '').trim();
    navigator.clipboard.writeText(text).then(() => alert('Copied.'));
}
</script>
</body>
</html>
<?php exit; }

/* =========================================================
   LIST PAGE LOGIC
========================================================= */
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$mobileSelect = ($vendorMobileColumn === 'mobile_no') ? "v.mobile_no AS mobile" : "v.mobile AS mobile";

// Added COLLATE fix and ensuring all columns are selected
$sql = "SELECT 
            r.*, 
            l.atm_id, 
            l.booth_name, 
            l.branch_name,
            l.zone_name,
            cl.m_ip, 
            cl.ip_address, 
            cl.subnet, 
            cl.gateway,
            v.vendor_name AS assigned_vendor_name,
            v.contact_person,
            $mobileSelect,
            COALESCE((SELECT SUM(qty * item_price) FROM cctv_spare_requisition_items WHERE spare_requisition_id = r.id), 0) AS item_amount,
            (SELECT remark FROM cctv_spare_requisition_remarks WHERE spare_requisition_id = r.id ORDER BY id DESC LIMIT 1) AS latest_remark
        FROM cctv_spare_requisition r
        LEFT JOIN cctv_locations l ON r.cctv_location_id = l.id
        LEFT JOIN cctv_list cl ON TRIM(l.atm_id) COLLATE utf8mb4_general_ci = TRIM(cl.atm_id) COLLATE utf8mb4_general_ci
        LEFT JOIN cctv_vendors v ON r.assigned_vendor_id = v.id
        WHERE 1=1";

if ($search !== '') {
    $searchEsc = $conn->real_escape_string($search);
    $sql .= " AND (r.requisition_no LIKE '%$searchEsc%' OR l.atm_id LIKE '%$searchEsc%' OR l.booth_name LIKE '%$searchEsc%' OR v.vendor_name LIKE '%$searchEsc%')";
}
if ($status_filter !== '') {
    $sql .= " AND r.status = '" . $conn->real_escape_string($status_filter) . "'";
}
$sql .= " ORDER BY r.id DESC";

$res = $conn->query($sql);

if (!$res) {
    die("Query failed: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCTV Spare Requisition List</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f4f7fa;margin:0;padding:20px;}
        .container{max-width:1600px;margin:auto;background:#fff;padding:25px;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);}
        table{width:100%;border-collapse:collapse;margin-top:15px;}
        th,td{border:1px solid #dee2e6;padding:10px;text-align:left;font-size:13px;vertical-align:top;}
        th{background:#f8f9fa;}
        .btn{padding:5px 10px;border-radius:6px;text-decoration:none;color:#fff;font-size:11px;margin:2px;display:inline-block;font-weight:bold;border:none;}
        .btn-blue{background:#0d6efd;} .btn-success{background:#198754;} .btn-warning{background:#ffc107;color:#000;}
        .status-badge{padding:4px 8px;border-radius:12px;font-size:11px;color:#fff;background:#999;}
        .small-text{font-size:11px; color:#666;}
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <h2 style="margin:0;color:#0d6efd;">CCTV Spare Requisitions</h2>
        <div>
            <a href="cctv_dashboard.php" class="btn btn-blue" style="background:#6c757d;">Dashboard</a>
            <a href="cctv_spare_requisition.php" class="btn btn-success">+ New Requisition</a>
        </div>
    </div>

    <form method="GET" style="margin-bottom:20px; display:flex; gap:10px;">
        <input type="text" name="search" placeholder="Search ATM/Booth/Req..." value="<?= h($search) ?>" style="padding:10px; border:1px solid #ccc; border-radius:6px; width:350px;">
        <button type="submit" class="btn btn-blue" style="font-size:14px; padding:8px 20px;">Filter</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>SL</th>
                <th>Req. Details</th>
                <th>ATM ID</th>
                <th>Location Details</th>
                <th>Branch Contact</th> <!-- Added This -->
                <th>IP Details</th>
                <th>Assigned Vendor</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php $sl=1; while($r = $res->fetch_assoc()): ?>
            <tr>
                <td><?= $sl++ ?></td>
                <td><strong><?= h($r['requisition_no']) ?></strong><br><span class="small-text"><?= date('d-M-Y', strtotime($r['application_date'])) ?></span></td>
                <td><?= h($r['atm_id']) ?></td>
                <td>
                    <div style="font-weight:bold;"><?= h($r['booth_name']) ?></div>
                    <div class="small-text"><?= h($r['branch_name']) ?></div>
                </td>
                <td><?= h($r['branch_contact'] ?: '-') ?></td> <!-- Added This -->
                <td>
                    <?php 
                        if (!empty($r['ip_details'])) {
                            echo '<span style="color:#0d6efd; font-weight:bold;">' . h($r['ip_details']) . '</span>';
                        } else {
                            $ipParts = [];
                            if (!empty($r['m_ip'])) $ipParts[] = 'M: ' . $r['m_ip'];
                            if (!empty($r['ip_address'])) $ipParts[] = 'I: ' . $r['ip_address'];
                            echo h(!empty($ipParts) ? implode(' | ', $ipParts) : '-');
                        }
                    ?>
                </td>
                <td>
                    <?= h($r['assigned_vendor_name'] ?: '-') ?>
                    <div class="small-text"><?= h($r['contact_person']) ?></div>
                </td>
                <td style="font-weight:bold;color:#198754;">৳<?= number_format($r['item_amount'] + $r['service_charge'], 2) ?></td>
                <td><span class="status-badge"><?= h($r['status']) ?></span></td>
                <td>
                    <a href="cctv_spare_requisition.php?id=<?= $r['id'] ?>" class="btn btn-blue">Edit</a>
                    <a href="cctv_spare_requisition_list.php?forwarding=vendor&id=<?= $r['id'] ?>" target="_blank" class="btn btn-warning">Forward</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>