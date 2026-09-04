<?php 
require_once __DIR__ . '/init.php'; 
// অথবা যদি সরাসরি db.php ব্যবহার করতে চান, তবে: include 'db.php';
?>
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
                    if ($res && mysqli_num_rows($res) > 0) {
                        while($row = mysqli_fetch_assoc($res)) {
                            $cls = ($row['stock_type']=='NEW_UNUSED') ? 'badge-success' : 'badge-warning';
                            $lbl = ($row['stock_type']=='NEW_UNUSED') ? 'New Stock' : 'Old Repaired';
                            echo "<tr>
                                    <td>".htmlspecialchars($row['transaction_date'])."</td>
                                    <td>".htmlspecialchars($row['item_name'])."</td>
                                    <td><span class='badge $cls'>$lbl</span></td>
                                    <td>".htmlspecialchars($row['brand'])." / ".htmlspecialchars($row['model'])."</td>
                                    <td>".htmlspecialchars($row['transaction_type'])."</td>
                                    <td>".htmlspecialchars($row['qty'])."</td>
                                    <td>".htmlspecialchars($row['letter_no'])."</td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' class='text-center'>No stock transactions found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>