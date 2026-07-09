<?php
header('Content-Type: text/plain; charset=UTF-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../helpers/drive_upload.php';

echo "--- REKAPIT GOOGLE DRIVE API DIAGNOSTIC SCRIPT ---\n\n";

echo "1. Checking GOOGLE_SERVICE_ACCOUNT_JSON...\n";
$credsJson = getenv('GOOGLE_SERVICE_ACCOUNT_JSON');
if (!$credsJson) {
    $credentialsPath = __DIR__ . '/../config/service-account.json';
    if (!file_exists($credentialsPath)) {
        $root_credentials = glob(dirname(__DIR__) . '/rekapit-*.json');
        if (!empty($root_credentials)) {
            $credentialsPath = $root_credentials[0];
        }
    }
    if (file_exists($credentialsPath)) {
        echo "Found credential file: " . basename($credentialsPath) . "\n";
        $creds = json_decode(file_get_contents($credentialsPath), true);
    } else {
        echo "ERROR: Credentials JSON not found in environment variable or config folder!\n";
        exit;
    }
} else {
    echo "Found credentials in environment variable.\n";
    $creds = json_decode($credsJson, true);
}

if (!$creds) {
    echo "ERROR: Failed to parse credentials JSON!\n";
    exit;
}

echo "Client Email: " . ($creds['client_email'] ?? 'Not found') . "\n";
echo "Private Key: " . (isset($creds['private_key']) ? "Found (length: " . strlen($creds['private_key']) . ")" : 'Not found') . "\n\n";

echo "2. Requesting Google Drive Access Token...\n";
$token = getDriveAccessToken();
if ($token) {
    echo "SUCCESS: Access token generated successfully!\nToken prefix: " . substr($token, 0, 15) . "...\n\n";
} else {
    echo "ERROR: Failed to generate access token!\n\n";
    exit;
}

echo "3. Testing direct raw API call to Google Drive (files.list)...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/drive/v3/files?pageSize=1');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
@curl_close($ch);

echo "Google Drive API Response Code: " . $code . "\n";
echo "Response Body:\n" . $res . "\n\n";

echo "4. Attempting dummy file upload to Google Drive root...\n";
$tempFile = sys_get_temp_dir() . '/rekapit_test_upload.txt';
file_put_contents($tempFile, 'This is a diagnostic file created by RekapIT to test Google Drive API.');

$fileName = 'rekapit_diagnostic_' . time() . '.txt';
$uploadedUrl = uploadFileToGoogleDrive($token, $tempFile, 'text/plain', $fileName, '');

if ($uploadedUrl) {
    echo "SUCCESS: Dummy file uploaded successfully!\nURL: " . $uploadedUrl . "\n";
} else {
    echo "ERROR: Dummy file upload failed!\n";
}

@unlink($tempFile);
