<?php

/**
 * Konfigurasi Database - Rekap IT (PostgreSQL Production Optimized)
 */

// Mengambil DATABASE_URL dari Environment Variable (diatur di Vercel Dashboard)
$dbUri = getenv('DATABASE_URL');

if (!$dbUri) {
    // Jika tidak ada DATABASE_URL, mungkin kita sedang di lokal/development
    // Bisa tambahkan fallback di sini jika perlu, namun untuk Vercel wajib ada.
    die("Error: DATABASE_URL tidak dikonfigurasi di environment variables.");
}

// Parsing URI: postgres://user:password@host:port/dbname?options
$url = parse_url($dbUri);

if (!$url) {
    die("Error: Format DATABASE_URL tidak valid.");
}

$host = $url["host"] ?? 'localhost';
$port = $url["port"] ?? '5432';
$db   = ltrim($url["path"] ?? '', '/');
$user = $url["user"] ?? '';
$pass = $url["pass"] ?? '';

try {
    // Path ke sertifikat CA
    $caCertPath = __DIR__ . '/ca-certificate.crt';
    
    // Membangun DSN untuk PostgreSQL
    // Kita menggunakan 'verify-full' agar SSL benar-benar divalidasi
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=verify-full;sslrootcert=$caCertPath";
    
    // Opsi tambahan untuk PDO
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $conn = new PDO($dsn, $user, $pass, $options);
    
} catch (PDOException $e) {
    // Menampilkan pesan error yang lebih jelas saat development, 
    // namun di production sebaiknya hanya log.
    error_log("Koneksi PDO (PostgreSQL) Gagal: " . $e->getMessage());
    die("Koneksi database gagal. Periksa konfigurasi dan log server.");
}
?>
