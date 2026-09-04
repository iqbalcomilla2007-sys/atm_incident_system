<?php
// Load bootstrap/init.php to register autoloader
require_once dirname(__DIR__) . '/init.php';

function hasPermission($conn, $permissionName) {
    return Auth::hasPermission($permissionName);
}

function requirePermission($conn, $permissionName) {
    Auth::requirePermission($permissionName);
}

function isAdminRole() {
    return Auth::isAdmin();
}

function isSuperAdminRole() {
    return Auth::isSuperAdmin();
}

function requireSuperAdmin() {
    Auth::requireSuperAdmin();
}

if (!function_exists('getRoleNameById')) {
    function getRoleNameById($conn, $id) {
        $userObj = new User();
        return $userObj->getRoleNameById($id);
    }
}

function buildZoneRestrictionClause($zoneField = 'm.zone_name') {
    $result = [
        'sql' => " AND 1=1 ",
        'params' => [],
        'types' => ""
    ];

    if (!Auth::isLoggedIn()) {
        $result['sql'] = " AND 1=0 ";
        return $result;
    }
    
    if (Auth::isSuperAdmin()) {
        return $result;
    }

    Auth::startSession();
    $assigned_zone = $_SESSION['assigned_zone'] ?? '';
    
    if ($assigned_zone === '') {
        return $result;
    }
    
    if ($zoneField === 'm') {
        $zoneField = 'm.zone_name';
    }
    
    $result['sql'] = " AND $zoneField = ? ";
    $result['params'] = [$assigned_zone];
    $result['types'] = "s";
    
    return $result;
}
?>
