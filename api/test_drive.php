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

$serviceAccountEmail = $creds['client_email'] ?? '';
echo "Client Email: " . $serviceAccountEmail . "\n";
echo "Private Key: " . (isset($creds['private_key']) ? "Found (length: " . strlen($creds['private_key']) . ")" : 'Not found') . "\n\n";

echo "2. Requesting Google Drive Access Token...\n";
$token = getDriveAccessToken();
if ($token) {
    echo "SUCCESS: Access token generated successfully!\n\n";
} else {
    echo "ERROR: Failed to generate access token!\n\n";
    exit;
}

$userFolderId = '1MtZ30lUjmEJ29ynpduUYU0hOd-amqnXd';
echo "3. Testing upload to user shared folder: " . $userFolderId . "...\n";
echo "Make sure you have shared this folder to: " . $serviceAccountEmail . " as EDITOR!\n\n";

$metadata = [
    'name' => 'rekapit_shared_folder_test.txt',
    'parents' => [$userFolderId]
];
$boundary = '-------314159265358979323846';
$fileContent = 'This is a test upload directly inside your shared Google Drive folder.';
$body = "--" . $boundary . "\r\n" .
        "Content-Type: application/json; charset=UTF-8\r\n\r\n" .
        json_encode($metadata) . "\r\n" .
        "--" . $boundary . "\r\n" .
        "Content-Type: text/plain\r\n\r\n" .
        $fileContent . "\r\n" .
        "--" . $boundary . "--";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: multipart/related; boundary=' . $boundary,
    'Content-Length: ' . strlen($body)
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$uploadRes = curl_exec($ch);
$uploadCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$uploadErr = curl_error($ch);
@curl_close($ch);

echo "Upload to Shared Folder HTTP Code: " . $uploadCode . "\n";
echo "Upload to Shared Folder Error (if any): " . $uploadErr . "\n";
echo "Upload to Shared Folder Response Body:\n" . $uploadRes . "\n\n";

if ($uploadCode === 200) {
    echo "SUCCESS! Upload to shared folder succeeded!\n";
} else {
    echo "FAILED! Upload to shared folder failed. Please check if you have shared the folder with Editor permissions to " . $serviceAccountEmail . ".\n";
}
