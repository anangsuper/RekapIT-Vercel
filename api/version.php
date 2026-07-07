<?php
header('Content-Type: text/plain');
$file = __DIR__ . '/telegram_webhook.php';
if (file_exists($file)) {
    echo "MD5: " . md5_file($file) . "\n";
    $lines = file($file);
    echo "Line 474 (1-indexed): " . ($lines[473] ?? 'Not found') . "\n";
    echo "Line 475: " . ($lines[474] ?? 'Not found') . "\n";
    echo "Line 476: " . ($lines[475] ?? 'Not found') . "\n";
} else {
    echo "File not found!";
}
