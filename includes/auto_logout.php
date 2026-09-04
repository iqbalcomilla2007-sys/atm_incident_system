<?php
// Auto logout after inactivity
$timeout = 1800; // 30 minutes
if (isset($_SESSION['last_activity'])) {
    $inactive_time = time() - $_SESSION['last_activity'];
    if ($inactive_time > $timeout) {
        session_unset();
        session_destroy();
        header("Location: login.php?msg=Session expired");
        exit;
    }
}
// update last activity time
$_SESSION['last_activity'] = time();