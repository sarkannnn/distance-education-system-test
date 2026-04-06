<?php
require_once 'student/config/database.php';
$db = Database::getInstance();
$res = $db->query("DESCRIBE live_classes");
while($row = $res->fetch()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
