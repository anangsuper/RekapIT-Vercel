<?php
// Query untuk mengambil statistik
try {
    $stmtAssets = $conn->query("SELECT COUNT(*) as total FROM assets");
    $totalAssets = $stmtAssets->fetch()['total'];

    $stmtMaintenance = $conn->query("SELECT COUNT(*) as total FROM maintenance WHERE MONTH(tanggal) = MONTH(CURRENT_DATE)");
    $totalMaintenance = $stmtMaintenance->fetch()['total'];

    $stmtRepairs = $conn->query("SELECT COUNT(*) as total FROM repairs WHERE status = 'Proses'");
    $totalRepairs = $stmtRepairs->fetch()['total'];

    $stmtCost = $conn->query("SELECT SUM(biaya) as total FROM repairs WHERE status = 'Selesai' AND MONTH(created_at) = MONTH(CURRENT_DATE)");
    $totalCost = $stmtCost->fetch()['total'] ?? 0;

    // Perlu Tindakan: Aset Rusak Ringan / Rusak Berat
    $stmtPerluTindakan = $conn->query("SELECT COUNT(*) as total FROM assets WHERE kondisi IN ('Rusak Ringan', 'Rusak Berat')");
    $totalPerluTindakan = $stmtPerluTindakan->fetch()['total'];

    $stmtBroken = $conn->query("SELECT a.*, c.nama_cabang, d.nama_divisi
                                FROM assets a
                                LEFT JOIN cabang c ON a.id_cabang = c.id
                                LEFT JOIN divisi d ON a.id_divisi = d.id
                                WHERE a.kondisi IN ('Rusak Ringan', 'Rusak Berat')
                                ORDER BY a.kondisi DESC, a.created_at DESC LIMIT 5");
    $brokenAssets = $stmtBroken->fetchAll();

    // Tambahan: Aktivitas Terbaru
    $stmtLogs = $conn->query("SELECT al.*, u.nama as user_nama FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 5");
    $recentLogs = $stmtLogs->fetchAll();

    // Tambahan: Distribusi Aset per Cabang
    $stmtBranch = $conn->query("SELECT c.nama_cabang, COUNT(a.id) as total FROM cabang c LEFT JOIN assets a ON c.id = a.id_cabang GROUP BY c.id");
    $branchDistribution = $stmtBranch->fetchAll();

    // Top 5 Aset Terboros (Biaya Perbaikan Tertinggi)
    $stmtCostlyAssets = $conn->query("SELECT a.id, a.kode_aset, a.nama_aset, c.nama_cabang, SUM(r.biaya) as total_biaya
                                     FROM repairs r
                                     JOIN assets a ON r.asset_id = a.id
                                     LEFT JOIN cabang c ON a.id_cabang = c.id
                                     GROUP BY a.id
                                     ORDER BY total_biaya DESC
                                     LIMIT 5");
    $costlyAssets = $stmtCostlyAssets->fetchAll();

    // Biaya Perbaikan per Cabang Analytics
    $stmtBranchCosts = $conn->query("SELECT c.nama_cabang, COALESCE(SUM(r.biaya), 0) as total_biaya
                                     FROM cabang c
                                     LEFT JOIN assets a ON c.id = a.id_cabang
                                     LEFT JOIN repairs r ON a.id = r.asset_id
                                     GROUP BY c.id ORDER BY total_biaya DESC");
    $branchCostAnalytics = $stmtBranchCosts->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard PDO Error: " . $e->getMessage());
    $totalAssets = $totalMaintenance = $totalRepairs = $totalCost = $totalPerluTindakan = 0;
    $brokenAssets = [];
    $recentLogs = [];
    $branchDistribution = [];
    $costlyAssets = [];
    $branchCostAnalytics = [];
}
?>

<!-- Maintenance Reminder Alert -->
<?php
require_once __DIR__ . '/../models/Maintenance.php';
$maintModel = new Maintenance($conn);
$upcomingMaint = $maintModel->getUpcomingNotifications(7); // Next 7 days
?>
<?php if (!empty($upcomingMaint)): ?>
<div class="alert alert-warning alert-dismissible fade show mb-4 border-0 shadow-sm rounded-4" role="alert">
    <div class="d-flex align-items-center">
        <i class="bi bi-calendar-event fs-4 me-3"></i>
        <div>
            <h6 class="fw-bold mb-1">Pengingat Maintenance!</h6>
            <p class="mb-0 small">Ada <?= count($upcomingMaint) ?> aset yang dijadwalkan untuk maintenance dalam 7 hari ke depan.</p>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<style>
    .lux-card {
        background: var(--card-bg) !important;
        border: 1px solid var(--card-border);
        border-radius: 18px;
        transition: all 0.2s ease;
        box-shadow: var(--card-shadow);
        position: relative;
        overflow: hidden;
        z-index: 1;
    }
    .lux-card::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 5px;
        background: #bef264;
    }
    .row > .col-md-3:nth-child(2) .lux-card::before { background: #93c5fd; }
    .row > .col-md-3:nth-child(3) .lux-card::before { background: #f9a8d4; }
    .row > .col-md-3:nth-child(4) .lux-card::before { background: #fdba74; }
    .lux-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--card-shadow-hover);
    }
    .fw-800 { font-weight: 800; }
    .fw-700 { font-weight: 700; }
    .transition-hover {
        transition: all 0.2s ease;
        border: 1px solid var(--card-border) !important;
        background: var(--table-tr-bg) !important;
    }
    .transition-hover:hover {
        background-color: var(--table-tr-hover) !important;
        border-color: var(--primary-color) !important;
        transform: translateY(-2px);
        box-shadow: var(--card-shadow-hover);
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.6; transform: scale(1.1); }
    }
    .animate-pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        display: inline-block;
    }

    /* Progress Bars & Custom badges */
    .progress {
        background-color: var(--sidebar-hover) !important;
        height: 8px;
        border-radius: 99px;
        overflow: hidden;
    }
    .progress-bar {
        background: var(--primary-color) !important;
        border-radius: 99px;
    }

    .list-group-item {
        background: transparent !important;
        border-bottom: 1px solid var(--card-border) !important;
    }
    .list-group-item:last-child {
        border-bottom: none !important;
    }
    .stat-label {
        color: var(--text-soft) !important;
        letter-spacing: 0.04em !important;
    }
    .stat-chip {
        background: var(--input-bg) !important;
        border: 1px solid var(--card-border) !important;
        color: var(--text-main) !important;
    }
</style>

<!-- Quick Search Asset Widget on Dashboard -->
<div class="card border-0 shadow-lg rounded-4 p-4 mb-4" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(6, 182, 212, 0.06) 100%) !important; border: 1px solid rgba(99, 102, 241, 0.2) !important;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="p-3 rounded-4 text-white shadow-sm" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
                <i class="bi bi-search fs-4"></i>
            </div>
            <div>
                <h5 class="fw-800 m-0 text-dark">Pencarian Cepat Aset IT</h5>
                <p class="text-muted small m-0 mt-1">Cari perangkat berdasarkan Kode Aset, Serial Number, Nama, atau Penanggung Jawab</p>
            </div>
        </div>
        <form action="index.php" method="GET" class="d-flex gap-2 flex-grow-1" style="max-width: 480px;">
            <input type="hidden" name="page" value="inventaris">
            <div class="input-group">
                <span class="input-group-text bg-white border-0 text-muted"><i class="bi bi-qr-code-scan"></i></span>
                <input type="text" name="search" class="form-control border-0 shadow-sm px-3" placeholder="Ketik Kode Aset / Serial Number..." required>
                <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                    <i class="bi bi-search me-1"></i> Cari
                </button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4 mb-5 animate-fade-in">
    <!-- Stat Card 1 -->
    <div class="col-12 col-sm-6 col-lg-3" id="tour-step-stats">
        <div class="card border-0 h-100 shadow-sm" style="border-radius: 18px;">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <div class="small fw-bold text-muted stat-label mb-1">TOTAL ASET</div>
                    <h2 class="fw-800 text-dark mb-0"><?= $totalAssets ?></h2>
                    <small class="text-muted d-block mt-2"><i class="bi bi-arrow-up-right me-1 text-primary"></i> Aktif di sistem</small>
                </div>
                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-4 fs-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                    <i class="bi bi-box-seam"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Card 2 -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card border-0 h-100 shadow-sm" style="border-radius: 18px;">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <div class="small fw-bold text-muted stat-label mb-1">MAINTENANCE</div>
                    <h2 class="fw-800 text-dark mb-0"><?= $totalMaintenance ?></h2>
                    <small class="text-muted d-block mt-2"><i class="bi bi-calendar-event me-1 text-success"></i> Bulan ini</small>
                </div>
                <div class="bg-success bg-opacity-10 text-success p-3 rounded-4 fs-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                    <i class="bi bi-check2-circle"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Card 3 -->
    <div class="col-12 col-sm-6 col-lg-3">
        <a href="index.php?page=inventaris&filter_kondisi=rusak" class="text-decoration-none d-block h-100">
            <div class="card border-0 h-100 shadow-sm" style="border-radius: 18px;">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="small fw-bold text-muted stat-label mb-1">PERLU TINDAKAN</div>
                        <h2 class="fw-800 text-dark mb-0"><?= $totalPerluTindakan ?></h2>
                        <small class="text-muted d-block mt-2"><i class="bi bi-exclamation-circle me-1 text-warning"></i> Bermasalah</small>
                    </div>
                    <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-4 fs-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Stat Card 4 -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card border-0 h-100 shadow-sm" style="border-radius: 18px;">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <div class="small fw-bold text-muted stat-label mb-1">BIAYA REPAIR</div>
                    <h3 class="fw-800 text-dark mb-0 text-nowrap" style="font-size: 1.35rem; margin-top: 2px;">Rp <?= number_format($totalCost, 0, ',', '.') ?></h3>
                    <small class="text-muted d-block mt-2"><i class="bi bi-graph-up me-1 text-danger"></i> Periode ini</small>
                </div>
                <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-4 fs-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                    <i class="bi bi-wallet2"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-5 animate-fade-in" style="animation-delay: 0.1s;">
    <!-- Welcome Card -->
    <div class="col-md-8">
        <div class="card p-4 border-0 mb-4 h-100 shadow-sm" id="tour-step-welcome">
            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
                <div class="d-flex align-items-center">
                    <div class="bg-dark text-white p-3 rounded-4 me-3">
                        <i class="bi bi-stars fs-4"></i>
                    </div>
                    <div>
                        <h5 class="fw-800 m-0 text-dark">Selamat Datang, <?= isset($_SESSION['nama']) ? htmlspecialchars($_SESSION['nama']) : 'Pengguna' ?>!</h5>
                        <p class="text-muted small m-0">Kelola aset, maintenance, dan repair dari satu tempat.</p>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary btn-sm px-3 shadow-sm" style="border-radius: 20px;" onclick="startInteractiveTour()">
                        <i class="bi bi-compass me-1"></i> Tur Panduan
                    </button>
                    <a href="index.php?page=logs" class="btn btn-secondary btn-sm px-3 shadow-sm" style="border-radius: 20px;">
                        <i class="bi bi-clock-history me-1 text-primary"></i> Log Aktivitas
                    </a>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <div class="p-4 rounded-4 transition-hover">
                        <i class="bi bi-plus-circle-fill text-primary fs-3 mb-3 d-block"></i>
                        <h6 class="fw-700 text-dark">Input Inventaris</h6>
                        <p class="small text-muted mb-3">Daftarkan aset baru.</p>
                        <a href="index.php?page=inventaris" class="btn btn-primary btn-sm w-100 text-white">Tambah Aset</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-4 rounded-4 transition-hover">
                        <i class="bi bi-tools text-success fs-3 mb-3 d-block"></i>
                        <h6 class="fw-700 text-dark">Maintenance</h6>
                        <p class="small text-muted mb-3">Catat hasil pengecekan.</p>
                        <a href="index.php?page=maintenance" class="btn btn-primary btn-sm w-100 text-white">Catat Cek</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-4 rounded-4 transition-hover">
                        <i class="bi bi-wrench-adjustable text-warning fs-3 mb-3 d-block"></i>
                        <h6 class="fw-700 text-dark">Tiket Perbaikan</h6>
                        <p class="small text-muted mb-3">Pantau status perbaikan.</p>
                        <a href="index.php?page=perbaikan" class="btn btn-primary btn-sm w-100 fw-bold">Lihat Tiket</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Aktivitas Terbaru & Google Sheets -->
    <div class="col-md-4">
        <?php
        // Load spreadsheet config
        $google_spreadsheet_id = getenv('GOOGLE_SPREADSHEET_ID') ?: '';
        $spreadsheet_url = $google_spreadsheet_id ? "https://docs.google.com/spreadsheets/d/" . htmlspecialchars($google_spreadsheet_id) . "/edit" : "#";

        if (getenv('VERCEL') || DIRECTORY_SEPARATOR === '/') {
            $sqlite_db_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rekapit_cache.sqlite';
        } else {
            $sqlite_db_path = __DIR__ . '/../database/rekapit_cache.sqlite';
        }
        $metaFile = $sqlite_db_path . '.json';
        $lastSync = "Belum pernah";
        if (file_exists($metaFile)) {
            $meta = json_decode(file_get_contents($metaFile), true);
            if (isset($meta['last_sync'])) {
                $lastSync = format_tanggal_indonesia($meta['last_sync']) . ', ' . date('H:i:s', $meta['last_sync']);
            }
        }
        ?>
        <!-- Google Sheets Card -->
        <div class="card p-4 border-0 mb-4 shadow-sm h-100" style="border-radius: 18px;" id="tour-step-sheets">
            <div class="d-flex align-items-center mb-3">
                <div class="bg-success bg-opacity-10 p-2.5 rounded-3 me-3 text-success d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                    <i class="bi bi-cloud-check fs-4"></i>
                </div>
                <div>
                    <h6 class="fw-800 m-0 text-dark">Google Sheets</h6>
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill small mt-1">
                        <?= $google_spreadsheet_id ? 'Terhubung' : 'Belum Terhubung' ?>
                    </span>
                </div>
            </div>
            <div class="mb-4">
                <div class="small text-muted mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">ID SPREADSHEET</div>
                <div class="fw-bold text-dark text-truncate mb-3" style="max-width: 100%; font-size: 0.85rem;" title="<?= htmlspecialchars($google_spreadsheet_id) ?>">
                    <?= $google_spreadsheet_id ? htmlspecialchars($google_spreadsheet_id) : 'Tidak terkonfigurasi' ?>
                </div>
                <div class="small text-muted mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">SINKRONISASI TERAKHIR</div>
                <div class="fw-bold text-dark" style="font-size: 0.85rem;">
                    <?= $lastSync ?>
                </div>
            </div>
            <?php if ($google_spreadsheet_id): ?>
                <div class="d-grid gap-2">
                    <a href="<?= $spreadsheet_url ?>" target="_blank" class="btn btn-outline-success btn-sm fw-bold py-2 d-flex align-items-center justify-content-center gap-2" style="border-radius: 12px;">
                        <i class="bi bi-box-arrow-up-right"></i> Buka Spreadsheet
                    </a>
                    <button onclick="triggerDashboardSync(this)" class="btn btn-success btn-sm fw-bold py-2 d-flex align-items-center justify-content-center gap-2" style="border-radius: 12px; color: #ffffff !important;">
                        <i class="bi bi-arrow-repeat"></i> Sinkronisasi Sekarang
                    </button>
                </div>
                <script>
                function triggerDashboardSync(btn) {
                    const originalText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sinkronisasi...';
                    
                    fetch('api/sync.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Sinkronisasi berhasil! Halaman akan dimuat ulang.');
                                window.location.reload();
                            } else {
                                alert('Gagal melakukan sinkronisasi: ' + data.error);
                                btn.disabled = false;
                                btn.innerHTML = originalText;
                            }
                        })
                        .catch(err => {
                            alert('Terjadi kesalahan koneksi: ' + err.message);
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                        });
                }
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Panduan Penggunaan / Instruksi Cepat (Collapsible) -->
<div class="accordion mb-5 animate-fade-in" id="accordionGuide" style="animation-delay: 0.15s; border-radius: 18px;">
    <div class="accordion-item border-0 card shadow-sm" style="border-radius: 18px; overflow: hidden;">
        <h2 class="accordion-header" id="headingGuide">
            <button class="accordion-button collapsed fw-800 text-dark bg-transparent border-0 py-3.5 d-flex align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#collapseGuide" aria-expanded="false" aria-controls="collapseGuide" style="box-shadow: none; font-size: 0.95rem;">
                <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3 me-3 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                    <i class="bi bi-info-circle-fill"></i>
                </div>
                Panduan & Instruksi Penggunaan Sistem (Klik untuk membuka)
            </button>
        </h2>
        <div id="collapseGuide" class="accordion-collapse collapse" aria-labelledby="headingGuide" data-bs-parent="#accordionGuide">
            <div class="accordion-body p-4 pt-0">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="h-100 p-3 rounded-4 bg-light border-0 transition-hover">
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge bg-primary rounded-circle p-2 me-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">1</span>
                                <h6 class="fw-bold m-0 text-dark">Registrasi Aset</h6>
                            </div>
                            <p class="small text-muted mb-0">Masuk ke menu <strong>Inventaris</strong>, klik <strong>Tambah Aset</strong>. Isi detail perangkat seperti Merk, SN, Lokasi Cabang, dan Kategori.</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="h-100 p-3 rounded-4 bg-light border-0 transition-hover">
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge bg-success rounded-circle p-2 me-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">2</span>
                                <h6 class="fw-bold m-0 text-dark">Perawatan Berkala</h6>
                            </div>
                            <p class="small text-muted mb-0">Lakukan pengecekan rutin di menu <strong>Maintenance</strong>. Aset yang sudah diperiksa bulan ini otomatis difilter agar tidak terinput ganda.</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="h-100 p-3 rounded-4 bg-light border-0 transition-hover">
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge bg-warning text-dark rounded-circle p-2 me-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">3</span>
                                <h6 class="fw-bold m-0 text-dark">Kelola Perbaikan</h6>
                            </div>
                            <p class="small text-muted mb-0">Jika aset bermasalah, buat tiket di menu <strong>Perbaikan</strong>. Anda bisa memantau status pengerjaan dan melacak total pengeluaran biaya.</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="h-100 p-3 rounded-4 bg-light border-0 transition-hover">
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge bg-info text-dark rounded-circle p-2 me-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">4</span>
                                <h6 class="fw-bold m-0 text-dark">Audit & Laporan</h6>
                            </div>
                            <p class="small text-muted mb-0">Lakukan pencocokan data fisik di menu <strong>Audit Fisik</strong>. Ekspor seluruh laporan ke format Excel melalui menu <strong>Laporan</strong>.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Distribusi Aset & Top 5 Aset Terboros -->
<div class="row g-4 mb-5 animate-fade-in">
    <!-- Distribusi Aset per Cabang -->
    <div class="col-md-6">
        <div class="card p-4 border-0 h-100 shadow-sm">
            <h6 class="fw-800 mb-4 text-dark d-flex align-items-center">
                <i class="bi bi-pie-chart me-2 text-primary"></i> Distribusi Aset per Cabang
            </h6>
            <div class="row align-items-center">
                <div class="col-md-5 d-flex justify-content-center mb-3 mb-md-0">
                    <div style="max-height: 200px; position: relative; width: 100%;">
                        <canvas id="branchDistChart"></canvas>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="row g-2">
                        <?php 
                        $colors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#a855f7', '#06b6d4', '#ec4899', '#3b82f6'];
                        $idx = 0;
                        foreach ($branchDistribution as $branch): 
                            $color = $colors[$idx % count($colors)];
                            $idx++;
                            $pct = ($totalAssets > 0) ? round($branch['total'] / $totalAssets * 100, 1) : 0;
                        ?>
                            <div class="col-6">
                                <div class="p-2 rounded-3 bg-light border-start border-4 d-flex justify-content-between align-items-center" style="border-color: <?= $color ?> !important; font-size: 0.8rem;">
                                    <div class="text-truncate me-2">
                                        <div class="fw-bold text-dark text-truncate" title="<?= htmlspecialchars($branch['nama_cabang']) ?>"><?= htmlspecialchars($branch['nama_cabang']) ?></div>
                                        <small class="text-muted"><?= $pct ?>%</small>
                                    </div>
                                    <span class="badge bg-white text-dark border fw-bold px-2 py-1"><?= $branch['total'] ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top 5 Aset Terboros -->
    <div class="col-md-6">
        <div class="card p-4 border-0 h-100 shadow-sm">
            <h6 class="fw-800 mb-4 text-dark d-flex align-items-center">
                <i class="bi bi-cash-coin me-2 text-danger"></i> Top 5 Aset Terboros (Biaya Perbaikan)
            </h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th>Aset</th>
                            <th>Cabang</th>
                            <th class="text-end">Total Biaya</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($costlyAssets)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted small">
                                    <i class="bi bi-info-circle me-1 fs-5"></i> Belum ada data perbaikan dengan biaya.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($costlyAssets as $asset): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold text-dark"><?= htmlspecialchars($asset['nama_aset']) ?></span>
                                            <small class="text-muted"><?= htmlspecialchars($asset['kode_aset']) ?></small>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($asset['nama_cabang'] ?: '-') ?></td>
                                    <td class="text-end fw-bold text-danger">
                                        Rp <?= number_format($asset['total_biaya'], 0, ',', '.') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('branchDistChart').getContext('2d');
    
    const labels = [
        <?php foreach ($branchDistribution as $branch): ?>
            "<?= htmlspecialchars($branch['nama_cabang']) ?>",
        <?php endforeach; ?>
    ];
    
    const data = [
        <?php foreach ($branchDistribution as $branch): ?>
            <?= $branch['total'] ?>,
        <?php endforeach; ?>
    ];
    
    const colors = [
        <?php 
        $idx = 0;
        foreach ($branchDistribution as $branch): 
            echo "'" . $colors[$idx % count($colors)] . "',";
            $idx++;
        endforeach; 
        ?>
    ];

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed !== null) {
                                label += context.parsed + ' Aset';
                            }
                            return label;
                        }
                    }
                }
            },
            cutout: '70%'
        }
    });
});
</script>

<!-- Perangkat Perlu Tindakan & Aktivitas Terkini -->
<div class="row g-4 mb-5 animate-fade-in" style="animation-delay: 0.2s;">
    <!-- Perangkat Perlu Tindakan / Bermasalah -->
    <div class="col-md-7">
        <div class="card p-4 border-0 shadow-sm h-100" style="border-radius: 18px;">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h6 class="fw-800 text-dark d-flex align-items-center m-0" style="font-size: 0.95rem;">
                    <i class="bi bi-exclamation-octagon-fill me-2 text-danger animate-pulse"></i> Perangkat Rusak / Perlu Tindakan
                </h6>
                <a href="index.php?page=inventaris&filter_kondisi=rusak" class="btn btn-outline-danger btn-sm rounded-pill px-3 py-1.5 fw-bold" style="font-size: 0.72rem;">
                    Lihat Semua
                </a>
            </div>
            <div class="table-responsive" style="font-size: 0.82rem;">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th>Kode</th>
                            <th>Nama Aset</th>
                            <th>Cabang</th>
                            <th>Kondisi</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($brokenAssets)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted small">
                                    <i class="bi bi-emoji-smile me-1 text-success fs-5"></i> Semua perangkat saat ini dalam kondisi Baik.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($brokenAssets as $asset):
                                $is_heavy = $asset['kondisi'] === 'Rusak Berat';
                                $badge_class = $is_heavy ? 'bg-danger bg-opacity-10 text-danger' : 'bg-warning bg-opacity-10 text-warning';
                            ?>
                                <tr>
                                    <td><span class="fw-bold text-dark"><?= $asset['kode_aset'] ?></span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-light p-1.5 rounded-circle me-2">
                                                <i class="bi bi-pc-display text-muted" style="font-size: 0.8rem;"></i>
                                            </div>
                                            <strong class="text-truncate" style="max-width: 140px;" title="<?= htmlspecialchars($asset['nama_aset']) ?>"><?= htmlspecialchars($asset['nama_aset']) ?></strong>
                                        </div>
                                    </td>
                                    <td class="text-truncate" style="max-width: 100px;"><?= htmlspecialchars($asset['nama_cabang'] ?: '-') ?></td>
                                    <td>
                                        <span class="badge <?= $badge_class ?> rounded-pill px-2 py-1 fw-bold" style="font-size: 0.65rem;">
                                            <?= $asset['kondisi'] ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="index.php?page=perbaikan&asset_id=<?= $asset['id'] ?>" class="btn btn-sm btn-primary py-1 px-2.5 shadow-sm rounded-3" style="font-size: 0.72rem;">
                                            Perbaiki
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Aktivitas Terkini -->
    <div class="col-md-5">
        <div class="card p-4 border-0 shadow-sm h-100" style="border-radius: 18px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="fw-800 text-dark d-flex align-items-center m-0" style="font-size: 0.95rem;">
                    <i class="bi bi-clock-history me-2 text-primary"></i> Aktivitas Terkini
                </h6>
                <a href="index.php?page=logs" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-1.5 fw-bold" style="font-size: 0.72rem;">
                    Lihat Semua
                </a>
            </div>
            <div class="list-group list-group-flush" style="font-size: 0.82rem;">
                <?php if (empty($recentLogs)): ?>
                    <div class="text-center py-4 text-muted small">
                        Tidak ada aktivitas yang tercatat.
                    </div>
                <?php else: ?>
                    <?php foreach ($recentLogs as $log): ?>
                        <?php
                        $action = strtoupper($log['action']);
                        // Set semantic colors for tech badges based on the log action
                        $badgeStyle = "background: rgba(99, 102, 241, 0.15); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.25);"; // Default (Indigo)
                        if (strpos($action, 'LOGIN') !== false || strpos($action, 'MASUK') !== false) {
                            $badgeStyle = "background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.25);"; // Green
                        } elseif (strpos($action, 'LOGOUT') !== false || strpos($action, 'KELUAR') !== false || strpos($action, 'DELETE') !== false || strpos($action, 'HAPUS') !== false) {
                            $badgeStyle = "background: rgba(239, 68, 68, 0.15); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.25);"; // Red
                        } elseif (strpos($action, 'UPDATE') !== false || strpos($action, 'UBAH') !== false || strpos($action, 'EDIT') !== false) {
                            $badgeStyle = "background: rgba(245, 158, 11, 0.15); color: #fde047; border: 1px solid rgba(245, 158, 11, 0.25);"; // Yellow
                        }
                        ?>
                        <div class="list-group-item px-0 border-0 mb-2.5 d-flex align-items-start">
                            <div class="bg-primary bg-opacity-10 p-1.5 rounded-circle me-2.5 mt-1 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; border: 1px solid var(--sidebar-border);">
                                <i class="bi bi-person-fill text-primary" style="font-size: 0.8rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-width-0">
                                <div class="d-flex justify-content-between align-items-center gap-2">
                                    <span class="badge fw-bold px-2 py-0.5 text-truncate" style="font-size: 0.65rem; max-width: 100px; <?= $badgeStyle ?>"><?= htmlspecialchars($log['action']) ?></span>
                                    <span class="text-muted small text-truncate" style="font-size: 0.68rem;" title="<?= date('d M Y, H:i:s', strtotime($log['created_at'])) ?>">
                                        <?= htmlspecialchars($log['user_nama'] ?: 'Sistem') ?> &bull; <?= date('d M, H:i', strtotime($log['created_at'])) ?>
                                    </span>
                                </div>
                                <div class="text-muted small mt-1 text-truncate" style="font-size: 0.78rem;" title="<?= htmlspecialchars($log['description']) ?>">
                                    <?= htmlspecialchars($log['description']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Floating Interactive Tour Elements -->
<style>
/* Styling Tour Panduan Interaktif */
.tour-card {
    position: absolute !important;
    z-index: 100000 !important;
    background: #0f172a !important;
    border: 1px solid rgba(99, 102, 241, 0.5) !important;
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.6), 0 0 15px rgba(99, 102, 241, 0.2) !important;
    border-radius: 16px !important;
    padding: 20px !important;
    width: 320px !important;
    color: #f8fafc !important;
    font-family: 'Plus Jakarta Sans', Arial, sans-serif !important;
    opacity: 0;
    pointer-events: none;
    transform: scale(0.95);
    transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
}
.tour-card.active {
    opacity: 1 !important;
    pointer-events: auto !important;
    transform: scale(1) !important;
}
.tour-card-header {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    margin-bottom: 12px !important;
}
.tour-step-badge {
    background: rgba(99, 102, 241, 0.15) !important;
    color: #818cf8 !important;
    border: 1px solid rgba(99, 102, 241, 0.25) !important;
    padding: 2px 8px !important;
    border-radius: 99px !important;
    font-size: 0.7rem !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
}
.tour-close-btn {
    background: transparent !important;
    border: none !important;
    color: #94a3b8 !important;
    font-size: 1.2rem !important;
    cursor: pointer !important;
    line-height: 1 !important;
    padding: 0 !important;
    transition: color 0.2s ease !important;
}
.tour-close-btn:hover {
    color: #ef4444 !important;
}
.tour-title {
    font-size: 0.95rem !important;
    font-weight: 800 !important;
    color: #ffffff !important;
    margin-bottom: 8px !important;
}
.tour-desc {
    font-size: 0.8rem !important;
    line-height: 1.5 !important;
    color: #94a3b8 !important;
    margin-bottom: 16px !important;
}
.tour-card-footer {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
}
.tour-dots {
    display: flex !important;
    gap: 4px !important;
}
.tour-dot {
    width: 6px !important;
    height: 6px !important;
    border-radius: 50% !important;
    background: #475569 !important;
    transition: all 0.2s ease !important;
}
.tour-dot.active {
    background: #6366f1 !important;
    width: 12px !important;
    border-radius: 3px !important;
}
.tour-nav-btns {
    display: flex !important;
    gap: 6px !important;
}
.tour-nav-btns button {
    padding: 6px 12px !important;
    border-radius: 8px !important;
    font-size: 0.72rem !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    transition: all 0.2s ease !important;
}
.tour-btn-prev {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    color: #cbd5e1 !important;
}
.tour-btn-prev:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
}
.tour-btn-next {
    background: #6366f1 !important;
    border: 1px solid #6366f1 !important;
    color: #ffffff !important;
}
.tour-btn-next:hover {
    background: #4f46e5 !important;
}
</style>

<script>
let currentTourStep = 0;
const tourSteps = [
    {
        target: 'tour-step-stats',
        title: 'Statistik Sistem Utama',
        desc: 'Di sini Anda dapat memantau indikator operasional secara real-time, seperti total aset terdaftar, jumlah perawatan terjadwal, aset bermasalah (perlu tindakan), serta total biaya perbaikan bulan ini.',
        position: 'bottom'
    },
    {
        target: 'tour-step-welcome',
        title: 'Pintasan Operasional Cepat',
        desc: 'Gunakan tombol aksi cepat ini untuk langsung mendaftarkan aset baru, mengisi laporan pemeriksaan maintenance rutin, atau membuka daftar tiket perbaikan.',
        position: 'bottom'
    },
    {
        target: 'tour-step-sheets',
        title: 'Konektivitas Google Sheets',
        desc: 'Semua perubahan data langsung disimpan ke Google Sheets (write-through). Data dibaca dari SQLite cache lokal (super cepat). Klik tombol "Sinkronisasi" di sini jika Anda melakukan perubahan manual di Google Sheets agar data lokal ter-update.',
        position: 'left'
    },
    {
        target: 'sidebarContainer',
        title: 'Navigasi Menu Utama',
        desc: 'Menu navigasi di sisi kiri mengelompokkan fitur aplikasi: Monitoring (Dashboard & Log), Operasional (Maintenance, Perbaikan, Helpdesk, Audit), dan Manajemen Aset (Inventaris, Cetak Kartu, Kategori, Mutasi).',
        position: 'right'
    }
];

function startInteractiveTour() {
    // Ensure existing overlay is cleared
    let oldOverlay = document.getElementById('tourOverlay');
    if (oldOverlay) oldOverlay.remove();
    
    // Create Tour Card if not exist
    let tourCard = document.getElementById('tourCard');
    if (!tourCard) {
        tourCard = document.createElement('div');
        tourCard.id = 'tourCard';
        tourCard.className = 'tour-card';
        document.body.appendChild(tourCard);
    }
    
    currentTourStep = 0;
    showTourStep(0);
}

function showTourStep(stepIndex) {
    const step = tourSteps[stepIndex];
    let targetElement = document.getElementById(step.target);
    
    // Handle mobile view sidebar (might be collapsed)
    if (step.target === 'sidebarContainer' && window.innerWidth < 1024) {
        // If sidebar is hidden/collapsed on mobile, target the mobile toggle button instead
        const mobileToggle = document.getElementById('sidebarToggleBtn');
        if (mobileToggle) targetElement = mobileToggle;
    }
    
    // Update spotlight viewport panels
    updateTourSpotlight(targetElement);
    
    if (targetElement) {
        // Scroll target into view gently
        targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    const card = document.getElementById('tourCard');
    card.classList.add('active');
    
    // Generate dots progress indicator
    let dotsHtml = '';
    for (let i = 0; i < tourSteps.length; i++) {
        dotsHtml += `<span class="tour-dot ${i === stepIndex ? 'active' : ''}"></span>`;
    }
    
    // Set Next/Finish button
    const isLastStep = stepIndex === tourSteps.length - 1;
    const nextBtnText = isLastStep ? 'Selesai' : 'Lanjut <i class="bi bi-chevron-right ms-1"></i>';
    
    card.innerHTML = `
        <div class="tour-card-header">
            <span class="tour-step-badge">Langkah ${stepIndex + 1} dari ${tourSteps.length}</span>
            <button class="tour-close-btn" onclick="endInteractiveTour()">&times;</button>
        </div>
        <div class="tour-title">${step.title}</div>
        <div class="tour-desc">${step.desc}</div>
        <div class="tour-card-footer">
            <div class="tour-dots">${dotsHtml}</div>
            <div class="tour-nav-btns">
                ${stepIndex > 0 ? `<button class="tour-btn-prev" onclick="prevTourStep()"><i class="bi bi-chevron-left me-1"></i> Kembali</button>` : ''}
                <button class="tour-btn-next" onclick="nextTourStep()">${nextBtnText}</button>
            </div>
        </div>
    `;
    
    // Wait for DOM layout adjustment before positioning
    setTimeout(() => {
        positionTourCard(card, targetElement, step.position);
    }, 50);
}

function updateTourSpotlight(target) {
    let overlay = document.getElementById('tourOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'tourOverlay';
        overlay.style.position = 'fixed';
        overlay.style.top = '0';
        overlay.style.left = '0';
        overlay.style.width = '100vw';
        overlay.style.height = '100vh';
        overlay.style.pointerEvents = 'none';
        overlay.style.zIndex = '99999';
        
        // Create 4 shadow panels covering viewport except target area
        const panels = ['top', 'left', 'right', 'bottom'];
        panels.forEach(p => {
            const div = document.createElement('div');
            div.id = `tour-panel-${p}`;
            div.style.position = 'fixed';
            div.style.backgroundColor = 'rgba(5, 7, 15, 0.65)';
            div.style.backdropFilter = 'blur(2px)';
            div.style.webkitBackdropFilter = 'blur(2px)';
            div.style.transition = 'all 0.3s ease';
            div.style.pointerEvents = 'auto'; // clicking panels can trigger close
            
            div.addEventListener('click', endInteractiveTour);
            overlay.appendChild(div);
        });
        
        // Spotlight glow border box
        const glow = document.createElement('div');
        glow.id = 'tour-spotlight-glow';
        glow.style.position = 'absolute';
        glow.style.border = '2.5px solid #6366f1';
        glow.style.borderRadius = '18px';
        glow.style.boxShadow = '0 0 15px rgba(99, 102, 241, 0.5)';
        glow.style.pointerEvents = 'none';
        glow.style.transition = 'all 0.3s ease';
        glow.style.zIndex = '100000';
        overlay.appendChild(glow);
        
        document.body.appendChild(overlay);
    }
    
    const topPanel = document.getElementById('tour-panel-top');
    const leftPanel = document.getElementById('tour-panel-left');
    const rightPanel = document.getElementById('tour-panel-right');
    const bottomPanel = document.getElementById('tour-panel-bottom');
    const glow = document.getElementById('tour-spotlight-glow');
    
    if (!target) {
        // Spotlight hidden, black out viewport
        topPanel.style.left = '0'; topPanel.style.top = '0'; topPanel.style.width = '100vw'; topPanel.style.height = '100vh';
        leftPanel.style.width = '0'; rightPanel.style.width = '0'; bottomPanel.style.height = '0';
        glow.style.display = 'none';
        return;
    }
    
    glow.style.display = 'block';
    const rect = target.getBoundingClientRect();
    const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    
    // Position glow outline box on target
    glow.style.top = (rect.top + scrollTop - 2.5) + 'px';
    glow.style.left = (rect.left + scrollLeft - 2.5) + 'px';
    glow.style.width = (rect.width + 5) + 'px';
    glow.style.height = (rect.height + 5) + 'px';
    
    // Position the 4 viewport panels surrounding target bounding rect
    topPanel.style.left = '0';
    topPanel.style.top = '0';
    topPanel.style.width = '100vw';
    topPanel.style.height = rect.top + 'px';
    
    leftPanel.style.left = '0';
    leftPanel.style.top = rect.top + 'px';
    leftPanel.style.width = rect.left + 'px';
    leftPanel.style.height = rect.height + 'px';
    
    rightPanel.style.left = rect.right + 'px';
    rightPanel.style.top = rect.top + 'px';
    rightPanel.style.width = (window.innerWidth - rect.right) + 'px';
    rightPanel.style.height = rect.height + 'px';
    
    bottomPanel.style.left = '0';
    bottomPanel.style.top = rect.bottom + 'px';
    bottomPanel.style.width = '100vw';
    bottomPanel.style.height = (window.innerHeight - rect.bottom) + 'px';
}

function positionTourCard(card, target, position) {
    if (!target) {
        // Centered fallback if target is not found
        card.style.position = 'fixed';
        card.style.top = '50%';
        card.style.left = '50%';
        card.style.transform = 'translate(-50%, -50%) scale(1)';
        return;
    }
    
    card.style.position = 'absolute';
    const rect = target.getBoundingClientRect();
    const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    
    let top, left;
    const margin = 15;
    const cardWidth = 320;
    const cardHeight = card.offsetHeight || 160;
    
    if (position === 'bottom') {
        top = rect.bottom + scrollTop + margin;
        left = rect.left + scrollLeft + (rect.width / 2) - (cardWidth / 2);
    } else if (position === 'top') {
        top = rect.top + scrollTop - cardHeight - margin;
        left = rect.left + scrollLeft + (rect.width / 2) - (cardWidth / 2);
    } else if (position === 'left') {
        top = rect.top + scrollTop + (rect.height / 2) - (cardHeight / 2);
        left = rect.left + scrollLeft - cardWidth - margin;
    } else if (position === 'right') {
        top = rect.top + scrollTop + (rect.height / 2) - (cardHeight / 2);
        left = rect.right + scrollLeft + margin;
    }
    
    // Bounds check to prevent bleeding off edge of document viewport
    const screenWidth = window.innerWidth;
    if (left < 10) left = 10;
    if (left + cardWidth > screenWidth - 10) left = screenWidth - cardWidth - 10;
    if (top < 10) top = 10;
    
    card.style.top = top + 'px';
    card.style.left = left + 'px';
}

function nextTourStep() {
    if (currentTourStep < tourSteps.length - 1) {
        currentTourStep++;
        showTourStep(currentTourStep);
    } else {
        endInteractiveTour();
    }
}

function prevTourStep() {
    if (currentTourStep > 0) {
        currentTourStep--;
        showTourStep(currentTourStep);
    }
}

function endInteractiveTour() {
    const overlay = document.getElementById('tourOverlay');
    const card = document.getElementById('tourCard');
    
    if (overlay) overlay.remove();
    if (card) {
        card.classList.remove('active');
        // Let transition complete then remove card from DOM
        setTimeout(() => {
            if (!card.classList.contains('active')) card.remove();
        }, 300);
    }
}
</script>
