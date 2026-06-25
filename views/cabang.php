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

$cabangs = $cabangModel->getAll();
?>

<div class="card p-4 lux-card" style="border-radius: 20px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold">Manajemen Cabang</h4>
        <button class="btn btn-primary" style="background: linear-gradient(135deg, #6366f1, #a855f7); border: none; padding: 10px 20px; border-radius: 12px;" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="fas fa-plus me-2"></i> Tambah Cabang
        </button>
    </div>

<?php if (isset($_GET['status'])): ?>
    <div class="alert alert-success alert-dismissible fade show mb-4" style="border-radius: 15px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #065f46;" role="alert">
        Berhasil memproses data cabang!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card p-4">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Cabang</th>
                    <th>Alamat</th>
                    <th>Dibuat Pada</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cabangs as $index => $c): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><strong><?= $c['nama_cabang'] ?></strong></td>
                    <td><?= $c['alamat'] ?></td>
                    <td><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                    <td>
                        <button class="btn btn-sm btn-light text-primary btn-edit" 
                                data-id="<?= $c['id'] ?>"
                                data-nama="<?= $c['nama_cabang'] ?>"
                                data-alamat="<?= $c['alamat'] ?>"
                                title="Edit"><i class="fas fa-edit"></i></button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus cabang ini?')">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit" name="hapus" class="btn btn-sm btn-light text-danger" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Cabang Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Cabang</label>
                        <input type="text" name="nama_cabang" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea name="alamat" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Cabang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Cabang</label>
                        <input type="text" name="nama_cabang" id="edit_nama" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea name="alamat" id="edit_alamat" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="update" class="btn btn-primary">Simpan Perubahan</button>
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
        const alamat = this.getAttribute('data-alamat');

        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nama').value = nama;
        document.getElementById('edit_alamat').value = alamat;

        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    });
});
</script>
