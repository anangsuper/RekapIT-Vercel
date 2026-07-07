<?php
require_once 'controllers/MaintenanceController.php';
require_once 'models/Asset.php';
require_once 'models/Cabang.php';

$maintenanceController = new MaintenanceController($conn);
$assetModel = new Asset($conn);
$cabangModel = new Cabang($conn);

$sub = $_GET['sub'] ?? 'history';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['tambah']) && $sub === 'history') {
        $data = [
            'asset_id' => $_POST['asset_id'],
            'tanggal' => $_POST['tanggal'],
            'teknisi' => $_POST['teknisi'],
            'temuan' => $_POST['temuan'],
            'tindakan' => $_POST['tindakan'],
            'rekomendasi' => $_POST['rekomendasi'],
            'status' => $_POST['status'],
            'id_detail_jadwal' => null
        ];
        if ($maintenanceController->store($data)) {
            header("Location: index.php?page=maintenance&status=success");
            exit();
        }
    } elseif (isset($_POST['proses_massal_final']) && $sub === 'massal') {
        $asset_ids = array_unique($_POST['asset_ids'] ?? []);
        $conn->beginTransaction();
        try {
            require_once 'models/Maintenance.php';
            require_once 'models/Asset.php';
            $maintModel = new Maintenance($conn);
            $assetModel = new Asset($conn);
            
            $summaryLines = [];
            $generalTeknisi = '';

            foreach ($asset_ids as $id) {
                // Ambil checklist terpilih dan format sebagai teks
                $chkList = $_POST['checklist'][$id] ?? [];
                $checklist_str = !empty($chkList) ? "Checklist: " . implode(', ', $chkList) : "";
                
                $tindakan_input = $_POST['tindakan'][$id] ?? '';
                if ($checklist_str) {
                    $tindakan = $checklist_str . ($tindakan_input ? ". Tindakan: " . $tindakan_input : "");
                } else {
                    $tindakan = $tindakan_input;
                }

                $data = [
                    'asset_id' => $id,
                    'tanggal' => $_POST['tanggal'][$id],
                    'teknisi' => $_POST['teknisi'][$id],
                    'temuan' => $_POST['temuan'][$id],
                    'tindakan' => $tindakan,
                    'rekomendasi' => $_POST['rekomendasi'][$id],
                    'status' => $_POST['status'][$id],
                    'id_detail_jadwal' => null
                ];
                $maintModel->create($data);

                // Kumpulkan info aset untuk ringkasan Telegram
                $asset = $assetModel->getById($id);
                $kodeAset = $asset ? $asset['kode_aset'] : "Aset ID: $id";
                $namaAset = $asset ? $asset['nama_aset'] : "Tidak Diketahui";
                $statusAset = $_POST['status'][$id] ?? 'Selesai';
                $temuanAset = $_POST['temuan'][$id] ?? 'Baik';
                
                $summaryLines[] = "• *{$kodeAset}* ({$namaAset}): {$temuanAset} (Status: {$statusAset})";
                
                if (empty($generalTeknisi)) {
                    $generalTeknisi = $_POST['teknisi'][$id] ?? '';
                }
            }
            $conn->commit();

            // Kirim satu rangkuman Telegram
            require_once 'helpers/notification.php';
            $tanggal = date('d M Y');
            $totalAset = count($asset_ids);
            
            $msg = "📦 *MAINTENANCE MASSAL SELESAI*\n\n"
                 . "*• Tanggal:* {$tanggal}\n"
                 . "*• Total Aset:* {$totalAset} Aset\n"
                 . "*• Teknisi:* " . ($generalTeknisi ?: ($_SESSION['nama'] ?? 'Sistem')) . "\n\n"
                 . "*Rincian Pemeriksaan:*\n"
                 . implode("\n", $summaryLines);
            
            sendTelegramNotification($msg);

            header("Location: index.php?page=maintenance&sub=history&status=mass_success");
            exit();
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Gagal memproses maintenance massal: " . $e->getMessage();
        }
    }
}

// Prepare data
$maintenanceModel = new Maintenance($conn);
$assetsAvailable = $assetModel->getAssetsAvailableForMaintenance(date('m'), date('Y'));
$cabangs = $cabangModel->getAll();

$id_cabang = $_GET['id_cabang'] ?? '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : null;

// Pagination for history sub-page
$limit = 10;
$pageNumber = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($pageNumber - 1) * $limit;

if ($sub === 'history') {
    $totalMaint = $maintenanceModel->countAll($id_cabang ?: null, $search_query);
    $totalPages = ceil($totalMaint / $limit);
    $maintenances = $maintenanceModel->getPaginated($limit, $offset, $id_cabang ?: null, $search_query);
    
    $paginationUrl = "index.php?page=maintenance&sub=history";
    if ($id_cabang) $paginationUrl .= "&id_cabang=" . urlencode($id_cabang);
    if ($search_query) $paginationUrl .= "&search=" . urlencode($search_query);
} else {
    $maintenances = [];
    $totalPages = 0;
}

$assets = $id_cabang ? $assetModel->getAll($id_cabang) : [];
?>
<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show animate-fade-in shadow-sm" role="alert">
    <div class="d-flex align-items-center">
        <i class="bi bi-exclamation-octagon-fill fs-5 me-2"></i>
        <div>
            <strong>Gagal Menyimpan!</strong> <?= htmlspecialchars($error) ?>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4 animate-fade-in">
    <div>
        <h4 class="fw-800 m-0">Maintenance</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php?page=maintenance&sub=history" class="text-decoration-none <?= $sub === 'history' ? 'fw-bold text-primary' : 'text-muted' ?>">History</a></li>
                <li class="breadcrumb-item"><a href="index.php?page=maintenance&sub=massal" class="text-decoration-none <?= $sub === 'massal' ? 'fw-bold text-primary' : 'text-muted' ?>">Massal</a></li>
            </ol>
        </nav>
    </div>
    <?php if ($sub === 'history'): ?>
    <div class="d-flex gap-2">
        <button onclick="broadcastScheduleToTelegram(this)" class="btn btn-outline-info shadow-sm d-flex align-items-center gap-2" style="border-radius: 12px;">
            <i class="bi bi-telegram" id="tg-sched-icon"></i> <span id="tg-sched-text">Kirim Jadwal ke Telegram</span>
        </button>
        <a href="index.php?page=maintenance&sub=massal" class="btn btn-success shadow-sm">
            <i class="bi bi-layers-half me-2"></i> Maintenance Massal
        </a>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="bi bi-plus-lg me-2"></i> Log Check
        </button>
    </div>
    <?php endif; ?>
</div>

<?php if ($sub === 'history'): ?>
    <!-- Search & Filter Card -->
    <div class="card border-0 shadow-sm mb-4 rounded-4 animate-fade-in">
        <div class="card-body p-4">
            <form method="GET" action="index.php" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="maintenance">
                <input type="hidden" name="sub" value="history">
                
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted">🏢 Filter Kantor Cabang</label>
                    <select name="id_cabang" class="form-select bg-light border-0" onchange="this.form.submit()">
                        <option value="">-- Semua Cabang --</option>
                        <?php foreach ($cabangs as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($id_cabang == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nama_cabang']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted">🔍 Cari Log Maintenance</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control bg-light border-0" placeholder="Cari Kode Aset, Nama Aset, Teknisi, Temuan..." value="<?= htmlspecialchars($search_query ?? '') ?>">
                        <?php if ($search_query): ?>
                            <a href="index.php?page=maintenance&sub=history&id_cabang=<?= urlencode($id_cabang) ?>" class="btn btn-light border-0 d-flex align-items-center text-danger"><i class="bi bi-x-circle-fill"></i></a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary d-none">Cari</button>
                    <a href="index.php?page=maintenance&sub=history" class="btn btn-outline-secondary w-100 fw-bold py-2 shadow-sm rounded-3">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset Filter
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Filter Condition Badge -->
    <?php if ($id_cabang || $search_query): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4 border-0 shadow-sm rounded-4 d-flex justify-content-between align-items-center animate-fade-in" role="alert">
            <div class="m-0 small">
                <i class="bi bi-filter-circle-fill text-warning me-2 fs-5"></i> 
                Menampilkan log maintenance aktif untuk: 
                <?php if ($id_cabang): ?>
                    <span class="badge bg-primary rounded-pill px-2.5 py-1">Cabang ID: <?= $id_cabang ?></span>
                <?php endif; ?>
                <?php if ($search_query): ?>
                    <span class="badge bg-info text-dark rounded-pill px-2.5 py-1">Kata Kunci: "<?= htmlspecialchars($search_query) ?>"</span>
                <?php endif; ?>
            </div>
            <a href="index.php?page=maintenance&sub=history" class="btn btn-sm btn-light border-0 shadow-sm rounded-pill px-3 py-1.5 fw-bold"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset</a>
        </div>
    <?php endif; ?>

<div class="card border-0 shadow-sm animate-fade-in overflow-hidden">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light border-bottom">
                    <tr>
                        <th class="ps-4" width="150">Tanggal</th>
                        <th width="220">Aset</th>
                        <th width="150">Teknisi</th>
                        <th width="200">Kondisi / Temuan</th>
                        <th>Tindakan & Rekomendasi</th>
                        <th class="text-end pe-4" width="100">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($maintenances)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="No data" width="80" class="opacity-50 mb-3">
                                <p class="text-muted mb-0">Belum ada riwayat maintenance.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($maintenances as $m): 
                        // Map status to classes
                        $status = $m['status'] ?? 'Baik';
                        if ($status === 'Baik') {
                            $badge_class = 'bg-success bg-opacity-10 text-success';
                            $status_icon = 'bi-check-circle-fill';
                        } elseif ($status === 'Perlu Perbaikan') {
                            $badge_class = 'bg-warning bg-opacity-10 text-warning';
                            $status_icon = 'bi-exclamation-triangle-fill';
                        } else {
                            $badge_class = 'bg-danger bg-opacity-10 text-danger';
                            $status_icon = 'bi-x-circle-fill';
                        }
                    ?>
                    <tr class="align-middle">
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-calendar3 text-muted me-2"></i>
                                <span class="fw-semibold text-dark"><?= date('d M Y', strtotime($m['tanggal'])) ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold text-primary mb-0"><?= $m['kode_aset'] ?></div>
                            <div class="text-muted small text-truncate" style="max-width: 200px;" title="<?= $m['nama_aset'] ?>"><?= $m['nama_aset'] ?></div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-1 small fw-medium">
                                    <i class="bi bi-person-fill me-1"></i><?= $m['teknisi'] ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-column gap-1">
                                <div>
                                    <span class="badge <?= $badge_class ?> rounded-pill px-2.5 py-1.5 fw-bold">
                                        <i class="bi <?= $status_icon ?> me-1"></i><?= $status ?>
                                    </span>
                                </div>
                                <?php if (!empty($m['temuan'])): ?>
                                    <small class="text-muted text-wrap" style="max-width: 180px;"><i class="bi bi-search me-1 small"></i><?= $m['temuan'] ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="small">
                                <?php
                                $displayTindakan = $m['tindakan'];
                                $checklistStr = '';
                                if (strpos($displayTindakan, 'Checklist: ') !== false) {
                                    $parts = explode('. Tindakan: ', $displayTindakan);
                                    $checklistStr = str_replace('Checklist: ', '', $parts[0]);
                                    $displayTindakan = isset($parts[1]) ? $parts[1] : 'Pengecekan Rutin';
                                }
                                ?>
                                <div class="text-dark fw-medium text-truncate mb-1" style="max-width: 300px;" title="<?= htmlspecialchars($m['tindakan']) ?>">
                                    <strong>Tindakan:</strong> <?= htmlspecialchars($displayTindakan) ?: '<span class="text-muted">-</span>' ?>
                                </div>
                                <?php if (!empty($checklistStr) || strpos($m['tindakan'], 'Checklist: ') !== false): ?>
                                <div class="mt-1.5 d-flex flex-wrap gap-1 align-items-center" style="max-width: 320px;">
                                    <?php 
                                    $savedItems = array_map('trim', explode(',', $checklistStr));
                                    $allChecklistItems = [
                                        "Scan Virus", "Update Antivirus", "Deleting Temporary", 
                                        "Cek Keyboard", "Cek Mouse", "Cek CPU & Monitor", 
                                        "Cek Tinta", "Cek Catdridge", "Cek Nozel"
                                    ];
                                    foreach ($allChecklistItems as $item): 
                                        $isChecked = in_array($item, $savedItems);
                                        if ($isChecked):
                                    ?>
                                            <span class="text-success fw-bold d-inline-flex align-items-center" style="font-size: 0.7rem; background: rgba(25, 135, 84, 0.08); padding: 2px 6px; border-radius: 5px; margin-bottom: 2px;">
                                                <i class="bi bi-check-circle-fill me-1" style="font-size: 0.65rem;"></i><?= htmlspecialchars($item) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-danger fw-bold d-inline-flex align-items-center" style="font-size: 0.7rem; background: rgba(220, 53, 69, 0.08); padding: 2px 6px; border-radius: 5px; margin-bottom: 2px;">
                                                <i class="bi bi-x-circle-fill me-1" style="font-size: 0.65rem;"></i><?= htmlspecialchars($item) ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($m['rekomendasi'])): ?>
                                <div class="text-muted text-truncate" style="max-width: 300px;" title="<?= htmlspecialchars($m['rekomendasi']) ?>">
                                    <strong>Rekomendasi:</strong> <?= htmlspecialchars($m['rekomendasi']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-end pe-4">
                            <button type="button" class="btn btn-sm btn-light border btn-hover-primary btn-detail-maint" data-id="<?= $m['id'] ?>">
                                <i class="bi bi-search me-1"></i> Detail
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination Controls -->
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white border-top-0 pt-2 pb-4 d-flex justify-content-center">
            <?= getPaginationControls($pageNumber, $totalPages, $paginationUrl) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<?php 
$stage = $_POST['stage'] ?? 'select';
$selected_ids = array_unique($_POST['asset_ids'] ?? []);
?>

<div class="animate-fade-in">
    <div class="card border-0 shadow-sm mb-4 overflow-hidden">
        <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
            <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-building me-2"></i>Pilih Cabang</h5>
            <p class="text-muted small mt-1">Pilih cabang untuk memuat daftar aset yang akan dimaintenance secara massal.</p>
        </div>
        <div class="card-body px-4 pb-4">
            <form method="GET" action="index.php">
                <input type="hidden" name="page" value="maintenance">
                <input type="hidden" name="sub" value="massal">
                <div class="row">
                    <div class="col-md-6 col-lg-5">
                        <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-geo-alt-fill text-muted"></i></span>
                            <select name="id_cabang" class="form-select border-0 bg-light" onchange="this.form.submit()">
                                <option value="">-- Pilih Cabang --</option>
                                <?php foreach ($cabangs as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($id_cabang == $c['id']) ? 'selected' : '' ?>><?= $c['nama_cabang'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary px-4 fw-bold">Muat Aset</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <form method="POST">
        <?php if ($id_cabang && $stage === 'select'): ?>
            <input type="hidden" name="stage" value="select">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom pt-4 pb-3 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0"><i class="bi bi-pc-display-horizontal text-primary me-2"></i>Daftar Aset / Komputer</h5>
                        <p class="text-muted small mt-1 mb-0">Pilih aset yang ingin di-maintenance secara massal.</p>
                    </div>
                    <div>
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2">
                            Total: <?= count($assets) ?> Aset
                        </span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="60" class="text-center ps-3">
                                        <div class="form-check d-flex justify-content-center m-0">
                                            <input type="checkbox" id="checkAll" class="form-check-input" style="width: 1.2em; height: 1.2em;">
                                        </div>
                                    </th>
                                    <th>Kode Aset</th>
                                    <th>Nama Aset</th>
                                    <th>Kondisi Saat Ini</th>
                                    <th class="text-end pe-4">Pemegang (User)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($assets)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="No data" width="80" class="opacity-50 mb-3">
                                            <p class="text-muted mb-0">Tidak ada aset ditemukan untuk cabang ini.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($assets as $a): ?>
                                    <tr>
                                        <td class="text-center ps-3">
                                            <div class="form-check d-flex justify-content-center m-0">
                                                <input type="checkbox" name="asset_ids[]" value="<?= $a['id'] ?>" class="form-check-input asset-checkbox" style="width: 1.2em; height: 1.2em;">
                                            </div>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-dark"><?= $a['kode_aset'] ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3">
                                                    <i class="bi bi-display text-primary"></i>
                                                </div>
                                                <span class="fw-medium"><?= $a['nama_aset'] ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $kondisi = $a['kondisi'] ?? 'Baik';
                                            if ($kondisi === 'Baik') {
                                                echo '<span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2.5">Baik</span>';
                                            } elseif ($kondisi === 'Rusak Ringan') {
                                                echo '<span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-2.5">Rusak Ringan</span>';
                                            } else {
                                                echo '<span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2.5">Rusak Berat</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <span class="fw-semibold text-muted"><?= $a['nama_karyawan'] ?? '-' ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if(!empty($assets)): ?>
                <div class="card-footer bg-light border-0 p-4 text-end">
                    <button type="submit" name="stage" value="review" class="btn btn-primary px-5 py-2 fw-bold shadow-sm" id="btnNext" disabled>
                        Lanjut ke Edit Detail <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        <?php elseif ($stage === 'review'): ?>
            <input type="hidden" name="stage" value="review">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Detail Maintenance (<?= count($selected_ids) ?> Aset)</h5>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="history.back()"><i class="bi bi-arrow-left me-1"></i> Kembali</button>
            </div>
            
            <div class="card p-4 mb-4 border-0 shadow-sm bg-primary bg-opacity-10 rounded-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                        <i class="bi bi-lightning-fill"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-primary mb-0">Terapkan Cepat ke Semua Aset</h6>
                        <small class="text-muted">Isi form ini untuk menyamakan data pada semua aset terpilih di bawah.</small>
                    </div>
                </div>
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">Tanggal</label>
                        <input type="date" id="all_tanggal" class="form-control form-control-sm border-0 shadow-sm" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">Teknisi</label>
                        <input type="text" id="all_teknisi" class="form-control form-control-sm border-0 shadow-sm" placeholder="Nama Teknisi">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small fw-bold text-muted mb-1">Kondisi</label>
                        <select id="all_status" class="form-select form-select-sm border-0 shadow-sm">
                            <option value="Baik">Baik</option>
                            <option value="Perlu Perbaikan">Perlu Perbaikan</option>
                            <option value="Rusak">Rusak</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">Temuan</label>
                        <input type="text" id="all_temuan" class="form-control form-control-sm border-0 shadow-sm" placeholder="Contoh: Kotor">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">Tindakan</label>
                        <input type="text" id="all_tindakan" class="form-control form-control-sm border-0 shadow-sm" placeholder="Contoh: Dibersihkan">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">Rekomendasi</label>
                        <input type="text" id="all_rekomendasi" class="form-control form-control-sm border-0 shadow-sm" placeholder="Contoh: Ganti RAM">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-primary btn-sm w-100 shadow-sm" onclick="applyToAll()">
                            <i class="bi bi-check-all me-1"></i> Terapkan
                        </button>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-top">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label small fw-bold text-primary m-0">Terapkan Checklist ke Semua:</label>
                        <div class="form-check m-0">
                            <input class="form-check-input" type="checkbox" id="all_select_all" onchange="toggleSelectAllGlobal(this)">
                            <label class="form-check-label small fw-bold text-secondary" style="font-size: 0.75rem;" for="all_select_all">Pilih Semua</label>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4 col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="all_chk_scan">
                                <label class="form-check-label small" for="all_chk_scan">Scan Virus</label>
                            </div>
                        </div>
                        <div class="col-md-4 col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="all_chk_antivirus">
                                <label class="form-check-label small" for="all_chk_antivirus">Update Antivirus</label>
                            </div>
                        </div>
                        <div class="col-md-4 col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="all_chk_temp">
                                <label class="form-check-label small" for="all_chk_temp">Deleting Temporary</label>
                            </div>
                        </div>
                        <div class="col-md-4 col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="all_chk_keyboard">
                                <label class="form-check-label small" for="all_chk_keyboard">Cek Keyboard</label>
                            </div>
                        </div>
                        <div class="col-md-4 col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="all_chk_mouse">
                                <label class="form-check-label small" for="all_chk_mouse">Cek Mouse</label>
                            </div>
                        </div>
                        <div class="col-md-4 col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="all_chk_cpu">
                                <label class="form-check-label small" for="all_chk_cpu">Cek CPU & Monitor</label>
                            </div>
                        </div>
                        <div class="col-md-4 col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="all_chk_tinta">
                                <label class="form-check-label small" for="all_chk_tinta">Cek Tinta</label>
                            </div>
                        </div>
                        <div class="col-md-4 col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="all_chk_catdridge">
                                <label class="form-check-label small" for="all_chk_catdridge">Cek Catdridge</label>
                            </div>
                        </div>
                        <div class="col-md-4 col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="all_chk_nozel">
                                <label class="form-check-label small" for="all_chk_nozel">Cek Nozel</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
            <?php foreach ($selected_ids as $id): 
                $a = $assetModel->getById($id); ?>
                <div class="col-12">
                    <div class="card p-4 border-0 shadow-sm asset-row rounded-4 border-start border-primary border-4">
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                            <div class="bg-light p-2 rounded-circle me-3">
                                <i class="bi bi-pc-display text-dark"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0"><?= $a['nama_aset'] ?></h6>
                                <small class="text-muted">Kode: <?= $a['kode_aset'] ?></small>
                            </div>
                        </div>
                        <input type="hidden" name="asset_ids[]" value="<?= $id ?>">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label small fw-bold text-muted">Tanggal <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal[<?= $id ?>]" class="form-control row-tanggal bg-light border-0" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold text-muted">Teknisi <span class="text-danger">*</span></label>
                                <input type="text" name="teknisi[<?= $id ?>]" class="form-control row-teknisi bg-light border-0" placeholder="Teknisi" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold text-muted">Kondisi <span class="text-danger">*</span></label>
                                <?php 
                                $currentKondisi = $a['kondisi'] ?? 'Baik';
                                $selectedStatus = 'Baik';
                                if ($currentKondisi === 'Rusak Ringan') {
                                    $selectedStatus = 'Perlu Perbaikan';
                                } elseif ($currentKondisi === 'Rusak Berat') {
                                    $selectedStatus = 'Rusak';
                                }
                                ?>
                                <select name="status[<?= $id ?>]" class="form-select row-status bg-light border-0">
                                    <option value="Baik" <?= $selectedStatus === 'Baik' ? 'selected' : '' ?>>Baik</option>
                                    <option value="Perlu Perbaikan" <?= $selectedStatus === 'Perlu Perbaikan' ? 'selected' : '' ?>>Perlu Perbaikan</option>
                                    <option value="Rusak" <?= $selectedStatus === 'Rusak' ? 'selected' : '' ?>>Rusak</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold text-muted">Temuan</label>
                                <input type="text" name="temuan[<?= $id ?>]" class="form-control row-temuan bg-light border-0" placeholder="Temuan">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold text-muted">Tindakan</label>
                                <input type="text" name="tindakan[<?= $id ?>]" class="form-control row-tindakan bg-light border-0" placeholder="Tindakan">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold text-muted">Rekomendasi</label>
                                <input type="text" name="rekomendasi[<?= $id ?>]" class="form-control row-rekomendasi bg-light border-0" placeholder="Rekomendasi">
                            </div>
                        </div>
                        
                        <?php 
                        $isPrinter = (stripos($a['nama_aset'], 'printer') !== false || stripos($a['kode_aset'], 'prn') !== false);
                        ?>
                        <div class="mt-3 pt-3 border-top">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label small fw-bold text-muted m-0">Checklist Pekerjaan:</label>
                                <div class="form-check m-0">
                                    <input class="form-check-input form-check-input-sm" type="checkbox" id="select_all_<?= $id ?>" onchange="toggleSelectAllRow(this, '<?= $id ?>')">
                                    <label class="form-check-label small fw-bold text-secondary" style="font-size: 0.75rem;" for="select_all_<?= $id ?>">Pilih Semua</label>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-x-3 gap-y-2 align-items-center bg-light p-3 rounded-3 border">
                                <div class="form-check form-check-inline m-0 me-3">
                                    <input class="form-check-input row-chk-scan" type="checkbox" name="checklist[<?= $id ?>][]" value="Scan Virus" id="chk_scan_<?= $id ?>">
                                    <label class="form-check-label small text-dark" style="font-size: 0.8rem;" for="chk_scan_<?= $id ?>">Scan Virus</label>
                                </div>
                                <div class="form-check form-check-inline m-0 me-3">
                                    <input class="form-check-input row-chk-antivirus" type="checkbox" name="checklist[<?= $id ?>][]" value="Update Antivirus" id="chk_antivirus_<?= $id ?>">
                                    <label class="form-check-label small text-dark" style="font-size: 0.8rem;" for="chk_antivirus_<?= $id ?>">Update Antivirus</label>
                                </div>
                                <div class="form-check form-check-inline m-0 me-3">
                                    <input class="form-check-input row-chk-temp" type="checkbox" name="checklist[<?= $id ?>][]" value="Deleting Temporary" id="chk_temp_<?= $id ?>">
                                    <label class="form-check-label small text-dark" style="font-size: 0.8rem;" for="chk_temp_<?= $id ?>">Deleting Temporary</label>
                                </div>
                                <div class="form-check form-check-inline m-0 me-3">
                                    <input class="form-check-input row-chk-keyboard" type="checkbox" name="checklist[<?= $id ?>][]" value="Cek Keyboard" id="chk_keyboard_<?= $id ?>">
                                    <label class="form-check-label small text-dark" style="font-size: 0.8rem;" for="chk_keyboard_<?= $id ?>">Cek Keyboard</label>
                                </div>
                                <div class="form-check form-check-inline m-0 me-3">
                                    <input class="form-check-input row-chk-mouse" type="checkbox" name="checklist[<?= $id ?>][]" value="Cek Mouse" id="chk_mouse_<?= $id ?>">
                                    <label class="form-check-label small text-dark" style="font-size: 0.8rem;" for="chk_mouse_<?= $id ?>">Cek Mouse</label>
                                </div>
                                <div class="form-check form-check-inline m-0 me-3">
                                    <input class="form-check-input row-chk-cpu" type="checkbox" name="checklist[<?= $id ?>][]" value="Cek CPU & Monitor" id="chk_cpu_<?= $id ?>">
                                    <label class="form-check-label small text-dark" style="font-size: 0.8rem;" for="chk_cpu_<?= $id ?>">Cek CPU & Monitor</label>
                                </div>
                                <div class="form-check form-check-inline m-0 me-3">
                                    <input class="form-check-input row-chk-tinta" type="checkbox" name="checklist[<?= $id ?>][]" value="Cek Tinta" id="chk_tinta_<?= $id ?>">
                                    <label class="form-check-label small text-dark" style="font-size: 0.8rem;" for="chk_tinta_<?= $id ?>">Cek Tinta</label>
                                </div>
                                <div class="form-check form-check-inline m-0 me-3">
                                    <input class="form-check-input row-chk-catdridge" type="checkbox" name="checklist[<?= $id ?>][]" value="Cek Catdridge" id="chk_catdridge_<?= $id ?>">
                                    <label class="form-check-label small text-dark" style="font-size: 0.8rem;" for="chk_catdridge_<?= $id ?>">Cek Catdridge</label>
                                </div>
                                <div class="form-check form-check-inline m-0 me-3">
                                    <input class="form-check-input row-chk-nozel" type="checkbox" name="checklist[<?= $id ?>][]" value="Cek Nozel" id="chk_nozel_<?= $id ?>">
                                    <label class="form-check-label small text-dark" style="font-size: 0.8rem;" for="chk_nozel_<?= $id ?>">Cek Nozel</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            <?php endforeach; ?>
            </div>
            
            <div class="mt-4 mb-5 text-end">
                <button type="submit" name="proses_massal_final" class="btn btn-success btn-lg px-5 py-3 fw-bold shadow-sm rounded-pill">
                    <i class="bi bi-save me-2"></i> Simpan Semua Maintenance
                </button>
            </div>

            <script>
                function applyToAll() {
                    const fields = ['tanggal', 'teknisi', 'status', 'temuan', 'tindakan', 'rekomendasi'];
                    fields.forEach(field => {
                        const allVal = document.getElementById('all_' + field).value;
                        if (allVal) {
                            document.querySelectorAll('.row-' + field).forEach(el => el.value = allVal);
                        }
                    });
                    
                    // Apply checklist checkboxes to all rows
                    const chkKeys = ['scan', 'antivirus', 'temp', 'keyboard', 'mouse', 'cpu', 'tinta', 'catdridge', 'nozel'];
                    chkKeys.forEach(key => {
                        const allChk = document.getElementById('all_chk_' + key);
                        if (allChk) {
                            const isChecked = allChk.checked;
                            document.querySelectorAll('.row-chk-' + key).forEach(el => {
                                el.checked = isChecked;
                            });
                        }
                    });
                }

                function toggleSelectAllGlobal(master) {
                    const chkKeys = ['scan', 'antivirus', 'temp', 'keyboard', 'mouse', 'cpu', 'tinta', 'catdridge', 'nozel'];
                    chkKeys.forEach(key => {
                        const el = document.getElementById('all_chk_' + key);
                        if (el) el.checked = master.checked;
                    });
                }

                function toggleSelectAllRow(master, id) {
                    const rowCheckboxes = document.querySelectorAll(`input[name="checklist[${id}][]"]`);
                    rowCheckboxes.forEach(cb => cb.checked = master.checked);
                }
            </script>
        <?php endif; ?>
    </form>
</div>

<script>
    const checkAll = document.getElementById('checkAll');
    const checkboxes = document.querySelectorAll('.asset-checkbox');
    const btnNext = document.getElementById('btnNext');

    if (checkAll) {
        checkAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            if(btnNext) btnNext.disabled = !this.checked;
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const checkedCount = document.querySelectorAll('.asset-checkbox:checked').length;
            if(btnNext) btnNext.disabled = checkedCount === 0;
            if(checkAll) checkAll.checked = checkedCount === checkboxes.length && checkboxes.length > 0;
        });
    });
</script>
<?php endif; ?>

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
                        <div class="row g-3 mb-4 ps-2" id="checklistContainer">
                            <!-- Dinamis oleh Javascript -->
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

<script>
    const maintenanceData = <?= json_encode($maintenances) ?>;

    document.querySelectorAll('.btn-detail-maint').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const data = maintenanceData.find(item => item.id == id);
            if (data) {
                showAssetDetailModal(data);
            }
        });
    });

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

        const status = data.status;
        // Render 9 Checklist Items
        const isPrinter = data.nama_aset.toLowerCase().includes('printer') || data.kode_aset.toLowerCase().includes('prn');
        const checklistContainer = document.getElementById('checklistContainer');
        checklistContainer.innerHTML = '';

        const checklists = [
            { id: 1, text: "Scan Virus", type: "pc" },
            { id: 2, text: "Update Antivirus", type: "pc" },
            { id: 3, text: "Deleting Temporary", type: "pc" },
            { id: 4, text: "Cek Keyboard", type: "pc" },
            { id: 5, text: "Cek Mouse", type: "pc" },
            { id: 6, text: "Cek CPU & Monitor", type: "pc" },
            { id: 7, text: "Cek Tinta", type: "printer" },
            { id: 8, text: "Cek Catdridge", type: "printer" },
            { id: 9, text: "Cek Nozel", type: "printer" }
        ];

        const hasSavedChecklist = data.tindakan && data.tindakan.includes("Checklist:");

        checklists.forEach(item => {
            let iconClass = "bi-check-circle-fill text-success";
            
            if (hasSavedChecklist) {
                const isChecked = data.tindakan.includes(item.text);
                if (isChecked) {
                    iconClass = "bi-check-circle-fill text-success";
                } else {
                    iconClass = "bi-circle text-muted";
                }
            } else {
                if (status === 'Rusak') {
                    iconClass = "bi-x-circle-fill text-danger";
                } else if (status === 'Perlu Perbaikan') {
                    iconClass = "bi-exclamation-circle-fill text-warning";
                }
            }

            const col = document.createElement('div');
            col.className = `col-6 py-1`;
            col.innerHTML = `<div class="d-flex align-items-center"><i class="bi ${iconClass} me-2 fs-6"></i><span class="small fw-semibold text-dark">${item.text}</span></div>`;
            checklistContainer.appendChild(col);
        });

        // Clean up display text for Tindakan / Aksi
        let displayTindakan = data.tindakan ? data.tindakan : 'Pengecekan Rutin';
        if (displayTindakan.includes(". Tindakan: ")) {
            displayTindakan = displayTindakan.split(". Tindakan: ")[1];
        } else if (displayTindakan.includes("Checklist: ") && !displayTindakan.includes(". Tindakan: ")) {
            displayTindakan = "Pengecekan Rutin";
        }

        // Bind Timeline Checkups
        const dateStr = new Date(data.tanggal).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
        document.getElementById('timelineM1Date').innerText = dateStr;
        document.getElementById('timelineM1Tindakan').innerText = displayTindakan;
        document.getElementById('timelineM2Date').innerText = dateStr + ' (Selesai)';
        document.getElementById('timelineM2Status').innerText = 'Kondisi perangkat dinyatakan ' + status;
        document.getElementById('modalFieldActions').innerText = displayTindakan;

        // Show modal
        var myModal = new bootstrap.Modal(document.getElementById('modalDetailAset'));
        myModal.show();
    }

    function broadcastScheduleToTelegram(btn) {
        const icon = document.getElementById('tg-sched-icon');
        const text = document.getElementById('tg-sched-text');
        
        if (btn.disabled) return;
        btn.disabled = true;
        const originalIconClass = icon.className;
        const originalText = text.innerText;
        
        icon.className = 'spinner-border spinner-border-sm me-2';
        text.innerText = 'Mengirim...';
        
        fetch('api/broadcast_maintenance_schedule.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Jadwal maintenance berhasil dikirim ke grup Telegram!');
                } else {
                    alert('Gagal mengirim jadwal: ' + data.error);
                }
            })
            .catch(err => {
                alert('Terjadi kesalahan koneksi: ' + err.message);
            })
            .finally(() => {
                btn.disabled = false;
                icon.className = originalIconClass;
                text.innerText = originalText;
            });
    }
</script>

