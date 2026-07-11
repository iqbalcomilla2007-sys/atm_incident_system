<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('view_dashboard');

$search = trim($_GET['search'] ?? '');
$group_filter = trim($_GET['group_no'] ?? '');
$problem_filter = trim($_GET['problem'] ?? '');
$page_limit = 100;

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function parseManualMinutes($text) {
    $text = strtolower(trim((string)$text));
    if ($text === '') return 0;

    $days = 0;
    $hours = 0;
    $mins = 0;

    if (preg_match('/(\d+)\s*day/', $text, $m)) $days = (int)$m[1];
    if (preg_match('/(\d+)\s*hour/', $text, $m)) $hours = (int)$m[1];
    if (preg_match('/(\d+)\s*min/', $text, $m)) $mins = (int)$m[1];

    return ($days * 1440) + ($hours * 60) + $mins;
}

function formatMinutesToText($minutes) {
    $minutes = max(0, (int)$minutes);

    $days = intdiv($minutes, 1440);
    $minutes %= 1440;
    $hours = intdiv($minutes, 60);
    $mins = $minutes % 60;

    $parts = [];
    if ($days > 0) $parts[] = $days . ' day';
    if ($hours > 0) $parts[] = $hours . ' hour';
    $parts[] = $mins . ' min';

    return implode(' ', $parts);
}

function getGrowingDownTime($manualDownTime, $createdAt) {
    $baseMinutes = parseManualMinutes($manualDownTime);

    try {
        $created = new DateTime($createdAt, new DateTimeZone('Asia/Dhaka'));
        $now = new DateTime('now', new DateTimeZone('Asia/Dhaka'));
        $diffSeconds = max(0, $now->getTimestamp() - $created->getTimestamp());
        $extraMinutes = (int)floor($diffSeconds / 60);
    } catch (Exception $e) {
        $extraMinutes = 0;
    }

    return formatMinutesToText($baseMinutes + $extraMinutes);
}

$sql = "
SELECT 
    a.incident_id,
    a.atm_id,
    a.atm_name,
    a.atm_vendor,
    a.ups_vendor,
    a.problem,
    a.down_time,
    a.group_no,
    a.created_at,
    a.last_modified_by,
    u.full_name AS last_modified_user,
    gd.group_leader_name,
    gd.group_members,
    gd.zones,
    m.zone_name,
    lr.remark AS latest_remark
FROM atm_update a
LEFT JOIN users u 
    ON a.last_modified_by = u.id
LEFT JOIN group_details gd 
    ON a.group_no = gd.group_no
LEFT JOIN atm_master m 
    ON a.atm_id = m.atm_id
LEFT JOIN (
    SELECT r1.incident_id, r1.remark
    FROM incident_remarks r1
    INNER JOIN (
        SELECT incident_id, MAX(id) AS max_id
        FROM incident_remarks
        GROUP BY incident_id
    ) r2
    ON r1.incident_id = r2.incident_id
   AND r1.id = r2.max_id
) lr
    ON a.incident_id = lr.incident_id
WHERE a.incident_status = 'Open'
";

$params = [];
$types = '';

$zoneRestrict = buildZoneRestrictionClause('m');
$sql .= $zoneRestrict['sql'];
if (!empty($zoneRestrict['params'])) {
    $params = array_merge($params, $zoneRestrict['params']);
    $types .= $zoneRestrict['types'];
}

if ($search !== '') {
    $sql .= " AND (
        a.atm_id LIKE ? OR
        a.atm_name LIKE ? OR
        a.atm_vendor LIKE ? OR
        a.ups_vendor LIKE ? OR
        a.problem LIKE ? OR
        u.full_name LIKE ? OR
        m.zone_name LIKE ? OR
        lr.remark LIKE ?
    )";

    $searchLike = '%' . $search . '%';
    for ($i = 0; $i < 8; $i++) {
        $params[] = $searchLike;
    }
    $types .= 'ssssssss';
}

if ($group_filter !== '') {
    $sql .= " AND a.group_no = ?";
    $params[] = (int)$group_filter;
    $types .= 'i';
}

if ($problem_filter !== '') {
    $sql .= " AND a.problem = ?";
    $params[] = $problem_filter;
    $types .= 's';
}

$sql .= " ORDER BY a.group_no ASC, a.created_at ASC LIMIT " . (int)$page_limit;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("<div class='form-card'><strong style='color:red;'>Prepare failed: " . h($conn->error) . "</strong></div>");
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    die("<div class='form-card'><strong style='color:red;'>Execute failed: " . h($stmt->error) . "</strong></div>");
}

$result = $stmt->get_result();

$groups = [];
$totalOpen = 0;

while ($row = $result->fetch_assoc()) {
    $groupKey = ($row['group_no'] !== null && $row['group_no'] !== '') ? $row['group_no'] : 'No Group';
    $groups[$groupKey][] = $row;
    $totalOpen++;
}

if ($totalOpen == 0) {
    echo '<div class="empty-box"><h3>No Open Incidents</h3><p>No incidents found for current filter.</p></div>';
    exit;
}

foreach ($groups as $groupNo => $rows) {
?>
    <div class="group-section modern-group">

        <div class="group-heading">
            <h3>
                Group <?php echo h((string)$groupNo); ?>
                <?php
                $leader = $rows[0]['group_leader_name'] ?? '';
                $members = $rows[0]['group_members'] ?? '';
                $zones = $rows[0]['zones'] ?? '';

                $extra = [];
                if ($leader !== '') $extra[] = "Leader: " . $leader;
                if ($members !== '') $extra[] = "Members: " . $members;
                if ($zones !== '') $extra[] = "Zones: " . $zones;

                if (!empty($extra)) {
                    echo " (" . h(implode(' | ', $extra)) . ")";
                }
                ?>
            </h3>
            <span class="group-count"><?php echo count($rows); ?> items</span>
        </div>

        <div class="table-wrap">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>S/L</th>
                        <th>ATM ID</th>
                        <th>ATM Name</th>
                        <th>Zone</th>
                        <th>ATM Vendor</th>
                        <th>UPS Vendor</th>
                        <th>Problem</th>
                        <th>Down Time</th>
                        <th>Created At</th>
                        <th>Latest Remark</th>
                        <th>Modified By</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($rows as $index => $row) { ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo h($row['atm_id']); ?></td>
                            <td><?php echo h($row['atm_name']); ?></td>
                            <td><?php echo h($row['zone_name']); ?></td>
                            <td><?php echo h($row['atm_vendor']); ?></td>
                            <td><?php echo h($row['ups_vendor']); ?></td>
                            <td><?php echo h($row['problem']); ?></td>
                            <td><?php echo h(getGrowingDownTime($row['down_time'] ?? '', $row['created_at'] ?? date('Y-m-d H:i:s'))); ?></td>
                            <td><?php echo !empty($row['created_at']) ? date('d-M-Y h:i A', strtotime($row['created_at'])) : ''; ?></td>
                            <td><?php echo !empty($row['latest_remark']) ? nl2br(h($row['latest_remark'])) : '<span class="muted-text">N/A</span>'; ?></td>
                            <td><?php echo !empty($row['last_modified_user']) ? h($row['last_modified_user']) : '<span class="muted-text">N/A</span>'; ?></td>

                            <td class="action-cell">
                                <?php if (Auth::hasPermission('edit_incident')) { ?>
                                    <a class="link-btn edit-btn"
                                       href="edit.php?id=<?php echo (int)$row['incident_id']; ?>&search=<?php echo urlencode($search); ?>&group_no=<?php echo urlencode($group_filter); ?>&problem=<?php echo urlencode($problem_filter); ?>">
                                       Edit
                                    </a>
                                <?php } ?>

                                <?php if (Auth::hasPermission('close_incident')) { ?>
                                    <a class="link-btn close-btn"
                                       href="close.php?id=<?php echo (int)$row['incident_id']; ?>&search=<?php echo urlencode($search); ?>&group_no=<?php echo urlencode($group_filter); ?>&problem=<?php echo urlencode($problem_filter); ?>"
                                       onclick="return confirm('Do you want to close ATM ID: <?php echo addslashes($row['atm_id']); ?>?')">
                                       Close
                                    </a>
                                <?php } ?>

                                <?php if (Auth::hasPermission('generate_letter')) { ?>
                                    <a class="link-btn"
                                       href="print_dashboard_incident_letter.php?id=<?php echo (int)$row['incident_id']; ?>"
                                       target="_blank">
                                       Letter
                                    </a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>

            </table>
        </div>
    </div>
<?php
}
?>