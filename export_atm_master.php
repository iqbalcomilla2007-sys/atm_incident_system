<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('manage_atm_master');

function branchKeySql($expr) {
    $key = "LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(IFNULL($expr, ''), ',', 1), '(', 1)))";
    foreach ([
        'jessore' => 'jashore',
        'barisal' => 'barishal',
        'comilla' => 'cumilla',
        'gonj' => 'ganj',
        'nawabgonj' => 'nawabganj',
    ] as $from => $to) {
        $key = "REPLACE($key, '$from', '$to')";
    }
    foreach ([' sub branch', ' sub-branch', ' branch', ' br.', ' br', ' sme', ' krishi'] as $word) {
        $key = "REPLACE($key, '$word', '')";
    }
    foreach ([' ', '-', '/', '.', ',', '&', '(', ')', '?', '`'] as $char) {
        $key = "REPLACE($key, '$char', '')";
    }
    $key = "REPLACE($key, CHAR(39), '')";
    return $key;
}

function branchMatchSql($contactKey, $masterKey) {
    return "(
        $contactKey = $masterKey
        OR ($masterKey = 'agrabad' AND $contactKey = 'agrabadcorporate')
    )";
}

function atmIdKeySql($expr) {
    $key = "LOWER(TRIM(IFNULL($expr, '')))";
    foreach (['atm id', 'atm', 'id', ' ', '-', '_', '/', '.', ':'] as $part) {
        $key = "REPLACE($key, '$part', '')";
    }
    return $key;
}

$isAdmin = Auth::isAdmin();
$assignedZone = $_SESSION['assigned_zone'] ?? '';
$search = trim($_GET['search'] ?? '');

// Headers for Excel download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=ATM_Master_Export_" . date('Y-m-d_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

$atmBranchKey = branchKeySql('a.branch_name');
$contactBranchKey = branchKeySql('ac.branch_name');
$sgBranchKey = branchKeySql('sg.branch_name');
$contactBranchMatch = "(
    (ac.branch_code = a.branch_code AND ac.branch_code <> '' AND ac.branch_code IS NOT NULL)
    OR
    (
        (ac.branch_code IS NULL OR ac.branch_code = '' OR a.branch_code IS NULL OR a.branch_code = '')
        AND
        " . branchMatchSql($contactBranchKey, $atmBranchKey) . "
    )
)";
$atmIdKey = atmIdKeySql('a.atm_id');
$sgAtmIdKey = atmIdKeySql('sg.atm_id');
$sgAtmMatch = "(
    $sgAtmIdKey = $atmIdKey
    OR ($sgBranchKey = $atmBranchKey AND $sgAtmIdKey <> '' AND RIGHT($sgAtmIdKey, 4) = RIGHT($atmIdKey, 4))
)";
$sgBranchMatch = "($sgBranchKey = $atmBranchKey)";
$sgOrder = "($sgAtmIdKey = $atmIdKey) DESC, ($sgBranchKey = $atmBranchKey AND $sgAtmIdKey <> '' AND RIGHT($sgAtmIdKey, 4) = RIGHT($atmIdKey, 4)) DESC, sg.id DESC";

$baseSelect = "
    SELECT 
        a.*,
        av.vendor_name AS atm_vendor_name,
        uv.vendor_name AS ups_vendor_name,
        (SELECT ac.custodian1_name FROM atm_contact ac WHERE $contactBranchMatch ORDER BY ac.id DESC LIMIT 1) AS custodian1_name,
        (SELECT ac.custodian1_mobile FROM atm_contact ac WHERE $contactBranchMatch ORDER BY ac.id DESC LIMIT 1) AS custodian1_mobile,
        (SELECT ac.custodian2_name FROM atm_contact ac WHERE $contactBranchMatch ORDER BY ac.id DESC LIMIT 1) AS custodian2_name,
        (SELECT ac.custodian2_mobile FROM atm_contact ac WHERE $contactBranchMatch ORDER BY ac.id DESC LIMIT 1) AS custodian2_mobile,
        (SELECT ac.manager_name FROM atm_contact ac WHERE $contactBranchMatch ORDER BY ac.id DESC LIMIT 1) AS manager_name,
        (SELECT ac.manager_mobile FROM atm_contact ac WHERE $contactBranchMatch ORDER BY ac.id DESC LIMIT 1) AS manager_mobile,
        (
            SELECT sg.sg1_name
            FROM atm_sg sg
            WHERE $sgAtmMatch OR $sgBranchMatch
            ORDER BY $sgOrder
            LIMIT 1
        ) AS sg1_name,
        (
            SELECT sg.sg1_mobile
            FROM atm_sg sg
            WHERE $sgAtmMatch OR $sgBranchMatch
            ORDER BY $sgOrder
            LIMIT 1
        ) AS sg1_mobile,
        (
            SELECT sg.sg2_name
            FROM atm_sg sg
            WHERE $sgAtmMatch OR $sgBranchMatch
            ORDER BY $sgOrder
            LIMIT 1
        ) AS sg2_name,
        (
            SELECT sg.sg2_mobile
            FROM atm_sg sg
            WHERE $sgAtmMatch OR $sgBranchMatch
            ORDER BY $sgOrder
            LIMIT 1
        ) AS sg2_mobile,
        (
            SELECT sg.sg3_name
            FROM atm_sg sg
            WHERE $sgAtmMatch OR $sgBranchMatch
            ORDER BY $sgOrder
            LIMIT 1
        ) AS sg3_name,
        (
            SELECT sg.sg3_mobile
            FROM atm_sg sg
            WHERE $sgAtmMatch OR $sgBranchMatch
            ORDER BY $sgOrder
            LIMIT 1
        ) AS sg3_mobile
    FROM atm_master a
    LEFT JOIN vendor_master av ON a.atm_vendor_id = av.id
    LEFT JOIN vendor_master uv ON a.ups_vendor_id = uv.id
";

$sql = $baseSelect;
$params = [];
$types = '';

if ($search !== '') {
    $safe = '%' . $search . '%';
    if ($isAdmin) {
        $sql .= " WHERE (a.atm_id LIKE ? OR a.atm_name LIKE ? OR a.zone_name LIKE ? OR a.branch_name LIKE ? OR av.vendor_name LIKE ? OR uv.vendor_name LIKE ? OR a.group_no LIKE ?)";
        $params = [$safe, $safe, $safe, $safe, $safe, $safe, $safe];
        $types = "sssssss";
    } else {
        $sql .= " WHERE a.zone_name = ? AND (a.atm_id LIKE ? OR a.atm_name LIKE ? OR a.zone_name LIKE ? OR a.branch_name LIKE ? OR av.vendor_name LIKE ? OR uv.vendor_name LIKE ? OR a.group_no LIKE ?)";
        $params = [$assignedZone, $safe, $safe, $safe, $safe, $safe, $safe, $safe];
        $types = "ssssssss";
    }
} else {
    if (!$isAdmin && $assignedZone !== '') {
        $sql .= " WHERE a.zone_name = ?";
        $params = [$assignedZone];
        $types = "s";
    }
}

$sql .= " ORDER BY a.group_no ASC, a.zone_name ASC, a.branch_name ASC, a.atm_name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

echo '<table border="1">';
echo '<thead>
        <tr>
            <th style="background-color:#f2f2f2;">ATM ID</th>
            <th style="background-color:#f2f2f2;">ATM Name</th>
            <th style="background-color:#f2f2f2;">Zone</th>
            <th style="background-color:#f2f2f2;">Branch</th>
            <th style="background-color:#f2f2f2;">ATM Vendor</th>
            <th style="background-color:#f2f2f2;">Group</th>
            <th style="background-color:#f2f2f2;">Monitoring IP</th>
            <th style="background-color:#f2f2f2;">Internal IP</th>
            <th style="background-color:#f2f2f2;">Subnet Mask</th>
            <th style="background-color:#f2f2f2;">Gateway</th>
            <th style="background-color:#f2f2f2;">Custodian 1</th>
            <th style="background-color:#f2f2f2;">C1 Mobile</th>
            <th style="background-color:#f2f2f2;">Custodian 2</th>
            <th style="background-color:#f2f2f2;">C2 Mobile</th>
            <th style="background-color:#f2f2f2;">Manager</th>
            <th style="background-color:#f2f2f2;">Manager Mobile</th>
            <th style="background-color:#f2f2f2;">SG 1 Name</th>
            <th style="background-color:#f2f2f2;">SG 1 Mobile</th>
            <th style="background-color:#f2f2f2;">SG 2 Name</th>
            <th style="background-color:#f2f2f2;">SG 2 Mobile</th>
            <th style="background-color:#f2f2f2;">SG 3 Name</th>
            <th style="background-color:#f2f2f2;">SG 3 Mobile</th>
        </tr>
      </thead>';
echo '<tbody>';

while ($row = $result->fetch_assoc()) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($row['atm_id']) . '</td>';
    echo '<td>' . htmlspecialchars($row['atm_name']) . '</td>';
    echo '<td>' . htmlspecialchars($row['zone_name']) . '</td>';
    echo '<td>' . htmlspecialchars($row['branch_name']) . '</td>';
    echo '<td>' . htmlspecialchars($row['atm_vendor_name'] ?? $row['atm_vendor'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['group_no']) . '</td>';
    echo '<td>' . htmlspecialchars($row['monitoring_ip']) . '</td>';
    echo '<td>' . htmlspecialchars($row['internal_ip']) . '</td>';
    echo '<td>' . htmlspecialchars($row['subnet_mask']) . '</td>';
    echo '<td>' . htmlspecialchars($row['gateway']) . '</td>';
    echo '<td>' . htmlspecialchars($row['custodian1_name']) . '</td>';
    echo '<td>' . htmlspecialchars($row['custodian1_mobile']) . '</td>';
    echo '<td>' . htmlspecialchars($row['custodian2_name']) . '</td>';
    echo '<td>' . htmlspecialchars($row['custodian2_mobile']) . '</td>';
    echo '<td>' . htmlspecialchars($row['manager_name']) . '</td>';
    echo '<td>' . htmlspecialchars($row['manager_mobile']) . '</td>';
    echo '<td>' . htmlspecialchars($row['sg1_name']) . '</td>';
    echo '<td>' . htmlspecialchars($row['sg1_mobile']) . '</td>';
    echo '<td>' . htmlspecialchars($row['sg2_name']) . '</td>';
    echo '<td>' . htmlspecialchars($row['sg2_mobile']) . '</td>';
    echo '<td>' . htmlspecialchars($row['sg3_name']) . '</td>';
    echo '<td>' . htmlspecialchars($row['sg3_mobile']) . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';
exit;
