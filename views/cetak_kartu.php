<?php
require_once 'models/InventarisKartu.php';
require_once 'models/Cabang.php';

$inventarisModel = new InventarisKartu($conn);
$cabangModel = new Cabang($conn);

$branches = $cabangModel->getAll();

// Map branch ID to branch name for autofill
$branchMap = [];
foreach ($branches as $b) {
    $code = str_pad($b['id'], 2, '0', STR_PAD_LEFT);
    $branchMap[$code] = $b['nama_cabang'];
}

// Proses Hapus
if (isset($_POST['hapus'])) {
    $id = $_POST['id'];
    if ($inventarisModel->delete($id)) {
        header("Location: index.php?page=cetak_kartu&status=deleted");
        exit();
    }
}

// Proses Hapus Massal
if (isset($_POST['hapus_massal'])) {
    $ids = $_POST['ids'] ?? [];
    if (!empty($ids) && is_array($ids)) {
        $deletedCount = count($ids);
        if ($inventarisModel->deleteMultiple($ids)) {
            header("Location: index.php?page=cetak_kartu&status=deleted_massal&count=" . $deletedCount);
            exit();
        }
    }
}

// Proses Impor dari Aset IT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_assets'])) {
    $selectedAssetIds = $_POST['asset_ids'] ?? [];
    if (!empty($selectedAssetIds)) {
        $placeholders = implode(',', array_fill(0, count($selectedAssetIds), '?'));
        $stmt = $conn->prepare("
            SELECT a.kode_aset, a.nama_aset, a.created_at
            FROM assets a
            WHERE a.id IN ($placeholders)
        ");
        $stmt->execute($selectedAssetIds);
        $imported = $stmt->fetchAll();
        
        $stmtInsert = $conn->prepare("
            INSERT INTO inventaris_kartu (nomor_rekening, nama_barang, tanggal_perolehan, barcode_data)
            VALUES (?, ?, ?, ?)
        ");
        
        $successCount = 0;
        foreach ($imported as $imp) {
            $tanggal = date('Y-m-d', strtotime($imp['created_at']));
            $stmtInsert->execute([
                $imp['kode_aset'],
                $imp['nama_aset'],
                $tanggal,
                $imp['kode_aset']
            ]);
            $successCount++;
        }
        
        header("Location: index.php?page=cetak_kartu&status=success_import&count=" . $successCount);
        exit();
    }
}

// Proses Tambah
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah'])) {
    $data = [
        'nomor_rekening' => trim($_POST['nomor_rekening']),
        'nama_barang' => trim($_POST['nama_barang']),
        'tanggal_perolehan' => $_POST['tanggal_perolehan'],
        'barcode_data' => trim($_POST['barcode_data'])
    ];
    if ($inventarisModel->create($data)) {
        header("Location: index.php?page=cetak_kartu&status=success");
        exit();
    }
}

// Proses Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $id = $_POST['id'];
    $data = [
        'nomor_rekening' => trim($_POST['nomor_rekening']),
        'nama_barang' => trim($_POST['nama_barang']),
        'tanggal_perolehan' => $_POST['tanggal_perolehan'],
        'barcode_data' => trim($_POST['barcode_data'])
    ];
    if ($inventarisModel->update($id, $data)) {
        header("Location: index.php?page=cetak_kartu&status=updated");
        exit();
    }
}

// Hanya ambil data input manual khusus dari tabel inventaris_kartu
$items = $inventarisModel->getAll();

// Ambil daftar aset IT untuk modal impor
try {
    $stmtAssetsList = $conn->query("
        SELECT a.id, a.kode_aset, a.nama_aset, a.kondisi, a.created_at, 
               c.nama_cabang, d.nama_divisi, k.nama_kategori
        FROM assets a
        LEFT JOIN cabang c ON a.id_cabang = c.id
        LEFT JOIN divisi d ON a.id_divisi = d.id
        LEFT JOIN kategori_aset k ON a.id_kategori = k.id
        ORDER BY a.created_at DESC
    ");
    $assetsList = $stmtAssetsList->fetchAll();
} catch (PDOException $e) {
    $assetsList = [];
}

// Path preloading logo kustom
$base_dir_path = dirname($_SERVER['SCRIPT_NAME']);
$base_dir_path = str_replace('\\', '/', $base_dir_path);
if ($base_dir_path === '/') {
    $base_dir_path = '';
}
$preload_logo_path = $base_dir_path . '/assets/LOGO TYPE 2.png';
?>

<!-- Form Hidden Hapus Massal -->
<form id="formHapusMassal" action="index.php?page=cetak_kartu" method="POST" style="display: none;">
    <input type="hidden" name="hapus_massal" value="1">
    <div id="hapusMassalInputs"></div>
</form>

<!-- Preload logo to browser cache to ensure it prints instantly -->
<img src="<?= htmlspecialchars($preload_logo_path) ?>" style="display: none;">

<!-- QR Code Generator Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<div class="container-fluid animate-fade-in">
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center">
            <div class="bg-primary bg-opacity-10 p-2.5 rounded-3 me-3 text-primary">
                <i class="bi bi-card-heading fs-4"></i>
            </div>
            <div>
                <h4 class="fw-800 m-0">Cetak Kartu Inventaris <span class="badge bg-primary bg-opacity-10 text-primary fs-6 fw-bold ms-2">Rekap IT - Asset Management</span></h4>
                <p class="text-muted small m-0">Kelola dan cetak kartu inventaris berukuran ATM (CR80) secara massal - Rekap IT - Asset Management.</p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" id="btnHapusMassal" class="btn btn-outline-danger shadow-sm" disabled title="Hapus kartu terpilih secara sekaligus">
                <i class="bi bi-trash me-1"></i> Hapus Terpilih (<span id="deleteSelectedCount">0</span>)
            </button>
            <button type="button" id="btnCetakMassal" class="btn btn-outline-primary shadow-sm" disabled>
                <i class="bi bi-printer me-2"></i> Cetak Kartu Pilihan (<span id="selectedCount">0</span>)
            </button>
            <button class="btn btn-outline-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalImportAsset">
                <i class="bi bi-box-seam me-2"></i> Pilih dari Aset IT
            </button>
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="bi bi-plus-lg me-2"></i> Tambah Data Kartu
            </button>
        </div>
    </div>


    <!-- Notification Alert -->
    <?php if (isset($_GET['status'])): 
        $status = $_GET['status'];
        $msg = "Berhasil memproses data!";
        if ($status === 'success') $msg = "Data kartu inventaris baru berhasil ditambahkan!";
        if ($status === 'success_import') {
            $count = isset($_GET['count']) ? intval($_GET['count']) : 0;
            $msg = "Berhasil mengimpor $count data aset dari database Aset IT secara otomatis!";
        }
        if ($status === 'updated') $msg = "Perubahan data kartu berhasil disimpan!";
        if ($status === 'deleted') $msg = "Data kartu inventaris berhasil dihapus!";
        if ($status === 'deleted_massal') {
            $count = isset($_GET['count']) ? intval($_GET['count']) : 0;
            $msg = "Berhasil menghapus $count data kartu inventaris secara massal!";
        }
    ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 p-3 mb-4 d-flex align-items-center justify-content-between animate-fade-in" role="alert" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <span class="small fw-semibold"><?= htmlspecialchars($msg) ?></span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Main Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <!-- Table Toolbar / Filters (Integrated & Sleek) -->
        <div class="p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-3" style="border-color: var(--card-border) !important;">
            <div class="d-flex align-items-center gap-2 flex-grow-1 flex-md-grow-0">
                <div class="position-relative flex-grow-1" style="min-width: 240px;">
                    <i class="bi bi-search position-absolute top-50 start-3 translate-middle-y text-muted" style="left: 12px; transform: translateY(-50%); pointer-events: none;"></i>
                    <input type="text" id="filterRekening" class="form-control ps-5" placeholder="Cari nomor rekening..." style="font-size: 0.85rem; height: 38px;">
                </div>
                <select id="filterCabang" class="form-select" style="width: 200px; font-size: 0.85rem; height: 38px;">
                    <option value="">Semua Cabang</option>
                    <?php foreach ($branches as $branch): 
                        $code = str_pad($branch['id'], 2, '0', STR_PAD_LEFT);
                    ?>
                        <option value="<?= $code ?>"><?= htmlspecialchars($branch['nama_cabang']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button id="btnClearFilters" class="btn btn-secondary p-2" title="Reset Filter" style="height: 38px; width: 38px;">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4" style="width: 50px;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="checkAll">
                                </div>
                            </th>
                            <th>Nomor Rekening</th>
                            <th>Nama Barang</th>
                            <th>Tanggal Perolehan</th>
                            <th>Nomor Asset (Gabungan)</th>
                            <th>Kode QR / Barcode</th>
                            <th class="text-end pe-4" style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <div class="bg-light bg-opacity-10 text-secondary rounded-circle d-inline-flex p-3 mb-3">
                                        <i class="bi bi-card-heading fs-3"></i>
                                    </div>
                                    <p class="small fw-semibold mb-0">Belum ada data kartu inventaris.</p>
                                    <small class="text-muted">Klik tombol "Tambah Data Kartu" untuk memulai.</small>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): 
                                // Format gabungan Nomor Asset (alphanumeric saja dari Rekening + Tanggal)
                                $cleanRek = preg_replace('/[^a-zA-Z0-9]/', '', $item['nomor_rekening']);
                                $cleanTgl = '';
                                if ($item['tanggal_perolehan']) {
                                    $cleanTgl = date('dmY', strtotime($item['tanggal_perolehan']));
                                }
                                $combinedAssetNum = $cleanRek . $cleanTgl;
                                $branchCode = substr($item['nomor_rekening'], 0, 2);
                                $branchName = isset($branchMap[$branchCode]) ? $branchMap[$branchCode] : '';
                            ?>
                            <tr class="card-row-item" data-rekening="<?= htmlspecialchars($item['nomor_rekening']) ?>" data-branch-code="<?= $branchCode ?>">
                                <td class="ps-4">
                                    <div class="form-check">
                                        <input class="form-check-input item-checkbox" type="checkbox" value="<?= $item['id'] ?>" 
                                               data-rekening="<?= htmlspecialchars($item['nomor_rekening']) ?>"
                                               data-nama="<?= htmlspecialchars($item['nama_barang']) ?>"
                                               data-tanggal="<?= date('d/m/Y', strtotime($item['tanggal_perolehan'])) ?>"
                                               data-assetnum="<?= htmlspecialchars($combinedAssetNum) ?>"
                                               data-barcode="<?= htmlspecialchars($item['barcode_data']) ?>"
                                               data-cabang="<?= htmlspecialchars($branchName) ?>">
                                    </div>
                                </td>
                                <td><strong><?= htmlspecialchars($item['nomor_rekening']) ?></strong></td>
                                <td><?= htmlspecialchars($item['nama_barang']) ?></td>
                                <td>
                                    <div class="small text-muted">
                                        <i class="bi bi-calendar3 me-1 text-primary"></i>
                                        <?= format_tanggal_indonesia($item['tanggal_perolehan']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.76rem;">
                                        <?= htmlspecialchars($combinedAssetNum) ?>
                                    </span>
                                </td>
                                <td><code class="text-secondary"><?= htmlspecialchars($item['barcode_data']) ?></code></td>
                                <td class="text-end pe-4">
                                    <button class="btn-action-edit btn-edit me-1" 
                                            data-id="<?= $item['id'] ?>"
                                            data-rekening="<?= htmlspecialchars($item['nomor_rekening']) ?>"
                                            data-nama="<?= htmlspecialchars($item['nama_barang']) ?>"
                                            data-tanggal="<?= htmlspecialchars($item['tanggal_perolehan']) ?>"
                                            data-barcode="<?= htmlspecialchars($item['barcode_data']) ?>"
                                            title="Edit">
                                        <i class="bi bi-pencil-square fs-6"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data kartu ini?')">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" name="hapus" class="btn-action-delete" title="Hapus">
                                            <i class="bi bi-trash fs-6"></i>
                                        </button>
                                    </form>
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

<!-- Modal Import dari Aset IT -->
<div class="modal fade" id="modalImportAsset" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-800 m-0"><i class="bi bi-box-seam-fill text-primary me-2"></i>Impor dari Aset IT</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">Pilih aset dari database utama untuk ditambahkan secara otomatis ke daftar kartu inventaris.</p>
                    
                    <div class="position-relative mb-3">
                        <i class="bi bi-search position-absolute top-50 start-3 translate-middle-y text-muted" style="left: 12px; transform: translateY(-50%); pointer-events: none;"></i>
                        <input type="text" id="importSearchInput" class="form-control ps-5" placeholder="Cari Kode Aset, Nama, Merk, Model, Cabang..." style="font-size: 0.85rem; height: 38px;">
                    </div>

                    <div class="border rounded-4 overflow-hidden" style="max-height: 350px; overflow-y: auto !important; border-color: var(--card-border) !important;">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.84rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 50px;">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="importCheckAll">
                                        </div>
                                    </th>
                                    <th>Kode Aset</th>
                                    <th>Nama Aset</th>
                                    <th>Cabang - Divisi</th>
                                    <th>Kondisi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($assetsList)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted small">Tidak ada data aset IT tersedia.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($assetsList as $asset): ?>
                                        <tr class="import-asset-row" data-search="<?= htmlspecialchars(strtolower($asset['kode_aset'] . ' ' . $asset['nama_aset'] . ' ' . ($asset['nama_cabang'] ?? '') . ' ' . ($asset['nama_kategori'] ?? ''))) ?>">
                                            <td class="ps-3">
                                                <div class="form-check">
                                                    <input class="form-check-input import-item-checkbox" type="checkbox" name="asset_ids[]" value="<?= $asset['id'] ?>">
                                                </div>
                                            </td>
                                            <td><strong><?= htmlspecialchars($asset['kode_aset']) ?></strong></td>
                                            <td><?= htmlspecialchars($asset['nama_aset']) ?></td>
                                            <td><small class="text-muted"><?= htmlspecialchars(($asset['nama_cabang'] ?: '-') . ' - ' . ($asset['nama_divisi'] ?: '-')) ?></small></td>
                                            <td>
                                                <?php 
                                                $condColor = 'success';
                                                if ($asset['kondisi'] === 'Rusak Ringan') $condColor = 'warning';
                                                if ($asset['kondisi'] === 'Rusak Berat') $condColor = 'danger';
                                                ?>
                                                <span class="badge bg-<?= $condColor ?> bg-opacity-10 text-<?= $condColor ?> rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.65rem;">
                                                    <?= $asset['kondisi'] ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="import_assets" class="btn btn-primary px-4" id="btnSubmitImport" disabled>
                        Impor Terpilih (<span id="importSelectedCount">0</span>)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-800 m-0"><i class="bi bi-plus-circle-fill text-primary me-2"></i>Tambah Kartu Inventaris</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Nomor Rekening</label>
                        <input type="text" name="nomor_rekening" id="nomor_rekening" class="form-control" placeholder="Contoh: 01.5.00003" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Nama Barang</label>
                        <input type="text" name="nama_barang" class="form-control" placeholder="Contoh: BANGUNAN GEDUNG KANTOR PUSA" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Tanggal Perolehan</label>
                        <input type="date" name="tanggal_perolehan" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Kode QR / Barcode (Data QR Code)</label>
                        <input type="text" name="barcode_data" class="form-control" placeholder="Salin/tempel kode QR di sini" required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary px-4">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-800 m-0"><i class="bi bi-pencil-square text-warning me-2"></i>Perbarui Kartu Inventaris</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Nomor Rekening</label>
                        <input type="text" name="nomor_rekening" id="edit_rekening" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Nama Barang</label>
                        <input type="text" name="nama_barang" id="edit_nama" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Tanggal Perolehan</label>
                        <input type="date" name="tanggal_perolehan" id="edit_tanggal" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Kode QR / Barcode (Data QR Code)</label>
                        <input type="text" name="barcode_data" id="edit_barcode" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="update" class="btn btn-warning text-dark fw-bold px-4">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Cetak Setup -->
<div class="modal fade" id="modalCetak" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 p-4 pb-0 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-800 m-0"><i class="bi bi-printer-fill text-primary me-2"></i>Konfigurasi & Cetak Kartu A4 (CR80)</h5>
                    <p class="text-muted small m-0">Sesuaikan lokasi aset, layout cetak, dan tinjau pratinjau kartu secara real-time.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Nav Tabs for Configuration vs Live Preview -->
                <ul class="nav nav-pills mb-4 gap-2 border-bottom pb-3" id="printModalTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active px-4 py-2 rounded-3 fw-bold small" id="tab-config-tab" data-bs-toggle="pill" data-bs-target="#tab-config" type="button" role="tab">
                            <i class="bi bi-sliders me-2"></i> 1. Pengaturan Lokasi & Layout
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link px-4 py-2 rounded-3 fw-bold small" id="tab-preview-tab" data-bs-toggle="pill" data-bs-target="#tab-preview" type="button" role="tab">
                            <i class="bi bi-eye me-2"></i> 2. Pratinjau Kartu Fisik (Live Preview)
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="printModalTabContent">
                    <!-- Tab 1: Config -->
                    <div class="tab-pane fade show active" id="tab-config" role="tabpanel">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Layout Grid Kertas A4</label>
                                <select id="printLayout" class="form-select">
                                    <option value="8">8 Kartu per Lembar (2x4 - Portrait)</option>
                                    <option value="10">10 Kartu per Lembar (2x5 - Portrait)</option>
                                    <option value="12">12 Kartu per Lembar (3x4 - Landscape)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Teks Perhatian (Bagian Bawah Kartu)</label>
                                <textarea id="printAttention" class="form-control" rows="2">Perhatian
Dilarang memindahkan barang inventaris ini tanpa seizin Human Resource Departement (HRD) Bank Mitra</textarea>
                            </div>
                        </div>

                        <!-- Tips Alert -->
                        <div class="alert alert-info border-0 rounded-4 p-3 mb-4 d-flex align-items-start gap-2">
                            <i class="bi bi-info-circle-fill fs-5 mt-0.5"></i>
                            <div class="small">
                                <strong>Tips Menyimpan PDF:</strong> Untuk menyimpan hasil cetak sebagai file PDF, pilih opsi <b>"Simpan sebagai PDF" / "Save as PDF"</b> pada pilihan <b>Tujuan / Destination</b> di jendela cetak browser Anda.
                            </div>
                        </div>

                        <h6 class="fw-bold mb-3"><i class="bi bi-geo-alt-fill text-danger me-2"></i>Isi Lokasi Aset Secara Manual</h6>
                        <div class="border rounded-4 overflow-hidden mb-2" style="max-height: 220px; overflow-y: auto !important; border-color: var(--card-border) !important;">
                            <table class="table table-sm align-middle mb-0" style="font-size: 0.84rem;">
                                <thead>
                                    <tr>
                                        <th class="ps-3">Nomor Rekening</th>
                                        <th>Nama Barang</th>
                                        <th class="pe-3" style="width: 320px;">Lokasi Aset (Ketik Manual)</th>
                                    </tr>
                                </thead>
                                <tbody id="printLocationsList">
                                    <!-- Populated by JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab 2: Live Preview -->
                    <div class="tab-pane fade" id="tab-preview" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="small text-muted fw-semibold" id="previewCardInfo">Menampilkan pratinjau kartu 1 dari 1</span>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary" id="btnPrevCard"><i class="bi bi-chevron-left"></i> Sblm</button>
                                <button type="button" class="btn btn-outline-secondary" id="btnNextCard">Slanjut <i class="bi bi-chevron-right"></i></button>
                            </div>
                        </div>
                        <div class="d-flex justify-content-center align-items-center p-4 bg-light bg-opacity-50 rounded-4 border overflow-hidden" style="min-height: 300px;">
                            <div id="liveCardPreviewContainer" class="position-relative shadow-lg rounded-3 overflow-hidden bg-white" style="width: 85.6mm; min-height: 54.0mm; transform: scale(1.35); transform-origin: center center; margin: 30px 0;">
                                <!-- Rendered dynamically by JS -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 gap-2">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Batal</button>
                <button type="button" id="btnEksporWord" class="btn btn-success px-4">
                    <i class="bi bi-file-earmark-word me-2"></i> Ekspor Word (.doc)
                </button>
                <button type="button" id="btnProsesCetak" class="btn btn-primary px-4">
                    <i class="bi bi-printer me-2"></i> Cetak Sekarang
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* CSS khusus cetak kartu inventaris A4 */
#print-container {
    display: none;
}

/* Web Action Buttons styling */
.btn-action-edit {
    background: rgba(245, 158, 11, 0.08) !important;
    border: 1px solid rgba(245, 158, 11, 0.15) !important;
    color: #f59e0b !important;
    padding: 6px 10px !important;
    border-radius: 8px !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}
.btn-action-edit:hover {
    background: #f59e0b !important;
    color: #ffffff !important;
    transform: translateY(-1px);
}
.btn-action-delete {
    background: rgba(239, 68, 68, 0.08) !important;
    border: 1px solid rgba(239, 68, 68, 0.15) !important;
    color: #ef4444 !important;
    padding: 6px 10px !important;
    border-radius: 8px !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}
.btn-action-delete:hover {
    background: #ef4444 !important;
    color: #ffffff !important;
    transform: translateY(-1px);
}

@media print {
    /* Sembunyikan seluruh UI web */
    body * {
        visibility: hidden;
    }
    #print-container, #print-container * {
        visibility: visible;
    }
    #print-container {
        display: block !important;
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 0;
        background: #fff;
    }

    /* Grid A4 Layout */
    .print-page {
        page-break-after: always !important;
        break-after: page !important;
        box-sizing: border-box !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        display: grid !important;
        justify-content: center !important;
        align-content: start !important;
        overflow: hidden !important;
    }
    
    /* Layout 8 Kartu (Portrait) */
    .print-page.layout-8 {
        grid-template-columns: repeat(2, 85.6mm) !important;
        grid-gap: 10mm 14mm !important;
        padding-top: 12mm !important;
        height: 297mm !important;
        max-height: 297mm !important;
    }
    
    /* Layout 10 Kartu (Portrait) */
    .print-page.layout-10 {
        grid-template-columns: repeat(2, 85.6mm) !important;
        grid-gap: 4mm 14mm !important;
        padding-top: 6mm !important;
        height: 297mm !important;
        max-height: 297mm !important;
    }

    /* Layout 12 Kartu (Landscape) */
    .print-page.layout-12 {
        grid-template-columns: repeat(3, 85.6mm) !important;
        grid-gap: 4mm 6mm !important;
        transform: scale(0.92) !important;
        transform-origin: center top !important;
        padding-top: 8mm !important;
        height: 210mm !important;
        max-height: 210mm !important;
    }

    /* Ukuran ATM Card (CR80) */
    .atm-card {
        width: 85.6mm !important;
        height: 54.0mm !important;
        box-sizing: border-box !important;
        border: 1.2px solid #e2e8f0 !important;
        border-radius: 8px !important;
        background: #ffffff !important;
        font-family: 'Plus Jakarta Sans', Arial, sans-serif !important;
        color: #0f172a !important;
        overflow: hidden !important;
        position: relative !important;
        display: flex !important;
        flex-direction: column !important;
        padding: 0 !important;
        margin: 0 !important;
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.04) !important;
        /* Garis bantu gunting / crop marks */
        outline: 0.8px dashed #cbd5e1 !important;
        outline-offset: 1.5mm !important;
    }

    /* Top Header Section */
    .card-header-sec {
        display: flex !important;
        width: 100% !important;
        height: 11.5mm !important;
        border-bottom: 1.5px solid #003b73 !important;
        box-sizing: border-box !important;
    }
    .header-logo-box {
        width: 27% !important;
        height: 100% !important;
        border-right: 1px solid #e2e8f0 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: #ffffff !important;
        box-sizing: border-box !important;
        padding: 0.8mm !important;
    }
    .header-logo-box img {
        max-height: 9.8mm !important;
        max-width: 95% !important;
        object-fit: contain !important;
    }
    .header-title-box {
        width: 73% !important;
        height: 100% !important;
        display: flex !important;
        flex-direction: column !important;
        box-sizing: border-box !important;
    }
    .header-main-title {
        background-color: #003b73 !important;
        color: #ffffff !important;
        font-weight: 800 !important;
        font-size: 7.8pt !important;
        text-align: center !important;
        height: 5.5mm !important;
        line-height: 5.5mm !important;
        text-transform: uppercase !important;
        letter-spacing: 0.2px !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .header-sub-sec {
        height: 6.0mm !important;
        background: #ffffff !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        padding: 0 !important;
        box-sizing: border-box !important;
        position: relative !important;
    }
    
    /* Green Banner with Teal Accent and Slant */
    .green-banner-wrapper {
        position: relative !important;
        display: flex !important;
        height: 100% !important;
        width: 75% !important;
    }
    .teal-stripe {
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        height: 100% !important;
        background-color: #009ca6 !important;
        clip-path: polygon(0 0, 78% 0, 71% 100%, 0 100%) !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .green-banner {
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 92% !important;
        height: 100% !important;
        background: #7ac142 !important;
        clip-path: polygon(0 0, 78% 0, 71% 100%, 0 100%) !important;
        color: #ffffff !important;
        font-weight: 800 !important;
        font-size: 7.2pt !important;
        letter-spacing: 0.5px !important;
        display: flex !important;
        align-items: center !important;
        padding-left: 2.5mm !important;
        box-sizing: border-box !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    .header-dots-container {
        display: flex !important;
        align-items: center !important;
        padding-right: 3mm !important;
        height: 100% !important;
    }
    .header-dots {
        display: grid !important;
        grid-template-columns: repeat(3, 1mm) !important;
        grid-gap: 0.8mm !important;
    }
    .header-dots span {
        width: 0.9mm !important;
        height: 0.9mm !important;
        background-color: #7ac142 !important;
        border-radius: 50% !important;
        display: block !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Fields Section */
    .card-fields-sec {
        display: flex !important;
        flex-direction: column !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        box-sizing: border-box !important;
    }
    .card-field-row {
        display: flex !important;
        width: 100% !important;
        height: 6.125mm !important;
        border-bottom: 1px solid #e2e8f0 !important;
        box-sizing: border-box !important;
    }
    .field-icon-box {
        width: 8.5mm !important;
        height: 100% !important;
        background-color: #003b73 !important;
        color: #ffffff !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-sizing: border-box !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .field-svg-icon {
        width: 3.5mm !important;
        height: 3.5mm !important;
        color: #ffffff !important;
    }
    .field-content-box {
        display: flex !important;
        align-items: center !important;
        width: 77.1mm !important;
        height: 100% !important;
        background: #ffffff !important;
        box-sizing: border-box !important;
    }
    .field-lbl {
        width: 25.5mm !important;
        color: #003b73 !important;
        font-weight: 800 !important;
        font-size: 7.0pt !important;
        display: flex !important;
        align-items: center !important;
        padding-left: 3.0mm !important;
        box-sizing: border-box !important;
        text-transform: uppercase !important;
        letter-spacing: 0.2px !important;
    }
    .field-sep {
        color: #7ac142 !important;
        font-weight: 800 !important;
        font-size: 9.0pt !important;
        margin: 0 1.0mm 0 2.0mm !important;
        display: flex !important;
        align-items: center !important;
    }
    .field-val {
        font-size: 7.0pt !important;
        color: #1e293b !important;
        font-weight: 800 !important;
        padding-left: 2.0mm !important;
        white-space: normal !important;
        line-height: 1.05 !important;
        overflow: hidden !important;
        display: -webkit-box !important;
        -webkit-line-clamp: 2 !important;
        -webkit-box-orient: vertical !important;
        text-transform: uppercase !important;
        flex-grow: 1;
        align-self: center !important;
        padding-top: 0.1mm !important;
        padding-bottom: 0.1mm !important;
        word-break: break-word !important;
    }

    /* Bottom Section */
    .card-bottom-sec {
        display: flex !important;
        width: 100% !important;
        height: 18.0mm !important;
        border-top: 1.5px solid #7ac142 !important;
        background-color: #ffffff !important;
        box-sizing: border-box !important;
        position: relative !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    /* Decorative Waves */
    .card-waves {
        position: absolute !important;
        bottom: 0 !important;
        left: 0 !important;
        width: 85.6mm !important;
        height: 7.5mm !important;
        z-index: 1 !important;
        pointer-events: none !important;
    }
    
    .bottom-left-attention {
        width: 66% !important;
        height: 100% !important;
        display: flex !important;
        align-items: center !important;
        padding: 1.0mm 1.5mm 1.0mm 3.0mm !important;
        box-sizing: border-box !important;
        z-index: 2 !important;
    }
    .attention-icon {
        margin-right: 2.0mm !important;
        display: flex !important;
        align-items: center !important;
    }
    .attention-svg-icon {
        width: 7.0mm !important;
        height: 7.0mm !important;
        color: #003b73 !important;
    }
    .attention-text-box {
        display: flex !important;
        flex-direction: column !important;
    }
    .attention-title {
        font-weight: 800 !important;
        font-size: 6.8pt !important;
        color: #dc2626 !important;
        margin-bottom: 0.3mm !important;
        text-transform: uppercase !important;
        letter-spacing: 0.3px !important;
    }
    .attention-desc {
        font-size: 4.6pt !important;
        line-height: 1.25 !important;
        color: #475569 !important;
        font-weight: 600 !important;
    }
    
    .attention-qr-separator {
        width: 1px !important;
        height: 13.0mm !important;
        background-color: #e2e8f0 !important;
        align-self: center !important;
        z-index: 2 !important;
    }
    
    .bottom-right-qr {
        width: 34% !important;
        height: 100% !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        box-sizing: border-box !important;
        padding: 0.8mm 1.0mm !important;
        z-index: 2 !important;
    }
    .qr-border-box {
        border: 1px solid #e2e8f0 !important;
        border-radius: 5px !important;
        padding: 0.5mm !important;
        background: #ffffff !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02) !important;
        margin-bottom: 0.6mm !important;
    }
    .card-qr-img {
        width: 10.5mm !important;
        height: 10.5mm !important;
    }
    .card-qr-img canvas, .card-qr-img img {
        width: 10.5mm !important;
        height: 10.5mm !important;
        margin: 0 auto !important;
    }
    
    /* Green Scan Info Capsule */
    .scan-info-capsule {
        background-color: #008744 !important;
        color: #ffffff !important;
        border-radius: 12px !important;
        padding: 0.4mm 1.6mm !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 0.5mm !important;
        height: 2.8mm !important;
        width: 19.0mm !important;
        box-sizing: border-box !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .scan-icon {
        width: 1.8mm !important;
        height: 1.8mm !important;
        color: #ffffff !important;
    }
    .scan-info-capsule span {
        font-size: 3.5pt !important;
        font-weight: 800 !important;
        white-space: nowrap !important;
        letter-spacing: 0.1px !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Input Masking for Nomor Rekening (Format: XX.X.XXXXX)
    function applyRekeningMask(input) {
        let val = input.value.replace(/\D/g, ''); // Remove non-digits
        if (val.length > 8) {
            val = val.substring(0, 8);
        }
        let formatted = '';
        if (val.length > 0) {
            formatted += val.substring(0, 2);
        }
        if (val.length > 2) {
            formatted += '.' + val.substring(2, 3);
        }
        if (val.length > 3) {
            formatted += '.' + val.substring(3, 8);
        }
        input.value = formatted;
    }

    const inputRekening = document.getElementById('nomor_rekening');
    const inputEditRekening = document.getElementById('edit_rekening');

    if (inputRekening) {
        inputRekening.addEventListener('input', function() {
            applyRekeningMask(this);
        });
    }
    if (inputEditRekening) {
        inputEditRekening.addEventListener('input', function() {
            applyRekeningMask(this);
        });
    }

    const checkAll = document.getElementById('checkAll');
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const btnCetakMassal = document.getElementById('btnCetakMassal');
    const btnHapusMassal = document.getElementById('btnHapusMassal');
    const selectedCount = document.getElementById('selectedCount');
    const deleteSelectedCount = document.getElementById('deleteSelectedCount');
    const printLocationsList = document.getElementById('printLocationsList');
    const btnProsesCetak = document.getElementById('btnProsesCetak');
    const printLayout = document.getElementById('printLayout');
    const printAttention = document.getElementById('printAttention');

    // Helper: Async QR Code Generator (Base64)
    function generateQrBase64(text) {
        return new Promise((resolve) => {
            const tempDiv = document.createElement('div');
            tempDiv.style.position = 'absolute';
            tempDiv.style.left = '-9999px';
            tempDiv.style.top = '-9999px';
            document.body.appendChild(tempDiv);
            
            new QRCode(tempDiv, {
                text: text || 'REKAPIT',
                width: 120,
                height: 120,
                correctLevel: QRCode.CorrectLevel.M
            });

            setTimeout(() => {
                let imgData = '';
                const canvas = tempDiv.querySelector('canvas');
                if (canvas) {
                    imgData = canvas.toDataURL('image/png');
                } else {
                    const img = tempDiv.querySelector('img');
                    if (img && img.src) imgData = img.src;
                }
                document.body.removeChild(tempDiv);
                resolve(imgData);
            }, 80);
        });
    }

    // Handle Edit Button Click
    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const rekening = this.getAttribute('data-rekening');
            const nama = this.getAttribute('data-nama');
            const tanggal = this.getAttribute('data-tanggal');
            const barcode = this.getAttribute('data-barcode');

            document.getElementById('edit_id').value = id;
            document.getElementById('edit_rekening').value = rekening;
            document.getElementById('edit_nama').value = nama;
            document.getElementById('edit_tanggal').value = tanggal;
            document.getElementById('edit_barcode').value = barcode;

            new bootstrap.Modal(document.getElementById('modalEdit')).show();
        });
    });

    // Helper for visible checkboxes
    function getVisibleCheckboxes() {
        return Array.from(document.querySelectorAll('.item-checkbox')).filter(cb => {
            const row = cb.closest('tr');
            return row && row.style.display !== 'none';
        });
    }

    // Checkbox logic for selection
    function updateSelectionState() {
        const visibleCbs = getVisibleCheckboxes();
        const checked = visibleCbs.filter(cb => cb.checked);
        const count = checked.length;

        if (selectedCount) selectedCount.innerText = count;
        if (deleteSelectedCount) deleteSelectedCount.innerText = count;

        if (count > 0) {
            if (btnCetakMassal) {
                btnCetakMassal.disabled = false;
                btnCetakMassal.classList.remove('btn-outline-primary');
                btnCetakMassal.classList.add('btn-primary');
            }
            if (btnHapusMassal) {
                btnHapusMassal.disabled = false;
                btnHapusMassal.classList.remove('btn-outline-danger');
                btnHapusMassal.classList.add('btn-danger');
            }
        } else {
            if (btnCetakMassal) {
                btnCetakMassal.disabled = true;
                btnCetakMassal.classList.add('btn-outline-primary');
                btnCetakMassal.classList.remove('btn-primary');
            }
            if (btnHapusMassal) {
                btnHapusMassal.disabled = true;
                btnHapusMassal.classList.add('btn-outline-danger');
                btnHapusMassal.classList.remove('btn-danger');
            }
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function() {
            const visibleCbs = getVisibleCheckboxes();
            visibleCbs.forEach(cb => cb.checked = checkAll.checked);
            updateSelectionState();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const visibleCbs = getVisibleCheckboxes();
            const checkedVisible = visibleCbs.filter(c => c.checked);
            if (checkAll) {
                checkAll.checked = (visibleCbs.length > 0 && checkedVisible.length === visibleCbs.length);
            }
            updateSelectionState();
        });
    });

    // Handle Bulk Delete
    if (btnHapusMassal) {
        btnHapusMassal.addEventListener('click', function() {
            const visibleCbs = getVisibleCheckboxes();
            const checked = visibleCbs.filter(c => c.checked);
            if (checked.length === 0) return;

            if (confirm(`Apakah Anda yakin ingin menghapus ${checked.length} data kartu inventaris terpilih secara massal? Action ini tidak dapat dibatalkan.`)) {
                const container = document.getElementById('hapusMassalInputs');
                container.innerHTML = '';
                checked.forEach(cb => {
                    const inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'ids[]';
                    inp.value = cb.value;
                    container.appendChild(inp);
                });
                document.getElementById('formHapusMassal').submit();
            }
        });
    }

    // Live Card Preview Logic
    let currentPreviewIndex = 0;
    let previewItemsList = [];

    async function renderLiveCardPreview(index) {
        const container = document.getElementById('liveCardPreviewContainer');
        const infoSpan = document.getElementById('previewCardInfo');
        if (!container || previewItemsList.length === 0) return;

        if (index < 0) index = 0;
        if (index >= previewItemsList.length) index = previewItemsList.length - 1;
        currentPreviewIndex = index;

        if (infoSpan) {
            infoSpan.innerText = `Menampilkan pratinjau kartu ${currentPreviewIndex + 1} dari ${previewItemsList.length}`;
        }

        const item = previewItemsList[currentPreviewIndex];
        const locationInput = document.querySelector(`.print-location-input[data-id="${item.id}"]`);
        const manualLoc = locationInput ? (locationInput.value.trim() || '-') : (item.cabang || '-');
        const attentionText = printAttention ? printAttention.value.trim() : '';

        const currentPath = window.location.pathname;
        const baseDir = currentPath.substring(0, currentPath.lastIndexOf('/'));
        const logoUrl = (baseDir ? baseDir : '') + '/assets/LOGO TYPE 2.png';

        const qrBase64 = await generateQrBase64(item.barcode);

        container.innerHTML = `
            <div class="atm-card" style="width:100%; height:100%; display:flex; flex-direction:column;">
                <!-- Top Header -->
                <div class="card-header-sec">
                    <div class="header-logo-box">
                        <img src="${logoUrl}" alt="Logo">
                    </div>
                    <div class="header-title-box">
                        <div class="header-main-title">PT BPR MITRATAMA ARTHABUANA</div>
                        <div class="header-sub-sec">
                            <div class="green-banner-wrapper">
                                <div class="teal-stripe"></div>
                                <div class="green-banner">ASSET TETAP</div>
                            </div>
                            <div class="header-dots-container">
                                <div class="header-dots">
                                    <span></span><span></span><span></span>
                                    <span></span><span></span><span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Data Fields Section -->
                <div class="card-fields-sec">
                    <!-- Nomor Asset -->
                    <div class="card-field-row">
                        <div class="field-icon-box">
                            <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M2 2a1 1 0 0 1 1-1h4.586a1 1 0 0 1 .707.293l7 7a1 1 0 0 1 0 1.414l-4.586 4.586a1 1 0 0 1-1.414 0l-7-7A1 1 0 0 1 2 8.586V2zm3.5 3.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/></svg>
                        </div>
                        <div class="field-content-box">
                            <span class="field-lbl">NOMOR ASSET</span>
                            <span class="field-sep">|</span>
                            <span class="field-val">${item.assetnum}</span>
                        </div>
                    </div>
                    <!-- Nama Asset -->
                    <div class="card-field-row">
                        <div class="field-icon-box">
                            <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M12 1H4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zM4 2h8a1 1 0 0 1 1 1v7H3V3a1 1 0 0 1 1-1z"/><path d="M8 12a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/></svg>
                        </div>
                        <div class="field-content-box">
                            <span class="field-lbl">NAMA ASSET</span>
                            <span class="field-sep">|</span>
                            <span class="field-val">${item.nama}</span>
                        </div>
                    </div>
                    <!-- Tgl Perolehan -->
                    <div class="card-field-row">
                        <div class="field-icon-box">
                            <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/></svg>
                        </div>
                        <div class="field-content-box">
                            <span class="field-lbl">TGL PEROLEHAN</span>
                            <span class="field-sep">|</span>
                            <span class="field-val">${item.tanggal}</span>
                        </div>
                    </div>
                    <!-- Lokasi -->
                    <div class="card-field-row">
                        <div class="field-icon-box">
                            <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/></svg>
                        </div>
                        <div class="field-content-box">
                            <span class="field-lbl">LOKASI</span>
                            <span class="field-sep">|</span>
                            <span class="field-val">${manualLoc}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Bottom Section -->
                <div class="card-bottom-sec">
                    <div class="card-waves">
                        <svg viewBox="0 0 85.6 7.5" preserveAspectRatio="none" style="width: 100%; height: 100%; display: block;">
                            <path d="M 0 3 C 8 2.5, 18 5, 24 7.5 L 0 7.5 Z" fill="#7ac142" />
                            <path d="M 5 7.5 Q 32 3, 58 6.5 T 85.6 3.5 L 85.6 7.5 Z" fill="#003b73" />
                        </svg>
                    </div>
                    
                    <div class="bottom-left-attention">
                        <div class="attention-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#003b73" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="attention-svg-icon">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                <line x1="12" y1="8" x2="12" y2="13" />
                                <line x1="12" y1="16.5" x2="12.01" y2="16.5" stroke-width="3" />
                            </svg>
                        </div>
                        <div class="attention-text-box">
                            <div class="attention-title">Perhatian</div>
                            <div class="attention-desc">${attentionText}</div>
                        </div>
                    </div>
                    
                    <div class="attention-qr-separator"></div>
                    
                    <div class="bottom-right-qr">
                        <div class="qr-border-box">
                            <img src="${qrBase64}" class="card-qr-img" alt="QR Code">
                        </div>
                        <div class="scan-info-capsule">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="scan-icon">
                                <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                                <line x1="12" y1="18" x2="12.01" y2="18"/>
                            </svg>
                            <span>SCAN UNTUK INFO</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    const btnPrevCard = document.getElementById('btnPrevCard');
    const btnNextCard = document.getElementById('btnNextCard');
    if (btnPrevCard) {
        btnPrevCard.addEventListener('click', () => renderLiveCardPreview(currentPreviewIndex - 1));
    }
    if (btnNextCard) {
        btnNextCard.addEventListener('click', () => renderLiveCardPreview(currentPreviewIndex + 1));
    }

    // When clicking Cetak Kartu Pilihan
    btnCetakMassal.addEventListener('click', function() {
        printLocationsList.innerHTML = '';
        
        const visibleCbs = getVisibleCheckboxes();
        const checked = visibleCbs.filter(c => c.checked);

        previewItemsList = checked.map(cb => ({
            id: cb.value,
            rekening: cb.getAttribute('data-rekening'),
            nama: cb.getAttribute('data-nama'),
            tanggal: cb.getAttribute('data-tanggal'),
            assetnum: cb.getAttribute('data-assetnum'),
            barcode: cb.getAttribute('data-barcode'),
            cabang: cb.getAttribute('data-cabang') || ''
        }));

        previewItemsList.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="ps-3"><strong>${item.rekening}</strong></td>
                <td>${item.nama}</td>
                <td class="pe-3 py-2">
                    <input type="text" class="form-control form-control-sm print-location-input" 
                           data-id="${item.id}" 
                           value="${item.cabang}"
                           placeholder="Contoh: KC.BTL/Lt-2/Ruang-AO">
                </td>
            `;
            printLocationsList.appendChild(tr);
        });

        // Add event listener to update preview when location input changes
        printLocationsList.querySelectorAll('.print-location-input').forEach(input => {
            input.addEventListener('input', () => {
                renderLiveCardPreview(currentPreviewIndex);
            });
        });

        currentPreviewIndex = 0;
        renderLiveCardPreview(0);

        new bootstrap.Modal(document.getElementById('modalCetak')).show();
    });

    if (printAttention) {
        printAttention.addEventListener('input', () => {
            renderLiveCardPreview(currentPreviewIndex);
        });
    }

    // Handle Printing
    btnProsesCetak.addEventListener('click', async function() {
        const visibleCbs = getVisibleCheckboxes();
        const checked = visibleCbs.filter(c => c.checked);
        if (checked.length === 0) return;

        const origText = btnProsesCetak.innerHTML;
        btnProsesCetak.disabled = true;
        btnProsesCetak.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Menyiapkan Cetak...';

        // Get manual locations
        const locationsMap = {};
        document.querySelectorAll('.print-location-input').forEach(input => {
            const id = input.getAttribute('data-id');
            locationsMap[id] = input.value.trim() || '-';
        });

        const limitPerPage = parseInt(printLayout.value);
        const attentionText = printAttention.value.trim();
        const orientationClass = (limitPerPage === 12) ? 'layout-12' : (limitPerPage === 10 ? 'layout-10' : 'layout-8');
        const pageOrientationRule = (limitPerPage === 12) ? 'landscape' : 'portrait';

        // Prepare print container
        let oldPrintContainer = document.getElementById('print-container');
        if (oldPrintContainer) {
            oldPrintContainer.remove();
        }

        const printContainer = document.createElement('div');
        printContainer.id = 'print-container';
        document.body.appendChild(printContainer);

        const currentPath = window.location.pathname;
        const baseDir = currentPath.substring(0, currentPath.lastIndexOf('/'));
        const logoUrl = (baseDir ? baseDir : '') + '/assets/LOGO TYPE 2.png';

        // Pre-generate items with Base64 QR code
        const selectedItems = [];
        for (const cb of checked) {
            const barcodeData = cb.getAttribute('data-barcode');
            const qrBase64 = await generateQrBase64(barcodeData);
            selectedItems.push({
                id: cb.value,
                rekening: cb.getAttribute('data-rekening'),
                nama: cb.getAttribute('data-nama'),
                tanggal: cb.getAttribute('data-tanggal'),
                assetnum: cb.getAttribute('data-assetnum'),
                barcode: barcodeData,
                qrBase64: qrBase64
            });
        }

        let pageHtml = '';
        for (let i = 0; i < selectedItems.length; i += limitPerPage) {
            const chunk = selectedItems.slice(i, i + limitPerPage);
            pageHtml += `<div class="print-page ${orientationClass}">`;
            
            chunk.forEach(item => {
                const manualLoc = locationsMap[item.id] || '-';
                
                pageHtml += `
                    <div class="atm-card">
                        <!-- Top Header -->
                        <div class="card-header-sec">
                            <div class="header-logo-box">
                                <img src="${logoUrl}" alt="Logo">
                            </div>
                            <div class="header-title-box">
                                <div class="header-main-title">PT BPR MITRATAMA ARTHABUANA</div>
                                <div class="header-sub-sec">
                                    <div class="green-banner-wrapper">
                                        <div class="teal-stripe"></div>
                                        <div class="green-banner">ASSET TETAP</div>
                                    </div>
                                    <div class="header-dots-container">
                                        <div class="header-dots">
                                            <span></span><span></span><span></span>
                                            <span></span><span></span><span></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Data Fields Section -->
                        <div class="card-fields-sec">
                            <!-- Nomor Asset -->
                            <div class="card-field-row">
                                <div class="field-icon-box">
                                    <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M2 2a1 1 0 0 1 1-1h4.586a1 1 0 0 1 .707.293l7 7a1 1 0 0 1 0 1.414l-4.586 4.586a1 1 0 0 1-1.414 0l-7-7A1 1 0 0 1 2 8.586V2zm3.5 3.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/></svg>
                                </div>
                                <div class="field-content-box">
                                    <span class="field-lbl">NOMOR ASSET</span>
                                    <span class="field-sep">|</span>
                                    <span class="field-val">${item.assetnum}</span>
                                </div>
                            </div>
                            <!-- Nama Asset -->
                            <div class="card-field-row">
                                <div class="field-icon-box">
                                    <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M12 1H4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zM4 2h8a1 1 0 0 1 1 1v7H3V3a1 1 0 0 1 1-1z"/><path d="M8 12a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/></svg>
                                </div>
                                <div class="field-content-box">
                                    <span class="field-lbl">NAMA ASSET</span>
                                    <span class="field-sep">|</span>
                                    <span class="field-val">${item.nama}</span>
                                </div>
                            </div>
                            <!-- Tgl Perolehan -->
                            <div class="card-field-row">
                                <div class="field-icon-box">
                                    <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/></svg>
                                </div>
                                <div class="field-content-box">
                                    <span class="field-lbl">TGL PEROLEHAN</span>
                                    <span class="field-sep">|</span>
                                    <span class="field-val">${item.tanggal}</span>
                                </div>
                            </div>
                            <!-- Lokasi -->
                            <div class="card-field-row">
                                <div class="field-icon-box">
                                    <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/></svg>
                                </div>
                                <div class="field-content-box">
                                    <span class="field-lbl">LOKASI</span>
                                    <span class="field-sep">|</span>
                                    <span class="field-val">${manualLoc}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Bottom Section (Attention & QR) -->
                        <div class="card-bottom-sec">
                            <div class="card-waves">
                                <svg viewBox="0 0 85.6 7.5" preserveAspectRatio="none" style="width: 100%; height: 100%; display: block;">
                                    <path d="M 0 3 C 8 2.5, 18 5, 24 7.5 L 0 7.5 Z" fill="#7ac142" />
                                    <path d="M 5 7.5 Q 32 3, 58 6.5 T 85.6 3.5 L 85.6 7.5 Z" fill="#003b73" />
                                </svg>
                            </div>
                            
                            <div class="bottom-left-attention">
                                <div class="attention-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="#003b73" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="attention-svg-icon">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                        <line x1="12" y1="8" x2="12" y2="13" />
                                        <line x1="12" y1="16.5" x2="12.01" y2="16.5" stroke-width="3" />
                                    </svg>
                                </div>
                                <div class="attention-text-box">
                                    <div class="attention-title">Perhatian</div>
                                    <div class="attention-desc">${attentionText}</div>
                                </div>
                            </div>
                            
                            <div class="attention-qr-separator"></div>
                            
                            <div class="bottom-right-qr">
                                <div class="qr-border-box">
                                    <img src="${item.qrBase64}" class="card-qr-img" alt="QR Code">
                                </div>
                                <div class="scan-info-capsule">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="scan-icon">
                                        <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                                        <line x1="12" y1="18" x2="12.01" y2="18"/>
                                    </svg>
                                    <span>SCAN UNTUK INFO</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            pageHtml += `</div>`;
        }

        printContainer.innerHTML = pageHtml;

        // Add dynamic print style rule for orientation
        let styleSheet = document.getElementById('print-orientation-style');
        if (!styleSheet) {
            styleSheet = document.createElement('style');
            styleSheet.id = 'print-orientation-style';
            document.head.appendChild(styleSheet);
        }
        styleSheet.innerHTML = `@media print { @page { size: ${pageOrientationRule}; margin: 0; } }`;

        btnProsesCetak.disabled = false;
        btnProsesCetak.innerHTML = origText;

        setTimeout(() => {
            window.print();
        }, 150);
    });

    // Handle Ekspor ke MS Word (.doc)
    const btnEksporWord = document.getElementById('btnEksporWord');
    if (btnEksporWord) {
        btnEksporWord.addEventListener('click', async function() {
            const visibleCbs = getVisibleCheckboxes();
            const checked = visibleCbs.filter(c => c.checked);
            if (checked.length === 0) return;

            const originalBtnHtml = btnEksporWord.innerHTML;
            btnEksporWord.disabled = true;
            btnEksporWord.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Mengolah Data...';

            const locationsMap = {};
            document.querySelectorAll('.print-location-input').forEach(input => {
                const id = input.getAttribute('data-id');
                locationsMap[id] = input.value.trim() || '-';
            });

            const limitPerPage = parseInt(printLayout.value);
            const attentionText = printAttention.value.trim();

            const currentPath = window.location.pathname;
            const baseDir = currentPath.substring(0, currentPath.lastIndexOf('/'));
            const logoUrl = (baseDir ? baseDir : '') + '/assets/LOGO TYPE 2.png';

            let logoBase64 = '';
            try {
                logoBase64 = await new Promise((resolve) => {
                    const img = new Image();
                    img.crossOrigin = 'Anonymous';
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
                        canvas.width = img.width;
                        canvas.height = img.height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0);
                        resolve(canvas.toDataURL('image/png'));
                    };
                    img.onerror = () => resolve('');
                    img.src = logoUrl;
                });
            } catch (e) {
                console.error(e);
            }

            const cardDataList = [];
            for (const cb of checked) {
                const id = cb.value;
                const barcodeData = cb.getAttribute('data-barcode');
                const manualLoc = locationsMap[id] || '-';
                const qrBase64 = await generateQrBase64(barcodeData);

                cardDataList.push({
                    id: id,
                    rekening: cb.getAttribute('data-rekening'),
                    nama: cb.getAttribute('data-nama'),
                    tanggal: cb.getAttribute('data-tanggal'),
                    assetnum: cb.getAttribute('data-assetnum'),
                    location: manualLoc,
                    qrBase64: qrBase64
                });
            }

            const cols = (limitPerPage === 12) ? 3 : 2;
            const cardCellWidth = (cols === 3) ? "220" : "320";
            const orientationSetting = (cols === 3) ? "landscape" : "portrait";
            const pageSizeSetting = (cols === 3) ? "29.7cm 21.0cm" : "21.0cm 29.7cm";

            let wordHtml = `
            <html xmlns:o='urn:schemas-microsoft-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
            <head>
                <meta charset='utf-8'>
                <title>Kartu Inventaris - Rekap IT</title>
                <!--[if gte mso 9]>
                <xml>
                <w:WordDocument>
                    <w:View>Print</w:View>
                    <w:Zoom>100</w:Zoom>
                    <w:DoNotOptimizeForBrowser/>
                </w:WordDocument>
                </xml>
                <![endif]-->
                <style>
                    @page Section1 { size: ${pageSizeSetting}; margin: 8mm; mso-page-orientation: ${orientationSetting}; }
                    div.Section1 { page: Section1; }
                    body { font-family: Arial, sans-serif; font-size: 8pt; background: #ffffff; color: #000000; margin: 0; padding: 0; }
                    table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
                    td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
                </style>
            </head>
            <body>
                <div class="Section1">
                <table width="100%" border="0" cellpadding="0" cellspacing="8" style="width:100%;">
            `;

            for (let i = 0; i < cardDataList.length; i += cols) {
                wordHtml += `<tr>`;
                for (let j = 0; j < cols; j++) {
                    const item = cardDataList[i + j];
                    if (item) {
                        wordHtml += `
                            <td width="${cardCellWidth}" valign="top" style="width:${cardCellWidth}px; border:1px solid #cbd5e1; background:#ffffff; vertical-align:top; padding:0;">
                                <!-- Card Header -->
                                <table width="100%" border="0" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; border-bottom:1.5pt solid #003b73;">
                                    <tr>
                                        <td width="75" align="center" valign="middle" style="width:75px; padding:4px; text-align:center; border-right:1px solid #e2e8f0; vertical-align:middle;">
                                            ${logoBase64 ? `<img src="${logoBase64}" width="65" height="26" border="0" style="display:block; margin:0 auto; width:65px; height:26px;">` : '<strong style="color:#003b73; font-size:7pt;">BANK MITRA</strong>'}
                                        </td>
                                        <td valign="middle" style="vertical-align:middle; padding:0;">
                                            <div style="background-color:#003b73; color:#ffffff; font-weight:bold; font-size:7.5pt; text-align:center; padding:3px 2px; text-transform:uppercase; margin:0;">PT BPR MITRATAMA ARTHABUANA</div>
                                            <div style="background-color:#7ac142; color:#ffffff; font-weight:bold; font-size:6.5pt; text-align:left; padding:2px 6px; text-transform:uppercase; margin:0;">ASSET TETAP</div>
                                        </td>
                                    </tr>
                                </table>
                                
                                <!-- Fields Table -->
                                <table width="100%" border="0" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse;">
                                    <tr>
                                        <td width="85" style="width:85px; font-weight:bold; color:#003b73; font-size:7.5pt; padding:3px 6px; border-bottom:1px solid #e2e8f0; background:#f8fafc; text-transform:uppercase;">NOMOR ASSET</td>
                                        <td width="10" align="center" style="width:10px; color:#7ac142; font-weight:bold; font-size:8pt; padding:3px 0; border-bottom:1px solid #e2e8f0;">|</td>
                                        <td style="font-weight:bold; color:#1e293b; font-size:7.5pt; padding:3px 6px; border-bottom:1px solid #e2e8f0;">${item.assetnum}</td>
                                    </tr>
                                    <tr>
                                        <td width="85" style="width:85px; font-weight:bold; color:#003b73; font-size:7.5pt; padding:3px 6px; border-bottom:1px solid #e2e8f0; background:#f8fafc; text-transform:uppercase;">NAMA ASSET</td>
                                        <td width="10" align="center" style="width:10px; color:#7ac142; font-weight:bold; font-size:8pt; padding:3px 0; border-bottom:1px solid #e2e8f0;">|</td>
                                        <td style="font-weight:bold; color:#1e293b; font-size:7.5pt; padding:3px 6px; border-bottom:1px solid #e2e8f0;">${item.nama}</td>
                                    </tr>
                                    <tr>
                                        <td width="85" style="width:85px; font-weight:bold; color:#003b73; font-size:7.5pt; padding:3px 6px; border-bottom:1px solid #e2e8f0; background:#f8fafc; text-transform:uppercase;">TGL PEROLEHAN</td>
                                        <td width="10" align="center" style="width:10px; color:#7ac142; font-weight:bold; font-size:8pt; padding:3px 0; border-bottom:1px solid #e2e8f0;">|</td>
                                        <td style="font-weight:bold; color:#1e293b; font-size:7.5pt; padding:3px 6px; border-bottom:1px solid #e2e8f0;">${item.tanggal}</td>
                                    </tr>
                                    <tr>
                                        <td width="85" style="width:85px; font-weight:bold; color:#003b73; font-size:7.5pt; padding:3px 6px; border-bottom:1px solid #e2e8f0; background:#f8fafc; text-transform:uppercase;">LOKASI</td>
                                        <td width="10" align="center" style="width:10px; color:#7ac142; font-weight:bold; font-size:8pt; padding:3px 0; border-bottom:1px solid #e2e8f0;">|</td>
                                        <td style="font-weight:bold; color:#1e293b; font-size:7.5pt; padding:3px 6px; border-bottom:1px solid #e2e8f0;">${item.location}</td>
                                    </tr>
                                </table>

                                <!-- Bottom Section -->
                                <table width="100%" border="0" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; border-top:1.5pt solid #7ac142; background:#ffffff;">
                                    <tr>
                                        <td valign="top" style="padding:4px 6px; vertical-align:top;">
                                            <div style="color:#dc2626; font-weight:bold; font-size:6.5pt; text-transform:uppercase; margin-bottom:2px;">PERHATIAN</div>
                                            <div style="color:#475569; font-size:5pt; line-height:1.2; font-weight:normal;">${attentionText}</div>
                                        </td>
                                        <td width="75" align="center" valign="middle" style="width:75px; padding:4px; text-align:center; border-left:1px solid #e2e8f0; vertical-align:middle;">
                                            ${item.qrBase64 ? `<img src="${item.qrBase64}" width="45" height="45" border="0" style="display:block; margin:0 auto; width:45px; height:45px;">` : ''}
                                            <div style="background-color:#008744; color:#ffffff; font-size:4.5pt; font-weight:bold; padding:1px 4px; border-radius:4px; display:inline-block; margin-top:2px;">SCAN UNTUK INFO</div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        `;
                    } else {
                        wordHtml += `<td width="${cardCellWidth}" style="width:${cardCellWidth}px; border:none;"></td>`;
                    }
                }
                wordHtml += `</tr>`;
            }

            wordHtml += `
                </table>
                </div>
            </body>
            </html>
            `;

            // Trigger Download as .doc
            const blob = new Blob(['\ufeff' + wordHtml], { type: 'application/msword' });
            const downloadUrl = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = downloadUrl;
            a.download = `Kartu_Inventaris_RekapIT_${new Date().toISOString().slice(0, 10)}.doc`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(downloadUrl);

            btnEksporWord.disabled = false;
            btnEksporWord.innerHTML = originalBtnHtml;
        });
    }

    // Client-side Filtering for Nomor Rekening and Cabang
    const filterRekening = document.getElementById('filterRekening');
    const filterCabang = document.getElementById('filterCabang');
    const btnClearFilters = document.getElementById('btnClearFilters');
    const cardRows = document.querySelectorAll('.card-row-item');

    function applyFilters() {
        const searchVal = filterRekening ? filterRekening.value.toLowerCase().trim() : '';
        const branchVal = filterCabang ? filterCabang.value : '';

        cardRows.forEach(row => {
            const rek = row.getAttribute('data-rekening').toLowerCase();
            const branchCode = row.getAttribute('data-branch-code');

            const matchesRek = rek.includes(searchVal) || rek.replace(/\D/g, '').includes(searchVal);
            const matchesBranch = (branchVal === '' || branchCode === branchVal);

            if (matchesRek && matchesBranch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
                const cb = row.querySelector('.item-checkbox');
                if (cb && cb.checked) {
                    cb.checked = false;
                }
            }
        });

        if (checkAll) checkAll.checked = false;
        updateSelectionState();
    }

    if (filterRekening && filterCabang) {
        filterRekening.addEventListener('input', applyFilters);
        filterCabang.addEventListener('change', applyFilters);
    }

    if (btnClearFilters) {
        btnClearFilters.addEventListener('click', function() {
            filterRekening.value = '';
            filterCabang.value = '';
            applyFilters();
        });
    }

    // Modal Import JS logic
    const importCheckAll = document.getElementById('importCheckAll');
    const importCheckboxes = document.querySelectorAll('.import-item-checkbox');
    const btnSubmitImport = document.getElementById('btnSubmitImport');
    const importSelectedCount = document.getElementById('importSelectedCount');
    const importSearchInput = document.getElementById('importSearchInput');
    const importAssetRows = document.querySelectorAll('.import-asset-row');

    function updateImportSelectionState() {
        const checked = document.querySelectorAll('.import-item-checkbox:checked');
        if (importSelectedCount) importSelectedCount.innerText = checked.length;
        if (btnSubmitImport) btnSubmitImport.disabled = (checked.length === 0);
    }

    if (importCheckAll) {
        importCheckAll.addEventListener('change', function() {
            importCheckboxes.forEach(cb => {
                const row = cb.closest('tr');
                if (row.style.display !== 'none') {
                    cb.checked = importCheckAll.checked;
                }
            });
            updateImportSelectionState();
        });
    }

    importCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            if (!cb.checked) {
                if (importCheckAll) importCheckAll.checked = false;
            } else {
                const visibleCheckboxes = Array.from(importCheckboxes).filter(c => c.closest('tr').style.display !== 'none');
                const visibleChecked = visibleCheckboxes.filter(c => c.checked);
                if (importCheckAll) importCheckAll.checked = (visibleChecked.length === visibleCheckboxes.length);
            }
            updateImportSelectionState();
        });
    });

    if (importSearchInput) {
        importSearchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            importAssetRows.forEach(row => {
                const text = row.getAttribute('data-search');
                if (text.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                    const cb = row.querySelector('.import-item-checkbox');
                    if (cb && cb.checked) {
                        cb.checked = false;
                    }
                }
            });
            if (importCheckAll) importCheckAll.checked = false;
            updateImportSelectionState();
        });
    }
});
</script>
