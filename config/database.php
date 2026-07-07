<?php

// Mulai buffering dan session jika belum untuk mendukung lingkungan serverless Vercel
if (ob_get_level() === 0) {
    ob_start();
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent browser and CDN caching for all dynamic pages
if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
}

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

// Sinkronisasi cookie ke $_SESSION (stateless session fallback)
if (isset($_COOKIE['REKAPIT_SESSION'])) {
    $decrypted = json_decode(base64_decode($_COOKIE['REKAPIT_SESSION']), true);
    if (is_array($decrypted)) {
        $_SESSION = array_merge($_SESSION, $decrypted);
    }
}

// Fungsi untuk menyimpan $_SESSION ke cookie saat script selesai
function save_session_to_cookie() {
    if (headers_sent()) {
        return;
    }
    $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if (!empty($_SESSION)) {
        $data = base64_encode(json_encode($_SESSION));
        // Simpan cookie selama 1 hari (secure & httponly)
        setcookie('REKAPIT_SESSION', $data, time() + 86400, '/', '', $isSecure, true);
    } else {
        // Hapus cookie jika session dikosongkan (misal saat logout)
        setcookie('REKAPIT_SESSION', '', time() - 3600, '/', '', $isSecure, true);
    }
}
register_shutdown_function('save_session_to_cookie');

/**
 * Konfigurasi Database - Rekap IT (Google Sheets Backend)
 */

// Silakan masukkan URL Web App Google Apps Script Anda di sini atau lewat env variable GOOGLE_SHEET_WEBAPP_URL
$google_sheet_webapp_url = getenv('GOOGLE_SHEET_WEBAPP_URL') ?: 'https://script.google.com/macros/s/AKfycbysMXyw48D4STuA8cOc-hwOlWgWoltjSaT04W-ouuI4Gs10qXE9ioTgOj3Bzx32q0eDKQ/exec';

class GoogleSheetsSync {
    private $dbPath;
    private $spreadsheetId;
    private $credentialsPath;
    private $cacheDuration = 300; // 300 detik cache (5 menit) agar tidak membebani pemuatan halaman di Vercel
    private $conn;

    public function __construct($dbPath, $spreadsheetId, $credentialsPath) {
        $this->dbPath = $dbPath;
        $this->spreadsheetId = $spreadsheetId;
        $this->credentialsPath = $credentialsPath;
    }

    public function setConnection($conn) {
        $this->conn = $conn;
    }

    public function getSQLiteConnection() {
        $conn = new PDO("sqlite:" . $this->dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        @$conn->sqliteCreateFunction('MONTH', function($date) {
            if (!$date) return null;
            return (int)date('m', strtotime($date));
        });
        @$conn->sqliteCreateFunction('YEAR', function($date) {
            if (!$date) return null;
            return (int)date('Y', strtotime($date));
        });
        return $conn;
    }

    public function ensureInitialized($forceSync = false, $throwOnError = false) {
        if (isset($_SESSION['needs_sync']) && $_SESSION['needs_sync'] === true) {
            $forceSync = true;
            $_SESSION['needs_sync'] = false;
        }

        $dbDir = dirname($this->dbPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }

        $metaFile = $this->dbPath . '.json';
        $needsSync = false;

        // Coba salin database cache bawaan dari folder repository jika di temp Vercel belum ada
        if (!file_exists($this->dbPath)) {
            $repoDbPath = dirname(__DIR__) . '/database/rekapit_cache.sqlite';
            if (file_exists($repoDbPath)) {
                copy($repoDbPath, $this->dbPath);
                $repoMetaPath = $repoDbPath . '.json';
                if (file_exists($repoMetaPath)) {
                    copy($repoMetaPath, $metaFile);
                }
            }
        }

        if (!file_exists($this->dbPath) || !file_exists($metaFile) || $forceSync) {
            $needsSync = true;
        } else {
            $meta = json_decode(file_get_contents($metaFile), true);
            $diff = time() - ($meta['last_sync'] ?? 0);
            if (!$meta || ($diff > $this->cacheDuration) || ($diff < 0)) {
                $needsSync = true;
            }
        }

        if ($needsSync) {
            if (defined('SKIP_DB_SYNC') && SKIP_DB_SYNC && file_exists($this->dbPath)) {
                return;
            }
            $this->pullFromGoogleSheets($throwOnError);
        }
    }

    private function getTablesSchema() {
        return [
            "cabang" => "CREATE TABLE IF NOT EXISTS cabang (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nama_cabang TEXT NOT NULL,
                alamat TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );",
            "divisi" => "CREATE TABLE IF NOT EXISTS divisi (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nama_divisi TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );",
            "kategori_aset" => "CREATE TABLE IF NOT EXISTS kategori_aset (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nama_kategori TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );",
            "karyawan" => "CREATE TABLE IF NOT EXISTS karyawan (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nama_karyawan TEXT NOT NULL,
                nip TEXT UNIQUE,
                id_cabang INTEGER,
                id_divisi INTEGER,
                jabatan TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );",
            "users" => "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nama TEXT NOT NULL,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                role TEXT DEFAULT 'teknisi',
                id_cabang INTEGER NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );",
            "assets" => "CREATE TABLE IF NOT EXISTS assets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                kode_aset TEXT NOT NULL UNIQUE,
                nama_aset TEXT NOT NULL,
                serial_number TEXT,
                id_kategori INTEGER,
                merk TEXT,
                model TEXT,
                tanggal_kadaluarsa_garansi TEXT NULL,
                id_cabang INTEGER,
                id_divisi INTEGER,
                id_karyawan INTEGER,
                spesifikasi TEXT,
                kondisi TEXT DEFAULT 'Baik',
                foto TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );",
            "asset_history" => "CREATE TABLE IF NOT EXISTS asset_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                asset_id INTEGER NOT NULL,
                user_id INTEGER,
                field_changed TEXT NOT NULL,
                old_value TEXT,
                new_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );",
            "maintenance" => "CREATE TABLE IF NOT EXISTS maintenance (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                asset_id INTEGER NOT NULL,
                tanggal TEXT NOT NULL,
                teknisi TEXT,
                temuan TEXT,
                tindakan TEXT,
                rekomendasi TEXT,
                status TEXT DEFAULT 'Baik',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );",
            "repairs" => "CREATE TABLE IF NOT EXISTS repairs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                asset_id INTEGER NOT NULL,
                keluhan TEXT NOT NULL,
                tindakan TEXT,
                biaya REAL DEFAULT 0.00,
                status TEXT DEFAULT 'Proses',
                tanggal_mulai TEXT,
                tanggal_selesai TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );",
            "activity_logs" => "CREATE TABLE IF NOT EXISTS activity_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                action TEXT NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );",
            "asset_mutations" => "CREATE TABLE IF NOT EXISTS asset_mutations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                asset_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                id_cabang_lama INTEGER,
                id_divisi_lama INTEGER,
                id_karyawan_lama INTEGER,
                id_cabang_baru INTEGER,
                id_divisi_baru INTEGER,
                id_karyawan_baru INTEGER,
                tanggal_mutasi TEXT NOT NULL,
                keterangan TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );",
            "audits" => "CREATE TABLE IF NOT EXISTS audits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                asset_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                tanggal_audit TEXT NOT NULL,
                kondisi_dilaporkan TEXT,
                kondisi_fisik TEXT NOT NULL,
                lokasi_fisik TEXT,
                catatan TEXT,
                status_verifikasi TEXT DEFAULT 'Sesuai',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );",
            "sparepart" => "CREATE TABLE IF NOT EXISTS sparepart (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                kode_sparepart TEXT UNIQUE,
                nama_sparepart TEXT NOT NULL,
                stok INTEGER DEFAULT 0,
                satuan TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );",
            "penggunaan_sparepart" => "CREATE TABLE IF NOT EXISTS penggunaan_sparepart (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_repair INTEGER NOT NULL,
                id_sparepart INTEGER NOT NULL,
                jumlah INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );"
        ];
    }

    public function getAccessToken() {
        $tokenFile = $this->dbPath . '_token.json';
        if (file_exists($tokenFile)) {
            $tokenData = json_decode(file_get_contents($tokenFile), true);
            if ($tokenData && isset($tokenData['access_token']) && isset($tokenData['expires_at']) && $tokenData['expires_at'] > time() + 60) {
                return $tokenData['access_token'];
            }
        }

        $creds = null;
        if (file_exists($this->credentialsPath)) {
            $creds = json_decode(file_get_contents($this->credentialsPath), true);
        } elseif (getenv('GOOGLE_SERVICE_ACCOUNT_JSON')) {
            $creds = json_decode(getenv('GOOGLE_SERVICE_ACCOUNT_JSON'), true);
        }

        if (!$creds || !isset($creds['private_key']) || !isset($creds['client_email'])) {
            throw new Exception("Kredensial Service Account tidak ditemukan di file config/service-account.json maupun env variable GOOGLE_SERVICE_ACCOUNT_JSON.");
        }

        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $now = time();
        $payload = json_encode([
            'iss' => $creds['client_email'],
            'scope' => 'https://www.googleapis.com/auth/spreadsheets',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        ]);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        $signatureInput = $base64UrlHeader . "." . $base64UrlPayload;

        $privateKey = $creds['private_key'];
        $signature = '';
        if (!openssl_sign($signatureInput, $signature, $privateKey, 'SHA256')) {
            throw new Exception("Gagal menandatangani JWT dengan OpenSSL.");
        }
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        $jwt = $signatureInput . "." . $base64UrlSignature;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            throw new Exception("Gagal menukar JWT dengan Access Token: " . $response);
        }

        $resData = json_decode($response, true);
        if (!isset($resData['access_token'])) {
            throw new Exception("Token akses tidak ditemukan dalam response.");
        }

        file_put_contents($tokenFile, json_encode([
            'access_token' => $resData['access_token'],
            'expires_at' => $now + ($resData['expires_in'] ?? 3600)
        ]));

        return $resData['access_token'];
    }

    public function pullFromGoogleSheets($throwOnError = false) {
        if (!$this->spreadsheetId) {
            if (!file_exists($this->dbPath)) {
                $this->initializeOfflineDatabase();
            }
            if ($throwOnError) {
                throw new Exception("GOOGLE_SPREADSHEET_ID tidak terkonfigurasi di env variable.");
            }
            return;
        }

        try {
            $accessToken = $this->getAccessToken();
        } catch (Exception $e) {
            error_log("Gagal autentikasi Service Account: " . $e->getMessage());
            if (!file_exists($this->dbPath)) {
                $this->initializeOfflineDatabase();
            }
            // Update last_sync to prevent immediate retry loop
            @file_put_contents($this->dbPath . '.json', json_encode(['last_sync' => time()]));
            if ($throwOnError) {
                throw $e;
            }
            return;
        }

        $tables = array_keys($this->getTablesSchema());
        $queryParams = [];
        foreach ($tables as $t) {
            $queryParams[] = 'ranges=' . urlencode($t . '!A:Z');
        }
        $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $this->spreadsheetId . '/values:batchGet?' . implode('&', $queryParams);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            $errMsg = "Google Sheets API error HTTP " . $httpCode . ": " . $response;
            error_log($errMsg);
            if (!file_exists($this->dbPath)) {
                $this->initializeOfflineDatabase();
            }
            // Update last_sync to prevent immediate retry loop
            @file_put_contents($this->dbPath . '.json', json_encode(['last_sync' => time()]));
            if ($throwOnError) {
                throw new Exception($errMsg);
            }
            return;
        }

        $resData = json_decode($response, true);
        if (!isset($resData['valueRanges']) || !is_array($resData['valueRanges'])) {
            if (!file_exists($this->dbPath)) {
                $this->initializeOfflineDatabase();
            }
            // Update last_sync to prevent immediate retry loop
            @file_put_contents($this->dbPath . '.json', json_encode(['last_sync' => time()]));
            if ($throwOnError) {
                throw new Exception("Format respons Google Sheets API tidak valid.");
            }
            return;
        }

        $data = [];
        foreach ($resData['valueRanges'] as $vr) {
            $rangeParts = explode('!', $vr['range'] ?? '');
            $tableName = str_replace("'", "", $rangeParts[0]);
            
            $values = $vr['values'] ?? [];
            if (empty($values)) {
                $data[$tableName] = [];
                continue;
            }

            $headers = $values[0];
            $rows = [];
            for ($i = 1; $i < count($values); $i++) {
                $row = [];
                $rowValues = $values[$i];
                $isEmpty = true;
                
                foreach ($headers as $colIdx => $header) {
                    $val = $rowValues[$colIdx] ?? '';
                    $row[$header] = $val;
                    if ($val !== '' && $val !== null) {
                        $isEmpty = false;
                    }
                }
                
                if (!$isEmpty) {
                    $rows[] = $row;
                }
            }
            $data[$tableName] = $rows;
        }

        $db = $this->getSQLiteConnection();
        $db->exec("PRAGMA synchronous = OFF");
        $db->exec("PRAGMA journal_mode = MEMORY");
        $schemas = $this->getTablesSchema();

        $db->beginTransaction();
        try {
            foreach ($schemas as $table => $sql) {
                $db->exec("DROP TABLE IF EXISTS `$table`");
                $db->exec($sql);

                if (isset($data[$table]) && is_array($data[$table])) {
                    $rows = $data[$table];
                    if (count($rows) > 0) {
                        $cols = array_keys($rows[0]);
                        $colList = implode(', ', array_map(fn($c) => "`$c`", $cols));
                        $paramList = implode(', ', array_map(fn($c) => ":$c", $cols));

                        $stmt = $db->prepare("INSERT OR REPLACE INTO `$table` ($colList) VALUES ($paramList)");
                        foreach ($rows as $row) {
                            $stmt->execute($row);
                        }
                    }
                }
            }

            $userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
            if ($userCount == 0) {
                $db->exec("INSERT INTO users (nama, username, password, role) VALUES ('Administrator', 'admin', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')");
                $this->appendRowToSheets('users', [
                    'id' => 1,
                    'nama' => 'Administrator',
                    'username' => 'admin',
                    'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                    'role' => 'admin'
                ]);
            }

            $db->commit();
            file_put_contents($this->dbPath . '.json', json_encode(['last_sync' => time()]));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Gagal memuat data dari Google Sheets API: " . $e->getMessage());
            if (!file_exists($this->dbPath)) {
                $this->initializeOfflineDatabase();
            }
            // Update last_sync to prevent immediate retry loop
            @file_put_contents($this->dbPath . '.json', json_encode(['last_sync' => time()]));
            if ($throwOnError) {
                throw $e;
            }
        }
    }

    private function initializeOfflineDatabase() {
        $db = $this->getSQLiteConnection();
        $schemas = $this->getTablesSchema();
        foreach ($schemas as $table => $sql) {
            $db->exec($sql);
        }
        $userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($userCount == 0) {
            $db->exec("INSERT INTO users (nama, username, password, role) VALUES ('Administrator', 'admin', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')");
        }
        file_put_contents($this->dbPath . '.json', json_encode(['last_sync' => time()]));
    }

    private function getSheetHeaders($table, $accessToken) {
        $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $this->spreadsheetId . '/values/' . urlencode($table) . '!A1:Z1';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $res = curl_exec($ch);
        @curl_close($ch);
        $data = json_decode($res, true);
        return $data['values'][0] ?? [];
    }

    private function appendRowToSheets($table, $rowData) {
        if (!$this->spreadsheetId) return;
        try {
            $accessToken = $this->getAccessToken();
            $headers = $this->getSheetHeaders($table, $accessToken);
            
            if (empty($headers)) {
                $headers = array_keys($rowData);
                if (!in_array('id', $headers)) {
                    array_unshift($headers, 'id');
                }
                $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $this->spreadsheetId . '/values/' . urlencode($table) . '!A1';
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url . '?valueInputOption=USER_ENTERED');
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['values' => [$headers]]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json'
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_exec($ch);
                @curl_close($ch);
            }

            $rowValues = [];
            foreach ($headers as $h) {
                $rowValues[] = isset($rowData[$h]) ? $rowData[$h] : "";
            }

            $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $this->spreadsheetId . '/values/' . urlencode($table) . '!A:Z:append?valueInputOption=USER_ENTERED';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['values' => [$rowValues]]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_exec($ch);
            @curl_close($ch);
        } catch (Exception $e) {
            error_log("Gagal append ke Google Sheets: " . $e->getMessage());
        }
    }

    private function findRowIndexById($table, $id, $accessToken) {
        $headers = $this->getSheetHeaders($table, $accessToken);
        $idColIdx = array_search('id', $headers);
        if ($idColIdx === false) $idColIdx = 0;
        
        $colLetter = chr(65 + $idColIdx);
        $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $this->spreadsheetId . '/values/' . urlencode($table) . '!' . $colLetter . ':' . $colLetter;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $res = curl_exec($ch);
        @curl_close($ch);
        
        $data = json_decode($res, true);
        $values = $data['values'] ?? [];
        
        for ($i = 1; $i < count($values); $i++) {
            $rowId = $values[$i][0] ?? '';
            if (strval($rowId) === strval($id)) {
                return $i + 1;
            }
        }
        return -1;
    }

    private function updateRowInSheets($table, $id, $rowData) {
        if (!$this->spreadsheetId) return;
        try {
            $accessToken = $this->getAccessToken();
            $rowIndex = $this->findRowIndexById($table, $id, $accessToken);
            if ($rowIndex === -1) return;

            $headers = $this->getSheetHeaders($table, $accessToken);
            
            $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $this->spreadsheetId . '/values/' . urlencode($table) . '!A' . $rowIndex . ':Z' . $rowIndex;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $res = curl_exec($ch);
            @curl_close($ch);
            $oldRowData = json_decode($res, true);
            $oldValues = $oldRowData['values'][0] ?? [];

            $rowValues = [];
            foreach ($headers as $idx => $h) {
                if (isset($rowData[$h])) {
                    $rowValues[] = $rowData[$h];
                } else {
                    $rowValues[] = $oldValues[$idx] ?? '';
                }
            }

            $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $this->spreadsheetId . '/values/' . urlencode($table) . '!A' . $rowIndex . '?valueInputOption=USER_ENTERED';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['values' => [$rowValues]]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_exec($ch);
            @curl_close($ch);
        } catch (Exception $e) {
            error_log("Gagal update Google Sheets: " . $e->getMessage());
        }
    }

    private function getSheetIdByName($tableName, $accessToken) {
        $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $this->spreadsheetId . '?fields=sheets(properties(sheetId,title))';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $res = curl_exec($ch);
        @curl_close($ch);
        
        $data = json_decode($res, true);
        $sheets = $data['sheets'] ?? [];
        foreach ($sheets as $s) {
            $title = $s['properties']['title'] ?? '';
            if ($title === $tableName) {
                return $s['properties']['sheetId'] ?? null;
            }
        }
        return null;
    }

    private function deleteRowInSheets($table, $id) {
        if (!$this->spreadsheetId) return;
        try {
            $accessToken = $this->getAccessToken();
            $rowIndex = $this->findRowIndexById($table, $id, $accessToken);
            if ($rowIndex === -1) return;

            $sheetId = $this->getSheetIdByName($table, $accessToken);
            if ($sheetId === null) return;

            $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $this->spreadsheetId . ':batchUpdate';
            $payload = [
                'requests' => [
                    [
                        'deleteDimension' => [
                            'range' => [
                                'sheetId' => $sheetId,
                                'dimension' => 'ROWS',
                                'startIndex' => $rowIndex - 1,
                                'endIndex' => $rowIndex
                            ]
                        ]
                    ]
                ]
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_exec($ch);
            @curl_close($ch);
        } catch (Exception $e) {
            error_log("Gagal delete dari Google Sheets: " . $e->getMessage());
        }
    }

    public function onWriteExecute($query, $params, $stmt) {
        $table = $this->getTableName($query);
        if (!$table) return;

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['needs_sync'] = true;
        }

        if (stripos($query, 'insert') === 0) {
            $db = $this->conn ?: $this->getSQLiteConnection();
            $lastId = $db->lastInsertId();
            if ($lastId) {
                $row = $db->query("SELECT * FROM `$table` WHERE id = $lastId")->fetch();
                if ($row) {
                    $this->appendRowToSheets($table, $row);
                }
            }
        } elseif (stripos($query, 'update') === 0) {
            $id = $this->extractId($query, $params);
            if ($id) {
                $db = $this->conn ?: $this->getSQLiteConnection();
                $row = $db->query("SELECT * FROM `$table` WHERE id = " . intval($id))->fetch();
                if ($row) {
                    $this->updateRowInSheets($table, $id, $row);
                }
            }
        } elseif (stripos($query, 'delete') === 0) {
            $id = $this->extractId($query, $params);
            if ($id) {
                $this->deleteRowInSheets($table, $id);
            }
        }
    }

    private function getTableName($query) {
        if (preg_match('/insert\s+into\s+`?([a-zA-Z0-9_]+)`?/i', $query, $matches)) {
            return $matches[1];
        }
        if (preg_match('/update\s+`?([a-zA-Z0-9_]+)`?/i', $query, $matches)) {
            return $matches[1];
        }
        if (preg_match('/delete\s+from\s+`?([a-zA-Z0-9_]+)`?/i', $query, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractId($query, $params) {
        if (empty($params)) return null;
        if (is_array($params)) {
            if (isset($params['id'])) return $params['id'];
            if (isset($params[':id'])) return $params[':id'];
            if (count($params) === 1 && isset($params[0])) {
                return $params[0];
            }
            if (preg_match('/where\s+id\s*=\s*\?/i', $query)) {
                return end($params);
            }
        }
        return null;
    }
}

class GoogleSheetsPDO extends PDO {
    private $syncInstance;
    
    public function __construct($dsn, $syncInstance) {
        parent::__construct($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $this->syncInstance = $syncInstance;
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [GoogleSheetsPDOStatement::class, [$this->syncInstance]]);
        @$this->sqliteCreateFunction('MONTH', function($date) {
            if (!$date) return null;
            return (int)date('m', strtotime($date));
        });
        @$this->sqliteCreateFunction('YEAR', function($date) {
            if (!$date) return null;
            return (int)date('Y', strtotime($date));
        });
    }
}

class GoogleSheetsPDOStatement extends PDOStatement {
    private $syncInstance;
    
    protected function __construct($syncInstance) {
        $this->syncInstance = $syncInstance;
    }
    
    public function execute(?array $params = null): bool {
        $res = parent::execute($params);
        if ($res) {
            try {
                $this->syncInstance->onWriteExecute($this->queryString, $params, $this);
            } catch (Exception $e) {
                error_log("Sync post error: " . $e->getMessage());
            }
        }
        return $res;
    }
}

// Inisialisasi Database
date_default_timezone_set('Asia/Jakarta');

if (getenv('VERCEL') || DIRECTORY_SEPARATOR === '/') {
    $sqlite_db_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rekapit_cache.sqlite';
} else {
    $sqlite_db_path = __DIR__ . '/../database/rekapit_cache.sqlite';
}

$google_spreadsheet_id = getenv('GOOGLE_SPREADSHEET_ID') ?: '';
$google_sheet_credentials_path = __DIR__ . '/service-account.json';

// Fallback jika file credentials tidak ada di config/, cari file rekapit-*.json di root folder
if (!file_exists($google_sheet_credentials_path)) {
    $root_credentials = glob(dirname(__DIR__) . '/rekapit-*.json');
    if (!empty($root_credentials)) {
        $google_sheet_credentials_path = $root_credentials[0];
    }
}

$sync = new GoogleSheetsSync($sqlite_db_path, $google_spreadsheet_id, $google_sheet_credentials_path);

// Check if manual sync is requested via GET parameter to bypass serverless container isolation
if (isset($_GET['sync_now']) && $_GET['sync_now'] === '1') {
    try {
        $sync->ensureInitialized(true, true);
        
        // Redirect back to clean the URL
        $cleanUrl = strtok($_SERVER['REQUEST_URI'], '?');
        $params = $_GET;
        unset($params['sync_now']);
        $params['sync_status'] = 'success';
        $queryString = http_build_query($params);
        
        header('Location: ' . $cleanUrl . ($queryString ? '?' . $queryString : ''));
        exit();
    } catch (Exception $e) {
        die("Sinkronisasi gagal: " . htmlspecialchars($e->getMessage()));
    }
} else {
    $sync->ensureInitialized(false, true);
}

try {
    $conn = new GoogleSheetsPDO("sqlite:" . $sqlite_db_path, $sync);
    $sync->setConnection($conn);
} catch (PDOException $e) {
    error_log("Koneksi SQLite Gagal: " . $e->getMessage());
    die("Koneksi database gagal: " . htmlspecialchars($e->getMessage()));
}

?>
