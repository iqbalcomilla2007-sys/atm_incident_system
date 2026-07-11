<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('manage_atm_master');

$updated = 0;

$sql1 = "
    UPDATE atm_update u
    JOIN atm_master m ON TRIM(u.atm_id) = TRIM(m.atm_id)
    LEFT JOIN vendor_master av ON m.atm_vendor_id = av.id
    LEFT JOIN vendor_master uv ON m.ups_vendor_id = uv.id
    SET 
        u.atm_name = m.atm_name,
        u.group_no = m.group_no,
        u.atm_vendor = COALESCE(av.vendor_name, m.atm_vendor),
        u.ups_vendor = COALESCE(uv.vendor_name, m.ups_vendor)
    WHERE u.incident_status = 'Open'
";

if ($conn->query($sql1)) {
    $updated += $conn->affected_rows;
}

$sql2 = "
    UPDATE cctv_list c
    JOIN atm_master m ON TRIM(c.atm_id) = TRIM(m.atm_id)
    SET
        c.atm_name = m.atm_name,
        c.branch_name = m.branch_name,
        c.zone_name = m.zone_name
";

if ($conn->query($sql2)) {
    $updated += $conn->affected_rows;
}

header("Location: manage_atm_master.php?msg=sync_done&count=" . $updated);
exit;