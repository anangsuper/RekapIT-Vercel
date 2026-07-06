<?php
ob_start();
session_start();
require_once __DIR__ . '/config/database.php';

// Kosongkan $_SESSION dan $_COOKIE superglobal
$_SESSION = [];
if (isset($_COOKIE['REKAPIT_SESSION'])) {
    unset($_COOKIE['REKAPIT_SESSION']);
}

// Hapus cookie session
$isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
if (!headers_sent()) {
    setcookie('REKAPIT_SESSION', '', time() - 3600, '/', '', $isSecure, true);
}



if (function_exists('save_session_to_cookie')) {
    save_session_to_cookie();
}

$reason = isset($_GET['reason']) ? '?reason=' . urlencode($_GET['reason']) : '?logged_out=1';

$base_path = '/';
if (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1')) {
    $script_name = $_SERVER['SCRIPT_NAME'];
    $base_dir = str_replace(basename($script_name), '', $script_name);
    $base_dir = str_replace('/api/', '/', $base_dir);
    $base_path = '/' . trim($base_dir, '/') . '/';
    if ($base_path === '//') $base_path = '/';
}

header('Location: ' . $base_path . 'login.php' . $reason);
exit();
?>
