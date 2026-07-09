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
echo "3. Testing upload via Google Apps Script Web App...\n";
$webAppUrl = getGoogleSheetsWebAppUrl();
echo "Web App URL: " . $webAppUrl . "\n\n";

if (empty($webAppUrl) || strpos($webAppUrl, 'script.google.com') === false) {
    echo "ERROR: Web App URL is not configured in database.php!\n";
    exit;
}

$payload = [
    'action' => 'uploadFile',
    'filename' => 'rekapit_webapp_test_' . time() . '.txt',
    'mimeType' => 'text/plain',
    'fileContent' => base64_encode('This is a test upload via the Google Apps Script Web App.'),
    'folderId' => $userFolderId
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webAppUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$uploadRes = curl_exec($ch);
$uploadCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$uploadErr = curl_error($ch);
@curl_close($ch);

echo "Web App Response Code: " . $uploadCode . "\n";
echo "Web App Error (if any): " . $uploadErr . "\n";
echo "Web App Response Body:\n" . $uploadRes . "\n\n";

$result = json_decode($uploadRes, true);
if (isset($result['success']) && $result['success'] === true && isset($result['url'])) {
    echo "SUCCESS! Web App file upload succeeded!\nUploaded file URL: " . $result['url'] . "\n";
} else {
    echo "FAILED! Web App file upload failed. Please verify that you have deployed the correct code and set permissions to 'Anyone' on Google Apps Script.\n";
}
