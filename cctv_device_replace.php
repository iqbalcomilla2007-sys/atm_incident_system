<?php
include 'auth_check.php';
include 'db.php';
include 'includes/cctv_helpers.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        cctv_replace_device($conn, $_POST);
        $message = "Device replaced successfully";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// active devices
$devices = $conn->query("
SELECT i.id, im.item_name, i.serial_no
FROM cctv_installed_devices i
JOIN cctv_item_master im ON im.id=i.item_id
WHERE i.status='Active'
")->fetch_all(MYSQLI_ASSOC);

$vendors = $conn->query("SELECT id,vendor_name FROM cctv_vendors WHERE is_active=1")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Device Replacement</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; margin: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        h2 { margin-top: 0; color: #1e293b; }
        input, select { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
        button { background: #0d6efd; color: #fff; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
<h2>Device Replacement</h2>

<?php if($message) echo $message; ?>
<?php if($error) echo $error; ?>

<form method="post">

Old Device:
<select name="old_device_id">
<?php foreach($devices as $d): ?>
<option value="<?php echo $d['id']; ?>">
<?php echo $d['item_name']." | ".$d['serial_no']; ?>
</option>
<?php endforeach; ?>
</select><br><br>

Vendor:
<select name="vendor_id">
<?php foreach($vendors as $v): ?>
<option value="<?php echo $v['id']; ?>"><?php echo $v['vendor_name']; ?></option>
<?php endforeach; ?>
</select><br><br>

Item ID: <input name="item_id"><br><br>
Brand: <input name="brand"><br><br>
Model: <input name="model"><br><br>
Serial: <input name="serial_no"><br><br>

Date: <input type="date" name="installation_date"><br><br>

Reason: <input name="reason"><br><br>

<input type="hidden" name="source_reference_id" value="0">

<button>Replace</button>

</form>
</div>
</body>
</html>