<?php
// Auto-forward QR Scan dari stiker fisik lama ke sistem Maintenance-QR baru
$qs = $_SERVER['QUERY_STRING'] ?? '';
header("Location: https://maintenance-qr.vercel.app/scan" . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
