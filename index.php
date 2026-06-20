<?php
ob_start();
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/auth.php'; // Memastikan helper dimuat sebelum header

if (!isset($_SESSION['user_id'])) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    if ($currentPage != 'login.php' && !isset($_GET['page'])) {
        header('Location: login.php');
        exit();
    }
}

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$file = __DIR__ . '/views/' . $page . '.php';

include __DIR__ . '/views/header.php';

if (file_exists($file)) {
    include $file;
} else {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Halaman [$page] tidak ditemukan!</div></div>";
}

include __DIR__ . '/views/footer.php';
ob_end_flush();
?>
