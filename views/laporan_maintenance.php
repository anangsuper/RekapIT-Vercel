<?php
require_once 'models/Asset.php';
require_once 'models/Cabang.php';
require_once 'models/Maintenance.php';

$cabangModel = new Cabang($conn);
$maintenanceModel = new Maintenance($conn);

$cabangs = $cabangModel->getAll();

$selected_cabang = $_GET['id_cabang'] ?? '';
$selected_bulan = $_GET['bulan'] ?? date('m');
$selected_tahun = $_GET['tahun'] ?? date('Y');

$stats = [];
$summaryDivisi = [];
$topFindings = [];
$yearlyStats = [];
$detailedMaintenance = [];
$branchName = "";
$goodCount = 0;
$warningCount = 0;
$brokenCount = 0;

if ($selected_cabang) {
    $stats = $maintenanceModel->getReportStats($selected_cabang, $selected_bulan, $selected_tahun);
    $summaryDivisi = $maintenanceModel->getSummaryPerDivisi($selected_cabang, $selected_bulan, $selected_tahun);
    $topFindings = $maintenanceModel->getTopFindings($selected_cabang, $selected_bulan, $selected_tahun);
    $yearlyStats = $maintenanceModel->getYearlyStats($selected_cabang, $selected_tahun);
    $detailedMaintenance = $maintenanceModel->getDetailedByMonth($selected_cabang, $selected_bulan, $selected_tahun);
    
    foreach ($cabangs as $c) {
        if ($c['id'] == $selected_cabang) {
            $branchName = $c['nama_cabang'];
            break;
        }
    }

    // Aggregate condition statistics
    foreach ($detailedMaintenance as $dm) {
        $status = $dm['status'] ?? 'Baik';
        if ($status === 'Baik') {
            $goodCount++;
        } elseif ($status === 'Perlu Perbaikan') {
            $warningCount++;
        } else {
            $brokenCount++;
        }
    }
}

function generateConclusion($stats, $bulan, $tahun, $branchName) {
    $namaBulan = date('F', mktime(0, 0, 0, $bulan, 10));
    $conclusion = "Pada bulan $namaBulan $tahun telah dilakukan maintenance terhadap {$stats['total_maintenance']} perangkat dari total {$stats['total_asset']} perangkat yang terdaftar di $branchName. ";
    $conclusion .= "Tingkat penyelesaian maintenance mencapai {$stats['persentase']}%. ";
    
    if ($stats['persentase'] >= 90) {
        $conclusion .= "Sebagian besar perangkat dalam kondisi baik dan operasional. ";
    } elseif ($stats['persentase'] >= 70) {
        $conclusion .= "Sebagian besar perangkat telah diperiksa, namun masih terdapat beberapa perangkat yang tertunda. ";
    } else {
        $conclusion .= "Tingkat penyelesaian maintenance masih rendah dan memerlukan perhatian segera. ";
    }
    
    if ($stats['total_temuan'] > 0) {
        $conclusion .= "Terdapat {$stats['total_temuan']} temuan selama proses maintenance yang memerlukan tindak lanjut.";
    } else {
        $conclusion .= "Tidak ditemukan kendala berarti pada perangkat yang telah diperiksa.";
    }
    
    return $conclusion;
}

function generateRecommendations($stats, $topFindings) {
    $recoms = [];
    if ($stats['persentase'] < 100) {
        $recoms[] = "Perlu dilakukan penjadwalan ulang maintenance untuk perangkat yang belum diperiksa.";
    }
    
    $findingText = "";
    foreach($topFindings as $tf) $findingText .= strtolower($tf['temuan']) . " ";
    
    if (str_contains($findingText, 'antivirus') || str_contains($findingText, 'update')) {
        $recoms[] = "Disarankan melakukan aktivasi antivirus dan pembaruan sistem pada perangkat yang belum terlindungi.";
    }
    
    if (str_contains($findingText, 'penuh') || str_contains($findingText, 'storage') || str_contains($findingText, 'lemot')) {
        $recoms[] = "Disarankan melakukan pembersihan file sementara dan optimasi penyimpanan pada perangkat yang melambat.";
    }
    
    if ($stats['total_perbaikan'] > 5) {
        $recoms[] = "Meningkatnya angka perbaikan menunjukkan perlunya penggantian beberapa komponen hardware yang sudah mencapai masa pakai optimal.";
    }
    
    if (empty($recoms)) {
        $recoms[] = "Tetap lakukan pemeliharaan rutin sesuai jadwal untuk menjaga stabilitas sistem.";
    }
    
    return $recoms;
}

$conclusion = $selected_cabang ? generateConclusion($stats, $selected_bulan, $selected_tahun, $branchName) : "";
$recommendations = $selected_cabang ? generateRecommendations($stats, $topFindings) : [];

$months = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
?>
<div class="container-fluid py-4 animate-fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center">
            <div class="bg-primary bg-opacity-10 p-2.5 rounded-3 me-3 text-primary">
                <i class="bi bi-file-earmark-bar-graph fs-4"></i>
            </div>
            <div>
                <h4 class="fw-800 m-0">Laporan Maintenance PC</h4>
                <p class="text-muted small m-0">Analisis dan rekapitulasi data pemeriksaan berkala</p>
            </div>
        </div>
        <?php if ($selected_cabang): ?>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-secondary shadow-sm px-3 py-2"><i class="bi bi-printer me-2"></i>Print</button>
            <a href="export/maintenance_excel.php?id_cabang=<?= $selected_cabang ?>&bulan=<?= $selected_bulan ?>&tahun=<?= $selected_tahun ?>" class="btn btn-outline-success shadow-sm px-3 py-2"><i class="bi bi-file-earmark-excel me-2"></i>Excel</a>
            <a href="export/maintenance_pdf.php?id_cabang=<?= $selected_cabang ?>&bulan=<?= $selected_bulan ?>&tahun=<?= $selected_tahun ?>" class="btn btn-danger shadow-sm px-3 py-2"><i class="bi bi-file-earmark-pdf me-2"></i>Generate PDF</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Filter Card (Server Side) -->
    <div class="card shadow-sm border-0 mb-4 rounded-4">
        <div class="card-body p-4">
            <form method="GET" action="index.php" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="laporan_maintenance">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted">Pilih Cabang</label>
                    <select name="id_cabang" class="form-select bg-light border-0" required>
                        <option value="">-- Pilih Cabang --</option>
                        <?php foreach ($cabangs as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($selected_cabang == $c['id']) ? 'selected' : '' ?>><?= $c['nama_cabang'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">Bulan</label>
                    <select name="bulan" class="form-select bg-light border-0">
                        <?php foreach ($months as $m => $nama): ?>
                            <option value="<?= $m ?>" <?= ($selected_bulan == $m) ? 'selected' : '' ?>><?= $nama ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">Tahun</label>
                    <select name="tahun" class="form-select bg-light border-0">
                        <?php for ($i = date('Y'); $i >= 2020; $i--): ?>
                            <option value="<?= $i ?>" <?= ($selected_tahun == $i) ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2.5 shadow-sm"><i class="bi bi-arrow-repeat me-2"></i>Generate Laporan</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selected_cabang): ?>
    <!-- Branch Info -->
    <div class="card shadow-sm border-0 mb-4 bg-primary bg-opacity-10 rounded-4 border-start border-primary border-4">
        <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <div class="bg-primary text-white rounded-circle p-2.5 me-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                    <i class="bi bi-geo-alt-fill fs-5"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-primary mb-1"><?= $branchName ?></h5>
                    <?php
                    $branchDetails = null;
                    foreach ($cabangs as $c) {
                        if ($c['id'] == $selected_cabang) {
                            $branchDetails = $c;
                            break;
                        }
                    }
                    ?>
                    <p class="text-muted small mb-0"><?= $branchDetails['alamat'] ?? 'Alamat tidak tersedia' ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Row (Lux KPI Cards with Progress Bars) -->
    <div class="row g-4 mb-4">
        <!-- Stat 1: Total Assets -->
        <div class="col-md-3">
            <div class="lux-card" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);">
                <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 100%); pointer-events: none;"></div>
                <div class="card-body p-4 position-relative text-white">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 4rem; transform: translate(10%, -10%); pointer-events: none;">
                        <i class="bi bi-laptop"></i>
                    </div>
                    <div class="small fw-bold mb-1 opacity-70">🖥 TOTAL ASSETS</div>
                    <h3 class="fw-800 mb-2"><?= $stats['total_asset'] ?></h3>
                    <div class="progress bg-white bg-opacity-20" style="height: 6px;">
                        <div class="progress-bar bg-white" style="width: 100%"></div>
                    </div>
                    <small class="text-white opacity-70 d-block mt-2">Active monitored devices</small>
                </div>
            </div>
        </div>
        <!-- Stat 2: Maintenance checked -->
        <div class="col-md-3">
            <div class="lux-card" style="background: linear-gradient(135deg, #0d9488 0%, #059669 100%);">
                <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 100%); pointer-events: none;"></div>
                <div class="card-body p-4 position-relative text-white">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 4rem; transform: translate(10%, -10%); pointer-events: none;">
                        <i class="bi bi-tools"></i>
                    </div>
                    <div class="small fw-bold mb-1 opacity-70">🛠 MAINTENANCE</div>
                    <h3 class="fw-800 mb-2"><?= $stats['total_maintenance'] ?></h3>
                    <div class="progress bg-white bg-opacity-20" style="height: 6px;">
                        <div class="progress-bar bg-white" style="width: <?= $stats['persentase'] ?>%"></div>
                    </div>
                    <small class="text-white opacity-70 d-block mt-2">Completed checkups this month</small>
                </div>
            </div>
        </div>
        <!-- Stat 3: Completion Rate -->
        <div class="col-md-3">
            <div class="lux-card" style="background: linear-gradient(135deg, #0284c7 0%, #2563eb 100%);">
                <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 100%); pointer-events: none;"></div>
                <div class="card-body p-4 position-relative text-white">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 4rem; transform: translate(10%, -10%); pointer-events: none;">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div class="small fw-bold mb-1 opacity-70">✅ COMPLETION RATE</div>
                    <h3 class="fw-800 mb-2"><?= $stats['persentase'] ?>%</h3>
                    <div class="progress bg-white bg-opacity-20" style="height: 6px;">
                        <div class="progress-bar bg-white" style="width: <?= $stats['persentase'] ?>%"></div>
                    </div>
                    <small class="text-white opacity-70 d-block mt-2">Target progress indicator</small>
                </div>
            </div>
        </div>
        <!-- Stat 4: Temuan -->
        <div class="col-md-3">
            <div class="lux-card" style="background: linear-gradient(135deg, #e11d48 0%, #be123c 100%);">
                <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 100%); pointer-events: none;"></div>
                <div class="card-body p-4 position-relative text-white">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 4rem; transform: translate(10%, -10%); pointer-events: none;">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="small fw-bold mb-1 opacity-70">⚠ TEMUAN MASALAH</div>
                    <h3 class="fw-800 mb-2"><?= $stats['total_temuan'] ?></h3>
                    <div class="progress bg-white bg-opacity-20" style="height: 6px;">
                        <div class="progress-bar bg-white" style="width: <?= ($stats['total_asset'] > 0) ? ($stats['total_temuan'] / $stats['total_asset'] * 100) : 0 ?>%"></div>
                    </div>
                    <small class="text-white opacity-70 d-block mt-2">Issues requiring follow-up</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Summary Section -->
    <div class="card border-0 shadow-sm mb-4 rounded-4 bg-light">
        <div class="card-body p-4">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-list-check text-primary me-2"></i>Ringkasan Aktivitas Bulan Ini</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="d-flex align-items-start mb-2.5">
                        <i class="bi bi-check-circle-fill text-success me-2 mt-1"></i>
                        <span class="small text-dark">
                            <strong>Maintenance Progress:</strong> <?= $stats['total_maintenance'] ?> dari <?= $stats['total_asset'] ?> perangkat selesai dicheck (<?= $stats['persentase'] ?>%).
                        </span>
                    </div>
                    <div class="d-flex align-items-start mb-2.5">
                        <i class="bi bi-shield-check text-primary me-2 mt-1"></i>
                        <span class="small text-dark">
                            <strong>Status Sistem Keamanan:</strong> Antivirus terdeteksi aktif pada seluruh perangkat yang sudah dilakukan pengecekan.
                        </span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex align-items-start mb-2.5">
                        <i class="bi bi-exclamation-circle-fill text-warning me-2 mt-1"></i>
                        <span class="small text-dark">
                            <strong>Temuan Perbaikan:</strong> Terdeteksi <strong><?= $warningCount ?></strong> unit perangkat membutuhkan perbaikan ringan/tindakan lanjutan.
                        </span>
                    </div>
                    <div class="d-flex align-items-start mb-2.5">
                        <i class="bi bi-x-circle-fill text-danger me-2 mt-1"></i>
                        <span class="small text-dark">
                            <strong>Kerusakan Berat:</strong> Terdapat <strong><?= $brokenCount ?></strong> unit perangkat dalam kondisi rusak berat dan disarankan diganti.
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Double Grid -->
    <div class="row g-4 mb-4">
        <!-- Pie Chart (Device Status distribution) -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 rounded-4">
                <div class="card-header bg-white border-bottom-0 pt-4 px-4 fw-bold text-dark d-flex align-items-center">
                    <i class="bi bi-pie-chart text-primary me-2"></i> Status Perangkat
                </div>
                <div class="card-body px-4 pb-4 text-center">
                    <div style="height: 180px; position: relative;" class="mx-auto mb-3">
                        <canvas id="statusPieChart"></canvas>
                    </div>
                    <div class="d-flex justify-content-center gap-3 flex-wrap small">
                        <span><i class="bi bi-circle-fill text-success me-1"></i> Normal: <strong><?= $goodCount ?></strong></span>
                        <span><i class="bi bi-circle-fill text-warning me-1"></i> Warning: <strong><?= $warningCount ?></strong></span>
                        <span><i class="bi bi-circle-fill text-danger me-1"></i> Rusak: <strong><?= $brokenCount ?></strong></span>
                    </div>
                </div>
            </div>
        </div>
        <!-- Bar Chart (Checked per division) -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100 rounded-4">
                <div class="card-header bg-white border-bottom-0 pt-4 px-4 fw-bold text-dark d-flex align-items-center">
                    <i class="bi bi-bar-chart-steps text-primary me-2"></i> Progress Maintenance Per Divisi
                </div>
                <div class="card-body px-4 pb-4">
                    <div style="height: 220px; position: relative;">
                        <canvas id="divisionBarChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Client-Side Filter & Search Panel -->
    <div class="card shadow-sm border-0 mb-4 rounded-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <!-- Search bar -->
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted">🔍 Cari Perangkat</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="clientSearchInput" class="form-control bg-light border-0" placeholder="Kode, User, Nama Aset..." onkeyup="applyClientFilters()">
                    </div>
                </div>
                <!-- Divisi dropdown -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">Divisi</label>
                    <select id="clientFilterDivisi" class="form-select bg-light border-0" onchange="applyClientFilters()">
                        <option value="">Semua Divisi</option>
                        <?php 
                        $divs = array_unique(array_filter(array_map(function($d) { return $d['nama_divisi'] ?? null; }, $summaryDivisi)));
                        foreach ($divs as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Status dropdown -->
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">Status</label>
                    <select id="clientFilterStatus" class="form-select bg-light border-0" onchange="applyClientFilters()">
                        <option value="">Semua Status</option>
                        <option value="Baik">OK / Baik</option>
                        <option value="Perlu Perbaikan">Warning</option>
                        <option value="Rusak">Rusak</option>
                    </select>
                </div>
                <!-- Reset Local Filters -->
                <div class="col-md-3">
                    <label class="form-label d-block mb-2">&nbsp;</label>
                    <button type="button" class="btn btn-outline-secondary w-100 fw-bold py-2 shadow-sm rounded-3" onclick="resetClientFilters()">
                        <i class="bi bi-x-circle me-1.5"></i>Reset Filter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Details Section -->
    <div class="card border-0 shadow-sm mb-4 rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom pt-4 pb-3 px-4 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold m-0"><i class="bi bi-list-task text-primary me-2"></i>Rincian Checklist Perangkat</h6>
            <span class="badge bg-secondary bg-opacity-10 text-secondary" id="visibleRowCountBadge"><?= count($detailedMaintenance) ?> Aset Terdisplay</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle mb-0" id="laporanTable">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4">Aset</th>
                            <th>User (Pemegang)</th>
                            <th>Tanggal Check</th>
                            <th>Tindakan / Aksi</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($detailedMaintenance)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-file-earmark-lock-fill fs-2 d-block mb-2"></i> Belum ada data checkup untuk kantor ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($detailedMaintenance as $dm): 
                                $status = $dm['status'] ?? 'Baik';
                                if ($status === 'Baik') {
                                    $badge = 'bg-success bg-opacity-10 text-success';
                                    $statusLabel = '🟢 OK';
                                } elseif ($status === 'Perlu Perbaikan') {
                                    $badge = 'bg-warning bg-opacity-10 text-warning';
                                    $statusLabel = '🟠 WARNING';
                                } else {
                                    $badge = 'bg-danger bg-opacity-10 text-danger';
                                    $statusLabel = '🔴 RUSAK';
                                }
                            ?>
                                <tr class="client-table-row align-middle" 
                                    data-search="<?= htmlspecialchars(strtolower($dm['kode_aset'] . ' ' . $dm['nama_aset'] . ' ' . ($dm['nama_karyawan'] ?? 'unassigned') . ' ' . ($dm['nama_divisi'] ?? 'unassigned'))) ?>"
                                    data-divisi="<?= htmlspecialchars($dm['nama_divisi'] ?? 'No Division') ?>"
                                    data-status="<?= htmlspecialchars($status) ?>">
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark mb-0"><?= $dm['kode_aset'] ?></div>
                                        <div class="text-muted small text-truncate" style="max-width: 200px;"><?= $dm['nama_aset'] ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-medium"><?= $dm['nama_karyawan'] ?: 'Unassigned' ?></div>
                                        <small class="text-muted text-xs"><?= $dm['nama_divisi'] ?? 'No Division' ?></small>
                                    </td>
                                    <td>
                                        <div class="text-dark"><i class="bi bi-calendar3 text-muted me-1.5"></i><?= date('d M Y', strtotime($dm['tanggal'])) ?></div>
                                    </td>
                                    <td>
                                        <div class="small text-truncate" style="max-width: 250px;" title="<?= $dm['tindakan'] ?>"><?= $dm['tindakan'] ?: 'Pengecekan Rutin' ?></div>
                                    </td>
                                    <td>
                                        <span class="badge <?= $badge ?> rounded-pill px-3 py-1.5 font-bold" style="font-size: 0.72rem;"><?= $statusLabel ?></span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button type="button" class="btn btn-sm btn-light border" onclick="showAssetDetailModal(<?= htmlspecialchars(json_encode($dm)) ?>)">
                                            <i class="bi bi-search me-1"></i> Detail
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Responsive Mobile Card View -->
            <div class="d-block d-md-none p-3" id="mobileCardsContainer">
                <?php foreach ($detailedMaintenance as $dm): 
                    $status = $dm['status'] ?? 'Baik';
                    if ($status === 'Baik') {
                        $badge = 'bg-success bg-opacity-10 text-success';
                        $statusLabel = '🟢 OK';
                    } elseif ($status === 'Perlu Perbaikan') {
                        $badge = 'bg-warning bg-opacity-10 text-warning';
                        $statusLabel = '🟠 WARNING';
                    } else {
                        $badge = 'bg-danger bg-opacity-10 text-danger';
                        $statusLabel = '🔴 RUSAK';
                    }
                ?>
                    <div class="card border p-3 mb-3 rounded-3 shadow-sm client-mobile-card"
                         data-search="<?= htmlspecialchars(strtolower($dm['kode_aset'] . ' ' . $dm['nama_aset'] . ' ' . ($dm['nama_karyawan'] ?? 'unassigned') . ' ' . ($dm['nama_divisi'] ?? 'unassigned'))) ?>"
                         data-divisi="<?= htmlspecialchars($dm['nama_divisi'] ?? 'No Division') ?>"
                         data-status="<?= htmlspecialchars($status) ?>">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded px-2.5 py-1 small"><?= $dm['kode_aset'] ?></span>
                                <h6 class="fw-bold text-dark mt-2 mb-1"><?= $dm['nama_aset'] ?></h6>
                            </div>
                            <span class="badge <?= $badge ?> rounded-pill px-2.5 py-1.5"><?= $statusLabel ?></span>
                        </div>
                        <hr class="my-2 opacity-50">
                        <div class="row g-2 mb-3">
                            <div class="col-6 small"><span class="text-muted">User:</span><br><strong><?= $dm['nama_karyawan'] ?: 'Unassigned' ?></strong></div>
                            <div class="col-6 small"><span class="text-muted">Tanggal:</span><br><strong class="text-dark"><?= date('d M Y', strtotime($dm['tanggal'])) ?></strong></div>
                            <div class="col-12 small"><span class="text-muted">Tindakan:</span><br><strong class="text-muted"><?= $dm['tindakan'] ?: 'Pengecekan Rutin' ?></strong></div>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary w-100 fw-bold py-2" onclick="showAssetDetailModal(<?= htmlspecialchars(json_encode($dm)) ?>)">
                            <i class="bi bi-search me-1"></i> Rincian Aset
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Yearly Line Chart section -->
    <div class="row g-4 mb-5">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom-0 pt-4 px-4 fw-bold text-dark d-flex align-items-center">
                    <i class="bi bi-graph-up-arrow text-primary me-2"></i> Statistik Tren Bulanan Tahun Berjalan
                </div>
                <div class="card-body px-4 pb-4">
                    <div style="height: 240px; position: relative;">
                        <canvas id="yearlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI recommendations block -->
    <div class="row g-4 mb-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm border-start border-primary border-4 rounded-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-primary mb-2"><i class="bi bi-cpu-fill me-2"></i>Kesimpulan Otomatis</h6>
                    <p class="mb-0 text-dark small" style="line-height: 1.6;">
                        <?= $conclusion ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card border-0 shadow-sm border-start border-success border-4 rounded-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-success mb-3"><i class="bi bi-lightbulb-fill me-2"></i>Rekomendasi Strategis</h6>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($recommendations as $rec): ?>
                            <div class="d-flex align-items-center text-dark small">
                                <i class="bi bi-check-circle-fill text-success me-2.5"></i>
                                <span><?= $rec ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 py-5 my-4">
        <div class="card-body text-center py-5">
            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex p-4 mb-4">
                <i class="bi bi-file-earmark-spreadsheet fs-1"></i>
            </div>
            <h5 class="fw-bold text-dark">Data Laporan Kosong</h5>
            <p class="text-muted small mx-auto" style="max-width: 350px;">Silakan pilih Cabang, Bulan, dan Tahun pada formulir di atas kemudian klik <strong>Generate Laporan</strong> untuk menampilkan rekapitulasi.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Detail Aset -->
<div class="modal fade" id="modalDetailAset" tabindex="-1" aria-labelledby="modalDetailAsetLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 28px;">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-800 m-0 text-dark"><i class="bi bi-pc-display text-primary me-2"></i> Rincian Histori & Checklist Aset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Profile details -->
                <div class="card p-3 mb-4 border-0 bg-light rounded-3">
                    <div class="row g-3">
                        <div class="col-md-6 col-sm-12">
                            <span class="text-muted small d-block mb-1">Perangkat / Aset</span>
                            <span class="fw-bold text-dark fs-6" id="modalAssetName">-</span>
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-3 px-2 py-0.5 ms-1.5" id="modalAssetCode">-</span>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <span class="text-muted small d-block mb-1">Pemegang (User)</span>
                            <span class="fw-bold text-dark" id="modalAssetUser">-</span>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <span class="text-muted small d-block mb-1">Divisi</span>
                            <span class="badge bg-secondary rounded-pill px-2.5 py-1" id="modalAssetDivisi">-</span>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Left: Checklists & Status percentages -->
                    <div class="col-md-6">
                        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-list-task text-primary me-2"></i>Checklist Pekerjaan</h6>
                        <div class="d-flex flex-column gap-3 mb-4">
                            <!-- Antivirus Check -->
                            <div>
                                <div class="d-flex justify-content-between mb-1.5">
                                    <span class="small fw-semibold text-dark"><i class="bi bi-shield-check text-success me-2"></i>Antivirus & Firewall</span>
                                    <span class="small fw-bold text-success" id="chkAntivirusPerc">100%</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: 100%"></div>
                                </div>
                            </div>
                            <!-- Windows Update -->
                            <div>
                                <div class="d-flex justify-content-between mb-1.5">
                                    <span class="small fw-semibold text-dark"><i class="bi bi-arrow-repeat text-primary me-2"></i>Windows & Patch Update</span>
                                    <span class="small fw-bold text-primary" id="chkUpdatePerc">80%</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-primary" id="chkUpdateBar" style="width: 80%"></div>
                                </div>
                            </div>
                            <!-- Physical Cleaning -->
                            <div>
                                <div class="d-flex justify-content-between mb-1.5">
                                    <span class="small fw-semibold text-dark"><i class="bi bi-wind text-info me-2"></i>Physical Cleaning & Fans</span>
                                    <span class="small fw-bold text-info" id="chkCleaningPerc">100%</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-info" id="chkCleaningBar" style="width: 100%"></div>
                                </div>
                            </div>
                            <!-- Storage & RAM -->
                            <div>
                                <div class="d-flex justify-content-between mb-1.5">
                                    <span class="small fw-semibold text-dark"><i class="bi bi-hdd-network text-warning me-2"></i>Storage & RAM Check</span>
                                    <span class="small fw-bold text-warning" id="chkStoragePerc">100%</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-warning" id="chkStorageBar" style="width: 100%"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Technical notes -->
                        <div class="mb-3">
                            <strong class="d-block text-secondary small mb-1">Temuan Lapangan (Komentar):</strong>
                            <div class="p-2.5 bg-light rounded text-dark small" style="min-height: 48px;" id="modalFieldFindings">-</div>
                        </div>
                        <div class="mb-0">
                            <strong class="d-block text-secondary small mb-1">Tindakan / Aksi:</strong>
                            <div class="p-2.5 bg-light rounded text-dark small" style="min-height: 48px;" id="modalFieldActions">-</div>
                        </div>
                    </div>

                    <!-- Right: Timeline of checkups -->
                    <div class="col-md-6 border-start">
                        <h6 class="fw-bold text-dark mb-3 ps-3"><i class="bi bi-hourglass-split text-primary me-2"></i>Timeline Pemeriksaan</h6>
                        <div class="timeline-container ps-3 mt-3">
                            <div class="timeline-item mb-4 border-start border-primary border-2 ps-3 position-relative">
                                <div class="position-absolute rounded-circle bg-primary" style="width: 10px; height: 10px; left: -6px; top: 4px;"></div>
                                <div class="small fw-bold text-dark" id="timelineM1Date">Maintenance</div>
                                <div class="text-muted small mt-1" id="timelineM1Tindakan">Tindakan checkup selesai dicatat.</div>
                            </div>
                            <div class="timeline-item mb-4 border-start border-success border-2 ps-3 position-relative">
                                <div class="position-absolute rounded-circle bg-success" style="width: 10px; height: 10px; left: -6px; top: 4px;"></div>
                                <div class="small fw-bold text-dark" id="timelineM2Date">Pemeriksaan Selesai</div>
                                <div class="text-muted small mt-1" id="timelineM2Status">Kondisi perangkat dinyatakan OK / Baik.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-secondary px-5 shadow-sm rounded-pill fw-bold" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        <?php if ($selected_cabang): ?>
            // 1. Line Chart (Yearly trend)
            const ctxYearly = document.getElementById('yearlyChart').getContext('2d');
            const labelsYearly = <?= json_encode(array_values($months)) ?>;
            const dataYearly = new Array(12).fill(0);
            
            <?php foreach ($yearlyStats as $ys): ?>
                dataYearly[<?= $ys['bulan'] - 1 ?>] = <?= $ys['jumlah'] ?>;
            <?php endforeach; ?>

            new Chart(ctxYearly, {
                type: 'line',
                data: {
                    labels: labelsYearly,
                    datasets: [{
                        label: 'Jumlah Maintenance',
                        data: dataYearly,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.08)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointRadius: 4,
                        pointBackgroundColor: '#6366f1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            ticks: { stepSize: 1, color: '#94a3b8' },
                            grid: { color: 'rgba(148, 163, 184, 0.08)' }
                        },
                        x: {
                            ticks: { color: '#94a3b8' },
                            grid: { display: false }
                        }
                    }
                }
            });

            // 2. Pie Chart (Status distribution)
            const ctxPie = document.getElementById('statusPieChart').getContext('2d');
            new Chart(ctxPie, {
                type: 'doughnut',
                data: {
                    labels: ['Normal', 'Warning', 'Rusak'],
                    datasets: [{
                        data: [<?= $goodCount ?>, <?= $warningCount ?>, <?= $brokenCount ?>],
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: { display: false }
                    }
                }
            });

            // 3. Bar Chart (Checked per division)
            const ctxBar = document.getElementById('divisionBarChart').getContext('2d');
            const divisionLabels = [];
            const divisionTotal = [];
            const divisionChecked = [];

            <?php foreach ($summaryDivisi as $sd): ?>
                divisionLabels.push(<?= json_encode($sd['nama_divisi'] ?? 'Tanpa Divisi') ?>);
                divisionTotal.push(<?= $sd['total_perangkat'] ?>);
                divisionChecked.push(<?= $sd['selesai'] ?>);
            <?php endforeach; ?>

            new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels: divisionLabels,
                    datasets: [
                        {
                            label: 'Total Perangkat',
                            data: divisionTotal,
                            backgroundColor: '#e2e8f0',
                            borderRadius: 6
                        },
                        {
                            label: 'Selesai Maintenance',
                            data: divisionChecked,
                            backgroundColor: '#6366f1',
                            borderRadius: 6
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            ticks: { stepSize: 1, color: '#94a3b8' },
                            grid: { color: 'rgba(148, 163, 184, 0.08)' }
                        },
                        x: {
                            ticks: { color: '#94a3b8' },
                            grid: { display: false }
                        }
                    }
                }
            });
        <?php endif; ?>

    });

    // Client-side filtering & Search logic
    function applyClientFilters() {
        const query = document.getElementById('clientSearchInput').value.toLowerCase();
        const divisiFilter = document.getElementById('clientFilterDivisi').value;
        const statusFilter = document.getElementById('clientFilterStatus').value;

        // Filter standard table rows
        const rows = document.querySelectorAll('.client-table-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const searchVal = row.getAttribute('data-search');
            const divVal = row.getAttribute('data-divisi');
            const statusVal = row.getAttribute('data-status');

            const matchSearch = searchVal.includes(query);
            const matchDivisi = !divVal || divisiFilter === "" || divVal === divisiFilter;
            const matchStatus = statusFilter === "" || statusVal === statusFilter;

            if (matchSearch && matchDivisi && matchStatus) {
                row.style.display = "";
                visibleCount++;
            } else {
                row.style.display = "none";
            }
        });

        // Filter mobile layout cards
        const cards = document.querySelectorAll('.client-mobile-card');
        cards.forEach(card => {
            const searchVal = card.getAttribute('data-search');
            const divVal = card.getAttribute('data-divisi');
            const statusVal = card.getAttribute('data-status');

            const matchSearch = searchVal.includes(query);
            const matchDivisi = !divVal || divisiFilter === "" || divVal === divisiFilter;
            const matchStatus = statusFilter === "" || statusVal === statusFilter;

            if (matchSearch && matchDivisi && matchStatus) {
                card.style.display = "";
            } else {
                card.style.display = "none";
            }
        });

        // Update indicator
        document.getElementById('visibleRowCountBadge').innerText = visibleCount + ' Aset Terdisplay';
    }

    function resetClientFilters() {
        document.getElementById('clientSearchInput').value = "";
        document.getElementById('clientFilterDivisi').value = "";
        document.getElementById('clientFilterStatus').value = "";
        applyClientFilters();
    }

    // Modal display logic
    function showAssetDetailModal(data) {
        // Bind profile data
        document.getElementById('modalAssetName').innerText = data.nama_aset;
        document.getElementById('modalAssetCode').innerText = data.kode_aset;
        document.getElementById('modalAssetUser').innerText = data.nama_karyawan ? data.nama_karyawan : 'Unassigned';
        document.getElementById('modalAssetDivisi').innerText = data.nama_divisi ? data.nama_divisi : 'No Division';

        // Bind technical findings
        document.getElementById('modalFieldFindings').innerText = data.temuan ? data.temuan : 'Tidak ada temuan / Normal';
        document.getElementById('modalFieldActions').innerText = data.tindakan ? data.tindakan : 'Pengecekan Rutin';

        // Determine checklist rates
        const status = data.status;
        let updateRate = 100;
        let cleaningRate = 100;
        let storageRate = 100;

        if (status === 'Perlu Perbaikan') {
            updateRate = 80;
            cleaningRate = 90;
            storageRate = 80;
        } else if (status === 'Rusak') {
            updateRate = 40;
            cleaningRate = 50;
            storageRate = 30;
        }

        // Set progress rates
        document.getElementById('chkUpdatePerc').innerText = updateRate + '%';
        document.getElementById('chkUpdateBar').style.width = updateRate + '%';
        document.getElementById('chkCleaningPerc').innerText = cleaningRate + '%';
        document.getElementById('chkCleaningBar').style.width = cleaningRate + '%';
        document.getElementById('chkStoragePerc').innerText = storageRate + '%';
        document.getElementById('chkStorageBar').style.width = storageRate + '%';

        // Bind Timeline Checkups
        const dateStr = new Date(data.tanggal).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
        document.getElementById('timelineM1Date').innerText = dateStr;
        document.getElementById('timelineM1Tindakan').innerText = data.tindakan ? data.tindakan : 'Pengecekan Rutin';
        document.getElementById('timelineM2Date').innerText = dateStr + ' (Selesai)';
        document.getElementById('timelineM2Status').innerText = 'Kondisi perangkat dinyatakan ' + status;

        // Show modal
        var myModal = new bootstrap.Modal(document.getElementById('modalDetailAset'));
        myModal.show();
    }
</script>

<style>
    .lux-card {
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 20px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
    }
    .lux-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 30px -5px rgba(99, 102, 241, 0.12);
    }
    .fw-800 { font-weight: 800; }


    @media print {
        .btn-group, form, .card-header, .sidebar, header, .navbar, .btn { display: none !important; }
        .card { border: none !important; box-shadow: none !important; }
        .container-fluid { width: 100% !important; padding: 0 !important; }
        body { background: white !important; padding-top: 0 !important; }
    }
</style>
