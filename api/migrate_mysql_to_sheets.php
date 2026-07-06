<?php
/**
 * Script Migrasi MySQL ke Google Sheets
 * Dilengkapi UI Progress Bar Interaktif untuk menghindari timeout di Vercel (Limit 10s).
 */

// 1. Konfigurasi MySQL Lama
$mysql_host = 'z74bzi.h.filess.io';
$mysql_port = '3307';
$mysql_user = 'rekapit_momenthelp';
$mysql_pass = '00e2bc34ce76ea678fa903001f3ec5f24e91ba49';
$mysql_name = 'rekapit_momenthelp';

// Load local .env file if it exists to populate getenv/$_ENV
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $name = trim($parts[0]);
            $value = trim($parts[1]);
            // Remove surrounding quotes if any
            if (preg_match('/^"(.*)"$/', $value, $matches)) {
                $value = $matches[1];
            } elseif (preg_match('/^\'(.*)\'$/', $value, $matches)) {
                $value = $matches[1];
            }
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// 2. URL Web App Google Apps Script
$google_sheet_webapp_url = getenv('GOOGLE_SHEET_WEBAPP_URL') ?: 'https://script.google.com/macros/s/AKfycbysMXyw48D4STuA8cOc-hwOlWgWoltjSaT04W-ouuI4Gs10qXE9ioTgOj3Bzx32q0eDKQ/exec'; 

$tables = [
    "cabang", "divisi", "kategori_aset", "karyawan", "users", "assets", 
    "asset_history", "maintenance", "repairs", "activity_logs", 
    "asset_mutations", "audits", "sparepart", "penggunaan_sparepart"
];

// Cek parameter action untuk request AJAX
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'syncTable') {
    header('Content-Type: application/json');
    $table = isset($_GET['table']) ? $_GET['table'] : '';
    
    if (!in_array($table, $tables)) {
        echo json_encode(['success' => false, 'error' => 'Tabel tidak valid']);
        exit;
    }
    
    try {
        // Koneksi ke MySQL
        $dsn = "mysql:host=$mysql_host;port=$mysql_port;dbname=$mysql_name;charset=utf8mb4";
        $mysql_conn = new PDO($dsn, $mysql_user, $mysql_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        // Cek apakah tabel ada
        $check = $mysql_conn->query("SHOW TABLES LIKE '$table'")->fetch();
        if (!$check) {
            echo json_encode(['success' => true, 'skipped' => true, 'message' => "Tabel '$table' tidak ditemukan di MySQL. Dilewati."]);
            exit;
        }
        
        // Baca data dari MySQL
        $stmt = $mysql_conn->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll();
        
        // Kirim data ke Google Sheets
        $payload = [
            'action' => 'batchSync',
            'tables' => [
                $table => $rows
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $google_sheet_webapp_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);
        
        if ($httpCode === 200) {
            $resData = json_decode($response, true);
            if (isset($resData['success']) && $resData['success'] === true) {
                echo json_encode(['success' => true, 'count' => count($rows)]);
            } else {
                echo json_encode(['success' => false, 'error' => ($resData['error'] ?? 'API error')]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => "Google Web App HTTP Code: $httpCode"]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'clearCache') {
    header('Content-Type: application/json');
    try {
        if (getenv('VERCEL') || DIRECTORY_SEPARATOR === '/') {
            $sqlite_db_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rekapit_cache.sqlite';
        } else {
            $sqlite_db_path = dirname(__DIR__) . '/database/rekapit_cache.sqlite';
        }

        if (file_exists($sqlite_db_path)) {
            unlink($sqlite_db_path);
        }
        if (file_exists($sqlite_db_path . '.json')) {
            unlink($sqlite_db_path . '.json');
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrasi Database - Rekap IT</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #0f172a;
            color: #f1f5f9;
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .migrator-card {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(16px);
            border-radius: 24px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .header-icon {
            font-size: 3.5rem;
            color: #bef264;
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }
        .btn-start {
            background-color: #bef264;
            color: #0f172a;
            font-weight: 700;
            padding: 14px 28px;
            border-radius: 999px;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-start:hover:not(:disabled) {
            background-color: #d9f99d;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(190, 242, 100, 0.25);
        }
        .btn-start:disabled {
            background-color: #475569;
            color: #94a3b8;
        }
        .progress {
            height: 12px;
            background-color: #334155;
            border-radius: 999px;
            overflow: hidden;
        }
        .progress-bar {
            background-color: #bef264;
            border-radius: 999px;
        }
        .log-box {
            background-color: #020617;
            border: 1px solid #1e293b;
            border-radius: 16px;
            height: 200px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 0.85rem;
            padding: 15px;
            color: #38bdf8;
        }
        .log-entry {
            margin-bottom: 6px;
            line-height: 1.4;
        }
        .log-success { color: #4ade80; }
        .log-warning { color: #fbbf24; }
        .log-error { color: #f87171; }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
    </style>
</head>
<body>

<div class="migrator-card text-center">
    <div class="header-icon">
        <i class="bi bi-database-fill-gear"></i>
    </div>
    <h2 class="fw-bold mb-2">MySQL ➡️ Google Sheets</h2>
    <p class="text-muted mb-4">Migrasi data otomatis table-by-table untuk menghindari batas waktu pemrosesan serverless Vercel.</p>
    
    <div class="mb-4 text-start">
        <label class="form-label d-flex justify-content-between">
            <span>Kemajuan Migrasi</span>
            <span id="progress-text" class="fw-bold">0%</span>
        </label>
        <div class="progress mb-3">
            <div id="progress-bar" class="progress-bar" role="progressbar" style="width: 0%"></div>
        </div>
    </div>
    
    <div class="log-box text-start mb-4" id="log-box">
        <div class="log-entry text-muted">Sistem siap. Klik "Mulai Migrasi" untuk memulai proses transfer data.</div>
    </div>
    
    <button class="btn btn-start w-100" id="btn-start" onclick="startMigration()">
        <i class="bi bi-play-fill me-1"></i> Mulai Migrasi
    </button>
</div>

<script>
const tables = <?php echo json_encode($tables); ?>;
const logBox = document.getElementById('log-box');
const progressBar = document.getElementById('progress-bar');
const progressText = document.getElementById('progress-text');
const btnStart = document.getElementById('btn-start');

function appendLog(message, type = 'info') {
    const entry = document.createElement('div');
    entry.className = `log-entry ${type === 'success' ? 'log-success' : type === 'warning' ? 'log-warning' : type === 'error' ? 'log-error' : ''}`;
    entry.innerHTML = `[${new Date().toLocaleTimeString()}] ${message}`;
    logBox.appendChild(entry);
    logBox.scrollTop = logBox.scrollHeight;
}

async function startMigration() {
    btnStart.disabled = true;
    btnStart.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Memproses migrasi...';
    
    logBox.innerHTML = '';
    appendLog('Memulai migrasi database...', 'info');
    
    let successCount = 0;
    
    for (let i = 0; i < tables.length; i++) {
        const table = tables[i];
        appendLog(`Memproses tabel <strong>${table}</strong> (${i+1}/${tables.length})...`, 'info');
        
        try {
            const response = await fetch(`migrate_mysql_to_sheets.php?action=syncTable&table=${table}`);
            const result = await response.json();
            
            if (result.success) {
                if (result.skipped) {
                    appendLog(`⚠️ ${result.message}`, 'warning');
                } else {
                    appendLog(`✅ Tabel <strong>${table}</strong> berhasil disinkronkan (${result.count} baris).`, 'success');
                }
                successCount++;
            } else {
                appendLog(`❌ Gagal mensinkronkan tabel <strong>${table}</strong>: ${result.error}`, 'error');
            }
        } catch (error) {
            appendLog(`❌ Koneksi gagal saat memproses <strong>${table}</strong>: ${error.message}`, 'error');
        }
        
        // Update progress bar
        const percentage = Math.round(((i + 1) / tables.length) * 100);
        progressBar.style.width = `${percentage}%`;
        progressText.innerText = `${percentage}%`;
    }
    
    if (successCount === tables.length) {
        appendLog('Membersihkan cache SQLite lokal agar sinkron...', 'info');
        try {
            const cacheRes = await fetch('migrate_mysql_to_sheets.php?action=clearCache');
            const cacheResult = await cacheRes.json();
            if (cacheResult.success) {
                appendLog('🎉 <strong>MIGRASI SELESAI DENGAN SUKSES!</strong> Semua data telah dipindahkan ke Google Sheets.', 'success');
                btnStart.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Selesai!';
                btnStart.className = 'btn btn-success w-100 fw-bold py-3 mt-3';
                
                // Add button to return to dashboard
                const dashBtn = document.createElement('a');
                dashBtn.href = 'index.php';
                dashBtn.className = 'btn btn-outline-light w-100 fw-bold py-3 mt-2';
                dashBtn.innerText = 'Buka Dashboard Aplikasi';
                btnStart.parentNode.appendChild(dashBtn);
            } else {
                appendLog(`❌ Gagal membersihkan cache SQLite: ${cacheResult.error}`, 'error');
                resetStartBtn();
            }
        } catch (error) {
            appendLog(`❌ Koneksi cache error: ${error.message}`, 'error');
            resetStartBtn();
        }
    } else {
        appendLog('⚠️ Migrasi selesai dengan beberapa kesalahan. Periksa log di atas.', 'warning');
        resetStartBtn();
    }
}

function resetStartBtn() {
    btnStart.disabled = false;
    btnStart.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Ulangi Migrasi';
}
</script>
</body>
</html>
