<?php
define('ROLE_ADMIN', 'admin');
define('ROLE_TEKNISI', 'teknisi');

/**
 * Mengecek apakah user memiliki peran yang diizinkan.
 * @param array|string $allowedRoles Peran yang diizinkan (misal: ROLE_ADMIN atau [ROLE_ADMIN, ROLE_TEKNISI])
 * @return bool
 */
function hasRole($allowedRoles) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    if (is_array($allowedRoles)) {
        return in_array($_SESSION['role'], $allowedRoles);
    }
    
    return $_SESSION['role'] === $allowedRoles;
}

/**
 * Membatasi akses ke halaman tertentu jika peran tidak sesuai.
 * @param array|string $allowedRoles
 * @param string|null $redirectPage Halaman untuk redirect jika akses ditolak (default: dashboard)
 */
function checkAccess($allowedRoles, $redirectPage = 'dashboard') {
    if (!hasRole($allowedRoles)) {
        // Log unauthorized access attempt if needed
        header('Location: index.php?page=' . $redirectPage . '&error=unauthorized');
        exit();
    }
}
?>