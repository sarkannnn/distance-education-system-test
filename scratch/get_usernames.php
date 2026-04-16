<?php
require_once 'webinar/config/database.php';
try {
    $db = WebinarDatabase::getInstance();
    $users = $db->fetchAll('SELECT username FROM webinar_users');
    foreach($users as $u) {
        echo $u['username'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
