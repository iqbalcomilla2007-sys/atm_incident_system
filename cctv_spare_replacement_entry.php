<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';
include 'includes/cctv_helpers.php';

Auth::requirePermission('cctv_spare_replacement_entry');

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

$spareReqId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($spareReqId <= 0) {
    die("Invalid spare requisition ID.");
}

/* =========================
   Fetch Requisition + Location
========================= */
$stmt = $conn->prepare("
    SELECT s.*, l.id AS location_id, l.atm_id, l.branch_name, l.booth_name
    FROM cctv_spare_requisition s
    INNER JOIN cctv_locations l ON l.id = s.cctv_location_id
    WHERE s.id = ?
");
$stmt->bind_param("i", $spareReqId);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$req) {
    die("Requisition not found.");
}

$locationId = (int)$req['location_id'];

/* =========================
   Existing Installed Devices
========================= */
$devices = [];
$res = $conn->query("
    SELECT d.id, d.item_id, i.item_name, d.serial_no
    FROM cctv_installed_devices d
    INNER JOIN cctv_item_master i ON i.id = d.item_id
    WHERE d.cctv_location_id = $locationId
      AND d.status = 'Active'
");

while ($row = $res->fetch_assoc()) {
    $devices[] = $row;
}

/* =========================
   Items + Vendors
========================= */
$items = cctv_fetch_item_options($conn);
$vendors = cctv_fetch_vendor_options($conn);

$message = '';

/* =========================
   SAVE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {

        $old_device_id = (int)$_POST['old_device_id'];
        $item_id       = (int)$_POST['item_id'];
        $vendor_id     = $_POST['vendor_id'] ?: null;

        $brand   = trim($_POST['brand']);
        $model   = trim($_POST['model']);
        $serial  = trim($_POST['serial_no']);
        $install = $_POST['installation_date'];
        if (!$item_id || !$install) {
            throw new Exception("Item and installation date required.");
        }

        $conn->begin_transaction();

        /* 1. mark old device replaced */
        if ($old_device_id > 0) {
            $stmt = $conn->prepare("
                UPDATE cctv_installed_devices
                SET status='Replaced'
                WHERE id=?
            ");
            $stmt->bind_param("i", $old_device_id);
            $stmt->execute();
            $stmt->close();
        }

        /* 2. insert new device with item-master warranty */
        cctv_insert_installed_device($conn, [
            'cctv_location_id' => $locationId,
            'source_type' => 'SPARE_REPLACEMENT',
            'source_reference_id' => $spareReqId,
            'item_id' => $item_id,
            'vendor_id' => $vendor_id,
            'brand' => $brand,
            'model' => $model,
            'serial_no' => $serial,
            'installation_date' => $install,
            'status' => 'Active',
            'remarks' => 'Installed from spare requisition ' . ($req['requisition_no'] ?? $spareReqId)
        ]);

        /* 3. update spare requisition */
        $stmt = $conn->prepare("
            UPDATE cctv_spare_requisition
            SET status='Installed'
            WHERE id=?
        ");
        $stmt->bind_param("i", $spareReqId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        header("Location: cctv_spare_requisition_list.php?msg=replaced");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Spare Replacement Entry</title>
    <style>
        body{font-family:Arial;background:#f4f6f9;padding:20px;}
        .box{background:#fff;padding:20px;border-radius:10px;max-width:800px;margin:auto;}
        input,select{width:100%;padding:10px;margin-bottom:10px;border:1px solid #ccc;border-radius:6px;}
        .btn{padding:10px 15px;background:#0d6efd;color:#fff;border:none;border-radius:6px;}
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="box">
    <h2>Spare Replacement Entry</h2>

    <p><b>ATM:</b> <?php echo h($req['atm_id']); ?></p>
    <p><b>Branch:</b> <?php echo h($req['branch_name']); ?></p>
    <p><b>Booth:</b> <?php echo h($req['booth_name']); ?></p>

    <?php if ($message): ?>
        <p style="color:red;"><?php echo h($message); ?></p>
    <?php endif; ?>

    <form method="post">

        <label>Old Device (optional)</label>
        <select name="old_device_id">
            <option value="">Select</option>
            <?php foreach ($devices as $d): ?>
                <option value="<?php echo $d['id']; ?>">
                    <?php echo h($d['item_name'].' - '.$d['serial_no']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>New Item</label>
        <select name="item_id" required>
            <option value="">Select</option>
            <?php foreach ($items as $it): ?>
                <option value="<?php echo $it['id']; ?>">
                    <?php echo h($it['item_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Vendor</label>
        <select name="vendor_id">
            <option value="">Select</option>
            <?php foreach ($vendors as $v): ?>
                <option value="<?php echo $v['id']; ?>">
                    <?php echo h($v['vendor_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Brand</label>
        <input type="text" name="brand">

        <label>Model</label>
        <input type="text" name="model">

        <label>Serial No</label>
        <input type="text" name="serial_no">

        <label>Installation Date</label>
        <input type="date" name="installation_date" required>

        <p style="background:#eef6ff;border:1px solid #cfe2ff;border-radius:6px;padding:10px;">
            Warranty will be calculated automatically from Item Master using the installation date.
        </p>

        <button class="btn">Save Replacement</button>

    </form>
</div>

</body>
</html>
