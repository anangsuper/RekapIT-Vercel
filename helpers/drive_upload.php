<?php
/**
 * Generate Google Drive Access Token using Google Service Account
 */
function getDriveAccessToken() {
    $credentialsPath = __DIR__ . '/../config/service-account.json';
    if (!file_exists($credentialsPath)) {
        $root_credentials = glob(dirname(__DIR__) . '/rekapit-*.json');
        if (!empty($root_credentials)) {
            $credentialsPath = $root_credentials[0];
        }
    }

    $creds = null;
    if (file_exists($credentialsPath)) {
        $creds = json_decode(file_get_contents($credentialsPath), true);
    } elseif (getenv('GOOGLE_SERVICE_ACCOUNT_JSON')) {
        $creds = json_decode(getenv('GOOGLE_SERVICE_ACCOUNT_JSON'), true);
    }
    
    if (!$creds || !isset($creds['private_key']) || !isset($creds['client_email'])) {
        error_log("Google Drive Token Exchange: Credentials not found.");
        return false;
    }
    
    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $now = time();
    $payload = json_encode([
        'iss' => $creds['client_email'],
        'scope' => 'https://www.googleapis.com/auth/drive.file https://www.googleapis.com/auth/drive',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ]);
    
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    $signatureInput = $base64UrlHeader . "." . $base64UrlPayload;
    
    $privateKey = $creds['private_key'];
    $signature = '';
    if (!openssl_sign($signatureInput, $signature, $privateKey, 'SHA256')) {
        error_log("Google Drive Token Exchange: Signature generation failed.");
        return false;
    }
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    $jwt = $signatureInput . "." . $base64UrlSignature;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = curl_exec($ch);
    
    if ($response) {
        $resData = json_decode($response, true);
        return $resData['access_token'] ?? false;
    }
    return false;
}

/**
 * Upload local file directly to Google Drive via cURL multipart API
 */
function uploadFileToGoogleDrive($accessToken, $filePath, $mimeType, $fileName, $targetFolderId = '') {
    $folderId = $targetFolderId;
    
    $metadata = ['name' => $fileName];
    if (!empty($folderId)) {
        $metadata['parents'] = [$folderId];
    }
    
    $boundary = '-------314159265358979323846';
    $delimiter = "\r\n--" . $boundary . "\r\n";
    $closeDelimiter = "\r\n--" . $boundary . "--";
    
    $fileData = file_get_contents($filePath);
    
    $body = $delimiter . 'Content-Type: application/json; charset=UTF-8' . "\r\n\r\n" . json_encode($metadata) .
            $delimiter . 'Content-Type: ' . $mimeType . "\r\n\r\n" . $fileData .
            $closeDelimiter;
            
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: multipart/related; boundary=' . $boundary,
        'Content-Length: ' . strlen($body)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    
    if (!$response) {
        return false;
    }
    
    $fileInfo = json_decode($response, true);
    $fileId = $fileInfo['id'] ?? null;
    
    if (!$fileId) {
        error_log("Google Drive direct upload failed: " . $response);
        return false;
    }
    
    $permData = [
        'role' => 'reader',
        'type' => 'anyone'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/drive/v3/files/' . $fileId . '/permissions');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($permData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_exec($ch);
    
    return "https://docs.google.com/uc?export=download&id=" . $fileId;
}
