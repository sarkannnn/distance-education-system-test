<?php
require_once 'teacher/config/database.php';
$db = Database::getInstance();
$tables = $db->fetchAll("SHOW TABLES");
print_r($tables);
