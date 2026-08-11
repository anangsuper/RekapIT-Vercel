<?php
require_once 'models/Asset.php';
require_once 'models/Mutation.php';
require_once 'models/Cabang.php';
require_once 'models/Divisi.php';
require_once 'models/Karyawan.php';
require_once 'models/ActivityLog.php';
require_once 'helpers/notification.php';

$assetModel = new Asset($conn);
$mutationModel = new Mutation($conn);
$cabangModel = new Cabang($conn);
$divisiModel = new Divisi($conn);
$karyawanModel = new Karyawan($conn);
$logModel = new ActivityLog($conn);

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// 1. Proses Pengajuan / Form Mutasi Baru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['proses_mutasi'])) {
    $asset_id = $_POST['asset_id'];
    $currentAsset = $assetModel->getById($asset_id);
    
    // Tentukan status mutasi (Admin bisa memilih langsung Disetujui atau Menunggu, Teknisi/Staff otomatis Menunggu)
    $status_input = $_POST['status_mutasi'] ?? 'Disetujui';
    if (!$isAdmin) {
        $status_input = 'Menunggu';
    }

    $data = [
        'asset_id' => $asset_id,
        'user_id' => $_SESSION['user_id'],
        'id_cabang_lama' => $currentAsset['id_cabang'],
        'id_divisi_lama' => $currentAsset['id_divisi'],
        'id_karyawan_lama' => $currentAsset['id_karyawan'],
        'id_cabang_baru' => $_POST['id_cabang_baru'],
        'id_divisi_baru' => $_POST['id_divisi_baru'],
        'id_karyawan_baru' => $_POST['id_karyawan_baru'],
        'tanggal_mutasi' => $_POST['tanggal_mutasi'],
        'keterangan' => $_POST['keterangan'],
        'status' => $status_input
    ];

    if ($isAdmin && $status_input === 'Disetujui') {
        $data['approved_by'] = $_SESSION['user_id'];
    }

    if ($mutationModel->create($data)) {
        $namaUser = $_SESSION['nama'] ?? 'User';
        
        if ($status_input === 'Menunggu') {
            $logModel->add($_SESSION['user_id'], 'Pengajuan Mutasi', "Pengajuan mutasi aset " . $currentAsset['nama_aset'] . " (" . $currentAsset['kode_aset'] . ")");
            $msg = "📩 *PENGAJUAN MUTASI ASET (MENUNGGU APPROVAL)*\n\n"
                 . "*• Kode Aset:* `{$currentAsset['kode_aset']}`\n"
                 . "*• Perangkat:* {$currentAsset['nama_aset']}\n"
                 . "*• Diajukan Oleh:* {$namaUser}\n"
                 . "*• Tanggal Mutasi:* " . date('d M Y', strtotime($_POST['tanggal_mutasi'])) . "\n"
                 . "*• Catatan:* Perlu persetujuan Admin di menu Mutasi Aset.";
            sendTelegramNotification($msg);
            header("Location: index.php?page=mutasi&status=submitted_pending");
        } else {
            $logModel->add($_SESSION['user_id'], 'Mutasi Aset', "Mutasi aset " . $currentAsset['nama_aset'] . " (" . $currentAsset['kode_aset'] . ")");
            $msg = "🔄 *MUTASI ASET DISETUJUI & EKSEKUSI*\n\n"
                 . "*• Kode Aset:* `{$currentAsset['kode_aset']}`\n"
                 . "*• Perangkat:* {$currentAsset['nama_aset']}\n"
                 . "*• Oleh Admin:* {$namaUser}\n"
                 . "*• Tanggal Mutasi:* " . date('d M Y', strtotime($_POST['tanggal_mutasi']));
            sendTelegramNotification($msg);
            header("Location: index.php?page=mutasi&status=success");
        }
        exit();
    } else {
        $error = "Gagal memproses mutasi aset.";
    }
}

// 2. Proses Approval (Setujui Mutasi - Admin Only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['setujui_mutasi'])) {
    if (!$isAdmin) {
        header("Location: index.php?page=mutasi&error=unauthorized");
        exit();
    }
    $mutation_id = (int)$_POST['mutation_id'];
    $mut = $mutationModel->getById($mutation_id);

    if ($mut && $mutationModel->approve($mutation_id, $_SESSION['user_id'])) {
        $namaUser = $_SESSION['nama'] ?? 'Admin';
        $logModel->add($_SESSION['user_id'], 'Setujui Mutasi', "Menyetujui mutasi aset " . $mut['nama_aset'] . " (" . $mut['kode_aset'] . ")");
        
        $msg = "✅ *MUTASI ASET DISETUJUI*\n\n"
             . "*• Kode Aset:* `{$mut['kode_aset']}`\n"
             . "*• Perangkat:* {$mut['nama_aset']}\n"
             . "*• Lokasi Baru:* {$mut['cabang_baru']} (" . ($mut['karyawan_baru'] ?: 'Unassigned') . ")\n"
             . "*• Disetujui Oleh:* {$namaUser}\n"
             . "*• Waktu:* " . date('d M Y, H:i:s');
        sendTelegramNotification($msg);

        header("Location: index.php?page=mutasi&status=approved");
        exit();
    } else {
        $error = "Gagal menyetujui mutasi.";
    }
}

// 3. Proses Penolakan (Tolak Mutasi - Admin Only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tolak_mutasi'])) {
    if (!$isAdmin) {
        header("Location: index.php?page=mutasi&error=unauthorized");
        exit();
    }
    $mutation_id = (int)$_POST['mutation_id'];
    $alasan = trim($_POST['alasan_penolakan'] ?? 'Tidak disetujui oleh admin.');
    $mut = $mutationModel->getById($mutation_id);

    if ($mut && $mutationModel->reject($mutation_id, $_SESSION['user_id'], $alasan)) {
        $namaUser = $_SESSION['nama'] ?? 'Admin';
        $logModel->add($_SESSION['user_id'], 'Tolak Mutasi', "Menolak pengajuan mutasi aset " . $mut['nama_aset'] . " (" . $mut['kode_aset'] . ")");
        
        $msg = "❌ *MUTASI ASET DITOLAK*\n\n"
             . "*• Kode Aset:* `{$mut['kode_aset']}`\n"
             . "*• Perangkat:* {$mut['nama_aset']}\n"
             . "*• Ditolak Oleh:* {$namaUser}\n"
             . "*• Alasan:* {$alasan}\n"
             . "*• Waktu:* " . date('d M Y, H:i:s');
        sendTelegramNotification($msg);

        header("Location: index.php?page=mutasi&status=rejected");
        exit();
    } else {
        $error = "Gagal menolak mutasi.";
    }
}

// Filters & Pagination
$filter_status = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : null;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : null;

$allMutations = $mutationModel->getAll();

$limit = 10;
$pageNumber = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($pageNumber - 1) * $limit;

$totalMutations = $mutationModel->countAll($search_query, $filter_status);
$totalPages = ceil($totalMutations / $limit);

$mutations = $mutationModel->getPaginated($limit, $offset, $search_query, $filter_status);
$paginationUrl = "index.php?page=mutasi";
if ($search_query) {
    $paginationUrl .= "&search=" . urlencode($search_query);
}
if ($filter_status) {
    $paginationUrl .= "&status_filter=" . urlencode($filter_status);
}

$assets = $assetModel->getAll();
$cabangs = $cabangModel->getAll();
$divisis = $divisiModel->getAll();
$karyawans = $karyawanModel->getAll();

// Calculate Stats counters
$totalMutasiCount = count($allMutations);
$pendingCount = 0;
$approvedMonthCount = 0;
$rejectedCount = 0;

foreach ($allMutations as $m) {
    $st = $m['status'] ?? 'Disetujui';
    if ($st === 'Menunggu') {
        $pendingCount++;
    } elseif ($st === 'Disetujui') {
        if (date('m-Y', strtotime($m['tanggal_mutasi'])) === date('m-Y')) {
            $approvedMonthCount++;
        }
    } elseif ($st === 'Ditolak') {
        $rejectedCount++;
    }
}
?>

<div class="container-fluid animate-fade-in">
    <!-- Hero Header Banner Card -->
    <div class="card border-0 shadow-lg rounded-4 p-4 mb-4" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(6, 182, 212, 0.06) 100%) !important; border: 1px solid rgba(99, 102, 241, 0.2) !important;">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded-4 text-white shadow-sm" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
                    <i class="bi bi-arrow-left-right fs-3"></i>
                </div>
                <div>
                    <h4 class="fw-800 m-0 text-dark">Mutasi & Approval Aset IT <span class="badge bg-primary bg-opacity-10 text-primary fs-6 fw-bold ms-2">Rekap IT</span></h4>
                    <p class="text-muted small m-0 mt-1">Kelola pengajuan, alur persetujuan (*approval*), dan riwayat perpindahan perangkat.</p>
                </div>
            </div>
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalMutasi">
                <i class="bi bi-plus-lg me-2"></i> Ajukan Mutasi Aset
            </button>
        </div>
    </div>

    <!-- Alert Status -->
    <?php if(isset($_GET['status'])): ?>
        <?php if ($_GET['status'] == 'success'): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4 animate-fade-in" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> Mutasi aset berhasil diproses dan lokasi fisik perangkat telah diperbarui!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($_GET['status'] == 'submitted_pending'): ?>
            <div class="alert alert-info alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4 animate-fade-in" role="alert">
                <i class="bi bi-clock-history me-2"></i> Pengajuan mutasi berhasil dikirim! Status saat ini: <strong>Menunggu Approval Admin</strong>.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($_GET['status'] == 'approved'): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4 animate-fade-in" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> Mutasi aset telah <strong>DISETUJUI</strong> dan lokasi fisik perangkat resmi dipindahkan!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($_GET['status'] == 'rejected'): ?>
            <div class="alert alert-warning alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4 animate-fade-in" role="alert">
                <i class="bi bi-x-circle-fill me-2"></i> Pengajuan mutasi telah <strong>DITOLAK</strong>. Perangkat tetap berada di lokasi awal.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4 animate-fade-in" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- KPI Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
                <div class="card-body p-4 text-white position-relative">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3.5rem; transform: translate(10%, -10%);">
                        <i class="bi bi-arrow-left-right"></i>
                    </div>
                    <span class="small fw-bold opacity-75">TOTAL MUTASI</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $totalMutasiCount ?></h3>
                    <small class="opacity-70 d-block mt-2">Seluruh riwayat pengajuan & mutasi</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <div class="card-body p-4 text-white position-relative">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3.5rem; transform: translate(10%, -10%);">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <span class="small fw-bold opacity-75">MENUNGGU APPROVAL</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $pendingCount ?></h3>
                    <small class="opacity-70 d-block mt-2">Membutuhkan persetujuan admin</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <div class="card-body p-4 text-white position-relative">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3.5rem; transform: translate(10%, -10%);">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <span class="small fw-bold opacity-75">DISETUJUI BULAN INI</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $approvedMonthCount ?></h3>
                    <small class="opacity-70 d-block mt-2">Berhasil dipindahkan bulan ini</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                <div class="card-body p-4 text-white position-relative">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3.5rem; transform: translate(10%, -10%);">
                        <i class="bi bi-x-circle-fill"></i>
                    </div>
                    <span class="small fw-bold opacity-75">MUTASI DITOLAK</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $rejectedCount ?></h3>
                    <small class="opacity-70 d-block mt-2">Pengajuan yang tidak disetujui</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Status Tabs & Search Panel -->
    <div class="card border-0 shadow-sm mb-4 rounded-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                <!-- Status Filter Pills -->
                <div class="nav nav-pills gap-2">
                    <a href="index.php?page=mutasi" class="btn btn-sm <?= empty($filter_status) ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-3 py-1.5 fw-bold">
                        Semua Mutasi
                    </a>
                    <a href="index.php?page=mutasi&status_filter=Menunggu" class="btn btn-sm <?= ($filter_status === 'Menunggu') ? 'btn-warning text-dark' : 'btn-outline-warning' ?> rounded-pill px-3 py-1.5 fw-bold">
                        <i class="bi bi-clock-history me-1"></i> Menunggu Approval (<?= $pendingCount ?>)
                    </a>
                    <a href="index.php?page=mutasi&status_filter=Disetujui" class="btn btn-sm <?= ($filter_status === 'Disetujui') ? 'btn-success' : 'btn-outline-success' ?> rounded-pill px-3 py-1.5 fw-bold">
                        <i class="bi bi-check-circle-fill me-1"></i> Disetujui
                    </a>
                    <a href="index.php?page=mutasi&status_filter=Ditolak" class="btn btn-sm <?= ($filter_status === 'Ditolak') ? 'btn-danger' : 'btn-outline-danger' ?> rounded-pill px-3 py-1.5 fw-bold">
                        <i class="bi bi-x-circle-fill me-1"></i> Ditolak
                    </a>
                </div>
            </div>

            <form method="GET" action="index.php" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="mutasi">
                <?php if ($filter_status): ?>
                    <input type="hidden" name="status_filter" value="<?= htmlspecialchars($filter_status) ?>">
                <?php endif; ?>
                <div class="col-md-9">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" id="mutationSearch" class="form-control bg-light border-0" placeholder="Cari Kode Aset, Perangkat, Lokasi, Pemegang Baru..." value="<?= htmlspecialchars($search_query ?? '') ?>">
                        <?php if ($search_query): ?>
                            <a href="index.php?page=mutasi<?= $filter_status ? '&status_filter=' . urlencode($filter_status) : '' ?>" class="btn btn-light border-0 d-flex align-items-center text-danger"><i class="bi bi-x-circle-fill"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary d-none">Cari</button>
                    <a href="index.php?page=mutasi" class="btn btn-outline-secondary w-100 fw-bold py-2 shadow-sm rounded-3">
                        <i class="bi bi-arrow-counterclockwise me-1.5"></i>Reset Filter
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Filter Condition Badge -->
    <?php if ($search_query || $filter_status): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4 border-0 shadow-sm rounded-4 d-flex justify-content-between align-items-center" role="alert">
            <div class="m-0 small">
                <i class="bi bi-filter-circle-fill text-warning me-2 fs-5"></i> 
                Menampilkan filter: 
                <?php if ($filter_status): ?>
                    <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1 me-1">Status: <?= strtoupper($filter_status) ?></span>
                <?php endif; ?>
                <?php if ($search_query): ?>
                    <span class="badge bg-info text-dark rounded-pill px-2.5 py-1">Kata Kunci: "<?= htmlspecialchars($search_query) ?>"</span>
                <?php endif; ?>
            </div>
            <a href="index.php?page=mutasi" class="btn btn-sm btn-light border-0 shadow-sm rounded-pill px-3 py-1.5 fw-bold"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset</a>
        </div>
    <?php endif; ?>

    <!-- Mutation Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle mb-0" id="mutationTable">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4">Aset</th>
                            <th style="width: 30%;">Alur Perpindahan Lokasi & Pemegang</th>
                            <th>Status Approval</th>
                            <th>Tanggal Mutasi</th>
                            <th>Pemohon / Pelaksana</th>
                            <th class="pe-4 text-end">Aksi / Decision</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($mutations)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-arrow-left-right fs-2 d-block mb-2"></i> Belum ada riwayat mutasi aset.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($mutations as $m): 
                                $st = $m['status'] ?? 'Disetujui';
                            ?>
                            <tr class="mutation-row align-middle" data-search="<?= htmlspecialchars(strtolower($m['nama_aset'] . ' ' . $m['kode_aset'] . ' ' . $m['cabang_lama'] . ' ' . $m['cabang_baru'] . ' ' . ($m['karyawan_lama'] ?? '') . ' ' . ($m['karyawan_baru'] ?? '') . ' ' . ($m['keterangan'] ?? ''))) ?>">
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($m['nama_aset']) ?></div>
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded px-2 py-0.5 mt-1 small" style="font-size: 0.72rem;"><?= htmlspecialchars($m['kode_aset']) ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <!-- Old place -->
                                        <div class="bg-light p-2.5 rounded-3 flex-fill text-center" style="max-width: 130px;">
                                            <div class="small fw-bold text-danger opacity-75 text-truncate" title="<?= htmlspecialchars($m['cabang_lama']) ?>"><?= htmlspecialchars($m['cabang_lama']) ?></div>
                                            <div class="text-muted text-xs text-truncate" style="font-size: 0.73rem;" title="<?= htmlspecialchars($m['karyawan_lama'] ?: 'Unassigned') ?>"><?= htmlspecialchars($m['karyawan_lama'] ?: 'Unassigned') ?></div>
                                        </div>
                                        
                                        <!-- Direction Arrow -->
                                        <i class="bi bi-arrow-right text-primary fs-5"></i>
                                        
                                        <!-- New Place -->
                                        <div class="bg-primary bg-opacity-10 p-2.5 rounded-3 flex-fill text-center" style="max-width: 130px;">
                                            <div class="small fw-bold text-success text-truncate" title="<?= htmlspecialchars($m['cabang_baru']) ?>"><?= htmlspecialchars($m['cabang_baru']) ?></div>
                                            <div class="text-dark text-xs text-truncate" style="font-size: 0.73rem;" title="<?= htmlspecialchars($m['karyawan_baru'] ?: 'Unassigned') ?>"><?= htmlspecialchars($m['karyawan_baru'] ?: 'Unassigned') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($st === 'Menunggu'): ?>
                                        <span class="badge bg-warning bg-opacity-15 text-warning border border-warning rounded-pill px-3 py-1 fw-bold" style="font-size: 0.75rem;">
                                            <i class="bi bi-clock-history me-1 animate-pulse"></i> Menunggu Approval
                                        </span>
                                    <?php elseif ($st === 'Disetujui'): ?>
                                        <span class="badge bg-success bg-opacity-15 text-success border border-success rounded-pill px-3 py-1 fw-bold" style="font-size: 0.75rem;">
                                            <i class="bi bi-check-circle-fill me-1"></i> Disetujui
                                        </span>
                                        <?php if (!empty($m['penyetujui'])): ?>
                                            <small class="d-block text-muted text-xs mt-0.5">By: <?= htmlspecialchars($m['penyetujui']) ?></small>
                                        <?php endif; ?>
                                    <?php elseif ($st === 'Ditolak'): ?>
                                        <span class="badge bg-danger bg-opacity-15 text-danger border border-danger rounded-pill px-3 py-1 fw-bold" style="font-size: 0.75rem;">
                                            <i class="bi bi-x-circle-fill me-1"></i> Ditolak
                                        </span>
                                        <?php if (!empty($m['alasan_penolakan'])): ?>
                                            <small class="d-block text-danger text-xs mt-0.5 text-truncate" style="max-width: 140px;" title="<?= htmlspecialchars($m['alasan_penolakan']) ?>"><?= htmlspecialchars($m['alasan_penolakan']) ?></small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small fw-bold text-dark"><i class="bi bi-calendar3 text-muted me-1.5"></i><?= date('d M Y', strtotime($m['tanggal_mutasi'])) ?></div>
                                    <div class="text-muted small text-truncate mt-0.5" style="max-width: 150px;" title="<?= htmlspecialchars($m['keterangan']) ?>"><?= htmlspecialchars($m['keterangan'] ?: '-') ?></div>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark"><i class="bi bi-person-fill text-muted me-1.5"></i><?= htmlspecialchars($m['pelaksana']) ?></div>
                                </td>
                                <td class="pe-4 text-end">
                                    <div class="d-flex justify-content-end gap-1.5">
                                        <?php if ($st === 'Menunggu' && $isAdmin): ?>
                                            <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin MENYETUJUI mutasi ini? Lokasi aset akan langsung diperbarui.');" style="display:inline;">
                                                <input type="hidden" name="setujui_mutasi" value="1">
                                                <input type="hidden" name="mutation_id" value="<?= $m['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success px-2.5 shadow-sm" title="Setujui Mutasi">
                                                    <i class="bi bi-check-lg"></i> Setujui
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-outline-danger px-2.5 shadow-sm" onclick="openTolakModal(<?= $m['id'] ?>, '<?= htmlspecialchars(addslashes($m['nama_aset'])) ?>')" title="Tolak Mutasi">
                                                <i class="bi bi-x-lg"></i> Tolak
                                            </button>
                                        <?php endif; ?>

                                        <button class="btn btn-sm btn-light border" onclick="showMutationDetail(<?= htmlspecialchars(json_encode($m)) ?>)">
                                            <i class="bi bi-info-circle"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Responsive Mobile Grid -->
            <div class="d-block d-md-none p-3" id="mobileMutationContainer">
                <?php foreach ($mutations as $m): 
                    $st = $m['status'] ?? 'Disetujui';
                ?>
                    <div class="card border p-3 mb-3 rounded-3 shadow-sm mobile-mutation-card">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded px-2.5 py-1 small"><?= htmlspecialchars($m['kode_aset']) ?></span>
                                <h6 class="fw-bold text-dark mt-2 mb-1"><?= htmlspecialchars($m['nama_aset']) ?></h6>
                            </div>
                            <?php if ($st === 'Menunggu'): ?>
                                <span class="badge bg-warning text-dark rounded-pill px-2 py-1 small">Pending</span>
                            <?php elseif ($st === 'Disetujui'): ?>
                                <span class="badge bg-success rounded-pill px-2 py-1 small">Disetujui</span>
                            <?php elseif ($st === 'Ditolak'): ?>
                                <span class="badge bg-danger rounded-pill px-2 py-1 small">Ditolak</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="bg-light p-2.5 rounded-3 mb-3 d-flex align-items-center justify-content-between gap-1">
                            <div class="text-center flex-fill">
                                <span class="text-xs text-muted d-block" style="font-size: 0.7rem;">Dari:</span>
                                <strong class="text-danger small text-truncate d-block" style="max-width: 100px;"><?= htmlspecialchars($m['cabang_lama']) ?></strong>
                            </div>
                            <i class="bi bi-arrow-right text-primary"></i>
                            <div class="text-center flex-fill">
                                <span class="text-xs text-muted d-block" style="font-size: 0.7rem;">Ke:</span>
                                <strong class="text-success small text-truncate d-block" style="max-width: 100px;"><?= htmlspecialchars($m['cabang_baru']) ?></strong>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6 small"><span class="text-muted">Tanggal:</span><br><strong><?= date('d M Y', strtotime($m['tanggal_mutasi'])) ?></strong></div>
                            <div class="col-6 small"><span class="text-muted">Pelaksana:</span><br><strong><?= htmlspecialchars($m['pelaksana']) ?></strong></div>
                        </div>

                        <div class="d-flex gap-2">
                            <?php if ($st === 'Menunggu' && $isAdmin): ?>
                                <form method="POST" onsubmit="return confirm('Setujui mutasi ini?');" class="flex-fill">
                                    <input type="hidden" name="setujui_mutasi" value="1">
                                    <input type="hidden" name="mutation_id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success w-100 fw-bold py-2"><i class="bi bi-check-lg"></i> Setujui</button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-danger flex-fill fw-bold py-2" onclick="openTolakModal(<?= $m['id'] ?>, '<?= htmlspecialchars(addslashes($m['nama_aset'])) ?>')">
                                    <i class="bi bi-x-lg"></i> Tolak
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-outline-primary flex-fill fw-bold py-2" onclick="showMutationDetail(<?= htmlspecialchars(json_encode($m)) ?>)">
                                <i class="bi bi-info-circle me-1"></i> Detail
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white border-top-0 pt-2 pb-4 d-flex justify-content-center">
            <?= getPaginationControls($pageNumber, $totalPages, $paginationUrl) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tolak Mutasi -->
<div class="modal fade" id="modalTolakMutasi" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px;">
            <form method="POST">
                <input type="hidden" name="tolak_mutasi" value="1">
                <input type="hidden" name="mutation_id" id="tolak_mutation_id">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-800 text-danger m-0"><i class="bi bi-x-circle-fill me-2"></i> Konfirmasi Penolakan Mutasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">Tentukan alasan menolak pengajuan mutasi aset <strong id="tolak_asset_name" class="text-dark">-</strong>.</p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Alasan Penolakan</label>
                        <textarea name="alasan_penolakan" class="form-control bg-light border-0" rows="3" placeholder="Contoh: Perangkat masih digunakan di divisi cabang asal..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger px-4 rounded-pill fw-bold shadow-sm">Tolak Mutasi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detail Mutasi -->
<div class="modal fade" id="modalDetailMutasi" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 28px;">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-800 m-0"><i class="bi bi-info-circle-fill text-primary me-2"></i> Rincian Riwayat Mutasi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <span class="text-muted small d-block mb-1">Aset / Perangkat</span>
                    <strong class="text-dark fs-6" id="detAssetName">-</strong>
                    <span class="badge bg-primary bg-opacity-10 text-primary ms-1.5 rounded-3 px-2 py-0.5" id="detAssetCode">-</span>
                </div>

                <div class="mb-3">
                    <span class="text-muted small d-block mb-1">Status Approval</span>
                    <div id="detStatusContainer">-</div>
                </div>

                <div class="card p-3 bg-light border-0 mb-4 rounded-3">
                    <h6 class="fw-bold small text-dark mb-2.5"><i class="bi bi-arrow-left-right text-primary me-1"></i> Detail Perpindahan</h6>
                    <div class="row g-3">
                        <div class="col-6">
                            <span class="text-muted text-xs d-block mb-1">Lokasi Awal</span>
                            <span class="small fw-bold text-danger" id="detBranchLama">-</span>
                            <span class="d-block text-xs text-muted" id="detUserLama">-</span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted text-xs d-block mb-1">Lokasi Baru</span>
                            <span class="small fw-bold text-success" id="detBranchBaru">-</span>
                            <span class="d-block text-xs text-dark" id="detUserBaru">-</span>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <span class="text-muted text-xs d-block">Tanggal Mutasi</span>
                        <strong class="small text-dark" id="detTanggal">-</strong>
                    </div>
                    <div class="col-6">
                        <span class="text-muted text-xs d-block">Pemohon / Pelaksana</span>
                        <strong class="small text-dark" id="detPelaksana">-</strong>
                    </div>
                    <div class="col-12">
                        <span class="text-muted text-xs d-block">Keterangan / Alasan</span>
                        <div class="p-2.5 bg-light rounded text-dark small mt-1" style="min-height: 48px;" id="detKeterangan">-</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-secondary px-5 shadow-sm rounded-pill fw-bold" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Form Mutasi Baru -->
<div class="modal fade" id="modalMutasi" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 28px;">
            <form method="POST">
                <input type="hidden" name="proses_mutasi" value="1">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-800 m-0"><i class="bi bi-arrow-left-right text-primary me-2"></i> Form Mutasi Aset IT</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-4">Pilih perangkat yang akan dimutasi dan tentukan cabang, divisi, serta pemegang baru.</p>
                    
                    <div class="row g-4">
                        <!-- Filter Cabang Aset -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Filter Cabang Asal Aset</label>
                            <select id="mutasi_filter_cabang_asset" class="form-select bg-light border-0">
                                <option value="">-- Semua Cabang --</option>
                                <?php foreach ($cabangs as $c): ?>
                                    <option value="<?= htmlspecialchars($c['nama_cabang']) ?>"><?= htmlspecialchars($c['nama_cabang']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Pencarian Kata Kunci Aset -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Cari Nama / Kode Aset</label>
                            <input type="text" id="mutasi_search_asset_input" class="form-control bg-light border-0" placeholder="Ketik nama atau kode...">
                        </div>

                        <!-- Pilih Aset -->
                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-muted">Pilih Aset</label>
                            <select name="asset_id" id="mutasi_asset_id" class="form-select bg-light border-0" required>
                                <option value="">-- Pilih Aset --</option>
                                <?php foreach ($assets as $a): ?>
                                    <option value="<?= $a['id'] ?>" 
                                            data-cabang-name="<?= htmlspecialchars($a['nama_cabang']) ?>"
                                            data-cabang="<?= htmlspecialchars($a['nama_cabang']) ?>" 
                                            data-divisi="<?= htmlspecialchars($a['nama_divisi']) ?>" 
                                            data-karyawan="<?= htmlspecialchars($a['nama_karyawan'] ?: 'Unassigned') ?>">
                                        <?= htmlspecialchars($a['kode_aset']) ?> - <?= htmlspecialchars($a['nama_aset']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Info Aset Sekarang -->
                        <div class="col-md-12">
                            <div class="p-3 rounded-4 bg-light border border-dashed text-muted small">
                                <i class="bi bi-info-circle me-2"></i> Lokasi Saat Ini: 
                                <span id="info_lokasi_lama" class="fw-bold text-dark">Pilih aset terlebih dahulu</span>
                            </div>
                        </div>

                        <!-- Target Lokasi Baru -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Cabang Tujuan Baru</label>
                            <select name="id_cabang_baru" class="form-select bg-light border-0" required>
                                <option value="">-- Pilih Cabang --</option>
                                <?php foreach ($cabangs as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nama_cabang']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Divisi Tujuan Baru</label>
                            <select name="id_divisi_baru" class="form-select bg-light border-0" required>
                                <option value="">-- Pilih Divisi --</option>
                                <?php foreach ($divisis as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nama_divisi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Penanggung Jawab Baru</label>
                            <select name="id_karyawan_baru" class="form-select bg-light border-0">
                                <option value="">-- Unassigned --</option>
                                <?php foreach ($karyawans as $k): ?>
                                    <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_karyawan']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Tanggal Efektif Mutasi</label>
                            <input type="date" name="tanggal_mutasi" class="form-control bg-light border-0" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <?php if ($isAdmin): ?>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Opsi Executable Status</label>
                                <select name="status_mutasi" class="form-select bg-light border-0">
                                    <option value="Disetujui" selected>🟢 Langsung Disetujui (Direct Approve)</option>
                                    <option value="Menunggu">🟡 Pengajuan Menunggu Approval</option>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-muted">Keterangan / Alasan Mutasi</label>
                            <textarea name="keterangan" class="form-control bg-light border-0" rows="3" placeholder="Jelaskan alasan perpindahan perangkat..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-5 rounded-pill fw-bold shadow-sm">Kirim Mutasi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showMutationDetail(m) {
    document.getElementById('detAssetName').innerText = m.nama_aset || '-';
    document.getElementById('detAssetCode').innerText = m.kode_aset || '-';
    document.getElementById('detBranchLama').innerText = m.cabang_lama || '-';
    document.getElementById('detUserLama').innerText = m.karyawan_lama || 'Unassigned';
    document.getElementById('detBranchBaru').innerText = m.cabang_baru || '-';
    document.getElementById('detUserBaru').innerText = m.karyawan_baru || 'Unassigned';
    document.getElementById('detTanggal').innerText = m.tanggal_mutasi || '-';
    document.getElementById('detPelaksana').innerText = m.pelaksana || '-';
    document.getElementById('detKeterangan').innerText = m.keterangan || '-';

    const st = m.status || 'Disetujui';
    const statusContainer = document.getElementById('detStatusContainer');
    if (st === 'Menunggu') {
        statusContainer.innerHTML = '<span class="badge bg-warning text-dark px-3 py-1.5 rounded-pill"><i class="bi bi-clock-history me-1"></i> Menunggu Approval</span>';
    } else if (st === 'Disetujui') {
        statusContainer.innerHTML = '<span class="badge bg-success px-3 py-1.5 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> Disetujui</span>';
    } else if (st === 'Ditolak') {
        let msg = '<span class="badge bg-danger px-3 py-1.5 rounded-pill"><i class="bi bi-x-circle-fill me-1"></i> Ditolak</span>';
        if (m.alasan_penolakan) {
            msg += '<div class="text-danger small mt-1">Alasan: ' + m.alasan_penolakan + '</div>';
        }
        statusContainer.innerHTML = msg;
    }

    const modal = new bootstrap.Modal(document.getElementById('modalDetailMutasi'));
    modal.show();
}

function openTolakModal(id, assetName) {
    document.getElementById('tolak_mutation_id').value = id;
    document.getElementById('tolak_asset_name').innerText = assetName;
    const modal = new bootstrap.Modal(document.getElementById('modalTolakMutasi'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    const selectAsset = document.getElementById('mutasi_asset_id');
    const infoLokasiLama = document.getElementById('info_lokasi_lama');
    const filterCabang = document.getElementById('mutasi_filter_cabang_asset');
    const searchInput = document.getElementById('mutasi_search_asset_input');

    if (selectAsset) {
        selectAsset.addEventListener('change', function() {
            const opt = selectAsset.options[selectAsset.selectedIndex];
            if (opt && opt.value) {
                const cabang = opt.getAttribute('data-cabang') || '-';
                const divisi = opt.getAttribute('data-divisi') || '-';
                const karyawan = opt.getAttribute('data-karyawan') || 'Unassigned';
                infoLokasiLama.innerHTML = `Cabang: <strong>${cabang}</strong> &bull; Divisi: <strong>${divisi}</strong> &bull; Pemegang: <strong>${karyawan}</strong>`;
            } else {
                infoLokasiLama.innerText = 'Pilih aset terlebih dahulu';
            }
        });
    }

    function filterAssetOptions() {
        if (!selectAsset) return;
        const cbVal = filterCabang ? filterCabang.value.toLowerCase() : '';
        const srVal = searchInput ? searchInput.value.toLowerCase() : '';

        Array.from(selectAsset.options).forEach((opt, idx) => {
            if (idx === 0) return;
            const text = opt.text.toLowerCase();
            const cabang = (opt.getAttribute('data-cabang-name') || '').toLowerCase();
            
            const matchCb = !cbVal || cabang.includes(cbVal);
            const matchSr = !srVal || text.includes(srVal);

            if (matchCb && matchSr) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        });
    }

    if (filterCabang) filterCabang.addEventListener('change', filterAssetOptions);
    if (searchInput) searchInput.addEventListener('input', filterAssetOptions);
});
</script>
