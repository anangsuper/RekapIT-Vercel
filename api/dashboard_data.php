<?php
define('SKIP_DB_SYNC', true);
// Dashboard Data API
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

try {
    // 1. Data per Cabang
    $stmtBranch = $conn->query("SELECT c.nama_cabang, COUNT(a.id) as total 
                                FROM cabang c 
                                LEFT JOIN assets a ON c.id = a.id_cabang 
                                GROUP BY c.id");
    $branchData = $stmtBranch->fetchAll(PDO::FETCH_ASSOC);

    // 2. Data Kondisi
    $stmtCondition = $conn->query("SELECT kondisi, COUNT(*) as total 
                                   FROM assets 
                                   GROUP BY kondisi");
    $conditionData = $stmtCondition->fetchAll(PDO::FETCH_ASSOC);

    $stmtCosts = $conn->query("SELECT strftime('%Y-%m', tanggal_selesai) as bulan, SUM(biaya) as total 
                               FROM repairs 
                               WHERE status = 'Selesai' 
                               AND tanggal_selesai >= date('now', '-6 month')
                               GROUP BY bulan 
                               ORDER BY bulan ASC");
    $costsData = $stmtCosts->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'branch' => $branchData,
        'condition' => $conditionData,
        'costs' => $costsData
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
