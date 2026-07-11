<?php
class Zone {
    private $conn;

    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }

    // Zone Master
    public function getAllZones() {
        return $this->conn->query("SELECT * FROM zone_master ORDER BY zone_name ASC");
    }

    public function saveZone($data) {
        $id = (int)($data['id'] ?? 0);
        $zone_name = trim($data['zone_name'] ?? '');
        $active_status = (int)($data['active_status'] ?? 1);

        if ($zone_name === '') {
            return ["success" => false, "error" => "Zone name is required."];
        }

        if ($id > 0) {
            $stmt = $this->conn->prepare("UPDATE zone_master SET zone_name = ?, active_status = ?, updated_at = NOW() WHERE id = ?");
            if (!$stmt) return ["success" => false, "error" => $this->conn->error];
            $stmt->bind_param("sii", $zone_name, $active_status, $id);
            if ($stmt->execute()) return ["success" => true, "msg" => "Zone updated successfully."];
            return ["success" => false, "error" => $stmt->error];
        } else {
            $checkStmt = $this->conn->prepare("SELECT id FROM zone_master WHERE zone_name = ? LIMIT 1");
            $checkStmt->bind_param("s", $zone_name);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                return ["success" => false, "error" => "This zone already exists."];
            }

            $stmt = $this->conn->prepare("INSERT INTO zone_master (zone_name, active_status, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            if (!$stmt) return ["success" => false, "error" => $this->conn->error];
            $stmt->bind_param("si", $zone_name, $active_status);
            if ($stmt->execute()) return ["success" => true, "msg" => "Zone added successfully."];
            return ["success" => false, "error" => $stmt->error];
        }
    }

    public function deleteZone($id) {
        $id = (int)$id;
        if ($id <= 0) return false;
        $stmt = $this->conn->prepare("DELETE FROM zone_master WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    // Zonal Heads
    public function getAllZonalHeads() {
        return $this->conn->query("SELECT * FROM zonal_head_contacts ORDER BY zone_name ASC");
    }

    public function saveZonalHead($action, $data) {
        if ($action === 'add') {
            $zone_name = trim($data['zone_name'] ?? '');
            $zonal_head_name = trim($data['zonal_head_name'] ?? '');
            $mobile = trim($data['mobile'] ?? '');
            $ip_phone = trim($data['ip_phone'] ?? '');

            if ($zone_name !== '') {
                $stmt = $this->conn->prepare("INSERT INTO zonal_head_contacts (zone_name, zonal_head_name, mobile, ip_phone) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $zone_name, $zonal_head_name, $mobile, $ip_phone);
                if ($stmt->execute()) return ["success" => true];
                return ["success" => false, "error" => $stmt->error];
            }
            return ["success" => false, "error" => "Zone name required"];
        }

        if ($action === 'update') {
            $id = (int)($data['id'] ?? 0);
            $zone_name = trim($data['zone_name'] ?? '');
            $zonal_head_name = trim($data['zonal_head_name'] ?? '');
            $mobile = trim($data['mobile'] ?? '');
            $ip_phone = trim($data['ip_phone'] ?? '');

            $stmt = $this->conn->prepare("UPDATE zonal_head_contacts SET zone_name = ?, zonal_head_name = ?, mobile = ?, ip_phone = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $zone_name, $zonal_head_name, $mobile, $ip_phone, $id);
            if ($stmt->execute()) return ["success" => true];
            return ["success" => false, "error" => $stmt->error];
        }
        return ["success" => false, "error" => "Invalid action"];
    }

    public function deleteZonalHead($id) {
        $id = (int)$id;
        if ($id <= 0) return false;
        $stmt = $this->conn->prepare("DELETE FROM zonal_head_contacts WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}
