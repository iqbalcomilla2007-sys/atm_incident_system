<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

Auth::requirePermission('view_dashboard');

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$problemOptions = $conn->query("SELECT problem_name FROM problem_master ORDER BY problem_name ASC");
if (!$problemOptions) {
    die("Problem list query failed: " . $conn->error);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ATM Incident Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .loading-box {
            padding: 20px;
            text-align: center;
            color: #666;
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container">

    <div class="hero-header">
        <div>
            <h1>ATM Incident Dashboard</h1>
            <p>Fast AJAX dashboard</p>
        </div>

        <div class="hero-actions">
            <span style="color:#fff; font-weight:bold; align-self:center;">
                Welcome, <?php echo h($_SESSION['full_name'] ?? 'User'); ?>
                <?php if (!empty($_SESSION['role_name'])) { ?>
                    (<?php echo h($_SESSION['role_name']); ?>)
                <?php } ?>
                <?php if (!empty($_SESSION['assigned_zone'])) { ?>
                    - <?php echo h($_SESSION['assigned_zone']); ?>
                <?php } ?>
            </span>

            <?php if (Auth::hasPermission('view_history')) { ?>

            <?php } ?>

            <?php if (Auth::hasPermission('manage_problem_master')) { ?>

            <?php } ?>

            <?php if (Auth::hasPermission('manage_atm_master')) { ?>

            <?php } ?>

            <?php if (Auth::hasPermission('manage_users')) { ?>

            <?php } ?>

            <?php if (Auth::isSuperAdmin()) { ?>

            <?php } ?>

            <?php if (Auth::hasPermission('manage_penalty')) { ?>

            <?php } ?>
</div>
    </div>

    <?php if (isset($_GET['saved']) && $_GET['saved'] == '1') { ?>
        <div class="form-card">
            <strong style="color:green;">Incident saved successfully.</strong>
        </div>
    <?php } ?>

    <?php if (isset($_GET['updated']) && $_GET['updated'] == '1') { ?>
        <div class="form-card">
            <strong style="color:green;">Incident updated successfully.</strong>
        </div>
    <?php } ?>

    <?php if (isset($_GET['closed']) && $_GET['closed'] == '1') { ?>
        <div class="form-card">
            <strong style="color:green;">Incident closed successfully.</strong>
        </div>
    <?php } ?>

    <div id="summary_cards">
        <div class="loading-box">Loading summary...</div>
    </div>

    <div class="form-card">
        <form id="filterForm" class="modern-filter" onsubmit="return false;">
            <input type="text" id="search" name="search" placeholder="Search ATM ID / Booth / Problem / Vendor / User / Zone / Remark">

            <select id="group_no" name="group_no">
                <option value="">All Groups</option>
                <option value="1">Group 1</option>
                <option value="2">Group 2</option>
                <option value="3">Group 3</option>
                <option value="4">Group 4</option>
                <option value="5">Group 5</option>
                <option value="6">Group 6</option>
            </select>

            <select id="problem" name="problem">
                <option value="">All Problems</option>
                <?php while ($p = $problemOptions->fetch_assoc()) { ?>
                    <option value="<?php echo h($p['problem_name']); ?>">
                        <?php echo h($p['problem_name']); ?>
                    </option>
                <?php } ?>
            </select>

            <button type="button" class="btn" onclick="loadDashboardData()">Search</button>
            <button type="button" class="btn btn-secondary" onclick="resetFilters()">Reset</button>
        </form>

        <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
            <?php if (Auth::hasPermission('add_incident')) { ?>
                <a class="btn btn-light" href="index.php">+ Add Incident</a>
            <?php } ?>

            <a class="btn btn-secondary" href="#" onclick="printOpenReport(); return false;">🖨 Print</a>
            <a class="btn btn-secondary" href="#" onclick="printProblemSummary(); return false;">Problem Summary</a>
        </div>
    </div>

    <div id="dashboard_table_area">
        <div class="loading-box">Loading incidents...</div>
    </div>

</div>

<script>
function getFilters() {
    return {
        search: document.getElementById('search').value,
        group_no: document.getElementById('group_no').value,
        problem: document.getElementById('problem').value
    };
}

function buildQuery(params) {
    return new URLSearchParams(params).toString();
}

function loadDashboardData() {
    const filters = getFilters();
    const query = buildQuery(filters);

    document.getElementById('dashboard_table_area').innerHTML =
        '<div class="loading-box">Loading incidents...</div>';

    fetch('dashboard_ajax_data.php?' + query)
        .then(res => res.text())
        .then(html => {
            document.getElementById('dashboard_table_area').innerHTML = html;
        })
        .catch(() => {
            document.getElementById('dashboard_table_area').innerHTML =
                '<div class="form-card"><strong style="color:red;">Failed to load dashboard data.</strong></div>';
        });

    loadSummaryData();
}

function loadSummaryData() {
    const filters = getFilters();
    const query = buildQuery(filters);

    fetch('dashboard_summary_data.php?' + query)
        .then(res => res.text())
        .then(html => {
            document.getElementById('summary_cards').innerHTML = html;
        })
        .catch(() => {
            document.getElementById('summary_cards').innerHTML =
                '<div class="form-card"><strong style="color:red;">Failed to load summary data.</strong></div>';
        });
}

function resetFilters() {
    document.getElementById('search').value = '';
    document.getElementById('group_no').value = '';
    document.getElementById('problem').value = '';
    loadDashboardData();
}

function printOpenReport() {
    const filters = getFilters();
    const query = buildQuery(filters);
    window.open('print_open_report.php?' + query, '_blank');
}

function printProblemSummary() {
    const filters = getFilters();
    const query = buildQuery(filters);
    window.open('print_problem_summary.php?' + query, '_blank');
}

document.getElementById('search').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        loadDashboardData();
    }
});

/* initial load */
loadDashboardData();

/* auto refresh every 30 sec */
setInterval(loadDashboardData, 30000);
</script>

</body>
</html>