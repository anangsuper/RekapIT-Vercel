<?php
// Script untuk membuat struktur folder dan file otomatis
$folders = ['assets/css', 'assets/js', 'config', 'controllers', 'models', 'views', 'database', 'uploads'];
foreach ($folders as $folder) {
    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
        echo "Folder created: $folder <br>";
    }
}

// 1. File Database
$sql = "CREATE DATABASE IF NOT EXISTS rekapit_momenthelp;
USE rekapit_momenthelp;
CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, nama VARCHAR(100), username VARCHAR(50) UNIQUE, password VARCHAR(255), role ENUM('admin', 'teknisi') DEFAULT 'teknisi');
INSERT INTO users (nama, username, password, role) VALUES ('Admin', 'admin', '" . password_hash('admin123', PASSWORD_BCRYPT) . "', 'admin');";
file_put_contents('database/rekap_it.sql', $sql);

// 2. File Config
$config = "<?php
\$host = 'localhost'; \$dbname = 'rekapit_momenthelp'; \$username = 'root'; \$password = '';
try { \$conn = new PDO(\"mysql:host=\$host;dbname=\$dbname;charset=utf8\", \$username, \$password); \$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
catch(PDOException \$e) { die(\"Koneksi gagal: \" . \$e->getMessage()); }";
file_put_contents('config/database.php', $config);

// 3. File Index.php
$index = "<?php session_start(); require_once 'config/database.php'; \$page = isset(\$_GET['page']) ? \$_GET['page'] : 'dashboard';
if (!isset(\$_SESSION['user_id']) && \$page != 'login') { header('Location: login.php'); exit(); }
echo '<h1>Selamat Datang di Halaman ' . ucfirst(\$page) . '</h1>';";
file_put_contents('index.php', $index);

echo "<h2>Semua file dan folder berhasil dibuat otomatis!</h2>";
?>