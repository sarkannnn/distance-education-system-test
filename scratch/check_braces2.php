<?php
$content = file_get_contents('d:/laragon/www/distant-tehsil-test/teacher/live-studio_livekit.php');
$lines = explode("\n", $content);
$balance = 0;
$scriptTagActive = false;
$lineBalances = [];

foreach ($lines as $i => $line) {
    if (strpos($line, '<script>') !== false || strpos($line, '<script') !== false && strpos($line, 'src') === false) {
        $scriptTagActive = true;
    }
    if (strpos($line, '</script>') !== false) {
        $scriptTagActive = false;
    }
    
    if ($scriptTagActive) {
        $cleanLine = preg_replace('/\/\/.*/', '', $line);
        $cleanLine = preg_replace('/\'[^\']*\'/', '', $cleanLine);
        $cleanLine = preg_replace('/"[^"]*"/', '', $cleanLine);
        $cleanLine = preg_replace('/`[^`]*`/', '', $cleanLine);
        
        $opens = substr_count($cleanLine, '{');
        $closes = substr_count($cleanLine, '}');
        $balance += ($opens - $closes);
        
        $lineBalances[$i+1] = $balance;
    }
}

$prev = 0;
foreach($lineBalances as $ln => $b) {
    if ($b != $prev) {
        if ($b == 0 && $prev > 0) {
            echo "Balance reached 0 at line $ln\n";
        }
        if ($b < 0) {
            echo "Negative balance at line $ln\n";
        }
        $prev = $b;
    }
}
echo "Final balance: $balance\n";
?>
