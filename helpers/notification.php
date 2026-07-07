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
