<?php
$content = file_get_contents('d:/laragon/www/distant-tehsil-test/student/live-view_livekit.php');
$lines = explode("\n", $content);
$balance = 0;
$scriptTagActive = false;

foreach ($lines as $i => $line) {
    if (strpos($line, '<script>') !== false || strpos($line, '<script') !== false && strpos($line, 'src') === false) {
        $scriptTagActive = true;
    }
    if (strpos($line, '</script>') !== false) {
        $scriptTagActive = false;
        if ($balance != 0) {
            echo "Warning: Script block ending at line " . ($i + 1) . " has balance " . $balance . "\n";
            $balance = 0; // reset for next block
        }
    }
    
    if ($scriptTagActive) {
        // Very basic counting, ignoring strings/comments (this is just an approximation)
        $cleanLine = preg_replace('/\/\/.*/', '', $line);
        $cleanLine = preg_replace('/\'[^\']*\'/', '', $cleanLine);
        $cleanLine = preg_replace('/"[^"]*"/', '', $cleanLine);
        $cleanLine = preg_replace('/`[^`]*`/', '', $cleanLine);
        
        $opens = substr_count($cleanLine, '{');
        $closes = substr_count($cleanLine, '}');
        $balance += ($opens - $closes);
    }
}
echo "Final balance: " . $balance . "\n";
?>
