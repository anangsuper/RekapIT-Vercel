<?php

/**
 * Konfigurasi Database - Rekap IT
 * Mendukung environment Lokal (XAMPP) dan Production (Railway)
 */

// 1. Ambil variabel dari Environment Railway (prioritas) atau default Lokal
$db_host = getenv('DB_HOST')     ?: getenv('MYSQLHOST')     ?: '127.0.0.1';
$db_port = getenv('DB_PORT')     ?: getenv('MYSQLPORT')     ?: '3306';
$db_user = getenv('DB_USERNAME') ?: getenv('MYSQLUSER')     ?: 'root';
$db_pass = getenv('DB_PASSWORD') ?: getenv('MYSQLPASSWORD') ?: '';
$db_name = getenv('DB_DATABASE') ?: getenv('MYSQLDATABASE') ?: 'rekap_it';

// 2. Validasi: Jika sedang di Railway tapi variabel penting kosong
if (getenv('RAILWAY_ENVIRONMENT') && empty($db_host)) {
    die("Error: Variabel environment database belum dikonfigurasi di Railway.");
}

/**
 * OPSI 1: MENGGUNAKAN PDO (Direkomendasikan)
 */
try {
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
    $conn = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
} catch (PDOException $e) {
    // Log error secara aman (jangan tampilkan password di layar production)
    error_log("Koneksi PDO Gagal: " . $e->getMessage());
    die("Koneksi database gagal. Silakan cek log server.");
}

/**
 * OPSI 2: MENGGUNAKAN MySQLi (Alternatif)
 * Jika Anda ingin menggunakan MySQLi, hapus komentar di bawah ini:
 */
/*
$conn_mysqli = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
if (!$conn_mysqli) {
    error_log("Koneksi MySQLi Gagal: " . mysqli_connect_error());
    die("Koneksi database (MySQLi) gagal.");
}
mysqli_set_charset($conn_mysqli, "utf8mb4");
*/

?>
