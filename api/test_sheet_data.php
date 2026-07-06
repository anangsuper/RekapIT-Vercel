<?php
// Diagnostic tool to check Google Sheets actual raw data
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$response = [];

try {
    $accessToken = $sync->getAccessToken();
    $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $google_spreadsheet_id . '/values/users!A1:Z100';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $response['http_code'] = $httpCode;
    if ($httpCode === 200) {
        $data = json_decode($res, true);
        $response['range'] = $data['range'] ?? '';
        $response['values'] = $data['values'] ?? [];
        $response['row_count'] = isset($data['values']) ? count($data['values']) : 0;
    } else {
        $response['error_response'] = $res;
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
