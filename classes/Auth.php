<?php

class Auth {
    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function isLoggedIn() {
        self::startSession();
        return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
    }

    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header("Location: login.php");
            exit;
        }
    }

    public static function login($username, $password) {
        self::startSession();
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.password, u.full_name, u.role_id, u.assigned_zone, u.user_type, r.role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.username = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();

        if (!$user) {
            AuditLog::log("LOGIN_FAILED", "User not found: " . $username);
            return ["success" => false, "error" => "User not found."];
        }

        if (password_verify($password, $user['password']) || $password === $user['password']) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $user['username'] ?? '';
            $_SESSION['full_name'] = $user['full_name'] ?? '';
            $_SESSION['role_id'] = (int)($user['role_id'] ?? 0);
            $_SESSION['role_name'] = $user['role_name'] ?? '';
            $_SESSION['assigned_zone'] = $user['assigned_zone'] ?? '';
            $_SESSION['user_type'] = $user['user_type'] ?? '';
            AuditLog::log("LOGIN", "User logged in successfully");
            return ["success" => true];
        } else {
            AuditLog::log("LOGIN_FAILED", "Invalid password for user: " . $username);
            return ["success" => false, "error" => "Invalid password."];
        }
    }

    public static function logout() {
        self::startSession();
        AuditLog::log("LOGOUT", "User logged out successfully");
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    public static function hasPermission($permissionName) {
        if (!self::isLoggedIn()) return false;
        if (($_SESSION['role_name'] ?? '') === 'Super Administrator') return true;

        $db = Database::getInstance();
        $conn = $db->getConnection();

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $roleId = (int)($_SESSION['role_id'] ?? 0);
        $userType = $_SESSION['user_type'] ?? '';

        // 1. Check Individual EXCLUSIONS
        $stmtIndEx = $conn->prepare("
            SELECT p.id 
            FROM permissions p
            JOIN user_excluded_permissions uep ON p.id = uep.permission_id
            WHERE uep.user_id = ? AND p.permission_name = ?
            LIMIT 1
        ");
        if ($stmtIndEx) {
            $stmtIndEx->bind_param("is", $userId, $permissionName);
            $stmtIndEx->execute();
            if ($stmtIndEx->get_result()->num_rows > 0) return false; 
        }

        // 2. Check User Specific Permissions (Individual Overrides - ALLOW)
        $stmtU = $conn->prepare("
            SELECT p.id
            FROM permissions p
            JOIN user_permissions up ON p.id = up.permission_id
            WHERE up.user_id = ? AND p.permission_name = ?
            LIMIT 1
        ");
        if ($stmtU) {
            $stmtU->bind_param("is", $userId, $permissionName);
            $stmtU->execute();
            if ($stmtU->get_result()->num_rows > 0) return true;
        }

        // 3. Check User-Type EXCLUSIONS
        if ($userType !== '') {
            $stmtEx = $conn->prepare("
                SELECT p.id 
                FROM permissions p
                JOIN user_type_excluded_permissions utep ON p.id = utep.permission_id
                WHERE utep.user_type = ? AND p.permission_name = ?
                LIMIT 1
            ");
            if ($stmtEx) {
                $stmtEx->bind_param("ss", $userType, $permissionName);
                $stmtEx->execute();
                if ($stmtEx->get_result()->num_rows > 0) return false;
            }
        }

        // 4. Check Role Permissions
        $stmt = $conn->prepare("
            SELECT p.id 
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ? AND p.permission_name = ?
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param("is", $roleId, $permissionName);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) return true;
        }

        return false;
    }

    public static function requirePermission($permissionName) {
        if (!self::hasPermission($permissionName)) {
            echo "<div style='font-family: sans-serif; padding: 50px 20px; text-align: center; max-width: 500px; margin: 80px auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid #f1f5f9;'>";
            echo "<div style='font-size: 48px; margin-bottom: 15px;'>🔒</div>";
            echo "<h2 style='color: #ef4444; margin: 0 0 10px; font-size: 22px; font-weight: 700;'>Access Denied</h2>";
            echo "<p style='color: #64748b; font-size: 14px; line-height: 1.5; margin: 0 0 20px;'>You do not have permission to view this page ($permissionName). If you recently imported a new database, your session might be outdated.</p>";
            echo "<a href='logout.php' style='display: inline-flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #2563eb, #3b82f6); color: #fff; padding: 10px 24px; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 600; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2); transition: 0.2s;'>Logout & Login Again</a>";
            echo "</div>";
            exit;
        }
    }

    public static function isAdmin() {
        self::startSession();
        $role = $_SESSION['role_name'] ?? '';
        return in_array($role, ['Super Administrator', 'Admin'], true);
    }

    public static function isSuperAdmin() {
        self::startSession();
        return ($_SESSION['role_name'] ?? '') === 'Super Administrator';
    }

    public static function requireSuperAdmin() {
        if (!self::isSuperAdmin()) {
            echo "<div style='font-family: sans-serif; padding: 50px 20px; text-align: center; max-width: 500px; margin: 80px auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid #f1f5f9;'>";
            echo "<div style='font-size: 48px; margin-bottom: 15px;'>🔒</div>";
            echo "<h2 style='color: #ef4444; margin: 0 0 10px; font-size: 22px; font-weight: 700;'>Access Denied</h2>";
            echo "<p style='color: #64748b; font-size: 14px; line-height: 1.5; margin: 0 0 20px;'>Super Administrator permission is required to access this resource.</p>";
            echo "<a href='logout.php' style='display: inline-flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #2563eb, #3b82f6); color: #fff; padding: 10px 24px; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 600; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2); transition: 0.2s;'>Logout & Login Again</a>";
            echo "</div>";
            exit;
        }
    }

    public static function canManageRoleName($roleName) {
        self::startSession();
        $currentRole = $_SESSION['role_name'] ?? '';

        if ($currentRole === 'Super Administrator') {
            return true;
        }

        if ($currentRole === 'Admin') {
            return in_array($roleName, ['Operator', 'Viewer'], true);
        }

        return false;
    }
}
