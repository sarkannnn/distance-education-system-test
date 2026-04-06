<?php
require_once 'student/config/database.php';
$db = Database::getInstance();
$tables = $db->fetchAll("SHOW TABLES");
foreach($tables as $tableRow) {
    $table = array_values($tableRow)[0];
    try {
        $count = $db->fetch("SELECT COUNT(*) as c FROM `$table`")['c'];
        echo "$table: $count\n";
    } catch(Exception $e) {}
}
