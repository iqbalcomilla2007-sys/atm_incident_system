<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('edit_incident');

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$id       = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$search   = trim($_GET['search'] ?? $_POST['search'] ?? '');
$group_no = trim($_GET['group_no'] ?? $_POST['group_no'] ?? '');
$problemf = trim($_GET['problem'] ?? $_POST['problem'] ?? '');
$limit    = trim($_GET['limit'] ?? $_POST['limit'] ?? '100');
$page     = trim($_GET['page'] ?? $_POST['page'] ?? '1');

if ($id <= 0) {
    die("Invalid incident ID.");
}

$message = '';

$incidentObj = new Incident();
$problemList = $incidentObj->getProblems();

$incident = $incidentObj->getById($id);
if (!$incident) {
    die("Incident not found.");
}

/* =========================================================
   FINAL DISPLAY VALUES
========================================================= */
$displayAtmName = trim((string)($incident['master_atm_name'] ?? ''));
if ($displayAtmName === '') {
    $displayAtmName = trim((string)($incident['atm_name'] ?? ''));
}

$displayZone = trim((string)($incident['master_zone_name'] ?? ''));
if ($displayZone === '') {
    $displayZone = trim((string)($incident['zone_name'] ?? ''));
}

$displayGroupNo = trim((string)($incident['master_group_no'] ?? ''));
if ($displayGroupNo === '') {
    $displayGroupNo = trim((string)($incident['group_no'] ?? ''));
}

$displayAtmVendor = trim((string)($incident['atm_vendor_name'] ?? ''));
if ($displayAtmVendor === '') {
    $displayAtmVendor = trim((string)($incident['master_atm_vendor'] ?? ''));
}
if ($displayAtmVendor === '') {
    $displayAtmVendor = trim((string)($incident['atm_vendor'] ?? ''));
}

$displayUpsVendor = trim((string)($incident['ups_vendor_name'] ?? ''));
if ($displayUpsVendor === '') {
    $displayUpsVendor = trim((string)($incident['master_ups_vendor'] ?? ''));
}
if ($displayUpsVendor === '') {
    $displayUpsVendor = trim((string)($incident['ups_vendor'] ?? ''));
}

/* =========================================================
   RESPONSIBLE VENDOR LOGIC
========================================================= */
$problemText = strtolower(trim((string)($incident['problem'] ?? '')));
$vendorType  = strtoupper(trim((string)($incident['responsible_vendor_type'] ?? '')));

$responsibleVendorDisplay = trim((string)($incident['responsible_vendor_name'] ?? ''));

if ($vendorType === 'UPS' || strpos($problemText, 'ups') !== false) {
    if ($displayUpsVendor !== '') {
        $responsibleVendorDisplay = $displayUpsVendor;
    }
} elseif ($vendorType === 'ATM' || $vendorType === 'CRM') {
    if ($displayAtmVendor !== '') {
        $responsibleVendorDisplay = $displayAtmVendor;
    }
}

if ($responsibleVendorDisplay === '') {
    $responsibleVendorDisplay = $displayAtmVendor;
}

if ($responsibleVendorDisplay === '') {
    $responsibleVendorDisplay = $displayUpsVendor;
}

/* =========================================================
   UPDATE INCIDENT
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_incident'])) {
    $result = $incidentObj->update($id, $_POST);

    if ($result['success']) {
        $query = http_build_query([
            'updated'  => 1,
            'search'   => $search,
            'group_no' => $group_no,
            'problem'  => $problemf,
            'limit'    => $limit,
            'page'     => $page
        ]);

        header("Location: dashboard_ajax_v2.php?" . $query);
        exit;
    } else {
        $message = $result['error'] ?? "Update failed.";
    }

    $incident['atm_name'] = $_POST['atm_name'] ?? '';
    $incident['problem'] = $_POST['problem'] ?? '';
    $incident['down_time'] = $_POST['down_time'] ?? '';
    $incident['responsible_vendor_name'] = $_POST['responsible_vendor_name'] ?? '';
}

/* =========================================================
   ADD REMARK
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_remark'])) {

    $remark = trim($_POST['remark'] ?? '');
    $userId = (int)($_SESSION['user_id'] ?? 0);

    if ($remark !== '') {
        $rStmt = $conn->prepare("
            INSERT INTO incident_remarks (incident_id, remark, user_id)
            VALUES (?, ?, ?)
        ");

        if (!$rStmt) {
            die("Prepare failed: " . $conn->error);
        }

        $rStmt->bind_param("isi", $id, $remark, $userId);
        $rStmt->execute();
        $rStmt->close();

        header("Location: edit.php?id=" . $id .
            "&search=" . urlencode($search) .
            "&group_no=" . urlencode($group_no) .
            "&problem=" . urlencode($problemf) .
            "&limit=" . urlencode($limit) .
            "&page=" . urlencode($page) .
            "&remark_saved=1"
        );
        exit;
    } else {
        $message = "Remark cannot be empty.";
    }
}

/* =========================================================
   REMARKS HISTORY
========================================================= */
$remarksStmt = $conn->prepare("
    SELECT r.*, u.full_name
    FROM incident_remarks r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.incident_id = ?
    ORDER BY r.created_at DESC, r.id DESC
");

if (!$remarksStmt) {
    die("Prepare failed: " . $conn->error);
}

$remarksStmt->bind_param("i", $id);
$remarksStmt->execute();
$remarks = $remarksStmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Edit Incident</title>
    <link rel="stylesheet" href="style.css?v=edit_incident_vendor_fix_01">
</head>

<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">

    <div class="hero-header">
        <div>
            <h1>Edit Incident</h1>
            <p>Update incident information</p>
        </div>

        <div class="hero-actions"></div>
    </div>

    <?php if ($message !== '') { ?>
        <div class="form-card">
            <strong style="color:red;"><?php echo h($message); ?></strong>
        </div>
    <?php } ?>

    <?php if (isset($_GET['remark_saved'])) { ?>
        <div class="form-card">
            <strong style="color:green;">Remark added successfully.</strong>
        </div>
    <?php } ?>

    <div class="form-card">
        <form method="POST">

            <input type="hidden" name="update_incident" value="1">
            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
            <input type="hidden" name="search" value="<?php echo h($search); ?>">
            <input type="hidden" name="group_no" value="<?php echo h($group_no); ?>">
            <input type="hidden" name="problem_filter" value="<?php echo h($problemf); ?>">
            <input type="hidden" name="limit" value="<?php echo h($limit); ?>">
            <input type="hidden" name="page" value="<?php echo h($page); ?>">

            <div class="form-grid">

                <div>
                    <label>ATM ID</label>
                    <input type="text" value="<?php echo h($incident['atm_id'] ?? ''); ?>" readonly>
                </div>

                <div>
                    <label>ATM Name</label>
                    <input type="text" name="atm_name" value="<?php echo h($displayAtmName); ?>" required>
                </div>

                <div>
                    <label>Problem</label>
                    <select name="problem" required>
                        <option value="">Select Problem</option>

                        <?php
                        mysqli_data_seek($problemList, 0);
                        while ($p = $problemList->fetch_assoc()) {
                            $selected = (($incident['problem'] ?? '') === $p['problem_name']) ? 'selected' : '';
                            echo '<option value="' . h($p['problem_name']) . '" ' . $selected . '>' . h($p['problem_name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label>Down Time</label>
                    <input type="text" name="down_time" value="<?php echo h($incident['down_time'] ?? ''); ?>">
                </div>

                <div>
                    <label>Responsible Vendor Name</label>
                    <input type="text" name="responsible_vendor_name" value="<?php echo h($responsibleVendorDisplay); ?>">
                </div>

                <div>
                    <label>ATM Vendor</label>
                    <input type="text" value="<?php echo h($displayAtmVendor); ?>" readonly>
                </div>

                <div>
                    <label>UPS Vendor</label>
                    <input type="text" value="<?php echo h($displayUpsVendor); ?>" readonly>
                </div>

                <div>
                    <label>Group No</label>
                    <input type="text" value="<?php echo h($displayGroupNo); ?>" readonly>
                </div>

                <div>
                    <label>Zone</label>
                    <input type="text" value="<?php echo h($displayZone); ?>" readonly>
                </div>

                <div>
                    <label>Last Modified At</label>
                    <input type="text" value="<?php echo h($incident['updated_at'] ?? ''); ?>" readonly>
                </div>

                <div class="full-width">
                    <button type="submit" class="btn">Update Incident</button>
                </div>

            </div>
        </form>
    </div>

    <div class="form-card">
        <h3>Add Remark</h3>

        <form method="POST">
            <input type="hidden" name="add_remark" value="1">
            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
            <input type="hidden" name="search" value="<?php echo h($search); ?>">
            <input type="hidden" name="group_no" value="<?php echo h($group_no); ?>">
            <input type="hidden" name="problem" value="<?php echo h($problemf); ?>">
            <input type="hidden" name="limit" value="<?php echo h($limit); ?>">
            <input type="hidden" name="page" value="<?php echo h($page); ?>">

            <label>Remark</label>
            <textarea name="remark" required></textarea>
            <br>

            <button class="btn" type="submit">Add Remark</button>
        </form>
    </div>

    <div class="table-card">
        <h3>Remarks History</h3>

        <div class="table-wrap">
            <table class="modern-table">
                <tr>
                    <th>Date</th>
                    <th>Remark</th>
                    <th>User</th>
                </tr>

                <?php if ($remarks->num_rows > 0) { ?>
                    <?php while ($r = $remarks->fetch_assoc()) { ?>
                        <tr>
                            <td><?php echo h($r['created_at']); ?></td>
                            <td><?php echo nl2br(h($r['remark'])); ?></td>
                            <td><?php echo h($r['full_name'] ?? ''); ?></td>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr>
                        <td colspan="3">No remarks found.</td>
                    </tr>
                <?php } ?>

            </table>
        </div>
    </div>

</div>

<?php include 'includes/auto_logout.php'; ?>
</body>
</html>