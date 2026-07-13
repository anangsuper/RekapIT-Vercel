<?php

function sendTelegramNotification($message) {
    // Robust environment variables loading (compatible with getenv, $_ENV, and $_SERVER)
    $token = getenv('TELEGRAM_BOT_TOKEN') ?: ($_ENV['TELEGRAM_BOT_TOKEN'] ?? ($_SERVER['TELEGRAM_BOT_TOKEN'] ?? ''));
    $chatId = getenv('TELEGRAM_CHAT_ID') ?: ($_ENV['TELEGRAM_CHAT_ID'] ?? ($_SERVER['TELEGRAM_CHAT_ID'] ?? ''));

    if (empty($token) || empty($chatId)) {
        error_log("Telegram Notifier: Token or Chat ID is missing.");
        return false;
    }

    $url = "https://api.telegram.org/bot" . $token . "/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];

    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data),
            'timeout' => 5 // Max 5 seconds timeout to prevent thread blocking
        ],
        // Disable SSL peer verification to bypass missing CA bundle issues on serverless PHP
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ];

    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        error_log("Telegram Notifier: Failed to send request to Telegram API.");
    }
    
    return $result !== false;
}

function getDeviceDetails() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $os = 'Unknown OS';
    if (preg_match('/windows|win32/i', $userAgent)) {
        $os = 'Windows';
    } elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
        $os = 'macOS';
    } elseif (preg_match('/android/i', $userAgent)) {
        $os = 'Android';
    } elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
        $os = 'iOS';
    } elseif (preg_match('/linux/i', $userAgent)) {
        $os = 'Linux';
    }

    $browser = 'Unknown Browser';
    if (preg_match('/edge/i', $userAgent)) {
        $browser = 'Microsoft Edge';
    } elseif (preg_match('/chrome/i', $userAgent)) {
        $browser = 'Google Chrome';
    } elseif (preg_match('/firefox/i', $userAgent)) {
        $browser = 'Mozilla Firefox';
    } elseif (preg_match('/safari/i', $userAgent)) {
        $browser = 'Apple Safari';
    } elseif (preg_match('/opera/i', $userAgent)) {
        $browser = 'Opera';
    }
    
    return "$browser ($os)";
}

function getClientIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return trim($ip);
}
