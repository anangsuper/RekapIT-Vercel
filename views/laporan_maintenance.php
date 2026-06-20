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
$branchName = "";

if ($selected_cabang) {
    $stats = $maintenanceModel->getReportStats($selected_cabang, $selected_bulan, $selected_tahun);
    $summaryDivisi = $maintenanceModel->getSummaryPerDivisi($selected_cabang, $selected_bulan, $selected_tahun);
    $topFindings = $maintenanceModel->getTopFindings($selected_cabang, $selected_bulan, $selected_tahun);
    $yearlyStats = $maintenanceModel->getYearlyStats($selected_cabang, $selected_tahun);
    
    foreach ($cabangs as $c) {
        if ($c['id'] == $selected_cabang) {
            $branchName = $c['nama_cabang'];
            break;
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

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary"><i class="fas fa-file-contract me-2"></i>Laporan Maintenance Massal</h2>
        <?php if ($selected_cabang): ?>
        <div class="btn-group">
            <button onclick="window.print()" class="btn btn-outline-secondary"><i class="fas fa-print me-2"></i>Print</button>
            <a href="export/maintenance_excel.php?id_cabang=<?= $selected_cabang ?>&bulan=<?= $selected_bulan ?>&tahun=<?= $selected_tahun ?>" class="btn btn-outline-success"><i class="fas fa-file-excel me-2"></i>Excel</a>
            <a href="export/maintenance_pdf.php?id_cabang=<?= $selected_cabang ?>&bulan=<?= $selected_bulan ?>&tahun=<?= $selected_tahun ?>" class="btn btn-danger"><i class="fas fa-file-pdf me-2"></i>Generate PDF</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="index.php" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="laporan_maintenance">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Pilih Cabang</label>
                    <select name="id_cabang" class="form-select shadow-none border-2" required>
                        <option value="">-- Pilih Cabang --</option>
                        <?php foreach ($cabangs as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($selected_cabang == $c['id']) ? 'selected' : '' ?>><?= $c['nama_cabang'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Bulan</label>
                    <select name="bulan" class="form-select shadow-none border-2">
                        <?php foreach ($months as $m => $nama): ?>
                            <option value="<?= $m ?>" <?= ($selected_bulan == $m) ? 'selected' : '' ?>><?= $nama ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Tahun</label>
                    <select name="tahun" class="form-select shadow-none border-2">
                        <?php for ($i = date('Y'); $i >= 2020; $i--): ?>
                            <option value="<?= $i ?>" <?= ($selected_tahun == $i) ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2"><i class="fas fa-sync-alt me-2"></i>Generate Laporan</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selected_cabang): ?>
    <!-- Branch Info -->
    <div class="card shadow-sm border-0 mb-4 bg-light">
        <div class="card-body">
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
            <p class="text-muted mb-0"><i class="fas fa-map-marker-alt me-2"></i><?= $branchDetails['alamat'] ?? 'Alamat tidak tersedia' ?></p>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-primary text-white p-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="small opacity-75">Total Asset</div>
                        <h3 class="fw-bold mb-0"><?= $stats['total_asset'] ?></h3>
                    </div>
                    <i class="fas fa-boxes fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-secondary text-white p-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="small opacity-75">Total Komputer/Laptop</div>
                        <h3 class="fw-bold mb-0"><?= $stats['total_komputer'] ?></h3>
                    </div>
                    <i class="fas fa-desktop fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-info text-white p-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="small opacity-75">Total Maintenance</div>
                        <h3 class="fw-bold mb-0"><?= $stats['total_maintenance'] ?></h3>
                    </div>
                    <i class="fas fa-tools fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-success text-white p-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="small opacity-75">Tingkat Penyelesaian</div>
                        <h3 class="fw-bold mb-0"><?= $stats['persentase'] ?>%</h3>
                    </div>
                    <i class="fas fa-check-circle fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Summary Per Division -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Ringkasan Per Divisi</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Divisi</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Selesai</th>
                                    <th class="text-center">Belum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($summaryDivisi as $sd): ?>
                                <tr>
                                    <td><?= $sd['nama_divisi'] ?? 'Tanpa Divisi' ?></td>
                                    <td class="text-center"><?= $sd['total_perangkat'] ?></td>
                                    <td class="text-center text-success fw-bold"><?= $sd['selesai'] ?></td>
                                    <td class="text-center text-danger fw-bold"><?= $sd['belum'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- Charts -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Statistik Maintenance Tahun Berjalan</div>
                <div class="card-body">
                    <canvas id="yearlyChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- AI & Recommendations -->
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card border-0 shadow-sm border-start border-4 border-primary">
                <div class="card-body">
                    <h5 class="fw-bold text-primary"><i class="fas fa-robot me-2"></i>Kesimpulan Otomatis</h5>
                    <p class="lead" style="font-size: 1.1rem; line-height: 1.6;">
                        "<?= $conclusion ?>"
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-12 mb-4">
            <div class="card border-0 shadow-sm border-start border-4 border-success">
                <div class="card-body">
                    <h5 class="fw-bold text-success"><i class="fas fa-lightbulb me-2"></i>Rekomendasi Strategis</h5>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recommendations as $rec): ?>
                            <li class="list-group-item border-0 ps-0"><i class="fas fa-check-circle text-success me-2"></i><?= $rec ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('yearlyChart').getContext('2d');
        const labels = <?= json_encode(array_values($months)) ?>;
        const dataValues = new Array(12).fill(0);
        
        <?php foreach ($yearlyStats as $ys): ?>
            dataValues[<?= $ys['bulan'] - 1 ?>] = <?= $ys['jumlah'] ?>;
        <?php endforeach; ?>

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jumlah Maintenance',
                    data: dataValues,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    </script>
    <?php else: ?>
    <div class="text-center py-5">
        <img src="assets/images/report-placeholder.svg" alt="Select Branch" style="width: 200px; opacity: 0.5;">
        <p class="mt-4 text-muted">Silakan pilih Cabang, Bulan, dan Tahun untuk meng-generate laporan.</p>
    </div>
    <?php endif; ?>
</div>

<style>
@media print {
    .btn-group, form, .card-header, .sidebar, header { display: none !important; }
    .card { border: none !important; box-shadow: none !important; }
    .container-fluid { width: 100% !important; padding: 0 !important; }
    body { background: white !important; }
}
</style>
