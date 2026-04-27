<?php
require_once 'teacher/config/database.php';
$db = Database::getInstance();
$schema = $db->fetchAll("DESCRIBE webinars");
print_r($schema);
