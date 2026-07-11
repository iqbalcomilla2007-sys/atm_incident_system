<?php
require_once __DIR__ . '/init.php';
Auth::requirePermission('delete_incident');

$id = (int)$_GET['id'];
$incidentObj = new Incident();
$incidentObj->delete($id);

header("Location: dashboard.php");
exit;
?>