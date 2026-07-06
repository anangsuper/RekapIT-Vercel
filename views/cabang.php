<?php
require_once 'models/Cabang.php';
$cabangModel = new Cabang($conn);

// Proses Hapus
if (isset($_POST['hapus'])) {
    $id = $_POST['id'];
    if ($cabangModel->delete($id)) {
        header("Location: index.php?page=cabang&status=deleted");
        exit();
    }
}

// Proses Tambah
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah'])) {
    $data = [
        'nama_cabang' => $_POST['nama_cabang'],
        'alamat' => $_POST['alamat']
    ];
    if ($cabangModel->create($data)) {
        header("Location: index.php?page=cabang&status=success");
        exit();
    }
}

// Proses Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $id = $_POST['id'];
    $data = [
        'nama_cabang' => $_POST['nama_cabang'],
        'alamat' => $_POST['alamat']
    ];
    if ($cabangModel->update($id, $data)) {
        header("Location: index.php?page=cabang&status=updated");
        exit();
    }
}

// Fetch branches with asset counts
$stmt = $conn->prepare("SELECT c.*, COUNT(a.id) as total_aset 
                        FROM cabang c 
                        LEFT JOIN assets a ON c.id = a.id_cabang 
                        GROUP BY c.id 
                        ORDER BY c.nama_cabang ASC");
$stmt->execute();
$cabangs = $stmt->fetchAll();

// Calculate Stats
$totalCabang = count($cabangs);
$totalAset = 0;
foreach ($cabangs as $c) {
    $totalAset += $c['total_aset'];
}
?>

<div class="container-fluid animate-fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center">
            <div class="bg-primary bg-opacity-10 p-2.5 rounded-3 me-3 text-primary">
                <i class="bi bi-building fs-4"></i>
            </div>
            <div>
                <h4 class="fw-800 m-0">Manajemen Cabang</h4>
                <p class="text-muted small m-0">Kelola lokasi kantor cabang dan distribusi aset perusahaan</p>
            </div>
        </div>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="bi bi-plus-lg me-2"></i> Tambah Cabang
        </button>
    </div>

    <?php if (isset($_GET['status'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4 animate-fade-in" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> 
            <?php 
                if ($_GET['status'] == 'success') echo "Kantor cabang baru berhasil ditambahkan!";
                elseif ($_GET['status'] == 'updated') echo "Data cabang berhasil diperbarui!";
                elseif ($_GET['status'] == 'deleted') echo "Cabang berhasil dihapus!";
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- KPI Widgets -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
                <div class="card-body p-4 text-white position-relative">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3.5rem; transform: translate(10%, -10%);">
                        <i class="bi bi-building"></i>
                    </div>
                    <span class="small fw-bold opacity-75">TOTAL CABANG</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $totalCabang ?></h3>
                    <small class="opacity-70 d-block mt-2">Kantor operasional terdaftar</small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <div class="card-body p-4 text-white position-relative">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3.5rem; transform: translate(10%, -10%);">
                        <i class="bi bi-pc-display-horizontal"></i>
                    </div>
                    <span class="small fw-bold opacity-75">TOTAL SEBARAN ASET</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $totalAset ?> Unit</h3>
                    <small class="opacity-70 d-block mt-2">Seluruh aset hardware terdistribusi</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Client-Side Search Panel -->
    <div class="card border-0 shadow-sm mb-4 rounded-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label small fw-bold text-muted">🔍 Cari Cabang</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="branchSearch" class="form-control bg-light border-0" placeholder="Cari Nama Cabang atau Alamat..." onkeyup="filterBranches()">
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

    <!-- Branches List -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <!-- Desktop view -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle mb-0" id="branchTable">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4" width="80">No</th>
                            <th>Kantor Cabang</th>
                            <th>Alamat Lengkap</th>
                            <th>Jumlah Aset</th>
                            <th>Dibuat Pada</th>
                            <th class="pe-4 text-end" width="150">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($cabangs)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-building fs-2 d-block mb-2"></i> Belum ada data cabang.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($cabangs as $index => $c): ?>
                            <tr class="branch-row" data-search="<?= htmlspecialchars(strtolower($c['nama_cabang'] . ' ' . $c['alamat'])) ?>">
                                <td class="ps-4 fw-bold text-muted"><?= $index + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3 text-primary">
                                            <i class="bi bi-geo-alt"></i>
                                        </div>
                                        <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($c['nama_cabang']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-muted small"><?= htmlspecialchars($c['alamat'] ?: '-') ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-bold" style="font-size: 0.72rem;">
                                        📦 <?= $c['total_aset'] ?> Aset
                                    </span>
                                </td>
                                <td>
                                    <span class="small text-muted"><?= date('d M Y', strtotime($c['created_at'])) ?></span>
                                </td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm btn-light border btn-edit me-1" 
                                            data-id="<?= $c['id'] ?>"
                                            data-nama="<?= htmlspecialchars($c['nama_cabang']) ?>"
                                            data-alamat="<?= htmlspecialchars($c['alamat']) ?>">
                                        <i class="bi bi-pencil text-warning"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus cabang ini? Semua data karyawan dan aset terkait di cabang ini akan terdampak.')">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button type="submit" name="hapus" class="btn btn-sm btn-light border text-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile view -->
            <div class="d-block d-md-none p-3" id="mobileBranchContainer">
                <?php foreach ($cabangs as $index => $c): ?>
                    <div class="card border p-3 mb-3 rounded-3 shadow-sm mobile-branch-card" data-search="<?= htmlspecialchars(strtolower($c['nama_cabang'] . ' ' . $c['alamat'])) ?>">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-muted small">#<?= $index + 1 ?></span>
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1.5 fw-bold" style="font-size: 0.7rem;">
                                📦 <?= $c['total_aset'] ?> Aset
                            </span>
                        </div>
                        <h6 class="fw-bold text-dark"><i class="bi bi-geo-alt-fill text-primary me-1.5"></i><?= htmlspecialchars($c['nama_cabang']) ?></h6>
                        <p class="text-muted small mt-2 mb-3"><?= htmlspecialchars($c['alamat'] ?: 'Alamat belum diatur.') ?></p>
                        
                        <hr class="my-2 opacity-25">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small" style="font-size: 0.72rem;">Dibuat: <?= date('d/m/Y', strtotime($c['created_at'])) ?></span>
                            <div>
                                <button class="btn btn-sm btn-light border btn-edit me-1" 
                                        data-id="<?= $c['id'] ?>"
                                        data-nama="<?= htmlspecialchars($c['nama_cabang']) ?>"
                                        data-alamat="<?= htmlspecialchars($c['alamat']) ?>">
                                    <i class="bi bi-pencil text-warning"></i> Edit
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus cabang ini?')">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="submit" name="hapus" class="btn btn-sm btn-light border text-danger">
                                        <i class="bi bi-trash"></i> Hapus
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 28px;">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-800 m-0"><i class="bi bi-plus-circle-fill text-primary me-2"></i> Tambah Cabang Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Nama Cabang</label>
                        <input type="text" name="nama_cabang" class="form-control bg-light border-0" placeholder="Contoh: Cabang Jakarta Pusat" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Alamat Kantor</label>
                        <textarea name="alamat" class="form-control bg-light border-0" rows="3" placeholder="Masukkan alamat lengkap kantor cabang..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 shadow-sm" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary px-4 shadow-sm">Simpan Cabang</button>
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
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-800 m-0"><i class="bi bi-pencil-fill text-primary me-2"></i> Edit Data Cabang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Nama Cabang</label>
                        <input type="text" name="nama_cabang" id="edit_nama" class="form-control bg-light border-0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Alamat Kantor</label>
                        <textarea name="alamat" id="edit_alamat" class="form-control bg-light border-0" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 shadow-sm" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                    <button type="submit" name="update" class="btn btn-primary px-4 shadow-sm">Simpan Perubahan</button>
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
        const alamat = this.getAttribute('data-alamat');

        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nama').value = nama;
        document.getElementById('edit_alamat').value = alamat;

        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    });
});

// Client search filter
function filterBranches() {
    const query = document.getElementById('branchSearch').value.toLowerCase();
    
    // Desktop rows
    const rows = document.querySelectorAll('.branch-row');
    rows.forEach(row => {
        const searchVal = row.getAttribute('data-search');
        if (searchVal.includes(query)) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });

    // Mobile cards
    const cards = document.querySelectorAll('.mobile-branch-card');
    cards.forEach(card => {
        const searchVal = card.getAttribute('data-search');
        if (searchVal.includes(query)) {
            card.style.display = "";
        } else {
            card.style.display = "none";
        }
    });
}

function resetFilters() {
    document.getElementById('branchSearch').value = "";
    filterBranches();
}
</script>

<style>
    .fw-800 { font-weight: 800; }
</style>
