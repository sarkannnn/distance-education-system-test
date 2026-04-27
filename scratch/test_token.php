<?php
$_GET['room'] = '233';
session_name('DISTANT_T_SESSION_V4');
session_start();
$_SESSION['logged_in'] = true;
$_SESSION['user_role'] = 'instructor';
$_SESSION['user_id'] = '2';
$_SESSION['user_name'] = 'Test Muellim';
session_write_close();

// Call token API
ob_start();
include 'api/livekit_token.php';
$output = ob_get_clean();

echo "OUTPUT:\n$output\n";
