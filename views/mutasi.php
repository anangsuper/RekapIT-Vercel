<?php
require_once 'models/Asset.php';
require_once 'models/Mutation.php';
require_once 'models/Cabang.php';
require_once 'models/Divisi.php';
require_once 'models/Karyawan.php';
require_once 'models/ActivityLog.php';

$assetModel = new Asset($conn);
$mutationModel = new Mutation($conn);
$cabangModel = new Cabang($conn);
$divisiModel = new Divisi($conn);
$karyawanModel = new Karyawan($conn);
$logModel = new ActivityLog($conn);

// Proses Form Mutasi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['proses_mutasi'])) {
    $asset_id = $_POST['asset_id'];
    $currentAsset = $assetModel->getById($asset_id);
    
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
        'keterangan' => $_POST['keterangan']
    ];

    if ($mutationModel->create($data)) {
        $logModel->add($_SESSION['user_id'], 'Mutasi Aset', "Mutasi aset " . $currentAsset['nama_aset'] . " (" . $currentAsset['kode_aset'] . ")");
        header("Location: index.php?page=mutasi&status=success");
        exit();
    } else {
        $error = "Gagal memproses mutasi aset.";
    }
}

$allMutations = $mutationModel->getAll();
$search_query = isset($_GET['search']) ? trim($_GET['search']) : null;

// Pagination logic
$limit = 10;
$pageNumber = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($pageNumber - 1) * $limit;

$totalMutations = $mutationModel->countAll($search_query);
$totalPages = ceil($totalMutations / $limit);

$mutations = $mutationModel->getPaginated($limit, $offset, $search_query);
$paginationUrl = "index.php?page=mutasi";
if ($search_query) {
    $paginationUrl .= "&search=" . urlencode($search_query);
}

$assets = $assetModel->getAll();
$cabangs = $cabangModel->getAll();
$divisis = $divisiModel->getAll();
$karyawans = $karyawanModel->getAll();

// Calculate Stats
$totalMutasi = count($allMutations);
$mutasiBulanIni = 0;
$uniqueAssets = [];
foreach ($allMutations as $m) {
    $uniqueAssets[$m['asset_id']] = true;
    if (date('m-Y', strtotime($m['tanggal_mutasi'])) === date('m-Y')) {
        $mutasiBulanIni++;
    }
}
$totalAsetTerlibat = count($uniqueAssets);
?>

<div class="container-fluid animate-fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center">
            <div class="bg-primary bg-opacity-10 p-2.5 rounded-3 me-3 text-primary">
                <i class="bi bi-arrow-left-right fs-4"></i>
            </div>
            <div>
                <h4 class="fw-800 m-0">Mutasi Aset</h4>
                <p class="text-muted small m-0">Kelola riwayat perpindahan dan penugasan perangkat</p>
            </div>
        </div>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalMutasi">
            <i class="bi bi-plus-lg me-2"></i> Baru Mutasi
        </button>
    </div>

    <?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4 animate-fade-in" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Mutasi aset berhasil diproses dan tercatat.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4 animate-fade-in" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- KPI Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
                <div class="card-body p-4 text-white position-relative">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3.5rem; transform: translate(10%, -10%);">
                        <i class="bi bi-arrow-left-right"></i>
                    </div>
                    <span class="small fw-bold opacity-75">TOTAL MUTASI</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $totalMutasi ?></h3>
                    <small class="opacity-70 d-block mt-2">Seluruh riwayat log perpindahan aset</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <div class="card-body p-4 text-white position-relative">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3.5rem; transform: translate(10%, -10%);">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <span class="small fw-bold opacity-75">MUTASI BULAN INI</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $mutasiBulanIni ?></h3>
                    <small class="opacity-70 d-block mt-2">Perpindahan di bulan berjalan</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <div class="card-body p-4 text-white position-relative">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3.5rem; transform: translate(10%, -10%);">
                        <i class="bi bi-laptop"></i>
                    </div>
                    <span class="small fw-bold opacity-75">ASET TERLIBAT</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $totalAsetTerlibat ?></h3>
                    <small class="opacity-70 d-block mt-2">Unit unik perangkat yang dipindahkan</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Server-Side Search Panel -->
    <div class="card border-0 shadow-sm mb-4 rounded-4">
        <div class="card-body p-4">
            <form method="GET" action="index.php" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="mutasi">
                <div class="col-md-8">
                    <label class="form-label small fw-bold text-muted">🔍 Cari Mutasi</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" id="mutationSearch" class="form-control bg-light border-0" placeholder="Cari Kode Aset, Nama Aset, Lokasi, Karyawan..." value="<?= htmlspecialchars($search_query ?? '') ?>">
                        <?php if ($search_query): ?>
                            <a href="index.php?page=mutasi" class="btn btn-light border-0 d-flex align-items-center text-danger"><i class="bi bi-x-circle-fill"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary d-none">Cari</button>
                    <a href="index.php?page=mutasi" class="btn btn-outline-secondary w-100 fw-bold py-2 shadow-sm rounded-3">
                        <i class="bi bi-arrow-counterclockwise me-1.5"></i>Reset Pencarian
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Filter Condition Badge -->
    <?php if ($search_query): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4 border-0 shadow-sm rounded-4 d-flex justify-content-between align-items-center" role="alert">
            <div class="m-0 small">
                <i class="bi bi-filter-circle-fill text-warning me-2 fs-5"></i> 
                Menampilkan hasil mutasi untuk kata kunci: <span class="badge bg-info text-dark rounded-pill px-2.5 py-1">"<?= htmlspecialchars($search_query) ?>"</span>
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
                            <th style="width: 32%;">Alur Perpindahan Lokasi & Pemegang</th>
                            <th>Tanggal Mutasi</th>
                            <th>Pelaksana (IT)</th>
                            <th class="pe-4 text-end">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($mutations)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-arrow-left-right fs-2 d-block mb-2"></i> Belum ada riwayat mutasi aset.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($mutations as $m): ?>
                            <tr class="mutation-row align-middle" data-search="<?= htmlspecialchars(strtolower($m['nama_aset'] . ' ' . $m['kode_aset'] . ' ' . $m['cabang_lama'] . ' ' . $m['cabang_baru'] . ' ' . ($m['karyawan_lama'] ?? '') . ' ' . ($m['karyawan_baru'] ?? '') . ' ' . ($m['keterangan'] ?? ''))) ?>">
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= $m['nama_aset'] ?></div>
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded px-2 py-0.5 mt-1 small" style="font-size: 0.72rem;"><?= $m['kode_aset'] ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <!-- Old place -->
                                        <div class="bg-light p-2.5 rounded-3 flex-fill text-center" style="max-width: 140px;">
                                            <div class="small fw-bold text-danger opacity-75 text-truncate" title="<?= $m['cabang_lama'] ?>"><?= $m['cabang_lama'] ?></div>
                                            <div class="text-muted text-xs text-truncate" style="font-size: 0.75rem;" title="<?= $m['karyawan_lama'] ?: 'Unassigned' ?>"><?= $m['karyawan_lama'] ?: 'Unassigned' ?></div>
                                        </div>
                                        
                                        <!-- Direction Arrow -->
                                        <i class="bi bi-arrow-right text-primary fs-5"></i>
                                        
                                        <!-- New Place -->
                                        <div class="bg-primary bg-opacity-10 p-2.5 rounded-3 flex-fill text-center" style="max-width: 140px;">
                                            <div class="small fw-bold text-success text-truncate" title="<?= $m['cabang_baru'] ?>"><?= $m['cabang_baru'] ?></div>
                                            <div class="text-dark text-xs text-truncate" style="font-size: 0.75rem;" title="<?= $m['karyawan_baru'] ?: 'Unassigned' ?>"><?= $m['karyawan_baru'] ?: 'Unassigned' ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small fw-bold text-dark"><i class="bi bi-calendar3 text-muted me-1.5"></i><?= date('d M Y', strtotime($m['tanggal_mutasi'])) ?></div>
                                    <div class="text-muted small text-truncate mt-0.5" style="max-width: 160px;" title="<?= $m['keterangan'] ?>"><?= $m['keterangan'] ?: '-' ?></div>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark"><i class="bi bi-person-fill text-muted me-1.5"></i><?= $m['pelaksana'] ?></div>
                                </td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm btn-light border" onclick="showMutationDetail(<?= htmlspecialchars(json_encode($m)) ?>)">
                                        <i class="bi bi-info-circle me-1"></i> Rincian
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Responsive Mobile Grid -->
            <div class="d-block d-md-none p-3" id="mobileMutationContainer">
                <?php foreach ($mutations as $m): ?>
                    <div class="card border p-3 mb-3 rounded-3 shadow-sm mobile-mutation-card" data-search="<?= htmlspecialchars(strtolower($m['nama_aset'] . ' ' . $m['kode_aset'] . ' ' . $m['cabang_lama'] . ' ' . $m['cabang_baru'] . ' ' . ($m['karyawan_lama'] ?? '') . ' ' . ($m['karyawan_baru'] ?? '') . ' ' . ($m['keterangan'] ?? ''))) ?>">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded px-2.5 py-1 small"><?= $m['kode_aset'] ?></span>
                                <h6 class="fw-bold text-dark mt-2 mb-1"><?= $m['nama_aset'] ?></h6>
                            </div>
                        </div>
                        
                        <div class="bg-light p-2.5 rounded-3 mb-3 d-flex align-items-center justify-content-between gap-1">
                            <div class="text-center flex-fill">
                                <span class="text-xs text-muted d-block" style="font-size: 0.7rem;">Dari:</span>
                                <strong class="text-danger small text-truncate d-block" style="max-width: 100px;"><?= $m['cabang_lama'] ?></strong>
                                <span class="text-muted text-xs d-block text-truncate" style="font-size: 0.72rem; max-width: 100px;"><?= $m['karyawan_lama'] ?: 'Unassigned' ?></span>
                            </div>
                            <i class="bi bi-arrow-right text-primary"></i>
                            <div class="text-center flex-fill">
                                <span class="text-xs text-muted d-block" style="font-size: 0.7rem;">Ke:</span>
                                <strong class="text-success small text-truncate d-block" style="max-width: 100px;"><?= $m['cabang_baru'] ?></strong>
                                <span class="text-dark text-xs d-block text-truncate" style="font-size: 0.72rem; max-width: 100px;"><?= $m['karyawan_baru'] ?: 'Unassigned' ?></span>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6 small"><span class="text-muted">Tanggal:</span><br><strong><?= date('d M Y', strtotime($m['tanggal_mutasi'])) ?></strong></div>
                            <div class="col-6 small"><span class="text-muted">Pelaksana:</span><br><strong><?= $m['pelaksana'] ?></strong></div>
                            <div class="col-12 small"><span class="text-muted">Alasan:</span><br><strong class="text-muted"><?= $m['keterangan'] ?: '-' ?></strong></div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary w-100 fw-bold py-2" onclick="showMutationDetail(<?= htmlspecialchars(json_encode($m)) ?>)">
                            <i class="bi bi-info-circle me-1"></i> Rincian Mutasi
                        </button>
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
                        <span class="text-muted text-xs d-block">Pelaksana (IT)</span>
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

<!-- Modal Mutasi -->
<div class="modal fade" id="modalMutasi" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 28px;">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-800 m-0"><i class="bi bi-arrow-left-right text-primary me-2"></i> Form Mutasi Aset</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-4">Pilih aset yang akan dimutasi dan tentukan lokasi atau pemegang baru.</p>
                    
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
                                            data-cabang="<?= $a['nama_cabang'] ?>" 
                                            data-divisi="<?= $a['nama_divisi'] ?>" 
                                            data-karyawan="<?= $a['nama_karyawan'] ?: 'Unassigned' ?>">
                                        <?= $a['kode_aset'] ?> - <?= $a['nama_aset'] ?>
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

                        <hr class="my-2 opacity-25">
                        <div class="col-md-12 py-0"><h6 class="fw-bold m-0 text-success"><i class="bi bi-geo-alt-fill me-1"></i> Lokasi / Penugasan Baru</h6></div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Cabang Baru</label>
                            <select name="id_cabang_baru" id="mutasi_cabang_baru" class="form-select bg-light border-0" required>
                                <option value="">-- Pilih Cabang --</option>
                                <?php foreach ($cabangs as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= $c['nama_cabang'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Divisi Baru</label>
                            <select name="id_divisi_baru" class="form-select bg-light border-0" required>
                                <option value="">-- Pilih Divisi --</option>
                                <?php foreach ($divisis as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= $d['nama_divisi'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Karyawan Baru</label>
                            <select name="id_karyawan_baru" id="mutasi_karyawan_baru" class="form-select bg-light border-0">
                                <option value="">-- Unassigned --</option>
                                <?php foreach ($karyawans as $kr): ?>
                                    <option value="<?= $kr['id'] ?>" data-cabang="<?= $kr['id_cabang'] ?>"><?= $kr['nama_karyawan'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Tanggal Mutasi</label>
                            <input type="date" name="tanggal_mutasi" class="form-control bg-light border-0" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Keterangan / Alasan</label>
                            <input type="text" name="keterangan" class="form-control bg-light border-0" placeholder="Contoh: Perpindahan tugas / Promosi">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 shadow-sm" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                    <button type="submit" name="proses_mutasi" class="btn btn-primary px-4 shadow-sm">Proses Mutasi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Filter Asset list helper function
function filterAssetOptions() {
    const selectedCabangName = document.getElementById('mutasi_filter_cabang_asset').value;
    const query = document.getElementById('mutasi_search_asset_input').value.toLowerCase();
    const selectAsset = document.getElementById('mutasi_asset_id');
    const options = selectAsset.querySelectorAll('option');

    options.forEach(option => {
        if (option.value === "") {
            option.style.display = 'block';
            return;
        }

        const text = option.textContent.toLowerCase();
        const cabangName = option.getAttribute('data-cabang-name');
        
        const matchQuery = text.includes(query);
        const matchBranch = (selectedCabangName === "" || cabangName === selectedCabangName);

        if (matchQuery && matchBranch) {
            option.style.display = 'block';
        } else {
            option.style.display = 'none';
        }
    });
}

// Smart Filter Aset based on Branch
document.getElementById('mutasi_filter_cabang_asset').addEventListener('change', function() {
    filterAssetOptions();
    document.getElementById('mutasi_asset_id').value = "";
    document.getElementById('info_lokasi_lama').innerText = "Pilih aset terlebih dahulu";
});

// Smart Filter Aset based on Search Query
document.getElementById('mutasi_search_asset_input').addEventListener('input', filterAssetOptions);

// Show current location when asset is selected
document.getElementById('mutasi_asset_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    if (this.value) {
        const cabang = selectedOption.getAttribute('data-cabang');
        const divisi = selectedOption.getAttribute('data-divisi');
        const karyawan = selectedOption.getAttribute('data-karyawan');
        document.getElementById('info_lokasi_lama').innerText = `${cabang} - ${divisi} (${karyawan})`;
    } else {
        document.getElementById('info_lokasi_lama').innerText = "Pilih aset terlebih dahulu";
    }
});

// Smart Filter Karyawan based on Branch
document.getElementById('mutasi_cabang_baru').addEventListener('change', function() {
    const selectedCabangId = this.value;
    const selectKaryawan = document.getElementById('mutasi_karyawan_baru');
    const options = selectKaryawan.querySelectorAll('option');

    options.forEach(option => {
        const cabangId = option.getAttribute('data-cabang');
        if (!cabangId) {
            option.style.display = 'block';
        } else {
            option.style.display = (cabangId === selectedCabangId) ? 'block' : 'none';
        }
    });
    selectKaryawan.value = "";
});

// Client search filter (Obsolete - Handled Server-Side)

// Modal Detail Bindings
function showMutationDetail(data) {
    document.getElementById('detAssetName').innerText = data.nama_aset;
    document.getElementById('detAssetCode').innerText = data.kode_aset;
    document.getElementById('detBranchLama').innerText = data.cabang_lama;
    document.getElementById('detUserLama').innerText = data.karyawan_lama ? data.karyawan_lama : 'Unassigned';
    document.getElementById('detBranchBaru').innerText = data.cabang_baru;
    document.getElementById('detUserBaru').innerText = data.karyawan_baru ? data.karyawan_baru : 'Unassigned';
    
    const dateStr = new Date(data.tanggal_mutasi).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    document.getElementById('detTanggal').innerText = dateStr;
    document.getElementById('detPelaksana').innerText = data.pelaksana;
    document.getElementById('detKeterangan').innerText = data.keterangan ? data.keterangan : '-';
    
    var myModal = new bootstrap.Modal(document.getElementById('modalDetailMutasi'));
    myModal.show();
}
</script>

<style>
    .fw-800 { font-weight: 800; }
    .text-xs { font-size: 0.78rem; }
    .border-dashed { border-style: dashed !important; }
</style>
