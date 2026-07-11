<?php
include 'db.php';

if (isset($_POST['submit'])) {
    $item_id = $_POST['item_id'];
    $stock_type = $_POST['stock_type'];
    $brand = mysqli_real_escape_string($conn, $_POST['brand']);
    $model = mysqli_real_escape_string($conn, $_POST['model']);
    $serial_no = mysqli_real_escape_string($conn, $_POST['serial_no']);
    $qty = (int)$_POST['qty'];
    $received_date = $_POST['received_date'];
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks'] ?? '');

    // যদি নতুন আইটেম টাইপ করা হয়
    if ($item_id == 'new') {
        $new_name = trim(mysqli_real_escape_string($conn, $_POST['new_item_name']));
        $cat = $_POST['item_category'] ?? 'SPARE_PART';
        $check = $conn->query("SELECT id FROM cctv_item_master WHERE item_name = '$new_name' LIMIT 1");
        if ($check->num_rows > 0) {
            $item_id = $check->fetch_assoc()['id'];
        } else {
            $conn->query("INSERT INTO cctv_item_master (item_name, item_category, is_active) VALUES ('$new_name', '$cat', 1)");
            $item_id = $conn->insert_id;
        }
    }

    $sql = "INSERT INTO cctv_stock (item_id, stock_type, brand, model, serial_no, qty, received_date, status, remarks) 
            VALUES ('$item_id', '$stock_type', '$brand', '$model', '$serial_no', '$qty', '$received_date', 'In_Stock', '$remarks')";

    if ($conn->query($sql)) {
        $stock_id = $conn->insert_id;
        $conn->query("INSERT INTO cctv_stock_transactions (stock_id, transaction_type, transaction_date, qty, remarks) 
                      VALUES ('$stock_id', 'IN', '$received_date', '$qty', 'Initial Stock Receive')");
        echo "<script>alert('Stock Saved Successfully!'); window.location='cctv_stock_ledger.php';</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCTV Stock Receive</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
</head>
<body class="container py-4 bg-light">
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
    <div class="card shadow">
        <div class="card-header bg-primary text-white"><h5>Receive CCTV Stock</h5></div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Select Item</label>
                        <select name="item_id" class="form-control" onchange="if(this.value=='new')document.getElementById('new_item_div').style.display='block'; else document.getElementById('new_item_div').style.display='none';" required>
                            <option value="">-- Choose --</option>
                            <?php 
                            $res = $conn->query("SELECT id, item_name FROM cctv_item_master ORDER BY item_name");
                            while($r = $res->fetch_assoc()) echo "<option value='{$r['id']}'>{$r['item_name']}</option>";
                            ?>
                            <option value="new" style="color:red; font-weight:bold;">+ Add New Item</option>
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Stock Condition</label>
                        <select name="stock_type" class="form-control">
                            <option value="NEW_UNUSED">New / Fresh</option>
                            <option value="OLD_REPAIRED">Old / Repaired</option>
                        </select>
                    </div>
                </div>

                <div id="new_item_div" style="display:none;" class="alert alert-warning">
                    <label>New Item Name</label>
                    <input type="text" name="new_item_name" class="form-control mb-2">
                    <label>Category</label>
                    <select name="item_category" class="form-control">
                        <option value="SPARE_PART">SPARE_PART</option>
                        <option value="SET_ITEM">SET_ITEM</option>
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group"><label>Brand</label><input type="text" name="brand" class="form-control"></div>
                    <div class="col-md-4 form-group"><label>Model</label><input type="text" name="model" class="form-control"></div>
                    <div class="col-md-4 form-group"><label>Serial No</label><input type="text" name="serial_no" class="form-control"></div>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group"><label>Qty</label><input type="number" name="qty" class="form-control" value="1"></div>
                    <div class="col-md-4 form-group"><label>Date</label><input type="date" name="received_date" class="form-control" value="<?=date('Y-m-d')?>"></div>
                    <div class="col-md-4 form-group"><label>Remarks</label><input type="text" name="remarks" class="form-control"></div>
                </div>
                <button name="submit" class="btn btn-success btn-block">Save to Ledger</button>
            </form>
        </div>
    </div>
</body>
</html>