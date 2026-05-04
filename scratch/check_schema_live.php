<?php
require_once 'teacher/config/database.php';
$db = Database::getInstance();
$schema = $db->fetchAll("DESCRIBE live_classes");
print_r($schema);
