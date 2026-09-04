<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';
include 'includes/cctv_helpers.php';

Auth::requirePermission('cctv_branch_dispatch');

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function colExists($conn, $table, $column) {
    $table  = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($res && $res->num_rows > 0);
}

/* =========================================================
   CREATE / UPDATE LOCATION
   Source: cctv_list.atm_name
   Destination: cctv_locations.booth_name
========================================================= */
function getOrCreateLocationFromCctvList($conn, $atm_id) {
    $stmt = $conn->prepare("
        SELECT atm_id, atm_name, branch_name, zone_name
        FROM cctv_list
        WHERE TRIM(atm_id) = TRIM(?)
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception("CCTV List fetch prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $atm_id);
    $stmt->execute();
    $cctv = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cctv) {
        throw new Exception("Selected ATM ID not found in CCTV List.");
    }

    $atm_id      = trim($cctv['atm_id'] ?? '');
    $booth_name  = trim($cctv['atm_name'] ?? '');
    $branch_name = trim($cctv['branch_name'] ?? '');
    $zone_name   = trim($cctv['zone_name'] ?? '');

    $stmt = $conn->prepare("
        SELECT id
        FROM cctv_locations
        WHERE TRIM(atm_id) = TRIM(?)
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception("Location check prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $atm_id);
    $stmt->execute();
    $loc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($loc) {
        $location_id = (int)$loc['id'];

        $stmt = $conn->prepare("
            UPDATE cctv_locations
            SET booth_name = ?, branch_name = ?, zone_name = ?
            WHERE id = ?
        ");

        if (!$stmt) {
            throw new Exception("Location update prepare failed: " . $conn->error);
        }

        $stmt->bind_param("sssi", $booth_name, $branch_name, $zone_name, $location_id);
        $stmt->execute();
        $stmt->close();

        return $location_id;
    }

    $stmt = $conn->prepare("
        INSERT INTO cctv_locations
        (atm_id, booth_name, branch_name, zone_name)
        VALUES (?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception("Location insert prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ssss", $atm_id, $booth_name, $branch_name, $zone_name);
    $stmt->execute();
    $location_id = $stmt->insert_id;
    $stmt->close();

    return $location_id;
}

/* =========================================================
   PRINT FORWARDING (Updated to Match cctv_list.php Style)
========================================================= */
if (isset($_GET['print']) && $_GET['print'] === 'forwarding' && isset($_GET['id'])) {
    $dispatch_id = (int)$_GET['id'];

    $stmt = $conn->prepare("
        SELECT 
            d.*,
            l.atm_id,
            l.booth_name,
            l.branch_name,
            l.zone_name
        FROM cctv_branch_dispatch d
        LEFT JOIN cctv_locations l ON d.cctv_location_id = l.id
        WHERE d.id = ?
        LIMIT 1
    ");

    if (!$stmt) die("Dispatch fetch prepare failed: " . $conn->error);
    $stmt->bind_param("i", $dispatch_id);
    $stmt->execute();
    $dispatch = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$dispatch) die("Dispatch not found.");

    $hasStockIdInDispatchItems = colExists($conn, 'cctv_branch_dispatch_items', 'stock_id');
    $sqlItems = $hasStockIdInDispatchItems 
        ? "SELECT di.qty, s.brand, s.model, s.serial_no, im.item_name FROM cctv_branch_dispatch_items di LEFT JOIN cctv_stock s ON di.stock_id = s.id LEFT JOIN cctv_item_master im ON di.item_id = im.id WHERE di.dispatch_id = ? ORDER BY di.id ASC"
        : "SELECT di.qty, '' AS brand, '' AS model, '' AS serial_no, im.item_name FROM cctv_branch_dispatch_items di LEFT JOIN cctv_item_master im ON di.item_id = im.id WHERE di.dispatch_id = ? ORDER BY di.id ASC";

    $stmt = $conn->prepare($sqlItems);
    $stmt->bind_param("i", $dispatch_id);
    $stmt->execute();
    $items = $stmt->get_result();
    $stmt->close();

    $dispatchTypeText = $dispatch['dispatch_type'];
    if ($dispatchTypeText === 'OLD_REPAIRED_BACKUP') {
        $dispatchTypeText = 'Old Repaired CCTV Item Sent as Backup';
    } elseif ($dispatchTypeText === 'NEW_TENDER_STOCK') {
        $dispatchTypeText = 'New Tender Stock Item';
    }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CCTV Dispatch Forwarding</title>
    <style>
    @page { size: A4; margin: 0; }
    body { margin: 0; font-family: "Times New Roman", serif; color: #000; }
    .page { width: 210mm; height: 297mm; box-sizing: border-box; padding: 11mm 14mm 8mm 14mm; display: flex; flex-direction: column; overflow: hidden; background: #fff; }
    .main-content { flex: 1; font-size: 12.5px; line-height: 1.32; }
    .letterhead { height: 65px; position: relative; margin-bottom: 8px; }
    .left-logo { position: absolute; left: 0; top: 0; width: 60px; }
    .right-logo { position: absolute; right: 0; top: 3px; width: 225px; text-align: right; }
    .left-logo img, .right-logo img { max-width: 100%; height: auto; }
    .meta { display: flex; justify-content: space-between; margin-bottom: 9px; }
    p { margin: 5px 0; text-align: justify; }
    .subject { font-weight: bold; text-decoration: underline; margin: 8px 0; }
    .data-table { width: 100%; border-collapse: collapse; margin: 7px 0 9px; font-size: 12px; }
    .data-table th, .data-table td { border: 1px solid #333; padding: 4px 6px; }
    .data-table th { background: #eee; text-align: center; }
    .footer-box { text-align: center; font-size: 9.5px; line-height: 1.18; border-top: 1px solid #777; padding-top: 4px; }
    .no-print { position: fixed; top: 10px; right: 10px; z-index: 999; }
    @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="no-print">
    <button onclick="window.print()" style="padding:10px 20px; background:#0d6efd; color:#fff; border:none; cursor:pointer; border-radius:5px;">Print Forwarding</button>
    <button onclick="window.close()" style="padding:10px 20px; background:#6c757d; color:#fff; border:none; cursor:pointer; border-radius:5px;">Close</button>
</div>

<div class="page">
    <div class="main-content">
        <!-- Header with Logos -->
        <div class="letterhead">
            <div class="left-logo"><img src="assets/ibbl_mark.png"></div>
            <div class="right-logo"><img src="assets/ibbl_header_logo.png"></div>
        </div>

        <!-- Ref & Date -->
        <div class="meta">
            <div>Ref: IBBPLC/HO/DBW/ATMMD/CCTV/<?= date('Y') ?>/<?= h($dispatch['letter_no'] ?: $dispatch['dispatch_no']) ?></div>
            <div>Date: <?= date('d.m.Y', strtotime($dispatch['dispatch_date'])) ?></div>
        </div>

        <!-- Recipient -->
        <p>To<br>The Head of Branch / In-charge<br><strong><?= h($dispatch['branch_name'] ?: 'Concerned Branch') ?></strong></p>

        <!-- Subject -->
        <p class="subject">Subject: Forwarding of CCTV equipment(s) for <?= h($dispatch['booth_name'] ?: 'ATM Booth') ?>.</p>

        <p>Muhtaram,<br> Assalamu Alaikum,</p>

        <p>With reference to the above, we are forwarding the following CCTV item(s) to your branch/booth for <strong><?= h($dispatchTypeText) ?></strong>. You are requested to receive the item(s), ensure safe-keeping and acknowledge receipt. The details of the dispatched items are as follows:</p>

        <!-- Item Table -->
        <table class="data-table">
            <thead>
                <tr>
                    <th width="5%">SL</th>
                    <th>Item Name</th>
                    <th>Brand/Model</th>
                    <th>Serial No</th>
                    <th width="10%">Qty</th>
                </tr>
            </thead>
            <tbody>
                <?php $sl = 1; while ($it = $items->fetch_assoc()): ?>
                <tr>
                    <td style="text-align:center;"><?= $sl++ ?></td>
                    <td><?= h($it['item_name'] ?: '-') ?></td>
                    <td><?= h($it['brand']) ?> <?= h($it['model']) ?></td>
                    <td><?= h($it['serial_no'] ?: '-') ?></td>
                    <td style="text-align:center;"><?= h($it['qty']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <p>Please note that ATM Booth CCTV is a critical security control. You are advised to cooperate with the assigned vendor technician for the installation/replacement work and confirm completion to ATM Management Division.</p>

        <p>Ma-assalam,<br>With Best Regards,<br><br><br><br>___________________________<br><strong>Md. Mahbub Al Hassan</strong><br>SVP & Head of ATMMD, DBW</p>
    </div>

    <!-- Footer Box -->
    <div class="footer-box">
        <strong>ATM Management Division, DBW, HO</strong><br>
        75, Dilkusha C/A, Dhaka-1000, Bangladesh; email: group_atmmd@islamibankbd.com
    </div>
</div>

</body>
</html>
<?php
    exit;
}

/* =========================================================
   NORMAL PAGE
========================================================= */
$error = '';
$dispatch_no = cctv_generate_dispatch_no($conn);

/* =========================================================
   SAVE DISPATCH
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        $dispatch_date = trim($_POST['dispatch_date'] ?? '');
        $dispatch_type = trim($_POST['dispatch_type'] ?? '');
        $letter_no     = trim($_POST['letter_no'] ?? '');
        $atm_id        = trim($_POST['atm_id'] ?? '');

        if ($dispatch_date === '') {
            throw new Exception("Dispatch date is required.");
        }

        if ($dispatch_type === '') {
            throw new Exception("Dispatch type is required.");
        }

        if ($atm_id === '') {
            throw new Exception("ATM ID is required.");
        }

        $location_id = getOrCreateLocationFromCctvList($conn, $atm_id);

        $stock_ids = $_POST['stock_id'] ?? [];
        $qtys      = $_POST['qty'] ?? [];

        $validItems = [];

        for ($i = 0; $i < count($stock_ids); $i++) {
            $sid = (int)($stock_ids[$i] ?? 0);
            $qty = (int)($qtys[$i] ?? 0);

            if ($sid > 0 && $qty > 0) {
                $validItems[] = [
                    'stock_id' => $sid,
                    'qty' => $qty
                ];
            }
        }

        if (empty($validItems)) {
            throw new Exception("Please select at least one item from stock.");
        }

        $stmt = $conn->prepare("
            INSERT INTO cctv_branch_dispatch
            (
                dispatch_no,
                dispatch_date,
                dispatch_type,
                letter_no,
                cctv_location_id,
                acknowledgement_status
            )
            VALUES (?, ?, ?, ?, ?, 'Pending')
        ");

        if (!$stmt) {
            throw new Exception("Dispatch insert prepare failed: " . $conn->error);
        }

        $stmt->bind_param("ssssi", $dispatch_no, $dispatch_date, $dispatch_type, $letter_no, $location_id);
        $stmt->execute();
        $dispatchId = $stmt->insert_id;
        $stmt->close();

        $hasQty = colExists($conn, 'cctv_stock', 'qty');
        $dispatchItemsHasStockId = colExists($conn, 'cctv_branch_dispatch_items', 'stock_id');
        $dispatchItemsHasItemId  = colExists($conn, 'cctv_branch_dispatch_items', 'item_id');

        foreach ($validItems as $item) {
            $stockId = (int)$item['stock_id'];
            $qty     = (int)$item['qty'];

            if ($hasQty) {
                $stmt = $conn->prepare("
                    SELECT id, item_id, status, qty
                    FROM cctv_stock
                    WHERE id = ?
                    FOR UPDATE
                ");
            } else {
                $stmt = $conn->prepare("
                    SELECT id, item_id, status
                    FROM cctv_stock
                    WHERE id = ?
                    FOR UPDATE
                ");
            }

            if (!$stmt) {
                throw new Exception("Stock check prepare failed: " . $conn->error);
            }

            $stmt->bind_param("i", $stockId);
            $stmt->execute();
            $stockRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$stockRow) {
                throw new Exception("Selected stock item not found. Stock ID: " . $stockId);
            }

            if (strtolower(trim($stockRow['status'])) !== strtolower('In_Stock')) {
                throw new Exception("Selected item is not in stock. Stock ID: " . $stockId);
            }

            $itemId = (int)($stockRow['item_id'] ?? 0);
            if ($itemId <= 0) {
                throw new Exception("Selected stock item has no valid item_id. Stock ID: " . $stockId);
            }

            if ($hasQty) {
                $availableQty = (int)($stockRow['qty'] ?? 0);

                if ($availableQty < $qty) {
                    throw new Exception("Insufficient stock quantity. Stock ID: " . $stockId);
                }
            } else {
                $qty = 1;
            }

            if ($dispatchItemsHasStockId && $dispatchItemsHasItemId) {
                $stmt = $conn->prepare("
                    INSERT INTO cctv_branch_dispatch_items 
                    (dispatch_id, stock_id, item_id, qty)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("iiii", $dispatchId, $stockId, $itemId, $qty);
            } elseif ($dispatchItemsHasItemId) {
                $stmt = $conn->prepare("
                    INSERT INTO cctv_branch_dispatch_items 
                    (dispatch_id, item_id, qty)
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param("iii", $dispatchId, $itemId, $qty);
            } elseif ($dispatchItemsHasStockId) {
                $stmt = $conn->prepare("
                    INSERT INTO cctv_branch_dispatch_items 
                    (dispatch_id, stock_id, qty)
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param("iii", $dispatchId, $stockId, $qty);
            } else {
                throw new Exception("cctv_branch_dispatch_items table must have item_id or stock_id column.");
            }

            if (!$stmt) {
                throw new Exception("Dispatch item insert prepare failed: " . $conn->error);
            }

            $stmt->execute();
            $stmt->close();

            $remarks = "Dispatched to branch. Dispatch No: " . $dispatch_no;

            $stmt = $conn->prepare("
                INSERT INTO cctv_stock_transactions
                (stock_id, transaction_type, transaction_date, qty, remarks)
                VALUES (?, 'DISPATCH', ?, ?, ?)
            ");

            if (!$stmt) {
                throw new Exception("Stock transaction prepare failed: " . $conn->error);
            }

            $stmt->bind_param("isis", $stockId, $dispatch_date, $qty, $remarks);
            $stmt->execute();
            $stmt->close();

            if ($hasQty) {
                $stmt = $conn->prepare("
                    UPDATE cctv_stock
                    SET 
                        qty = qty - ?,
                        status = CASE 
                            WHEN qty - ? <= 0 THEN 'Dispatched'
                            ELSE 'In_Stock'
                        END
                    WHERE id = ?
                ");
                $stmt->bind_param("iii", $qty, $qty, $stockId);
            } else {
                $stmt = $conn->prepare("
                    UPDATE cctv_stock
                    SET status = 'Dispatched'
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $stockId);
            }

            if (!$stmt) {
                throw new Exception("Stock update prepare failed: " . $conn->error);
            }

            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();

        header("Location: cctv_branch_dispatch.php?msg=success&id=" . $dispatchId);
        exit;

    } catch (Throwable $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

/* =========================================================
   CCTV ATM OPTIONS FROM cctv_list
   IMPORTANT: cctv_list has atm_name, not booth_name
========================================================= */
$cctvAtmOptions = [];

$resAtm = $conn->query("
    SELECT 
        TRIM(atm_id) AS atm_id,
        atm_name,
        branch_name
    FROM cctv_list
    WHERE atm_id IS NOT NULL
      AND TRIM(atm_id) <> ''
    ORDER BY atm_id ASC
");

if ($resAtm) {
    while ($r = $resAtm->fetch_assoc()) {
        $cctvAtmOptions[] = $r;
    }
} else {
    $error = "ATM dropdown query failed: " . $conn->error;
}

/* =========================================================
   STOCK OPTIONS
========================================================= */
$stockOptions = [];

$resS = $conn->query("
    SELECT 
        s.id,
        s.item_id,
        s.brand,
        s.model,
        s.serial_no,
        s.status,
        im.item_name
    FROM cctv_stock s
    LEFT JOIN cctv_item_master im ON im.id = s.item_id
    WHERE s.status = 'In_Stock'
    ORDER BY im.item_name ASC, s.brand ASC, s.model ASC, s.serial_no ASC
");

if ($resS) {
    while ($s = $resS->fetch_assoc()) {
        $stockOptions[] = $s;
    }
} else {
    $error = "Stock dropdown query failed: " . $conn->error;
}

$successId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CCTV Branch Dispatch</title>
<style>
body{font-family:Arial,sans-serif;background:#f4f6f9;padding:20px;font-size:14px;}
.container{max-width:1050px;margin:auto;background:#fff;padding:25px;border-radius:12px;box-shadow:0 5px 15px rgba(0,0,0,0.08);}
h2{color:#0d6efd;margin-top:0;}
.row{display:flex;flex-wrap:wrap;gap:15px;margin-bottom:12px;}
.col{flex:1;min-width:220px;}
label{display:block;font-weight:bold;margin-bottom:5px;color:#555;}
input,select,textarea{width:100%;padding:10px;box-sizing:border-box;border:1px solid #ccc;border-radius:6px;}
input[readonly]{background:#f1f3f5;}
.btn{padding:10px 18px;border:none;border-radius:6px;cursor:pointer;color:#fff;text-decoration:none;font-weight:bold;font-size:13px;display:inline-block;}
.btn-blue{background:#0d6efd;}
.btn-secondary{background:#6c757d;}
.btn-success{background:#198754;}
.btn-danger{background:#dc3545;}
table{width:100%;border-collapse:collapse;margin-top:15px;}
th,td{border:1px solid #ddd;padding:10px;text-align:left;}
th{background:#f8f9fa;}
.alert-success{padding:12px;background:#d1e7dd;color:#0f5132;border:1px solid #badbcc;border-radius:6px;margin-bottom:15px;}
.alert-error{padding:12px;background:#f8d7da;color:#842029;border:1px solid #f5c2c7;border-radius:6px;margin-bottom:15px;}
</style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>


<div class="container">

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
    <h2 style="margin:0;">CCTV Branch Dispatch</h2>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="cctv_dashboard.php" class="btn btn-blue">CCTV Dashboard</a>
        <a href="dashboard_ajax_v2.php" class="btn btn-secondary">Main Dashboard</a>
        <a href="cctv_dispatch_acknowledgement.php" class="btn btn-success">Acknowledgements</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php if (($_GET['msg'] ?? '') === 'success' && $successId > 0): ?>
    <div class="alert-success">
        Dispatch executed successfully.
        <br><br>
        <a class="btn btn-blue" target="_blank" href="cctv_branch_dispatch.php?print=forwarding&id=<?= $successId ?>">
            Print Branch Forwarding
        </a>
    </div>
<?php endif; ?>

<form method="post">

<div class="row">
    <div class="col">
        <label>Dispatch No</label>
        <input type="text" name="dispatch_no" value="<?= h($dispatch_no) ?>" readonly>
    </div>

    <div class="col">
        <label>Dispatch Date</label>
        <input type="date" name="dispatch_date" value="<?= date('Y-m-d') ?>" required>
    </div>

    <div class="col">
        <label>Dispatch Type</label>
        <select name="dispatch_type" required>
            <option value="">-- Select Type --</option>
            <option value="OLD_REPAIRED_BACKUP">Old Repaired Item Sent as Backup</option>
            <option value="NEW_TENDER_STOCK">New Tender Stock Item</option>
        </select>
    </div>
</div>

<div class="row">
    <div class="col">
        <label>ATM ID</label>
        <select name="atm_id" id="atm_id" required>
            <option value="">-- Select ATM ID --</option>
            <?php foreach ($cctvAtmOptions as $atm): ?>
                <option 
                    value="<?= h($atm['atm_id']) ?>"
                    data-booth-name="<?= h($atm['atm_name']) ?>"
                    data-branch-name="<?= h($atm['branch_name']) ?>"
                >
                    <?= h($atm['atm_id']) ?> - <?= h($atm['atm_name']) ?> - <?= h($atm['branch_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col">
        <label>Booth Name</label>
        <input type="text" id="booth_name" readonly>
    </div>

    <div class="col">
        <label>Branch Name</label>
        <input type="text" id="branch_name" readonly>
    </div>
</div>

<div class="row">
    <div class="col">
        <label>Letter / Reference No</label>
        <input type="text" name="letter_no" placeholder="Reference Letter No">
    </div>
</div>

<h3 style="margin-top:30px;border-bottom:1px solid #ddd;padding-bottom:10px;">Items to Dispatch</h3>

<table id="item_table">
<thead>
<tr>
    <th>Item from Stock</th>
    <th style="width:120px;">Qty</th>
    <th style="width:90px;">Action</th>
</tr>
</thead>
<tbody>
<tr>
    <td>
        <select name="stock_id[]" required>
            <option value="">-- Select from Stock --</option>
            <?php foreach ($stockOptions as $s): ?>
                <option value="<?= (int)$s['id'] ?>">
                    <?= h(($s['item_name'] ?: 'Item') . ' - ' . ($s['brand'] ?: '') . ' ' . ($s['model'] ?: '') . ' (' . ($s['serial_no'] ?: 'No Serial') . ')') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </td>
    <td><input type="number" name="qty[]" value="1" min="1" required></td>
    <td>-</td>
</tr>
</tbody>
</table>

<button type="button" class="btn btn-success" style="margin-top:10px;" onclick="addRow()">+ Add More Item</button>

<div style="margin-top:30px;text-align:right;">
    <button type="submit" class="btn btn-blue">Execute Dispatch</button>
</div>

</form>
</div>

<script>
function addRow() {
    const tbody = document.querySelector('#item_table tbody');
    const row = tbody.insertRow();

    row.innerHTML = tbody.rows[0].innerHTML;
    row.cells[2].innerHTML =
        '<button type="button" class="btn btn-danger" style="padding:6px 10px;" onclick="this.closest(\\'tr\\').remove()">X</button>';

    row.querySelectorAll('select').forEach(s => s.selectedIndex = 0);

    const qty = row.querySelector('input[name="qty[]"]');
    if (qty) qty.value = 1;
}

document.getElementById('atm_id').addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];

    document.getElementById('booth_name').value = opt.getAttribute('data-booth-name') || '';
    document.getElementById('branch_name').value = opt.getAttribute('data-branch-name') || '';
});
</script>

</body>
</html>