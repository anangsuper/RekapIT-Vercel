<?php
// Set headers
header('Content-Type: application/json');

// Load database connection
if (getenv('VERCEL') || DIRECTORY_SEPARATOR === '/') {
    $dbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rekapit_cache.sqlite';
} else {
    $dbPath = __DIR__ . '/../database/rekapit_cache.sqlite';
}

try {
    $conn = new PDO("sqlite:" . $dbPath);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Webhook DB Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit();
}

// Get POST request body from Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update || !isset($update["message"])) {
    echo json_encode(['success' => false, 'message' => 'No message found']);
    exit();
}

$message = $update["message"];
$chatId = $message["chat"]["id"];
$text = $message["text"] ?? '';
$messageId = $message["message_id"];

// Only respond if text starts with "/"
if (strpos($text, '/') !== 0) {
    echo json_encode(['success' => true, 'message' => 'Not a command']);
    exit();
}

// Parse command and arguments
$parts = explode(' ', $text, 2);
$command = strtolower($parts[0]);
$argument = isset($parts[1]) ? trim($parts[1]) : '';

// Remove bot username suffix if present (e.g. /cari@RekapItBot)
if (strpos($command, '@') !== false) {
    $command = explode('@', $command)[0];
}

$responseText = '';

if ($command === '/start' || $command === '/help') {
    $responseText = "🤖 *REKAP IT TELEGRAM BOT*\n\n"
                  . "Halo! Anda dapat mencari informasi aset langsung melalui chat Telegram ini.\n\n"
                  . "*Daftar Perintah:*\n"
                  . "🔍 `/cari [kode/nama_aset]` - Mencari detail aset berdasarkan kode/nama\n"
                  . "❓ `/help` - Menampilkan daftar perintah bantuan\n\n"
                  . "_Contoh penggunaan: `/cari LAP-001`_";
} elseif ($command === '/cari') {
    if (empty($argument)) {
        $responseText = "⚠️ *Format Salah.*\nSilakan masukkan kode atau nama aset yang ingin dicari.\nContoh: `/cari LAP-001`";
    } else {
        // Query asset from database
        $query = "SELECT a.*, c.nama_cabang, d.nama_divisi, k.nama_karyawan 
                  FROM assets a
                  LEFT JOIN cabang c ON a.id_cabang = c.id
                  LEFT JOIN divisi d ON a.id_divisi = d.id
                  LEFT JOIN karyawan k ON a.id_karyawan = k.id
                  WHERE a.kode_aset LIKE :q OR a.nama_aset LIKE :q LIMIT 1";
                  
        $stmt = $conn->prepare($query);
        $stmt->execute([':q' => "%$argument%"]);
        $asset = $stmt->fetch();
        
        if ($asset) {
            $statusEmoji = ($asset['kondisi'] === 'Baik') ? '🟢' : (($asset['kondisi'] === 'Rusak Ringan') ? '🟡' : '🔴');
            
            // Get last maintenance activity
            $maintQuery = "SELECT * FROM maintenance WHERE asset_id = ? ORDER BY tanggal DESC LIMIT 1";
            $maintStmt = $conn->prepare($maintQuery);
            $maintStmt->execute([$asset['id']]);
            $lastMaint = $maintStmt->fetch();
            
            $maintText = "-";
            if ($lastMaint) {
                $maintDate = date('d M Y', strtotime($lastMaint['tanggal']));
                $maintText = "{$maintDate} (Hasil: {$lastMaint['temuan']})";
            }
            
            $responseText = "🔍 *HASIL PENCARIAN ASET*\n\n"
                          . "*• Kode Aset:* `{$asset['kode_aset']}`\n"
                          . "*• Nama Aset:* {$asset['nama_aset']}\n"
                          . "*• Merk/Model:* " . ($asset['merk'] ? "{$asset['merk']} " : "") . "{$asset['model']}\n"
                          . "*• Kondisi:* {$statusEmoji} *{$asset['kondisi']}*\n"
                          . "*• Cabang:* {$asset['nama_cabang']}\n"
                          . "*• Divisi:* {$asset['nama_divisi']}\n"
                          . "*• Pengguna:* " . ($asset['nama_karyawan'] ?: '-') . "\n"
                          . "*• Serial Number:* `" . ($asset['serial_number'] ?: '-') . "`\n"
                          . "*• Cek Terakhir:* {$maintText}";
        } else {
            $responseText = "❌ Aset dengan kata kunci *\"{$argument}\"* tidak ditemukan di database.";
        }
    }
}

if (!empty($responseText)) {
    // Reply back via Telegram API using stream context
    $token = getenv('TELEGRAM_BOT_TOKEN') ?: ($_ENV['TELEGRAM_BOT_TOKEN'] ?? ($_SERVER['TELEGRAM_BOT_TOKEN'] ?? ''));
    if (!empty($token)) {
        $url = "https://api.telegram.org/bot" . $token . "/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => $responseText,
            'parse_mode' => 'Markdown',
            'reply_to_message_id' => $messageId
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
                'verify_peer_name' => false
            ]
        ];
        $context  = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        
        echo json_encode(['success' => $result !== false]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No Telegram Token configured']);
    }
} else {
    echo json_encode(['success' => true, 'message' => 'No action taken']);
}
