<?php
ob_start();
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/pagination.php'; // Include pagination helper

if (!isset($_SESSION['user_id'])) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    if ($currentPage != 'login.php' && !isset($_GET['page'])) {
        header('Location: login.php');
        exit();
    }
}

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$file = __DIR__ . '/views/' . $page . '.php';

// Restricted pages for teknisi
$restrictedPages = [
    'audit', 'cabang', 'divisi', 'inventaris', 'karyawan', 'kategori', 'laporan', 'logs', 'mutasi', 'pengguna'
];

if (hasRole('teknisi') && in_array($page, $restrictedPages)) {
    header('Location: index.php?page=dashboard');
    exit();
}

include __DIR__ . '/views/header.php';

if (file_exists($file)) {
    include $file;
} else {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Halaman [$page] tidak ditemukan!</div></div>";
}

include __DIR__ . '/views/footer.php';
ob_end_flush();
?>
