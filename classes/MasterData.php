<?php
class MasterData {
    private $conn;

    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }

    // --- Problem Master ---
    public function getAllProblems() {
        return $this->conn->query("SELECT * FROM problem_master ORDER BY id DESC");
    }

    public function saveProblem($action, $data) {
        $problem_name = trim($data['problem_name'] ?? '');
        $responsible_vendor_type = trim($data['responsible_vendor_type'] ?? 'ATM');

        if ($action === 'add') {
            if ($problem_name === '') return ["success" => false, "error" => "Problem name is required."];

            $checkStmt = $this->conn->prepare("SELECT id FROM problem_master WHERE problem_name = ? LIMIT 1");
            $checkStmt->bind_param("s", $problem_name);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                return ["success" => false, "error" => "This problem already exists."];
            }

            $stmt = $this->conn->prepare("INSERT INTO problem_master (problem_name, responsible_vendor_type) VALUES (?, ?)");
            $stmt->bind_param("ss", $problem_name, $responsible_vendor_type);
            if ($stmt->execute()) return ["success" => true];
            return ["success" => false, "error" => $stmt->error];
        }

        if ($action === 'update') {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0 || $problem_name === '') return ["success" => false, "error" => "Invalid problem data."];

            $dupStmt = $this->conn->prepare("SELECT id FROM problem_master WHERE problem_name = ? AND id <> ? LIMIT 1");
            $dupStmt->bind_param("si", $problem_name, $id);
            $dupStmt->execute();
            if ($dupStmt->get_result()->num_rows > 0) {
                return ["success" => false, "error" => "Another problem with this name already exists."];
            }

            $stmt = $this->conn->prepare("UPDATE problem_master SET problem_name = ?, responsible_vendor_type = ? WHERE id = ?");
            $stmt->bind_param("ssi", $problem_name, $responsible_vendor_type, $id);
            if ($stmt->execute()) return ["success" => true];
            return ["success" => false, "error" => $stmt->error];
        }
        return ["success" => false, "error" => "Invalid action."];
    }

    public function deleteProblem($id) {
        $id = (int)$id;
        if ($id <= 0) return false;
        $stmt = $this->conn->prepare("DELETE FROM problem_master WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    // --- Group Details ---
    public function getAllGroups() {
        return $this->conn->query("SELECT * FROM group_details ORDER BY group_no ASC");
    }

    public function saveGroup($action, $data) {
        $group_no = (int)($data['group_no'] ?? 0);
        $zones = trim($data['zones'] ?? '');
        $group_leader_name = trim($data['group_leader_name'] ?? '');
        $group_members = trim($data['group_members'] ?? '');

        if ($action === 'add') {
            if ($group_no > 0) {
                $stmt = $this->conn->prepare("INSERT INTO group_details (group_no, zones, group_leader_name, group_members) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $group_no, $zones, $group_leader_name, $group_members);
                if ($stmt->execute()) return ["success" => true];
                return ["success" => false, "error" => $stmt->error];
            }
            return ["success" => false, "error" => "Invalid group no"];
        }

        if ($action === 'update') {
            $id = (int)($data['id'] ?? 0);
            if ($id > 0) {
                $stmt = $this->conn->prepare("UPDATE group_details SET group_no = ?, zones = ?, group_leader_name = ?, group_members = ? WHERE id = ?");
                $stmt->bind_param("isssi", $group_no, $zones, $group_leader_name, $group_members, $id);
                if ($stmt->execute()) return ["success" => true];
                return ["success" => false, "error" => $stmt->error];
            }
            return ["success" => false, "error" => "Invalid ID"];
        }
        return ["success" => false, "error" => "Invalid action"];
    }

    public function deleteGroup($id) {
        $id = (int)$id;
        if ($id <= 0) return false;
        $stmt = $this->conn->prepare("DELETE FROM group_details WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}
