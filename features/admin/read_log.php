<?php
$log = '/home/u632052358/.logs/error_log_club61_site';
if (file_exists($log)) {
    $lines = file($log);
    $last = array_slice($lines, -50);
    foreach ($last as $line) {
        echo htmlspecialchars($line) . '<br>';
    }
} else {
    echo 'Arquivo não encontrado: ' . $log;
}
