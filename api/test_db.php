<?php
define('SKIP_DB_SYNC', true);
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

try {
    $stmt = $conn->query("SELECT * FROM assets");
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $metaFile = $sqlite_db_path . '.json';
    $meta = file_exists($metaFile) ? json_decode(file_get_contents($metaFile), true) : null;

    echo json_encode([
        'status' => 'OK',
        'spreadsheet_id' => $google_spreadsheet_id,
        'env_id' => getenv('GOOGLE_SPREADSHEET_ID'),
        'last_sync' => $meta ? $meta['last_sync'] : null,
        'assets_count' => count($assets),
        'assets' => $assets
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
