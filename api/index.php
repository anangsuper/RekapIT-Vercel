<?php
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__DIR__));

$uri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($uri, 'dashboard_data.php') !== false) {
    require __DIR__ . '/dashboard_data.php';
    exit;
}
if (strpos($uri, 'generate_asset_code.php') !== false) {
    require __DIR__ . '/generate_asset_code.php';
    exit;
}
if (strpos($uri, 'sync.php') !== false) {
    require __DIR__ . '/sync.php';
    exit;
}
if (strpos($uri, 'health.php') !== false) {
    require __DIR__ . '/health.php';
    exit;
}
if (strpos($uri, 'test_db.php') !== false) {
    require __DIR__ . '/test_db.php';
    exit;
}

require __DIR__ . '/../index.php';
