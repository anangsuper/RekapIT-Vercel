<?php
ob_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/pagination.php'; // Include pagination helper
require_once __DIR__ . '/helpers/ui.php'; // Include UI helper

// Inactivity timeout: 24 hours (86400 seconds)
if (isset($_SESSION['user_id'])) {
    $now = time();
    $expire_time = 86400; // 24 jam
    if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity'] > $expire_time)) {
        header("Location: logout.php?reason=timeout");
        exit();
    }
    $_SESSION['last_activity'] = $now;
}

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
    'audit', 'cabang', 'divisi', 'inventaris', 'karyawan', 'kategori', 'laporan', 'logs', 'mutasi', 'pengguna', 'cetak_kartu'
];

if (in_array($page, $restrictedPages)) {
    checkAccess('admin');
}

include __DIR__ . '/views/header.php';

if (file_exists($file)) {
    include $file;
} else {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Halaman [$page] tidak ditemukan!</div></div>";
}

include __DIR__ . '/views/footer.php';
if (function_exists('save_session_to_cookie')) {
    save_session_to_cookie();
}
ob_end_flush();
?>
