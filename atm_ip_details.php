<?php
require_once __DIR__ . '/init.php';

// Access check
Auth::requirePermission('manage_atm_master');

$conn = Database::getInstance()->getConnection();
$msg = '';
$error = '';

if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/* ========================================================
   1. Fetch Dynamic Columns from Table
======================================================== */
$columns = [];
$allColumns = [];
$primaryKey = null;

$colResult = $conn->query("SHOW COLUMNS FROM atm_ip_details");

if ($colResult) {
    while ($col = $colResult->fetch_assoc()) {
        $allColumns[] = $col['Field'];
        // Detect Primary Key or Auto Increment
        if ($col['Key'] === 'PRI' || $col['Extra'] === 'auto_increment' || strtolower($col['Field']) === 'id') {
            $primaryKey = $col['Field'];
        }
    }
} else {
    die("Error fetching table columns. Ensure 'atm_ip_details' table exists.");
}

// Fallback: If no primary key is explicitly set, use the first column of the table
if (!$primaryKey && count($allColumns) > 0) {
    $primaryKey = $allColumns[0];
}

// Separate the primary key & timestamp columns from normal form columns
foreach ($allColumns as $col) {
    if ($col === $primaryKey || strtolower($col) === 'created_at' || strtolower($col) === 'updated_at') {
        continue; 
    }
    $columns[] = $col;
}

/* ========================================================
   2. Search Parameters
======================================================== */
$search = trim($_GET['search'] ?? '');
$searchQueryString = $search !== '' ? '&search=' . urlencode($search) : '';
$cancelQueryString = $search !== '' ? '?search=' . urlencode($search) : '';

/* ========================================================
   3. ADD / UPDATE
======================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pkValue = $_POST['hidden_pk_value'] ?? '';
    
    $updateFields = [];
    $insertCols = [];
    $insertVals = [];
    
    foreach ($columns as $col) {
        $val = $conn->real_escape_string($_POST[$col] ?? '');
        $updateFields[] = "`$col` = '$val'";
        $insertCols[] = "`$col`";
        $insertVals[] = "'$val'";
    }
    
    if ($pkValue !== '') {
        // UPDATE Query
        $safePk = $conn->real_escape_string($pkValue);
        $sql = "UPDATE atm_ip_details SET " . implode(', ', $updateFields) . " WHERE `$primaryKey` = '$safePk'";
        if ($conn->query($sql)) {
            $msg = "Record updated successfully.";
        } else {
            $error = "Update Failed: " . $conn->error;
        }
    } else {
        // INSERT Query
        $sql = "INSERT INTO atm_ip_details (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertVals) . ")";
        if ($conn->query($sql)) {
            $msg = "New record added successfully.";
        } else {
            $error = "Insert Failed: " . $conn->error;
        }
    }
}

/* ========================================================
   4. DELETE
======================================================== */
if (isset($_GET['delete'])) {
    $delId = $conn->real_escape_string($_GET['delete']);
    if ($conn->query("DELETE FROM atm_ip_details WHERE `$primaryKey` = '$delId'")) {
        // Redirect with search parameter
        header("Location: atm_ip_details.php?msg=deleted" . $searchQueryString);
        exit;
    } else {
        $error = "Delete Failed: " . $conn->error;
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $msg = "Record deleted successfully.";
}

/* ========================================================
   5. LOAD EDIT DATA
======================================================== */
$editData = null;
if (isset($_GET['edit'])) {
    $editId = $conn->real_escape_string($_GET['edit']);
    $res = $conn->query("SELECT * FROM atm_ip_details WHERE `$primaryKey` = '$editId' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $editData = $res->fetch_assoc();
    }
}

/* ========================================================
   6. LIST DATA (ONLY IF SEARCHED)
======================================================== */
$listResult = null;
if ($search !== '') {
    $safeSearch = $conn->real_escape_string($search);
    $whereClauses = [];
    
    // Add all columns to search query (including primary key)
    $searchCols = array_merge([$primaryKey], $columns);
    foreach ($searchCols as $col) {
        $whereClauses[] = "`$col` LIKE '%$safeSearch%'";
    }
    
    $whereSql = implode(' OR ', $whereClauses);
    $listResult = $conn->query("SELECT * FROM atm_ip_details WHERE $whereSql ORDER BY `$primaryKey` DESC");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage ATM IP Details</title>
    <style>
        :root { --primary: #2563eb; --success: #059669; --warning: #f59e0b; --danger: #dc2626; --secondary: #64748b; --dark: #1e293b; --info: #0ea5e9; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f1f5f9; margin: 0; padding: 20px; color: #334155; }
        .container { max-width: 1300px; margin: auto; }
        .card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; margin-bottom: 20px; }
        
        .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .title { font-size: 20px; font-weight: 800; color: var(--primary); text-transform: uppercase; }
        
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 9px 16px; border-radius: 8px; border: none; cursor: pointer; color: #fff; text-decoration: none; font-size: 13px; font-weight: 600; }
        .btn:hover { filter: brightness(1.1); }
        .btn-green { background: var(--success); } .btn-warning { background: var(--warning); color: #000; } .btn-danger { background: var(--danger); } .btn-secondary { background: var(--secondary); } .btn-blue { background: var(--primary); } .btn-sm { padding: 5px 10px; font-size: 12px; }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        label { font-weight: 700; font-size: 11px; color: #64748b; text-transform: uppercase; margin-bottom: 5px; display: block; }
        input { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; box-sizing: border-box; }
        input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }

        .search-box { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px; font-size: 14px; outline: none; }
        
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; margin-top: 10px; }
        th, td { padding: 10px; text-align: left; border: 1px solid #e2e8f0; font-size: 13px; }
        th { background: #f8fafc; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 11px; }
        tr:nth-child(even) { background-color: #f8fafc; }
        
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 8px; font-weight: 600; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container">
    
    <div class="card header-bar">
        <div class="title">ATM IP Details Management</div>
        <a href="manage_atm_master.php" class="btn btn-secondary">Back to Master</a>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success"><?= h($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 16px; color: var(--dark);">
            <?= $editData ? 'Edit IP Record' : 'Add New IP Record' ?>
        </h3>
        
        <form method="post" action="atm_ip_details.php<?= $cancelQueryString ?>">
            <input type="hidden" name="hidden_pk_value" value="<?= h($editData[$primaryKey] ?? '') ?>">
            
            <div class="form-grid">
                <?php foreach ($columns as $col): ?>
                    <div>
                        <label><?= htmlspecialchars(ucwords(str_replace('_', ' ', $col))) ?></label>
                        <input type="text" name="<?= $col ?>" value="<?= h($editData[$col] ?? '') ?>" required>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div>
                <button type="submit" class="btn btn-green"><?= $editData ? 'Update Record' : 'Save Record' ?></button>
                <?php if ($editData): ?>
                    <a href="atm_ip_details.php<?= $cancelQueryString ?>" class="btn btn-secondary">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <div style="margin-bottom: 20px;">
            <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 16px; color: var(--dark);">Search Database</h3>
            <form method="get" style="display: flex; gap: 10px; max-width: 600px;">
                <input type="text" name="search" value="<?= h($search) ?>" class="search-box" placeholder="Enter IP Address, ATM ID, Name..." required>
                <button type="submit" class="btn btn-blue">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="atm_ip_details.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="table-responsive">
            <?php if ($search === ''): ?>
                <div style="padding:15px; background:#f8fafc; color:#64748b; border: 1px solid #e2e8f0; border-radius:8px; text-align: center;">
                    <strong>Please enter a search keyword above to view matching records.</strong>
                </div>
            <?php elseif ($listResult && $listResult->num_rows > 0): ?>
                <div style="margin-bottom: 10px; font-size: 13px; color: var(--success); font-weight: bold;">
                    Found <?= $listResult->num_rows ?> matching record(s).
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>SL No.</th>
                            <?php foreach ($columns as $col): ?>
                                <th><?= htmlspecialchars(ucwords(str_replace('_', ' ', $col))) ?></th>
                            <?php endforeach; ?>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sl = 1; while ($row = $listResult->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= $sl++ ?></strong></td>
                                <?php foreach ($columns as $col): ?>
                                    <td><?= h($row[$col]) ?></td>
                                <?php endforeach; ?>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="?edit=<?= urlencode((string)$row[$primaryKey]) ?><?= $searchQueryString ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="?delete=<?= urlencode((string)$row[$primaryKey]) ?><?= $searchQueryString ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this record?');">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="padding:15px; background:#fee2e2; color:#b91c1c; border-radius:8px; text-align: center;">
                    <strong>No records found for "<?= h($search) ?>".</strong>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

</body>
</html>