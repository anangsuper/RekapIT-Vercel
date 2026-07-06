<?php
require_once 'models/KategoriAset.php';
$kategoriModel = new KategoriAset($conn);

// Proses Hapus
if (isset($_POST['hapus'])) {
    $id = $_POST['id'];
    if ($kategoriModel->delete($id)) {
        header("Location: index.php?page=kategori&status=deleted");
        exit();
    }
}

// Proses Tambah
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah'])) {
    $data = ['nama_kategori' => $_POST['nama_kategori']];
    if ($kategoriModel->create($data)) {
        header("Location: index.php?page=kategori&status=success");
        exit();
    }
}

// Proses Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $id = $_POST['id'];
    $data = ['nama_kategori' => $_POST['nama_kategori']];
    if ($kategoriModel->update($id, $data)) {
        header("Location: index.php?page=kategori&status=updated");
        exit();
    }
}

$kategoris = $kategoriModel->getAll();
?>

<!-- Header Section -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-800 m-0 text-dark">Kategori Aset</h4>
        <p class="text-muted small m-0">Kelola kelompok dan klasifikasi perangkat keras IT Anda.</p>
    </div>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah" style="border-radius: 14px;">
        <i class="bi bi-plus-circle me-2"></i> Tambah Kategori
    </button>
</div>

<!-- Notification Alert -->
<?php if (isset($_GET['status'])): 
    $status = $_GET['status'];
    $msg = "Berhasil memproses data kategori!";
    if ($status === 'success') $msg = "Kategori baru berhasil ditambahkan!";
    if ($status === 'updated') $msg = "Perubahan kategori berhasil disimpan!";
    if ($status === 'deleted') $msg = "Kategori berhasil dihapus!";
?>
    <div class="alert alert-success border-0 shadow-sm rounded-4 p-3 mb-4 d-flex align-items-center justify-content-between" role="alert" style="background: rgba(16, 185, 129, 0.1); color: #065f46;">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <span class="small fw-semibold"><?= htmlspecialchars($msg) ?></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Main Table Card -->
<div class="card p-4 border-0 shadow-sm" style="border-radius: 20px;">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 80px;">No</th>
                    <th>Nama Kategori</th>
                    <th>Dibuat Pada</th>
                    <th class="text-end" style="width: 150px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($kategoris)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-5 text-muted">
                            <div class="bg-light bg-opacity-50 text-secondary rounded-circle d-inline-flex p-3 mb-3">
                                <i class="bi bi-tag fs-3"></i>
                            </div>
                            <p class="small fw-semibold mb-0">Belum ada kategori aset.</p>
                            <small class="text-muted">Klik tombol "Tambah Kategori" untuk menambahkan.</small>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($kategoris as $index => $k): ?>
                    <tr>
                        <td><span class="fw-bold text-muted"><?= $index + 1 ?></span></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 p-2.5 rounded-3 text-primary d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="bi bi-tag-fill fs-5"></i>
                                </div>
                                <strong class="text-dark" style="font-size: 0.95rem;"><?= htmlspecialchars($k['nama_kategori']) ?></strong>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center text-muted small">
                                <i class="bi bi-calendar3 me-2 text-primary opacity-70"></i>
                                <span><?= date('d M Y', strtotime($k['created_at'])) ?></span>
                            </div>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-light text-primary btn-edit p-2 rounded-3 me-1 shadow-sm" 
                                    data-id="<?= $k['id'] ?>"
                                    data-nama="<?= htmlspecialchars($k['nama_kategori']) ?>"
                                    title="Edit" style="border: 1px solid rgba(226, 232, 240, 0.8);">
                                <i class="bi bi-pencil-square fs-6"></i>
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kategori ini?')">
                                <input type="hidden" name="id" value="<?= $k['id'] ?>">
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

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <form method="POST">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-800 m-0 text-dark"><i class="bi bi-tag-fill text-primary me-2"></i>Tambah Kategori Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label fw-600 text-dark">Nama Kategori</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted" style="border-radius: 12px 0 0 12px;"><i class="bi bi-tag"></i></span>
                            <input type="text" name="nama_kategori" class="form-control border-start-0" placeholder="Contoh: Laptop, Router, Switch" required style="border-radius: 0 12px 12px 0;">
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
        <div class="modal-content border-0">
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-800 m-0 text-dark"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label fw-600 text-dark">Nama Kategori</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted" style="border-radius: 12px 0 0 12px;"><i class="bi bi-tag"></i></span>
                            <input type="text" name="nama_kategori" id="edit_nama" class="form-control border-start-0" required style="border-radius: 0 12px 12px 0;">
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
document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const nama = this.getAttribute('data-nama');

        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nama').value = nama;

        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    });
});
</script>
