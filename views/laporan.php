<?php
require_once 'models/Asset.php';
require_once 'models/Maintenance.php';
require_once 'models/Repair.php';
require_once 'models/Cabang.php';

$assetModel = new Asset($conn);
$maintenanceModel = new Maintenance($conn);
$repairModel = new Repair($conn);
$cabangModel = new Cabang($conn);

// Filter
$tgl_mulai = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tgl_selesai = $_GET['tgl_selesai'] ?? date('Y-m-d');
$id_cabang = $_GET['id_cabang'] ?? '';

// Fetch Data for Report
$cabangs = $cabangModel->getAll();

// Get Stats (simplified for the view)
try {
    $where = " WHERE 1=1";
    $params = [];
    if ($id_cabang) {
        $where .= " AND id_cabang = :id_cabang";
        $params[':id_cabang'] = $id_cabang;
    }

    $stmtAssets = $conn->prepare("SELECT COUNT(*) as total FROM assets" . $where);
    $stmtAssets->execute($params);
    $totalAssets = $stmtAssets->fetch()['total'];

    $whereDate = $where . " AND tanggal BETWEEN :tgl_mulai AND :tgl_selesai";
    $paramsDate = $params;
    $paramsDate[':tgl_mulai'] = $tgl_mulai;
    $paramsDate[':tgl_selesai'] = $tgl_selesai;

    // Maintenance Count (Joined with assets for branch filter)
    $qMaint = "SELECT COUNT(m.id) as total FROM maintenance m JOIN assets a ON m.asset_id = a.id" . 
              str_replace('id_cabang', 'a.id_cabang', $whereDate);
    $stmtMaint = $conn->prepare($qMaint);
    $stmtMaint->execute($paramsDate);
    $totalMaint = $stmtMaint->fetch()['total'];

    // Repair Count & Cost
    $qRepair = "SELECT COUNT(r.id) as total, SUM(r.biaya) as total_biaya FROM repairs r JOIN assets a ON r.asset_id = a.id" . 
               str_replace('id_cabang', 'a.id_cabang', $whereDate);
    $stmtRepair = $conn->prepare($qRepair);
    $stmtRepair->execute($paramsDate);
    $repairData = $stmtRepair->fetch();
    $totalRepair = $repairData['total'];
    $totalCost = $repairData['total_biaya'] ?? 0;

} catch (PDOException $e) {
    $totalAssets = $totalMaint = $totalRepair = $totalCost = 0;
}
?>

<div class="card border-0 shadow-sm rounded-4 mb-4 p-4 animate-fade-in">
    <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-funnel me-2 text-primary"></i> Filter Laporan</h5>
    <form method="GET" action="index.php" class="row g-3">
        <input type="hidden" name="page" value="laporan">
        <div class="col-md-3">
            <label class="form-label small fw-bold text-muted">Dari Tanggal</label>
            <input type="date" name="tgl_mulai" class="form-control bg-light border-0" value="<?= $tgl_mulai ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-bold text-muted">Sampai Tanggal</label>
            <input type="date" name="tgl_selesai" class="form-control bg-light border-0" value="<?= $tgl_selesai ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-bold text-muted">Cabang</label>
            <select name="id_cabang" class="form-select bg-light border-0">
                <option value="">-- Semua Cabang --</option>
                <?php foreach ($cabangs as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($id_cabang == $c['id']) ? 'selected' : '' ?>><?= $c['nama_cabang'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm py-2" style="border-radius: 10px;"><i class="bi bi-search me-1"></i> Tampilkan</button>
        </div>
    </form>
</div>

<div class="row g-4 mb-4 animate-fade-in">
    <!-- Card Total Aset -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
            <div class="card-body p-4 text-white position-relative">
                <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3.5rem; transform: translate(10%, -10%);">
                    <i class="bi bi-laptop"></i>
                </div>
                <span class="small fw-bold opacity-75">TOTAL ASET</span>
                <h3 class="fw-800 mb-0 mt-1"><?= $totalAssets ?></h3>
                <small class="opacity-70 d-block mt-2"><i class="bi bi-laptop me-1"></i> Perangkat Terdaftar</small>
            </div>
        </div>
    </div>
    <!-- Card Maintenance -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
            <div class="card-body p-4 text-white position-relative">
                <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3.5rem; transform: translate(10%, -10%);">
                    <i class="bi bi-tools"></i>
                </div>
                <span class="small fw-bold opacity-75">MAINTENANCE</span>
                <h3 class="fw-800 mb-0 mt-1"><?= $totalMaint ?></h3>
                <small class="opacity-70 d-block mt-2"><i class="bi bi-check-circle me-1"></i> Pemeriksaan Rutin</small>
            </div>
        </div>
    </div>
    <!-- Card Perbaikan -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
            <div class="card-body p-4 text-white position-relative">
                <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3.5rem; transform: translate(10%, -10%);">
                    <i class="bi bi-wrench"></i>
                </div>
                <span class="small fw-bold opacity-75">PERBAIKAN</span>
                <h3 class="fw-800 mb-0 mt-1"><?= $totalRepair ?></h3>
                <small class="opacity-70 d-block mt-2"><i class="bi bi-exclamation-triangle me-1"></i> Kasus Kerusakan</small>
            </div>
        </div>
    </div>
    <!-- Card Total Biaya -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
            <div class="card-body p-4 text-white position-relative">
                <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3rem; transform: translate(10%, -10%);">
                    <i class="bi bi-wallet2"></i>
                </div>
                <span class="small fw-bold opacity-75">TOTAL BIAYA</span>
                <h4 class="fw-800 mb-0 mt-1">Rp <?= number_format($totalCost, 0, ',', '.') ?></h4>
                <small class="opacity-70 d-block mt-2"><i class="bi bi-cash-stack me-1"></i> Pengeluaran Perbaikan</small>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 mb-5 animate-fade-in" style="animation-delay: 0.1s;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h5 class="fw-800 m-0 text-dark"><i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i> Ringkasan Operasional IT</h5>
        <div>
            <button onclick="window.print()" class="btn btn-outline-danger btn-sm rounded-3 me-2 fw-bold"><i class="bi bi-file-earmark-pdf me-1"></i> Cetak / PDF</button>
            <a href="export/excel.php?id_cabang=<?= $id_cabang ?>&tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>" class="btn btn-outline-success btn-sm rounded-3 fw-bold"><i class="bi bi-file-earmark-excel me-1"></i> Export Excel</a>
        </div>
    </div>

    <style>
        @media print {
            .sidebar, .top-navbar, .btn, .nav-tabs, .card:first-child, .small.text-muted {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            .card {
                box-shadow: none !important;
                border: none !important;
            }
            .table {
                width: 100% !important;
                border-collapse: collapse !important;
            }
            .table th, .table td {
                border: 1px solid #dee2e6 !important;
            }
            .tab-pane {
                display: block !important;
                opacity: 1 !important;
            }
        }
    </style>

    <ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-assets">Aset</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-maint">Maintenance</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-repairs">Perbaikan</button>
        </li>
    </ul>

    <div class="tab-content" id="reportTabsContent">
        <div class="tab-pane fade show active" id="tab-assets">
            <div class="table-responsive">
                <table class="table table-sm table-hover border">
                    <thead class="bg-light">
                        <tr>
                            <th>Kode</th>
                            <th>Nama Aset</th>
                            <th>Cabang</th>
                            <th>Divisi</th>
                            <th>Kondisi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $repAssets = $assetModel->getAll($id_cabang);
                        foreach(array_slice($repAssets, 0, 10) as $a): 
                        ?>
                        <tr>
                            <td><?= $a['kode_aset'] ?></td>
                            <td><?= $a['nama_aset'] ?></td>
                            <td><?= $a['nama_cabang'] ?></td>
                            <td><?= $a['nama_divisi'] ?></td>
                            <td><span class="badge bg-<?= ($a['kondisi'] == 'Baik') ? 'success' : 'warning' ?> bg-opacity-10 text-<?= ($a['kondisi'] == 'Baik') ? 'success' : 'warning' ?>"><?= $a['kondisi'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="small text-muted mt-2">* Menampilkan 10 aset terbaru berdasarkan filter.</p>
            </div>
        </div>
        
        <div class="tab-pane fade" id="tab-maint">
            <div class="table-responsive">
                <table class="table table-sm table-hover border">
                    <thead class="bg-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Aset</th>
                            <th>Teknisi</th>
                            <th>Temuan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $repMaint = $maintenanceModel->getAll($id_cabang, $tgl_mulai, $tgl_selesai);
                        foreach(array_slice($repMaint, 0, 10) as $m): 
                        ?>
                        <tr>
                            <td><?= date('d/m/y', strtotime($m['tanggal'])) ?></td>
                            <td><?= $m['nama_aset'] ?></td>
                            <td><?= $m['teknisi'] ?></td>
                            <td class="small"><?= $m['temuan'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-repairs">
            <div class="table-responsive">
                <table class="table table-sm table-hover border">
                    <thead class="bg-light">
                        <tr>
                            <th>Aset</th>
                            <th>Keluhan</th>
                            <th>Status</th>
                            <th>Biaya</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $repRepairs = $repairModel->getAll($id_cabang, $tgl_mulai, $tgl_selesai);
                        foreach(array_slice($repRepairs, 0, 10) as $r): 
                        ?>
                        <tr>
                            <td><?= $r['nama_aset'] ?></td>
                            <td><?= $r['keluhan'] ?></td>
                            <td><span class="badge bg-<?= ($r['status'] == 'Selesai') ? 'success' : 'warning' ?>"><?= $r['status'] ?></span></td>
                            <td>Rp <?= number_format($r['biaya'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
