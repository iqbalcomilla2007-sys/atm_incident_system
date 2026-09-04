<?php
// Set default timezone
date_default_timezone_set('Asia/Dhaka');

// Include autoloader and register
require_once __DIR__ . '/classes/Autoloader.php';
Autoloader::register();

// Start session
Auth::startSession();

// Backwards compatibility with procedural files using $conn
$db = Database::getInstance();
$conn = $db->getConnection();
