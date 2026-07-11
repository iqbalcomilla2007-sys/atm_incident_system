<?php

class Incident {
    private $conn;

    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }

    public function getProblems() {
        $result = $this->conn->query("
            SELECT problem_name
            FROM problem_master
            ORDER BY problem_name ASC
        ");
        if (!$result) {
            die("Problem query failed: " . $this->conn->error);
        }
        return $result;
    }

    public function getAtmInfo($atmId) {
        $atmId = strtoupper(trim($atmId));
        $stmt = $this->conn->prepare("
            SELECT 
                a.atm_id,
                a.atm_name,
                a.zone_name,
                a.branch_name,
                a.group_no,
                a.machine_type,
                COALESCE(av.vendor_name, a.atm_vendor) AS atm_vendor,
                a.ups_vendor AS ups_vendor
            FROM atm_master a
            LEFT JOIN vendor_master av ON a.atm_vendor_id = av.id
            WHERE UPPER(a.atm_id) = ?
            LIMIT 1
        ");
        if (!$stmt) return null;

        $stmt->bind_param("s", $atmId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if (!$row) return null;

        // machine type fallback
        $machine_type = strtoupper(trim((string)($row['machine_type'] ?? '')));
        if ($machine_type === '') {
            if (strpos($atmId, 'IBCR') === 0) {
                $machine_type = 'CRM';
            } elseif (strpos($atmId, 'IBBL') === 0) {
                $machine_type = 'ATM';
            } else {
                $machine_type = 'ATM';
            }
        }
        $row['machine_type'] = $machine_type;
        $row['atm_vendor'] = trim((string)($row['atm_vendor'] ?? ''));
        $row['ups_vendor'] = trim((string)($row['ups_vendor'] ?? ''));
        return $row;
    }

    public function getProblemVendorMapping($problemName) {
        $problemName = trim($problemName);
        $stmt = $this->conn->prepare("
            SELECT responsible_vendor_type
            FROM problem_master
            WHERE problem_name = ?
            LIMIT 1
        ");
        if (!$stmt) return '';

        $stmt->bind_param("s", $problemName);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        return trim((string)($row['responsible_vendor_type'] ?? ''));
    }

    public function hasOpenIncident($atmId) {
        $atmId = strtoupper(trim($atmId));
        $stmt = $this->conn->prepare("
            SELECT incident_id
            FROM atm_update
            WHERE atm_id = ? AND incident_status = 'Open'
            LIMIT 1
        ");
        if (!$stmt) return false;

        $stmt->bind_param("s", $atmId);
        $stmt->execute();
        $res = $stmt->get_result();
        $hasOpen = ($res->num_rows > 0);
        $stmt->close();

        return $hasOpen;
    }

    public function create($data) {
        $atmId = strtoupper(trim($data['atm_id'] ?? ''));
        $problem = trim($data['problem'] ?? '');
        $downTime = trim($data['down_time'] ?? '');

        if ($atmId === '' || $problem === '') {
            return ["success" => false, "error" => "ATM ID and Problem are required."];
        }

        if ($this->hasOpenIncident($atmId)) {
            return ["success" => false, "error" => "duplicate", "atm_id" => $atmId];
        }

        // Get trusted ATM data
        $stmt = $this->conn->prepare("
            SELECT 
                a.atm_id,
                a.atm_name,
                a.group_no,
                a.machine_type,
                av.vendor_name AS atm_vendor,
                uv.vendor_name AS ups_vendor
            FROM atm_master a
            LEFT JOIN vendor_master av ON a.atm_vendor_id = av.id
            LEFT JOIN vendor_master uv ON a.ups_vendor_id = uv.id
            WHERE a.atm_id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return ["success" => false, "error" => "ATM query prepare failed: " . $this->conn->error];
        }
        $stmt->bind_param("s", $atmId);
        $stmt->execute();
        $atm = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$atm) {
            return ["success" => false, "error" => "Invalid ATM ID."];
        }

        // Priority to user input from frontend, otherwise auto-calculate
        $responsibleVendor = trim($data['responsible_vendor_name'] ?? '');
        
        if ($responsibleVendor === '') {
            $mappedValue = $this->getProblemVendorMapping($problem);
            $mappedUpper = strtoupper($mappedValue);

            if ($mappedUpper === 'UPS') {
                $responsibleVendor = trim($atm['ups_vendor'] ?? '');
            } elseif ($mappedUpper === 'ATM' || $mappedUpper === 'CRM') {
                $responsibleVendor = trim($atm['atm_vendor'] ?? '');
            } elseif ($mappedValue !== '') {
                $responsibleVendor = $mappedValue;
            } else {
                $responsibleVendor = trim($atm['atm_vendor'] ?? '');
                if ($responsibleVendor === '') {
                    $responsibleVendor = trim($atm['ups_vendor'] ?? '');
                }
            }
        }

        Auth::startSession();
        $lastModifiedBy = (int)($_SESSION['user_id'] ?? 0);

        $stmt = $this->conn->prepare("
            INSERT INTO atm_update (
                atm_id,
                atm_name,
                problem,
                down_time,
                group_no,
                atm_vendor,
                ups_vendor,
                responsible_vendor_name,
                created_at,
                incident_status,
                last_modified_by
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Open', ?)
        ");
        if (!$stmt) {
            return ["success" => false, "error" => "Insert prepare failed: " . $this->conn->error];
        }

        $stmt->bind_param(
            "ssssssssi",
            $atm['atm_id'],
            $atm['atm_name'],
            $problem,
            $downTime,
            $atm['group_no'],
            $atm['atm_vendor'],
            $atm['ups_vendor'],
            $responsibleVendor,
            $lastModifiedBy
        );

        if (!$stmt->execute()) {
            $stmt->close();
            return ["success" => false, "error" => "Insert failed: " . $this->conn->error];
        }
        $stmt->close();

        AuditLog::log("CREATE_INCIDENT", "Created incident for ATM ID: " . $atm['atm_id'] . ", Problem: " . $problem);
        return ["success" => true];
    }

    public function getById($id) {
        $id = (int)$id;
        $stmt = $this->conn->prepare("
            SELECT
                a.*,
                m.atm_name AS master_atm_name,
                m.zone_name AS master_zone_name,
                m.group_no AS master_group_no,
                m.atm_vendor AS master_atm_vendor,
                m.ups_vendor AS master_ups_vendor,
                av.vendor_name AS atm_vendor_name,
                uv.vendor_name AS ups_vendor_name,
                pm.responsible_vendor_type
            FROM atm_update a
            LEFT JOIN atm_master m ON TRIM(a.atm_id) = TRIM(m.atm_id)
            LEFT JOIN vendor_master av ON m.atm_vendor_id = av.id
            LEFT JOIN vendor_master uv ON m.ups_vendor_id = uv.id
            LEFT JOIN problem_master pm ON a.problem = pm.problem_name
            WHERE a.incident_id = ?
            LIMIT 1
        ");
        if (!$stmt) return null;
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row;
    }

    public function update($id, $data) {
        $id = (int)$id;
        $atmName = trim($data['atm_name'] ?? '');
        $problem = trim($data['problem'] ?? '');
        $downTime = trim($data['down_time'] ?? '');
        $responsibleVendor = trim($data['responsible_vendor_name'] ?? '');

        if ($atmName === '' || $problem === '') {
            return ["success" => false, "error" => "ATM Name and Problem are required."];
        }

        Auth::startSession();
        $userId = (int)($_SESSION['user_id'] ?? 0);

        $stmt = $this->conn->prepare("
            UPDATE atm_update
            SET 
                atm_name = ?,
                problem = ?,
                down_time = ?,
                responsible_vendor_name = ?,
                last_modified_by = ?,
                updated_at = NOW()
            WHERE incident_id = ?
        ");
        if (!$stmt) {
            return ["success" => false, "error" => "Update prepare failed: " . $this->conn->error];
        }

        $stmt->bind_param("ssssii", $atmName, $problem, $downTime, $responsibleVendor, $userId, $id);

        if ($stmt->execute()) {
            $stmt->close();
            AuditLog::log("UPDATE_INCIDENT", "Updated incident ID: " . $id . ", ATM Name: " . $atmName . ", Problem: " . $problem);
            return ["success" => true];
        } else {
            $stmt->close();
            return ["success" => false, "error" => "Update failed: " . $this->conn->error];
        }
    }

    public function close($id) {
        $id = (int)$id;
        if ($id <= 0) return false;

        Auth::startSession();
        $userId = (int)($_SESSION['user_id'] ?? 0);

        $stmt = $this->conn->prepare("
            UPDATE atm_update
            SET incident_status = 'Closed',
                last_modified_by = ?,
                updated_at = NOW()
            WHERE incident_id = ?
        ");
        if (!$stmt) return false;
        
        $stmt->bind_param("ii", $userId, $id);
        $res = $stmt->execute();
        $stmt->close();
        if ($res) {
            AuditLog::log("CLOSE_INCIDENT", "Closed incident ID: " . $id);
        }
        return $res;
    }

    public function getRemarks($incidentId) {
        $incidentId = (int)$incidentId;
        $stmt = $this->conn->prepare("
            SELECT r.*, u.full_name, u.username
            FROM incident_remarks r
            LEFT JOIN users u ON r.user_id = u.id
            WHERE r.incident_id = ?
            ORDER BY r.created_at DESC
        ");
        if (!$stmt) return null;
        
        $stmt->bind_param("i", $incidentId);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
        return $res;
    }

    public function addRemark($incidentId, $remarkText) {
        $incidentId = (int)$incidentId;
        $remarkText = trim($remarkText);
        if ($incidentId <= 0 || $remarkText === '') return false;

        Auth::startSession();
        $userId = (int)($_SESSION['user_id'] ?? 0);

        $stmt = $this->conn->prepare("
            INSERT INTO incident_remarks (incident_id, user_id, remark)
            VALUES (?, ?, ?)
        ");
        if (!$stmt) return false;
        
        $stmt->bind_param("iis", $incidentId, $userId, $remarkText);
        $res = $stmt->execute();
        $stmt->close();
        if ($res) {
            AuditLog::log("ADD_REMARK", "Added remark to incident ID: " . $incidentId . ". Remark: " . $remarkText);
        }
        return $res;
    }

    public function getHistory($incidentId) {
        $incidentId = (int)$incidentId;
        $stmt = $this->conn->prepare("
            SELECT h.*, u.full_name, u.username
            FROM atm_update_history h
            LEFT JOIN users u ON h.last_modified_by = u.id
            WHERE h.incident_id = ?
            ORDER BY h.updated_at DESC
        ");
        if (!$stmt) return null;
        
        $stmt->bind_param("i", $incidentId);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
        return $res;
    }

    public function delete($id) {
        $id = (int)$id;
        if ($id <= 0) return false;
        $res = $this->conn->query("DELETE FROM atm_update WHERE incident_id = $id");
        if ($res) {
            AuditLog::log("DELETE_INCIDENT", "Deleted incident ID: " . $id);
        }
        return $res;
    }
}
