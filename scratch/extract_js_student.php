<?php
$content = file_get_contents('d:/laragon/www/distant-tehsil-test/student/live-view_livekit.php');
if (preg_match('/<script>(.*?)<\/script>/s', $content, $matches)) {
    // Replace PHP tags with dummy JS values to make it valid JS for checking
    $js = $matches[1];
    $js = preg_replace('/<\?php.*?\?>/s', '"dummy"', $js);
    file_put_contents('d:/laragon/www/distant-tehsil-test/scratch/test_js_syntax.js', $js);
    echo "Extracted JS to scratch/test_js_syntax.js\n";
} else {
    echo "Script tag not found\n";
}
