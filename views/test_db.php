<?php
define('SKIP_DB_SYNC', true);
header('Content-Type: application/json');

try {
    $stmt = $conn->query("SELECT * FROM assets");
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'OK',
        'assets_count' => count($assets),
        'assets' => $assets
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
exit();
