<?php
class AtmMaster {
    private $conn;

    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }

    private function getVendorNameById($vendorId) {
        $vendorId = (int)$vendorId;
        if ($vendorId <= 0) return '';
        $stmt = $this->conn->prepare("SELECT vendor_name FROM vendor_master WHERE id = ? LIMIT 1");
        if (!$stmt) return '';
        $stmt->bind_param("i", $vendorId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        return trim($row['vendor_name'] ?? '');
    }

    public function branchKeySql($expr) {
        $key = "LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(IFNULL($expr, ''), ',', 1), '(', 1)))";
        foreach (['jessore'=>'jashore','barisal'=>'barishal','comilla'=>'cumilla','gonj'=>'ganj','nawabgonj'=>'nawabganj'] as $from=>$to) {
            $key = "REPLACE($key, '$from', '$to')";
        }
        foreach ([' sub branch',' sub-branch',' branch',' br.',' br',' sme',' krishi'] as $word) {
            $key = "REPLACE($key, '$word', '')";
        }
        foreach ([' ','-','/','.',',','&','(',')','?','`'] as $char) {
            $key = "REPLACE($key, '$char', '')";
        }
        return "REPLACE($key, CHAR(39), '')";
    }

    public function branchMatchSql($contactKey, $masterKey) {
        return "($contactKey = $masterKey OR ($masterKey = 'agrabad' AND $contactKey = 'agrabadcorporate'))";
    }

    public function atmIdKeySql($expr) {
        $key = "LOWER(TRIM(IFNULL($expr, '')))";
        foreach (['atm id','atm','id',' ','-','_','/','.',':'] as $part) {
            $key = "REPLACE($key, '$part', '')";
        }
        return $key;
    }

   public function save($data) {
        $id = (int)($data['id'] ?? 0);
        $atm_id = trim($data['atm_id'] ?? '');
        $atm_name = trim($data['atm_name'] ?? '');
        $zone_name = trim($data['zone_name'] ?? '');
        $branch_name = trim($data['branch_name'] ?? '');
        $atm_vendor_id = (int)($data['atm_vendor_id'] ?? 0);
        $ups_vendor_id = (int)($data['ups_vendor_id'] ?? 0);
        $group_no = trim($data['group_no'] ?? '');
        $monitoring_ip = trim($data['monitoring_ip'] ?? '');
        $internal_ip = trim($data['internal_ip'] ?? '');
        $subnet_mask = trim($data['subnet_mask'] ?? '');
        $gateway = trim($data['gateway'] ?? '');

        // --- zone_branch_map থেকে অটোমেটিক branch_code সিঙ্ক করার লজিক ---
        $branch_code = '';
        if ($branch_name !== '') {
            $stmtB = $this->conn->prepare("SELECT branch_code FROM zone_branch_map WHERE LOWER(TRIM(branch_name)) = LOWER(TRIM(?)) LIMIT 1");
            if ($stmtB) {
                $stmtB->bind_param("s", $branch_name);
                $stmtB->execute();
                $resB = $stmtB->get_result();
                if ($rowB = $resB->fetch_assoc()) {
                    $branch_code = trim($rowB['branch_code'] ?? '');
                }
            }
        }

        $atm_vendor = $this->getVendorNameById($atm_vendor_id);
        $ups_vendor = $this->getVendorNameById($ups_vendor_id);

        if ($atm_id === '') return ["success" => false, "error" => "ATM ID required"];

        if ($id > 0) {
            $oldRes = $this->conn->query("SELECT atm_id FROM atm_master WHERE id=$id");
            $old_atm_id = $oldRes ? $oldRes->fetch_assoc()['atm_id'] : '';

            $stmt = $this->conn->prepare("UPDATE atm_master SET atm_id=?, atm_name=?, zone_name=?, branch_name=?, branch_code=?, atm_vendor_id=?, atm_vendor=?, ups_vendor_id=?, ups_vendor=?, group_no=?, monitoring_ip=?, internal_ip=?, subnet_mask=?, gateway=? WHERE id=?");
            $stmt->bind_param("sssssisissssssi", $atm_id, $atm_name, $zone_name, $branch_name, $branch_code, $atm_vendor_id, $atm_vendor, $ups_vendor_id, $ups_vendor, $group_no, $monitoring_ip, $internal_ip, $subnet_mask, $gateway, $id);
            if ($stmt->execute()) {
                if ($old_atm_id) {
                    $sync = $this->conn->prepare("UPDATE atm_update SET atm_id=?, atm_name=?, group_no=?, atm_vendor=?, ups_vendor=? WHERE atm_id=? AND incident_status='Open'");
                    if ($sync) {
                        $sync->bind_param("ssssss", $atm_id, $atm_name, $group_no, $atm_vendor, $ups_vendor, $old_atm_id);
                        $sync->execute();
                    }
                }
                return ["success" => true, "msg" => "Record updated and synchronized."];
            }
            return ["success" => false, "error" => $this->conn->error];
        } else {
            // এখানে ১৪টি প্রশ্নবোধক চিহ্ন (?) দেওয়া হলো
            $stmt = $this->conn->prepare("INSERT INTO atm_master (atm_id, atm_name, zone_name, branch_name, branch_code, atm_vendor_id, atm_vendor, ups_vendor_id, ups_vendor, group_no, monitoring_ip, internal_ip, subnet_mask, gateway) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("sssssisissssss", $atm_id, $atm_name, $zone_name, $branch_name, $branch_code, $atm_vendor_id, $atm_vendor, $ups_vendor_id, $ups_vendor, $group_no, $monitoring_ip, $internal_ip, $subnet_mask, $gateway);
            if ($stmt->execute()) {
                return ["success" => true, "msg" => "ATM Master added successfully."];
            }
            return ["success" => false, "error" => $this->conn->error];
        }
    }

    public function delete($id) {
        $id = (int)$id;
        if ($id <= 0) return false;
        try {
            return $this->conn->query("DELETE FROM atm_master WHERE id = $id");
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1451) {
                $_SESSION['error_msg'] = "Cannot delete this ATM because it has associated incidents.";
            } else {
                $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
            }
            return false;
        }
    }

    public function getVendorSummary() {
        $summary = [];
        $res = $this->conn->query("SELECT v.vendor_name, 
            SUM(CASE WHEN a.atm_id LIKE 'IBBL%' OR a.atm_id LIKE 'IBOA%' THEN 1 ELSE 0 END) as atm_cnt,
            SUM(CASE WHEN a.atm_id LIKE 'IBCR%' THEN 1 ELSE 0 END) as crm_cnt,
            (SELECT COUNT(*) FROM atm_master WHERE ups_vendor_id = v.id OR LOWER(ups_vendor) = LOWER(v.vendor_name)) as ups_cnt
            FROM vendor_master v 
            LEFT JOIN atm_master a ON (a.atm_vendor_id = v.id OR LOWER(a.atm_vendor) = LOWER(v.vendor_name))
            WHERE v.status=1 GROUP BY v.id ORDER BY v.vendor_name ASC");
        if ($res) {
            while ($r = $res->fetch_assoc()) $summary[] = $r;
        }
        return $summary;
    }

    public function getTotalCount($isAdmin, $assignedZone) {
        $query = "SELECT COUNT(*) as tot FROM atm_master";
        if (!$isAdmin) {
            $query .= " WHERE zone_name='" . $this->conn->real_escape_string($assignedZone) . "'";
        }
        $res = $this->conn->query($query);
        return $res ? $res->fetch_assoc()['tot'] : 0;
    }

   public function getList($search = '', $f_atm_v_id = 0, $f_ups_v_id = 0, $f_zone = '', $f_group = '', $isAdmin = false, $assignedZone = '') {
        $conn = Database::getInstance()->getConnection();
        
        $masterKey = $this->branchKeySql('a.branch_name');
        $contactKey = $this->branchKeySql('c.branch_name');
        $branchMatchCondition = "(
            (c.branch_code = IFNULL(a.branch_code, zb.branch_code) AND c.branch_code <> '' AND c.branch_code IS NOT NULL)
            OR
            (
                (c.branch_code IS NULL OR c.branch_code = '' OR IFNULL(a.branch_code, zb.branch_code) IS NULL OR IFNULL(a.branch_code, zb.branch_code) = '')
                AND
                " . $this->branchMatchSql($contactKey, $masterKey) . "
            )
        )";
        
        $zbKey = $this->branchKeySql('zb.branch_name');
        $zbMatchCondition = $this->branchMatchSql($zbKey, $masterKey);
        
        $atmIdKey = $this->atmIdKeySql('a.atm_id');
        $sgAtmIdKey = $this->atmIdKeySql('s.atm_id');
        $atmBranchKey = $this->branchKeySql('a.branch_name');
        $sgBranchKey = $this->branchKeySql('s.branch_name');
        
        $sgMatchCondition = "(
            ($sgAtmIdKey = $atmIdKey AND $sgAtmIdKey <> '' AND $sgAtmIdKey IS NOT NULL)
            OR
            (
                ($sgAtmIdKey IS NULL OR $sgAtmIdKey = '')
                AND
                ($sgBranchKey = $atmBranchKey)
            )
        )";
        
        $sql = "SELECT a.*, 
                       IFNULL(a.branch_code, zb.branch_code) AS branch_code,
                       c.manager_name AS manager, 
                       c.manager_mobile AS manager_mobile,
                       c.custodian1_name AS cust1, 
                       c.custodian1_mobile AS cust1_mobile,
                       c.custodian2_name AS cust2, 
                       c.custodian2_mobile AS cust2_mobile,
                       s.sg1_name AS sg1,
                       s.sg1_mobile AS sg1_mob,
                       s.sg2_name AS sg2,
                       s.sg2_mobile AS sg2_mob,
                       s.sg3_name AS sg3,
                       s.sg3_mobile AS sg3_mob,
                       s.company_details AS sg_company,
                       s.supervisor_details AS sg_supervisor
                FROM atm_master a 
                LEFT JOIN zone_branch_map zb ON $zbMatchCondition
                LEFT JOIN atm_contact c ON $branchMatchCondition 
                LEFT JOIN atm_sg s ON $sgMatchCondition 
                WHERE 1=1"; 
                
        $types = "";
        $params = [];

        if (!$isAdmin && $assignedZone !== '') {
            $sql .= " AND a.zone_name = ?";
            $types .= "s";
            $params[] = $assignedZone;
        }

        if ($f_zone !== '') {
            $sql .= " AND a.zone_name = ?";
            $types .= "s";
            $params[] = $f_zone;
        }

        if ($f_group !== '') {
            $sql .= " AND a.group_no = ?";
            $types .= "s"; 
            $params[] = $f_group;
        }

        if ($f_atm_v_id > 0) {
            $sql .= " AND a.atm_vendor_id = ?";
            $types .= "i";
            $params[] = $f_atm_v_id;
        }

        if ($f_ups_v_id > 0) {
            $sql .= " AND a.ups_vendor_id = ?";
            $types .= "i";
            $params[] = $f_ups_v_id;
        }

        if ($search !== '') {
            $sql .= " AND (a.atm_id LIKE ? OR a.atm_name LIKE ? OR a.branch_name LIKE ?)";
            $types .= "sss";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        // এখানে GROUP BY a.id যোগ করা হয়েছে ডুপ্লিকেট রিমুভ করার জন্য
        $sql .= " GROUP BY a.id ORDER BY a.atm_id ASC";

        $stmt = $conn->prepare($sql);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        return $stmt->get_result();
    }
    public function getById($id) {
        $id = (int)$id;
        if ($id <= 0) return null;
        
        $masterKey = $this->branchKeySql('a.branch_name');
        $zbKey = $this->branchKeySql('zb.branch_name');
        $zbMatchCondition = $this->branchMatchSql($zbKey, $masterKey);

        // Edit করার সময়ও যেন branch_code ম্যাপ টেবিল থেকে ব্যাকআপ পায়
        $res = $this->conn->query("SELECT a.*, IFNULL(a.branch_code, zb.branch_code) AS branch_code 
                                   FROM atm_master a 
                                   LEFT JOIN zone_branch_map zb ON $zbMatchCondition 
                                   WHERE a.id = $id");
        return $res ? $res->fetch_assoc() : null;
    }
}
?>