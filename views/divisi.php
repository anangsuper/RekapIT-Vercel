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

$divisis = $divisiModel->getAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold">Manajemen Divisi</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
        <i class="fas fa-plus me-2"></i> Tambah Divisi
    </button>
</div>

<?php if (isset($_GET['status'])): ?>
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        Berhasil memproses data divisi!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card p-4">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Divisi</th>
                    <th>Dibuat Pada</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($divisis as $index => $d): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><strong><?= $d['nama_divisi'] ?></strong></td>
                    <td><?= date('d/m/Y', strtotime($d['created_at'])) ?></td>
                    <td>
                        <button class="btn btn-sm btn-light text-primary btn-edit" 
                                data-id="<?= $d['id'] ?>"
                                data-nama="<?= $d['nama_divisi'] ?>"
                                title="Edit"><i class="fas fa-edit"></i></button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus divisi ini?')">
                            <input type="hidden" name="id" value="<?= $d['id'] ?>">
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

<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Divisi Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Divisi</label>
                        <input type="text" name="nama_divisi" class="form-control" required>
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

<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Divisi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Divisi</label>
                        <input type="text" name="nama_divisi" id="edit_nama" class="form-control" required>
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

        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nama').value = nama;

        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    });
});
</script>
