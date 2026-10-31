<?php
// Auto-forward QR Scan dari stiker fisik lama ke custom domain / sistem baru
$targetDomain = getenv('TARGET_SCAN_DOMAIN') ?: 'maintenance-qr.vercel.app';
$targetDomain = preg_replace('#^https?://#i', '', rtrim($targetDomain, '/'));
$qs = $_SERVER['QUERY_STRING'] ?? '';
header("Location: https://{$targetDomain}/scan" . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
