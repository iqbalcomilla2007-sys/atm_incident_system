<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('manage_penalty');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $return_url = $_POST['return_url'] ?? 'penalty_summary_report.php';

    $vendor_ticket_no = $_POST['vendor_ticket_no'] ?? '';
    $atm_id = $_POST['atm_id'] ?? '';
    $incident_name = $_POST['incident_name'] ?? '';
    $vendor_name = $_POST['vendor_name'] ?? '';
    $service_type = $_POST['service_type'] ?? '';
    $machine_type = $_POST['machine_type'] ?? '';
    $penalty_from = $_POST['penalty_from'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    $deduction_rate = (float)($_POST['deduction_rate'] ?? 0);
    $penalty_amount = (float)($_POST['penalty_amount'] ?? 0);
    
    $updated_by = $_SESSION['user_id'] ?? null;
    $updated_at = date('Y-m-d H:i:s');

    $sql = "UPDATE penalty_reports SET 
            vendor_ticket_no = ?, 
            atm_id = ?, 
            incident_name = ?, 
            vendor_name = ?, 
            service_type = ?, 
            machine_type = ?, 
            penalty_from = ?, 
            remarks = ?, 
            deduction_rate = ?, 
            penalty_amount = ?,
            updated_by = ?,
            updated_at = ?
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ssssssssddisi", 
        $vendor_ticket_no, 
        $atm_id, 
        $incident_name, 
        $vendor_name, 
        $service_type, 
        $machine_type, 
        $penalty_from, 
        $remarks, 
        $deduction_rate, 
        $penalty_amount,
        $updated_by,
        $updated_at,
        $id
    );

    if ($stmt->execute()) {
        header("Location: " . $return_url);
        exit;
    } else {
        echo "Update failed: " . $stmt->error;
    }
    $stmt->close();
} else {
    die("Invalid request method.");
}
