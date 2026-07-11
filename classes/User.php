<?php

class User {
    private $conn;

    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }

    public static function canManageRoleName($roleName) {
        Auth::startSession();
        $currentRole = $_SESSION['role_name'] ?? '';

        if ($currentRole === 'Super Administrator') return true;

        if ($currentRole === 'Admin') {
            return in_array($roleName, ['Operator', 'Viewer'], true);
        }

        return false;
    }

    public function getRoleNameById($roleId) {
        $stmt = $this->conn->prepare("SELECT role_name FROM roles WHERE id=? LIMIT 1");
        if (!$stmt) return '';

        $stmt->bind_param("i", $roleId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        return $row['role_name'] ?? '';
    }

    public function getTargetUserRoleName($userId) {
        $stmt = $this->conn->prepare("
            SELECT r.role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
            LIMIT 1
        ");
        if (!$stmt) return '';

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        return $row['role_name'] ?? '';
    }

    public function create($data) {
        $username = trim($data['username'] ?? '');
        $fullName = trim($data['full_name'] ?? '');
        $password = $data['password'] ?? '';
        $roleId = (int)($data['role_id'] ?? 0);
        $zone = trim($data['assigned_zone'] ?? '');
        $userType = trim($data['user_type'] ?? '');

        $roleName = $this->getRoleNameById($roleId);

        if ($username === '' || $fullName === '' || $password === '' || $roleId <= 0) {
            return ["success" => false, "error" => "Username, Full Name, Password and Role are required."];
        }

        if (!self::canManageRoleName($roleName)) {
            return ["success" => false, "error" => "Not allowed role."];
        }

        // Check if username exists
        $checkStmt = $this->conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        if (!$checkStmt) {
            return ["success" => false, "error" => $this->conn->error];
        }
        $checkStmt->bind_param("s", $username);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            return ["success" => false, "error" => "This username already exists."];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->conn->prepare("
            INSERT INTO users (username, full_name, password, role_id, assigned_zone, user_type)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            return ["success" => false, "error" => $this->conn->error];
        }

        $stmt->bind_param("ssisss", $username, $fullName, $hash, $roleId, $zone, $userType);
        if ($stmt->execute()) {
            AuditLog::log("CREATE_USER", "Created user: " . $username . ", Role ID: " . $roleId);
            return ["success" => true];
        } else {
            return ["success" => false, "error" => $stmt->error];
        }
    }

    public function update($id, $data) {
        $id = (int)$id;
        $username = trim($data['username'] ?? '');
        $fullName = trim($data['full_name'] ?? '');
        $roleId = (int)($data['role_id'] ?? 0);
        $zone = trim($data['assigned_zone'] ?? '');
        $userType = trim($data['user_type'] ?? '');

        $targetCurrentRoleName = $this->getTargetUserRoleName($id);
        $newRoleName = $this->getRoleNameById($roleId);

        if ($id <= 0 || $username === '' || $fullName === '' || $roleId <= 0) {
            return ["success" => false, "error" => "Invalid user data."];
        }

        if (!self::canManageRoleName($targetCurrentRoleName)) {
            return ["success" => false, "error" => "Access Denied."];
        }

        if (!self::canManageRoleName($newRoleName)) {
            return ["success" => false, "error" => "Not allowed to assign this role."];
        }

        // Check duplicate username
        $dupStmt = $this->conn->prepare("
            SELECT id
            FROM users
            WHERE username = ? AND id <> ?
            LIMIT 1
        ");
        if (!$dupStmt) {
            return ["success" => false, "error" => $this->conn->error];
        }
        $dupStmt->bind_param("si", $username, $id);
        $dupStmt->execute();
        if ($dupStmt->get_result()->num_rows > 0) {
            return ["success" => false, "error" => "Another user already uses this username."];
        }

        $stmt = $this->conn->prepare("
            UPDATE users
            SET username=?, full_name=?, role_id=?, assigned_zone=?, user_type=?
            WHERE id=?
        ");
        if (!$stmt) {
            return ["success" => false, "error" => $this->conn->error];
        }

        $stmt->bind_param("ssissi", $username, $fullName, $roleId, $zone, $userType, $id);
        if ($stmt->execute()) {
            AuditLog::log("UPDATE_USER", "Updated user ID: " . $id . ", Username: " . $username . ", Role ID: " . $roleId);
            return ["success" => true];
        } else {
            return ["success" => false, "error" => $stmt->error];
        }
    }

    public function delete($id) {
        $id = (int)$id;
        Auth::startSession();
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);

        if ($currentUserId === $id) {
            return ["success" => false, "error" => "You cannot delete your own account while logged in."];
        }

        $targetRole = $this->getTargetUserRoleName($id);
        if (!self::canManageRoleName($targetRole)) {
            return ["success" => false, "error" => "You are not allowed to delete this user."];
        }

        $stmt = $this->conn->prepare("DELETE FROM users WHERE id=?");
        if (!$stmt) {
            return ["success" => false, "error" => $this->conn->error];
        }

        $stmt->bind_param("i", $id);
        try {
            if ($stmt->execute()) {
                AuditLog::log("DELETE_USER", "Deleted user ID: " . $id);
                return ["success" => true];
            } else {
                return ["success" => false, "error" => $stmt->error];
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1451) {
                return ["success" => false, "error" => "Cannot delete user because they have associated records."];
            }
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    public function getUsersList() {
        Auth::startSession();
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);

        if (Auth::isSuperAdmin()) {
            $query = "
                SELECT u.*, r.role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                ORDER BY
                    CASE
                        WHEN r.role_name = 'Super Administrator' THEN 1
                        WHEN r.role_name = 'Admin' THEN 2
                        WHEN r.role_name = 'Operator' THEN 3
                        WHEN r.role_name = 'Viewer' THEN 4
                        ELSE 5
                    END,
                    u.full_name ASC,
                    u.username ASC
            ";
            return $this->conn->query($query);
        } else {
            $stmtUsers = $this->conn->prepare("
                SELECT u.*, r.role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE r.role_name IN ('Operator','Viewer') OR u.id = ?
                ORDER BY
                    CASE
                        WHEN r.role_name = 'Operator' THEN 1
                        WHEN r.role_name = 'Viewer' THEN 2
                        WHEN r.role_name = 'Admin' THEN 3
                        ELSE 4
                    END,
                    u.full_name ASC,
                    u.username ASC
            ");
            if (!$stmtUsers) return null;
            $stmtUsers->bind_param("i", $currentUserId);
            $stmtUsers->execute();
            return $stmtUsers->get_result();
        }
    }

    public function assignRoleAndZone($userId, $roleId, $assignedZone) {
        $userId = (int)$userId;
        $roleId = (int)$roleId;
        $assignedZone = trim($assignedZone);

        if ($userId <= 0 || $roleId <= 0) {
            return ["success" => false, "error" => "Invalid user or role."];
        }

        $targetCurrentRoleName = $this->getTargetUserRoleName($userId);
        $newRoleName = $this->getRoleNameById($roleId);

        if (!self::canManageRoleName($targetCurrentRoleName)) {
            return ["success" => false, "error" => "Access Denied."];
        }

        if (!self::canManageRoleName($newRoleName)) {
            return ["success" => false, "error" => "Not allowed to assign this role."];
        }

        $stmt = $this->conn->prepare("UPDATE users SET role_id = ?, assigned_zone = ? WHERE id = ?");
        if (!$stmt) return ["success" => false, "error" => $this->conn->error];

        $stmt->bind_param("isi", $roleId, $assignedZone, $userId);
        if ($stmt->execute()) {
            return ["success" => true];
        } else {
            return ["success" => false, "error" => $stmt->error];
        }
    }
}
