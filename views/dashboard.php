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

} catch (PDOException $e) {
    error_log("Dashboard PDO Error: " . $e->getMessage());
    $totalAssets = $totalMaintenance = $totalRepairs = $totalCost = $totalPerluTindakan = 0;
    $brokenAssets = [];
    $recentLogs = [];
    $branchDistribution = [];
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
        background: #ffffff !important;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        transition: all 0.2s ease;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
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
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
    }
    .fw-800 { font-weight: 800; }
    .fw-700 { font-weight: 700; }
    .transition-hover {
        transition: all 0.2s ease;
        border: 1px solid #e5e7eb !important;
        background: #f9fafb !important;
    }
    .transition-hover:hover {
        background-color: #ffffff !important;
        border-color: #d1d5db !important;
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
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
        background-color: #e2e8f0;
        height: 8px;
        border-radius: 99px;
        overflow: hidden;
    }
    .progress-bar {
        background: #111827 !important;
        border-radius: 99px;
    }

    .list-group-item {
        background: transparent !important;
        border-bottom: 1px solid rgba(226, 232, 240, 0.5) !important;
    }
    .list-group-item:last-child {
        border-bottom: none !important;
    }
    .stat-label {
        color: #6b7280 !important;
        letter-spacing: 0.04em !important;
    }
    .stat-chip {
        background: #f3f4f6 !important;
        border: 1px solid #e5e7eb !important;
        color: #111827 !important;
    }
</style>

<div class="row g-4 mb-5 animate-fade-in">
    <!-- Stat Card 1 -->
    <div class="col-md-3">
        <div class="lux-card" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);">
            <!-- Glossy Glass Overlay -->
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 100%); pointer-events: none; z-index: 0;"></div>
            <div class="card-body p-4 position-relative text-white" style="z-index: 1;">
                <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 5rem; transform: translate(15%, -15%); pointer-events: none; z-index: 0;">
                    <i class="bi bi-box-seam"></i>
                </div>
                <div class="small fw-bold mb-1 stat-label">TOTAL ASET</div>
                <h2 class="fw-800 mb-0"><?= $totalAssets ?></h2>
                <div class="mt-3">
                    <span class="badge stat-chip rounded-pill small">
                        <i class="bi bi-arrow-up-right me-1"></i> Aktif
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Card 2 -->
    <div class="col-md-3">
        <div class="lux-card" style="background: linear-gradient(135deg, #059669 0%, #0d9488 100%);">
            <!-- Glossy Glass Overlay -->
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 100%); pointer-events: none; z-index: 0;"></div>
            <div class="card-body p-4 position-relative text-white" style="z-index: 1;">
                <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 5rem; transform: translate(15%, -15%); pointer-events: none; z-index: 0;">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div class="small fw-bold mb-1 stat-label">MAINTENANCE</div>
                <h2 class="fw-800 mb-0"><?= $totalMaintenance ?></h2>
                <div class="mt-3">
                    <span class="badge stat-chip rounded-pill small">
                        <i class="bi bi-calendar-event me-1"></i> Bulan ini
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Card 3 -->
    <div class="col-md-3">
        <a href="index.php?page=inventaris&filter_kondisi=rusak" class="text-decoration-none d-block h-100" style="color: inherit;">
            <div class="lux-card h-100" style="background: linear-gradient(135deg, #d97706 0%, #db2777 100%);">
                <!-- Glossy Glass Overlay -->
                <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 100%); pointer-events: none; z-index: 0;"></div>
                <div class="card-body p-4 position-relative text-white" style="z-index: 1;">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 5rem; transform: translate(15%, -15%); pointer-events: none; z-index: 0;">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="small fw-bold mb-1 stat-label">PERLU TINDAKAN</div>
                    <h2 class="fw-800 mb-0"><?= $totalPerluTindakan ?></h2>
                    <div class="mt-3">
                        <span class="badge stat-chip rounded-pill small">
                            <i class="bi bi-exclamation-circle me-1"></i> Bermasalah
                        </span>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Stat Card 4 -->
    <div class="col-md-3">
        <div class="lux-card" style="background: linear-gradient(135deg, #dc2626 0%, #ea580c 100%);">
            <!-- Glossy Glass Overlay -->
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 100%); pointer-events: none; z-index: 0;"></div>
            <div class="card-body p-4 position-relative text-white" style="z-index: 1;">
                <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 5rem; transform: translate(15%, -15%); pointer-events: none; z-index: 0;">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div class="small fw-bold mb-1 stat-label">BIAYA REPAIR</div>
                <h2 class="fw-800 mb-0 text-nowrap" style="font-size: 1.45rem;">Rp <?= number_format($totalCost, 0, ',', '.') ?></h2>
                <div class="mt-3">
                    <span class="badge stat-chip rounded-pill small">
                        <i class="bi bi-graph-up me-1"></i> Periode ini
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 animate-fade-in" style="animation-delay: 0.1s;">
    <!-- Welcome Card -->
    <div class="col-md-8">
        <div class="card p-4 border-0 mb-4 h-100">
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
                <a href="index.php?page=logs" class="btn btn-secondary btn-sm px-3 shadow-sm" style="border-radius: 20px;">
                    <i class="bi bi-clock-history me-1 text-primary"></i> Log Aktivitas
                </a>
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
                $lastSync = date('d M Y, H:i:s', $meta['last_sync']);
            }
        }
        ?>
        <!-- Google Sheets Card -->
        <div class="card p-4 border-0 mb-4 shadow-sm text-white" style="background: linear-gradient(135deg, #107c41 0%, #1f9a55 100%); border-radius: 18px; position: relative; overflow: hidden;">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%); pointer-events: none;"></div>
            <div class="position-absolute top-0 end-0 p-3 opacity-10" style="font-size: 5rem; transform: translate(10%, -10%); pointer-events: none;">
                <i class="bi bi-file-earmark-spreadsheet text-white"></i>
            </div>
            <div class="d-flex align-items-center mb-3">
                <div class="bg-white bg-opacity-20 p-2 rounded-3 me-3 text-white d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                    <i class="bi bi-cloud-check fs-4"></i>
                </div>
                <div>
                    <h6 class="fw-800 m-0">Koneksi Google Sheets</h6>
                    <span class="badge bg-white bg-opacity-25 rounded-pill small mt-1">
                        <?= $google_spreadsheet_id ? 'Terhubung' : 'Belum Terhubung' ?>
                    </span>
                </div>
            </div>
            <div class="mb-4">
                <div class="small text-white text-opacity-75 mb-1" style="font-size: 0.75rem; letter-spacing: 0.05em;">ID SPREADSHEET</div>
                <div class="fw-bold text-truncate mb-3" style="max-width: 100%; font-size: 0.85rem;" title="<?= htmlspecialchars($google_spreadsheet_id) ?>">
                    <?= $google_spreadsheet_id ? htmlspecialchars($google_spreadsheet_id) : 'Tidak terkonfigurasi' ?>
                </div>
                <div class="small text-white text-opacity-75 mb-1" style="font-size: 0.75rem; letter-spacing: 0.05em;">SINKRONISASI TERAKHIR</div>
                <div class="fw-bold" style="font-size: 0.85rem;">
                    <?= $lastSync ?>
                </div>
            </div>
            <?php if ($google_spreadsheet_id): ?>
                <div class="d-grid gap-2">
                    <a href="<?= $spreadsheet_url ?>" target="_blank" class="btn btn-light btn-sm fw-bold border-0 text-success py-2 d-flex align-items-center justify-content-center gap-2" style="border-radius: 12px; box-shadow: 0 4px 12px rgba(16, 124, 65, 0.2);">
                        <i class="bi bi-box-arrow-up-right"></i> Buka Spreadsheet
                    </a>
                    <button onclick="triggerDashboardSync(this)" class="btn btn-outline-light btn-sm fw-bold py-2 d-flex align-items-center justify-content-center gap-2" style="border-radius: 12px;">
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

<!-- Panduan Penggunaan / Instruksi Cepat -->
<div class="row g-4 mb-5 animate-fade-in" style="animation-delay: 0.15s;">
    <div class="col-md-12">
        <div class="card p-4 border-0 shadow-sm">
            <div class="d-flex align-items-center mb-4">
                <div class="bg-primary bg-opacity-10 p-3 rounded-4 me-3 text-primary">
                    <i class="bi bi-info-circle fs-4"></i>
                </div>
                <div>
                    <h6 class="fw-800 m-0 text-dark">Panduan & Instruksi Penggunaan Sistem</h6>
                    <p class="text-muted small m-0">Ikuti langkah-langkah berikut untuk mengoptimalkan pengelolaan aset IT Anda.</p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-3">
                    <div class="h-100 p-3 rounded-4 bg-white border border-light shadow-sm transition-hover">
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge bg-primary rounded-circle p-2 me-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">1</span>
                            <h6 class="fw-bold m-0 text-dark">Registrasi Aset</h6>
                        </div>
                        <p class="small text-muted mb-0">Masuk ke menu <strong>Inventaris</strong>, klik <strong>Tambah Aset</strong>. Isi detail perangkat seperti Merk, SN, Lokasi Cabang, dan Kategori.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="h-100 p-3 rounded-4 bg-white border border-light shadow-sm transition-hover">
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge bg-success rounded-circle p-2 me-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">2</span>
                            <h6 class="fw-bold m-0 text-dark">Perawatan Berkala</h6>
                        </div>
                        <p class="small text-muted mb-0">Lakukan pengecekan rutin di menu <strong>Maintenance</strong>. Aset yang sudah diperiksa bulan ini otomatis difilter agar tidak terinput ganda.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="h-100 p-3 rounded-4 bg-white border border-light shadow-sm transition-hover">
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge bg-warning text-dark rounded-circle p-2 me-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">3</span>
                            <h6 class="fw-bold m-0 text-dark">Kelola Perbaikan</h6>
                        </div>
                        <p class="small text-muted mb-0">Jika aset bermasalah, buat tiket di menu <strong>Perbaikan</strong>. Anda bisa memantau status pengerjaan dan melacak total pengeluaran biaya.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="h-100 p-3 rounded-4 bg-white border border-light shadow-sm transition-hover">
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

<!-- Distribusi Aset per Cabang -->
<div class="row g-4 mb-5 animate-fade-in">
    <div class="col-md-12">
        <div class="card p-4 border-0">
            <h6 class="fw-800 mb-4 text-dark d-flex align-items-center">
                <i class="bi bi-pie-chart me-2 text-primary"></i> Distribusi Aset per Cabang
            </h6>
            <div class="row align-items-center">
                <div class="col-md-5 d-flex justify-content-center">
                    <div style="max-height: 250px; position: relative; width: 100%;">
                        <canvas id="branchDistChart"></canvas>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="row g-3">
                        <?php 
                        $colors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#a855f7', '#06b6d4', '#ec4899', '#3b82f6'];
                        $idx = 0;
                        foreach ($branchDistribution as $branch): 
                            $color = $colors[$idx % count($colors)];
                            $idx++;
                            $pct = ($totalAssets > 0) ? round($branch['total'] / $totalAssets * 100, 1) : 0;
                        ?>
                            <div class="col-md-6">
                                <div class="p-3 rounded-3 bg-light border-start border-4 d-flex justify-content-between align-items-center" style="border-color: <?= $color ?> !important;">
                                    <div>
                                        <div class="small fw-bold text-dark"><?= htmlspecialchars($branch['nama_cabang']) ?></div>
                                        <small class="text-muted"><?= $pct ?>% dari total</small>
                                    </div>
                                    <span class="badge bg-white text-dark border fw-bold px-2.5 py-1.5"><?= $branch['total'] ?> Aset</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
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

<!-- Perangkat Perlu Tindakan / Bermasalah -->
<div class="row g-4 mb-5 animate-fade-in" style="animation-delay: 0.2s;">
    <div class="col-md-12">
        <div class="card p-4 border-0 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h6 class="fw-800 text-dark d-flex align-items-center m-0">
                    <i class="bi bi-exclamation-octagon-fill me-2 text-danger animate-pulse"></i> Perangkat Rusak / Perlu Tindakan
                </h6>
                <a href="index.php?page=inventaris&filter_kondisi=rusak" class="btn btn-outline-danger btn-sm rounded-pill px-3 py-1 fw-bold">
                    Lihat Semua
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th>Kode Aset</th>
                            <th>Nama Aset</th>
                            <th>Cabang</th>
                            <th>Divisi</th>
                            <th>Kondisi</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($brokenAssets)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted small">
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
                                            <div class="bg-light p-2 rounded-circle me-3">
                                                <i class="bi bi-pc-display text-muted"></i>
                                            </div>
                                            <strong><?= $asset['nama_aset'] ?></strong>
                                        </div>
                                    </td>
                                    <td><?= $asset['nama_cabang'] ?: '-' ?></td>
                                    <td><?= $asset['nama_divisi'] ?: '-' ?></td>
                                    <td>
                                        <span class="badge <?= $badge_class ?> rounded-pill px-2.5 py-1.5 fw-bold">
                                            <i class="bi bi-exclamation-circle-fill me-1"></i><?= $asset['kondisi'] ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="index.php?page=perbaikan&asset_id=<?= $asset['id'] ?>" class="btn btn-sm btn-primary py-1.5 shadow-sm rounded-3">
                                            <i class="bi bi-wrench-adjustable"></i> Perbaiki
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
</div>

<!-- Aktivitas Terkini di paling bawah -->
<div class="row g-4 mb-5 animate-fade-in" style="animation-delay: 0.25s;">
    <div class="col-md-12">
        <div class="card p-4 border-0 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="fw-800 text-dark d-flex align-items-center m-0">
                    <i class="bi bi-clock-history me-2 text-primary"></i> Aktivitas Terkini
                </h6>
                <a href="index.php?page=logs" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-1 fw-bold">
                    Lihat Semua Log
                </a>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($recentLogs)): ?>
                    <div class="text-center py-4 text-muted small">
                        Tidak ada aktivitas yang tercatat.
                    </div>
                <?php else: ?>
                    <?php foreach ($recentLogs as $log): ?>
                        <div class="list-group-item px-0 border-0 mb-3 d-flex align-items-start">
                            <div class="bg-light p-2 rounded-circle me-3 mt-1">
                                <i class="bi bi-person-fill text-secondary"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-secondary-subtle text-dark border-0 fw-bold px-2 py-1" style="font-size: 0.75rem;"><?= htmlspecialchars($log['action']) ?></span>
                                    <span class="text-muted small" style="font-size: 0.75rem;" title="<?= date('d M Y, H:i:s', strtotime($log['created_at'])) ?>">
                                        <?= htmlspecialchars($log['user_nama'] ?: 'Sistem') ?> &bull; <?= date('d M Y, H:i', strtotime($log['created_at'])) ?>
                                    </span>
                                </div>
                                <div class="text-muted small mt-2" style="font-size: 0.8rem; line-height: 1.4;">
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
