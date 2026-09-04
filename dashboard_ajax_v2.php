<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('view_dashboard');

// --- [নতুন] AJAX Remark Save Logic (incident_remarks টেবিলে ইনসার্ট হবে) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_quick_remark') {
    header('Content-Type: application/json');
    $incident_id = (int)$_POST['incident_id'];
    $remark_text = trim($_POST['remark_text'] ?? '');
    $user_id     = $_SESSION['user_id'] ?? null;

    if ($incident_id > 0 && $remark_text !== '') {
        $stmt = $conn->prepare("INSERT INTO incident_remarks (incident_id, remark, user_id, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("isi", $incident_id, $remark_text, $user_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Remark content is required.']);
    }
    exit;
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ফিল্টার অপশনসমূহ
$problemOptions = $conn->query("SELECT problem_name FROM problem_master ORDER BY problem_name ASC");
$groupOptions = $conn->query("SELECT DISTINCT group_no FROM atm_update WHERE group_no IS NOT NULL AND group_no <> '' ORDER BY group_no ASC");
$userOptions = $conn->query("SELECT username FROM users ORDER BY username ASC");
$vendorOptions = $conn->query("SELECT DISTINCT responsible_vendor_name FROM atm_update WHERE incident_status = 'Open' AND responsible_vendor_name IS NOT NULL AND responsible_vendor_name <> '' ORDER BY responsible_vendor_name ASC");

// টোটাল কাউন্ট
$totalSql = "SELECT COUNT(*) AS total_open FROM atm_update a LEFT JOIN atm_master m ON a.atm_id = m.atm_id WHERE a.incident_status = 'Open' ";
$params = []; $types = '';
$zoneRestrict = buildZoneRestrictionClause('m');
$totalSql .= $zoneRestrict['sql'];
if (!empty($zoneRestrict['params'])) {
    $params = array_merge($params, $zoneRestrict['params']);
    $types .= $zoneRestrict['types'];
}
$totalStmt = $conn->prepare($totalSql);
if (!empty($params)) { $totalStmt->bind_param($types, ...$params); }
$totalStmt->execute();
$totalOpen = (int)($totalStmt->get_result()->fetch_assoc()['total_open'] ?? 0);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ATM Incident Dashboard</title>
    <link rel="stylesheet" href="style.css?v=final26">
    <style>
        /* Remark Modal CSS */
        #remarkModal { display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter:blur(2px); }
        .modal-content { background:#fff; width:450px; margin:15vh auto; padding:25px; border-radius:15px; box-shadow:0 15px 35px rgba(0,0,0,0.3); border:1px solid #ddd; }
        .modal-content h3 { margin:0 0 15px; color:#1e3a8a; font-size:18px; border-bottom:2px solid #f0f0f0; padding-bottom:10px; }
        .modal-content textarea { width:100%; height:120px; padding:12px; border:1px solid #cbd5e1; border-radius:10px; margin:10px 0; box-sizing:border-box; outline:none; font-size:14px; transition: 0.3s; }
        .modal-content textarea:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .modal-footer { text-align:right; margin-top:10px; }
        .btn-save { background:#2563eb; color:#fff; padding:10px 20px; border:none; border-radius:8px; cursor:pointer; font-weight:bold; }
        .btn-cancel { background:#f1f5f9; color:#475569; padding:10px 20px; border:none; border-radius:8px; cursor:pointer; margin-right:8px; }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container">
    <div class="hero-header">
        <div><h1>ATM Incident Dashboard</h1><p>Live monitoring view</p></div>
 <div class="hero-actions">
    <!-- Password - Sky Blue -->

    <!-- History - Slate/Grey -->
    <?php if (Auth::hasPermission('view_history')) { ?>

    <?php } ?>

    <!-- ATM Master - Indigo/Deep Blue -->
    <?php if (Auth::hasPermission('manage_atm_master')) { ?>

    <?php } ?>

    <!-- Users - Teal/Sea Green -->
    <?php if (Auth::hasPermission('manage_users')) { ?>

    <?php } ?>

    <!-- Penalty - Rose/Pink Red -->
    <?php if (Auth::hasPermission('manage_penalty')) { ?>

    <?php } ?>

    <!-- CCTV - Vivid Green -->

    <!-- Logout - Black -->

    <span class="current-user-text" style="background:#f3f4f6; padding:8px 12px; border-radius:6px; color:#374151; font-weight:bold;">
        <?php echo h($_SESSION['username'] ?? 'User'); ?>
    </span>
</div>    </div>

    <?php if (isset($_GET['saved']) && $_GET['saved'] === '1'): ?>
        <div class="form-card" style="background:#ecfdf5; border:1px solid #bbf7d0; color:#166534; margin-bottom:18px;">
            <strong>Incident added successfully.</strong>
        </div>
    <?php endif; ?>

    <!-- Filters Toolbar -->
    <div class="form-card">
        <div class="toolbar-inline">
            <div class="toolbar-item toolbar-search"><input type="text" id="search" placeholder="Search ATM/Problem/Zone"></div>
            <div class="toolbar-item">
                <select id="group_no"><option value="">All Groups</option>
                <?php while ($g = $groupOptions->fetch_assoc()) { echo "<option value='".h($g['group_no'])."'>Group ".h($g['group_no'])."</option>"; } ?></select>
            </div>
            <div class="toolbar-item">
                <select id="problem"><option value="">All Problems</option>
                <?php while ($p = $problemOptions->fetch_assoc()) { echo "<option value='".h($p['problem_name'])."'>".h($p['problem_name'])."</option>"; } ?></select>
            </div>
            <div class="toolbar-item">
                <select id="username"><option value="">All Users</option>
                <?php while ($u = $userOptions->fetch_assoc()) { echo "<option value='".h($u['username'])."'>".h($u['username'])."</option>"; } ?></select>
            </div>
            <div class="toolbar-item">
                <select id="vendor"><option value="">All Vendors</option>
                <?php while ($v = $vendorOptions->fetch_assoc()) { echo "<option value='".h($v['responsible_vendor_name'])."'>".h($v['responsible_vendor_name'])."</option>"; } ?></select>
            </div>
            <div class="toolbar-item">
                <input type="date" id="from_date" title="From Date" style="padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px;">
            </div>
            <div class="toolbar-item">
                <input type="date" id="to_date" title="To Date" style="padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px;">
            </div>
            <div class="toolbar-item">
                <select id="limit"><option value="50">50</option><option value="100" selected>100</option><option value="200">200</option></select>
            </div>
            <button class="btn btn-search" type="button" onclick="submitSearch()">Search</button>
            <button class="btn btn-reset" type="button" onclick="resetFilters()">Reset</button>
        </div>

        <div class="dashboard-quick-actions">
            <?php if (Auth::hasPermission('add_incident')) { ?><a class="btn btn-primary" href="index.php">Add Incident</a><?php } ?>
            <a class="btn btn-print" href="#" onclick="printOpenReport(); return false;">Print</a>
            <a class="btn btn-summary" href="#" onclick="printProblemSummary(); return false;">Problem Summary</a>
            <a class="btn btn-summary" href="#" onclick="printVendorSummary(); return false;" style="background:#8b5cf6;">Vendor Summary</a>
            <span class="total-incidents-inline">Total Open: <?php echo $totalOpen; ?></span>
        </div>
    </div>

    <div id="dashboard_table_area"><div class="form-card">Loading data...</div></div>

    <div class="form-card page-box">
        <button class="btn btn-secondary" type="button" onclick="prevPage()">Prev</button>
        <strong id="page_info">Page 1</strong>
        <button class="btn btn-secondary" type="button" onclick="nextPage()">Next</button>
    </div>
</div>

<!-- Remark Modal -->
<div id="remarkModal">
    <div class="modal-content">
        <h3>Quick Remark Update</h3>
        <input type="hidden" id="modal_incident_id">
        <textarea id="modal_remark_text" placeholder="Write update message here..."></textarea>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeRemarkModal()">Cancel</button>
            <button class="btn-save" id="saveRemarkBtn">Save Remark</button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let currentPage = 1;

function getFilters() {
    return {
        search: document.getElementById('search').value,
        group_no: document.getElementById('group_no').value,
        problem: document.getElementById('problem').value,
        username: document.getElementById('username').value,
        vendor: document.getElementById('vendor').value,
        from_date: document.getElementById('from_date').value,
        to_date: document.getElementById('to_date').value,
        limit: document.getElementById('limit').value,
        page: currentPage
    };
}

function debounce(fn, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn.apply(this, args), wait);
    };
}

function loadData(initial = false) {
    const filters = getFilters();
    if (initial) filters.initial_load = '1';
    const params = new URLSearchParams(filters).toString();
    fetch("dashboard_ajax_v2_data.php?" + params)
        .then(res => res.text())
        .then(html => {
            document.getElementById("dashboard_table_area").innerHTML = html;
            document.getElementById("page_info").innerText = "Page " + currentPage;
            const newTotalEl = document.getElementById("new_total_open");
            if (newTotalEl) {
                const titleSpan = document.querySelector(".total-incidents-inline");
                if (titleSpan) titleSpan.innerText = "Total Open: " + newTotalEl.innerText;
            }
        });
}

// --- Remark Functions ---
$(document).on('click', '.btn-remark', function() {
    const id = $(this).data('id');
    document.getElementById('modal_incident_id').value = id;
    document.getElementById('modal_remark_text').value = '';
    $('#remarkModal').fadeIn(200);
    $('#modal_remark_text').focus();
});

function closeRemarkModal() { $('#remarkModal').fadeOut(200); }

$('#saveRemarkBtn').click(function() {
    const id = $('#modal_incident_id').val();
    const remark = $('#modal_remark_text').val();
    const btn = $(this);
    if(!remark.trim()){ alert("Remark cannot be empty"); return; }
    btn.prop('disabled', true).text('Saving...');
    $.ajax({
        url: 'dashboard_ajax_v2.php',
        method: 'POST',
        data: { action: 'save_quick_remark', incident_id: id, remark_text: remark },
        success: function(r) {
            if(r.success) { closeRemarkModal(); loadData(); } else { alert('Error: ' + r.message); }
        },
        complete: function() { btn.prop('disabled', false).text('Save Remark'); }
    });
});

function submitSearch() { currentPage = 1; loadData(); }
function resetFilters() {
    document.getElementById("search").value = '';
    document.getElementById("group_no").value = '';
    document.getElementById("problem").value = '';
    document.getElementById("username").value = '';
    document.getElementById("vendor").value = '';
    document.getElementById("from_date").value = '';
    document.getElementById("to_date").value = '';
    currentPage = 1; loadData();
}
function nextPage() { currentPage++; loadData(); }
function prevPage() { if (currentPage > 1) { currentPage--; loadData(); } }

function initFilterAutoLoad() {
    ['group_no', 'problem', 'username', 'vendor', 'from_date', 'to_date', 'limit'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', () => {
                currentPage = 1;
                loadData();
            });
        }
    });

    const searchInput = document.getElementById('search');
    if (searchInput) {
        const debouncedSearch = debounce(() => {
            currentPage = 1;
            loadData();
        }, 350);

        searchInput.addEventListener('input', debouncedSearch);
        searchInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                submitSearch();
            }
        });
    }
}

function printOpenReport() { const q = new URLSearchParams(getFilters()).toString(); window.open("print_open_report.php?" + q, "_blank"); }
function printProblemSummary() { const q = new URLSearchParams(getFilters()).toString(); window.open("print_problem_summary.php?" + q, "_blank"); }
function printVendorSummary() { const q = new URLSearchParams(getFilters()).toString(); window.open("print_vendor_summary.php?" + q, "_blank"); }

function closeIncident(id, atmId, btn) {
    if (!confirm("Close ATM: " + atmId + " ?")) return;
    fetch("close_ajax.php", { method: "POST", headers: {"Content-Type": "application/x-www-form-urlencoded"}, body: "id=" + encodeURIComponent(id) })
    .then(res => res.json()).then(data => { if (data.success) { alert("Closed"); loadData(); } else { alert(data.message); } });
}

initFilterAutoLoad();
loadData(true);
</script>
</body>
</html>