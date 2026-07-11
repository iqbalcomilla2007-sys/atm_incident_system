<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';
include 'includes/cctv_helpers.php';

Auth::requirePermission('cctv_spare_bill_entry');

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

$spareReqId = isset($_GET['spare_req_id']) ? (int)$_GET['spare_req_id'] : 0;
$editId     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit     = $editId > 0;

$message = '';
$messageType = 'success';

/* =========================
   Fetch Spare Requisition
========================= */
$req = null;
if ($spareReqId > 0) {
    $stmt = $conn->prepare("
        SELECT s.id, s.requisition_no, s.assigned_vendor_id,
               l.atm_id, l.branch_name, l.booth_name
        FROM cctv_spare_requisition s
        INNER JOIN cctv_locations l ON l.id = s.cctv_location_id
        WHERE s.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $spareReqId);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$req) {
        die('Spare requisition not found.');
    }
}

/* =========================
   Vendors
========================= */
$vendors = cctv_fetch_vendor_options($conn);

/* =========================
   Default Form
========================= */
$form = [
    'bill_no'          => '',
    'bill_date'        => date('Y-m-d'),
    'vendor_id'        => $req['assigned_vendor_id'] ?? '',
    'parts_amount'     => '',
    'service_charge'   => '',
    'total_amount'     => '',
    'fad_forward_date' => '',
    'payment_date'     => '',
    'payment_status'   => 'Pending',
    'remarks'          => ''
];

/* =========================
   Edit Load
========================= */
if ($isEdit) {
    $stmt = $conn->prepare("
        SELECT *
        FROM cctv_vendor_bills
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$data) {
        die('Bill not found.');
    }

    foreach ($form as $k => $v) {
        if (isset($data[$k])) {
            $form[$k] = $data[$k];
        }
    }

    $spareReqId = (int)$data['spare_requisition_id'];
}

/* =========================
   SAVE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {

        $spareReqId      = (int)($_POST['spare_req_id'] ?? 0);
        $bill_no         = trim($_POST['bill_no'] ?? '');
        $bill_date       = trim($_POST['bill_date'] ?? '');
        $vendor_id       = (int)($_POST['vendor_id'] ?? 0);
        $parts_amount    = (float)($_POST['parts_amount'] ?? 0);
        $service_charge  = (float)($_POST['service_charge'] ?? 0);
        $total_amount    = $parts_amount + $service_charge;
        $fad_forward     = trim($_POST['fad_forward_date'] ?? '');
        $payment_date    = trim($_POST['payment_date'] ?? '');
        $payment_status  = trim($_POST['payment_status'] ?? 'Pending');
        $remarks         = trim($_POST['remarks'] ?? '');

        if ($bill_no === '') throw new Exception("Bill No required");
        if ($bill_date === '') throw new Exception("Bill Date required");
        if ($vendor_id <= 0) throw new Exception("Vendor required");

        $conn->begin_transaction();

        if ($isEdit) {
            $stmt = $conn->prepare("
                UPDATE cctv_vendor_bills
                SET bill_no=?, bill_date=?, vendor_id=?, parts_amount=?, service_charge=?, total_amount=?,
                    fad_forward_date=?, payment_date=?, payment_status=?, remarks=?
                WHERE id=?
            ");
            $stmt->bind_param(
                "ssidddssssi",
                $bill_no,
                $bill_date,
                $vendor_id,
                $parts_amount,
                $service_charge,
                $total_amount,
                $fad_forward,
                $payment_date,
                $payment_status,
                $remarks,
                $editId
            );
            $stmt->execute();
            $stmt->close();

        } else {
            $stmt = $conn->prepare("
                INSERT INTO cctv_vendor_bills
                (bill_no, bill_date, vendor_id, spare_requisition_id, parts_amount, service_charge, total_amount,
                 fad_forward_date, payment_date, payment_status, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "ssiidddssss",
                $bill_no,
                $bill_date,
                $vendor_id,
                $spareReqId,
                $parts_amount,
                $service_charge,
                $total_amount,
                $fad_forward,
                $payment_date,
                $payment_status,
                $remarks
            );
            $stmt->execute();
            $stmt->close();

            /* update spare status */
            $stmt = $conn->prepare("
                UPDATE cctv_spare_requisition
                SET status='Bill_Submitted'
                WHERE id=?
            ");
            $stmt->bind_param("i", $spareReqId);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
        header("Location: cctv_spare_requisition_list.php?msg=bill_saved");
        exit;

    } catch (Throwable $e) {
        $conn->rollback();
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Spare Bill Entry</title>
    <style>
        body{font-family:Arial;background:#f4f6f9;padding:20px;}
        .box{background:#fff;padding:20px;border-radius:10px;max-width:900px;margin:auto;}
        input,select,textarea{width:100%;padding:10px;margin-bottom:10px;border:1px solid #ccc;border-radius:6px;}
        .btn{padding:10px 15px;background:#0d6efd;color:#fff;border:none;border-radius:6px;}
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="box">
    <h2>Spare Bill Entry</h2>

    <?php if ($req): ?>
        <p><b>Requisition:</b> <?php echo h($req['requisition_no']); ?></p>
        <p><b>ATM:</b> <?php echo h($req['atm_id']); ?></p>
        <p><b>Branch:</b> <?php echo h($req['branch_name']); ?></p>
    <?php endif; ?>

    <?php if ($message): ?>
        <p style="color:red;"><?php echo h($message); ?></p>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="spare_req_id" value="<?php echo (int)$spareReqId; ?>">

        <label>Bill No</label>
        <input type="text" name="bill_no" value="<?php echo h($form['bill_no']); ?>">

        <label>Bill Date</label>
        <input type="date" name="bill_date" value="<?php echo h($form['bill_date']); ?>">

        <label>Vendor</label>
        <select name="vendor_id">
            <option value="">Select</option>
            <?php foreach ($vendors as $v): ?>
                <option value="<?php echo $v['id']; ?>" <?php echo ($form['vendor_id']==$v['id'])?'selected':''; ?>>
                    <?php echo h($v['vendor_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Parts Amount</label>
        <input type="number" step="0.01" name="parts_amount" id="parts_amount" value="<?php echo h($form['parts_amount']); ?>">

        <label>Service Charge</label>
        <input type="number" step="0.01" name="service_charge" id="service_charge" value="<?php echo h($form['service_charge']); ?>">

        <label>Total Amount</label>
        <input type="number" step="0.01" name="total_amount" id="total_amount" value="<?php echo h($form['total_amount']); ?>" readonly>

        <label>FAD Forward Date</label>
        <input type="date" name="fad_forward_date" value="<?php echo h($form['fad_forward_date']); ?>">

        <label>Payment Date</label>
        <input type="date" name="payment_date" value="<?php echo h($form['payment_date']); ?>">

        <label>Payment Status</label>
        <select name="payment_status">
            <?php foreach (['Pending','Forwarded_to_FAD','Paid','Rejected'] as $ps): ?>
                <option value="<?php echo $ps; ?>" <?php echo ($form['payment_status']==$ps)?'selected':''; ?>><?php echo $ps; ?></option>
            <?php endforeach; ?>
        </select>

        <label>Remarks</label>
        <textarea name="remarks"><?php echo h($form['remarks']); ?></textarea>

        <button class="btn">Save Bill</button>
    </form>
</div>

<script>
function calcTotal() {
    let p = parseFloat(document.getElementById('parts_amount').value) || 0;
    let s = parseFloat(document.getElementById('service_charge').value) || 0;
    document.getElementById('total_amount').value = (p + s).toFixed(2);
}

document.getElementById('parts_amount').addEventListener('input', calcTotal);
document.getElementById('service_charge').addEventListener('input', calcTotal);
</script>

</body>
</html>