<?php
// ১. এরর হ্যান্ডলিং ও ডিবাগিং
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';
include 'includes/cctv_helpers.php';

// এনকোডিং ঠিক করা যাতে ÃƒÆ’... সমস্যা না হয়
mysqli_set_charset($conn, "utf8");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction(); // ডাটা সেফটির জন্য ট্রানজ্যাকশন শুরু

        $editId   = (int)($_POST['id'] ?? 0);
        $isEdit   = $editId > 0;
        
        // ২. ফরম থেকে ডাটা রিসিভ করা
        $status         = mysqli_real_escape_string($conn, $_POST['status'] ?? 'Draft');
        $app_date       = $_POST['application_date'] ?? date('Y-m-d');
        $atm_id         = trim($_POST['atm_id'] ?? '');
        $prob           = mysqli_real_escape_string($conn, $_POST['problem_details'] ?? '');
        $branch_contact = mysqli_real_escape_string($conn, $_POST['branch_contact'] ?? '');
        $notes          = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
        $service_charge = (float)($_POST['service_charge'] ?? 0);
        $is_new_booth   = isset($_POST['is_new_booth']) ? 1 : 0;

        // ৩. লোকেশন ম্যানেজমেন্ট (cctv_locations)
        // cctv_helpers.php এর ফাংশন ব্যবহার করে লোকেশন আইডি বের করা
        $locationId = cctv_get_or_create_location($conn, [
            'atm_master_id' => $_POST['atm_master_id'] ?? null,
            'atm_id'        => $atm_id,
            'branch_name'   => $_POST['branch_name'] ?? '',
            'booth_name'    => $_POST['booth_name'] ?? '',
            'zone_name'     => $_POST['zone_name'] ?? '',
            'group_no'      => $_POST['group_no'] ?? '',
            'service_type'  => $_POST['service_type'] ?? 'ATM',
            'machine_type'  => $_POST['machine_type'] ?? 'ATM',
            'is_new_booth'  => $is_new_booth,
            'remarks'       => $_POST['location_remarks'] ?? ''
        ]);

        // ৪. মেইন রিকুইজিশন টেবিল সেভ অথবা আপডেট
        if ($isEdit) {
            $sql = "UPDATE cctv_spare_requisition SET 
                    cctv_location_id = '$locationId', 
                    application_date = '$app_date', 
                    problem_details = '$prob', 
                    status = '$status', 
                    branch_contact = '$branch_contact',
                    notes = '$notes',
                    service_charge = '$service_charge'
                    WHERE id = $editId";
            if (!$conn->query($sql)) { throw new Exception("Update Failed: " . $conn->error); }
            $spareReqId = $editId;
        } else {
            $req_no = $_POST['requisition_no'];
            $sql = "INSERT INTO cctv_spare_requisition 
                    (requisition_no, cctv_location_id, application_date, problem_details, status, branch_contact, notes, service_charge) 
                    VALUES ('$req_no', '$locationId', '$app_date', '$prob', '$status', '$branch_contact', '$notes', '$service_charge')";
            if (!$conn->query($sql)) { throw new Exception("Insert Failed: " . $conn->error); }
            $spareReqId = $conn->insert_id;
        }

        // ৫. আইটেম লিস্ট হ্যান্ডেল করা (cctv_spare_requisition_items)
        // আগের আইটেম মুছে নতুনগুলো দেওয়া
        $conn->query("DELETE FROM cctv_spare_requisition_items WHERE spare_requisition_id = $spareReqId");
        
        $item_ids   = $_POST['item_id'] ?? [];
        $qtys       = $_POST['qty'] ?? [];
        $sources    = $_POST['source_from'] ?? [];
        $stock_ids  = $_POST['stock_id'] ?? [];
        $remarksArr = $_POST['item_remarks'] ?? [];

        for ($i = 0; $i < count($item_ids); $i++) {
            $itemId = (int)$item_ids[$i];
            if ($itemId > 0) {
                $qty = (int)$qtys[$i];
                $src = $sources[$i];
                $stockId = !empty($stock_ids[$i]) ? (int)$stock_ids[$i] : "NULL";
                $item_rem = mysqli_real_escape_string($conn, $remarksArr[$i] ?? '');

                $item_sql = "INSERT INTO cctv_spare_requisition_items 
                             (spare_requisition_id, item_id, stock_id, qty, source_from, remarks) 
                             VALUES ($spareReqId, $itemId, $stockId, $qty, '$src', '$item_rem')";
                
                if (!$conn->query($item_sql)) { throw new Exception("Item Insert Error: " . $conn->error); }

                // ৬. স্টক ডিডাকশন লজিক (যদি স্ট্যাটাস Installed হয়)
                if ($status === 'Installed' && $stockId != "NULL") {
                    // স্টক থেকে মাল কমানো
                    $deduct_sql = "UPDATE cctv_stock SET qty = qty - $qty WHERE id = $stockId AND qty >= $qty";
                    $conn->query($deduct_sql);
                    
                    if ($conn->affected_rows > 0) {
                        // ট্রানজ্যাকশন হিস্ট্রিতে 'OUT' এন্ট্রি
                        $trans_rem = "Issued for Req #$spareReqId (Installed)";
                        $conn->query("INSERT INTO cctv_stock_transactions (stock_id, transaction_type, transaction_date, qty, remarks) 
                                      VALUES ($stockId, 'OUT', CURDATE(), $qty, '$trans_rem')");
                        
                        // যদি স্টক ০ হয়ে যায় তবে স্ট্যাটাস পরিবর্তন
                        $conn->query("UPDATE cctv_stock SET status = 'Issued' WHERE id = $stockId AND qty <= 0");
                    }
                }
            }
        }

        // ৭. রিমার্কস হিস্ট্রি সেভ করা
        $newRemark = trim($_POST['new_history_remark'] ?? '');
        if ($newRemark !== '') {
            $userId = $_SESSION['user_id'] ?? 0;
            $rem_sql = "INSERT INTO cctv_spare_requisition_remarks (spare_requisition_id, remark, created_by, created_at) 
                        VALUES ($spareReqId, '".mysqli_real_escape_string($conn, $newRemark)."', $userId, NOW())";
            $conn->query($rem_sql);
        }

        $conn->commit(); // সবকিছু ঠিক থাকলে ডাটাবেজে পার্মানেন্ট সেভ হবে
        header("Location: cctv_spare_requisition_list.php?msg=success");
        exit;

    } catch (Exception $e) {
        $conn->rollback(); // এরর হলে সব কাজ বাতিল হবে
        echo "<div style='color:red; font-family:sans-serif; padding:20px; border:1px solid red; background:#fff;'>";
        echo "<h3>Critical Database Error</h3>";
        echo "Message: " . $e->getMessage();
        echo "<br><br><a href='javascript:history.back()'>Go Back and Try Again</a>";
        echo "</div>";
        exit;
    }
}
?>