<?php
/**
 * Helper to upload files to ImgBB
 */
function uploadFileToImgBB($filePath, $fileName) {
    // Get API Key from env or config
    $apiKey = getenv('IMGBB_API_KEY');
    if (!$apiKey) {
        $dbConfigPath = __DIR__ . '/../config/database.php';
        if (file_exists($dbConfigPath)) {
            $content = file_get_contents($dbConfigPath);
            if (preg_match('/\$imgbb_api_key\s*=\s*getenv\(\'IMGBB_API_KEY\'\)\s*\|\|\s*\'([^\']+)\'/i', $content, $m)) {
                $apiKey = $m[1];
            } elseif (preg_match('/\$imgbb_api_key\s*=\s*\'([^\']+)\'/i', $content, $m)) {
                $apiKey = $m[1];
            }
        }
    }
    
    if (empty($apiKey)) {
        error_log("ImgBB API Key is not configured!");
        return false;
    }
    
    $fileData = file_get_contents($filePath);
    $base64Data = base64_encode($fileData);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.imgbb.com/1/upload?key=' . $apiKey);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'image' => $base64Data,
        'name' => $fileName
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    @curl_close($ch);
    
    if ($response) {
        $result = json_decode($response, true);
        if (isset($result['success']) && $result['success'] === true && isset($result['data']['url'])) {
            return $result['data']['url'];
        }
        error_log("ImgBB Upload Error response: " . $response);
    } else {
        error_log("ImgBB Upload request failed (HTTP {$httpCode})");
    }
    
    return false;
}
