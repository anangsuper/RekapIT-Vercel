<?php
require_once 'models/Divisi.php';
$divisiModel = new Divisi($conn);

// Proses Hapus
if (isset($_POST['hapus'])) {
    $id = $_POST['id'];
    if ($divisiModel->delete($id)) {
        header("Location: index.php?page=divisi&status=deleted");
        exit();
    }
}

// Proses Tambah
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah'])) {
    $data = ['nama_divisi' => $_POST['nama_divisi']];
    if ($divisiModel->create($data)) {
        header("Location: index.php?page=divisi&status=success");
        exit();
    }
}

// Proses Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $id = $_POST['id'];
    $data = ['nama_divisi' => $_POST['nama_divisi']];
    if ($divisiModel->update($id, $data)) {
        header("Location: index.php?page=divisi&status=updated");
        exit();
    }
}

// Fetch divisions with employee counts
try {
    $stmt = $conn->prepare("SELECT d.*, COUNT(k.id) as total_karyawan 
                            FROM divisi d 
                            LEFT JOIN karyawan k ON d.id = k.id_divisi 
                            GROUP BY d.id 
                            ORDER BY d.nama_divisi ASC");
    $stmt->execute();
    $divisis = $stmt->fetchAll();
} catch (PDOException $e) {
    $divisis = $divisiModel->getAll();
    // fallback total_karyawan to 0
    foreach ($divisis as &$div) {
        $div['total_karyawan'] = 0;
    }
}

// Calculate Stats
$totalDivisi = count($divisis);
$totalKaryawan = 0;
foreach ($divisis as $div) {
    $totalKaryawan += $div['total_karyawan'];
}
?>

<div class="container-fluid animate-fade-in">
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center">
            <div class="bg-primary bg-opacity-10 p-2.5 rounded-3 me-3 text-primary">
                <i class="bi bi-people fs-4"></i>
            </div>
            <div>
                <h4 class="fw-800 m-0 text-dark">Manajemen Divisi</h4>
                <p class="text-muted small m-0">Kelola divisi dan departemen kerja karyawan perusahaan</p>
            </div>
        </div>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah" style="border-radius: 14px;">
            <i class="bi bi-plus-circle me-2"></i> Tambah Divisi
        </button>
    </div>

    <!-- Notification Alert -->
    <?php if (isset($_GET['status'])): 
        $status = $_GET['status'];
        $msg = "Berhasil memproses data divisi!";
        if ($status === 'success') $msg = "Divisi baru berhasil ditambahkan!";
        if ($status === 'updated') $msg = "Perubahan divisi berhasil disimpan!";
        if ($status === 'deleted') $msg = "Divisi berhasil dihapus!";
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
                        <i class="bi bi-diagram-3"></i>
                    </div>
                    <span class="small fw-bold opacity-75">TOTAL DIVISI</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $totalDivisi ?></h3>
                    <small class="opacity-70 d-block mt-2">Departemen operasional perusahaan</small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%);">
                <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%); pointer-events: none;"></div>
                <div class="card-body p-4 text-white position-relative">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3.5rem; transform: translate(10%, -10%);">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <span class="small fw-bold opacity-75">SEBARAN KARYAWAN</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $totalKaryawan ?> Orang</h3>
                    <small class="opacity-70 d-block mt-2">Total staf terpetakan dalam divisi</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Client-Side Search Panel -->
    <div class="card border-0 shadow-sm mb-4 rounded-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label small fw-bold text-muted">🔍 Cari Divisi</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="divisiSearch" class="form-control bg-light border-0" placeholder="Cari Nama Divisi..." onkeyup="filterDivisions()">
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
            <table class="table table-hover align-middle mb-0" id="divisiTable">
                <thead>
                    <tr>
                        <th style="width: 80px;">No</th>
                        <th>Nama Divisi</th>
                        <th>Jumlah Karyawan</th>
                        <th>Dibuat Pada</th>
                        <th class="text-end" style="width: 150px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($divisis)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <div class="bg-light bg-opacity-50 text-secondary rounded-circle d-inline-flex p-3 mb-3">
                                    <i class="bi bi-diagram-3 fs-3"></i>
                                </div>
                                <p class="small fw-semibold mb-0">Belum ada divisi terdaftar.</p>
                                <small class="text-muted">Klik tombol "Tambah Divisi" untuk menambahkan.</small>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($divisis as $index => $d): ?>
                        <tr class="divisi-row" data-search="<?= htmlspecialchars(strtolower($d['nama_divisi'])) ?>">
                            <td><span class="fw-bold text-muted"><?= $index + 1 ?></span></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 p-2.5 rounded-3 text-primary d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                        <i class="bi bi-diagram-3-fill fs-5"></i>
                                    </div>
                                    <strong class="text-dark" style="font-size: 0.95rem;"><?= htmlspecialchars($d['nama_divisi']) ?></strong>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-purple bg-opacity-10 text-purple rounded-pill px-3 py-2 fw-bold" style="font-size: 0.72rem; color: #9333ea; background-color: rgba(147, 85, 247, 0.1);">
                                    👤 <?= $d['total_karyawan'] ?> Karyawan
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center text-muted small">
                                    <i class="bi bi-calendar3 me-2 text-primary opacity-70"></i>
                                    <span><?= date('d M Y', strtotime($d['created_at'])) ?></span>
                                </div>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-light text-primary btn-edit p-2 rounded-3 me-1 shadow-sm" 
                                        data-id="<?= $d['id'] ?>"
                                        data-nama="<?= htmlspecialchars($d['nama_divisi']) ?>"
                                        title="Edit" style="border: 1px solid rgba(226, 232, 240, 0.8);">
                                    <i class="bi bi-pencil-square fs-6"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus divisi ini?')">
                                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
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
                    <h5 class="fw-800 m-0 text-dark"><i class="bi bi-plus-circle-fill text-primary me-2"></i>Tambah Divisi Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label fw-600 text-dark">Nama Divisi</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted" style="border-radius: 12px 0 0 12px;"><i class="bi bi-diagram-3"></i></span>
                            <input type="text" name="nama_divisi" class="form-control border-start-0" placeholder="Contoh: Divisi IT, Divisi HRD" required style="border-radius: 0 12px 12px 0;">
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
                    <h5 class="fw-800 m-0 text-dark"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Divisi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label fw-600 text-dark">Nama Divisi</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted" style="border-radius: 12px 0 0 12px;"><i class="bi bi-diagram-3"></i></span>
                            <input type="text" name="nama_divisi" id="edit_nama" class="form-control border-start-0" required style="border-radius: 0 12px 12px 0;">
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

        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nama').value = nama;

        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    });
});

// Client search filter
function filterDivisions() {
    const query = document.getElementById('divisiSearch').value.toLowerCase();
    const rows = document.querySelectorAll('.divisi-row');
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
    document.getElementById('divisiSearch').value = "";
    filterDivisions();
}
</script>
