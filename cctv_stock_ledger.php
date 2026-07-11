<?php include('db_connection.php'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Stock Ledger</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
</head>
<body class="container-fluid py-4">
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
    <div class="card">
        <div class="card-header bg-dark text-white d-flex justify-content-between">
            <h5>Stock Transaction Ledger</h5>
            <a href="cctv_stock_receive.php" class="btn btn-sm btn-info">Add Stock</a>
        </div>
        <div class="card-body">
            <table class="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Item Name</th>
                        <th>Condition</th>
                        <th>Brand/Model</th>
                        <th>Transaction</th>
                        <th>Qty</th>
                        <th>Letter No</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT t.*, i.item_name, s.brand, s.model, s.stock_type 
                            FROM cctv_stock_transactions t
                            JOIN cctv_stock s ON t.stock_id = s.id
                            JOIN cctv_item_master i ON s.item_id = i.id
                            ORDER BY t.id DESC";
                    $res = mysqli_query($conn, $sql);
                    while($row = mysqli_fetch_assoc($res)) {
                        $cls = ($row['stock_type']=='NEW_UNUSED') ? 'badge-success' : 'badge-warning';
                        $lbl = ($row['stock_type']=='NEW_UNUSED') ? 'New Stock' : 'Old Repaired';
                        echo "<tr>
                                <td>".$row['transaction_date']."</td>
                                <td>".$row['item_name']."</td>
                                <td><span class='badge $cls'>$lbl</span></td>
                                <td>".$row['brand']." / ".$row['model']."</td>
                                <td>".$row['transaction_type']."</td>
                                <td>".$row['qty']."</td>
                                <td>".$row['letter_no']."</td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>