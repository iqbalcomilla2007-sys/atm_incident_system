<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';
include 'includes/cctv_helpers.php';

Auth::requirePermission('cctv_installation_entry');

function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

/* =========================
   SAVE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $requisition_id   = (int)$_POST['requisition_id'];
    $cctv_location_id = (int)$_POST['cctv_location_id'];
    $item_id          = (int)$_POST['item_id'];
    $vendor_id        = (int)$_POST['vendor_id'];
    $brand            = trim($_POST['brand']);
    $model            = trim($_POST['model']);
    $serial_no        = trim($_POST['serial_no']);
    $installation_date= $_POST['installation_date'];

    if ($item_id <= 0 || !$installation_date) {
        $error = "Item and Installation Date required";
    } else {

        try {
            cctv_insert_installed_device($conn, [
                'cctv_location_id' => $cctv_location_id,
                'source_type' => 'NEW_SET',
                'source_reference_id' => $requisition_id,
                'item_id' => $item_id,
                'vendor_id' => $vendor_id,
                'brand' => $brand,
                'model' => $model,
                'serial_no' => $serial_no,
                'installation_date' => $installation_date,
                'status' => 'Active'
            ]);
            $message = "Installation saved with warranty.";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

/* =========================
   ITEM + VENDOR LIST
========================= */
$items = $conn->query("SELECT id,item_name FROM cctv_item_master")->fetch_all(MYSQLI_ASSOC);
$vendors = $conn->query("SELECT id,vendor_name FROM cctv_vendors WHERE is_active=1")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Installation Entry</title>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<h2>Installation Entry</h2>

<?php if($message) echo "<p style='color:green'>$message</p>"; ?>
<?php if($error) echo "<p style='color:red'>$error</p>"; ?>

<form method="post">

<input type="hidden" name="requisition_id" value="<?php echo $_GET['requisition_id'] ?? 0; ?>">
<input type="hidden" name="cctv_location_id" value="<?php echo $_GET['location_id'] ?? 0; ?>">

Item:
<select name="item_id">
<?php foreach($items as $i): ?>
<option value="<?php echo $i['id']; ?>"><?php echo h($i['item_name']); ?></option>
<?php endforeach; ?>
</select><br><br>

Vendor:
<select name="vendor_id">
<?php foreach($vendors as $v): ?>
<option value="<?php echo $v['id']; ?>"><?php echo h($v['vendor_name']); ?></option>
<?php endforeach; ?>
</select><br><br>

Brand: <input name="brand"><br><br>
Model: <input name="model"><br><br>
Serial: <input name="serial_no"><br><br>

Install Date:
<input type="date" name="installation_date"><br><br>

<button type="submit">Save</button>

</form>

</body>
</html>
