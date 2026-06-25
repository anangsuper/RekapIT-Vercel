<?php

/**
 * Konfigurasi Database - Rekap IT (PostgreSQL Version for Vercel)
 */

$dbUri = getenv('DATABASE_URL');

if (!$dbUri) {
    die("Error: DATABASE_URL tidak ditemukan.");
}

$url = parse_url($dbUri);

$host = $url["host"];
$port = $url["port"];
$db   = ltrim($url["path"], "/");
$user = $url["user"];
$pass = $url["pass"];

try {
    // Path ke sertifikat CA
    $caCertPath = __DIR__ . '/ca-certificate.crt';
    
    // Koneksi ke PostgreSQL dengan verifikasi SSL
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=verify-full;sslrootcert=$caCertPath";
    
    $conn = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log("Koneksi PDO (PostgreSQL) Gagal: " . $e->getMessage());
    die("Koneksi database gagal.");
}
?>
