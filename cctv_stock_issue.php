<?php
date_default_timezone_set('Asia/Dhaka');
include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('cctv_stock_issue');

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$stocks = [];
$res = $conn->query("
    SELECT s.id, s.qty, s.serial_no, i.item_name
    FROM cctv_stock s
    JOIN cctv_item_master i ON i.id = s.item_id
    WHERE s.status='In_Stock' AND s.qty > 0
    ORDER BY s.id DESC
");
while ($res && $row = $res->fetch_assoc()) $stocks[] = $row;

$locations = [];
$res = $conn->query("
    SELECT id, atm_id, branch_name, booth_name
    FROM cctv_locations
    ORDER BY branch_name ASC
");
while ($res && $row = $res->fetch_assoc()) $locations[] = $row;

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stock_id = (int)($_POST['stock_id'] ?? 0);
        $location_id = (int)($_POST['location_id'] ?? 0);
        $qty_issue = (int)($_POST['qty'] ?? 0);
        $issue_date = trim($_POST['issue_date'] ?? '');
        $letter_no = trim($_POST['letter_no'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if (!$stock_id || !$location_id || !$qty_issue || $issue_date === '') {
            throw new Exception("Required fields missing.");
        }

        $stmt = $conn->prepare("SELECT qty FROM cctv_stock WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $stock_id);
        $stmt->execute();
        $stock = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$stock) throw new Exception("Stock not found.");
        if ($qty_issue > (int)$stock['qty']) throw new Exception("Issue qty exceeds available stock.");

        $stmt = $conn->prepare("SELECT branch_name, booth_name FROM cctv_locations WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $location_id);
        $stmt->execute();
        $loc = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $conn->begin_transaction();

        $remaining = (int)$stock['qty'] - $qty_issue;
        $newStatus = ($remaining <= 0) ? 'Issued' : 'In_Stock';

        $stmt = $conn->prepare("UPDATE cctv_stock SET qty = ?, status = ? WHERE id = ?");
        $stmt->bind_param("isi", $remaining, $newStatus, $stock_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("
            INSERT INTO cctv_stock_transactions
            (stock_id, transaction_type, transaction_date, branch_name, booth_name, qty, letter_no, remarks)
            VALUES (?, 'OUT', ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssiss", $stock_id, $issue_date, $loc['branch_name'], $loc['booth_name'], $qty_issue, $letter_no, $remarks);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $message = "Stock issued successfully.";
    } catch (Throwable $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CCTV Stock Issue</title>
    <style>
        body{font-family:Arial;background:#f4f6f9;padding:20px;}
        .container{max-width:900px;margin:auto;background:#fff;padding:20px;border-radius:10px;}
        input,select,textarea{width:100%;padding:9px;box-sizing:border-box;margin-bottom:10px;}
        .btn{padding:10px 14px;background:#0d6efd;color:#fff;border:none;border-radius:6px;cursor:pointer;}
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <h2>CCTV Stock Issue</h2>
    <?php if($message): ?><p style="color:green;"><?= h($message) ?></p><?php endif; ?>
    <?php if($error): ?><p style="color:red;"><?= h($error) ?></p><?php endif; ?>

    <form method="post">
        <label>Select Stock</label>
        <select name="stock_id" required>
            <option value="">Select</option>
            <?php foreach($stocks as $s): ?>
                <option value="<?= $s['id'] ?>"><?= h($s['item_name'].' | Serial: '.$s['serial_no'].' | Qty: '.$s['qty']) ?></option>
            <?php endforeach; ?>
        </select>

        <label>Select Location</label>
        <select name="location_id" required>
            <option value="">Select</option>
            <?php foreach($locations as $l): ?>
                <option value="<?= $l['id'] ?>"><?= h($l['branch_name'].' - '.$l['booth_name'].' ('.$l['atm_id'].')') ?></option>
            <?php endforeach; ?>
        </select>

        <label>Issue Quantity</label>
        <input type="number" name="qty" min="1" required>

        <label>Issue Date</label>
        <input type="date" name="issue_date" value="<?= date('Y-m-d') ?>" required>

        <label>Letter No</label>
        <input type="text" name="letter_no">

        <label>Remarks</label>
        <textarea name="remarks"></textarea>

        <button class="btn" type="submit">Issue Stock</button>
    </form>
</div>
</body>
</html>