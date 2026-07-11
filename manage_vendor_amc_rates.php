<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('manage_penalty');

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$message = '';
$messageType = 'success';
$search = trim($_GET['search'] ?? '');
$filter_service_type = trim($_GET['filter_service_type'] ?? '');

/* ===============================
   POST: ADD / UPDATE AMC RATE
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id           = (int)($_POST['id'] ?? 0);
    $vendor_name  = trim($_POST['vendor_name'] ?? '');
    $service_type = strtoupper(trim($_POST['service_type'] ?? 'ATM'));
    $amc_amount   = (float)($_POST['amc_amount'] ?? 0);

    if ($vendor_name === '' || $service_type === '' || $amc_amount <= 0) {
        $message = "Vendor, Service Type and valid AMC Amount are required.";
        $messageType = 'danger';
    } else {
        if ($id > 0) {
            $chk = $conn->prepare("
                SELECT id
                FROM vendor_amc_rates
                WHERE vendor_name = ?
                  AND service_type = ?
                  AND id <> ?
                LIMIT 1
            ");
            if ($chk) {
                $chk->bind_param("ssi", $vendor_name, $service_type, $id);
                $chk->execute();
                $dup = $chk->get_result();
                $chk->close();

                if ($dup && $dup->num_rows > 0) {
                    $message = "Another AMC rate already exists for this vendor and service type.";
                    $messageType = 'danger';
                } else {
                    $stmt = $conn->prepare("
                        UPDATE vendor_amc_rates
                        SET vendor_name = ?, service_type = ?, amc_amount = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    if ($stmt) {
                        $stmt->bind_param("ssdi", $vendor_name, $service_type, $amc_amount, $id);
                        if ($stmt->execute()) {
                            header("Location: manage_vendor_amc_rates.php?msg=updated");
                            exit;
                        } else {
                            $message = "Failed to update AMC rate.";
                            $messageType = 'danger';
                        }
                        $stmt->close();
                    }
                }
            }
        } else {
            $chk = $conn->prepare("
                SELECT id
                FROM vendor_amc_rates
                WHERE vendor_name = ?
                  AND service_type = ?
                LIMIT 1
            ");
            if ($chk) {
                $chk->bind_param("ss", $vendor_name, $service_type);
                $chk->execute();
                $dup = $chk->get_result();
                $chk->close();

                if ($dup && $dup->num_rows > 0) {
                    $message = "AMC rate already exists for this vendor and service type.";
                    $messageType = 'danger';
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO vendor_amc_rates
                        (vendor_name, service_type, amc_amount, active_status, created_at)
                        VALUES (?, ?, ?, 1, NOW())
                    ");
                    if ($stmt) {
                        $stmt->bind_param("ssd", $vendor_name, $service_type, $amc_amount);
                        if ($stmt->execute()) {
                            header("Location: manage_vendor_amc_rates.php?msg=added");
                            exit;
                        } else {
                            $message = "Failed to add AMC rate.";
                            $messageType = 'danger';
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}

/* ===============================
   DELETE
================================ */
if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM vendor_amc_rates WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            header("Location: manage_vendor_amc_rates.php?msg=deleted");
            exit;
        }
        $stmt->close();
    }
}

/* ===============================
   MESSAGE
================================ */
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') {
        $message = "AMC rate added successfully.";
    } elseif ($_GET['msg'] === 'updated') {
        $message = "AMC rate updated successfully.";
    } elseif ($_GET['msg'] === 'deleted') {
        $message = "AMC rate deleted successfully.";
    }
}

/* ===============================
   EDIT DATA
================================ */
$edit = null;
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM vendor_amc_rates WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $edit = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

/* ===============================
   VENDOR LIST
   ONLY ACTIVE VENDORS FROM vendor_master
================================ */
$vendors = [];
$resVendor = $conn->query("
    SELECT vendor_name
    FROM vendor_master
    WHERE status = 1
    ORDER BY vendor_name ASC
");
if ($resVendor) {
    while ($row = $resVendor->fetch_assoc()) {
        $vendors[] = $row['vendor_name'];
    }
}

/* ===============================
   LIST QUERY
================================ */
$sql = "
    SELECT *
    FROM vendor_amc_rates
    WHERE 1 = 1
";
$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (vendor_name LIKE ? OR service_type LIKE ?) ";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

if ($filter_service_type !== '') {
    $sql .= " AND service_type = ? ";
    $params[] = $filter_service_type;
    $types .= 's';
}

$sql .= " ORDER BY vendor_name ASC, service_type ASC, id DESC ";

$list = false;
$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $list = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Vendor AMC Rates</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{
            margin:0;
            padding:20px;
            font-family:Arial,sans-serif;
            background:#f4f7fb;
            color:#1f2937;
        }
        .container{
            max-width:1350px;
            margin:0 auto;
        }
        .hero{
            background:linear-gradient(135deg,#0f172a,#1d4ed8);
            color:#fff;
            border-radius:16px;
            padding:22px 24px;
            margin-bottom:20px;
            display:flex;
            justify-content:space-between;
            gap:20px;
            flex-wrap:wrap;
            align-items:center;
            box-shadow:0 8px 24px rgba(15,23,42,.16);
        }
        .hero h1{
            margin:0 0 6px;
            font-size:28px;
        }
        .hero p{
            margin:0;
            color:rgba(255,255,255,.88);
            font-size:14px;
        }
        .hero-actions{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }
        .card{
            background:#fff;
            border-radius:14px;
            padding:18px;
            margin-bottom:20px;
            box-shadow:0 6px 18px rgba(15,23,42,.06);
        }
        .card h2{
            margin:0 0 14px;
            font-size:20px;
        }
        .msg{
            padding:12px 14px;
            border-radius:10px;
            margin-bottom:15px;
            font-weight:700;
            font-size:14px;
            background:#e8fff0;
            color:#166534;
            border:1px solid #b7ebc6;
        }
        .msg-danger{
            background:#fff1f2;
            color:#b91c1c;
            border:1px solid #fecdd3;
        }
        .form-grid{
            display:grid;
            grid-template-columns:180px 1fr 180px 1fr;
            gap:12px 16px;
            align-items:center;
        }
        .form-grid label{
            font-weight:700;
            font-size:14px;
        }
        input[type="text"], input[type="number"], select{
            width:100%;
            min-height:42px;
            padding:10px 12px;
            border:1px solid #cbd5e1;
            border-radius:10px;
            background:#fff;
            font-size:14px;
        }
        .btn-row{
            margin-top:18px;
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }
        .btn{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-height:40px;
            padding:10px 14px;
            text-decoration:none;
            border:none;
            border-radius:10px;
            cursor:pointer;
            font-size:14px;
            font-weight:700;
        }
        .btn-primary{ background:#2563eb; color:#fff; }
        .btn-success{ background:#059669; color:#fff; }
        .btn-warning{ background:#f59e0b; color:#111827; }
        .btn-danger{ background:#dc2626; color:#fff; }
        .btn-secondary{ background:#64748b; color:#fff; }
        .btn-light{ background:#eef2ff; color:#1d4ed8; }

        .toolbar{
            display:flex;
            justify-content:space-between;
            gap:12px;
            flex-wrap:wrap;
            align-items:center;
            margin-bottom:14px;
        }
        .search-form{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }
        .search-form input{
            width:280px;
            max-width:100%;
        }
        .table-wrap{
            overflow-x:auto;
            border-radius:12px;
            border:1px solid #e5e7eb;
        }
        table{
            width:100%;
            border-collapse:collapse;
            background:#fff;
            min-width:900px;
        }
        th, td{
            border-bottom:1px solid #e5e7eb;
            padding:10px 12px;
            text-align:left;
            vertical-align:top;
            font-size:14px;
        }
        th{
            background:#f8fafc;
            font-weight:700;
            white-space:nowrap;
        }
        tr:hover td{
            background:#f9fbff;
        }
        .badge{
            display:inline-block;
            padding:4px 10px;
            border-radius:999px;
            font-size:12px;
            font-weight:700;
        }
        .badge-atm{ background:#dbeafe; color:#1d4ed8; }
        .badge-crm{ background:#ede9fe; color:#6d28d9; }
        .badge-ups{ background:#dcfce7; color:#15803d; }
        .actions{
            display:flex;
            gap:8px;
            flex-wrap:wrap;
        }
        @media (max-width:950px){
            .form-grid{
                grid-template-columns:1fr;
            }
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">

    <div class="hero">
        <div>
            <h1>Manage Vendor AMC Rates</h1>
            <p>Create and maintain vendor-wise AMC amount by service type</p>
        </div>
        <div class="hero-actions"></div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="msg <?= $messageType === 'danger' ? 'msg-danger' : '' ?>">
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2><?= $edit ? 'Edit AMC Rate' : 'Add New AMC Rate' ?></h2>

        <form method="POST">
            <input type="hidden" name="id" value="<?= h($edit['id'] ?? '') ?>">

            <div class="form-grid">
                <label>Vendor Name</label>
                <select name="vendor_name" required>
                    <option value="">Select Vendor</option>
                    <?php foreach ($vendors as $vendor): ?>
                        <option value="<?= h($vendor) ?>" <?= (($edit['vendor_name'] ?? '') === $vendor) ? 'selected' : '' ?>>
                            <?= h($vendor) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Service Type</label>
                <select name="service_type" required>
                    <option value="ATM" <?= (($edit['service_type'] ?? '') === 'ATM') ? 'selected' : '' ?>>ATM</option>
                    <option value="CRM" <?= (($edit['service_type'] ?? '') === 'CRM') ? 'selected' : '' ?>>CRM</option>
                    <option value="UPS" <?= (($edit['service_type'] ?? '') === 'UPS') ? 'selected' : '' ?>>UPS</option>
                </select>

                <label>AMC Amount</label>
                <input type="number" step="0.01" min="0" name="amc_amount" value="<?= h($edit['amc_amount'] ?? '') ?>" required>
            </div>

            <div class="btn-row">
                <?php if ($edit): ?>
                    <button type="submit" class="btn btn-success">Update AMC Rate</button>
                    <a href="manage_vendor_amc_rates.php" class="btn btn-secondary">Cancel</a>
                <?php else: ?>
                    <button type="submit" class="btn btn-primary">Save AMC Rate</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="toolbar">
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search vendor / service type" value="<?= h($search) ?>">
                <select name="filter_service_type">
                    <option value="">All Service Types</option>
                    <option value="ATM" <?= $filter_service_type === 'ATM' ? 'selected' : '' ?>>ATM</option>
                    <option value="CRM" <?= $filter_service_type === 'CRM' ? 'selected' : '' ?>>CRM</option>
                    <option value="UPS" <?= $filter_service_type === 'UPS' ? 'selected' : '' ?>>UPS</option>
                </select>
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="manage_vendor_amc_rates.php" class="btn btn-secondary">Reset</a>
            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Vendor Name</th>
                        <th>Service Type</th>
                        <th>AMC Amount</th>
                        <th>Created At</th>
                        <th>Updated At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($list && $list->num_rows > 0): ?>
                    <?php while ($r = $list->fetch_assoc()): ?>
                        <?php
                        $stype = strtoupper(trim((string)$r['service_type']));
                        $badgeClass = 'badge-atm';
                        if ($stype === 'CRM') $badgeClass = 'badge-crm';
                        elseif ($stype === 'UPS') $badgeClass = 'badge-ups';
                        ?>
                        <tr>
                            <td><?= (int)$r['id'] ?></td>
                            <td><?= h($r['vendor_name']) ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= h($r['service_type']) ?></span></td>
                            <td><?= h(number_format((float)$r['amc_amount'], 2)) ?></td>
                            <td><?= h($r['created_at'] ?? '') ?></td>
                            <td><?= h($r['updated_at'] ?? '') ?></td>
                            <td>
                                <div class="actions">
                                    <a href="?edit=<?= (int)$r['id'] ?>" class="btn btn-warning">Edit</a>
                                    <a href="?delete=<?= (int)$r['id'] ?>" class="btn btn-danger"
                                       onclick="return confirm('Delete this AMC rate?')">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center;">No AMC rate found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
