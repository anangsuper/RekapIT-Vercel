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

// Webhook Registration Handler
echo "<hr style='margin: 25px 0;'>";
echo "<h4>🔌 Integrasi Fitur Cari Aset Lewat Telegram Bot</h4>";
echo "Anda dapat mendaftarkan Webhook agar bot dapat langsung membalas perintah pencarian aset (seperti <code>/cari LAP-001</code>) di grup Telegram Anda.<br><br>";

$webhookUrl = "https://" . ($_SERVER['HTTP_HOST'] ?? '') . "/api/telegram_webhook.php";

if (isset($_GET['set_webhook'])) {
    $setUrl = "https://api.telegram.org/bot" . $token . "/setWebhook?url=" . urlencode($webhookUrl);
    
    // Use the same SSL options context
    $webContext = stream_context_create([
        'http' => ['timeout' => 5],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    
    $webhookRes = @file_get_contents($setUrl, false, $webContext);
    if ($webhookRes !== false) {
        $resJson = json_decode($webhookRes, true);
        if ($resJson && $resJson['ok']) {
            echo "<div style='background-color:#d4edda; color:#155724; padding:12px; border-radius:8px; border:1px solid #c3e6cb; margin-bottom:15px;'>";
            echo "<b>Berhasil!</b> Webhook Telegram aktif terhubung ke:<br><code>$webhookUrl</code>";
            echo "</div>";
        } else {
            echo "<div style='background-color:#f8d7da; color:#721c24; padding:12px; border-radius:8px; border:1px solid #f5c6cb; margin-bottom:15px;'>";
            echo "<b>Gagal Mendaftarkan:</b> " . htmlspecialchars($resJson['description'] ?? 'Respon error tidak diketahui.');
            echo "</div>";
        }
    } else {
        $error = error_get_last();
        echo "<div style='background-color:#f8d7da; color:#721c24; padding:12px; border-radius:8px; border:1px solid #f5c6cb; margin-bottom:15px;'>";
        echo "<b>Gagal:</b> Tidak dapat menghubungi API Telegram untuk mengatur webhook.<br>";
        echo "Detail Error: " . htmlspecialchars($error['message'] ?? 'Koneksi Timeout atau DNS gagal.');
        echo "</div>";
    }
}

if (isset($_GET['set_menu_button'])) {
    $webAppUrl = "https://" . ($_SERVER['HTTP_HOST'] ?? 'rekap-it-vercel-txjt.vercel.app') . "/api/telegram_add_asset.php";
    
    // For setChatMenuButton
    $menuUrl = "https://api.telegram.org/bot" . $token . "/setChatMenuButton";
    $menuData = [
        'menu_button' => json_encode([
            'type' => 'web_app',
            'text' => '➕ Tambah Aset',
            'web_app' => [
                'url' => $webAppUrl
            ]
        ])
    ];
    
    $menuContext = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($menuData),
            'timeout' => 5
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    
    $menuRes = @file_get_contents($menuUrl, false, $menuContext);
    if ($menuRes !== false) {
        $resJson = json_decode($menuRes, true);
        if ($resJson && $resJson['ok']) {
            echo "<div style='background-color:#d4edda; color:#155724; padding:12px; border-radius:8px; border:1px solid #c3e6cb; margin-bottom:15px;'>";
            echo "<b>Berhasil!</b> Tombol Menu WebApp telah diaktifkan ke:<br><code>$webAppUrl</code>";
            echo "</div>";
        } else {
            echo "<div style='background-color:#f8d7da; color:#721c24; padding:12px; border-radius:8px; border:1px solid #f5c6cb; margin-bottom:15px;'>";
            echo "<b>Gagal Mengatur Tombol Menu:</b> " . htmlspecialchars($resJson['description'] ?? 'Respon error tidak diketahui.');
            echo "</div>";
        }
    } else {
        $error = error_get_last();
        echo "<div style='background-color:#f8d7da; color:#721c24; padding:12px; border-radius:8px; border:1px solid #f5c6cb; margin-bottom:15px;'>";
        echo "<b>Gagal:</b> Tidak dapat menghubungi API Telegram untuk mengatur tombol menu.<br>";
        echo "Detail Error: " . htmlspecialchars($error['message'] ?? 'Koneksi Timeout.');
        echo "</div>";
    }
}

echo "<div style='margin-top: 15px;'>";
echo "<a href='?cb=" . time() . "&set_webhook=1' style='display:inline-block; background-color:#0088cc; color:white; padding:10px 20px; text-decoration:none; border-radius:8px; font-weight:bold; font-family:sans-serif; font-size:14px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: all 0.2s; margin-right: 10px;'>⚡ Aktifkan Fitur Pencarian Bot</a>";
echo "<a href='?cb=" . time() . "&set_menu_button=1' style='display:inline-block; background-color:#10b981; color:white; padding:10px 20px; text-decoration:none; border-radius:8px; font-weight:bold; font-family:sans-serif; font-size:14px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: all 0.2s;'>📱 Aktifkan Tombol Menu WebApp</a>";
echo "</div>";
echo "<br><small style='color:#666;'>Tautan Webhook/WebApp Anda: <code>$webhookUrl</code></small>";

