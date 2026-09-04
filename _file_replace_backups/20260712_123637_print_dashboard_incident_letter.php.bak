<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('view_dashboard');

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function branchSearchTerms($branchName) {
    $branchName = trim((string)$branchName);
    if ($branchName === '') return [];

    $terms = [$branchName];
    $withoutBranch = preg_replace('/\s+branch$/i', '', $branchName);
    if ($withoutBranch && strcasecmp($withoutBranch, $branchName) !== 0) {
        $terms[] = $withoutBranch;
    }

    return array_values(array_unique(array_filter(array_map('trim', $terms))));
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Invalid incident ID.");
}

$sql = "
    SELECT 
        a.incident_id,
        a.atm_id,
        a.atm_name,
        a.problem,
        a.down_time,
        a.created_at,
        a.group_no,
        a.responsible_vendor_name,
        m.zone_name,
        m.branch_code,
        m.branch_name,
        m.atm_vendor AS master_atm_vendor,
        m.ups_vendor AS master_ups_vendor,
        pm.responsible_vendor_type,
        av.vendor_name AS atm_vendor_name,
        av.vendor_mobile AS atm_vendor_mobile,
        uv.vendor_name AS ups_vendor_name,
        uv.vendor_mobile AS ups_vendor_mobile
    FROM atm_update a
    LEFT JOIN atm_master m ON a.atm_id = m.atm_id
    LEFT JOIN vendor_master av ON m.atm_vendor_id = av.id
    LEFT JOIN vendor_master uv ON m.ups_vendor_id = uv.id
    LEFT JOIN problem_master pm ON LOWER(TRIM(a.problem)) = LOWER(TRIM(pm.problem_name))
    WHERE a.incident_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    die("Incident not found.");
}

/* ===============================
   FINAL RESPONSIBLE VENDOR
================================ */
$responsibleVendorType = strtoupper(trim((string)($row['responsible_vendor_type'] ?? '')));
$problemText = strtoupper(trim((string)($row['problem'] ?? '')));

if ($responsibleVendorType === '' && strpos($problemText, 'UPS') !== false) {
    $responsibleVendorType = 'UPS';
}

$subjectServiceType = 'ATM';
if ($responsibleVendorType === 'UPS') {
    $subjectServiceType = 'UPS';
} elseif ($responsibleVendorType === 'CRM' || stripos((string)($row['atm_id'] ?? ''), 'IBCR') === 0) {
    $subjectServiceType = 'CRM';
}

$responsibleVendor = '';
$vendorMobile = '';

if ($responsibleVendorType === 'UPS') {
    $responsibleVendor = trim((string)($row['ups_vendor_name'] ?? ''));
    $vendorMobile = trim((string)($row['ups_vendor_mobile'] ?? ''));
    if ($responsibleVendor === '') {
        $responsibleVendor = trim((string)($row['master_ups_vendor'] ?? ''));
    }
} else {
    $responsibleVendor = trim((string)($row['atm_vendor_name'] ?? ''));
    $vendorMobile = trim((string)($row['atm_vendor_mobile'] ?? ''));
    if ($responsibleVendor === '') {
        $responsibleVendor = trim((string)($row['master_atm_vendor'] ?? ''));
    }
}

if ($responsibleVendor === '') {
    $responsibleVendor = trim((string)($row['responsible_vendor_name'] ?? ''));
}

if ($responsibleVendor !== '') {
    $stmtMobile = $conn->prepare("SELECT vm.vendor_name, vc.contact_value AS vendor_contact_mobile FROM vendor_master vm LEFT JOIN vendor_contacts vc ON vc.vendor_id = vm.id AND vc.contact_type = 'mobile' WHERE TRIM(vm.vendor_name) = ? ORDER BY vc.id DESC LIMIT 1");
    if ($stmtMobile) {
        $stmtMobile->bind_param("s", $responsibleVendor);
        $stmtMobile->execute();
        $mobileRow = $stmtMobile->get_result()->fetch_assoc();
        $stmtMobile->close();
        if (!empty($mobileRow['vendor_name'])) {
            $responsibleVendor = trim((string)$mobileRow['vendor_name']);
        }
        $vendorContactMobile = trim((string)($mobileRow['vendor_contact_mobile'] ?? ''));
        if ($vendorContactMobile !== '') {
            $vendorMobile = $vendorContactMobile;
        }
    }
}

$branchContactText = '-';
$branchCode = trim($row['branch_code'] ?? '');
$contactRow = null;

if ($branchCode !== '') {
    $stmtContact = $conn->prepare("
        SELECT custodian1_name, custodian1_mobile, custodian2_name, custodian2_mobile
        FROM atm_contact
        WHERE branch_code = ? AND branch_code <> '' AND branch_code IS NOT NULL
        ORDER BY id DESC
        LIMIT 1
    ");
    if ($stmtContact) {
        $stmtContact->bind_param("s", $branchCode);
        $stmtContact->execute();
        $contactRow = $stmtContact->get_result()->fetch_assoc();
        $stmtContact->close();
    }
}

if (!$contactRow) {
    $branchTerms = branchSearchTerms($row['branch_name'] ?? '');
    foreach ($branchTerms as $branchTerm) {
        $stmtContact = $conn->prepare("
            SELECT custodian1_name, custodian1_mobile, custodian2_name, custodian2_mobile
            FROM atm_contact
            WHERE LOWER(TRIM(branch_name)) = LOWER(TRIM(?))
               OR LOWER(TRIM(branch_name)) LIKE CONCAT('%', LOWER(TRIM(?)), '%')
               OR LOWER(TRIM(?)) LIKE CONCAT('%', LOWER(TRIM(branch_name)), '%')
            ORDER BY
                CASE WHEN LOWER(TRIM(branch_name)) = LOWER(TRIM(?)) THEN 0 ELSE 1 END,
                branch_name ASC
            LIMIT 1
        ");
        if ($stmtContact) {
            $stmtContact->bind_param("ssss", $branchTerm, $branchTerm, $branchTerm, $branchTerm);
            $stmtContact->execute();
            $contactRow = $stmtContact->get_result()->fetch_assoc();
            $stmtContact->close();

            if ($contactRow) {
                break;
            }
        }
    }
}

if ($contactRow) {
    $contacts = [];
    $custodian1 = trim((string)($contactRow['custodian1_name'] ?? ''));
    $custodian1Mobile = trim((string)($contactRow['custodian1_mobile'] ?? ''));
    $custodian2 = trim((string)($contactRow['custodian2_name'] ?? ''));
    $custodian2Mobile = trim((string)($contactRow['custodian2_mobile'] ?? ''));

    if ($custodian1 !== '') {
        $contacts[] = 'Custodian 1: ' . $custodian1 . ($custodian1Mobile !== '' ? ', ' . $custodian1Mobile : '');
    }
    if ($custodian2 !== '') {
        $contacts[] = 'Custodian 2: ' . $custodian2 . ($custodian2Mobile !== '' ? ', ' . $custodian2Mobile : '');
    }

    if ($contacts) {
        $branchContactText = implode("\n", $contacts);
    }
}

// Added \n after Mobile numbers to break the line
$atmmdContactText = "Hotline From Mobile: 09611216259, From IP: 777-6\n\n";
$groupNo = (int)($row['group_no'] ?? 0);
if ($groupNo > 0) {
    $stmtGroup = $conn->prepare("SELECT group_leader_name, group_members FROM group_details WHERE group_no = ? LIMIT 1");
    if ($stmtGroup) {
        $stmtGroup->bind_param("i", $groupNo);
        $stmtGroup->execute();
        $groupRow = $stmtGroup->get_result()->fetch_assoc();
        $stmtGroup->close();

        $groupLeader = trim((string)($groupRow['group_leader_name'] ?? ''));
        $groupMembers = trim((string)($groupRow['group_members'] ?? ''));
        
        $groupInfo = [];
        if ($groupLeader !== '') {
            $groupInfo[] = 'Group Leader: ' . $groupLeader;
        }
        if ($groupMembers !== '') {
            $groupInfo[] = 'Group Members: ' . $groupMembers;
        }
        
        // Added \n before Group information to move it to a new line
        if (!empty($groupInfo)) {
            $atmmdContactText .= "\n" . implode(', ', $groupInfo);
        }
    }
}

$today = date('d/m/Y');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Incident Letter</title>
    <style>
        body{
            font-family: Arial, sans-serif;
            background:#f3f4f6;
            margin:0;
            padding:20px;
        }
        .print-toolbar{
            max-width:900px;
            margin:0 auto 20px auto;
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }
        .btn{
            display:inline-block;
            padding:10px 14px;
            border:none;
            border-radius:6px;
            text-decoration:none;
            cursor:pointer;
            font-size:14px;
            font-weight:700;
        }
        .btn-print{ background:#2563eb; color:#fff; }
        .btn-back{ background:#6b7280; color:#fff; }

        .paper{
            max-width:900px;
            margin:0 auto;
            background:#fff;
            padding:50px 55px;
            box-shadow:0 2px 10px rgba(0,0,0,.08);
        }
        .meta{
            margin-bottom:20px;
            font-size:14px;
            line-height:1.7;
        }
        .subject{
            margin:20px 0 16px;
            font-size:15px;
            font-weight:700;
        }
        .body-text{
            font-size:15px;
            line-height:1.8;
            text-align:justify;
        }
        .info-table{
            width:100%;
            border-collapse:collapse;
            margin:18px 0;
        }
        .info-table th,
        .info-table td{
            border:1px solid #d1d5db;
            padding:10px 12px;
            text-align:left;
            vertical-align:top;
            font-size:14px;
        }
        .info-table th{
            background:#f8fafc;
            width:220px;
        }
        .signature{
            margin-top:45px;
            font-size:15px;
            line-height:1.8;
        }

        @media print {
            body{
                background:#fff;
                padding:0;
            }
            .print-toolbar{
                display:none;
            }
            .paper{
                box-shadow:none;
                max-width:none;
                margin:0;
                padding:20px 30px;
            }
        }
    </style>
</head>
<body>

<div class="print-toolbar">
    <button class="btn btn-print" onclick="window.print()">Print</button>
    <a href="dashboard_ajax_v2.php" class="btn btn-back">Back to Dashboard</a>
</div>

<div class="paper">
    <div class="meta">
        <strong>Attention:</strong> <?php echo h($vendorMobile ?: '-'); ?>, <?php echo h($responsibleVendor ?: '-'); ?><br>
            </div>

    <div class="subject">
    Subject: Request for Immediate Resolution of <?= h($subjectServiceType) ?> Related Incident at ATM ID <?= h($row['atm_id']) ?> (<?= h($row['atm_name']) ?>).
</div>

    <div class="body-text">
        Please find below the details of the incident for necessary information and taking required steps.
    </div>

    <table class="info-table">
        <tr>
            <th>ATM ID</th>
            <td><?php echo h($row['atm_id']); ?></td>
        </tr>
        <tr>
            <th>ATM / Booth Name</th>
            <td><?php echo h($row['atm_name']); ?></td>
        </tr>
        <tr>
            <th>Zone Name</th>
            <td><?php echo h($row['zone_name'] ?? ''); ?></td>
        </tr>
        <tr>
            <th>Branch Name</th>
            <td><?php echo h($row['branch_name'] ?? ''); ?></td>
        </tr>
        <tr>
            <th>Problem</th>
            <td><?php echo h($row['problem']); ?></td>
        </tr>
        <tr>
            <th>Down Time</th>
            <td><?php echo h($row['down_time']); ?></td>
        </tr>
        <tr>
            <th>Created At</th>
            <td><?php echo h($row['created_at']); ?></td>
        </tr>
        <tr>
            <th>Branch Contact name</th>
            <td><?php echo nl2br(h($branchContactText)); ?></td>
        </tr>
        <tr>
            <th>ATMMD Hotline</th>
            <td><?php echo nl2br(h($atmmdContactText)); ?></td>
        </tr>
    </table>

    <div class="body-text">
        Your are requested to solve the issue on urgent basis. Please inform us the details about your assigned Engineer. Also give us updates about the incident until it is solved.
    </div>
</div>

</body>
</html>