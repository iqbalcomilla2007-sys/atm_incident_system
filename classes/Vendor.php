<?php
class Vendor {
    private $conn;

    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }

    private function tableExists($table) {
        $res = $this->conn->query("SHOW TABLES LIKE '".$this->conn->real_escape_string($table)."'");
        return $res && $res->num_rows > 0;
    }

    private function columnExists($table, $column) {
        $res = $this->conn->query("SHOW COLUMNS FROM `".$this->conn->real_escape_string($table)."` LIKE '".$this->conn->real_escape_string($column)."'");
        return $res && $res->num_rows > 0;
    }

    public function syncVendorNameEverywhere($oldName, $newName) {
        if ($oldName === $newName) return;
        
        $syncTargets = [
            ['table' => 'atm_master', 'column' => 'atm_vendor'],
            ['table' => 'atm_master', 'column' => 'ups_vendor'],
            ['table' => 'atm_update', 'column' => 'responsible_vendor_name'],
            ['table' => 'penalty_reports', 'column' => 'vendor_name'],
        ];
        
        foreach ($syncTargets as $target) {
            if ($this->tableExists($target['table']) && $this->columnExists($target['table'], $target['column'])) {
                $stmt = $this->conn->prepare("UPDATE `{$target['table']}` SET `{$target['column']}` = ? WHERE TRIM(`{$target['column']}`) = ?");
                if ($stmt) {
                    $stmt->bind_param("ss", $newName, $oldName);
                    $stmt->execute();
                }
            }
        }
    }

    public function getAll($search = '') {
        $sql = "SELECT v.*, 
                (SELECT GROUP_CONCAT(contact_value SEPARATOR '<br>') FROM vendor_contacts WHERE vendor_id = v.id AND contact_type='mobile') as mobiles,
                (SELECT GROUP_CONCAT(contact_value SEPARATOR '<br>') FROM vendor_contacts WHERE vendor_id = v.id AND contact_type='email') as emails,
                (SELECT GROUP_CONCAT(contact_value SEPARATOR '<br>') FROM vendor_contacts WHERE vendor_id = v.id AND contact_type='address') as addresses
                FROM vendor_master v WHERE v.status = 1";
        
        if ($search !== '') {
            $like = "%" . $this->conn->real_escape_string($search) . "%";
            $sql .= " AND (vendor_name LIKE '$like' OR EXISTS (SELECT 1 FROM vendor_contacts WHERE vendor_id=v.id AND contact_value LIKE '$like'))";
        }
        $sql .= " ORDER BY vendor_name ASC";
        
        return $this->conn->query($sql);
    }

    public function getById($id) {
        $id = (int)$id;
        $res = $this->conn->query("SELECT * FROM vendor_master WHERE id = $id");
        return $res ? $res->fetch_assoc() : null;
    }

    public function getContacts($vendorId) {
        $vendorId = (int)$vendorId;
        $contacts = ['mobile' => [], 'email' => [], 'address' => []];
        $res = $this->conn->query("SELECT * FROM vendor_contacts WHERE vendor_id = $vendorId");
        if ($res) {
            while ($c = $res->fetch_assoc()) {
                $contacts[$c['contact_type']][] = $c['contact_value'];
            }
        }
        return $contacts;
    }

    public function save($data) {
        $id = (int)($data['id'] ?? 0);
        $vendorName = trim($data['vendor_name'] ?? '');
        $vendorType = trim($data['vendor_type'] ?? '');
        $mobiles = $data['mobiles'] ?? [];
        $emails = $data['emails'] ?? [];
        $addresses = $data['addresses'] ?? [];

        if ($vendorName === '') {
            return ["success" => false, "error" => "Vendor name is required."];
        }

        $this->conn->begin_transaction();
        try {
            if ($id > 0) {
                // Update
                $oldRow = $this->getById($id);
                $stmt = $this->conn->prepare("UPDATE vendor_master SET vendor_name = ?, vendor_type = ? WHERE id = ?");
                $stmt->bind_param("ssi", $vendorName, $vendorType, $id);
                $stmt->execute();

                if ($oldRow && $oldRow['vendor_name'] !== $vendorName) {
                    $this->syncVendorNameEverywhere($oldRow['vendor_name'], $vendorName);
                }

                $this->conn->query("DELETE FROM vendor_contacts WHERE vendor_id = $id");
                $vendorId = $id;
            } else {
                // Insert
                $stmt = $this->conn->prepare("INSERT INTO vendor_master (vendor_name, vendor_type, status, created_at) VALUES (?, ?, 1, NOW())");
                $stmt->bind_param("ss", $vendorName, $vendorType);
                $stmt->execute();
                $vendorId = $this->conn->insert_id;
            }

            // Contacts
            $contactStmt = $this->conn->prepare("INSERT INTO vendor_contacts (vendor_id, contact_type, contact_value) VALUES (?, ?, ?)");
            if (is_array($mobiles)) {
                foreach ($mobiles as $m) {
                    $m = trim($m);
                    if ($m !== '') { $t='mobile'; $contactStmt->bind_param("iss", $vendorId, $t, $m); $contactStmt->execute(); }
                }
            }
            if (is_array($emails)) {
                foreach ($emails as $e) {
                    $e = trim($e);
                    if ($e !== '') { $t='email'; $contactStmt->bind_param("iss", $vendorId, $t, $e); $contactStmt->execute(); }
                }
            }
            if (is_array($addresses)) {
                foreach ($addresses as $a) {
                    $a = trim($a);
                    if ($a !== '') { $t='address'; $contactStmt->bind_param("iss", $vendorId, $t, $a); $contactStmt->execute(); }
                }
            }

            $this->conn->commit();
            return ["success" => true];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    public function delete($id) {
        $id = (int)$id;
        if ($id <= 0) return false;
        return $this->conn->query("UPDATE vendor_master SET status = 0 WHERE id = $id");
    }
}
