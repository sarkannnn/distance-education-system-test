<?php
$content = file_get_contents('d:/laragon/www/distant-tehsil-test/student/live-view_livekit.php');
if (preg_match('/<script>(.*?)<\/script>/s', $content, $matches)) {
    $js = $matches[1];
    $balance = 0;
    $lines = explode("\n", $js);
    foreach ($lines as $i => $line) {
        $open = substr_count($line, '{');
        $close = substr_count($line, '}');
        $balance += $open;
        $balance -= $close;
        if ($balance < 0) {
            echo "Negative balance at line " . ($i + 1045) . ": $balance (Open: $open, Close: $close)\n";
            echo "Line: " . trim($line) . "\n";
            $balance = 0;
        }
    }
    echo "Final balance: $balance\n";
}
