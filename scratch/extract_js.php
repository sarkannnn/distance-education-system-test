<?php
$html = file_get_contents('student/live-view_livekit.php');
// Extract all script tags
preg_match_all('/<script[^>]*>(.*?)<\/script>/is', $html, $matches);
$js = "";
foreach($matches[1] as $match) {
    $js .= $match . "\n";
}
file_put_contents('scratch/test.js', $js);
echo "Extracted JS to test.js";
