<?php
require_once 'models/Sparepart.php';
$sparepartModel = new Sparepart($conn);

// Proses Hapus
if (isset($_POST['hapus'])) {
    $id = $_POST['id'];
    if ($sparepartModel->delete($id)) {
        header("Location: index.php?page=sparepart&status=deleted");
        exit();
    }
}

// Proses Tambah Stok
if (isset($_POST['tambah_stok'])) {
    $id = $_POST['id'];
    $jumlah = $_POST['jumlah'];
    if ($sparepartModel->updateStok($id, $jumlah)) {
        header("Location: index.php?page=sparepart&status=updated");
        exit();
    }
}

// Proses Tambah Baru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah'])) {
    $data = [
        'nama_sparepart' => $_POST['nama_sparepart'],
        'kode_sparepart' => $_POST['kode_sparepart'],
        'stok' => $_POST['stok'],
        'satuan' => $_POST['satuan']
    ];
    if ($sparepartModel->create($data)) {
        header("Location: index.php?page=sparepart&status=success");
        exit();
    }
}

// Proses Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $id = $_POST['id'];
    $data = [
        'nama_sparepart' => $_POST['nama_sparepart'],
        'kode_sparepart' => $_POST['kode_sparepart'],
        'stok' => $_POST['stok'],
        'satuan' => $_POST['satuan']
    ];
    if ($sparepartModel->update($id, $data)) {
        header("Location: index.php?page=sparepart&status=updated");
        exit();
    }
}

$spareparts = $sparepartModel->getAll();

// Calculate Stats
$totalItems = count($spareparts);
$lowStockCount = 0;
foreach ($spareparts as $s) {
    if ($s['stok'] < 5) {
        $lowStockCount++;
    }
}
?>

<div class="container-fluid animate-fade-in">
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center">
            <div class="bg-primary bg-opacity-10 p-2.5 rounded-3 me-3 text-primary">
                <i class="bi bi-cpu fs-4"></i>
            </div>
            <div>
                <h4 class="fw-800 m-0 text-dark">Manajemen Sparepart</h4>
                <p class="text-muted small m-0">Kelola ketersediaan suku cadang perangkat keras IT perusahaan</p>
            </div>
        </div>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah" style="border-radius: 14px;">
            <i class="bi bi-plus-circle me-2"></i> Tambah Sparepart
        </button>
    </div>

    <!-- Notification Alert -->
    <?php if (isset($_GET['status'])): 
        $status = $_GET['status'];
        $msg = "Berhasil memproses data sparepart!";
        if ($status === 'success') $msg = "Sparepart baru berhasil ditambahkan!";
        if ($status === 'updated') $msg = "Stok / Data sparepart berhasil diperbarui!";
        if ($status === 'deleted') $msg = "Sparepart berhasil dihapus!";
    ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 p-3 mb-4 d-flex align-items-center justify-content-between" role="alert" style="background: rgba(16, 185, 129, 0.1); color: #065f46;">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <span class="small fw-semibold"><?= htmlspecialchars($msg) ?></span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- KPI Widgets -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
                <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%); pointer-events: none;"></div>
                <div class="card-body p-4 text-white position-relative">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3.5rem; transform: translate(10%, -10%);">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <span class="small fw-bold opacity-75">TOTAL JENIS SPAREPART</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $totalItems ?> Jenis</h3>
                    <small class="opacity-70 d-block mt-2">Seluruh varian suku cadang terdaftar</small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%); pointer-events: none;"></div>
                <div class="card-body p-4 text-white position-relative">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3.5rem; transform: translate(10%, -10%);">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <span class="small fw-bold opacity-75">PERLU RESTOK (STOK &lt; 5)</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $lowStockCount ?> Item</h3>
                    <small class="opacity-70 d-block mt-2">Suku cadang hampir habis dan kritis</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Client-Side Search Panel -->
    <div class="card border-0 shadow-sm mb-4 rounded-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label small fw-bold text-muted">🔍 Cari Sparepart</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="sparepartSearch" class="form-control bg-light border-0" placeholder="Cari Kode atau Nama Sparepart..." onkeyup="filterSpareparts()">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label d-block mb-2">&nbsp;</label>
                    <button class="btn btn-outline-secondary w-100 fw-bold py-2 shadow-sm rounded-3" onclick="resetFilters()">
                        <i class="bi bi-x-circle me-1.5"></i>Reset Pencarian
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Table Card -->
    <div class="card p-4 border-0 shadow-sm" style="border-radius: 20px;">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="sparepartTable">
                <thead>
                    <tr>
                        <th style="width: 150px;">Kode</th>
                        <th>Nama Sparepart</th>
                        <th>Stok Saat Ini</th>
                        <th>Satuan</th>
                        <th class="text-end" style="width: 180px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($spareparts)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <div class="bg-light bg-opacity-50 text-secondary rounded-circle d-inline-flex p-3 mb-3">
                                    <i class="bi bi-cpu fs-3"></i>
                                </div>
                                <p class="small fw-semibold mb-0">Belum ada data sparepart.</p>
                                <small class="text-muted">Klik tombol "Tambah Sparepart" untuk menambahkan.</small>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($spareparts as $s): 
                            $is_low = $s['stok'] < 5;
                            $stock_badge = $is_low ? 'bg-danger bg-opacity-10 text-danger' : 'bg-success bg-opacity-10 text-success';
                        ?>
                        <tr class="sparepart-row" data-search="<?= htmlspecialchars(strtolower($s['kode_sparepart'] . ' ' . $s['nama_sparepart'])) ?>">
                            <td><span class="badge bg-light text-dark border px-2.5 py-1.5 fw-bold" style="font-size: 0.75rem;"><?= htmlspecialchars($s['kode_sparepart']) ?></span></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 p-2.5 rounded-3 text-primary d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                        <i class="bi bi-cpu-fill fs-5"></i>
                                    </div>
                                    <strong class="text-dark" style="font-size: 0.95rem;"><?= htmlspecialchars($s['nama_sparepart']) ?></strong>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $stock_badge ?> rounded-pill px-3 py-2 fw-bold" style="font-size: 0.75rem;">
                                    <?= $s['stok'] ?>
                                </span>
                                <?php if ($is_low): ?>
                                    <span class="text-danger small ms-1" style="font-size: 0.72rem; font-weight: 700;"><i class="bi bi-exclamation-triangle"></i> Kritis</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="text-muted small fw-600"><?= htmlspecialchars($s['satuan'] ?: 'Unit') ?></span></td>
                            <td class="text-end">
                                <!-- Tambah Stok Cepat (+1) -->
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <input type="hidden" name="jumlah" value="1">
                                    <button type="submit" name="tambah_stok" class="btn btn-sm btn-light text-success p-2 rounded-3 me-1 shadow-sm" title="Tambah 1 Stok" style="border: 1px solid rgba(226, 232, 240, 0.8);">
                                        <i class="bi bi-plus-circle-fill fs-6"></i>
                                    </button>
                                </form>

                                <!-- Edit -->
                                <button class="btn btn-sm btn-light text-primary btn-edit p-2 rounded-3 me-1 shadow-sm" 
                                        data-id="<?= $s['id'] ?>"
                                        data-nama="<?= htmlspecialchars($s['nama_sparepart']) ?>"
                                        data-kode="<?= htmlspecialchars($s['kode_sparepart']) ?>"
                                        data-stok="<?= $s['stok'] ?>"
                                        data-satuan="<?= htmlspecialchars($s['satuan']) ?>"
                                        title="Edit" style="border: 1px solid rgba(226, 232, 240, 0.8);">
                                    <i class="bi bi-pencil-square fs-6"></i>
                                </button>

                                <!-- Hapus -->
                                <form method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus sparepart ini?')">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
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

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 28px;">
            <form method="POST">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-800 m-0 text-dark"><i class="bi bi-plus-circle-fill text-primary me-2"></i>Tambah Sparepart Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label fw-600 text-dark">Kode Sparepart</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted" style="border-radius: 12px 0 0 12px;"><i class="bi bi-qr-code"></i></span>
                            <input type="text" name="kode_sparepart" class="form-control border-start-0" placeholder="Contoh: RAM-DDR4-8GB" required style="border-radius: 0 12px 12px 0;">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600 text-dark">Nama Sparepart</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted" style="border-radius: 12px 0 0 12px;"><i class="bi bi-cpu"></i></span>
                            <input type="text" name="nama_sparepart" class="form-control border-start-0" placeholder="Contoh: Corsair Vengeance 8GB DDR4" required style="border-radius: 0 12px 12px 0;">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-600 text-dark">Stok Awal</label>
                            <input type="number" name="stok" class="form-control" value="0" required style="border-radius: 12px;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-600 text-dark">Satuan</label>
                            <input type="text" name="satuan" class="form-control" placeholder="Contoh: Pcs, Unit" required style="border-radius: 12px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary px-4">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 28px;">
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-800 m-0 text-dark"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Sparepart</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label fw-600 text-dark">Kode Sparepart</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted" style="border-radius: 12px 0 0 12px;"><i class="bi bi-qr-code"></i></span>
                            <input type="text" name="kode_sparepart" id="edit_kode" class="form-control border-start-0" required style="border-radius: 0 12px 12px 0;">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600 text-dark">Nama Sparepart</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted" style="border-radius: 12px 0 0 12px;"><i class="bi bi-cpu"></i></span>
                            <input type="text" name="nama_sparepart" id="edit_nama" class="form-control border-start-0" required style="border-radius: 0 12px 12px 0;">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-600 text-dark">Stok</label>
                            <input type="number" name="stok" id="edit_stok" class="form-control" required style="border-radius: 12px;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-600 text-dark">Satuan</label>
                            <input type="text" name="satuan" id="edit_satuan" class="form-control" required style="border-radius: 12px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                    <button type="submit" name="update" class="btn btn-primary px-4">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Bind Edit Button Values to Modal Fields
document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const nama = this.getAttribute('data-nama');
        const kode = this.getAttribute('data-kode');
        const stok = this.getAttribute('data-stok');
        const satuan = this.getAttribute('data-satuan');

        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nama').value = nama;
        document.getElementById('edit_kode').value = kode;
        document.getElementById('edit_stok').value = stok;
        document.getElementById('edit_satuan').value = satuan;

        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    });
});

// Client search filter
function filterSpareparts() {
    const query = document.getElementById('sparepartSearch').value.toLowerCase();
    const rows = document.querySelectorAll('.sparepart-row');
    rows.forEach(row => {
        const searchVal = row.getAttribute('data-search');
        if (searchVal.includes(query)) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
}

function resetFilters() {
    document.getElementById('sparepartSearch').value = "";
    filterSpareparts();
}
</script>
