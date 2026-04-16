<?php
require_once 'webinar/config/database.php';
try {
    $db = WebinarDatabase::getInstance();
    $facs = $db->fetchAll('SELECT * FROM webinar_faculties');
    foreach($facs as $f) {
        echo $f['id'] . "|" . $f['name'] . "|" . $f['slug'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
