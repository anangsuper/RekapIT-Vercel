<?php
define('SKIP_DB_SYNC', true);
require_once __DIR__ . '/config/database.php';
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
}
header('Content-Type: application/json');
echo json_encode(['status' => 'OK', 'timestamp' => time()]);
?>
