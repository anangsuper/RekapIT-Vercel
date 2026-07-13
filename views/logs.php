<?php
require_once 'models/ActivityLog.php';
$logModel = new ActivityLog($conn);

// DIAGNOSTIC CODE
$debugInfo = [];
try {
    $db = $conn;
    // 1. Check if table activity_logs exists
    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='activity_logs'")->fetch();
    $debugInfo['activity_logs_table_exists'] = $tableCheck ? 'Yes' : 'No';
    
    if ($tableCheck) {
        // 2. Check total rows
        $totalRows = $db->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
        $debugInfo['activity_logs_total_rows'] = $totalRows;
        
        // 3. Get table schema
        $schema = $db->query("PRAGMA table_info(activity_logs)")->fetchAll(PDO::FETCH_ASSOC);
        $debugInfo['activity_logs_schema'] = $schema;
        
        // 4. Sample rows
        $sample = $db->query("SELECT * FROM activity_logs LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        $debugInfo['activity_logs_sample'] = $sample;
    }
    
    // 5. Check other tables
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    $debugInfo['all_tables'] = [];
    foreach ($tables as $tbl) {
        $count = $db->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
        $debugInfo['all_tables'][$tbl] = $count;
    }

    // 6. Test Google Sheets connectivity for activity_logs
    $accessToken = $sync->getAccessToken();
    $url = 'https://sheets.googleapis.com/v1/spreadsheets/' . $google_spreadsheet_id . '/values/' . urlencode('activity_logs') . '!A1:Z10';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    @curl_close($ch);
    
    $debugInfo['google_sheets_api'] = [
        'http_code' => $httpCode,
        'response' => json_decode($res, true) ?: $res
    ];
} catch (Exception $e) {
    $debugInfo['error'] = $e->getMessage();
}

$allLogs = $logModel->getRecent(50); // Show more logs on dedicated page
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h2 class="fw-800 text-dark"><i class="bi bi-clock-history me-2 text-primary"></i> Log Aktivitas Sistem</h2>
            <p class="text-muted">Riwayat lengkap aktivitas pengguna dan perubahan sistem.</p>
        </div>
        <div class="col-auto">
            <a href="index.php?page=dashboard" class="btn btn-outline-secondary rounded-pill">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3" style="width: 80px;">Icon</th>
                        <th class="py-3">Aksi</th>
                        <th class="py-3">Deskripsi</th>
                        <th class="py-3">Pengguna</th>
                        <th class="py-3 pe-4 text-end">Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allLogs as $log): 
                        $icon = 'bi-record-circle';
                        $color = 'text-primary';
                        $bg = 'bg-primary';
                        if(strpos(strtolower($log['action']), 'tambah') !== false) { $icon = 'bi-plus-circle'; $color = 'text-success'; $bg = 'bg-success'; }
                        if(strpos(strtolower($log['action']), 'hapus') !== false) { $icon = 'bi-trash'; $color = 'text-danger'; $bg = 'bg-danger'; }
                        if(strpos(strtolower($log['action']), 'login') !== false) { $icon = 'bi-person-check'; $color = 'text-info'; $bg = 'bg-info'; }
                    ?>
                        <tr>
                            <td class="ps-4">
                                <div class="<?= $bg ?> bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <i class="bi <?= $icon ?> <?= $color ?> fs-5"></i>
                                </div>
                            </td>
                            <td>
                                <span class="fw-bold"><?= $log['action'] ?></span>
                            </td>
                            <td>
                                <span class="text-muted small"><?= $log['description'] ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-secondary bg-opacity-10 rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                        <i class="bi bi-person text-secondary small"></i>
                                    </div>
                                    <span class="small fw-semibold"><?= $log['nama'] ?></span>
                                </div>
                            </td>
                            <td class="pe-4 text-end">
                                <div class="small fw-bold text-dark"><?= date('d M Y', strtotime($log['created_at'])) ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- DIAGNOSTIC OUTPUT -->
<div class="container-fluid mt-4 mb-5">
    <details class="card border-0 shadow-sm rounded-4 p-3 bg-light text-dark">
        <summary class="fw-bold text-muted" style="cursor: pointer;">Developer Debug Info (Activity Logs Troubleshooting)</summary>
        <pre class="mt-3 p-3 bg-white border rounded" style="font-size: 0.8rem; overflow: auto; max-height: 400px;"><?= htmlspecialchars(print_r($debugInfo, true)) ?></pre>
    </details>
</div>

<style>
    .fw-800 { font-weight: 800; }
</style>
