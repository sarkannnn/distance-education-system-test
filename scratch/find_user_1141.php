<?php
require_once 'student/config/database.php';
$db = Database::getInstance();

$id = '1141';
echo "Searching for $id...\n";

$res1 = $db->fetchAll("SELECT id, student_id, first_name, last_name, role FROM users WHERE id = :id OR student_id = :id OR student_id LIKE :id2", ['id' => $id, 'id2' => "%$id"]);
echo "Users Table Results:\n";
print_r($res1);

$res2 = $db->fetchAll("SELECT * FROM instructors WHERE name LIKE '%Sərkan%' OR email LIKE '%serxan%'");
echo "\nInstructors Table Results (Sərkan):\n";
print_r($res2);
?>
