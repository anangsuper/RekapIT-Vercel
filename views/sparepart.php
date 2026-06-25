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
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold">Manajemen Sparepart</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
        <i class="fas fa-plus me-2"></i> Tambah Sparepart
    </button>
</div>

<?php if (isset($_GET['status'])): ?>
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        Berhasil memproses data sparepart!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card p-4">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Sparepart</th>
                    <th>Stok</th>
                    <th>Satuan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($spareparts as $s): ?>
                <tr>
                    <td><span class="badge bg-light text-dark"><?= $s['kode_sparepart'] ?></span></td>
                    <td><strong><?= $s['nama_sparepart'] ?></strong></td>
                    <td>
                        <span class="fw-bold <?= ($s['stok'] < 5) ? 'text-danger' : '' ?>">
                            <?= $s['stok'] ?>
                        </span>
                    </td>
                    <td><?= $s['satuan'] ?></td>
                    <td>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <input type="hidden" name="jumlah" value="1">
                            <button type="submit" name="tambah_stok" class="btn btn-sm btn-light text-primary" title="Tambah 1 Stok"><i class="fas fa-plus-circle"></i></button>
                        </form>
                        <button class="btn btn-sm btn-light text-warning btn-edit" 
                                data-id="<?= $s['id'] ?>"
                                data-nama="<?= $s['nama_sparepart'] ?>"
                                data-kode="<?= $s['kode_sparepart'] ?>"
                                data-stok="<?= $s['stok'] ?>"
                                data-satuan="<?= $s['satuan'] ?>"
                                title="Edit"><i class="fas fa-edit"></i></button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus sparepart ini?')">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
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
                    <h5 class="modal-title">Tambah Sparepart Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kode Sparepart</label>
                        <input type="text" name="kode_sparepart" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Sparepart</label>
                        <input type="text" name="nama_sparepart" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stok Awal</label>
                            <input type="number" name="stok" class="form-control" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Satuan</label>
                            <input type="text" name="satuan" class="form-control" placeholder="Pcs/Unit/Botol">
                        </div>
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
                    <h5 class="modal-title">Edit Sparepart</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kode Sparepart</label>
                        <input type="text" name="kode_sparepart" id="edit_kode" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Sparepart</label>
                        <input type="text" name="nama_sparepart" id="edit_nama" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stok</label>
                            <input type="number" name="stok" id="edit_stok" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Satuan</label>
                            <input type="text" name="satuan" id="edit_satuan" class="form-control" placeholder="Pcs/Unit/Botol">
                        </div>
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
</script>
