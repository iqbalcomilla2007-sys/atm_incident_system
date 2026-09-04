<?php
require_once __DIR__ . '/init.php';
Auth::requirePermission('manage_users');

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$search = trim($_GET['search'] ?? '');
$actionFilter = trim($_GET['action_filter'] ?? '');

$sql = "SELECT * FROM audit_logs WHERE 1=1";
$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (username LIKE ? OR action LIKE ? OR details LIKE ? OR ip_address LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'ssss';
}

if ($actionFilter !== '') {
    $sql .= " AND action = ?";
    $params[] = $actionFilter;
    $types .= 's';
}

$sql .= " ORDER BY created_at DESC LIMIT 500";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    die("Database query failed: " . $conn->error);
}

// Get unique actions for filter
$actionsRes = $conn->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC");
$actions = [];
if ($actionsRes) {
    while ($actRow = $actionsRes->fetch_assoc()) {
        $actions[] = $actRow['action'];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Audit Logs</title>
<link rel="stylesheet" href="style.css?v=4">
<style>
.card {background:#fff;padding:20px;margin-bottom:20px;border-radius:10px}
.table {width:100%;border-collapse:collapse}
.table th,.table td {border:1px solid #ddd;padding:10px;vertical-align:top;text-align:left}
.table th {background:#f4f4f4}
input,select {padding:8px;box-sizing:border-box}
.muted-text {color:#777;font-size:12px}
.badge-action {
    background: #e0e7ff;
    color: #3730a3;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
}
</style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container">
    <div class="hero-header">
        <div>
            <h1>System Audit Logs</h1>
            <p>View system actions, modifications and logs</p>
        </div>

        <div class="hero-actions">
            <span style="color:#fff; font-weight:bold; align-self:center;">
                Welcome, <?php echo h($_SESSION['full_name'] ?? 'User'); ?>
            </span>
</div>
    </div>

    <div class="card">
        <form method="get" style="display:flex; gap:10px; margin-bottom:15px; flex-wrap:wrap;">
            <input type="text" name="search" placeholder="Search by user, details or IP..." value="<?= h($search) ?>" style="flex:1; min-width: 250px;">
            <select name="action_filter" style="min-width: 150px;">
                <option value="">All Actions</option>
                <?php foreach ($actions as $act): ?>
                    <option value="<?= h($act) ?>" <?= $actionFilter === $act ? 'selected' : '' ?>><?= h($act) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-blue" style="background:#2563eb; color:#fff; cursor:pointer; border:none; padding:8px 15px; border-radius:6px; font-weight:bold;">Filter</button>
            <a href="manage_audit_logs.php" class="btn btn-secondary" style="background:#6b7280; color:#fff; text-decoration:none; display:inline-block; padding:8px 15px; border-radius:6px; font-weight:bold;">Reset</a>
        </form>

        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 50px;">SL</th>
                        <th style="width: 160px;">Timestamp</th>
                        <th style="width: 150px;">User</th>
                        <th style="width: 180px;">Action</th>
                        <th>Details</th>
                        <th style="width: 120px;">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php $sl = 1; ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $sl++ ?></td>
                            <td><?= h($row['created_at']) ?></td>
                            <td>
                                <strong><?= h($row['username'] ?: 'Guest/System') ?></strong>
                                <?php if ($row['user_id']): ?>
                                    <span class="muted-text">(ID: <?= (int)$row['user_id'] ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-action"><?= h($row['action']) ?></span>
                            </td>
                            <td><?= h($row['details']) ?></td>
                            <td><code><?= h($row['ip_address']) ?></code></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center;">No audit logs found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
