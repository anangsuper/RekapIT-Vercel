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

<div class="card p-4 mb-4">
    <h5 class="fw-bold mb-3"><i class="fas fa-filter me-2 text-primary"></i> Filter Laporan</h5>
    <form method="GET" action="index.php" class="row g-3">
        <input type="hidden" name="page" value="laporan">
        <div class="col-md-3">
            <label class="form-label small fw-bold">Dari Tanggal</label>
            <input type="date" name="tgl_mulai" class="form-control form-control-sm" value="<?= $tgl_mulai ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-bold">Sampai Tanggal</label>
            <input type="date" name="tgl_selesai" class="form-control form-control-sm" value="<?= $tgl_selesai ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-bold">Cabang</label>
            <select name="id_cabang" class="form-select form-select-sm">
                <option value="">-- Semua Cabang --</option>
                <?php foreach ($cabangs as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($id_cabang == $c['id']) ? 'selected' : '' ?>><?= $c['nama_cabang'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-search me-1"></i> Tampilkan</button>
        </div>
    </form>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card p-3 shadow-sm border-0 bg-primary text-white">
            <div class="small opacity-75 fw-bold">TOTAL ASET</div>
            <h3 class="m-0 fw-bold"><?= $totalAssets ?></h3>
            <div class="mt-2 small"><i class="fas fa-laptop me-1"></i> Perangkat Terdaftar</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 shadow-sm border-0 bg-success text-white">
            <div class="small opacity-75 fw-bold">MAINTENANCE</div>
            <h3 class="m-0 fw-bold"><?= $totalMaint ?></h3>
            <div class="mt-2 small"><i class="fas fa-tools me-1"></i> Pemeriksaan Rutin</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 shadow-sm border-0 bg-warning text-dark">
            <div class="small opacity-75 fw-bold">PERBAIKAN</div>
            <h3 class="m-0 fw-bold"><?= $totalRepair ?></h3>
            <div class="mt-2 small"><i class="fas fa-wrench me-1"></i> Kasus Kerusakan</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 shadow-sm border-0 bg-danger text-white">
            <div class="small opacity-75 fw-bold">TOTAL BIAYA</div>
            <h4 class="m-0 fw-bold">Rp <?= number_format($totalCost, 0, ',', '.') ?></h4>
            <div class="mt-2 small"><i class="fas fa-wallet me-1"></i> Pengeluaran Perbaikan</div>
        </div>
    </div>
</div>

<div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="fw-bold m-0">Ringkasan Operasional IT</h5>
        <div>
            <button onclick="window.print()" class="btn btn-outline-danger btn-sm me-2"><i class="fas fa-file-pdf me-1"></i> Cetak / PDF</button>
            <a href="export/excel.php?id_cabang=<?= $id_cabang ?>&tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>" class="btn btn-outline-success btn-sm"><i class="fas fa-file-excel me-1"></i> Export Excel</a>
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
