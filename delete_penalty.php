<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('manage_penalty');

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM penalty_reports WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        header("Location: penalty_summary_report.php?msg=deleted");
    } else {
        echo "Error deleting record: " . $conn->error;
    }
    $stmt->close();
} else {
    echo "Invalid ID";
}

$conn->close();
?>
