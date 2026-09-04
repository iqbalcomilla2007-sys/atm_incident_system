<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    die("Invalid device ID.");
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $atm_id           = trim($_POST['atm_id'] ?? '');
    $atm_name         = trim($_POST['atm_name'] ?? '');
    $branch_name      = trim($_POST['branch_name'] ?? '');
    $dvr_vendor       = trim($_POST['dvr_vendor'] ?? '');
    $dvr_brand        = trim($_POST['dvr_brand'] ?? '');
    $dvr_model        = trim($_POST['dvr_model'] ?? '');
    $dvr_serial       = trim($_POST['dvr_serial'] ?? '');
    $ip_address       = trim($_POST['ip_address'] ?? '');
    $m_ip             = trim($_POST['m_ip'] ?? '');
    $http_port        = (int)($_POST['http_port'] ?? 80);
    $rtsp_port        = (int)($_POST['rtsp_port'] ?? 554);
    $total_channel    = (int)($_POST['total_channel'] ?? 4);
    $monitor_username = trim($_POST['monitor_username'] ?? '');
    $monitor_password = trim($_POST['monitor_password'] ?? '');
    $status           = trim($_POST['status'] ?? 'Active');
    $remarks          = trim($_POST['remarks'] ?? '');

    if ($ip_address === '') {
        $error = "IP Address is required.";
    } else {
        $stmt = $conn->prepare("
            UPDATE cctv_monitor_devices
            SET
                atm_id = ?,
                atm_name = ?,
                branch_name = ?,
                dvr_vendor = ?,
                dvr_brand = ?,
                dvr_model = ?,
                dvr_serial = ?,
                ip_address = ?,
                m_ip = ?,
                http_port = ?,
                rtsp_port = ?,
                total_channel = ?,
                monitor_username = ?,
                monitor_password = ?,
                status = ?,
                remarks = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        if (!$stmt) {
            $error = "Prepare failed: " . $conn->error;
        } else {
            $stmt->bind_param(
                "sssssssssiiissssi",
                $atm_id,
                $atm_name,
                $branch_name,
                $dvr_vendor,
                $dvr_brand,
                $dvr_model,
                $dvr_serial,
                $ip_address,
                $m_ip,
                $http_port,
                $rtsp_port,
                $total_channel,
                $monitor_username,
                $monitor_password,
                $status,
                $remarks,
                $id
            );

            if ($stmt->execute()) {
                $success = "Device updated successfully.";
            } else {
                $error = "Update failed: " . $stmt->error;
            }
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM cctv_monitor_devices WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$device = $res->fetch_assoc();

if (!$device) {
    die("Device not found.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Edit CCTV Monitoring Device</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f4f6f9; font-size:14px; }
        .card { border:0; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,.08); }
        label { font-weight:600; }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Edit CCTV Monitoring Device</h4>
        <div>
            <a href="cctv_monitor_dashboard.php" class="btn btn-secondary btn-sm">Back to Monitoring Dashboard</a>
            <a href="cctv_list.php" class="btn btn-dark btn-sm">CCTV List</a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">

            <form method="post">
                <input type="hidden" name="id" value="<?= (int)$device['id'] ?>">

                <div class="row g-3">

                    <div class="col-md-3">
                        <label>ATM ID</label>
                        <input type="text" name="atm_id" class="form-control" value="<?= h($device['atm_id'] ?? '') ?>">
                    </div>

                    <div class="col-md-5">
                        <label>Booth Name</label>
                        <input type="text" name="atm_name" class="form-control" value="<?= h($device['atm_name'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label>Branch Name</label>
                        <input type="text" name="branch_name" class="form-control" value="<?= h($device['branch_name'] ?? '') ?>">
                    </div>

                    <div class="col-md-3">
                        <label>DVR Vendor</label>
                        <input type="text" name="dvr_vendor" class="form-control" value="<?= h($device['dvr_vendor'] ?? '') ?>">
                    </div>

                    <div class="col-md-3">
                        <label>DVR Brand</label>
                        <select name="dvr_brand" class="form-select">
                            <?php
                            $brand = strtoupper(trim($device['dvr_brand'] ?? ''));
                            ?>
                            <option value="">Select Brand</option>
                            <option value="HIKVISION" <?= $brand === 'HIKVISION' ? 'selected' : '' ?>>HIKVISION</option>
                            <option value="DAHUA" <?= $brand === 'DAHUA' ? 'selected' : '' ?>>DAHUA</option>
                            <option value="<?= h($device['dvr_brand'] ?? '') ?>" <?= ($brand !== 'HIKVISION' && $brand !== 'DAHUA' && $brand !== '') ? 'selected' : '' ?>>
                                <?= h($device['dvr_brand'] ?? '') ?>
                            </option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>DVR Model</label>
                        <input type="text" name="dvr_model" class="form-control" value="<?= h($device['dvr_model'] ?? '') ?>">
                    </div>

                    <div class="col-md-3">
                        <label>DVR Serial</label>
                        <input type="text" name="dvr_serial" class="form-control" value="<?= h($device['dvr_serial'] ?? '') ?>">
                    </div>

                    <div class="col-md-3">
                        <label>Monitoring IP / IP Address</label>
                        <input type="text" name="ip_address" class="form-control" required value="<?= h($device['ip_address'] ?? '') ?>">
                    </div>

                    <div class="col-md-3">
                        <label>M IP</label>
                        <input type="text" name="m_ip" class="form-control" value="<?= h($device['m_ip'] ?? '') ?>">
                    </div>

                    <div class="col-md-2">
                        <label>HTTP Port</label>
                        <input type="number" name="http_port" class="form-control" value="<?= h($device['http_port'] ?? 80) ?>">
                    </div>

                    <div class="col-md-2">
                        <label>RTSP Port</label>
                        <input type="number" name="rtsp_port" class="form-control" value="<?= h($device['rtsp_port'] ?? 554) ?>">
                    </div>

                    <div class="col-md-2">
                        <label>Total Channel</label>
                        <input type="number" name="total_channel" class="form-control" min="1" max="32" value="<?= h($device['total_channel'] ?? 4) ?>">
                    </div>

                    <div class="col-md-3">
                        <label>Monitor Username</label>
                        <input type="text" name="monitor_username" class="form-control" value="<?= h($device['monitor_username'] ?? '') ?>">
                    </div>

                    <div class="col-md-3">
                        <label>Monitor Password</label>
                        <input type="password" name="monitor_password" class="form-control" value="<?= h($device['monitor_password'] ?? '') ?>">
                    </div>

                    <div class="col-md-3">
                        <label>Status</label>
                        <select name="status" class="form-select">
                            <?php $st = $device['status'] ?? 'Active'; ?>
                            <option value="Active" <?= $st === 'Active' ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= $st === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="Under Maintenance" <?= $st === 'Under Maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                        </select>
                    </div>

                    <div class="col-md-12">
                        <label>Remarks</label>
                        <textarea name="remarks" class="form-control" rows="3"><?= h($device['remarks'] ?? '') ?></textarea>
                    </div>

                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-success">Update Device</button>
                    <a href="cctv_monitor_dashboard.php" class="btn btn-secondary">Cancel</a>

                    <?php if (!empty($device['ip_address'])): ?>
                        <a href="http://<?= h($device['ip_address']) ?>:<?= (int)($device['http_port'] ?? 80) ?>" target="_blank" class="btn btn-primary">
                            Open DVR
                        </a>
                    <?php endif; ?>
                </div>

            </form>

        </div>
    </div>

</div>

</body>
</html>