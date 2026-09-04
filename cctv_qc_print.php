<?php
date_default_timezone_set('Asia/Dhaka');
include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

$requisition_id = isset($_GET['requisition_id']) ? (int)$_GET['requisition_id'] : 0;
if ($requisition_id <= 0) {
    die('Invalid requisition ID.');
}

$stmt = $conn->prepare("
    SELECT r.*, q.*
    FROM cctv_requisition r
    LEFT JOIN cctv_qc_entries q ON q.requisition_id = r.id
    WHERE r.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $requisition_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    die('Data not found.');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CCTV QC Print</title>
    <style>
        body { font-family: Arial; margin:20px; }
        .container { max-width:900px; margin:auto; }
        h2, h3, p { text-align:center; margin:4px 0; }
        table { width:100%; border-collapse:collapse; margin-top:20px; }
        th, td { border:1px solid #000; padding:8px; vertical-align:top; }
        th { width:35%; background:#f2f2f2; }
        .no-print { margin-bottom:15px; }
        .btn {
            display:inline-block; padding:8px 14px; background:#0d6efd; color:#fff;
            text-decoration:none; border-radius:4px; margin-right:8px;
        }
        @media print {
            .no-print { display:none; }
            body { margin:0; }
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <div class="no-print">
        <a href="cctv_requisition_view.php?id=<?= (int)$requisition_id ?>" class="btn">Back</a>
        <a href="#" onclick="window.print();" class="btn">Print</a>
    </div>

    <h2>Islami Bank Bangladesh PLC</h2>
    <h3>ATM Management Division, DBW</h3>
    <p>CCTV QC Report</p>

    <table>
        <tr><th>Requisition ID</th><td><?= (int)$data['id'] ?></td></tr>
        <tr><th>ATM ID</th><td><?= h($data['atm_id'] ?? '') ?></td></tr>
        <tr><th>Branch Name</th><td><?= h($data['branch_name'] ?? '') ?></td></tr>
        <tr><th>Booth Name</th><td><?= h($data['booth_name'] ?? '') ?></td></tr>
        <tr><th>Vendor Name</th><td><?= h($data['vendor_name'] ?? $data['selected_vendor_name'] ?? '') ?></td></tr>
        <tr><th>QC Date</th><td><?= h($data['qc_date'] ?? '') ?></td></tr>
        <tr><th>QC Status</th><td><?= h($data['qc_status'] ?? '') ?></td></tr>
        <tr><th>Checked By</th><td><?= h($data['checked_by'] ?? '') ?></td></tr>
        <tr><th>DVR OK</th><td><?= !empty($data['dvr_ok']) ? 'Yes' : 'No' ?></td></tr>
        <tr><th>Camera OK</th><td><?= !empty($data['camera_ok']) ? 'Yes' : 'No' ?></td></tr>
        <tr><th>HDD OK</th><td><?= !empty($data['hdd_ok']) ? 'Yes' : 'No' ?></td></tr>
        <tr><th>SMPS OK</th><td><?= !empty($data['smps_ok']) ? 'Yes' : 'No' ?></td></tr>
        <tr><th>Adapter OK</th><td><?= !empty($data['adapter_ok']) ? 'Yes' : 'No' ?></td></tr>
        <tr><th>Accessories OK</th><td><?= !empty($data['accessories_ok']) ? 'Yes' : 'No' ?></td></tr>
        <tr><th>Remarks</th><td><?= nl2br(h($data['remarks'] ?? '')) ?></td></tr>
    </table>

    <br><br><br>
    <table style="border:none;">
        <tr style="border:none;">
            <td style="border:none; text-align:center;">______________________<br>QC Member</td>
            <td style="border:none; text-align:center;">______________________<br>QC Member</td>
            <td style="border:none; text-align:center;">______________________<br>Authorized Signatory</td>
        </tr>
    </table>
</div>
</body>
</html>