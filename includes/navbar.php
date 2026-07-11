<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
/* Global Navbar Styles */
.global-nav {
    background-color: #1e293b;
    color: #fff;
    padding: 0 15px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    box-shadow: 0 4px 10px -1px rgba(0, 0, 0, 0.2);
    position: relative;
    z-index: 1000;
}
.global-nav ul {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
}
.global-nav li {
    position: relative;
}
.global-nav > ul > li > a {
    display: block;
    padding: 15px 20px;
    color: #cbd5e1;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.2s, color 0.2s;
}
.global-nav > ul > li > a:hover, 
.global-nav > ul > li > a.active {
    background-color: #334155;
    color: #fff;
}
.global-nav .dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    background-color: #ffffff;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
    border-radius: 0 0 6px 6px;
    z-index: 1001;
    min-width: 220px;
}
.global-nav li:hover .dropdown-menu {
    display: block;
}
.global-nav .dropdown-menu li {
    width: 100%;
}
.global-nav .dropdown-menu a {
    display: block;
    color: #334155;
    padding: 10px 15px;
    border-bottom: 1px solid #f1f5f9;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: background 0.2s;
}
.global-nav .dropdown-menu a:hover {
    background-color: #f8fafc;
    color: #0f172a;
    padding-left: 20px;
}
.global-nav .dropdown-menu a:last-child {
    border-bottom: none;
}
.nav-right {
    margin-left: auto;
    display: flex;
    align-items: center;
}
.nav-right a {
    background: #dc3545;
    color: #fff;
    border-radius: 4px;
    margin: 8px 0;
    padding: 7px 15px;
    text-decoration: none;
    font-size: 13px;
    font-weight: bold;
    transition: background 0.2s;
}
.nav-right a:hover {
    background: #c82333;
}
@media print {
    .global-nav { display: none !important; }
}
@media (max-width: 768px) {
    .global-nav {
        flex-direction: column;
        align-items: stretch;
    }
    .global-nav ul {
        flex-direction: column;
    }
    .global-nav .dropdown-menu {
        position: static;
        box-shadow: none;
        border: none;
        border-radius: 0;
        border-left: 2px solid #e2e8f0;
        margin-left: 10px;
        background-color: #f8fafc;
        display: none;
    }
    .global-nav li:hover .dropdown-menu {
        display: block;
    }
    .nav-right {
        margin: 10px 0;
    }
}
</style>

<nav class="global-nav">
    <ul>
        <li>
            <a href="dashboard_ajax_v2.php" class="<?= in_array($current_page, ['dashboard_ajax_v2.php', 'dashboard_ajax.php']) ? 'active' : '' ?>">Dashboard</a>
        </li>
        
        <li>
            <a href="#" class="<?= in_array($current_page, ['index.php', 'history.php', 'print_open_report.php']) ? 'active' : '' ?>">Incident Mgmt &#9662;</a>
            <ul class="dropdown-menu">
                <li><a href="index.php">Add New Incident</a></li>
                <li><a href="history.php">Incident History Log</a></li>
                <li><a href="print_open_report.php">Print Open Incidents</a></li>
            </ul>
        </li>

        <li>
            <a href="#" class="<?= in_array($current_page, ['manage_atm_master.php', 'manage_zone_branch_map.php', 'manage_atm_security.php', 'manage_atm_contact.php', 'manage_combos.php', 'manage_group_details.php', 'vendor_master.php', 'atm_device_movement.php', 'atm_ip_details.php', 'cash_empty_position.php']) ? 'active' : '' ?>">ATM Master &#9662;</a>
            <ul class="dropdown-menu">
                <li><a href="manage_atm_master.php">ATM Master Database</a></li>
                <li><a href="manage_zone_branch_map.php">Branch Mapping (Zone)</a></li>
                <li><a href="manage_atm_security.php">Security Guards Info</a></li>
                <li><a href="manage_atm_contact.php">ATM Custodians Info</a></li>
                <li><a href="atm_ip_details.php">ATM IP Details</a></li>
                <li><a href="cash_empty_position.php">Cash Empty Position</a></li>
                <li><a href="atm_device_movement.php">Device Movement Log</a></li>
                <li><a href="manage_combos.php">Problems Master List</a></li>
                <li><a href="manage_group_details.php">Groups Master</a></li>
                <li><a href="vendor_master.php">Vendors Master</a></li>
            </ul>
        </li>

        <li>
            <a href="#" class="<?= in_array($current_page, ['penalty_summary_report.php', 'manage_vendor_penalty_rules.php', 'manage_vendor_amc_rates.php']) ? 'active' : '' ?>">Penalty Mgmt &#9662;</a>
            <ul class="dropdown-menu">
                <li><a href="penalty_summary_report.php">Penalty Summary Report</a></li>
                <li><a href="manage_vendor_penalty_rules.php">Vendor Penalty Rules</a></li>
                <li><a href="manage_vendor_amc_rates.php">Vendor AMC Rates</a></li>
            </ul>
        </li>

        <li>
            <a href="#" class="<?= in_array($current_page, ['cctv_dashboard.php', 'cctv_list.php', 'cctv_set_requisition_list.php', 'cctv_spare_requisition_list.php', 'cctv_item_master.php', 'cctv_location_master.php', 'cctv_vendor_master.php']) ? 'active' : '' ?>">CCTV System &#9662;</a>
            <ul class="dropdown-menu">
                <li><a href="cctv_dashboard.php">CCTV Analytics Dashboard</a></li>
                <li><a href="cctv_list.php">CCTV Master List</a></li>
                <li><a href="cctv_set_requisition_list.php">Set Requisitions</a></li>
                <li><a href="cctv_spare_requisition_list.php">Spare Requisitions</a></li>
                <li><a href="cctv_item_master.php">CCTV Item Master</a></li>
                <li><a href="cctv_location_master.php">Location Master</a></li>
                <li><a href="cctv_vendor_master.php">Vendor Master</a></li>
            </ul>
        </li>

        <li>
            <a href="#" class="<?= in_array($current_page, ['manage_users.php', 'manage_roles.php', 'manage_audit_logs.php']) ? 'active' : '' ?>">Settings &#9662;</a>
            <ul class="dropdown-menu">
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="manage_roles.php">Manage Roles & Permissions</a></li>
                <li><a href="manage_audit_logs.php">System Audit Logs</a></li>
            </ul>
        </li>
    </ul>
    
    <div class="nav-right">
        <a href="change_password.php" style="background: #0d6efd; margin-right: 5px;">Change Password</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>
