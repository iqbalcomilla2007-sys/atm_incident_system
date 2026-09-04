<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';
include 'includes/cctv_helpers.php';

Auth::requirePermission('cctv_requisition_view');

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die('Invalid requisition ID.');
}

/* =========================
   Requisition + Location + Vendor
========================= */
$sql = "SELECT r.*,
               l.atm_master_id,
               l.atm_id,
               l.branch_name,
               l.booth_name,
               l.zone_name,
               l.group_no,
               l.service_type,
               l.is_new_booth,
               l.remarks AS location_remarks,
               v.vendor_name
        FROM cctv_set_requisition r
        INNER JOIN cctv_locations l ON l.id = r.cctv_location_id
        LEFT JOIN cctv_vendors v ON v.id = r.selected_vendor_id
        WHERE r.id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed (main query): " . $conn->error);
}
$stmt->bind_param("i", $id);
if (!$stmt->execute()) {
    die("Execute failed (main query): " . $stmt->error);
}
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if (!$data) {
    die('Requisition not found.');
}

/* =========================
   Requisition Items
========================= */
$itemSql = "SELECT ri.id, ri.qty, ri.remarks,
                   im.item_name, im.item_category
            FROM cctv_set_requisition_items ri
            INNER JOIN cctv_item_master im ON im.id = ri.item_id
            WHERE ri.requisition_id = ?
            ORDER BY ri.id ASC";

$stmt = $conn->prepare($itemSql);
if (!$stmt) {
    die("Prepare failed (items): " . $conn->error);
}
$stmt->bind_param("i", $id);
if (!$stmt->execute()) {
    die("Execute failed (items): " . $stmt->error);
}
$itemResult = $stmt->get_result();

$items = [];
while ($row = $itemResult->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

/* =========================
   Tender summary
========================= */
$tenderSummary = [];
$tenderSql = "
    SELECT tb.id, tb.requisition_id, tb.bid_date, tb.total_bid_amount,
           tb.technical_status, tb.financial_status,
           tb.remarks,
           v.vendor_name
    FROM cctv_tender_bids tb
    INNER JOIN cctv_vendors v ON v.id = tb.vendor_id
    WHERE tb.requisition_id = ?
    ORDER BY tb.id DESC
";
$stmt = $conn->prepare($tenderSql);
if (!$stmt) {
    die("Prepare failed (tender): " . $conn->error);
}
$stmt->bind_param("i", $id);
if (!$stmt->execute()) {
    die("Execute failed (tender): " . $stmt->error);
}
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $tenderSummary[] = $row;
}
$stmt->close();

/* =========================
   QC summary
========================= */
$qcSummary = [];
$qcSql = "
    SELECT id, requisition_id, qc_date, qc_status, remarks
    FROM cctv_qc_entries
    WHERE requisition_id = ?
    ORDER BY id DESC
";
$stmt = $conn->prepare($qcSql);
if (!$stmt) {
    die("Prepare failed (qc): " . $conn->error);
}
$stmt->bind_param("i", $id);
if (!$stmt->execute()) {
    die("Execute failed (qc): " . $stmt->error);
}
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $qcSummary[] = $row;
}
$stmt->close();

/* =========================
   Work order summary
========================= */
$workOrder = null;
$woSql = "
    SELECT *
    FROM cctv_work_orders
    WHERE requisition_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($woSql);
if (!$stmt) {
    die("Prepare failed (work order): " . $conn->error);
}
$stmt->bind_param("i", $id);
if (!$stmt->execute()) {
    die("Execute failed (work order): " . $stmt->error);
}
$res = $stmt->get_result();
$workOrder = $res->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CCTV Requisition View</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1300px;
            margin: auto;
        }
        .card {
            background: #fff;
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }
        h2, h3 {
            margin-top: 0;
        }
        .top-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .btn {
            display: inline-block;
            padding: 9px 14px;
            border-radius: 6px;
            text-decoration: none;
            color: #fff;
            background: #0d6efd;
            font-size: 14px;
        }
        .btn-success { background: #198754; }
        .btn-secondary { background: #6c757d; }
        .btn-dark { background: #212529; }

        .grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }
        .field {
            background: #fafafa;
            border: 1px solid #e5e5e5;
            border-radius: 6px;
            padding: 10px;
        }
        .label {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }
        .value {
            font-size: 14px;
            font-weight: bold;
            color: #222;
            word-break: break-word;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            font-size: 13px;
            vertical-align: top;
            text-align: left;
        }
        th {
            background: #f1f1f1;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 5px;
            color: #fff;
            font-size: 12px;
        }
        .Draft { background: #6c757d; }
        .Received { background: #0dcaf0; color:#000; }
        .Forwarded_to_CPD { background: #0d6efd; }
        .Tender_Received { background: #6f42c1; }
        .Under_Technical_Evaluation { background: #ffc107; color: #000; }
        .Technically_Qualified { background: #20c997; color:#000; }
        .Work_Order_Issued { background: #fd7e14; }
        .Product_Delivered { background: #6610f2; }
        .QC_Passed { background: #198754; }
        .Installation_Permission_Given { background: #0d6efd; }
        .Installed { background: #198754; }
        .Closed { background: #000; }
        .Cancelled { background: #dc3545; }

        @media (max-width: 1100px) {
            .grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 600px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">

    <h2>CCTV Requisition Details</h2>

    <div class="top-actions">
        <a class="btn btn-secondary" href="cctv_set_requisition_list.php">Back to List</a>
        <a class="btn" href="cctv_set_requisition.php?id=<?php echo (int)$data['id']; ?>">Edit Requisition</a>
        <a class="btn btn-dark" href="cctv_requisition_print.php?id=<?php echo (int)$data['id']; ?>">Print</a>
        <a class="btn" href="cctv_tender_bids.php?requisition_id=<?php echo (int)$data['id']; ?>">Tender</a>
        <a class="btn" href="cctv_work_order_entry.php?requisition_id=<?php echo (int)$data['id']; ?>">Work Order</a>
        <a class="btn" href="cctv_qc_entry.php?requisition_id=<?php echo (int)$data['id']; ?>">QC Entry</a>
        <a class="btn btn-success" href="cctv_installation_entry.php?requisition_id=<?php echo (int)$data['id']; ?>">Installation</a>
    </div>

    <div class="card">
        <h3>Requisition Information</h3>
        <div class="grid">
            <div class="field">
                <div class="label">Requisition ID</div>
                <div class="value"><?php echo (int)$data['id']; ?></div>
            </div>
            <div class="field">
                <div class="label">Requisition No</div>
                <div class="value"><?php echo h($data['requisition_no']); ?></div>
            </div>
            <div class="field">
                <div class="label">Requisition Date</div>
                <div class="value"><?php echo h($data['requisition_date']); ?></div>
            </div>
            <div class="field">
                <div class="label">Status</div>
                <div class="value">
                    <span class="badge <?php echo h($data['status']); ?>">
                        <?php echo h($data['status']); ?>
                    </span>
                </div>
            </div>

            <div class="field">
                <div class="label">Source Type</div>
                <div class="value"><?php echo h($data['source_type']); ?></div>
            </div>
            <div class="field">
                <div class="label">Received Date</div>
                <div class="value"><?php echo h($data['received_date']); ?></div>
            </div>
            <div class="field">
                <div class="label">Forwarded to CPD Date</div>
                <div class="value"><?php echo h($data['forwarded_to_cpd_date']); ?></div>
            </div>
            <div class="field">
                <div class="label">Tender Received Date</div>
                <div class="value"><?php echo h($data['tender_received_date']); ?></div>
            </div>

            <div class="field">
                <div class="label">Technical Evaluation Date</div>
                <div class="value"><?php echo h($data['technical_evaluation_date']); ?></div>
            </div>
            <div class="field">
                <div class="label">QC Date</div>
                <div class="value"><?php echo h($data['qc_date']); ?></div>
            </div>
            <div class="field">
                <div class="label">Installation Permission Date</div>
                <div class="value"><?php echo h($data['installation_permission_date']); ?></div>
            </div>
            <div class="field">
                <div class="label">Installation Date</div>
                <div class="value"><?php echo h($data['installation_date']); ?></div>
            </div>

            <div class="field">
                <div class="label">Work Order No</div>
                <div class="value"><?php echo h($data['work_order_no']); ?></div>
            </div>
            <div class="field">
                <div class="label">Work Order Date</div>
                <div class="value"><?php echo h($data['work_order_date']); ?></div>
            </div>
            <div class="field">
                <div class="label">Selected Vendor</div>
                <div class="value"><?php echo h($data['vendor_name']); ?></div>
            </div>
        </div>

        <div style="margin-top:15px;">
            <div class="field">
                <div class="label">Cause / Background</div>
                <div class="value"><?php echo nl2br(h($data['cause'] ?? '')); ?></div>
            </div>
        </div>

        <div style="margin-top:15px;">
            <div class="field">
                <div class="label">Notes</div>
                <div class="value"><?php echo nl2br(h($data['notes'] ?? '')); ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <h3>Location Information</h3>
        <div class="grid">
            <div class="field">
                <div class="label">ATM Master ID</div>
                <div class="value"><?php echo h($data['atm_master_id']); ?></div>
            </div>
            <div class="field">
                <div class="label">ATM ID</div>
                <div class="value"><?php echo h($data['atm_id']); ?></div>
            </div>
            <div class="field">
                <div class="label">Branch Name</div>
                <div class="value"><?php echo h($data['branch_name']); ?></div>
            </div>
            <div class="field">
                <div class="label">Booth Name</div>
                <div class="value"><?php echo h($data['booth_name']); ?></div>
            </div>

            <div class="field">
                <div class="label">Zone Name</div>
                <div class="value"><?php echo h($data['zone_name']); ?></div>
            </div>
            <div class="field">
                <div class="label">Group No</div>
                <div class="value"><?php echo h($data['group_no']); ?></div>
            </div>
            <div class="field">
                <div class="label">Service Type</div>
                <div class="value"><?php echo h($data['service_type']); ?></div>
            
            <div class="field">
                <div class="label">New Booth</div>
                <div class="value"><?php echo ((int)$data['is_new_booth'] === 1) ? 'Yes' : 'No'; ?></div>
            </div>
        </div>

        <div style="margin-top:15px;">
            <div class="field">
                <div class="label">Location Remarks</div>
                <div class="value"><?php echo nl2br(h($data['location_remarks'] ?? '')); ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <h3>Requested Items</h3>
        <table>
            <thead>
            <tr>
                <th>SL</th>
                <th>Item Name</th>
                <th>Category</th>
                <th>Qty</th>
                <th>Remarks</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="5" style="text-align:center;">No item found.</td>
                </tr>
            <?php else: ?>
                <?php $sl = 1; foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo $sl++; ?></td>
                        <td><?php echo h($item['item_name']); ?></td>
                        <td><?php echo h($item['item_category']); ?></td>
                        <td><?php echo h($item['qty']); ?></td>
                        <td><?php echo h($item['remarks']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3>Tender Summary</h3>
        <table>
            <thead>
            <tr>
                <th>SL</th>
                <th>Vendor</th>
                <th>Bid Date</th>
                <th>Total Bid Amount</th>
                <th>Technical Status</th>
                <th>Financial Status</th>
                <th>Remarks</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($tenderSummary)): ?>
                <tr>
                    <td colspan="7" style="text-align:center;">No tender bid found.</td>
                </tr>
            <?php else: ?>
                <?php $sl = 1; foreach ($tenderSummary as $row): ?>
                    <tr>
                        <td><?php echo $sl++; ?></td>
                        <td><?php echo h($row['vendor_name']); ?></td>
                        <td><?php echo h($row['bid_date']); ?></td>
                        <td><?php echo h($row['total_bid_amount']); ?></td>
                        <td><?php echo h($row['technical_status']); ?></td>
                        <td><?php echo h($row['financial_status']); ?></td>
                        <td><?php echo h($row['remarks']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3>Work Order Summary</h3>
        <?php if (!$workOrder): ?>
            <p>No work order found.</p>
        <?php else: ?>
            <div class="grid">
                <div class="field">
                    <div class="label">Vendor Name</div>
                    <div class="value"><?php echo h($workOrder['vendor_name'] ?? ''); ?></div>
                </div>
                <div class="field">
                    <div class="label">Work Order No</div>
                    <div class="value"><?php echo h($workOrder['work_order_no'] ?? ''); ?></div>
                </div>
                <div class="field">
                    <div class="label">Work Order Date</div>
                    <div class="value"><?php echo h($workOrder['work_order_date'] ?? ''); ?></div>
                </div>
                <div class="field">
                    <div class="label">Delivery Deadline</div>
                    <div class="value"><?php echo h($workOrder['delivery_deadline'] ?? ''); ?></div>
                </div>
                <div class="field">
                    <div class="label">Work Order Amount</div>
                    <div class="value"><?php echo h($workOrder['work_order_amount'] ?? ''); ?></div>
                </div>
            </div>

            <div style="margin-top:15px;">
                <div class="field">
                    <div class="label">Remarks</div>
                    <div class="value"><?php echo nl2br(h($workOrder['remarks'] ?? '')); ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>QC Summary</h3>
        <table>
            <thead>
            <tr>
                <th>SL</th>
                <th>QC Date</th>
                <th>QC Status</th>
                <th>Remarks</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($qcSummary)): ?>
                <tr>
                    <td colspan="4" style="text-align:center;">No QC record found.</td>
                </tr>
            <?php else: ?>
                <?php $sl = 1; foreach ($qcSummary as $row): ?>
                    <tr>
                        <td><?php echo $sl++; ?></td>
                        <td><?php echo h($row['qc_date']); ?></td>
                        <td><?php echo h($row['qc_status']); ?></td>
                        <td><?php echo h($row['remarks']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>