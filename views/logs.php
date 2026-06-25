<?php
require_once 'models/ActivityLog.php';
$logModel = new ActivityLog($conn);
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

<style>
    .fw-800 { font-weight: 800; }
</style>
