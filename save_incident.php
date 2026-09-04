<?php
require_once __DIR__ . '/init.php';
Auth::requirePermission('add_incident');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request");
}

$atm_id    = strtoupper(trim($_POST['atm_id'] ?? ''));
$problem   = trim($_POST['problem'] ?? '');
$down_time = trim($_POST['down_time'] ?? '');
$responsible_vendor_name = trim($_POST['responsible_vendor_name'] ?? '');

<<<<<<< HEAD
// ডেটাবেস কানেকশন নেওয়া হচ্ছে
$conn = Database::getInstance()->getConnection();

// ========================================================================
// ডুপ্লিকেট চেকিং লজিক (একই ATM-এ একই ভেন্ডরের নামে কোনো Open কাজ আছে কিনা)
// ========================================================================
$check_sql = "SELECT incident_id FROM atm_update WHERE atm_id = ? AND responsible_vendor_name = ? AND incident_status = 'Open'";
$stmt_chk = $conn->prepare($check_sql);

if ($stmt_chk) {
    $stmt_chk->bind_param("ss", $atm_id, $responsible_vendor_name);
    $stmt_chk->execute();
    $stmt_chk->store_result();

    // যদি রেকর্ড পাওয়া যায়, তার মানে কাজ এখনো ওপেন আছে
    if ($stmt_chk->num_rows > 0) {
        $stmt_chk->close();
        // ভেন্ডরের নাম এবং ATM ID সহ index.php তে রিডাইরেক্ট করে দেওয়া হচ্ছে
        header("Location: index.php?duplicate=1&vendor=" . urlencode($responsible_vendor_name) . "&atm_id=" . urlencode($atm_id));
        exit;
    }
    $stmt_chk->close();
}
// ========================================================================

// যদি ডুপ্লিকেট না থাকে, তবে নিচের লজিকে ডেটা সেভ হবে
=======
>>>>>>> c6a99dc9be510c188a6889613b6cd33eb079cdb1
$incidentObj = new Incident();
$result = $incidentObj->create([
    'atm_id' => $atm_id,
    'problem' => $problem,
    'down_time' => $down_time,
    'responsible_vendor_name' => $responsible_vendor_name
]);

if ($result['success']) {
    header("Location: dashboard_ajax_v2.php?saved=1");
    exit;
} else {
<<<<<<< HEAD
    // ফলব্যাক হিসেবে যদি ক্লাস থেকে কোনো ডুপ্লিকেট এরর আসে
    if (isset($result['error']) && $result['error'] === 'duplicate') {
        header("Location: index.php?duplicate=1&vendor=" . urlencode($responsible_vendor_name) . "&atm_id=" . urlencode($atm_id));
=======
    if (isset($result['error']) && $result['error'] === 'duplicate') {
        header("Location: index.php?duplicate=1&atm_id=" . urlencode($result['atm_id']));
>>>>>>> c6a99dc9be510c188a6889613b6cd33eb079cdb1
        exit;
    } else {
        die("Error saving incident: " . ($result['error'] ?? 'Unknown error'));
    }
}
?>