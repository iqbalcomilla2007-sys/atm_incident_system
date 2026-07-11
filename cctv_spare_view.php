<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die("Invalid ID");
}

/* =========================
   FETCH SPARE DATA
========================= */
$sql = "
SELECT s.*,
       l.atm_id,
       l.branch_name,
       l.booth_name,
       v.vendor_name
FROM cctv_spare_requisition s
LEFT JOIN cctv_locations l ON l.id = s.cctv_location_id
LEFT JOIN cctv_vendors v ON v.id = s.assigned_vendor_id
WHERE s.id = ?
LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    die("Data not found");
}

/* =========================
   FETCH ITEMS
========================= */
$items = [];
$res = $conn->query("
SELECT i.*, m.item_name
FROM cctv_spare_requisition_items i
LEFT JOIN cctv_item_master m ON m.id = i.item_id
WHERE i.spare_requisition_id = {$id}
");

if ($res) {
    while($row = $res->fetch_assoc()){
        $items[] = $row;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Spare Requisition View</title>
    <style>
        body{font-family:Arial;background:#f4f6f9;padding:20px;}
        .card{background:#fff;padding:20px;border-radius:10px;margin-bottom:15px;}
        table{width:100%;border-collapse:collapse;margin-top:10px;}
        th,td{border:1px solid #ddd;padding:8px;font-size:13px;}
        th{background:#eee;}
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<h2>Spare Requisition Details</h2>

<div class="card">
<b>Requisition No:</b> <?=h($data['requisition_no'])?><br>
<b>Date:</b> <?=h($data['application_date'])?><br>
<b>ATM ID:</b> <?=h($data['atm_id'])?><br>
<b>Branch:</b> <?=h($data['branch_name'])?><br>
<b>Branch Contact:</b> <?=h($data['branch_contact'] ?? '')?><br>
<b>Booth:</b> <?=h($data['booth_name'])?><br>
<b>Vendor:</b> <?=h($data['vendor_name'])?><br>
<b>Status:</b> <?=h($data['status'])?><br>
<b>Problem:</b> <?=h($data['problem_details'])?><br>
</div>

<div class="card">
<h3>Items</h3>
<table>
<tr>
<th>Item</th>
<th>Qty</th>
<th>Source</th>
</tr>

<?php foreach($items as $it): ?>
<tr>
<td><?=h($it['item_name'])?></td>
<td><?=h($it['qty'])?></td>
<td><?=h($it['source_from'])?></td>
</tr>
<?php endforeach; ?>

</table>
</div>

</body>
</html>