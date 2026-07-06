<?php
// API Endpoint for Manual Sync
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Check user login session or valid webhook token
$valid_token = getenv('WEBHOOK_TOKEN') ?: 'rekap_it_sync_secret_token_123';
$input_token = isset($_GET['token']) ? $_GET['token'] : '';

if (!isset($_SESSION['user_id']) && ($input_token === '' || $input_token !== $valid_token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Force sync pull from Google Sheets
    $sync->ensureInitialized(true, true);
    
    echo json_encode([
        'success' => true,
        'message' => 'Sinkronisasi berhasil dilakukan.',
        'last_sync' => time()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
