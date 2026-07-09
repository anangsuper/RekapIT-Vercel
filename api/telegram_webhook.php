<?php
// Set headers
header('Content-Type: application/json');

// Load central database config (Auto-initializes Vercel SQLite tables and schema)
require_once __DIR__ . '/../config/database.php';

// Get POST request body from Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    echo json_encode(['success' => false, 'message' => 'Invalid update JSON']);
    exit();
}

/**
 * -------------------------------------------------------------
 * TELEGRAM API HELPER FUNCTIONS
 * -------------------------------------------------------------
 */

/**
 * Send request to Telegram API.
 */
function sendTelegramApi($method, $data = []) {
    $token = getenv('TELEGRAM_BOT_TOKEN') ?: ($_ENV['TELEGRAM_BOT_TOKEN'] ?? ($_SERVER['TELEGRAM_BOT_TOKEN'] ?? ''));
    if (empty($token)) {
        error_log("Telegram API: Token is missing for method {$method}");
        return false;
    }
    
    $url = "https://api.telegram.org/bot" . $token . "/" . $method;
    
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
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    if ($result === false) {
        $error = error_get_last();
        error_log("Telegram API fail: Method {$method}. Error: " . ($error['message'] ?? 'Unknown'));
        return false;
    }
    
    return json_decode($result, true);
}

/**
 * Send standard Telegram message.
 */
function replyMessage($chatId, $text, $keyboard = null, $replyToMessageId = null) {
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    if ($replyToMessageId) {
        $data['reply_to_message_id'] = $replyToMessageId;
    }
    return sendTelegramApi('sendMessage', $data);
}

/**
 * Edit existing Telegram message text.
 */
function editMessage($chatId, $messageId, $text, $keyboard = null) {
    $data = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    return sendTelegramApi('editMessageText', $data);
}

/**
 * Acknowledge Callback Query.
 */
function answerCallback($callbackId) {
    return sendTelegramApi('answerCallbackQuery', ['callback_query_id' => $callbackId]);
}

/**
 * Answer Inline Query search results.
 */
function answerInline($queryId, $results) {
    return sendTelegramApi('answerInlineQuery', [
        'inline_query_id' => $queryId,
        'results' => json_encode($results),
        'cache_time' => 0
    ]);
}

/**
 * -------------------------------------------------------------
 * 1. INLINE QUERY HANDLER
 * -------------------------------------------------------------
 */
if (isset($update["inline_query"])) {
    $inlineQuery = $update["inline_query"];
    $queryId = $inlineQuery["id"];
    $queryString = trim($inlineQuery["query"]);
    
    $results = [];
    
    if (!empty($queryString)) {
        // Query database for matching assets
        $q = "%$queryString%";
        $query = "SELECT a.*, c.nama_cabang, d.nama_divisi, k.nama_karyawan 
                  FROM assets a
                  LEFT JOIN cabang c ON a.id_cabang = c.id
                  LEFT JOIN divisi d ON a.id_divisi = d.id
                  LEFT JOIN karyawan k ON a.id_karyawan = k.id
                  WHERE a.kode_aset LIKE :q OR a.nama_aset LIKE :q LIMIT 5";
                  
        $stmt = $conn->prepare($query);
        $stmt->execute([':q' => $q]);
        $assets = $stmt->fetchAll();
        
        foreach ($assets as $asset) {
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
            
            $responseText = "🔍 *DETAIL INFORMASI ASET*\n\n"
                          . "*• Kode Aset:* `{$asset['kode_aset']}`\n"
                          . "*• Nama Aset:* {$asset['nama_aset']}\n"
                          . "*• Merk/Model:* " . ($asset['merk'] ? "{$asset['merk']} " : "") . "{$asset['model']}\n"
                          . "*• Kondisi:* {$statusEmoji} *{$asset['kondisi']}*\n"
                          . "*• Cabang:* {$asset['nama_cabang']}\n"
                          . "*• Divisi:* {$asset['nama_divisi']}\n"
                          . "*• Pengguna:* " . ($asset['nama_karyawan'] ?: '-') . "\n"
                          . "*• Serial Number:* `" . ($asset['serial_number'] ?: '-') . "`\n"
                          . "*• Cek Terakhir:* {$maintText}";
                          
            $results[] = [
                'type' => 'article',
                'id' => uniqid(),
                'title' => "[{$asset['kode_aset']}] {$asset['nama_aset']}",
                'description' => "Kondisi: {$asset['kondisi']} | Cabang: {$asset['nama_cabang']}",
                'input_message_content' => [
                    'message_text' => $responseText,
                    'parse_mode' => 'Markdown'
                ]
            ];
        }
    }
    
    // Default placeholder suggestion list
    if (empty($results)) {
        $results[] = [
            'type' => 'article',
            'id' => 'prompt_search',
            'title' => 'Cari Aset IT...',
            'description' => 'Ketik kode/nama aset, contoh: LAP-001',
            'input_message_content' => [
                'message_text' => "Ketik nama bot diikuti kode aset untuk mencari secara instan.\nContoh: `@RekapItBot LAP-001` lalu klik pop-up hasil yang muncul.",
                'parse_mode' => 'Markdown'
            ]
        ];
    }
    
    answerInline($queryId, $results);
    echo json_encode(['success' => true]);
    exit();
}

/**
 * -------------------------------------------------------------
 * 2. CALLBACK QUERY HANDLER
 * -------------------------------------------------------------
 */
if (isset($update["callback_query"])) {
    $callbackQuery = $update["callback_query"];
    $callbackId = $callbackQuery["id"];
    $data = $callbackQuery["data"];
    $message = $callbackQuery["message"];
    $chatId = $message["chat"]["id"];
    $messageId = $message["message_id"];
    
    if ($data === 'new_wizard_start') {
        $cStmt = $conn->query("SELECT id, nama_kategori FROM kategori_aset ORDER BY nama_kategori ASC");
        $categories = $cStmt->fetchAll();
        $inlineButtons = [];
        foreach ($categories as $cat) {
            $inlineButtons[] = [['text' => $cat['nama_kategori'], 'callback_data' => "new_cat:{$cat['id']}"]];
        }
        $keyboard = ['inline_keyboard' => $inlineButtons];
        $text = "➕ *WIZARD TAMBAH ASET*\n\nSilakan pilih *Kategori Aset* yang ingin Anda daftarkan:";
        editMessage($chatId, $messageId, $text, $keyboard);
        
    } elseif (strpos($data, 'new_cat:') === 0) {
        $catId = intval(substr($data, 8));
        
        // Fetch category
        $cStmt = $conn->prepare("SELECT nama_kategori FROM kategori_aset WHERE id = ?");
        $cStmt->execute([$catId]);
        $kategori = $cStmt->fetch();
        
        if ($kategori) {
            // Fetch branches
            $bStmt = $conn->query("SELECT id, nama_cabang FROM cabang ORDER BY nama_cabang ASC");
            $branches = $bStmt->fetchAll();
            
            $inlineButtons = [];
            foreach ($branches as $br) {
                $inlineButtons[] = [['text' => $br['nama_cabang'], 'callback_data' => "new_br:{$catId}:{$br['id']}"]];
            }
            
            $keyboard = ['inline_keyboard' => $inlineButtons];
            $text = "➕ *TAMBAH ASET BARU*\n"
                  . "*• Kategori:* " . htmlspecialchars($kategori['nama_kategori']) . "\n\n"
                  . "Sekarang silakan klik *Kantor Cabang* penugasan aset:";
                  
            editMessage($chatId, $messageId, $text, $keyboard);
        }
        
    } elseif (strpos($data, 'new_br:') === 0) {
        $parts = explode(':', $data);
        $catId = intval($parts[1]);
        $brId = intval($parts[2]);
        
        // Fetch category and branch details
        $catStmt = $conn->prepare("SELECT nama_kategori FROM kategori_aset WHERE id = ?");
        $catStmt->execute([$catId]);
        $kategori = $catStmt->fetch();
        
        $brStmt = $conn->prepare("SELECT nama_cabang FROM cabang WHERE id = ?");
        $brStmt->execute([$brId]);
        $cabang = $brStmt->fetch();
        
        if ($kategori && $cabang) {
            // Generate Asset Code prefix
            $cleanName = preg_replace('/[^a-zA-Z]/', '', $kategori['nama_kategori']);
            $prefix = strtoupper(substr($cleanName, 0, 3));
            if (strlen($prefix) < 3) {
                $prefix = str_pad($prefix, 3, 'X');
            }
            
            // Find next incremental code
            $stmt = $conn->prepare("SELECT kode_aset FROM assets WHERE kode_aset LIKE ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$prefix . '-%']);
            $lastAsset = $stmt->fetch();
            
            $nextNum = 1;
            if ($lastAsset) {
                $codeParts = explode('-', $lastAsset['kode_aset']);
                if (count($codeParts) >= 2) {
                    $lastNum = intval($codeParts[1]);
                    $nextNum = $lastNum + 1;
                }
            }
            $newCode = $prefix . '-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
            $namaAset = $kategori['nama_kategori'] . " Baru " . $newCode;
            
            // Insert into Database
            $conn->beginTransaction();
            try {
                $insertStmt = $conn->prepare("INSERT INTO assets (kode_aset, nama_aset, id_kategori, id_cabang, kondisi, created_at) VALUES (?, ?, ?, ?, 'Baik', datetime('now', 'localtime'))");
                $insertStmt->execute([$newCode, $namaAset, $catId, $brId]);
                $conn->commit();
                
                $text = "✅ *ASET BARU BERHASIL DITAMBAHKAN*\n\n"
                      . "*• Kode Aset:* `{$newCode}`\n"
                      . "*• Nama Aset:* {$namaAset}\n"
                      . "*• Kategori:* {$kategori['nama_kategori']}\n"
                      . "*• Cabang:* {$cabang['nama_cabang']}\n"
                      . "*• Kondisi:* 🟢 Baik\n\n"
                      . "Aset baru telah didaftarkan secara real-time ke dalam database RekapIT. Anda dapat melengkapi serial number, spesifikasi, dan divisi melalui panel website.";
            } catch (Exception $e) {
                $conn->rollBack();
                $text = "❌ *Gagal menyimpan aset:* " . $e->getMessage();
            }
            
            editMessage($chatId, $messageId, $text);
        }
    }
    
    // Answer callback query
    answerCallback($callbackId);
    echo json_encode(['success' => true]);
    exit();
}

/**
 * -------------------------------------------------------------
 * 3. INCOMING COMMAND MESSAGE PARSER
 * -------------------------------------------------------------
 */
if (!isset($update["message"])) {
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
$replyMarkupKeyboard = null;

/**
 * -------------------------------------------------------------
 * 4. ROUTE COMMAND HANDLERS
 * -------------------------------------------------------------
 */
if ($command === '/start' || $command === '/help') {
    $responseText = "🤖 *REKAP IT TELEGRAM BOT*\n\n"
                  . "Halo! Anda dapat mengelola dan memantau database RekapIT langsung melalui chat Telegram ini.\n\n"
                  . "*Daftar Perintah (Commands):*\n"
                  . "🔍 `/cari [kode/nama_aset]` - Mencari detail aset berdasarkan kode/nama\n"
                  . "➕ `/tambah` - Tambah aset baru (Formulir Web)\n"
                  . "📝 `/tambah_manual` atau `/tm` - Tambah aset lengkap via formulir teks manual\n"
                  . "🛠 `/maintenance` atau `/m` - Catat laporan pemeriksaan maintenance massal\n"
                  . "❓ `/help` - Menampilkan daftar perintah bantuan ini\n\n"
                  . "*Fitur Pencarian Instan (Inline Query):*\n"
                  . "🔍 Cukup ketik `@RekapItBot [kode/nama]` di kolom obrolan mana saja (bahkan di grup atau chat pribadi lain) untuk mencari aset secara instan tanpa mengirim pesan.\n\n"
                  . "_Ketik `/tm` untuk melihat format template tambah manual._\n"
                  . "_Ketik `/m` untuk melihat format template maintenance._";
                  
    $appUrl = "https://" . ($_SERVER['HTTP_HOST'] ?? 'rekap-it-vercel-txjt.vercel.app');
    $fromUsername = $message['from']['username'] ?? '';
    $replyMarkupKeyboard = [
        'inline_keyboard' => [
            [
                [
                    'text' => '📱 Tambah Aset (Formulir Web)',
                    'web_app' => ['url' => $appUrl . "/api/telegram_add_asset.php?tg_user=" . urlencode($fromUsername)]
                ]
            ]
        ]
    ];
    
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
    
} elseif ($command === '/maintenance' || $command === '/m') {
    if (empty($argument)) {
        $responseText = "📝 *PANDUAN MAINTENANCE MASSAL VIA BOT*\n\n"
                      . "Kirim laporan pemeriksaan beberapa aset sekaligus dengan format formulir berikut:\n\n"
                      . "`/m`\n"
                      . "Teknisi: [Nama Anda]\n"
                      . "Tanggal: [YYYY-MM-DD (Opsional)]\n"
                      . "[KODE ASET 1] | [STATUS] | [TEMUAN]\n"
                      . "[KODE ASET 2] | [STATUS] | [TEMUAN]\n\n"
                      . "*Pilihan Status:* Baik / Perlu Tindakan / Rusak\n\n"
                      . "*Contoh Laporan:*\n"
                      . "`/m`\n"
                      . "Teknisi: Rian Hidayat\n"
                      . "LAP-001 | Baik | Pembersihan debu & ganti thermal paste\n"
                      . "PRN-002 | Perlu Tindakan | Kertas sering tersangkut\n"
                      . "MON-003 | Rusak | Panel pecah";
    } else {
        // Parse lines
        $lines = explode("\n", $argument);
        
        $teknisi = 'Teknisi Telegram';
        $tanggal = date('Y-m-d');
        $assetChecks = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Check headers
            if (stripos($line, 'Teknisi:') === 0) {
                $teknisi = trim(substr($line, 8));
                continue;
            }
            if (stripos($line, 'Tanggal:') === 0) {
                $tanggal = trim(substr($line, 8));
                continue;
            }
            
            // Parse row format: KODE | STATUS | TEMUAN
            $rowParts = explode('|', $line);
            if (count($rowParts) >= 2) {
                $kode = trim($rowParts[0]);
                $statusInput = strtolower(trim($rowParts[1]));
                $temuan = isset($rowParts[2]) ? trim($rowParts[2]) : 'Pengecekan Rutin';
                
                // Map status
                $statusDb = 'Baik';
                if (strpos($statusInput, 'tindakan') !== false || strpos($statusInput, 'perlu') !== false || strpos($statusInput, 'ringan') !== false) {
                    $statusDb = 'Perlu Perbaikan';
                } elseif (strpos($statusInput, 'rusak') !== false || strpos($statusInput, 'berat') !== false) {
                    $statusDb = 'Rusak';
                }
                
                $assetChecks[] = [
                    'kode' => $kode,
                    'status' => $statusDb,
                    'temuan' => $temuan
                ];
            }
        }
        
        if (empty($assetChecks)) {
            $responseText = "⚠️ *Laporan Gagal Dibuat.*\nFormat pengisian tidak valid. Pastikan menggunakan pemisah garis tegak `|`.\n\nContoh:\n`LAP-001 | Baik | Pembersihan debu`";
        } else {
            // Start transaction
            $conn->beginTransaction();
            try {
                $successList = [];
                $failList = [];
                
                foreach ($assetChecks as $check) {
                    // Find asset
                    $aStmt = $conn->prepare("SELECT id, nama_aset FROM assets WHERE kode_aset = ?");
                    $aStmt->execute([$check['kode']]);
                    $asset = $aStmt->fetch();
                    
                    if ($asset) {
                        // Insert maintenance
                        $mStmt = $conn->prepare("INSERT INTO maintenance (asset_id, tanggal, teknisi, temuan, tindakan, status) VALUES (?, ?, ?, ?, ?, ?)");
                        $mStmt->execute([
                            $asset['id'],
                            $tanggal,
                            $teknisi,
                            $check['temuan'],
                            'Pengecekan massal diinput via Telegram Bot',
                            $check['status']
                        ]);
                        
                        // Update asset status
                        $uStmt = $conn->prepare("UPDATE assets SET kondisi = ? WHERE id = ?");
                        $uStmt->execute([$check['status'], $asset['id']]);
                        
                        $statusEmoji = ($check['status'] === 'Baik') ? '🟢' : (($check['status'] === 'Perlu Perbaikan') ? '🟡' : '🔴');
                        $successList[] = "• `{$check['kode']}` - {$asset['nama_aset']}: {$statusEmoji} *{$check['status']}*";
                    } else {
                        $failList[] = "• `{$check['kode']}`: Aset tidak terdaftar";
                    }
                }
                
                $conn->commit();
                
                $responseText = "✅ *MAINTENANCE MASSAL BERHASIL DICATAT*\n\n"
                              . "*• Tanggal:* " . date('d M Y', strtotime($tanggal)) . "\n"
                              . "*• Teknisi:* {$teknisi}\n\n"
                              . "*Aset Berhasil Diperiksa:*\n"
                              . (implode("\n", $successList) ?: "Tidak ada\n");
                              
                if (!empty($failList)) {
                    $responseText .= "\n\n*Aset Gagal Diperiksa:*\n" . implode("\n", $failList);
                }
            } catch (Exception $e) {
                $conn->rollBack();
                $responseText = "❌ *Gagal menyimpan ke database:* " . $e->getMessage();
            }
        }
    }
    
} elseif ($command === '/tambah') {
    try {
        $appUrl = "https://" . ($_SERVER['HTTP_HOST'] ?? 'rekap-it-vercel-txjt.vercel.app');
        $fromUsername = $message['from']['username'] ?? '';
        $replyMarkupKeyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '📱 Buka Formulir Web (Rekomendasi)', 
                        'web_app' => ['url' => $appUrl . "/api/telegram_add_asset.php?tg_user=" . urlencode($fromUsername)]
                    ]
                ]
            ]
        ];
        
        $responseText = "➕ *TAMBAH ASET BARU*\n\n"
                      . "Silakan klik tombol di bawah ini untuk membuka formulir input interaktif langsung di dalam Telegram (tinggal klik pilihan, tanpa mengetik manual!).";
    } catch (Exception $e) {
        $responseText = "❌ *Gagal memproses perintah /tambah:* " . $e->getMessage();
        error_log("Error in /tambah command: " . $e->getMessage());
    }
    
} elseif ($command === '/tambah_manual' || $command === '/tm') {
    if (empty($argument)) {
        $responseText = "➕ *FORMULIR TAMBAH ASET MANUAL*\n\n"
                      . "Salin template teks berikut, lengkapi datanya, lalu kirim kembali:\n\n"
                      . "`/tm`\n"
                      . "Kode: LAP-020\n"
                      . "Nama: MacBook Air M2\n"
                      . "SN: C02XYZ1234\n"
                      . "Kategori: Laptop\n"
                      . "Merk: Apple\n"
                      . "Model: A2681\n"
                      . "Cabang: Cabang Jakarta\n"
                      . "Divisi: IT Support\n"
                      . "Karyawan: Ahmad Hafizh\n"
                      . "Spesifikasi: RAM 16GB, SSD 512GB\n\n"
                      . "*Tips:* Cukup ganti nilai di kanan tanda titik dua (`:`) sesuai kebutuhan.";
    } else {
        // Parse lines
        $lines = explode("\n", $argument);
        
        $fields = [
            'kode' => '',
            'nama' => '',
            'sn' => '',
            'kategori' => '',
            'merk' => '',
            'model' => '',
            'cabang' => '',
            'divisi' => '',
            'karyawan' => '',
            'spesifikasi' => ''
        ];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if (preg_match('/^(Kode|Nama|SN|Kategori|Merk|Model|Cabang|Divisi|Karyawan|Spesifikasi)\s*:\s*(.*)$/i', $line, $matches)) {
                $key = strtolower($matches[1]);
                $fields[$key] = trim($matches[2]);
            }
        }
        
        // Validate required fields
        if (empty($fields['kode'])) {
            $responseText = "⚠️ *Gagal:* Baris `Kode:` tidak boleh kosong.";
        } elseif (empty($fields['nama'])) {
            $responseText = "⚠️ *Gagal:* Baris `Nama:` tidak boleh kosong.";
        } elseif (empty($fields['cabang'])) {
            $responseText = "⚠️ *Gagal:* Baris `Cabang:` tidak boleh kosong.";
        } else {
            // Resolve Kategori
            try {
                $idKategori = null;
                if (!empty($fields['kategori'])) {
                    $cStmt = $conn->prepare("SELECT id FROM kategori_aset WHERE nama_kategori LIKE ? LIMIT 1");
                    $cStmt->execute(['%' . $fields['kategori'] . '%']);
                    $cat = $cStmt->fetch();
                    if ($cat) {
                        $idKategori = $cat['id'];
                    } else {
                        $cInsert = $conn->prepare("INSERT INTO kategori_aset (nama_kategori, created_at) VALUES (?, datetime('now', 'localtime'))");
                        $cInsert->execute([$fields['kategori']]);
                        $idKategori = $conn->lastInsertId();
                    }
                }
                
                // Resolve Cabang
                $idCabang = null;
                $cbrStmt = $conn->prepare("SELECT id, nama_cabang FROM cabang WHERE nama_cabang LIKE ? LIMIT 1");
                $cbrStmt->execute(['%' . $fields['cabang'] . '%']);
                $cab = $cbrStmt->fetch();
                if ($cab) {
                    $idCabang = $cab['id'];
                    $fields['cabang'] = $cab['nama_cabang'];
                } else {
                    $cbrInsert = $conn->prepare("INSERT INTO cabang (nama_cabang, created_at) VALUES (?, datetime('now', 'localtime'))");
                    $cbrInsert->execute([$fields['cabang']]);
                    $idCabang = $conn->lastInsertId();
                }
                
                // Resolve Divisi
                $idDivisi = null;
                if (!empty($fields['divisi'])) {
                    $dStmt = $conn->prepare("SELECT id, nama_divisi FROM divisi WHERE nama_divisi LIKE ? LIMIT 1");
                    $dStmt->execute(['%' . $fields['divisi'] . '%']);
                    $div = $dStmt->fetch();
                    if ($div) {
                        $idDivisi = $div['id'];
                        $fields['divisi'] = $div['nama_divisi'];
                    } else {
                        $dInsert = $conn->prepare("INSERT INTO divisi (nama_divisi, created_at) VALUES (?, datetime('now', 'localtime'))");
                        $dInsert->execute([$fields['divisi']]);
                        $idDivisi = $conn->lastInsertId();
                    }
                }
                
                // Resolve Karyawan
                $idKaryawan = null;
                if (!empty($fields['karyawan'])) {
                    $kStmt = $conn->prepare("SELECT id, nama_karyawan FROM karyawan WHERE nama_karyawan LIKE ? LIMIT 1");
                    $kStmt->execute(['%' . $fields['karyawan'] . '%']);
                    $kary = $kStmt->fetch();
                    if ($kary) {
                        $idKaryawan = $kary['id'];
                        $fields['karyawan'] = $kary['nama_karyawan'];
                    } else {
                        $kInsert = $conn->prepare("INSERT INTO karyawan (nama_karyawan, id_cabang, id_divisi, created_at) VALUES (?, ?, ?, datetime('now', 'localtime'))");
                        $kInsert->execute([$fields['karyawan'], $idCabang, $idDivisi]);
                        $idKaryawan = $conn->lastInsertId();
                    }
                }
                
                // Check if code already exists
                $dupStmt = $conn->prepare("SELECT id FROM assets WHERE kode_aset = ?");
                $dupStmt->execute([$fields['kode']]);
                if ($dupStmt->fetch()) {
                    $responseText = "⚠️ *Gagal:* Kode Aset `{$fields['kode']}` sudah terdaftar di database.";
                } else {
                    // Insert Asset
                    $insertStmt = $conn->prepare("INSERT INTO assets (kode_aset, nama_aset, serial_number, id_kategori, merk, model, id_cabang, id_divisi, id_karyawan, spesifikasi, kondisi, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Baik', datetime('now', 'localtime'))");
                    $insertStmt->execute([
                        $fields['kode'],
                        $fields['nama'],
                        $fields['sn'],
                        $idKategori,
                        $fields['merk'],
                        $fields['model'],
                        $idCabang,
                        $idDivisi,
                        $idKaryawan,
                        $fields['spesifikasi']
                    ]);
                    
                    $responseText = "✅ *ASET BERHASIL DITAMBAHKAN*\n\n"
                                  . "*• Kode Aset:* `{$fields['kode']}`\n"
                                  . "*• Nama Aset:* {$fields['nama']}\n"
                                  . "*• Serial Number:* `" . ($fields['sn'] ?: '-') . "`\n"
                                  . "*• Kategori:* " . ($fields['kategori'] ?: '-') . "\n"
                                  . "*• Merk/Model:* " . ($fields['merk'] ?: '-') . " / " . ($fields['model'] ?: '-') . "\n"
                                  . "*• Cabang:* {$fields['cabang']}\n"
                                  . "*• Divisi:* " . ($fields['divisi'] ?: '-') . "\n"
                                  . "*• Pengguna:* " . ($fields['karyawan'] ?: '-') . "\n"
                                  . "*• Spesifikasi:* " . ($fields['spesifikasi'] ?: '-');
                }
            } catch (Exception $e) {
                $responseText = "❌ *Gagal memproses database:* " . $e->getMessage();
            }
        }
    }
}

/**
 * -------------------------------------------------------------
 * 5. SEND FINAL RESPONSE MESSAGE
 * -------------------------------------------------------------
 */
if (!empty($responseText)) {
    $res = replyMessage($chatId, $responseText, $replyMarkupKeyboard, $messageId);
    echo json_encode(['success' => $res !== false]);
} else {
    echo json_encode(['success' => true, 'message' => 'No action taken']);
}
