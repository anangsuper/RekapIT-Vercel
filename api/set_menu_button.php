<?php
$token = '8763843693:AAF3istmrlhwuvCTXSM7XlU1FhTXcwlYG5s';
$url = "https://api.telegram.org/bot" . $token . "/setChatMenuButton";

$menuButton = [
    'type' => 'web_app',
    'text' => '➕ Tambah Aset',
    'web_app' => [
        'url' => 'https://rekap-it-vercel-txjt.vercel.app/api/telegram_add_asset.php'
    ]
];

$postData = [
    'menu_button' => json_encode($menuButton)
];

$options = [
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($postData),
        'timeout' => 5
    ]
];

$res = file_get_contents($url, false, stream_context_create($options));
echo $res;
