<?php
date_default_timezone_set('Asia/Dhaka');

include 'auth_check.php';
include 'db.php';
include 'includes/functions.php';

// চাইলে permission check রাখতে পারেন
// Auth::requirePermission('manage_users');

$dbName = $conn->query("SELECT DATABASE() AS db")->fetch_assoc()['db'] ?? '';
if ($dbName === '') {
    die('Database not selected.');
}

$filename = $dbName . '_backup_' . date('Y-m-d_H-i-s') . '.sql';

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "-- Database Backup\n";
echo "-- Database: `$dbName`\n";
echo "-- Backup Time: " . date('Y-m-d H:i:s') . "\n\n";
echo "SET FOREIGN_KEY_CHECKS=0;\n";
echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
echo "SET AUTOCOMMIT = 0;\n";
echo "START TRANSACTION;\n";
echo "SET time_zone = '+06:00';\n\n";

$tables = [];
$resTables = $conn->query("SHOW TABLES");
while ($row = $resTables->fetch_array()) {
    $tables[] = $row[0];
}

foreach ($tables as $table) {

    echo "\n-- ----------------------------\n";
    echo "-- Table structure for `$table`\n";
    echo "-- ----------------------------\n\n";

    $resCreate = $conn->query("SHOW CREATE TABLE `$table`");
    $createRow = $resCreate->fetch_assoc();

    $createSql = $createRow['Create Table'] ?? '';
    if ($createSql !== '') {
        echo "DROP TABLE IF EXISTS `$table`;\n";
        echo $createSql . ";\n\n";
    }

    echo "-- ----------------------------\n";
    echo "-- Records of `$table`\n";
    echo "-- ----------------------------\n\n";

    $resData = $conn->query("SELECT * FROM `$table`");
    if ($resData && $resData->num_rows > 0) {
        while ($dataRow = $resData->fetch_assoc()) {
            $columns = array_map(function ($col) {
                return "`" . str_replace("`", "``", $col) . "`";
            }, array_keys($dataRow));

            $values = array_map(function ($val) use ($conn) {
                if ($val === null) {
                    return "NULL";
                }
                return "'" . $conn->real_escape_string((string)$val) . "'";
            }, array_values($dataRow));

            echo "INSERT INTO `$table` (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ");\n";
        }
        echo "\n";
    }
}

echo "SET FOREIGN_KEY_CHECKS=1;\n";
echo "COMMIT;\n";
exit;
?>