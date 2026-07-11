<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

/* =========================
   PERMANENT DELETE
========================= */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {

    $deleteId = (int)$_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM cctv_item_master WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $deleteId);

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: cctv_item_master.php?msg=deleted");
        exit;
    } else {
        $error = "Delete failed: " . $stmt->error;
        $stmt->close();
    }
}

/* =========================
   SUCCESS MESSAGE AFTER REDIRECT
========================= */
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = "Item deleted successfully.";
}

/* =========================
   TOGGLE ACTIVE STATUS
========================= */
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {

    $id = (int)$_GET['toggle'];

    $stmt = $conn->prepare("SELECT is_active FROM cctv_item_master WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $newStatus = ((int)$row['is_active'] === 1) ? 0 : 1;

        $stmt = $conn->prepare("UPDATE cctv_item_master SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $newStatus, $id);

        if ($stmt->execute()) {
            header("Location: cctv_item_master.php");
            exit;
        } else {
            $error = "Status update failed: " . $stmt->error;
        }

        $stmt->close();
    }
}

/* =========================
   EDIT LOAD
========================= */
$editData = null;

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {

    $editId = (int)$_GET['edit'];

    $stmt = $conn->prepare("SELECT * FROM cctv_item_master WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$editData) {
        $error = "Item not found.";
    }
}

/* =========================
   SAVE / UPDATE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $edit_id = (int)($_POST['edit_id'] ?? 0);
    $item_name = trim($_POST['item_name'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $item_category = trim($_POST['item_category'] ?? '');
    $approved_rate = (float)($_POST['approved_rate'] ?? 0);
    $warranty_years = (int)($_POST['warranty_years'] ?? 0);

    $warranty_applicable = ($warranty_years > 0) ? 1 : 0;
    $requires_brand_model_serial = isset($_POST['requires_brand_model_serial']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $allowedCategories = ['SET_ITEM', 'SPARE_PART', 'ACCESSORY'];

    if ($item_name === '') {
        $error = "Item name is required.";
    } elseif (!in_array($item_category, $allowedCategories, true)) {
        $error = "Invalid item category.";
    } elseif ($approved_rate < 0) {
        $error = "Approved rate cannot be negative.";
    } else {

        if ($edit_id > 0) {
            $stmtCheck = $conn->prepare("SELECT id FROM cctv_item_master WHERE item_name = ? AND item_category = ? AND id <> ? LIMIT 1");
            $stmtCheck->bind_param("ssi", $item_name, $item_category, $edit_id);
        } else {
            $stmtCheck = $conn->prepare("SELECT id FROM cctv_item_master WHERE item_name = ? AND item_category = ? LIMIT 1");
            $stmtCheck->bind_param("ss", $item_name, $item_category);
        }

        $stmtCheck->execute();
        $duplicate = $stmtCheck->get_result()->fetch_assoc();
        $stmtCheck->close();

        if ($duplicate) {
            $error = "Error: Item '$item_name' already exists in category '$item_category'.";
        } else {

            if ($edit_id > 0) {

                $stmt = $conn->prepare("
                    UPDATE cctv_item_master
                    SET item_name = ?, brand = ?, model = ?, item_category = ?,
                        approved_rate = ?, item_price = ?, warranty_applicable = ?,
                        warranty_years = ?, requires_brand_model_serial = ?, is_active = ?
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "ssssddiiiii",
                    $item_name,
                    $brand,
                    $model,
                    $item_category,
                    $approved_rate,
                    $approved_rate,
                    $warranty_applicable,
                    $warranty_years,
                    $requires_brand_model_serial,
                    $is_active,
                    $edit_id
                );

                if ($stmt->execute()) {
                    header("Location: cctv_item_master.php");
                    exit;
                } else {
                    $error = "Update failed: " . $stmt->error;
                }

                $stmt->close();

            } else {

                $stmt = $conn->prepare("
                    INSERT INTO cctv_item_master
                    (item_name, brand, model, item_category, approved_rate, item_price,
                     warranty_applicable, warranty_years, requires_brand_model_serial, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "ssssddiiii",
                    $item_name,
                    $brand,
                    $model,
                    $item_category,
                    $approved_rate,
                    $approved_rate,
                    $warranty_applicable,
                    $warranty_years,
                    $requires_brand_model_serial,
                    $is_active
                );

                if ($stmt->execute()) {
                    header("Location: cctv_item_master.php");
                    exit;
                } else {
                    $error = "Insert failed: " . $stmt->error;
                }

                $stmt->close();
            }
        }
    }
}

/* =========================
   LOAD ITEMS
========================= */
$items = [];
$res = $conn->query("SELECT * FROM cctv_item_master ORDER BY item_name ASC, item_category ASC");

while ($row = $res->fetch_assoc()) {
    $items[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>CCTV Item Master</title>
<style>
body { font-family: Arial; background:#f4f6f9; margin:20px; }
.container { max-width:1300px; margin:auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.08); }
.msg { padding:12px; margin-bottom:15px; border-radius:6px; font-weight:bold; }
.success { background:#d1e7dd; color:#0f5132; }
.error { background:#f8d7da; color:#842029; }
.row { display:flex; gap:15px; margin-bottom:15px; flex-wrap:wrap; }
.col { flex:1; min-width:200px; }
label { display:block; margin-bottom:5px; font-weight:bold; }
input, select { width:100%; padding:9px; border:1px solid #ccc; border-radius:6px; }
button, .btn { padding:10px 18px; border:none; border-radius:6px; cursor:pointer; color:#fff; background:#0d6efd; text-decoration:none; }
table { width:100%; border-collapse:collapse; margin-top:20px; }
th, td { border:1px solid #dee2e6; padding:10px; font-size:13px; }
th { background:#f8f9fa; }
</style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container">

<h2>CCTV Item Master</h2>

<?php if ($message): ?><div class="msg success"><?=h($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="msg error"><?=h($error)?></div><?php endif; ?>

<form method="post">
<input type="hidden" name="edit_id" value="<?=h($editData['id'] ?? '')?>">

<div class="row">
<div class="col"><label>Item Name</label><input type="text" name="item_name" value="<?=h($editData['item_name'] ?? '')?>" required></div>
<div class="col"><label>Brand</label><input type="text" name="brand" value="<?=h($editData['brand'] ?? '')?>"></div>
<div class="col"><label>Model</label><input type="text" name="model" value="<?=h($editData['model'] ?? '')?>"></div>
</div>

<div class="row">
<div class="col">
<label>Category</label>
<select name="item_category" required>
<?php foreach(['SET_ITEM','SPARE_PART','ACCESSORY'] as $cat): ?>
<option value="<?=$cat?>" <?=($editData['item_category']??'')==$cat?'selected':''?>><?=$cat?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col"><label>Approved Rate</label><input type="number" step="0.01" name="approved_rate" value="<?=h($editData['approved_rate'] ?? '0')?>"></div>
<div class="col"><label>Warranty (Years)</label><input type="number" name="warranty_years" value="<?=h($editData['warranty_years'] ?? '0')?>"></div>
</div>

<div style="margin-top:15px;">
<button type="submit"><?= $editData ? 'Update Item' : 'Add Item' ?></button>
</div>

</form>

<table>
<thead>
<tr>
<th>ID</th>
<th>Name</th>
<th>Category</th>
<th>Brand/Model</th>
<th>Rate</th>
<th>Action</th>
</tr>
</thead>
<tbody>

<?php foreach($items as $i): ?>
<tr>
<td><?=$i['id']?></td>
<td><strong><?=h($i['item_name'])?></strong></td>
<td><?=$i['item_category']?></td>
<td><?=h($i['brand'])?> / <?=h($i['model'])?></td>
<td><?=number_format($i['approved_rate'],2)?></td>
<td>
<a href="?edit=<?=$i['id']?>">Edit</a> |
<a href="?toggle=<?=$i['id']?>" onclick="return confirm('Toggle status?')">
<?=$i['is_active']?'Deactivate':'Activate'?>
</a> |
<a href="?delete=<?=$i['id']?>" style="color:red;"
onclick="return confirm('Are you sure you want to permanently delete this item?')">
Delete
</a>
</td>
</tr>
<?php endforeach; ?>

</tbody>
</table>

</div>
</body>
</html>