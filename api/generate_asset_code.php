<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

// Restore session global
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$kategori_id = isset($_GET['kategori_id']) ? intval($_GET['kategori_id']) : 0;
if ($kategori_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Kategori ID tidak valid']);
    exit();
}

try {
    // Get category name
    $catStmt = $conn->prepare("SELECT nama_kategori FROM kategori WHERE id = ?");
    $catStmt->execute([$kategori_id]);
    $kategori = $catStmt->fetch();
    
    if (!$kategori) {
        echo json_encode(['success' => false, 'error' => 'Kategori tidak ditemukan']);
        exit();
    }
    
    // Generate a 3-letter uppercase prefix (e.g. LAPTOP -> LAP, PRINTER -> PRN)
    $cleanName = preg_replace('/[^a-zA-Z]/', '', $kategori['nama_kategori']);
    $prefix = strtoupper(substr($cleanName, 0, 3));
    if (strlen($prefix) < 3) {
        $prefix = str_pad($prefix, 3, 'X');
    }
    
    // Query last asset with matching prefix
    $stmt = $conn->prepare("SELECT kode_aset FROM assets WHERE kode_aset LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '-%']);
    $lastAsset = $stmt->fetch();
    
    $nextNum = 1;
    if ($lastAsset) {
        $parts = explode('-', $lastAsset['kode_aset']);
        if (count($parts) >= 2) {
            $lastNum = intval($parts[1]);
            $nextNum = $lastNum + 1;
        }
    }
    
    $newCode = $prefix . '-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
    
    echo json_encode(['success' => true, 'code' => $newCode]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
