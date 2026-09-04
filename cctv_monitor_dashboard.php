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

/* DELETE DEVICE */
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];

    if ($delete_id > 0) {
        $stmt = $conn->prepare("DELETE FROM cctv_monitor_results WHERE device_id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM cctv_monitor_devices WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: cctv_monitor_dashboard.php?deleted=1");
    exit;
}

/* FILTER INPUTS */
$search = trim($_GET['search'] ?? '');
$zone   = trim($_GET['zone'] ?? '');
$status = trim($_GET['status'] ?? '');
$cam    = trim($_GET['camera_status'] ?? '');

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(
        d.atm_id LIKE ? 
        OR d.atm_name LIKE ? 
        OR d.branch_name LIKE ? 
        OR d.zone_name LIKE ?
        OR d.ip_address LIKE ? 
        OR d.dvr_ip LIKE ?
        OR d.dvr_brand LIKE ? 
        OR d.dvr_model LIKE ? 
        OR d.remarks LIKE ?
    )";
    $s = "%{$search}%";
    array_push($params, $s, $s, $s, $s, $s, $s, $s, $s, $s);
    $types .= 'sssssssss';
}

if ($zone !== '') {
    $where[] = "d.zone_name = ?";
    $params[] = $zone;
    $types .= 's';
}

if ($status !== '') {
    $where[] = "r.dvr_online = ?";
    $params[] = $status;
    $types .= 's';
}

if ($cam !== '') {
    $where[] = "r.camera_status = ?";
    $params[] = $cam;
    $types .= 's';
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

/* MAIN DATA QUERY */
$sql = "
SELECT 
    d.id AS device_id,
    d.atm_id,
    d.atm_name,
    d.branch_name,
    d.zone_name,
    d.dvr_brand,
    d.dvr_model,
    d.ip_address,
    d.dvr_ip,
    d.http_port,
    d.rtsp_port,
    d.total_channel,
    d.status AS device_status,
    d.remarks,

    r.channel_no,
    r.dvr_online,
    r.http_status,
    r.rtsp_status,
    r.camera_status,
    r.brightness,
    r.contrast_value,
    r.snapshot_path,
    r.hdd_status,
    r.backup_status,
    r.last_checked_at
FROM cctv_monitor_devices d
LEFT JOIN (
    SELECT r1.*
    FROM cctv_monitor_results r1
    INNER JOIN (
        SELECT device_id, channel_no, MAX(id) AS max_id
        FROM cctv_monitor_results
        GROUP BY device_id, channel_no
    ) x ON r1.id = x.max_id
) r ON d.id = r.device_id
$whereSql
ORDER BY d.zone_name ASC, d.branch_name ASC, d.atm_id ASC, r.channel_no ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL Prepare Error: " . h($conn->error));
}

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

/* ZONE LIST */
$zones = [];
$zRes = $conn->query("
    SELECT DISTINCT zone_name 
    FROM cctv_monitor_devices 
    WHERE zone_name IS NOT NULL 
      AND zone_name <> '' 
    ORDER BY zone_name ASC
");
if ($zRes) {
    while ($zr = $zRes->fetch_assoc()) {
        $zones[] = $zr['zone_name'];
    }
}

/* SUMMARY */
$totalChannels = count($rows);
$online = $offline = $black = $normal = $snapshotFail = 0;

foreach ($rows as $r) {
    if (($r['dvr_online'] ?? '') === 'Online') $online++;
    if (($r['dvr_online'] ?? '') === 'Offline') $offline++;
    if (($r['camera_status'] ?? '') === 'Black Screen') $black++;
    if (($r['camera_status'] ?? '') === 'Normal') $normal++;
    if (($r['camera_status'] ?? '') === 'Snapshot Fail') $snapshotFail++;
}

function badge($value) {
    $value = (string)$value;
    $class = 'secondary';

    if (in_array($value, ['Online', 'Open', 'Normal', 'Working', 'OK'])) {
        $class = 'success';
    } elseif (in_array($value, ['Offline', 'Closed', 'Black Screen', 'No Signal', 'Snapshot Fail', 'Faulty'])) {
        $class = 'danger';
    } elseif ($value === '' || $value === 'Not Checked') {
        $class = 'secondary';
        $value = 'Not Checked';
    } else {
        $class = 'warning';
    }

    return '<span class="badge bg-' . $class . '">' . h($value) . '</span>';
}

function problemSummary($r) {
    $problems = [];

    if (($r['dvr_online'] ?? '') === 'Offline') {
        $problems[] = 'DVR Offline';
    }

    if (($r['http_status'] ?? '') === 'Closed') {
        $problems[] = 'HTTP Closed';
    }

    if (($r['rtsp_status'] ?? '') === 'Closed') {
        $problems[] = 'RTSP Closed';
    }

    if (in_array(($r['camera_status'] ?? ''), ['Black Screen', 'No Signal', 'Snapshot Fail'])) {
        $problems[] = $r['camera_status'];
    }

    if (!empty($r['hdd_status']) && !in_array($r['hdd_status'], ['OK', 'Working', 'Not Checked'])) {
        $problems[] = 'HDD: ' . $r['hdd_status'];
    }

    if (!empty($r['backup_status']) && !in_array($r['backup_status'], ['OK', 'Available', 'Not Checked'])) {
        $problems[] = 'Backup: ' . $r['backup_status'];
    }

    if (!$problems) {
        return '<span class="badge bg-success">OK</span>';
    }

    return '<span class="badge bg-danger">' . h(implode(', ', $problems)) . '</span>';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CCTV Live Monitoring Dashboard</title>
    <meta http-equiv="refresh" content="120">

    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f4f6f9; font-size:14px; }
        .page-title { font-weight:700; }
        .summary-card { border:0; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,.08); }
        .table th { background:#1f2937; color:#fff; white-space:nowrap; vertical-align:middle; text-align:center; }
        .table td { vertical-align:middle; white-space:nowrap; }
        .snapshot-img {
            width:120px;
            height:75px;
            object-fit:cover;
            border-radius:8px;
            border:1px solid #ddd;
            background:#eee;
        }
        .filter-box { background:#fff; border-radius:14px; padding:15px; box-shadow:0 4px 14px rgba(0,0,0,.06); }
        .small-text { font-size:12px; color:#666; }
        .remarks-cell { max-width:260px; white-space:normal !important; }
        .status-cell { max-width:260px; white-space:normal !important; }
        .booth-cell { max-width:280px; white-space:normal !important; }
        .branch-cell { max-width:220px; white-space:normal !important; }

        .table-scroll-top {
            overflow-x: auto;
            overflow-y: hidden;
            height: 18px;
            background: #f8f9fa;
            border-bottom: 1px solid #ddd;
        }
        .table-scroll-top div { height: 1px; }
        .table-responsive { overflow-x: auto; }

        @media print {
            .no-print { display:none !important; }
            body { background:#fff; }
            .table th { background:#ddd !important; color:#000 !important; }
            .snapshot-img { width:90px; height:55px; }
            .table-scroll-top { display:none !important; }
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container-fluid py-3">

    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <div>
            <h4 class="page-title mb-0">CCTV Live Monitoring Dashboard</h4>
            <div class="small-text">
                Auto refresh every 2 minutes | Last page load: <?= date('d-m-Y h:i:s A') ?>
            </div>
        </div>

        <div>
            <a href="dashboard_ajax_v2.php" class="btn btn-secondary btn-sm">Back to Dashboard</a>
            <a href="cctv_list.php" class="btn btn-dark btn-sm">CCTV List</a>
            <a href="cctv_monitor_runner.php" class="btn btn-success btn-sm"
               onclick="return confirm('Run CCTV monitoring now? This may take some time.');">
                Run Monitor Now
            </a>
            <button onclick="window.print()" class="btn btn-primary btn-sm">Print</button>
        </div>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success no-print">Device deleted successfully.</div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-md-2"><div class="card summary-card"><div class="card-body"><div class="small-text">Total Channels</div><h4><?= (int)$totalChannels ?></h4></div></div></div>
        <div class="col-md-2"><div class="card summary-card"><div class="card-body"><div class="small-text">DVR Online</div><h4 class="text-success"><?= (int)$online ?></h4></div></div></div>
        <div class="col-md-2"><div class="card summary-card"><div class="card-body"><div class="small-text">DVR Offline</div><h4 class="text-danger"><?= (int)$offline ?></h4></div></div></div>
        <div class="col-md-2"><div class="card summary-card"><div class="card-body"><div class="small-text">Normal Camera</div><h4 class="text-success"><?= (int)$normal ?></h4></div></div></div>
        <div class="col-md-2"><div class="card summary-card"><div class="card-body"><div class="small-text">Black Screen</div><h4 class="text-danger"><?= (int)$black ?></h4></div></div></div>
        <div class="col-md-2"><div class="card summary-card"><div class="card-body"><div class="small-text">Snapshot Fail</div><h4 class="text-warning"><?= (int)$snapshotFail ?></h4></div></div></div>
    </div>

    <form method="get" class="filter-box mb-3 no-print">
        <div class="row g-2">
            <div class="col-md-3">
                <input type="text" name="search" value="<?= h($search) ?>" class="form-control form-control-sm"
                       placeholder="Search ATM ID / Booth / Branch / IP / Brand / Model / Remarks">
            </div>

            <div class="col-md-2">
                <select name="zone" class="form-select form-select-sm">
                    <option value="">All Zone</option>
                    <?php foreach ($zones as $z): ?>
                        <option value="<?= h($z) ?>" <?= $zone === $z ? 'selected' : '' ?>>
                            <?= h($z) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All DVR Status</option>
                    <option value="Online" <?= $status === 'Online' ? 'selected' : '' ?>>Online</option>
                    <option value="Offline" <?= $status === 'Offline' ? 'selected' : '' ?>>Offline</option>
                </select>
            </div>

            <div class="col-md-2">
                <select name="camera_status" class="form-select form-select-sm">
                    <option value="">All Camera Status</option>
                    <option value="Normal" <?= $cam === 'Normal' ? 'selected' : '' ?>>Normal</option>
                    <option value="Black Screen" <?= $cam === 'Black Screen' ? 'selected' : '' ?>>Black Screen</option>
                    <option value="No Signal" <?= $cam === 'No Signal' ? 'selected' : '' ?>>No Signal</option>
                    <option value="Snapshot Fail" <?= $cam === 'Snapshot Fail' ? 'selected' : '' ?>>Snapshot Fail</option>
                    <option value="Not Checked" <?= $cam === 'Not Checked' ? 'selected' : '' ?>>Not Checked</option>
                </select>
            </div>

            <div class="col-md-3">
                <button class="btn btn-success btn-sm">Filter</button>
                <a href="cctv_monitor_dashboard.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body p-0">

            <div class="table-scroll-top no-print" id="scrollTop">
                <div></div>
            </div>

            <div class="table-responsive" id="scrollBottom">
                <table class="table table-bordered table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>SL</th>
                            <th>Status</th>
                            <th>Snapshot</th>
                            <th>ATM ID</th>
                            <th>Booth Name</th>
                            <th>Branch</th>
                            <th>Zone</th>
                            <th>DVR Brand</th>
                            <th>IP</th>
                            <th>CH</th>
                            <th>DVR</th>
                            <th>HTTP</th>
                            <th>RTSP</th>
                            <th>Camera</th>
                            <th>Brightness</th>
                            <th>Contrast</th>
                            <th>HDD</th>
                            <th>Backup</th>
                            <th>Last Checked</th>
                            <th>Remarks</th>
                            <th class="no-print">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="21" class="text-center text-muted py-4">
                                No monitoring data found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $sl = 1; foreach ($rows as $r): ?>
                            <?php
                                $snapshot = trim($r['snapshot_path'] ?? '');
                                $hasSnapshot = $snapshot !== '' && file_exists(__DIR__ . '/' . $snapshot);

                                $ip = trim($r['dvr_ip'] ?? '');
                                if ($ip === '') {
                                    $ip = trim($r['ip_address'] ?? '');
                                }

                                $port = (int)($r['http_port'] ?? 80);
                                $dvrUrl = '';

                                if ($ip !== '') {
                                    $dvrUrl = 'http://' . $ip;
                                    if ($port > 0 && $port !== 80) {
                                        $dvrUrl .= ':' . $port;
                                    }
                                }
                            ?>

                            <tr>
                                <td><?= $sl++ ?></td>

                                <td class="status-cell"><?= problemSummary($r) ?></td>

                                <td class="text-center">
                                    <?php if ($hasSnapshot): ?>
                                        <a href="<?= h($snapshot) ?>" target="_blank">
                                            <img src="<?= h($snapshot) ?>?t=<?= time() ?>" class="snapshot-img">
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">No Image</span>
                                    <?php endif; ?>
                                </td>

                                <td><?= h($r['atm_id']) ?></td>
                                <td class="booth-cell"><?= h($r['atm_name']) ?></td>
                                <td class="branch-cell"><?= h($r['branch_name']) ?></td>
                                <td><?= h($r['zone_name']) ?></td>

                                <td>
                                    <?= h($r['dvr_brand']) ?><br>
                                    <span class="small-text"><?= h($r['dvr_model']) ?></span>
                                </td>

                                <td><?= h($ip) ?></td>

                                <td class="text-center">
                                    <strong>CH-<?= h($r['channel_no'] ?? '-') ?></strong>
                                </td>

                                <td><?= badge($r['dvr_online'] ?? 'Not Checked') ?></td>
                                <td><?= badge($r['http_status'] ?? 'Not Checked') ?></td>
                                <td><?= badge($r['rtsp_status'] ?? 'Not Checked') ?></td>
                                <td><?= badge($r['camera_status'] ?? 'Not Checked') ?></td>

                                <td class="text-end">
                                    <?= $r['brightness'] !== null ? number_format((float)$r['brightness'], 2) : '-' ?>
                                </td>

                                <td class="text-end">
                                    <?= $r['contrast_value'] !== null ? number_format((float)$r['contrast_value'], 2) : '-' ?>
                                </td>

                                <td><?= badge($r['hdd_status'] ?? 'Not Checked') ?></td>
                                <td><?= badge($r['backup_status'] ?? 'Not Checked') ?></td>

                                <td>
                                    <?= $r['last_checked_at'] ? date('d-m-Y h:i:s A', strtotime($r['last_checked_at'])) : '-' ?>
                                </td>

                                <td class="remarks-cell">
                                    <?= nl2br(h($r['remarks'] ?? '')) ?>
                                </td>

                                <td class="no-print">
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($dvrUrl !== ''): ?>
                                            <a href="<?= h($dvrUrl) ?>" target="_blank" class="btn btn-outline-primary">
                                                Open DVR
                                            </a>
                                        <?php endif; ?>

                                        <a href="cctv_monitor_device_edit.php?id=<?= (int)$r['device_id'] ?>" class="btn btn-outline-warning">
                                            Edit
                                        </a>

                                        <a href="cctv_monitor_dashboard.php?delete_id=<?= (int)$r['device_id'] ?>"
                                           onclick="return confirm('Are you sure you want to delete this monitoring device and its results?');"
                                           class="btn btn-outline-danger">
                                            Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>

                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const topScroll = document.getElementById("scrollTop");
    const bottomScroll = document.getElementById("scrollBottom");

    if (!topScroll || !bottomScroll) return;

    const table = bottomScroll.querySelector("table");

    function syncWidth() {
        if (table && topScroll.firstElementChild) {
            topScroll.firstElementChild.style.width = table.scrollWidth + "px";
        }
    }

    syncWidth();
    window.addEventListener("resize", syncWidth);

    topScroll.addEventListener("scroll", function () {
        bottomScroll.scrollLeft = topScroll.scrollLeft;
    });

    bottomScroll.addEventListener("scroll", function () {
        topScroll.scrollLeft = bottomScroll.scrollLeft;
    });
});
</script>

</body>
</html>