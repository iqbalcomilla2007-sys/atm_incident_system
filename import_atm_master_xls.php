<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('manage_atm_master');

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$message = '';
$successCount = 0;
$errorRows = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_atm_xls'])) {

    if (!isset($_FILES['atm_file']) || $_FILES['atm_file']['error'] !== 0) {
        die("Please select a valid file.");
    }

    $tmpName = $_FILES['atm_file']['tmp_name'];
    $fileName = $_FILES['atm_file']['name'] ?? '';

    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['xls', 'csv'])) {
        die("Only .xls or .csv file is allowed.");
    }

    $handle = fopen($tmpName, 'r');
    if (!$handle) {
        die("Unable to open uploaded file.");
    }

    $rowNo = 0;

    while (($data = fgetcsv($handle, 10000, "\t")) !== false) {
        $rowNo++;

        // Skip header row
        if ($rowNo === 1) {
            continue;
        }

        $atm_id        = trim($data[0] ?? '');
        $atm_name      = trim($data[1] ?? '');
        $ups_vendor    = trim($data[2] ?? '');
        $atm_model     = trim($data[3] ?? '');
        $atm_vendor    = trim($data[4] ?? '');
        $branch_name   = trim($data[5] ?? '');
        $group_no      = trim($data[6] ?? '');
        $zone_name     = trim($data[7] ?? '');

        if ($atm_id === '' || $atm_name === '') {
            $errorRows[] = "Row {$rowNo}: ATM ID or ATM Name missing.";
            continue;
        }

        // find atm vendor id
        $atm_vendor_id = 0;
        if ($atm_vendor !== '') {
            $stmtVendor = $conn->prepare("SELECT id FROM vendor_master WHERE TRIM(vendor_name)=TRIM(?) LIMIT 1");
            if ($stmtVendor) {
                $stmtVendor->bind_param("s", $atm_vendor);
                $stmtVendor->execute();
                $vendorRow = $stmtVendor->get_result()->fetch_assoc();
                $atm_vendor_id = (int)($vendorRow['id'] ?? 0);
                $stmtVendor->close();
            }
        }

        // find ups vendor id
        $ups_vendor_id = 0;
        if ($ups_vendor !== '') {
            $stmtUps = $conn->prepare("SELECT id FROM vendor_master WHERE TRIM(vendor_name)=TRIM(?) LIMIT 1");
            if ($stmtUps) {
                $stmtUps->bind_param("s", $ups_vendor);
                $stmtUps->execute();
                $upsRow = $stmtUps->get_result()->fetch_assoc();
                $ups_vendor_id = (int)($upsRow['id'] ?? 0);
                $stmtUps->close();
            }
        }

        // check duplicate atm_id
        $stmtCheck = $conn->prepare("SELECT id FROM atm_master WHERE atm_id = ? LIMIT 1");
        if (!$stmtCheck) {
            $errorRows[] = "Row {$rowNo}: Prepare failed for duplicate check.";
            continue;
        }

        $stmtCheck->bind_param("s", $atm_id);
        $stmtCheck->execute();
        $existing = $stmtCheck->get_result()->fetch_assoc();
        $stmtCheck->close();

        if ($existing) {
            // update existing
            $stmtUpdate = $conn->prepare("
                UPDATE atm_master
                SET atm_name = ?, ups_vendor = ?, atm_model = ?, atm_vendor = ?, branch_name = ?, group_no = ?, zone_name = ?, atm_vendor_id = ?, ups_vendor_id = ?
                WHERE atm_id = ?
            ");

            if ($stmtUpdate) {
                $stmtUpdate->bind_param(
                    "sssssssiss",
                    $atm_name,
                    $ups_vendor,
                    $atm_model,
                    $atm_vendor,
                    $branch_name,
                    $group_no,
                    $zone_name,
                    $atm_vendor_id,
                    $ups_vendor_id,
                    $atm_id
                );

                if ($stmtUpdate->execute()) {
                    $successCount++;
                } else {
                    $errorRows[] = "Row {$rowNo}: Update failed for ATM ID {$atm_id}.";
                }
                $stmtUpdate->close();
            } else {
                $errorRows[] = "Row {$rowNo}: Update prepare failed.";
            }
        } else {
            // insert new
            $stmtInsert = $conn->prepare("
                INSERT INTO atm_master
                (atm_id, atm_name, ups_vendor, atm_model, atm_vendor, branch_name, group_no, zone_name, atm_vendor_id, ups_vendor_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if ($stmtInsert) {
                $stmtInsert->bind_param(
                    "ssssssssii",
                    $atm_id,
                    $atm_name,
                    $ups_vendor,
                    $atm_model,
                    $atm_vendor,
                    $branch_name,
                    $group_no,
                    $zone_name,
                    $atm_vendor_id,
                    $ups_vendor_id
                );

                if ($stmtInsert->execute()) {
                    $successCount++;
                } else {
                    $errorRows[] = "Row {$rowNo}: Insert failed for ATM ID {$atm_id}.";
                }
                $stmtInsert->close();
            } else {
                $errorRows[] = "Row {$rowNo}: Insert prepare failed.";
            }
        }
    }

    fclose($handle);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Import ATM Master XLS</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f7fb; padding:20px; }
        .container { max-width:1000px; margin:auto; background:#fff; padding:20px; border-radius:10px; }
        .msg-success { color:green; font-weight:bold; margin-bottom:15px; }
        .msg-error { color:red; margin-bottom:8px; }
        .btn {
            display:inline-block;
            padding:10px 14px;
            background:#6c757d;
            color:#fff;
            text-decoration:none;
            border-radius:6px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>ATM Master Import Result</h2>

    <div class="msg-success">
        Successfully processed rows: <?= (int)$successCount ?>
    </div>

    <?php if (!empty($errorRows)): ?>
        <h3>Errors</h3>
        <?php foreach ($errorRows as $err): ?>
            <div class="msg-error"><?= h($err) ?></div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No error found.</p>
    <?php endif; ?>

    <br>
    <a href="manage_atm_master.php" class="btn">Back to Manage ATM Master</a>
</div>
</body>
</html>