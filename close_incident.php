<?php
include 'db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid incident ID");
}

$id = (int) $_GET['id'];

$stmt = $conn->prepare("UPDATE atm_update SET incident_status = 'Closed' WHERE incident_id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: dashboard.php");
    exit;
} else {
    echo "Error closing incident";
}
?>