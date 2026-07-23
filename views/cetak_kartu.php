<?php
require_once 'models/InventarisKartu.php';
require_once 'models/Cabang.php';

$inventarisModel = new InventarisKartu($conn);
$cabangModel = new Cabang($conn);

$branches = $cabangModel->getAll();

// Proses Hapus
if (isset($_POST['hapus'])) {
    $id = $_POST['id'];
    if ($inventarisModel->delete($id)) {
        header("Location: index.php?page=cetak_kartu&status=deleted");
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

// Path preloading logo kustom
$base_dir_path = dirname($_SERVER['SCRIPT_NAME']);
$base_dir_path = str_replace('\\', '/', $base_dir_path);
if ($base_dir_path === '/') {
    $base_dir_path = '';
}
$preload_logo_path = $base_dir_path . '/assets/LOGO TYPE 2.png';
?>

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
                <h4 class="fw-800 m-0">Cetak Kartu Inventaris</h4>
                <p class="text-muted small m-0">Kelola dan cetak kartu inventaris berukuran ATM (CR80) secara massal.</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button id="btnCetakMassal" class="btn btn-outline-primary shadow-sm" disabled>
                <i class="bi bi-printer me-2"></i> Cetak Kartu Pilihan (<span id="selectedCount">0</span>)
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
        if ($status === 'updated') $msg = "Perubahan data kartu berhasil disimpan!";
        if ($status === 'deleted') $msg = "Data kartu inventaris berhasil dihapus!";
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
                            ?>
                            <tr class="card-row-item" data-rekening="<?= htmlspecialchars($item['nomor_rekening']) ?>" data-branch-code="<?= $branchCode ?>">
                                <td class="ps-4">
                                    <div class="form-check">
                                        <input class="form-check-input item-checkbox" type="checkbox" value="<?= $item['id'] ?>" 
                                               data-rekening="<?= htmlspecialchars($item['nomor_rekening']) ?>"
                                               data-nama="<?= htmlspecialchars($item['nama_barang']) ?>"
                                               data-tanggal="<?= date('d/m/Y', strtotime($item['tanggal_perolehan'])) ?>"
                                               data-assetnum="<?= htmlspecialchars($combinedAssetNum) ?>"
                                               data-barcode="<?= htmlspecialchars($item['barcode_data']) ?>">
                                    </div>
                                </td>
                                <td><strong><?= htmlspecialchars($item['nomor_rekening']) ?></strong></td>
                                <td><?= htmlspecialchars($item['nama_barang']) ?></td>
                                <td>
                                    <div class="small text-muted">
                                        <i class="bi bi-calendar3 me-1 text-primary"></i>
                                        <?= date('d M Y', strtotime($item['tanggal_perolehan'])) ?>
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
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-800 m-0"><i class="bi bi-printer-fill text-primary me-2"></i>Konfigurasi Cetak Kartu A4 (CR80)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
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

                <!-- Tips Alert Placed Safely Above Table -->
                <div class="alert alert-info border-0 rounded-4 p-3 mb-4 d-flex align-items-start gap-2">
                    <i class="bi bi-info-circle-fill fs-5 mt-0.5"></i>
                    <div class="small">
                        <strong>Tips Menyimpan PDF:</strong> Untuk menyimpan hasil cetak sebagai file PDF, pilih opsi <b>"Simpan sebagai PDF" / "Save as PDF"</b> pada pilihan <b>Tujuan / Destination</b> di jendela cetak browser Anda.
                    </div>
                </div>

                <h6 class="fw-bold mb-3"><i class="bi bi-geo-alt-fill text-danger me-2"></i>Isi Lokasi Aset Secara Manual</h6>
                <div class="border rounded-4 overflow-hidden mb-2" style="max-height: 220px; overflow-y: auto; border-color: var(--card-border) !important;">
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
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Batal</button>
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
        box-sizing: border-box !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        height: 100vh !important;
        display: grid !important;
        justify-content: center !important;
        align-content: start !important;
    }
    
    /* Layout 8 Kartu (Portrait) */
    .print-page.layout-8 {
        grid-template-columns: repeat(2, 85.6mm) !important;
        grid-gap: 12mm 14mm !important;
        padding-top: 15mm !important; /* Margin atas agar tidak terlalu rapat di pojok */
    }
    
    /* Layout 10 Kartu (Portrait) */
    .print-page.layout-10 {
        grid-template-columns: repeat(2, 85.6mm) !important;
        grid-gap: 5mm 14mm !important;
        padding-top: 8mm !important; /* Margin atas untuk 10 kartu */
    }

    /* Layout 12 Kartu (Landscape) */
    .print-page.layout-12 {
        grid-template-columns: repeat(3, 85.6mm) !important;
        grid-gap: 4mm 6mm !important;
        transform: scale(0.92) !important;
        transform-origin: center center !important;
        padding-top: 10mm !important; /* Margin atas untuk 12 kartu landscape */
    }

    /* Ukuran ATM Card (CR80) */
    .atm-card {
        width: 85.6mm !important;
        height: 54.0mm !important;
        box-sizing: border-box !important;
        border: 1.5px solid #003d79 !important;
        border-radius: 12px !important;
        background: #ffffff !important;
        font-family: Arial, sans-serif !important;
        color: #000000 !important;
        overflow: hidden !important;
        position: relative !important;
        display: flex !important;
        flex-direction: column !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    /* Top Header Section */
    .card-header-sec {
        display: flex !important;
        width: 100% !important;
        height: 9.5mm !important;
        border-bottom: 1.2px solid #003d79 !important;
        box-sizing: border-box !important;
    }
    .header-logo-box {
        width: 26% !important;
        height: 100% !important;
        border-right: 1.2px solid #003d79 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: #ffffff !important;
        box-sizing: border-box !important;
        padding: 0.5mm !important;
    }
    .header-logo-box img {
        max-height: 8.5mm !important;
        max-width: 95% !important;
        object-fit: contain !important;
    }
    .header-title-box {
        width: 74% !important;
        height: 100% !important;
        display: flex !important;
        flex-direction: column !important;
        box-sizing: border-box !important;
    }
    .header-main-title {
        background-color: #003d79 !important;
        color: #ffffff !important;
        font-weight: bold !important;
        font-size: 7.2pt !important;
        text-align: center !important;
        height: 4.5mm !important;
        line-height: 4.5mm !important;
        text-transform: uppercase !important;
        letter-spacing: 0.1px !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .header-sub-sec {
        height: 5.0mm !important;
        background: #ffffff !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        padding: 0 2mm 0 1.5mm !important;
        box-sizing: border-box !important;
        position: relative !important;
    }
    .header-sub-title {
        background: linear-gradient(90deg, #8dc63f 0%, #003d79 100%) !important;
        color: #ffffff !important;
        font-weight: bold !important;
        font-size: 7.5pt !important;
        text-align: center !important;
        height: 4.0mm !important;
        line-height: 4.0mm !important;
        padding: 0 4mm 0 2mm !important;
        border-radius: 0 10px 10px 0 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.2px !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .header-dots {
        display: grid !important;
        grid-template-columns: repeat(3, 1mm) !important;
        grid-gap: 0.5mm !important;
    }
    .header-dots span {
        width: 0.8mm !important;
        height: 0.8mm !important;
        background-color: #8dc63f !important;
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
        height: 6.0mm !important;
        border-bottom: 1.2px solid #003d79 !important;
        box-sizing: border-box !important;
    }
    .field-label {
        width: 26% !important;
        height: 100% !important;
        background-color: #003d79 !important;
        color: #ffffff !important;
        font-weight: bold !important;
        font-size: 7.2pt !important;
        display: flex !important;
        align-items: center !important;
        padding-left: 1.5mm !important;
        box-sizing: border-box !important;
        border-right: 1.2px solid #003d79 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .field-value-container {
        width: 74% !important;
        height: 100% !important;
        display: flex !important;
        align-items: center !important;
        background: #ffffff !important;
        box-sizing: border-box !important;
    }
    .field-icon {
        width: 6.5mm !important;
        height: 100% !important;
        border-right: 1.2px solid #003d79 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        color: #003d79 !important;
        box-sizing: border-box !important;
    }
    .field-svg-icon {
        width: 3.2mm !important;
        height: 3.2mm !important;
        color: #003d79 !important;
    }
    .field-value {
        font-size: 7.2pt !important;
        color: #000000 !important;
        padding-left: 1.5mm !important;
        font-weight: normal !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }
    .field-value.value-bold {
        font-weight: bold !important;
    }

    /* Bottom Section */
    .card-bottom-sec {
        display: flex !important;
        width: 100% !important;
        height: 20.5mm !important; /* Locked to precise height to fit 54mm card */
        border-top: 1.5px solid #8dc63f !important; /* Green divider line */
        box-sizing: border-box !important;
    }
    .bottom-left-attention {
        width: 68% !important;
        height: 100% !important;
        display: flex !important;
        align-items: center !important;
        padding: 1.0mm 1.5mm !important;
        box-sizing: border-box !important;
    }
    .attention-icon {
        margin-right: 1.5mm !important;
        display: flex !important;
        align-items: center !important;
    }
    .attention-text-box {
        display: flex !important;
        flex-direction: column !important;
    }
    .attention-title {
        font-weight: bold !important;
        font-size: 6.5pt !important;
        color: #003d79 !important;
        margin-bottom: 0.2mm !important;
    }
    .attention-desc {
        font-size: 4.8pt !important;
        line-height: 1.1 !important;
        color: #000000 !important;
        font-weight: bold !important;
    }
    .bottom-right-qr {
        width: 32% !important;
        height: 100% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: #ffffff !important;
        box-sizing: border-box !important;
        padding: 1.0mm !important;
    }
    .qr-border-box {
        border: 1px solid #d1d5db !important;
        border-radius: 2mm !important;
        padding: 0.5mm !important;
        background: #ffffff !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }
    .card-qr-img {
        width: 13.5mm !important;
        height: 13.5mm !important;
    }
    .card-qr-img canvas, .card-qr-img img {
        width: 13.5mm !important;
        height: 13.5mm !important;
        margin: 0 auto !important;
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
    const selectedCount = document.getElementById('selectedCount');
    const printLocationsList = document.getElementById('printLocationsList');
    const btnProsesCetak = document.getElementById('btnProsesCetak');
    const printLayout = document.getElementById('printLayout');
    const printAttention = document.getElementById('printAttention');

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

    // Checkbox logic for selection
    function updateSelectionState() {
        const checked = document.querySelectorAll('.item-checkbox:checked');
        selectedCount.innerText = checked.length;
        if (checked.length > 0) {
            btnCetakMassal.disabled = false;
            btnCetakMassal.classList.remove('btn-outline-primary');
            btnCetakMassal.classList.add('btn-primary');
        } else {
            btnCetakMassal.disabled = true;
            btnCetakMassal.classList.add('btn-outline-primary');
            btnCetakMassal.classList.remove('btn-primary');
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = checkAll.checked);
            updateSelectionState();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            if (!cb.checked) {
                checkAll.checked = false;
            } else if (document.querySelectorAll('.item-checkbox:checked').length === checkboxes.length) {
                checkAll.checked = true;
            }
            updateSelectionState();
        });
    });

    // When clicking Cetak Kartu Pilihan
    btnCetakMassal.addEventListener('click', function() {
        // Clear list
        printLocationsList.innerHTML = '';
        
        // Find all selected items
        const checked = document.querySelectorAll('.item-checkbox:checked');
        checked.forEach(cb => {
            const id = cb.value;
            const rekening = cb.getAttribute('data-rekening');
            const nama = cb.getAttribute('data-nama');
            
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="ps-3"><strong>${rekening}</strong></td>
                <td>${nama}</td>
                <td class="pe-3 py-2">
                    <input type="text" class="form-control form-control-sm print-location-input" 
                           data-id="${id}" 
                           placeholder="Contoh: KC.BTL/Lt-2/Ruang-AO">
                </td>
            `;
            printLocationsList.appendChild(tr);
        });

        new bootstrap.Modal(document.getElementById('modalCetak')).show();
    });

    // Handle Printing
    btnProsesCetak.addEventListener('click', function() {
        const checked = document.querySelectorAll('.item-checkbox:checked');
        if (checked.length === 0) return;

        // Get manual locations
        const locationsMap = {};
        document.querySelectorAll('.print-location-input').forEach(input => {
            const id = input.getAttribute('data-id');
            locationsMap[id] = input.value.trim() || '-';
        });

        // Get options
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

        // Get logo absolute path dynamically to prevent subfolder issues
        const currentPath = window.location.pathname;
        const baseDir = currentPath.substring(0, currentPath.lastIndexOf('/'));
        const logoUrl = (baseDir ? baseDir : '') + '/assets/LOGO TYPE 2.png';

        // Group selected items into pages
        const selectedItems = Array.from(checked).map(cb => ({
            id: cb.value,
            rekening: cb.getAttribute('data-rekening'),
            nama: cb.getAttribute('data-nama'),
            tanggal: cb.getAttribute('data-tanggal'),
            assetnum: cb.getAttribute('data-assetnum'),
            barcode: cb.getAttribute('data-barcode')
        }));

        let pageHtml = '';
        for (let i = 0; i < selectedItems.length; i += limitPerPage) {
            const chunk = selectedItems.slice(i, i + limitPerPage);
            pageHtml += `<div class="print-page ${orientationClass}">`;
            
            chunk.forEach(item => {
                const manualLoc = locationsMap[item.id] || '-';
                const cardId = `qr-card-${item.id}`;
                
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
                                    <div class="header-sub-title">ASSET TETAP</div>
                                    <div class="header-dots">
                                        <span></span><span></span><span></span>
                                        <span></span><span></span><span></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Data Fields Section -->
                        <div class="card-fields-sec">
                            <!-- Nomor Asset -->
                            <div class="card-field-row">
                                <div class="field-label">Nomor Asset</div>
                                <div class="field-value-container">
                                    <div class="field-icon">
                                        <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M2 2a1 1 0 0 1 1-1h4.586a1 1 0 0 1 .707.293l7 7a1 1 0 0 1 0 1.414l-4.586 4.586a1 1 0 0 1-1.414 0l-7-7A1 1 0 0 1 2 8.586V2zm3.5 3.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/></svg>
                                    </div>
                                    <div class="field-value">${item.assetnum}</div>
                                </div>
                            </div>
                            <!-- Nama Asset -->
                            <div class="card-field-row">
                                <div class="field-label">Nama Asset</div>
                                <div class="field-value-container">
                                    <div class="field-icon">
                                        <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M12 1H4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zM4 2h8a1 1 0 0 1 1 1v7H3V3a1 1 0 0 1 1-1z"/><path d="M8 12a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/></svg>
                                    </div>
                                    <div class="field-value value-bold">${item.nama}</div>
                                </div>
                            </div>
                            <!-- Tgl Perolehan -->
                            <div class="card-field-row">
                                <div class="field-label">Tgl Perolehan</div>
                                <div class="field-value-container">
                                    <div class="field-icon">
                                        <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/></svg>
                                    </div>
                                    <div class="field-value">${item.tanggal}</div>
                                </div>
                            </div>
                            <!-- Lokasi -->
                            <div class="card-field-row">
                                <div class="field-label">Lokasi</div>
                                <div class="field-value-container">
                                    <div class="field-icon">
                                        <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/></svg>
                                    </div>
                                    <div class="field-value">${manualLoc}</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Bottom Section (Attention & QR) -->
                        <div class="card-bottom-sec">
                            <div class="bottom-left-attention">
                                <div class="attention-icon">
                                    <svg viewBox="0 0 16 16" fill="currentColor" class="attention-svg-icon" style="color: #003d79; width: 20px; height: 20px;"><path d="M5.338 1.59a61.44 61.44 0 0 0-2.837.856.481.481 0 0 0-.328.39c-.554 4.117.773 7.537 2.527 9.578 1.158 1.348 2.63 2.106 3.292 2.402a.474.474 0 0 0 .416 0c.662-.296 2.134-1.054 3.292-2.402 1.754-2.04 3.081-5.461 2.527-9.578a.48.48 0 0 0-.328-.39 61.44 61.44 0 0 0-2.837-.856.481.481 0 0 0-.415.118L8 2.22l-2.247-1.512a.48.48 0 0 0-.415-.119zm0-1.59a1.48 1.48 0 0 1 .825.248L8 1.44l2.163-1.455a1.48 1.48 0 0 1 1.255-.078c1.373.486 2.536 1.058 3.524 1.402a1.48 1.48 0 0 1 .98 1.2c.706 5.253-1.05 9.475-3.328 12.124-1.523 1.772-3.468 2.684-4.224 3.022a1.475 1.475 0 0 1-1.34 0c-.756-.338-2.7-1.25-4.224-3.022C1.756 12.115-.002 7.893.704 2.64a1.48 1.48 0 0 1 .98-1.2c.988-.344 2.15-.916 3.524-1.402a1.48 1.48 0 0 1 .13-.048z"/><path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/></svg>
                                </div>
                                <div class="attention-text-box">
                                    <div class="attention-title">Perhatian</div>
                                    <div class="attention-desc">${attentionText}</div>
                                </div>
                            </div>
                            <div class="bottom-right-qr">
                                <div class="qr-border-box">
                                    <div id="${cardId}" class="card-qr-img"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            pageHtml += `</div>`;
        }

        printContainer.innerHTML = pageHtml;

        // Generate QR Codes inside the elements
        selectedItems.forEach(item => {
            const cardId = `qr-card-${item.id}`;
            const elem = document.getElementById(cardId);
            if (elem) {
                // Clear content
                elem.innerHTML = '';
                // Generate QR Code
                new QRCode(elem, {
                    text: item.barcode,
                    width: 38,
                    height: 38,
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        });

        // Add dynamic print style rule for orientation
        let styleSheet = document.getElementById('print-orientation-style');
        if (!styleSheet) {
            styleSheet = document.createElement('style');
            styleSheet.id = 'print-orientation-style';
            document.head.appendChild(styleSheet);
        }
        styleSheet.innerHTML = `@media print { @page { size: ${pageOrientationRule}; margin: 0; } }`;

        // Wait brief moment for QR Code canvases to render, then print
        setTimeout(() => {
            window.print();
        }, 300);
    });

    // Client-side Filtering for Nomor Rekening and Cabang
    const filterRekening = document.getElementById('filterRekening');
    const filterCabang = document.getElementById('filterCabang');
    const btnClearFilters = document.getElementById('btnClearFilters');
    const cardRows = document.querySelectorAll('.card-row-item');

    function applyFilters() {
        const searchVal = filterRekening.value.toLowerCase().trim();
        const branchVal = filterCabang.value;

        cardRows.forEach(row => {
            const rek = row.getAttribute('data-rekening').toLowerCase();
            const branchCode = row.getAttribute('data-branch-code');

            // Cocokkan nomor rekening (baik dengan format titik maupun digit saja)
            const matchesRek = rek.includes(searchVal) || rek.replace(/\D/g, '').includes(searchVal);
            // Cocokkan cabang (jika "Semua Cabang" kosong, maka true. Jika terisi, harus sama dengan kode cabang dari rekening)
            const matchesBranch = (branchVal === '' || branchCode === branchVal);

            if (matchesRek && matchesBranch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
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
});
</script>
