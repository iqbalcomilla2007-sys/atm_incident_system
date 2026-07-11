<?php

/**
 * Fetches vendor options for dropdowns
 */
function cctv_fetch_vendor_options($conn) {
    $vendors = [];
    $res = $conn->query("SELECT id, vendor_name FROM cctv_vendors WHERE is_active = 1 ORDER BY vendor_name ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $vendors[] = $row;
        }
    }
    return $vendors;
}

/**
 * Generates a unique requisition number
 */
function cctv_generate_requisition_no($conn) {
    $year = date('Y');
    $month = date('m');
    $prefix = "REQ-CCTV-$year$month-";
    
    $res = $conn->query("SELECT requisition_no FROM cctv_set_requisition WHERE requisition_no LIKE '$prefix%' ORDER BY id DESC LIMIT 1");
    $lastNum = 0;
    if ($res && $row = $res->fetch_assoc()) {
        $parts = explode('-', $row['requisition_no']);
        $lastNum = (int)end($parts);
    }
    
    $newNum = str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
    return $prefix . $newNum;
}

/**
 * Generates a unique spare requisition number
 */
function cctv_generate_spare_req_no($conn) {
    $year = date('Y');
    $month = date('m');
    $prefix = "SPR-CCTV-$year$month-";
    
    $res = $conn->query("SELECT requisition_no FROM cctv_spare_requisition WHERE requisition_no LIKE '$prefix%' ORDER BY id DESC LIMIT 1");
    $lastNum = 0;
    if ($res && $row = $res->fetch_assoc()) {
        $parts = explode('-', $row['requisition_no']);
        $lastNum = (int)end($parts);
    }
    
    $newNum = str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
    return $prefix . $newNum;
}

/**
 * Generates a unique dispatch number
 */
function cctv_generate_dispatch_no($conn) {
    $year = date('Y');
    $month = date('m');
    $prefix = "DSP-CCTV-$year$month-";
    
    $res = $conn->query("SELECT dispatch_no FROM cctv_branch_dispatch WHERE dispatch_no LIKE '$prefix%' ORDER BY id DESC LIMIT 1");
    $lastNum = 0;
    if ($res && $row = $res->fetch_assoc()) {
        $parts = explode('-', $row['dispatch_no']);
        $lastNum = (int)end($parts);
    }
    
    $newNum = str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
    return $prefix . $newNum;
}

/**
 * Fetches item options from master
 */
function cctv_fetch_item_options($conn, $category = null) {
    $items = [];
    $sql = "SELECT * FROM cctv_item_master WHERE is_active = 1";
    if ($category) {
        $sql .= " AND item_category = '" . mysqli_real_escape_string($conn, $category) . "'";
    }
    $sql .= " ORDER BY item_name ASC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $items[] = $row;
        }
    }
    return $items;
}

/**
 * Logs status changes
 */
function cctv_log_status($conn, $table, $ref_id, $old_status, $new_status, $remarks, $user_id) {
    // Basic logging implementation - assume table exists or skip
    $sql = "INSERT INTO cctv_status_logs (ref_table, ref_id, old_status, new_status, remarks, changed_by, changed_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("sisssi", $table, $ref_id, $old_status, $new_status, $remarks, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

function cctv_get_or_create_location($conn, $data) {
    $atm_id = $data['atm_id'];
    $booth_name = $data['booth_name'];
    
    // Check if location already exists for this ATM ID (if provided)
    if (!empty($atm_id)) {
        $stmt = $conn->prepare("SELECT id FROM cctv_locations WHERE atm_id = ? LIMIT 1");
        $stmt->bind_param("s", $atm_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($res) return $res['id'];
    }

    // Otherwise create a new one
    $sql = "INSERT INTO cctv_locations (atm_master_id, atm_id, branch_name, booth_name, zone_name, group_no, service_type, machine_type, is_new_booth, remarks) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssssssis", 
        $data['atm_master_id'], 
        $data['atm_id'], 
        $data['branch_name'], 
        $data['booth_name'], 
        $data['zone_name'], 
        $data['group_no'], 
        $data['service_type'], 
        $data['machine_type'], 
        $data['is_new_booth'], 
        $data['remarks']
    );
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();
    
    return $newId;
}
function cctv_spare_requisition_statuses() {
    return [
        'Draft' => 'Draft',
        'Received' => 'Received',
        'Vendor_Assigned' => 'Vendor Assigned',
        'Stock_Approved' => 'Stock Approved',
        'Forwarded_to_Branch' => 'Forwarded to Branch',
        'Installed' => 'Installed',
        'Old_Stock_Installed' => 'Old stock installed',
        'Bill_Submitted' => 'Bill Submitted',
        'Forwarded_to_FAD' => 'Forwarded to FAD',
        'Paid' => 'Paid',
        'Closed' => 'Closed',
        'Cancelled' => 'Cancelled'
    ];
}

function cctv_normalize_spare_requisition_status($status) {
    $status = trim((string)$status);
    $aliases = [
        'Vendor Assigned' => 'Vendor_Assigned',
        'Stock Approved' => 'Stock_Approved',
        'Old stock installed' => 'Old_Stock_Installed',
        'Old Stock Installed' => 'Old_Stock_Installed',
        'Forwarded to Branch' => 'Forwarded_to_Branch',
        'Bill sent to FAD' => 'Bill_Submitted',
        'Bill Submitted' => 'Bill_Submitted',
        'Forwarded to FAD' => 'Forwarded_to_FAD',
        'Waiting for Approval' => 'Received',
        'Waiting for Techinal Evaluation' => 'Received',
        'Waiting for Technical Evaluation' => 'Received'
    ];

    return $aliases[$status] ?? $status;
}

function cctv_spare_requisition_status_label($status) {
    $status = cctv_normalize_spare_requisition_status($status);
    $statuses = cctv_spare_requisition_statuses();
    return $statuses[$status] ?? str_replace('_', ' ', $status);
}
