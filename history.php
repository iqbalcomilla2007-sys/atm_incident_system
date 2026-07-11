<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('view_dashboard');

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* ===============================
   FILTER
================================ */
$search   = trim($_GET['search'] ?? '');
$zone     = trim($_GET['zone'] ?? '');
$branch   = trim($_GET['branch'] ?? '');
$vendor   = trim($_GET['vendor'] ?? '');
$fromDate = trim($_GET['from_date'] ?? '');
$toDate   = trim($_GET['to_date'] ?? '');
$showResults = $search !== '' || $zone !== '' || $branch !== '' || $vendor !== '' || $fromDate !== '' || $toDate !== '';

$branchList = [];
$zoneList = [];
$vendorList = [];

$branchRes = $conn->query("SELECT DISTINCT branch_name FROM atm_master WHERE branch_name IS NOT NULL AND branch_name <> '' ORDER BY branch_name ASC");
if ($branchRes) {
    while ($r = $branchRes->fetch_assoc()) {
        $branchList[] = $r['branch_name'];
    }
}

$zoneRes = $conn->query("SELECT DISTINCT zone_name FROM atm_master WHERE zone_name IS NOT NULL AND zone_name <> '' ORDER BY zone_name ASC");
if ($zoneRes) {
    while ($r = $zoneRes->fetch_assoc()) {
        $zoneList[] = $r['zone_name'];
    }
}

$vendorRes = $conn->query("SELECT DISTINCT responsible_vendor_name FROM atm_update WHERE responsible_vendor_name IS NOT NULL AND responsible_vendor_name <> '' ORDER BY responsible_vendor_name ASC");
if ($vendorRes) {
    while ($r = $vendorRes->fetch_assoc()) {
        $vendorList[] = $r['responsible_vendor_name'];
    }
}

/* ===============================
   AJAX REMARKS
================================ */
if (isset($_GET['action']) && $_GET['action'] === 'remarks_history' && isset($_GET['incident_id'])) {
    header('Content-Type: application/json; charset=utf-8');

    $incident_id = (int) $_GET['incident_id'];
    $data = [];

    $incidentObj = new Incident();
    $res = $incidentObj->getRemarks($incident_id);
    
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $data[] = [
                'remark' => $r['remark'],
                'created_at' => $r['created_at'],
                'remark_by' => $r['full_name'] ?: ($r['username'] ?: '-')
            ];
        }
    }

    echo json_encode($data);
    exit;
}

/* ===============================
   MAIN QUERY
================================ */
$where = "WHERE a.incident_status = 'Closed'";
$params = [];
$types = "";

if ($search !== '') {
    $where .= " AND (
        a.atm_id LIKE ? OR
        a.atm_name LIKE ? OR
        a.problem LIKE ? OR
        a.responsible_vendor_name LIKE ? OR
        m.zone_name LIKE ? OR
        m.branch_name LIKE ? OR
        lr.remark LIKE ? OR
        mu.username LIKE ? OR
        mu.full_name LIKE ?
    )";

    $like = "%$search%";

    for ($i = 0; $i < 9; $i++) {
        $params[] = $like;
        $types .= "s";
    }
}

if ($zone !== '') {
    $where .= " AND m.zone_name = ?";
    $params[] = $zone;
    $types .= "s";
}

if ($branch !== '') {
    $where .= " AND m.branch_name = ?";
    $params[] = $branch;
    $types .= "s";
}

if ($vendor !== '') {
    $where .= " AND a.responsible_vendor_name = ?";
    $params[] = $vendor;
    $types .= "s";
}

if ($fromDate !== '') {
    $where .= " AND a.updated_at >= ?";
    $params[] = $fromDate . ' 00:00:00';
    $types .= "s";
}

if ($toDate !== '') {
    $where .= " AND a.updated_at <= ?";
    $params[] = $toDate . ' 23:59:59';
    $types .= "s";
}

$result = null;

if ($showResults) {
    $sql = "
SELECT 
    a.incident_id,
    a.atm_id,
    a.atm_name,
    a.problem,
    a.down_time,
    a.created_at,
    a.updated_at,
    a.responsible_vendor_name,
    m.zone_name,
    m.branch_name,
    COALESCE(mu.username, mu.full_name, '-') AS modified_by_name,
    lr.remark AS latest_remark
FROM atm_update a
LEFT JOIN atm_master m ON TRIM(a.atm_id) = TRIM(m.atm_id)
LEFT JOIN users mu ON a.last_modified_by = mu.id
LEFT JOIN (
    SELECT r1.incident_id, r1.remark
    FROM incident_remarks r1
    INNER JOIN (
        SELECT incident_id, MAX(id) AS max_id
        FROM incident_remarks
        GROUP BY incident_id
    ) r2
    ON r1.incident_id = r2.incident_id
   AND r1.id = r2.max_id
) lr ON a.incident_id = lr.incident_id
$where
ORDER BY a.updated_at DESC
";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Closed Incident History</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<style>
body {
    font-family: Inter, Arial, sans-serif;
    background:#eef2ff;
    padding:24px;
    color:#111827;
}
.page-container {
    max-width:1400px;
    margin:0 auto;
}
.card {
    background:#ffffff;
    border:1px solid #e5e7eb;
    border-radius:18px;
    box-shadow:0 18px 40px rgba(15,23,42,0.08);
    padding:24px;
}
.page-title {
    margin:0 0 4px;
    font-size:28px;
    font-weight:700;
    color:#0f172a;
}
.page-subtitle {
    margin:0 0 24px;
    color:#475569;
    font-size:14px;
}
.search-box {
    margin-bottom:18px;
}
.search-panel {
    display:grid;
    gap:14px;
    grid-template-columns: repeat(12, minmax(0, 1fr));
}
.search-panel > * {
    min-width:0;
}
.search-panel .full { grid-column: span 12; }
.search-panel .half { grid-column: span 6; }
.search-panel .third { grid-column: span 4; }
.search-panel .quarter { grid-column: span 3; }
.search-panel input,
.search-panel select {
    width:100%;
    border:1px solid #d1d5db;
    border-radius:12px;
    padding:12px 14px;
    font-size:14px;
    color:#111827;
    background:#ffffff;
    outline:none;
    transition:border-color .2s ease, box-shadow .2s ease;
}
.search-panel input:focus,
.search-panel select:focus {
    border-color:#3b82f6;
    box-shadow:0 0 0 4px rgba(59,130,246,0.15);
}
.button-row {
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    align-items:center;
}
.button-row button,
.button-row a {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:44px;
    line-height:1;
    border-radius:12px;
    font-weight:600;
    text-decoration:none;
    cursor: pointer;
}
button.btn-primary {
    background:#2563eb;
    color:#ffffff;
    border:1px solid transparent;
    padding:0 18px;
}
button.btn-primary:hover {
    background:#1d4ed8;
}
.btn-export-excel { background: #10b981; color: #fff; padding:0 18px; border:none; }
.btn-export-excel:hover { background: #059669; }
.btn-export-pdf { background: #ef4444; color: #fff; padding:0 18px; border:none; }
.btn-export-pdf:hover { background: #dc2626; }
.btn-print { background: #64748b; color: #fff; padding:0 18px; border:none; }
.btn-print:hover { background: #475569; }

.btn-reset {
    color:#1f2937;
    background:#f8fafc;
    border:1px solid #cbd5e1;
    padding:0 18px;
}
.btn-penalty {
    background:#dc2626;
}
.btn-remarks {
    background:#2563eb;
}
.btn-penalty,
.btn-remarks {
    padding:8px 14px;
    border-radius:10px;
    color:#ffffff;
}
.button-row .filter-note {
    color:#64748b;
    font-size:13px;
}
.table-wrapper {
    overflow-x:auto;
    border-radius:18px;
    border:1px solid #e5e7eb;
    background:#ffffff;
}
table {
    width:100%;
    min-width:1200px;
    border-collapse:collapse;
}
th, td {
    border:1px solid #e2e8f0;
    padding:12px 14px;
    font-size:13px;
    color:#334155;
    vertical-align:middle;
}
th {
    background:#f8fafc;
    color:#0f172a;
    font-weight:700;
    text-align:left;
    position:sticky;
    top:0;
    z-index:2;
}
tbody tr:hover {
    background:#f8fafc;
}
.no-results {
    text-align:center;
    padding:24px;
    color:#64748b;
}

/* Print CSS */
@media print {
    body { background: #fff; padding: 0; }
    .no-print, nav, .search-box, .btn-reset, .page-subtitle { display: none !important; }
    .page-container, .card, .table-wrapper { box-shadow: none; border: none; margin: 0; padding: 0; max-width: 100%; }
    table { min-width: auto; width: 100%; font-size: 11px; }
    th, td { padding: 8px; }
}
</style>
</head>
<body>
<div class="no-print">
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>
</div>

<div class="page-container">
    <div class="card">
        <div class="button-row no-print" style="justify-content:space-between; margin-bottom:22px;">
            <div>
                <h1 class="page-title" style="margin-bottom:4px;">Closed Incident History</h1>
                <p class="page-subtitle">Search closed incidents by branch, zone, vendor, keyword, or date range.</p>
            </div>
            <a href="dashboard.php" class="btn-reset" style="padding:0 18px; height:44px; display:inline-flex; align-items:center;">Dashboard</a>
        </div>

        <div class="search-box no-print">
            <form method="get" class="search-panel">
                <div class="full">
                    <input type="text" name="search" placeholder="Search ATM / Problem / Vendor / Remark / Username" value="<?=h($search)?>">
                </div>

                <div class="quarter">
                    <select name="zone">
                        <option value="">All Zones</option>
                        <?php foreach ($zoneList as $z): ?>
                            <option value="<?=h($z)?>" <?= $zone === $z ? 'selected' : '' ?>><?=h($z)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="quarter">
                    <select name="branch">
                        <option value="">All Branches</option>
                        <?php foreach ($branchList as $b): ?>
                            <option value="<?=h($b)?>" <?= $branch === $b ? 'selected' : '' ?>><?=h($b)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="quarter">
                    <select name="vendor">
                        <option value="">All Vendors</option>
                        <?php foreach ($vendorList as $v): ?>
                            <option value="<?=h($v)?>" <?= $vendor === $v ? 'selected' : '' ?>><?=h($v)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="quarter">
                    <input type="date" name="from_date" value="<?=h($fromDate)?>" placeholder="From date">
                </div>

                <div class="quarter">
                    <input type="date" name="to_date" value="<?=h($toDate)?>" placeholder="To date">
                </div>

                <div class="full button-row" style="margin-top: 10px;">
                    <button type="submit" class="btn-primary">Apply Filters</button>
                    <a class="btn-reset" href="history.php">Reset</a>
                    
                    <button type="button" class="btn-export-excel" onclick="exportExcel()">Export Excel</button>
                    <button type="button" class="btn-export-pdf" onclick="exportPDF()">Export PDF</button>
                    <button type="button" class="btn-print" onclick="window.print()">Print</button>
                </div>
            </form>
            <?php if (!$showResults): ?>
                <p class="filter-note" style="margin-top:15px;">Use the filters above to view closed incidents. The page is optimized for focused search results.</p>
            <?php endif; ?>
        </div>

        <div class="table-wrapper">
            <table id="historyTable">
                <thead>
<tr>
<th>SL</th>
<th>Incident ID</th>
<th>ATM ID</th>
<th>ATM Name</th>
<th>Zone</th>
<th>Branch</th>
<th>Problem</th>
<th>Down Time</th>
<th>Vendor</th>
<th>Closed Time</th>
<th>Modified By</th>
<th>Remark</th>
<th class="no-print">Remarks</th>
<th class="no-print">Penalty</th>
</tr>
</thead>

<tbody>
<?php if ($showResults && $result && $result->num_rows > 0): ?>
<?php $sl=1; while($row=$result->fetch_assoc()): ?>
<tr>
<td><?=$sl++?></td>
<td><?=h($row['incident_id'])?></td>
<td><?=h($row['atm_id'])?></td>
<td><?=h($row['atm_name'])?></td>
<td><?=h($row['zone_name'])?></td>
<td><?=h($row['branch_name'] ?: '-')?></td>
<td><?=h($row['problem'])?></td>
<td><?=h($row['down_time'])?></td>
<td><?=h($row['responsible_vendor_name'] ?: '-')?></td>
<td><?=h($row['updated_at'])?></td>
<td><?=h($row['modified_by_name'] ?: '-')?></td>
<td><?=h($row['latest_remark'] ?: '-')?></td>

<td class="no-print">
<button class="btn btn-remarks" onclick="openRemarks(<?= (int)$row['incident_id'] ?>)">
View
</button>
</td>

<td class="no-print">
<a href="penalty.php?incident_id=<?= (int)$row['incident_id'] ?>" class="btn btn-penalty">
Penalty
</a>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="14" style="text-align: center;"><?= $showResults ? 'No data found' : 'Use search, branch, zone, vendor or date filters to view closed incidents.' ?></td></tr>
<?php endif; ?>
</tbody>
</table>
        </div>
    </div>
</div>

<div id="remarksBox" class="no-print" style="display:none; position:fixed; top:20%; left:30%; width:450px; background:#fff; padding:20px; border:1px solid #ccc; box-shadow:0 2px 12px rgba(0,0,0,.2); z-index:999;">
    <h3>Remarks History</h3>
    <div id="remarksContent"></div>
    <br>
    <button onclick="closeRemarks()" class="btn-reset" style="padding: 10px; border-radius: 8px;">Close</button>
</div>

<script>
/* --- Existing Remarks AJAX --- */
function openRemarks(id){
    document.getElementById('remarksBox').style.display='block';

    fetch('history.php?action=remarks_history&incident_id=' + encodeURIComponent(id))
    .then(res => res.json())
    .then(data => {
        let html = '';

        if (!data || data.length === 0) {
            html = 'No remarks found.';
        } else {
            data.forEach((r, i) => {
                html += '<div style="margin-bottom:8px;">';
                html += '<strong>' + (i + 1) + '.</strong> ';
                html += escapeHtml(r.remark || '');
                html += '<br><small>' + escapeHtml(r.created_at || '') + ' | By: ' + escapeHtml(r.remark_by || '-') + '</small>';
                html += '</div>';
            });
        }

        document.getElementById('remarksContent').innerHTML = html;
    });
}

function closeRemarks(){
    document.getElementById('remarksBox').style.display='none';
}

function escapeHtml(text) {
    return String(text)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

/* --- Export Excel (CSV format) --- */
function exportExcel() {
    let table = document.getElementById("historyTable");
    let rows = table.querySelectorAll("tr");
    let csv = [];
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        
        // Skip 'Remarks' and 'Penalty' action columns at the end (last 2 columns) if there's actual data
        let colCount = cols.length;
        if (colCount > 2 && !rows[i].querySelector('td[colspan]')) {
            colCount -= 2;
        } else if (rows[i].querySelector('td[colspan]')) {
            colCount = 1; // "No data found" row
        }

        for (let j = 0; j < colCount; j++) {
            let data = cols[j].innerText.replace(/"/g, '""'); // escape double quotes
            row.push('"' + data + '"');
        }
        csv.push(row.join(","));
    }
    
    let csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
    let downloadLink = document.createElement("a");
    downloadLink.download = "Closed_Incident_History.csv";
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

/* --- Export PDF --- */
function exportPDF() {
    const { jsPDF } = window.jspdf;
    // Create landscape A4 document
    const doc = new jsPDF('l', 'pt', 'a4'); 
    
    // Clone table to remove action columns before passing to autoTable
    let table = document.getElementById("historyTable").cloneNode(true);
    let rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        // Remove the last two action cells if it's a data/header row
        if(row.children.length > 2 && !row.querySelector('[colspan]')) {
            row.deleteCell(-1); // Delete Penalty
            row.deleteCell(-1); // Delete Remarks View
        }
    });

    doc.text("Closed Incident History", 40, 30);
    
    doc.autoTable({
        html: table,
        startY: 40,
        theme: 'grid',
        styles: { fontSize: 8, cellPadding: 3 },
        headStyles: { fillColor: [37, 99, 235] }
    });
    
    doc.save('Closed_Incident_History.pdf');
}
</script>

</body>
</html>