<?php
require_once 'models/InventarisKartu.php';
$inventarisModel = new InventarisKartu($conn);

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
                <h4 class="fw-800 m-0 text-dark">Cetak Kartu Inventaris</h4>
                <p class="text-muted small m-0">Buat, kelola, dan cetak kartu inventaris berukuran ATM (CR80) secara massal.</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button id="btnCetakMassal" class="btn btn-outline-primary shadow-sm" disabled style="border-radius: 12px;">
                <i class="bi bi-printer me-2"></i> Cetak Kartu Pilihan (<span id="selectedCount">0</span>)
            </button>
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah" style="border-radius: 12px;">
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
        <div class="alert alert-success border-0 shadow-sm rounded-4 p-3 mb-4 d-flex align-items-center justify-content-between animate-fade-in" role="alert" style="background: rgba(16, 185, 129, 0.1); color: #065f46;">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <span class="small fw-semibold"><?= htmlspecialchars($msg) ?></span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Main Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light border-bottom">
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
                                    <div class="bg-light bg-opacity-50 text-secondary rounded-circle d-inline-flex p-3 mb-3">
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
                            ?>
                            <tr>
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
                                <td><strong class="text-dark"><?= htmlspecialchars($item['nomor_rekening']) ?></strong></td>
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
                                    <button class="btn btn-sm btn-light text-primary btn-edit p-2 rounded-3 me-1 shadow-sm" 
                                            data-id="<?= $item['id'] ?>"
                                            data-rekening="<?= htmlspecialchars($item['nomor_rekening']) ?>"
                                            data-nama="<?= htmlspecialchars($item['nama_barang']) ?>"
                                            data-tanggal="<?= htmlspecialchars($item['tanggal_perolehan']) ?>"
                                            data-barcode="<?= htmlspecialchars($item['barcode_data']) ?>"
                                            title="Edit" style="border: 1px solid rgba(226, 232, 240, 0.8);">
                                        <i class="bi bi-pencil-square fs-6"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data kartu ini?')">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" name="hapus" class="btn btn-sm btn-light text-danger p-2 rounded-3 shadow-sm" title="Hapus" style="border: 1px solid rgba(226, 232, 240, 0.8);">
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
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-800 m-0 text-dark"><i class="bi bi-plus-circle-fill text-primary me-2"></i>Tambah Kartu Inventaris</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Nomor Rekening</label>
                        <input type="text" name="nomor_rekening" id="nomor_rekening" class="form-control bg-light border-0 py-2.5" placeholder="Contoh: 01.5.00003" required style="border-radius: 12px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Nama Barang</label>
                        <input type="text" name="nama_barang" class="form-control bg-light border-0 py-2.5" placeholder="Contoh: BANGUNAN GEDUNG KANTOR PUSA" required style="border-radius: 12px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Tanggal Perolehan</label>
                        <input type="date" name="tanggal_perolehan" class="form-control bg-light border-0 py-2.5" required style="border-radius: 12px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Kode QR / Barcode (Data QR Code)</label>
                        <input type="text" name="barcode_data" class="form-control bg-light border-0 py-2.5" placeholder="Salin/tempel kode QR di sini" required style="border-radius: 12px;">
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 py-2.5" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary px-4 py-2.5" style="border-radius: 12px;">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-800 m-0 text-dark"><i class="bi bi-pencil-square text-warning me-2"></i>Perbarui Kartu Inventaris</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Nomor Rekening</label>
                        <input type="text" name="nomor_rekening" id="edit_rekening" class="form-control bg-light border-0 py-2.5" required style="border-radius: 12px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Nama Barang</label>
                        <input type="text" name="nama_barang" id="edit_nama" class="form-control bg-light border-0 py-2.5" required style="border-radius: 12px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Tanggal Perolehan</label>
                        <input type="date" name="tanggal_perolehan" id="edit_tanggal" class="form-control bg-light border-0 py-2.5" required style="border-radius: 12px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Kode QR / Barcode (Data QR Code)</label>
                        <input type="text" name="barcode_data" id="edit_barcode" class="form-control bg-light border-0 py-2.5" required style="border-radius: 12px;">
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 py-2.5" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                    <button type="submit" name="update" class="btn btn-warning text-dark fw-bold px-4 py-2.5" style="border-radius: 12px;">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Cetak Setup -->
<div class="modal fade" id="modalCetak" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px;">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-800 m-0 text-dark"><i class="bi bi-printer-fill text-primary me-2"></i>Konfigurasi Cetak Kartu A4 (CR80)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Layout Grid Kertas A4</label>
                        <select id="printLayout" class="form-select bg-light border-0 py-2.5" style="border-radius: 12px;">
                            <option value="8">8 Kartu per Lembar (2x4 - Portrait)</option>
                            <option value="10">10 Kartu per Lembar (2x5 - Portrait)</option>
                            <option value="12">12 Kartu per Lembar (3x4 - Landscape)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Teks Perhatian (Bagian Bawah Kartu)</label>
                        <textarea id="printAttention" class="form-control bg-light border-0 text-xs" rows="2" style="border-radius: 12px; font-size: 0.8rem;">Perhatian
Dilarang memindahkan barang inventaris ini tanpa seizin Human Resource Departement (HRD) Bank Mitra</textarea>
                    </div>
                </div>

                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-geo-alt-fill text-danger me-2"></i>Isi Lokasi Aset Secara Manual</h6>
                <div class="table-responsive" style="max-height: 250px;">
                    <table class="table table-sm table-bordered align-middle text-xs">
                        <thead class="table-light">
                            <tr>
                                <th>Nomor Rekening</th>
                                <th>Nama Barang</th>
                                <th style="width: 320px;">Lokasi Aset (Ketik Manual)</th>
                            </tr>
                        </thead>
                        <tbody id="printLocationsList">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light px-4 py-2.5" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                <button type="button" id="btnProsesCetak" class="btn btn-primary px-4 py-2.5" style="border-radius: 12px;">
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
        border: 1.2px solid #000000 !important;
        background: #ffffff !important;
        font-family: Arial, sans-serif !important;
        color: #000000 !important;
        overflow: hidden !important;
        position: relative !important;
        font-size: 7.5pt !important;
        line-height: 1.15 !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .atm-card-table {
        width: 100% !important;
        height: 100% !important;
        border-collapse: collapse !important;
        table-layout: fixed !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
    }

    .atm-card-table td {
        border: 0.8px solid #000000 !important;
        padding: 2px 4px !important;
        vertical-align: middle !important;
        word-wrap: break-word !important;
        box-sizing: border-box !important;
        font-family: Arial, sans-serif !important;
        color: #000000 !important;
        background: #ffffff !important;
        font-size: 7.5pt !important;
        line-height: 1.15 !important;
    }

    /* Header styling */
    .card-header-logo {
        text-align: center !important;
        vertical-align: middle !important;
        background: #ffffff !important;
        padding: 2px !important;
    }
    .card-header-logo img {
        height: 28px !important;
        max-height: 28px !important;
        max-width: 95% !important;
        object-fit: contain !important;
        display: block !important;
        margin: 0 auto !important;
    }

    .card-header-title {
        background-color: #8cd4f5 !important; /* Biru muda */
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        font-weight: bold !important;
        text-align: center !important;
        font-size: 7.5pt !important;
        text-transform: uppercase !important;
        letter-spacing: -0.2px !important;
    }
    .card-header-subtitle {
        background-color: #8cd4f5 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        font-weight: bold !important;
        text-align: center !important;
        font-size: 8.5pt !important;
        text-transform: uppercase !important;
        letter-spacing: -0.2px !important;
    }

    /* Label & Value columns */
    .card-label {
        font-weight: bold !important;
        font-size: 7.5pt !important;
        text-transform: capitalize !important;
        padding-left: 5px !important;
    }
    .card-value {
        font-size: 7.5pt !important;
        font-weight: normal !important;
        word-break: break-all !important;
        padding-left: 5px !important;
    }
    .card-value-bold {
        font-weight: bold !important;
        font-size: 7.5pt !important;
        word-break: break-all !important;
        padding-left: 5px !important;
    }

    /* QR Code Cell */
    .card-qr-cell {
        text-align: center !important;
        vertical-align: middle !important;
        padding: 0 !important;
        background: #ffffff !important;
    }
    .card-qr-img {
        display: block !important;
        margin: 0 auto !important;
        width: 72px !important;
        height: 72px !important;
    }
    .card-qr-img img, .card-qr-img canvas {
        width: 72px !important;
        height: 72px !important;
        display: block !important;
        margin: 0 auto !important;
    }

    /* Attention text */
    .card-attention {
        font-size: 5pt !important;
        font-weight: bold !important;
        text-align: center !important;
        vertical-align: middle !important;
        line-height: 1.15 !important;
        padding: 1px 3px !important;
        white-space: pre-line !important;
        background: #ffffff !important;
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
                <td><strong>${rekening}</strong></td>
                <td>${nama}</td>
                <td>
                    <input type="text" class="form-control form-control-sm print-location-input" 
                           data-id="${id}" 
                           placeholder="Contoh: KC.BTL/Lt-2/Ruang-AO" 
                           style="border-radius: 8px;">
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
                        <table class="atm-card-table">
                            <colgroup>
                                <col style="width: 26%;">
                                <col style="width: 42%;">
                                <col style="width: 32%;">
                            </colgroup>
                            <tr>
                                <td class="card-header-logo" rowspan="2" style="text-align: center; vertical-align: middle;">
                                    <img src="${logoUrl}" alt="Logo">
                                </td>
                                <td class="card-header-title" colspan="2" style="height: 18px;">PT BPR Mitratama Arthabuana</td>
                            </tr>
                            <tr>
                                <td class="card-header-subtitle" colspan="2" style="height: 18px;">Asset Tetap</td>
                            </tr>
                            <tr>
                                <td class="card-label">Nomor Asset</td>
                                <td class="card-value" colspan="2" style="text-align: left; padding-left: 6px;">${item.assetnum}</td>
                            </tr>
                            <tr>
                                <td class="card-label">Nama Asset</td>
                                <td class="card-value-bold" colspan="2" style="text-align: left; padding-left: 6px; text-transform: uppercase;">${item.nama}</td>
                            </tr>
                            <tr>
                                <td class="card-label">Tgl Perolehan</td>
                                <td class="card-value" style="text-align: left; padding-left: 6px;">${item.tanggal}</td>
                                <td class="card-qr-cell" rowspan="3">
                                    <div id="${cardId}" class="card-qr-img"></div>
                                </td>
                            </tr>
                            <tr>
                                <td class="card-label">Lokasi</td>
                                <td class="card-value" style="text-align: left; padding-left: 6px;">${manualLoc}</td>
                            </tr>
                            <tr>
                                <td class="card-attention" colspan="2">${attentionText}</td>
                            </tr>
                        </table>
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
                    width: 56,
                    height: 56,
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
});
</script>
