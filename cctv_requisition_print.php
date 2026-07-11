<?php
date_default_timezone_set('Asia/Dhaka');
include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('Invalid requisition ID.');
}

$stmt = $conn->prepare("SELECT * FROM cctv_requisition WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    die('Requisition not found.');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CCTV Requisition Print</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color:#000; }
        .container { max-width: 900px; margin: auto; }
        h2, h3, p { text-align: center; margin: 4px 0; }
        table { width:100%; border-collapse: collapse; margin-top:20px; }
        th, td { border:1px solid #000; padding:8px; text-align:left; vertical-align:top; }
        th { width:30%; background:#f2f2f2; }
        .no-print { margin-bottom:15px; }
        .btn {
            display:inline-block; padding:8px 14px; background:#0d6efd; color:#fff;
            text-decoration:none; border-radius:4px; margin-right:8px;
        }
        @media print {
            .no-print { display:none; }
            body { margin:0; }
            .container { max-width:100%; }
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <div class="no-print">
        <a href="cctv_requisition_view.php?id=<?= (int)$data['id'] ?>" class="btn">Back</a>
        <a href="#" onclick="window.print();" class="btn">Print</a>
    </div>

    <h2>Islami Bank Bangladesh PLC</h2>
    <h3>ATM Management Division, DBW</h3>
    <p>CCTV New Set Requisition</p>

    <table>
        <tr><th>Requisition ID</th><td><?= (int)$data['id'] ?></td></tr>
        <tr><th>ATM ID</th><td><?= h($data['atm_id'] ?? '') ?></td></tr>
        <tr><th>Branch Name</th><td><?= h($data['branch_name'] ?? '') ?></td></tr>
        <tr><th>Booth Name</th><td><?= h($data['booth_name'] ?? '') ?></td></tr>
        <tr><th>No. of ATM</th><td><?= h($data['no_of_atm'] ?? '') ?></td></tr>
        <tr><th>Branch Contact</th><td><?= h($data['branch_contact'] ?? '') ?></td></tr>
        <tr><th>Requisition Date</th><td><?= h($data['requisition_date'] ?? '') ?></td></tr>
        <tr><th>Send to CPD Date</th><td><?= h($data['send_to_cpd_date'] ?? '') ?></td></tr>
        <tr><th>Work Order Date</th><td><?= h($data['work_order_date'] ?? '') ?></td></tr>
        <tr><th>Selected Vendor</th><td><?= h($data['selected_vendor_name'] ?? '') ?></td></tr>
        <tr><th>Requisition Status</th><td><?= h($data['requisition_status'] ?? '') ?></td></tr>
        <tr><th>Installation Status</th><td><?= h($data['installation_status'] ?? '') ?></td></tr>
        <tr><th>Cause / Requirement</th><td><?= nl2br(h($data['cause'] ?? '')) ?></td></tr>
    </table>

    <br><br><br>
    <table style="border:none;">
        <tr style="border:none;">
            <td style="border:none; text-align:center;">______________________<br>Prepared By</td>
            <td style="border:none; text-align:center;">______________________<br>Checked By</td>
            <td style="border:none; text-align:center;">______________________<br>Approved By</td>
        </tr>
    </table>
</div>
</body>
</html>
2) আগে Work Order table তৈরি করুন
CREATE TABLE cctv_work_orders (
    id INT(11) NOT NULL AUTO_INCREMENT,
    requisition_id INT(11) NOT NULL,
    vendor_id INT(11) DEFAULT NULL,
    vendor_name VARCHAR(150) DEFAULT NULL,
    work_order_no VARCHAR(100) DEFAULT NULL,
    work_order_date DATE DEFAULT NULL,
    delivery_deadline DATE DEFAULT NULL,
    work_order_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    remarks TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_workorder_requisition (requisition_id),
    KEY idx_workorder_vendor_id (vendor_id),
    CONSTRAINT fk_workorder_requisition
        FOREIGN KEY (requisition_id) REFERENCES cctv_requisition(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);