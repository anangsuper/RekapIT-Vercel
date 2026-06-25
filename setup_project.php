1 <?php
    2 // Script untuk membuat struktur folder dan file otomatis
    3 $folders = ['assets/css', 'assets/js', 'config', 'controllers', 'models', 'views', 'database', 'uploads'];
    4 foreach ($folders as $folder) {
    5     if (!is_dir($folder)) {
    6         mkdir($folder, 0777, true);
    7         echo "Folder created: $folder <br>";
    8     }
    9 }
   10
   11 // 1. File Database
   12 $sql = "CREATE DATABASE IF NOT EXISTS rekap_it;
   13 USE rekap_it;
   14 CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, nama VARCHAR(100), username VARCHAR(50) UNIQUE, password VARCHAR(255), role ENUM('admin',
      'teknisi') DEFAULT 'teknisi');
   15 INSERT INTO users (nama, username, password, role) VALUES ('Admin', 'admin', '" . password_hash('admin123', PASSWORD_BCRYPT) . "', 'admin');";
   16 file_put_contents('database/rekap_it.sql', $sql);
   17
   18 // 2. File Config
   19 $config = "<?php
   20 \$host = 'localhost'; \$dbname = 'rekap_it'; \$username = 'root'; \$password = '';
   21 try { \$conn = new PDO(\"mysql:host=\$host;dbname=\$dbname;charset=utf8\", \$username, \$password); \$conn->setAttribute(PDO::ATTR_ERRMODE,
      PDO::ERRMODE_EXCEPTION); }
   22 catch(PDOException \$e) { die(\"Koneksi gagal: \" . \$e->getMessage()); }";
   23 file_put_contents('config/database.php', $config);
   24
   25 // 3. File Index.php
   26 $index = "<?php session_start(); require_once 'config/database.php'; \$page = isset(\$_GET['page']) ? \$_GET['page'] : 'dashboard';
   27 if (!isset(\$_SESSION['user_id']) && \$page != 'login') { header('Location: login.php'); exit(); }
   28 echo '<h1>Selamat Datang di Halaman ' . ucfirst(\$page) . '</h1>';";
   29 file_put_contents('index.php', $index);
   30
   31 echo "<h2>Semua file dan folder berhasil dibuat otomatis!</h2>";
   32 ?>