<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';
include 'includes/penalty_functions.php';

Auth::requirePermission('manage_penalty');

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("Invalid ID");

/* ===============================
   FETCH PENALTY + INCIDENT
================================ */
$stmt = $conn->prepare("
    SELECT p.*, a.atm_id, a.problem, a.created_at,
           m.machine_type,
           pm.responsible_vendor_type
    FROM penalty_reports p
    LEFT JOIN atm_update a ON p.incident_id = a.incident_id
    LEFT JOIN atm_master m ON a.atm_id = m.atm_id
    LEFT JOIN problem_master pm ON a.problem = pm.problem_name
    WHERE p.id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) die("Data not found");

/* ===============================
   DETERMINE SERVICE TYPE
================================ */
$atm_id = strtoupper($data['atm_id']);
$machine_type = strtoupper($data['machine_type'] ?? '');
$responsible_vendor_type = strtoupper($data['responsible_vendor_type'] ?? '');

$service_type = 'ATM';

if ($responsible_vendor_type === 'UPS') {
    $service_type = 'UPS';
} elseif ($machine_type === 'CRM') {
    $service_type = 'CRM';
} else {
    $service_type = 'ATM';
}

/* ===============================
   CALCULATE DOWN TIME
================================ */
$penalty_from = $data['penalty_from'];
$current_time = date('Y-m-d H:i:s');

$downMinutes = 0;
if (function_exists('calculateMinutesDiff')) {
    $downMinutes = calculateMinutesDiff($penalty_from, $current_time);
}

/* ===============================
   AUTO CALC
================================ */
$calc = calculatePenaltyAmount(
    $conn,
    $data['vendor_name'],
    $service_type,
    $downMinutes
);

$autoPenalty = (float)($calc['penalty_amount'] ?? 0);

/* ===============================
   UPDATE
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $vendor_name   = trim($_POST['vendor_name']);
    $penalty_from  = trim($_POST['penalty_from']);
    $remarks       = trim($_POST['remarks']);
    $manual_amount = (float)($_POST['penalty_amount']);

    /* re-detect service_type */
    $service_type = 'ATM';

    if ($responsible_vendor_type === 'UPS') {
        $service_type = 'UPS';
    } elseif ($machine_type === 'CRM') {
        $service_type = 'CRM';
    }

    $penalty_from_db = date('Y-m-d H:i:s', strtotime($penalty_from));
    $downMinutes = calculateMinutesDiff($penalty_from_db, date('Y-m-d H:i:s'));

    $calc = calculatePenaltyAmount($conn, $vendor_name, $service_type, $downMinutes);
    $autoPenalty = (float)($calc['penalty_amount'] ?? 0);

    $finalPenalty = ($manual_amount > 0) ? $manual_amount : $autoPenalty;

    $stmt = $conn->prepare("
        UPDATE penalty_reports
        SET vendor_name=?,
            service_type=?,
            penalty_from=?,
            down_time_minutes=?,
            penalty_amount=?,
            remarks=?,
            updated_at=NOW()
        WHERE id=?
    ");

    $stmt->bind_param(
        "sssidsi",
        $vendor_name,
        $service_type,
        $penalty_from_db,
        $downMinutes,
        $finalPenalty,
        $remarks,
        $id
    );

    if ($stmt->execute()) {
        header("Location: penalty.php?incident_id=".$data['incident_id']."&msg=Updated");
        exit;
    } else {
        die("Update failed: ".$stmt->error);
    }
}

/* ===============================
   VENDOR LIST
================================ */
$vendors = $conn->query("SELECT vendor_name FROM vendor_master ORDER BY vendor_name");

?>

<!DOCTYPE html>
<html>
<head>
<title>Edit Penalty</title>
<style>
body{font-family:Arial;background:#f4f7fb;padding:20px;}
.card{background:#fff;padding:20px;border-radius:10px;max-width:600px;margin:auto;}
input,select{width:100%;padding:10px;margin-bottom:12px;}
button{background:red;color:#fff;padding:10px;border:none;}
</style>
</head>

<body>

<div class="card">
<h2>Edit Penalty</h2>

<form method="POST">

Vendor:
<select name="vendor_name">
<?php while($v=$vendors->fetch_assoc()){ ?>
<option value="<?=h($v['vendor_name'])?>"
<?= ($data['vendor_name']==$v['vendor_name'])?'selected':'' ?>>
<?=h($v['vendor_name'])?>
</option>
<?php } ?>
</select>

Penalty From:
<input type="datetime-local" name="penalty_from"
value="<?= date('Y-m-d\TH:i', strtotime($data['penalty_from'])) ?>">

Auto Down Time (min):
<input type="text" value="<?= $downMinutes ?>" readonly>

Auto Penalty:
<input type="text" value="<?= $autoPenalty ?>" readonly>

Manual Penalty Override:
<input type="number" step="0.01" name="penalty_amount"
value="<?= $data['penalty_amount'] ?>">

Remarks:
<textarea name="remarks"><?= h($data['remarks']) ?></textarea>

<button type="submit">Update</button>

</form>

</div>

</body>
</html>