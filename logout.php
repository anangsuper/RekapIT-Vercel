<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userRoleBefore = $_SESSION['role'] ?? '';
$redirectTarget = isset($_GET['redirect']) ? $_GET['redirect'] : '';

// 1. Clear session array in memory
$_SESSION = array();

// 2. Delete native PHP session cookie (PHPSESSID)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Delete stateless REKAPIT_SESSION cookie
$isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
setcookie('REKAPIT_SESSION', '', time() - 86400, '/', '', $isSecure, true);
if (isset($_COOKIE['REKAPIT_SESSION'])) {
    unset($_COOKIE['REKAPIT_SESSION']);
}

// 4. Destroy PHP session
@session_destroy();

// 5. Calculate base path
$base_path = '/';
if (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1')) {
    $script_name = $_SERVER['SCRIPT_NAME'];
    $base_dir = str_replace(basename($script_name), '', $script_name);
    $base_dir = str_replace('/api/', '/', $base_dir);
    $base_path = '/' . trim($base_dir, '/') . '/';
    if ($base_path === '//') $base_path = '/';
}

// 6. Redirect based on role / target
if ($redirectTarget === 'helpdesk' || $userRoleBefore === 'karyawan') {
    header('Location: ' . $base_path . 'helpdesk_login.php?logged_out=1');
} else {
    header('Location: ' . $base_path . 'login.php?logged_out=1');
}
exit();
?>
