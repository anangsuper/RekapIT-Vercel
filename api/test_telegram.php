<?php
// Start session
session_start();

// Load notification helper
require_once __DIR__ . '/../helpers/notification.php';

$token = getenv('TELEGRAM_BOT_TOKEN') ?: ($_ENV['TELEGRAM_BOT_TOKEN'] ?? ($_SERVER['TELEGRAM_BOT_TOKEN'] ?? ''));
$chatId = getenv('TELEGRAM_CHAT_ID') ?: ($_ENV['TELEGRAM_CHAT_ID'] ?? ($_SERVER['TELEGRAM_CHAT_ID'] ?? ''));

echo "<h3>Telegram Diagnostics Tool</h3>";
echo "• Token: " . ($token ? htmlspecialchars(substr($token, 0, 12)) . "*******************" : "<span style='color:red;'>MISSING</span>") . "<br>";
echo "• Chat ID: " . ($chatId ? htmlspecialchars($chatId) : "<span style='color:red;'>MISSING</span>") . "<br><br>";

echo "<b>Detected Env Keys:</b><br>";
$envKeys = [];
foreach ($_SERVER as $k => $v) {
    if (strpos($k, 'TELEGRAM_') === 0) $envKeys[] = "$k (in \$_SERVER)";
}
foreach ($_ENV as $k => $v) {
    if (strpos($k, 'TELEGRAM_') === 0) $envKeys[] = "$k (in \$_ENV)";
}
if (getenv('TELEGRAM_BOT_TOKEN') !== false) $envKeys[] = "TELEGRAM_BOT_TOKEN (in getenv)";
if (getenv('TELEGRAM_CHAT_ID') !== false) $envKeys[] = "TELEGRAM_CHAT_ID (in getenv)";
echo (implode('<br>', array_unique($envKeys)) ?: "None detected") . "<br><br>";

if (empty($token) || empty($chatId)) {
    echo "<span style='color:red;'>Error: TELEGRAM_BOT_TOKEN or TELEGRAM_CHAT_ID is not configured in Vercel.</span>";
    exit();
}

$url = "https://api.telegram.org/bot" . $token . "/sendMessage";
$data = [
    'chat_id' => $chatId,
    'text' => "🔔 *TEST NOTIFIKASI TELEGRAM*\nKoneksi berhasil terhubung ke sistem RekapIT!",
    'parse_mode' => 'Markdown'
];

$options = [
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($data),
        'timeout' => 5
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ]
];

$context  = stream_context_create($options);
echo "Sending test request to Telegram API...<br>";
$result = @file_get_contents($url, false, $context);

if ($result === false) {
    $error = error_get_last();
    echo "<span style='color:red;'><b>Request Failed!</b></span><br>";
    echo "PHP Error Message: " . htmlspecialchars($error['message'] ?? 'Unknown Connection Error') . "<br><br>";
    echo "<b>Saran Penyelesaian:</b><br>";
    echo "1. Pastikan Bot Anda sudah dimasukkan ke dalam Grup Telegram.<br>";
    echo "2. Pastikan Chat ID sudah benar (diawali tanda minus untuk grup, contoh: -100xxxxxxxx).<br>";
} else {
    echo "<span style='color:green;'><b>Request Succeeded!</b></span><br>";
    echo "Telegram Server Response:<br>";
    echo "<pre>" . htmlspecialchars(json_encode(json_decode($result), JSON_PRETTY_PRINT)) . "</pre>";
}
