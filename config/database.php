<?php

/**
 * Konfigurasi Database - Rekap IT (PostgreSQL Production Optimized)
 */

$dbUri = getenv('DATABASE_URL');

if (!$dbUri) {
    die("Error: DATABASE_URL tidak ditemukan.");
}

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
    
    // Koneksi ke PostgreSQL dengan verifikasi SSL
    // Menggunakan verify-full untuk keamanan maksimal
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=verify-full;sslrootcert=$caCertPath";
    
    $conn = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // Menampilkan pesan error detail untuk debugging
    die("Koneksi gagal! Detail error: " . $e->getMessage());
}
?>
