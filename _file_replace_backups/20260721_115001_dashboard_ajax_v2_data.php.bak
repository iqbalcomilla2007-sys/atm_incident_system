<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('view_dashboard');

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function tableExists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return ($res && $res->num_rows > 0);
}

function tableColumnExists($conn, $table, $column) {
    $table  = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($res && $res->num_rows > 0);
}

function parseMinutes($text) {
    $text = strtolower(trim((string)$text));
    if ($text === '') return 0;
    $d = 0; $h = 0; $m = 0;
    if (preg_match('/(\d+)\s*day/', $text, $x))  $d = (int)$x[1];
    if (preg_match('/(\d+)\s*hour/', $text, $x)) $h = (int)$x[1];
    if (preg_match('/(\d+)\s*min/', $text, $x))  $m = (int)$x[1];
    return ($d * 1440) + ($h * 60) + $m;
}

function getGrowingText($downTime, $createdAt) {
    $base = parseMinutes($downTime);
    try {
        $created = new DateTime($createdAt, new DateTimeZone('Asia/Dhaka'));
        $now = new DateTime('now', new DateTimeZone('Asia/Dhaka'));
        $extra = floor(($now->getTimestamp() - $created->getTimestamp()) / 60);
    } catch (Exception $e) { $extra = 0; }
    $minutes = $base + max(0, $extra);
    $days = floor($minutes / 1440); $minutes %= 1440;
    $hours = floor($minutes / 60); $mins = $minutes % 60;
    $parts = [];
    if ($days > 0) $parts[] = $days . ' day';
    if ($hours > 0) $parts[] = $hours . ' hour';
    $parts[] = $mins . ' min';
    return implode(' ', $parts);
}

function normalizeProblemText($text) {
    $text = strtolower(trim((string)$text));
    $text = preg_replace('/\s+/', ' ', $text);
    return $text;
}

/* ---------------------------------------------------------
   INPUTS
--------------------------------------------------------- */
$search   = trim($_GET['search'] ?? '');
$group_no = trim($_GET['group_no'] ?? '');
$problem  = trim($_GET['problem'] ?? '');
$username = trim($_GET['username'] ?? '');
$vendor   = trim($_GET['vendor'] ?? '');
$limit    = (int)($_GET['limit'] ?? 100);
$page     = (int)($_GET['page'] ?? 1);
$offset   = ($page - 1) * $limit;
$from_date= trim($_GET['from_date'] ?? '');
$to_date  = trim($_GET['to_date'] ?? '');

if ($search === '' && $group_no === '' && $problem === '' && $username === '' && $vendor === '' && $from_date === '' && $to_date === '' && isset($_GET['initial_load']) && $_GET['initial_load'] === '1') {
    echo '<div class="form-card" style="text-align:center; padding: 40px; font-size: 16px; color: #64748b; font-weight: 500;">
            <svg style="width: 48px; height: 48px; margin: 0 auto 10px; color: #94a3b8;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg><br>
            Please apply a search or filter to view incidents, or click Search to load all.
          </div>';
    exit;
}

/* ---------------------------------------------------------
   OPTIONAL PROBLEM MASTER JOIN
--------------------------------------------------------- */
$hasProblemMaster = tableExists($conn, 'problem_master');
$hasProblemVendorType = $hasProblemMaster && tableColumnExists($conn, 'problem_master', 'responsible_vendor_type');
$problemVendorTypeSelect = $hasProblemVendorType ? "pm.responsible_vendor_type AS problem_vendor_type" : "'' AS problem_vendor_type";
$problemJoinSql = $hasProblemVendorType ? "LEFT JOIN problem_master pm ON LOWER(TRIM(a.problem)) = LOWER(TRIM(pm.problem_name))" : "";

/* ---------------------------------------------------------
   MAIN QUERY
--------------------------------------------------------- */
$sql = "
SELECT
    a.incident_id, a.atm_id, a.atm_name, a.problem, a.down_time, a.group_no, a.created_at, a.responsible_vendor_name, a.last_modified_by,
    u.username AS last_modified_username, m.zone_name, m.atm_vendor, m.ups_vendor, gd.zones, gd.group_leader_name, gd.group_members, lr.latest_remark,
    $problemVendorTypeSelect
FROM atm_update a
LEFT JOIN atm_master m ON TRIM(a.atm_id) = TRIM(m.atm_id)
LEFT JOIN group_details gd ON a.group_no = gd.group_no
LEFT JOIN users u ON a.last_modified_by = u.id
$problemJoinSql
LEFT JOIN (
    SELECT r1.incident_id, r1.remark AS latest_remark
    FROM incident_remarks r1
    INNER JOIN (SELECT incident_id, MAX(id) AS max_id FROM incident_remarks GROUP BY incident_id) r2 ON r1.incident_id = r2.incident_id AND r1.id = r2.max_id
) lr ON a.incident_id = lr.incident_id
WHERE a.incident_status = 'Open'
";

$params = []; $types  = '';
$zone = buildZoneRestrictionClause('m');
$sql .= $zone['sql'];
$params = array_merge($params, $zone['params']);
$types .= $zone['types'];

if ($search !== '') {
    $sql .= " AND (a.atm_id LIKE ? OR a.atm_name LIKE ? OR a.problem LIKE ? OR m.zone_name LIKE ? OR lr.latest_remark LIKE ? OR u.username LIKE ?)";
    $st = "%$search%"; for ($i = 0; $i < 6; $i++) { $params[] = $st; $types .= 's'; }
}

if ($group_no !== '') { $sql .= " AND a.group_no = ?"; $params[] = $group_no; $types .= 's'; }
if ($problem !== '') { $sql .= " AND a.problem = ?"; $params[] = $problem; $types .= 's'; }
if ($username !== '') { $sql .= " AND u.username = ?"; $params[] = $username; $types .= 's'; }
if ($vendor !== '') { $sql .= " AND a.responsible_vendor_name = ?"; $params[] = $vendor; $types .= 's'; }
if ($from_date !== '') { $sql .= " AND DATE(a.created_at) >= ?"; $params[] = $from_date; $types .= 's'; }
if ($to_date !== '') { $sql .= " AND DATE(a.created_at) <= ?"; $params[] = $to_date; $types .= 's'; }

$sql .= " ORDER BY a.group_no ASC, a.created_at ASC LIMIT ? OFFSET ?";
$params[] = $limit; $params[] = $offset; $types .= 'ii';

$stmt = $conn->prepare($sql);
if (!$stmt) { die('Prepare failed: ' . h($conn->error)); }
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

// COUNT QUERY
$countSql = "SELECT COUNT(*) as total FROM atm_update a 
LEFT JOIN atm_master m ON TRIM(a.atm_id) = TRIM(m.atm_id) 
LEFT JOIN users u ON a.last_modified_by = u.id 
$problemJoinSql
LEFT JOIN (
    SELECT r1.incident_id, r1.remark AS latest_remark
    FROM incident_remarks r1
    INNER JOIN (SELECT incident_id, MAX(id) AS max_id FROM incident_remarks GROUP BY incident_id) r2 ON r1.incident_id = r2.incident_id AND r1.id = r2.max_id
) lr ON a.incident_id = lr.incident_id
WHERE a.incident_status = 'Open'";

$countSql .= $zone['sql'];
if ($search !== '') {
    $countSql .= " AND (a.atm_id LIKE ? OR a.atm_name LIKE ? OR a.problem LIKE ? OR m.zone_name LIKE ? OR lr.latest_remark LIKE ? OR u.username LIKE ?)";
}
if ($group_no !== '') { $countSql .= " AND a.group_no = ?"; }
if ($problem !== '') { $countSql .= " AND a.problem = ?"; }
if ($username !== '') { $countSql .= " AND u.username = ?"; }
if ($vendor !== '') { $countSql .= " AND a.responsible_vendor_name = ?"; }
if ($from_date !== '') { $countSql .= " AND DATE(a.created_at) >= ?"; }
if ($to_date !== '') { $countSql .= " AND DATE(a.created_at) <= ?"; }

$countStmt = $conn->prepare($countSql);
// The parameters for count are the same as for main query, EXCEPT the last two (limit, offset)
$countParams = array_slice($params, 0, -2);
$countTypes = substr($types, 0, -2);
if (!empty($countParams)) {
    $countStmt->bind_param($countTypes, ...$countParams);
}
$countStmt->execute();
$countRes = $countStmt->get_result();
$filteredTotal = $countRes->fetch_assoc()['total'] ?? 0;
$countStmt->close();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[$row['group_no'] ?: 'No Group'][] = $row;
}
$stmt->close();

echo '<div id="new_total_open" style="display:none;">' . $filteredTotal . '</div>';

if (empty($data)) { echo '<div class="form-card">No data found</div>'; exit; }


/* ---------------------------------------------------------
   RENDER TABLE (পরিবর্তিত অংশ)
--------------------------------------------------------- */
foreach ($data as $grp => $rows) {
    $sl = 1; $first = $rows[0];
    $zoneName = $first['zones'] ?: ($first['zone_name'] ?? '');

    echo '<div class="table-card group-box">';
    echo '<h3>Group ' . h($grp) . ' (' . count($rows) . ')</h3>';
    $groupMembers = trim((string)($first['group_members'] ?? ''));
    $extraParts = [];
    $extraParts[] = '<strong>Zone:</strong> ' . h($zoneName ?: '-');
    $extraParts[] = '<strong>Leader:</strong> ' . h($first['group_leader_name'] ?? '-');
    if ($groupMembers !== '') {
        $extraParts[] = '<strong>Members:</strong> ' . h($groupMembers);
    }
    echo '<div style="font-size:13px; margin-bottom:10px;">' . implode(' | ', $extraParts) . '</div>';

    echo '<div class="table-wrap"><table class="modern-table">';
    echo '<tr><th>SL</th><th>ATM ID</th><th>Name</th><th>Problem</th><th>Down Time</th><th>Zone</th><th>Responsible Vendor</th><th>Latest Remark</th><th>Created By</th><th>Action</th></tr>';

    foreach ($rows as $row) {
        $dt = getGrowingText($row['down_time'], $row['created_at']);
        $atmIdJs = htmlspecialchars(json_encode((string)$row['atm_id']), ENT_QUOTES, 'UTF-8');
        
        // Vendor Logic
        $problemVendorType = strtoupper(trim((string)($row['problem_vendor_type'] ?? '')));
        if ($problemVendorType === 'UPS' || stripos($row['problem'], 'UPS') !== false) {
            $responsibleVendor = $row['ups_vendor'];
        } elseif ($problemVendorType === 'ATM') {
            $responsibleVendor = $row['atm_vendor'];
        } else {
            $responsibleVendor = $row['responsible_vendor_name'] ?: $row['atm_vendor'];
        }

        echo '<tr>';
        echo '<td>' . $sl++ . '</td>';
        echo '<td>' . h($row['atm_id']) . '</td>';
        echo '<td>' . h($row['atm_name']) . '</td>';
        echo '<td>' . h($row['problem']) . '</td>';
        echo '<td>' . h($dt) . '</td>';
        echo '<td>' . h($row['zone_name']) . '</td>';
        echo '<td>' . h($responsibleVendor ?: '-') . '</td>';
        echo '<td>' . (!empty($row['latest_remark']) ? nl2br(h($row['latest_remark'])) : '-') . '</td>';
        echo '<td>' . h($row['last_modified_username'] ?? '-') . '</td>';

        echo '<td>';
        // এখানে অ্যাকশন বাটনগুলোকে একটি ফ্লেক্স কন্টেইনারের ভেতরে রাখা হয়েছে
        echo '<div class="action-buttons" style="display:flex; flex-wrap:wrap; gap:5px; justify-content:flex-start;">'; 
        
        // ১. Edit বাটন (URL ছোট করা হয়েছে Forbidden এরর এড়াতে)
        echo '<a href="edit.php?id=' . (int)$row['incident_id'] . '" class="btn-action btn-edit" style="font-size:11px; padding:4px 8px; background:#0d6efd; color:#fff; border-radius:4px; text-decoration:none;">Edit</a>';

        // ২. Remark বাটন (Orange)
        echo '<button type="button" class="btn-action btn-remark" data-id="' . (int)$row['incident_id'] . '" style="background:#f59e0b !important; color:#fff !important; border:none; font-weight:bold; font-size:11px; padding:4px 8px; border-radius:4px; cursor:pointer;">Remark</button>';

        // ৩. Close বাটন (Red)
        echo '<button type="button" class="btn-action btn-close" onclick="closeIncident(' . (int)$row['incident_id'] . ', ' . $atmIdJs . ', this)" style="background:#dc3545 !important; color:#fff !important; border:none; font-weight:bold; font-size:11px; padding:4px 8px; border-radius:4px; cursor:pointer;">Close</button>';

        // ৪. Letter বাটন (Light Blue)
        echo '<a href="print_dashboard_incident_letter.php?id=' . (int)$row['incident_id'] . '" class="btn-action btn-letter" target="_blank" style="background:#0dcaf0 !important; color:#000 !important; border:none; font-weight:bold; font-size:11px; padding:4px 8px; border-radius:4px; text-decoration:none;">Letter</a>';

        // ৫. Penalty বাটন (Deep Purple)
        if (Auth::hasPermission('manage_penalty')) {
            echo '<a href="penalty.php?incident_id=' . (int)$row['incident_id'] . '" class="btn-action btn-penalty" style="background:#6d28d9 !important; color:#fff !important; border:none; font-weight:bold; font-size:11px; padding:4px 8px; border-radius:4px; text-decoration:none;" onclick="return confirm(\'Impose penalty?\');">Penalty</a>';
        }

        echo '</div>'; // .action-buttons ডিভ শেষ
        echo '</td>';
        echo '</tr>';
    }
    echo '</table></div></div>';
}
?>