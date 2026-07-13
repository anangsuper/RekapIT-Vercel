<?php
require_once __DIR__ . '/../config/database.php';

$sync = new GoogleSheetsSync($sqlite_db_path, $google_spreadsheet_id, $google_sheet_credentials_path);

try {
    echo "Using Spreadsheet ID: " . $google_spreadsheet_id . "\n";
    $accessToken = $sync->getAccessToken();
    echo "Access Token retrieved successfully.\n";

    // Test batchGet
    $tables = ["assets", "cabang", "users"];
    $queryParams = [];
    foreach ($tables as $t) {
        $queryParams[] = 'ranges=' . urlencode($t . '!A:Z');
    }
    $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $google_spreadsheet_id . '/values:batchGet?' . implode('&', $queryParams);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Status Code: " . $httpCode . "\n";
    if ($httpCode === 200) {
        $resData = json_decode($response, true);
        echo "Response data keys: " . implode(', ', array_keys($resData)) . "\n";
        if (isset($resData['valueRanges'])) {
            foreach ($resData['valueRanges'] as $vr) {
                echo "Range: " . $vr['range'] . "\n";
                $values = $vr['values'] ?? [];
                echo "Number of rows: " . count($values) . "\n";
                if (count($values) > 0) {
                    echo "Headers: " . implode(', ', $values[0]) . "\n";
                    echo "First row data: " . implode(', ', $values[1] ?? []) . "\n";
                }
                echo "---------------------------------\n";
            }
        }
    } else {
        echo "Response: " . $response . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
