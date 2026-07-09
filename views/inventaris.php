<?php
require_once 'models/Asset.php';
require_once 'models/KategoriAset.php';
require_once 'models/Cabang.php';
require_once 'models/Divisi.php';
require_once 'models/Karyawan.php';
require_once 'models/ActivityLog.php';
require_once 'helpers/notification.php';

$assetModel = new Asset($conn);
$kategoriModel = new KategoriAset($conn);
$cabangModel = new Cabang($conn);
$divisiModel = new Divisi($conn);
$karyawanModel = new Karyawan($conn);
$logModel = new ActivityLog($conn);

// Proses Hapus
if (isset($_POST['hapus'])) {
    $id = $_POST['id'];
    $currentAsset = $assetModel->getById($id);
    if ($assetModel->delete($id)) {
        if($currentAsset) {
            $logModel->add($_SESSION['user_id'], 'Hapus Aset', "Menghapus aset: " . $currentAsset['nama_aset'] . " (" . $currentAsset['kode_aset'] . ")");
            
            // Telegram Notification
            $namaUser = $_SESSION['nama'] ?? 'Admin';
            $msg = "❌ *ASET DIHAPUS PERMANEN*\n\n"
                 . "*• Kode Aset:* `{$currentAsset['kode_aset']}`\n"
                 . "*• Nama Aset:* {$currentAsset['nama_aset']}\n"
                 . "*• Cabang:* " . ($currentAsset['nama_cabang'] ?? '-') . "\n"
                 . "*• Oleh:* {$namaUser}\n"
                 . "*• Waktu:* " . date('d M Y, H:i:s');
            sendTelegramNotification($msg);
        }
        header("Location: index.php?page=inventaris&status=deleted");
        exit();
    }
}

// Batasi akses cabang untuk teknisi
$id_cabang_filter = ($_SESSION['role'] === 'teknisi') ? $_SESSION['id_cabang'] : (isset($_GET['filter_cabang']) ? $_GET['filter_cabang'] : null);
$filter_kondisi = isset($_GET['filter_kondisi']) ? $_GET['filter_kondisi'] : null;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : null;

// Pagination logic
$limit = 10;
$pageNumber = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($pageNumber - 1) * $limit;

$totalAssets = $assetModel->countAll($id_cabang_filter, $filter_kondisi, $search_query);
$totalPages = ceil($totalAssets / $limit);

$assets = $assetModel->getPaginated($limit, $offset, $id_cabang_filter, $filter_kondisi, $search_query);
$kategoris = $kategoriModel->getAll();
$cabangs = $cabangModel->getAll();
$divisis = $divisiModel->getAll();
$karyawans = $karyawanModel->getAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah'])) {
    $data = [
        'kode_aset' => trim($_POST['kode_aset']),
        'nama_aset' => trim($_POST['nama_aset']),
        'serial_number' => trim($_POST['serial_number']),
        'id_kategori' => !empty($_POST['id_kategori']) ? intval($_POST['id_kategori']) : null,
        'merk' => trim($_POST['merk']),
        'model' => trim($_POST['model']),
        'id_cabang' => !empty($_POST['id_cabang']) ? intval($_POST['id_cabang']) : null,
        'id_divisi' => !empty($_POST['id_divisi']) ? intval($_POST['id_divisi']) : null,
        'id_karyawan' => !empty($_POST['id_karyawan']) ? intval($_POST['id_karyawan']) : null,
        'kondisi' => $_POST['kondisi'],
        'tanggal_kadaluarsa_garansi' => $_POST['tanggal_kadaluarsa_garansi'] ?: null,
        'spesifikasi' => $_POST['spesifikasi'] ?? null
    ];
    try {
        // Handle file upload to Google Drive
        $fotoUrl = '';
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            require_once 'helpers/drive_upload.php';
            $driveToken = getDriveAccessToken();
            if ($driveToken) {
                $targetFolderId = '';
                if (!empty($_SESSION['user_id'])) {
                    $userQuery = $conn->prepare("SELECT google_drive_folder_id FROM users WHERE id = ?");
                    $userQuery->execute([$_SESSION['user_id']]);
                    $targetFolderId = $userQuery->fetchColumn() ?: '';
                }
                
                $uploadedUrl = uploadFileToGoogleDrive(
                    $driveToken,
                    $_FILES['foto']['tmp_name'],
                    $_FILES['foto']['type'],
                    'asset_' . $data['kode_aset'] . '_' . time() . '_' . $_FILES['foto']['name'],
                    $targetFolderId
                );
                if ($uploadedUrl) {
                    $fotoUrl = $uploadedUrl;
                }
            }
        }
        $data['foto'] = $fotoUrl;

        if ($assetModel->create($data)) {
            $logModel->add($_SESSION['user_id'], 'Tambah Aset', "Menambahkan aset baru: " . $data['nama_aset'] . " (" . $data['kode_aset'] . ")");
            
            // Telegram Notification
            $namaUser = $_SESSION['nama'] ?? 'Admin';
            $msg = "➕ *ASET BARU DIDAFTARKAN*\n\n"
                 . "*• Kode Aset:* `{$data['kode_aset']}`\n"
                 . "*• Nama Aset:* {$data['nama_aset']}\n"
                 . "*• Serial Number:* `" . ($data['serial_number'] ?: '-') . "`\n"
                 . "*• Kondisi:* 🟢 {$data['kondisi']}\n"
                 . "*• Oleh:* {$namaUser}\n"
                 . "*• Waktu:* " . date('d M Y, H:i:s');
            if ($fotoUrl) {
                $msg .= "\n*• Foto Aset:* [Lihat di Google Drive]({$fotoUrl})";
            }
            sendTelegramNotification($msg);
            
            header("Location: index.php?page=inventaris&status=success");
            exit();
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'UNIQUE') !== false || $e->getCode() == 23000) {
            header("Location: index.php?page=inventaris&error=duplicate_code");
        } else {
            header("Location: index.php?page=inventaris&error=db_error&msg=" . urlencode($e->getMessage()));
        }
        exit();
    }
}

// Proses Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $id = $_POST['id'];
    $data = [
        'kode_aset' => trim($_POST['kode_aset']),
        'nama_aset' => trim($_POST['nama_aset']),
        'serial_number' => trim($_POST['serial_number']),
        'id_kategori' => !empty($_POST['id_kategori']) ? intval($_POST['id_kategori']) : null,
        'merk' => trim($_POST['merk']),
        'model' => trim($_POST['model']),
        'id_cabang' => !empty($_POST['id_cabang']) ? intval($_POST['id_cabang']) : null,
        'id_divisi' => !empty($_POST['id_divisi']) ? intval($_POST['id_divisi']) : null,
        'id_karyawan' => !empty($_POST['id_karyawan']) ? intval($_POST['id_karyawan']) : null,
        'kondisi' => $_POST['kondisi'],
        'tanggal_kadaluarsa_garansi' => $_POST['tanggal_kadaluarsa_garansi'] ?: null,
        'spesifikasi' => $_POST['spesifikasi'] ?? null
    ];
    try {
        // Handle file upload to Google Drive
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            require_once 'helpers/drive_upload.php';
            $driveToken = getDriveAccessToken();
            if ($driveToken) {
                $targetFolderId = '';
                if (!empty($_SESSION['user_id'])) {
                    $userQuery = $conn->prepare("SELECT google_drive_folder_id FROM users WHERE id = ?");
                    $userQuery->execute([$_SESSION['user_id']]);
                    $targetFolderId = $userQuery->fetchColumn() ?: '';
                }
                
                $uploadedUrl = uploadFileToGoogleDrive(
                    $driveToken,
                    $_FILES['foto']['tmp_name'],
                    $_FILES['foto']['type'],
                    'asset_' . $data['kode_aset'] . '_' . time() . '_' . $_FILES['foto']['name'],
                    $targetFolderId
                );
                if ($uploadedUrl) {
                    $data['foto'] = $uploadedUrl;
                }
            }
        }

        if ($assetModel->update($id, $data, $_SESSION['user_id'])) {
            $logModel->add($_SESSION['user_id'], 'Update Aset', "Memperbarui aset: " . $data['nama_aset'] . " (" . $data['kode_aset'] . ")");
            
            // Telegram Notification
            $namaUser = $_SESSION['nama'] ?? 'Admin';
            $msg = "📝 *ASET DIPERBARUI*\n\n"
                 . "*• Kode Aset:* `{$data['kode_aset']}`\n"
                 . "*• Nama Aset:* {$data['nama_aset']}\n"
                 . "*• Kondisi:* " . ($data['kondisi'] === 'Baik' ? '🟢' : ($data['kondisi'] === 'Rusak Ringan' ? '🟡' : '🔴')) . " {$data['kondisi']}\n"
                 . "*• Oleh:* {$namaUser}\n"
                 . "*• Waktu:* " . date('d M Y, H:i:s');
            if (!empty($data['foto'])) {
                $msg .= "\n*• Foto Aset:* [Lihat di Google Drive]({$data['foto']})";
            }
            sendTelegramNotification($msg);
            
            header("Location: index.php?page=inventaris&status=updated");
            exit();
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'UNIQUE') !== false || $e->getCode() == 23000) {
            header("Location: index.php?page=inventaris&error=duplicate_code");
        } else {
            header("Location: index.php?page=inventaris&error=db_error&msg=" . urlencode($e->getMessage()));
        }
        exit();
    }
}
?>

<?php
// Calculate global counters for KPI widgets
$allAssetsCount = $assetModel->countAll();
$allBaikCount = $assetModel->countAll(null, 'Baik');
$allRusakRinganCount = $assetModel->countAll(null, 'Rusak Ringan');
$allRusakBeratCount = $assetModel->countAll(null, 'Rusak Berat');
?>

<div class="container-fluid animate-fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center">
            <div class="bg-primary bg-opacity-10 p-2.5 rounded-3 me-3 text-primary">
                <i class="bi bi-laptop fs-4"></i>
            </div>
            <div>
                <h4 class="fw-800 m-0">Inventaris Aset</h4>
                <p class="text-muted small m-0">Kelola dan pantau seluruh aset hardware IT perusahaan</p>
            </div>
        </div>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="bi bi-plus-lg me-2"></i> Tambah Aset
        </button>
    </div>

    <!-- Alert Status -->
    <?php if (isset($_GET['status'])): ?>
        <?php if ($_GET['status'] == 'success'): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4 border-0 shadow-sm rounded-4 animate-fade-in" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> Aset baru berhasil didaftarkan ke sistem!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($_GET['status'] == 'updated'): ?>
            <div class="alert alert-info alert-dismissible fade show mb-4 border-0 shadow-sm rounded-4 animate-fade-in" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i> Data spesifikasi aset berhasil diperbarui!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($_GET['status'] == 'deleted'): ?>
            <div class="alert alert-warning alert-dismissible fade show mb-4 border-0 shadow-sm rounded-4 animate-fade-in" role="alert">
                <i class="bi bi-trash-fill me-2"></i> Aset telah berhasil dihapus secara permanen!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <?php if ($_GET['error'] == 'duplicate_code'): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4 border-0 shadow-sm rounded-4 animate-fade-in" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> Gagal! Kode Aset sudah digunakan oleh perangkat lain. Silakan gunakan kode unik.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($_GET['error'] == 'db_error'): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4 border-0 shadow-sm rounded-4 animate-fade-in" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> Gagal memproses data! Detail: <?= htmlspecialchars($_GET['msg'] ?? 'Kesalahan Database') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- KPI Widgets Grid -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
                <div class="card-body p-3.5 text-white">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3rem; transform: translate(10%, -10%);">
                        <i class="bi bi-pc-display"></i>
                    </div>
                    <span class="small fw-bold opacity-75 text-xs">TOTAL ASET</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $allAssetsCount ?></h3>
                    <small class="opacity-70 d-block mt-1">Seluruh unit hardware</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <div class="card-body p-3.5 text-white">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3rem; transform: translate(10%, -10%);">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <span class="small fw-bold opacity-75 text-xs">KONDISI BAIK</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $allBaikCount ?></h3>
                    <small class="opacity-70 d-block mt-1">Siap digunakan operasional</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <div class="card-body p-3.5 text-white">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3rem; transform: translate(10%, -10%);">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <span class="small fw-bold opacity-75 text-xs">RUSAK RINGAN</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $allRusakRinganCount ?></h3>
                    <small class="opacity-70 d-block mt-1">Butuh pemeriksaan minor</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                <div class="card-body p-3.5 text-white">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3rem; transform: translate(10%, -10%);">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <span class="small fw-bold opacity-75 text-xs">RUSAK BERAT</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $allRusakBeratCount ?></h3>
                    <small class="opacity-70 d-block mt-1">Tidak dapat digunakan</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Interactive Filters & Search Card -->
    <div class="card border-0 shadow-sm mb-4 rounded-4">
        <div class="card-body p-4">
            <form method="GET" action="index.php" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="inventaris">
                
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">🏢 Filter Kantor Cabang</label>
                    <select name="filter_cabang" class="form-select bg-light border-0" onchange="this.form.submit()">
                        <option value="">-- Semua Cabang --</option>
                        <?php foreach ($cabangs as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($id_cabang_filter == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nama_cabang']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">🛠️ Filter Kondisi</label>
                    <select name="filter_kondisi" class="form-select bg-light border-0" onchange="this.form.submit()">
                        <option value="">-- Semua Kondisi --</option>
                        <option value="Baik" <?= ($filter_kondisi === 'Baik') ? 'selected' : '' ?>>Baik (Excellent)</option>
                        <option value="Rusak Ringan" <?= ($filter_kondisi === 'Rusak Ringan') ? 'selected' : '' ?>>Rusak Ringan</option>
                        <option value="Rusak Berat" <?= ($filter_kondisi === 'Rusak Berat') ? 'selected' : '' ?>>Rusak Berat</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted">🔍 Cari Cepat Aset</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" id="assetSearchInput" class="form-control bg-light border-0" placeholder="Ketik Kode, Nama, Serial, Merk..." value="<?= htmlspecialchars($search_query ?? '') ?>">
                        <?php if ($search_query): ?>
                            <a href="index.php?page=inventaris&filter_cabang=<?= urlencode($id_cabang_filter) ?>&filter_kondisi=<?= urlencode($filter_kondisi) ?>" class="btn btn-light border-0 d-flex align-items-center text-danger"><i class="bi bi-x-circle-fill"></i></a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm rounded-3 d-none">Cari</button>
                    <a href="index.php?page=inventaris" class="btn btn-outline-secondary w-100 fw-bold py-2 shadow-sm rounded-3">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Filter Condition Badge -->
    <?php if ($filter_kondisi || $id_cabang_filter || $search_query): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4 border-0 shadow-sm rounded-4 d-flex justify-content-between align-items-center" role="alert">
            <div class="m-0 small">
                <i class="bi bi-filter-circle-fill text-warning me-2 fs-5"></i> 
                Menampilkan filter aktif untuk: 
                <?php if ($id_cabang_filter): ?>
                    <span class="badge bg-primary rounded-pill px-2.5 py-1">Cabang ID: <?= $id_cabang_filter ?></span>
                <?php endif; ?>
                <?php if ($filter_kondisi): ?>
                    <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1">Kondisi: <?= strtoupper($filter_kondisi) ?></span>
                <?php endif; ?>
                <?php if ($search_query): ?>
                    <span class="badge bg-info text-dark rounded-pill px-2.5 py-1">Kata Kunci: "<?= htmlspecialchars($search_query) ?>"</span>
                <?php endif; ?>
            </div>
            <a href="index.php?page=inventaris" class="btn btn-sm btn-light border-0 shadow-sm rounded-pill px-3 py-1.5 fw-bold"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filter</a>
        </div>
    <?php endif; ?>

    <!-- Assets Inventory Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5 animate-fade-in" style="animation-delay: 0.1s;">
        <div class="card-body p-0">
            <!-- Desktop Layout -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4">Kode Aset</th>
                            <th>Detail Perangkat</th>
                            <th>Lokasi</th>
                            <th>Penanggung Jawab</th>
                            <th>Garansi</th>
                            <th>Kondisi</th>
                            <th class="text-end pe-4" width="120">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($assets)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-laptop fs-2 d-block mb-2"></i> Tidak ada aset ditemukan.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assets as $a): ?>
                            <tr class="asset-row-item" data-search="<?= htmlspecialchars(strtolower($a['kode_aset'] . ' ' . $a['nama_aset'] . ' ' . ($a['serial_number'] ?? '') . ' ' . ($a['merk'] ?? '') . ' ' . ($a['model'] ?? '') . ' ' . $a['nama_cabang'] . ' ' . ($a['nama_karyawan'] ?? ''))) ?>">
                                <td class="ps-4">
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded px-2.5 py-1.5 fw-bold" style="font-size: 0.72rem;">
                                        <?= $a['kode_aset'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark mb-0.5"><?= htmlspecialchars($a['nama_aset']) ?></div>
                                    <div class="text-muted" style="font-size: 0.76rem;">
                                        <?= htmlspecialchars($a['nama_kategori']) ?> &bull; <?= htmlspecialchars($a['merk'] . ' ' . $a['model']) ?> &bull; <span class="text-dark">SN: <?= htmlspecialchars($a['serial_number'] ?: '-') ?></span>
                                    </div>
                                    <?php if (!empty($a['spesifikasi'])): ?>
                                        <div class="text-secondary small mt-1" style="font-size: 0.72rem;">
                                            <i class="bi bi-cpu me-1"></i><?= htmlspecialchars($a['spesifikasi']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small fw-bold text-dark"><i class="bi bi-geo-alt text-muted me-1"></i><?= htmlspecialchars($a['nama_cabang']) ?></div>
                                    <div class="text-muted text-xs" style="font-size: 0.72rem;"><?= htmlspecialchars($a['nama_divisi']) ?></div>
                                </td>
                                <td>
                                    <?php if ($a['id_karyawan']): ?>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-secondary bg-opacity-10 rounded-circle me-2 d-flex align-items-center justify-content-center text-secondary small fw-bold" style="width: 28px; height: 28px;">
                                                <?= strtoupper(substr($a['nama_karyawan'], 0, 1)) ?>
                                            </div>
                                            <span class="small fw-semibold"><?= htmlspecialchars($a['nama_karyawan']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted fw-normal rounded px-2.5 py-1">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if (!empty($a['tanggal_kadaluarsa_garansi'])) {
                                        $exp_date = strtotime($a['tanggal_kadaluarsa_garansi']);
                                        $today = strtotime(date('Y-m-d'));
                                        $diff_seconds = $exp_date - $today;
                                        $diff_days = round($diff_seconds / (60 * 60 * 24));
                                        
                                        if ($diff_days > 30) {
                                            $w_color = 'success';
                                            $w_text = 'Aktif (' . $diff_days . ' hari)';
                                        } elseif ($diff_days >= 0) {
                                            $w_color = 'warning';
                                            $w_text = 'Limit (' . $diff_days . ' hari)';
                                        } else {
                                            $w_color = 'danger';
                                            $w_text = 'Habis';
                                        }
                                        ?>
                                        <span class="badge bg-<?= $w_color ?> bg-opacity-10 text-<?= $w_color ?> rounded px-2.5 py-1.5 fw-bold" style="font-size: 0.68rem;" title="Hingga: <?= date('d M Y', $exp_date) ?>">
                                            🛡️ <?= $w_text ?>
                                        </span>
                                        <?php
                                    } else {
                                        echo '<span class="text-muted small">-</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $bg = 'success';
                                    if ($a['kondisi'] == 'Rusak Ringan') $bg = 'warning';
                                    if ($a['kondisi'] == 'Rusak Berat') $bg = 'danger';
                                    ?>
                                    <span class="badge bg-<?= $bg ?> bg-opacity-10 text-<?= $bg ?> rounded-pill px-3 py-1.5" style="font-size: 0.68rem; font-weight: 700;">
                                        <i class="bi bi-circle-fill me-1" style="font-size: 0.35rem; vertical-align: middle;"></i> <?= strtoupper($a['kondisi']) ?>
                                    </span>
                                </td>
                                <td class="pe-4 text-end">
                                    <div class="d-flex justify-content-end align-items-center gap-2">
                                        <?php if ($a['kondisi'] !== 'Baik'): ?>
                                            <a href="index.php?page=perbaikan&asset_id=<?= $a['id'] ?>" class="btn btn-warning text-dark btn-sm rounded-pill fw-bold px-3 py-1.5 d-inline-flex align-items-center gap-1 shadow-sm" style="font-size: 0.72rem;">
                                                <i class="bi bi-wrench" style="font-size: 0.75rem;"></i> Perbaikan
                                            </a>
                                        <?php endif; ?>
                                        <div class="dropdown">
                                            <button class="btn btn-light btn-sm rounded-circle shadow-sm border" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-1" style="border-radius: 12px;">
                                                <li><a class="dropdown-item py-2 btn-detail" href="#"
                                                       data-kode="<?= $a['kode_aset'] ?>"
                                                       data-nama="<?= htmlspecialchars($a['nama_aset']) ?>"
                                                       data-kategori="<?= htmlspecialchars($a['nama_kategori']) ?>"
                                                       data-merk="<?= htmlspecialchars($a['merk'] ?: '-') ?>"
                                                       data-model="<?= htmlspecialchars($a['model'] ?: '-') ?>"
                                                       data-sn="<?= htmlspecialchars($a['serial_number'] ?: '-') ?>"
                                                       data-cabang="<?= htmlspecialchars($a['nama_cabang']) ?>"
                                                       data-divisi="<?= htmlspecialchars($a['nama_divisi']) ?>"
                                                       data-karyawan="<?= htmlspecialchars($a['nama_karyawan'] ?: 'Unassigned') ?>"
                                                       data-kondisi="<?= $a['kondisi'] ?>"
                                                       data-garansi="<?= !empty($a['tanggal_kadaluarsa_garansi']) ? date('d M Y', strtotime($a['tanggal_kadaluarsa_garansi'])) : '-' ?>"
                                                       data-spesifikasi="<?= htmlspecialchars($a['spesifikasi'] ?: '') ?>"
                                                       data-foto="<?= htmlspecialchars($a['foto'] ?? '') ?>">
                                                    <i class="bi bi-eye me-2 text-info"></i> Lihat Detail</a></li>
                                                <?php if ($a['kondisi'] !== 'Baik'): ?>
                                                    <li><a class="dropdown-item py-2" href="index.php?page=perbaikan&asset_id=<?= $a['id'] ?>">
                                                        <i class="bi bi-wrench me-2 text-warning"></i> Laporkan Perbaikan</a></li>
                                                <?php endif; ?>
                                            <li><a class="dropdown-item py-2 btn-qr" href="#" 
                                                   data-kode="<?= $a['kode_aset'] ?>"
                                                   data-nama="<?= htmlspecialchars($a['nama_aset']) ?>"
                                                   data-cabang="<?= htmlspecialchars($a['nama_cabang']) ?>"
                                                   data-divisi="<?= htmlspecialchars($a['nama_divisi']) ?>"
                                                   data-karyawan="<?= htmlspecialchars($a['nama_karyawan'] ?: 'Unassigned') ?>">
                                                <i class="bi bi-qr-code me-2 text-primary"></i> Tampilkan QR Code</a></li>
                                            <li><a class="dropdown-item py-2 btn-edit" href="#" 
                                                   data-id="<?= $a['id'] ?>"
                                                   data-kode="<?= $a['kode_aset'] ?>"
                                                   data-nama="<?= htmlspecialchars($a['nama_aset']) ?>"
                                                   data-sn="<?= htmlspecialchars($a['serial_number'] ?: '') ?>"
                                                   data-kategori="<?= $a['id_kategori'] ?>"
                                                   data-merk="<?= htmlspecialchars($a['merk'] ?: '') ?>"
                                                   data-model="<?= htmlspecialchars($a['model'] ?: '') ?>"
                                                   data-cabang="<?= $a['id_cabang'] ?>"
                                                   data-divisi="<?= $a['id_divisi'] ?>"
                                                   data-karyawan="<?= $a['id_karyawan'] ?>"
                                                   data-kondisi="<?= $a['kondisi'] ?>"
                                                   data-garansi="<?= htmlspecialchars($a['tanggal_kadaluarsa_garansi'] ?: '') ?>"
                                                   data-spesifikasi="<?= htmlspecialchars($a['spesifikasi'] ?: '') ?>"
                                                   data-foto="<?= htmlspecialchars($a['foto'] ?? '') ?>">
                                                <i class="bi bi-pencil me-2 text-warning"></i> Edit Aset</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" onsubmit="return confirm('Hapus aset ini secara permanen?')">
                                                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                                    <button type="submit" name="hapus" class="dropdown-item py-2 text-danger">
                                                        <i class="bi bi-trash text-danger me-2"></i> Hapus Aset
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Layout -->
            <div class="d-block d-md-none p-3" id="mobileAssetContainer">
                <?php foreach ($assets as $a): ?>
                    <div class="card border p-3.5 mb-3 rounded-3 shadow-sm mobile-asset-card-item" data-search="<?= htmlspecialchars(strtolower($a['kode_aset'] . ' ' . $a['nama_aset'] . ' ' . ($a['serial_number'] ?? '') . ' ' . ($a['merk'] ?? '') . ' ' . ($a['model'] ?? '') . ' ' . ($a['nama_cabang'] . ' ' . ($a['nama_karyawan'] ?? '')))) ?>">
                        <div class="d-flex justify-content-between align-items-start mb-2.5">
                            <div>
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded px-2 py-1 small fw-bold" style="font-size: 0.7rem;"><?= $a['kode_aset'] ?></span>
                                <h6 class="fw-bold text-dark mt-2 mb-0.5"><?= htmlspecialchars($a['nama_aset']) ?></h6>
                                <small class="text-muted d-block"><?= htmlspecialchars($a['merk'] . ' ' . $a['model']) ?> &bull; SN: <?= htmlspecialchars($a['serial_number'] ?: '-') ?></small>
                                <?php if (!empty($a['spesifikasi'])): ?>
                                    <small class="text-secondary d-block mt-1" style="font-size: 0.7rem;"><i class="bi bi-cpu me-1"></i><?= htmlspecialchars($a['spesifikasi']) ?></small>
                                <?php endif; ?>
                            </div>
                            <?php 
                            $bg = 'success';
                            if ($a['kondisi'] == 'Rusak Ringan') $bg = 'warning';
                            if ($a['kondisi'] == 'Rusak Berat') $bg = 'danger';
                            ?>
                            <span class="badge bg-<?= $bg ?> bg-opacity-10 text-<?= $bg ?> rounded-pill px-2.5 py-1 small" style="font-size: 0.65rem; font-weight: 700;">
                                <?= strtoupper($a['kondisi']) ?>
                            </span>
                        </div>
                        
                        <div class="p-2.5 bg-light rounded-3 mb-3 small row g-2">
                            <div class="col-4"><span class="text-muted text-xs">Lokasi:</span><br><strong class="text-truncate d-block" style="max-width:100%;"><?= htmlspecialchars($a['nama_cabang']) ?></strong></div>
                            <div class="col-4"><span class="text-muted text-xs">Assignee:</span><br><strong class="text-truncate d-block" style="max-width:100%;"><?= htmlspecialchars($a['nama_karyawan'] ?: 'Unassigned') ?></strong></div>
                            <div class="col-4">
                                <span class="text-muted text-xs">Garansi:</span><br>
                                <?php 
                                if (!empty($a['tanggal_kadaluarsa_garansi'])) {
                                    $exp_date = strtotime($a['tanggal_kadaluarsa_garansi']);
                                    $today = strtotime(date('Y-m-d'));
                                    $diff_days = round(($exp_date - $today) / 86400);
                                    if ($diff_days > 30) {
                                        echo '<span class="text-success fw-bold">Aktif</span>';
                                    } elseif ($diff_days >= 0) {
                                        echo '<span class="text-warning fw-bold">Limit</span>';
                                    } else {
                                        echo '<span class="text-danger fw-bold">Habis</span>';
                                    }
                                } else {
                                    echo '<span class="text-muted">-</span>';
                                }
                                ?>
                            </div>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <?php if ($a['kondisi'] !== 'Baik'): ?>
                                <a href="index.php?page=perbaikan&asset_id=<?= $a['id'] ?>" class="btn btn-sm btn-warning text-dark flex-fill fw-bold py-2 d-flex align-items-center justify-content-center gap-1 shadow-sm">
                                    <i class="bi bi-wrench" style="font-size: 0.75rem;"></i> Perbaikan
                                </a>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-info flex-fill fw-bold py-2 btn-detail" 
                                    data-kode="<?= $a['kode_aset'] ?>"
                                    data-nama="<?= htmlspecialchars($a['nama_aset']) ?>"
                                    data-kategori="<?= htmlspecialchars($a['nama_kategori']) ?>"
                                    data-merk="<?= htmlspecialchars($a['merk'] ?: '-') ?>"
                                    data-model="<?= htmlspecialchars($a['model'] ?: '-') ?>"
                                    data-sn="<?= htmlspecialchars($a['serial_number'] ?: '-') ?>"
                                    data-cabang="<?= htmlspecialchars($a['nama_cabang']) ?>"
                                    data-divisi="<?= htmlspecialchars($a['nama_divisi']) ?>"
                                    data-karyawan="<?= htmlspecialchars($a['nama_karyawan'] ?: 'Unassigned') ?>"
                                    data-kondisi="<?= $a['kondisi'] ?>"
                                    data-garansi="<?= !empty($a['tanggal_kadaluarsa_garansi']) ? date('d M Y', strtotime($a['tanggal_kadaluarsa_garansi'])) : '-' ?>"
                                    data-spesifikasi="<?= htmlspecialchars($a['spesifikasi'] ?: '') ?>"
                                    data-foto="<?= htmlspecialchars($a['foto'] ?? '') ?>">
                                <i class="bi bi-eye"></i> Detail
                            </button>
                            <button class="btn btn-sm btn-outline-primary flex-fill fw-bold py-2 btn-qr" 
                                    data-kode="<?= $a['kode_aset'] ?>"
                                    data-nama="<?= htmlspecialchars($a['nama_aset']) ?>"
                                    data-cabang="<?= htmlspecialchars($a['nama_cabang']) ?>"
                                    data-divisi="<?= htmlspecialchars($a['nama_divisi']) ?>"
                                    data-karyawan="<?= htmlspecialchars($a['nama_karyawan'] ?: 'Unassigned') ?>">
                                <i class="bi bi-qr-code me-1"></i> QR
                            </button>
                            <button class="btn btn-sm btn-outline-warning flex-fill fw-bold py-2 btn-edit" 
                                   data-id="<?= $a['id'] ?>"
                                   data-kode="<?= $a['kode_aset'] ?>"
                                   data-nama="<?= htmlspecialchars($a['nama_aset']) ?>"
                                   data-sn="<?= htmlspecialchars($a['serial_number'] ?: '') ?>"
                                   data-kategori="<?= $a['id_kategori'] ?>"
                                   data-merk="<?= htmlspecialchars($a['merk'] ?: '') ?>"
                                   data-model="<?= htmlspecialchars($a['model'] ?: '') ?>"
                                   data-cabang="<?= $a['id_cabang'] ?>"
                                   data-divisi="<?= $a['id_divisi'] ?>"
                                   data-karyawan="<?= $a['id_karyawan'] ?>"
                                   data-kondisi="<?= $a['kondisi'] ?>"
                                   data-garansi="<?= htmlspecialchars($a['tanggal_kadaluarsa_garansi'] ?: '') ?>"
                                   data-spesifikasi="<?= htmlspecialchars($a['spesifikasi'] ?: '') ?>"
                                   data-foto="<?= htmlspecialchars($a['foto'] ?? '') ?>">
                                <i class="bi bi-pencil me-1"></i> Edit
                            </button>
                            <form method="POST" class="flex-fill" onsubmit="return confirm('Hapus aset ini secara permanen?')">
                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                <button type="submit" name="hapus" class="btn btn-sm btn-outline-danger w-100 fw-bold py-2">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Pagination Controls -->
        <?php 
        $paginationUrl = 'index.php?page=inventaris';
        if ($id_cabang_filter) {
            $paginationUrl .= '&filter_cabang=' . urlencode($id_cabang_filter);
        }
        if ($filter_kondisi) {
            $paginationUrl .= '&filter_kondisi=' . urlencode($filter_kondisi);
        }
        if ($search_query) {
            $paginationUrl .= '&search=' . urlencode($search_query);
        }
        ?>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white border-top-0 pt-2 pb-4 d-flex justify-content-center">
            <?= getPaginationControls($pageNumber, $totalPages, $paginationUrl) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 28px;">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-800 m-0"><i class="bi bi-plus-circle-fill text-primary me-2"></i> Daftarkan Aset Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-4">Lengkapi formulir spesifikasi teknis dan unit alokasi penugasan baru di bawah ini.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Kode Aset <span class="text-danger">*</span></label>
                            <input type="text" name="kode_aset" id="add_kode_aset" class="form-control bg-light border-0" placeholder="Contoh: LPT-001" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Serial Number (SN)</label>
                            <input type="text" name="serial_number" class="form-control bg-light border-0" placeholder="Contoh: SN12345678">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-muted">Nama Aset / Perangkat <span class="text-danger">*</span></label>
                            <input type="text" name="nama_aset" class="form-control bg-light border-0" placeholder="Contoh: ThinkPad X1 Carbon Gen 11" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Kategori</label>
                            <select name="id_kategori" id="add_id_kategori" class="form-select bg-light border-0" onchange="autoGenerateAssetCode(this.value)">
                                <?php foreach ($kategoris as $k): ?>
                                    <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Merk</label>
                            <input type="text" name="merk" class="form-control bg-light border-0" placeholder="Contoh: Lenovo">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Model / Seri</label>
                            <input type="text" name="model" class="form-control bg-light border-0" placeholder="Contoh: 20BS003A">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Kantor Cabang <span class="text-danger">*</span></label>
                            <select name="id_cabang" id="select_cabang" class="form-select bg-light border-0" required>
                                <option value="">-- Pilih Cabang --</option>
                                <?php foreach ($cabangs as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nama_cabang']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Divisi Departemen</label>
                            <select name="id_divisi" id="select_divisi" class="form-select bg-light border-0">
                                <option value="">-- Pilih Divisi --</option>
                                <?php foreach ($divisis as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nama_divisi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Penanggung Jawab (User)</label>
                            <select name="id_karyawan" id="select_karyawan" class="form-select bg-light border-0">
                                <option value="">-- Belum Ditugaskan (Unassigned) --</option>
                                <?php foreach ($karyawans as $kr): ?>
                                    <option value="<?= $kr['id'] ?>" data-cabang="<?= $kr['id_cabang'] ?>" data-divisi="<?= $kr['id_divisi'] ?>"><?= htmlspecialchars($kr['nama_karyawan']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Kondisi Fisik</label>
                            <select name="kondisi" class="form-select bg-light border-0">
                                <option value="Baik">Baik (Excellent)</option>
                                <option value="Rusak Ringan">Rusak Ringan</option>
                                <option value="Rusak Berat">Rusak Berat</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Tanggal Habis Garansi</label>
                            <input type="date" name="tanggal_kadaluarsa_garansi" class="form-control bg-light border-0">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-muted">Spesifikasi / Detail Teknis</label>
                            <textarea name="spesifikasi" class="form-control bg-light border-0" rows="3" placeholder="Contoh: RAM 16GB, SSD 512GB, Intel Core i7"></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-muted">Foto Aset (Opsional - Google Drive)</label>
                            <input type="file" name="foto" class="form-control bg-light border-0" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 shadow-sm" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary px-4 shadow-sm">Daftarkan Aset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 28px;">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-800 m-0"><i class="bi bi-pencil-square text-warning me-2"></i> Perbarui Detail Aset</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Kode Aset</label>
                            <input type="text" name="kode_aset" id="edit_kode" class="form-control bg-light border-0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Serial Number</label>
                            <input type="text" name="serial_number" id="edit_sn" class="form-control bg-light border-0">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-muted">Nama Aset / Perangkat</label>
                            <input type="text" name="nama_aset" id="edit_nama" class="form-control bg-light border-0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Kategori</label>
                            <select name="id_kategori" id="edit_kategori" class="form-select bg-light border-0">
                                <?php foreach ($kategoris as $k): ?>
                                    <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Merk</label>
                            <input type="text" name="merk" id="edit_merk" class="form-control bg-light border-0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Model</label>
                            <input type="text" name="model" id="edit_model" class="form-control bg-light border-0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Kantor Cabang</label>
                            <select name="id_cabang" id="edit_select_cabang" class="form-select bg-light border-0" required>
                                <option value="">-- Pilih Cabang --</option>
                                <?php foreach ($cabangs as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nama_cabang']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Divisi Departemen</label>
                            <select name="id_divisi" id="edit_select_divisi" class="form-select bg-light border-0">
                                <option value="">-- Pilih Divisi --</option>
                                <?php foreach ($divisis as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nama_divisi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Penanggung Jawab (User)</label>
                            <select name="id_karyawan" id="edit_select_karyawan" class="form-select bg-light border-0">
                                <option value="">-- Belum Ditugaskan (Unassigned) --</option>
                                <?php foreach ($karyawans as $kr): ?>
                                    <option value="<?= $kr['id'] ?>" data-cabang="<?= $kr['id_cabang'] ?>" data-divisi="<?= $kr['id_divisi'] ?>"><?= htmlspecialchars($kr['nama_karyawan']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Kondisi Fisik</label>
                            <select name="kondisi" id="edit_kondisi" class="form-select bg-light border-0">
                                <option value="Baik">Baik (Excellent)</option>
                                <option value="Rusak Ringan">Rusak Ringan</option>
                                <option value="Rusak Berat">Rusak Berat</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Tanggal Habis Garansi</label>
                            <input type="date" name="tanggal_kadaluarsa_garansi" id="edit_garansi" class="form-control bg-light border-0">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-muted">Spesifikasi / Detail Teknis</label>
                            <textarea name="spesifikasi" id="edit_spesifikasi" class="form-control bg-light border-0" rows="3" placeholder="Contoh: RAM 16GB, SSD 512GB, Intel Core i7"></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-muted">Foto Aset (Opsional - Google Drive)</label>
                            <input type="file" name="foto" class="form-control bg-light border-0" accept="image/*">
                            <div id="edit_foto_preview_container" class="mt-2 d-none">
                                <span class="small text-muted">Foto Saat Ini:</span><br>
                                <a id="edit_foto_link" href="#" target="_blank" class="small text-primary"><i class="bi bi-image me-1"></i> Lihat Foto</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 shadow-sm" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                    <button type="submit" name="update" class="btn btn-warning px-4 shadow-sm text-dark fw-bold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function autoGenerateAssetCode(categoryId) {
    if (!categoryId) return;
    fetch(`api/generate_asset_code.php?kategori_id=${categoryId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.code) {
                document.getElementById('add_kode_aset').value = data.code;
            }
        })
        .catch(err => console.error('Error generating code:', err));
}

// Trigger auto-code generation when modal opens
document.addEventListener('DOMContentLoaded', function() {
    const btnTambah = document.querySelector('[data-bs-target="#modalTambah"]');
    if (btnTambah) {
        btnTambah.addEventListener('click', function() {
            const catSelect = document.getElementById('add_id_kategori');
            if (catSelect) {
                autoGenerateAssetCode(catSelect.value);
            }
        });
    }
});

function filterKaryawan(cabangSelectId, divisiSelectId, karyawanSelectId) {
    const selectedCabangId = document.getElementById(cabangSelectId).value;
    const selectedDivisiId = document.getElementById(divisiSelectId).value;
    const selectKaryawan = document.getElementById(karyawanSelectId);
    const options = selectKaryawan.querySelectorAll('option');

    options.forEach(option => {
        const cabangId = option.getAttribute('data-cabang');
        const divisiId = option.getAttribute('data-divisi');
        
        // Option "Belum Ditugaskan"
        if (!option.value) {
            option.style.display = 'block';
            return;
        }

        let showCabang = true;
        let showDivisi = true;

        if (selectedCabangId && cabangId && cabangId !== selectedCabangId) {
            showCabang = false;
        }
        if (selectedDivisiId && divisiId && divisiId !== selectedDivisiId) {
            showDivisi = false;
        }

        if (showCabang && showDivisi) {
            option.style.display = 'block';
        } else {
            option.style.display = 'none';
        }
    });
}

document.getElementById('select_cabang').addEventListener('change', function() {
    filterKaryawan('select_cabang', 'select_divisi', 'select_karyawan');
    document.getElementById('select_karyawan').value = "";
});

document.getElementById('select_divisi').addEventListener('change', function() {
    filterKaryawan('select_cabang', 'select_divisi', 'select_karyawan');
    document.getElementById('select_karyawan').value = "";
});

document.getElementById('edit_select_cabang').addEventListener('change', function() {
    filterKaryawan('edit_select_cabang', 'edit_select_divisi', 'edit_select_karyawan');
    document.getElementById('edit_select_karyawan').value = "";
});

document.getElementById('edit_select_divisi').addEventListener('change', function() {
    filterKaryawan('edit_select_cabang', 'edit_select_divisi', 'edit_select_karyawan');
    document.getElementById('edit_select_karyawan').value = "";
});

// Client search query filtering (Obsolete - Handled Server-Side)

// Bind Edit Button Values to Edit Modal Input Fields
document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const id = this.getAttribute('data-id');
        const kode = this.getAttribute('data-kode');
        const nama = this.getAttribute('data-nama');
        const sn = this.getAttribute('data-sn');
        const kategori = this.getAttribute('data-kategori');
        const merk = this.getAttribute('data-merk');
        const model = this.getAttribute('data-model');
        const cabang = this.getAttribute('data-cabang');
        const divisi = this.getAttribute('data-divisi');
        const karyawan = this.getAttribute('data-karyawan');
        const kondisi = this.getAttribute('data-kondisi');
        const garansi = this.getAttribute('data-garansi');
        const spesifikasi = this.getAttribute('data-spesifikasi');
        const foto = this.getAttribute('data-foto');

        document.getElementById('edit_id').value = id;
        document.getElementById('edit_kode').value = kode;
        document.getElementById('edit_nama').value = nama;
        document.getElementById('edit_sn').value = sn;
        document.getElementById('edit_kategori').value = kategori;
        document.getElementById('edit_merk').value = merk;
        document.getElementById('edit_model').value = model;
        document.getElementById('edit_select_cabang').value = cabang;
        document.getElementById('edit_select_divisi').value = divisi;
        
        filterKaryawan('edit_select_cabang', 'edit_select_divisi', 'edit_select_karyawan');
        document.getElementById('edit_select_karyawan').value = karyawan || "";
        document.getElementById('edit_kondisi').value = kondisi;
        document.getElementById('edit_garansi').value = garansi || "";
        document.getElementById('edit_spesifikasi').value = spesifikasi || "";

        const previewContainer = document.getElementById('edit_foto_preview_container');
        const previewLink = document.getElementById('edit_foto_link');
        if (previewContainer && previewLink) {
            if (foto) {
                previewLink.href = foto;
                previewContainer.classList.remove('d-none');
            } else {
                previewLink.href = '#';
                previewContainer.classList.add('d-none');
            }
        }

        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    });
});

// QR Code Modal Bindings
document.querySelectorAll('.btn-qr').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const code = this.getAttribute('data-kode');
        const name = this.getAttribute('data-nama');
        const branch = this.getAttribute('data-cabang');
        const division = this.getAttribute('data-divisi');
        const user = this.getAttribute('data-karyawan');
        
        document.getElementById('qrCodeImage').src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(code)}`;
        document.getElementById('qrAssetCode').innerText = code;
        document.getElementById('qrAssetName').innerText = name;
        document.getElementById('qrAssetLocation').innerText = `${branch} - ${division}`;
        document.getElementById('qrAssetUser').innerText = user;
        
        var qrModal = new bootstrap.Modal(document.getElementById('modalQRCode'));
        qrModal.show();
    });
});

// Bind Detail Button Values to Detail Modal
document.querySelectorAll('.btn-detail').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const kode = this.getAttribute('data-kode');
        const nama = this.getAttribute('data-nama');
        const kategori = this.getAttribute('data-kategori');
        const merk = this.getAttribute('data-merk');
        const model = this.getAttribute('data-model');
        const sn = this.getAttribute('data-sn');
        const cabang = this.getAttribute('data-cabang');
        const divisi = this.getAttribute('data-divisi');
        const karyawan = this.getAttribute('data-karyawan');
        const kondisi = this.getAttribute('data-kondisi');
        const garansi = this.getAttribute('data-garansi');
        const spesifikasi = this.getAttribute('data-spesifikasi');
        const foto = this.getAttribute('data-foto');

        document.getElementById('detail_kode').innerText = kode;
        document.getElementById('detail_nama').innerText = nama;
        document.getElementById('detail_kategori').innerText = kategori;
        document.getElementById('detail_merk_model').innerText = `${merk} / ${model}`;
        document.getElementById('detail_sn').innerText = sn;
        document.getElementById('detail_lokasi').innerText = `${cabang} - ${divisi}`;
        document.getElementById('detail_user').innerText = karyawan;
        document.getElementById('detail_garansi').innerText = garansi || '-';
        document.getElementById('detail_spesifikasi').innerText = spesifikasi || '-';
        
        const badgeKondisi = document.getElementById('detail_kondisi');
        badgeKondisi.innerText = kondisi.toUpperCase();
        badgeKondisi.className = 'badge px-3 py-2 rounded-pill ';
        if (kondisi === 'Baik') {
            badgeKondisi.className += 'bg-success bg-opacity-10 text-success';
        } else if (kondisi === 'Rusak Ringan') {
            badgeKondisi.className += 'bg-warning bg-opacity-10 text-warning';
        } else {
            badgeKondisi.className += 'bg-danger bg-opacity-10 text-danger';
        }

        const fotoRow = document.getElementById('detail_foto_row');
        const fotoLink = document.getElementById('detail_foto_link');
        const fotoImg = document.getElementById('detail_foto_img');
        if (fotoRow && fotoLink && fotoImg) {
            if (foto && foto.trim() !== '') {
                fotoLink.href = foto;
                fotoImg.src = foto;
                fotoRow.classList.remove('d-none');
            } else {
                fotoLink.href = '#';
                fotoImg.src = '';
                fotoRow.classList.add('d-none');
            }
        }

        new bootstrap.Modal(document.getElementById('modalDetailAset')).show();
    });
});

function printQRLabel() {
    const code = document.getElementById('qrAssetCode').innerText;
    const name = document.getElementById('qrAssetName').innerText;
    const location = document.getElementById('qrAssetLocation').innerText;
    const user = document.getElementById('qrAssetUser').innerText;
    const qrSrc = document.getElementById('qrCodeImage').src;

    const printWindow = window.open('', '_blank', 'width=420,height=420');
    printWindow.document.write(`
        <html>
        <head>
            <title>Cetak QR - ${code}</title>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap');
                body {
                    font-family: 'Plus Jakarta Sans', sans-serif;
                    text-align: center;
                    padding: 20px;
                    margin: 0;
                    background: #fff;
                }
                .label-container {
                    border: 2px solid #1e293b;
                    border-radius: 14px;
                    padding: 20px;
                    display: inline-block;
                    width: 280px;
                    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
                }
                .qr-img {
                    margin-bottom: 12px;
                }
                .code {
                    font-size: 1.35rem;
                    font-weight: 800;
                    color: #0f172a;
                    margin: 5px 0;
                    letter-spacing: -0.5px;
                }
                .title {
                    font-size: 0.95rem;
                    font-weight: 700;
                    color: #334155;
                    margin-bottom: 8px;
                }
                .detail {
                    font-size: 0.78rem;
                    color: #64748b;
                    margin-top: 3px;
                }
            </style>
        </head>
        <body onload="window.print(); window.close();">
            <div class="label-container">
                <img class="qr-img" src="${qrSrc}" width="150" height="150">
                <div class="code">${code}</div>
                <div class="title">${name}</div>
                <div class="detail">Lokasi: <strong>${location}</strong></div>
                <div class="detail">User: <strong>${user}</strong></div>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// Compress image on client side before upload to prevent server timeout
document.querySelectorAll('input[type="file"][name="foto"]').forEach(input => {
    input.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        // Only compress images
        if (!file.type.startsWith('image/')) return;

        // Disable submit button and show loading state
        const submitBtn = this.form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.dataset.originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mengompres Gambar...';
        }

        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = function(event) {
            const img = new Image();
            img.src = event.target.result;
            img.onload = function() {
                const canvas = document.createElement('canvas');
                const MAX_WIDTH = 1000;
                const MAX_HEIGHT = 1000;
                let width = img.width;
                let height = img.height;

                if (width > height) {
                    if (width > MAX_WIDTH) {
                        height *= MAX_WIDTH / width;
                        width = MAX_WIDTH;
                    }
                } else {
                    if (height > MAX_HEIGHT) {
                        width *= MAX_HEIGHT / height;
                        height = MAX_HEIGHT;
                    }
                }

                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob(function(blob) {
                    // Create a new file from blob
                    const compressedFile = new File([blob], file.name, {
                        type: 'image/jpeg',
                        lastModified: Date.now()
                    });

                    // Replace files in the input element using DataTransfer
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(compressedFile);
                    input.files = dataTransfer.files;

                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = submitBtn.dataset.originalText;
                    }
                }, 'image/jpeg', 0.7); // 0.7 quality Jpeg
            };
        };
    });
});
</script>

<!-- Modal Detail Aset -->
<div class="modal fade" id="modalDetailAset" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px;">
            <div class="modal-header border-0 p-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-800 m-0"><i class="bi bi-info-circle-fill text-primary me-2"></i> Detail Rincian Aset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded mb-2 fw-bold" id="detail_kode" style="font-size: 0.9rem;">-</span>
                    <h4 class="fw-800 text-dark mb-1" id="detail_nama">-</h4>
                    <span id="detail_kondisi" class="badge bg-success bg-opacity-10 text-success px-3 py-1.5 rounded-pill fw-bold">-</span>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-sm table-borderless align-middle small mb-0">
                        <tbody>
                            <tr class="border-bottom py-2">
                                <td class="text-muted fw-semibold py-2" width="140">Kategori</td>
                                <td class="text-dark fw-bold py-2" id="detail_kategori">-</td>
                            </tr>
                            <tr class="border-bottom py-2">
                                <td class="text-muted fw-semibold py-2">Merk / Model</td>
                                <td class="text-dark fw-bold py-2" id="detail_merk_model">-</td>
                            </tr>
                            <tr class="border-bottom py-2">
                                <td class="text-muted fw-semibold py-2">Serial Number</td>
                                <td class="text-dark fw-bold py-2" id="detail_sn">-</td>
                            </tr>
                            <tr class="border-bottom py-2">
                                <td class="text-muted fw-semibold py-2">Masa Berlaku Garansi</td>
                                <td class="text-dark fw-bold py-2" id="detail_garansi">-</td>
                            </tr>
                            <tr class="border-bottom py-2">
                                <td class="text-muted fw-semibold py-2">Lokasi & Divisi</td>
                                <td class="text-dark fw-bold py-2" id="detail_lokasi">-</td>
                            </tr>
                            <tr class="border-bottom py-2">
                                <td class="text-muted fw-semibold py-2">Penanggung Jawab</td>
                                <td class="text-dark fw-bold py-2" id="detail_user">-</td>
                            </tr>
                            <tr class="border-bottom py-2">
                                <td class="text-muted fw-semibold py-2" valign="top">Spesifikasi</td>
                                <td class="text-dark fw-bold py-2" style="white-space: pre-line;" id="detail_spesifikasi">-</td>
                            </tr>
                            <tr class="border-bottom py-2 d-none" id="detail_foto_row">
                                <td class="text-muted fw-semibold py-2" valign="top">Foto Aset</td>
                                <td class="py-2">
                                    <a id="detail_foto_link" href="#" target="_blank" class="d-block mb-2 text-primary small fw-bold">
                                        <i class="bi bi-box-arrow-up-right me-1"></i> Buka di Tab Baru
                                    </a>
                                    <img id="detail_foto_img" src="" alt="Foto Aset" class="img-fluid rounded-3 border shadow-sm" style="max-height: 200px; object-fit: cover;">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light w-100 py-2.5 rounded-pill shadow-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal QR Code -->
<div class="modal fade" id="modalQRCode" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px;">
            <div class="modal-header border-0 p-4 pb-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-800 m-0"><i class="bi bi-qr-code text-primary me-2"></i> Label QR Code</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="p-3 bg-white rounded-4 shadow-sm border d-inline-block mb-3">
                    <img id="qrCodeImage" src="" alt="QR Code" width="150" height="150">
                </div>
                <h5 class="fw-bold text-dark mb-1" id="qrAssetCode">-</h5>
                <span class="text-muted small d-block text-truncate mb-3" style="max-width: 220px;" id="qrAssetName">-</span>
                
                <div class="p-3 rounded-3 bg-light text-start small">
                    <div class="mb-1.5"><span class="text-muted text-xs">Lokasi:</span> <strong id="qrAssetLocation" class="text-dark">-</strong></div>
                    <div><span class="text-muted text-xs">Assignee:</span> <strong id="qrAssetUser" class="text-dark">-</strong></div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-primary w-100 py-2.5 rounded-pill shadow-sm" onclick="printQRLabel()">
                    <i class="bi bi-printer me-2"></i> Cetak Label
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .fw-800 { font-weight: 800; }
    .fw-500 { font-weight: 500; }
    .dropdown-item i { width: 20px; }
    .cursor-pointer { cursor: pointer; }
</style>
