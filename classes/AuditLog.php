<?php

class AuditLog {
    public static function log($action, $details = '') {
        $db = Database::getInstance();
        $conn = $db->getConnection();

        // Start session if not already done
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $username = isset($_SESSION['username']) ? $_SESSION['username'] : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, username, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("issss", $userId, $username, $action, $details, $ip);
            $stmt->execute();
            $stmt->close();
        }
    }
}
