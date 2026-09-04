<?php

class Role {
    private $conn;

    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }

    public function getAll() {
        $result = $this->conn->query("SELECT * FROM roles ORDER BY role_name ASC");
        if (!$result) {
            die("Query failed: " . $this->conn->error);
        }
        return $result;
    }

    public function getPermissions() {
        $result = $this->conn->query("SELECT id, permission_name FROM permissions ORDER BY permission_name ASC");
        if (!$result) {
            die("Query failed: " . $this->conn->error);
        }
        return $result;
    }

    public function create($roleName) {
        $roleName = trim($roleName);
        if ($roleName === '') {
            return false;
        }

        $stmt = $this->conn->prepare("INSERT INTO roles (role_name) VALUES (?)");
        if (!$stmt) return false;

        $stmt->bind_param("s", $roleName);
        return $stmt->execute();
    }

    public function delete($roleId) {
        $roleId = (int)$roleId;
        if ($roleId <= 0) return false;

        $this->conn->query("DELETE FROM roles WHERE id = $roleId");
        $this->conn->query("DELETE FROM role_permissions WHERE role_id = $roleId");
        return true;
    }

    public function getRolePermissionIds($roleId) {
        $roleId = (int)$roleId;
        $result = $this->conn->query("SELECT permission_id FROM role_permissions WHERE role_id = $roleId");
        $perms = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $perms[] = (int)$row['permission_id'];
            }
        }
        return $perms;
    }

    public function saveRolePermissions($roleId, array $permissionIds) {
        $roleId = (int)$roleId;
        if ($roleId <= 0) return false;

        $this->conn->query("DELETE FROM role_permissions WHERE role_id = $roleId");

        if (!empty($permissionIds)) {
            $stmt = $this->conn->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            if (!$stmt) return false;

            foreach ($permissionIds as $pId) {
                $pId = (int)$pId;
                $stmt->bind_param("ii", $roleId, $pId);
                $stmt->execute();
            }
        }
        return true;
    }

    public function getExcludedPermissionIds($userType) {
        $userType = $this->conn->real_escape_string(trim($userType));
        $result = $this->conn->query("SELECT permission_id FROM user_type_excluded_permissions WHERE user_type = '$userType'");
        $excluded = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $excluded[] = (int)$row['permission_id'];
            }
        }
        return $excluded;
    }

    public function saveGlobalExclusions($userType, array $excludedPermissionIds) {
        $userType = trim($userType);
        if ($userType === '') return false;

        $stmtDel = $this->conn->prepare("DELETE FROM user_type_excluded_permissions WHERE user_type = ?");
        if (!$stmtDel) return false;

        $stmtDel->bind_param("s", $userType);
        $stmtDel->execute();

        if (!empty($excludedPermissionIds)) {
            $stmtIns = $this->conn->prepare("INSERT INTO user_type_excluded_permissions (user_type, permission_id) VALUES (?, ?)");
            if (!$stmtIns) return false;

            foreach ($excludedPermissionIds as $pId) {
                $pId = (int)$pId;
                $stmtIns->bind_param("si", $userType, $pId);
                $stmtIns->execute();
            }
        }
        return true;
    }
}
