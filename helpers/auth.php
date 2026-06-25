<?php
/**
 * Mengecek apakah user memiliki peran yang diizinkan.
 * @param array|string $allowedRoles Peran yang diizinkan (misal: 'admin' atau ['admin', 'teknisi'])
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
 */
function checkAccess($allowedRoles) {
    if (!hasRole($allowedRoles)) {
        // Redirect ke dashboard atau halaman error jika tidak punya akses
        header('Location: index.php?page=dashboard&error=unauthorized');
        exit();
    }
}
?>