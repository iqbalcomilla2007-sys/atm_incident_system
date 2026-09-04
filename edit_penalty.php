<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('manage_penalty');

if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('Invalid ID.');
}

$stmt = $conn->prepare("SELECT * FROM penalty_reports WHERE id = ? LIMIT 1");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    die('Penalty record not found.');
}

// Ensure return URL is properly captured
$return_url = $_GET['return_url'] ?? 'penalty_summary_report.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Penalty</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f4f6f9;}
        .container{width:95%;max-width:1100px;margin:20px auto;}
        .card{background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.08);padding:20px;}
        .grid{display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:14px;}
        label{display:block;margin-bottom:5px;font-weight:bold;font-size:13px;}
        input[type="text"], textarea, select, input[type="number"]{
            width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;box-sizing:border-box;
        }
        textarea{min-height:90px;}
        .btn{display:inline-block;text-decoration:none;padding:10px 14px;border-radius:6px;border:none;cursor:pointer;color:#fff;margin-right:8px;}
        .btn-green{background:#28a745;}
        .btn-secondary{background:#6c757d;}
        .readonly-box{background:#f8f9fa;}
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <div class="card">
        <h2>Edit Penalty</h2>

        <form method="post" action="update_penalty.php">
            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
            <input type="hidden" name="return_url" value="<?php echo h($return_url); ?>">

            <div class="grid">
                <div>
                    <label>Penalty ID</label>
                    <input type="text" value="<?php echo h($row['penalty_id']); ?>" readonly class="readonly-box">
                </div>

                <div>
                    <label>Incident ID</label>
                    <input type="text" value="<?php echo h($row['incident_id']); ?>" readonly class="readonly-box">
                </div>

                <div>
                    <label for="vendor_ticket_no">Vendor Ticket No</label>
                    <input type="text" name="vendor_ticket_no" id="vendor_ticket_no" value="<?php echo h($row['vendor_ticket_no'] ?? ''); ?>">
                </div>

                <div>
                    <label for="atm_id">ATM ID</label>
                    <input type="text" name="atm_id" id="atm_id" value="<?php echo h($row['atm_id']); ?>" required>
                </div>

                <div>
                    <label for="incident_name">Incident Name</label>
                    <input type="text" name="incident_name" id="incident_name" value="<?php echo h($row['incident_name']); ?>" required>
                </div>

                <div>
                    <label for="vendor_name">Vendor Name</label>
                    <input type="text" name="vendor_name" id="vendor_name" value="<?php echo h($row['vendor_name']); ?>" required>
                </div>

                <div>
                    <label for="service_type">Service Type</label>
                    <select name="service_type" id="service_type" required>
                        <option value="ATM" <?php echo ($row['service_type'] === 'ATM') ? 'selected' : ''; ?>>ATM</option>
                        <option value="CRM" <?php echo ($row['service_type'] === 'CRM') ? 'selected' : ''; ?>>CRM</option>
                        <option value="UPS" <?php echo ($row['service_type'] === 'UPS') ? 'selected' : ''; ?>>UPS</option>
                    </select>
                </div>

                <div>
                    <label for="machine_type">Machine Type</label>
                    <select name="machine_type" id="machine_type">
                        <option value="">Select</option>
                        <option value="ATM" <?php echo ($row['machine_type'] === 'ATM') ? 'selected' : ''; ?>>ATM</option>
                        <option value="CRM" <?php echo ($row['machine_type'] === 'CRM') ? 'selected' : ''; ?>>CRM</option>
                    </select>
                </div>

                <div>
                    <label for="penalty_from">Penalty From</label>
                    <input type="text" name="penalty_from" id="penalty_from"
                           value="<?php echo h(!empty($row['penalty_from']) ? date('Y-m-d H:i', strtotime($row['penalty_from'])) : ''); ?>" required>
                </div>

                <div>
                    <label>Created At</label>
                    <input type="text" value="<?php echo h(!empty($row['created_at']) ? date('d/m/Y h:i A', strtotime($row['created_at'])) : ''); ?>" readonly class="readonly-box">
                </div>

                <div>
                    <label>Down Time Minutes</label>
                    <input type="text" value="<?php echo h($row['down_time_minutes']); ?>" readonly class="readonly-box">
                </div>

                <div>
                    <label for="deduction_rate">Deduction Rate (%)</label>
                    <input type="number" step="0.01" name="deduction_rate" id="deduction_rate" value="<?php echo h($row['deduction_rate'] ?? 0); ?>">
                </div>

                <div>
                    <label for="penalty_amount">Penalty Amount</label>
                    <input type="number" step="0.01" name="penalty_amount" id="penalty_amount" value="<?php echo h($row['penalty_amount']); ?>" required>
                </div>
            </div>

            <div style="margin-top:14px;">
                <label for="remarks">Remarks</label>
                <textarea name="remarks" id="remarks"><?php echo h($row['remarks'] ?? ''); ?></textarea>
            </div>

            <div style="margin-top:18px;">
                <button type="submit" class="btn btn-green">Update Penalty</button>
                <a href="<?php echo h($return_url); ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// Initialize Datepicker for Penalty From
document.addEventListener('DOMContentLoaded', function() {
    flatpickr("#penalty_from", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",      // The format sent to backend
        altInput: true,
        altFormat: "d/m/Y h:i K",     // The format user sees (DD/MM/YYYY hh:mm AM/PM)
        allowInput: true
    });
});
</script>

</body>
</html>