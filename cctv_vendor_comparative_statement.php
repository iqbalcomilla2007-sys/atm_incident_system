<?php
date_default_timezone_set('Asia/Dhaka');
include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

$requisition_id = isset($_GET['requisition_id']) ? (int)$_GET['requisition_id'] : 0;

$requisitions = [];
$res = $conn->query("SELECT id, atm_id, branch_name, booth_name FROM cctv_requisition ORDER BY id DESC");
while ($row = $res->fetch_assoc()) {
    $requisitions[] = $row;
}

$req = null;
$bids = [];

if ($requisition_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM cctv_requisition WHERE id = ?");
    $stmt->bind_param("i", $requisition_id);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT *
        FROM cctv_tender_bids
        WHERE requisition_id = ?
        ORDER BY quoted_amount ASC, warranty_months DESC, delivery_days ASC
    ");
    $stmt->bind_param("i", $requisition_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $bids[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CCTV Vendor Comparative Statement</title>
    <style>
        body { font-family: Arial; background:#f6f8fb; margin:20px; }
        .container { max-width:1200px; margin:auto; background:#fff; padding:20px; border-radius:8px; }
        .no-print { margin-bottom:15px; }
        .row { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:15px; }
        .col { flex:1; min-width:250px; }
        label { display:block; margin-bottom:5px; font-weight:bold; }
        select { width:100%; padding:8px; }
        button, .btn {
            display:inline-block; padding:10px 16px; border:none; border-radius:4px;
            background:#0d6efd; color:#fff; text-decoration:none; cursor:pointer;
        }
        table { width:100%; border-collapse:collapse; margin-top:20px; }
        th, td { border:1px solid #ddd; padding:8px; vertical-align:top; }
        th { background:#f0f3f7; }
        h2, h3, p { text-align:center; margin:4px 0; }
        @media print {
            .no-print { display:none !important; }
            body { background:#fff; margin:0; }
            .container { max-width:100%; }
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <div class="no-print">
        <form method="get">
            <div class="row">
                <div class="col">
                    <label>Requisition</label>
                    <select name="requisition_id" required>
                        <option value="">Select Requisition</option>
                        <?php foreach ($requisitions as $r): ?>
                            <option value="<?= (int)$r['id'] ?>" <?= ($requisition_id == $r['id']) ? 'selected' : '' ?>>
                                <?= h($r['atm_id']) ?> | <?= h($r['branch_name']) ?> | <?= h($r['booth_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit">Show</button>
            <?php if ($requisition_id > 0): ?>
                <a href="#" onclick="window.print();" class="btn">Print</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($req): ?>
        <h2>Islami Bank Bangladesh PLC</h2>
        <h3>ATM Management Division, DBW</h3>
        <p>Comparative Statement of CCTV Tender Bids</p>
        <p><strong>ATM ID:</strong> <?= h($req['atm_id']) ?> |
           <strong>Branch:</strong> <?= h($req['branch_name']) ?> |
           <strong>Booth:</strong> <?= h($req['booth_name']) ?></p>

        <table>
            <thead>
                <tr>
                    <th>SL</th>
                    <th>Vendor Name</th>
                    <th>Quoted Amount</th>
                    <th>Delivery Days</th>
                    <th>Warranty (Months)</th>
                    <th>Bid Date</th>
                    <th>Selected</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($bids): ?>
                <?php $sl = 1; foreach ($bids as $bid): ?>
                    <tr>
                        <td><?= $sl++ ?></td>
                        <td><?= h($bid['vendor_name']) ?></td>
                        <td><?= number_format((float)$bid['quoted_amount'], 2) ?></td>
                        <td><?= h($bid['delivery_days']) ?></td>
                        <td><?= h($bid['warranty_months']) ?></td>
                        <td><?= h($bid['bid_date']) ?></td>
                        <td><?= !empty($bid['is_selected']) ? 'Yes' : 'No' ?></td>
                        <td><?= h($bid['remarks']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8" style="text-align:center;">No bid found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <br><br>
        <table style="border:none;">
            <tr style="border:none;">
                <td style="border:none; text-align:center;">_____________________<br>Member</td>
                <td style="border:none; text-align:center;">_____________________<br>Member</td>
                <td style="border:none; text-align:center;">_____________________<br>Convener</td>
            </tr>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
