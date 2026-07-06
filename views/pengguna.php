<?php
require_once 'models/User.php';
require_once 'models/Cabang.php';
require_once 'models/ActivityLog.php';

checkAccess('admin');

$userModel = new User($conn);
$cabangModel = new Cabang($conn);
$logModel = new ActivityLog($conn);

if (isset($_POST['hapus'])) {
    if ($userModel->delete($_POST['id'])) {
        $logModel->add($_SESSION['user_id'], 'Hapus User', "Menghapus user ID: " . $_POST['id']);
        header("Location: index.php?page=pengguna&status=deleted");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah'])) {
    checkAccess('admin');
    $data = [
        'nama' => $_POST['nama'],
        'username' => $_POST['username'],
        'password' => $_POST['password'],
        'role' => $_POST['role'],
        'id_cabang' => !empty($_POST['id_cabang']) ? $_POST['id_cabang'] : null
    ];
    if ($userModel->create($data)) {
        $logModel->add($_SESSION['user_id'], 'Tambah User', "Menambahkan user baru: " . $data['username']);
        header("Location: index.php?page=pengguna&status=success");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    checkAccess('admin');
    $id = $_POST['id'];
    $data = [
        'nama' => $_POST['nama'],
        'username' => $_POST['username'],
        'role' => $_POST['role'],
        'id_cabang' => !empty($_POST['id_cabang']) ? $_POST['id_cabang'] : null
    ];
    if (!empty($_POST['password'])) {
        $data['password'] = $_POST['password'];
    }
    if ($userModel->update($id, $data)) {
        $logModel->add($_SESSION['user_id'], 'Update User', "Memperbarui user ID: " . $id);
        header("Location: index.php?page=pengguna&status=updated");
        exit();
    }
}

$users = $userModel->getAll();
$cabangs = $cabangModel->getAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4 animate-fade-in">
    <div class="d-flex align-items-center">
        <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3 text-primary">
            <i class="bi bi-people fs-4"></i>
        </div>
        <div>
            <h4 class="fw-800 m-0">Manajemen Pengguna</h4>
            <p class="text-muted small m-0">Kelola akun administrator dan teknisi lapangan</p>
        </div>
    </div>
    <?php if (hasRole('admin')): ?>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="bi bi-person-plus me-2"></i> Tambah Pengguna
        </button>
    <?php endif; ?>
</div>

<?php if (isset($_GET['status'])): ?>
    <?php if ($_GET['status'] == 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4 border-0 shadow-sm rounded-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Pengguna baru berhasil ditambahkan!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($_GET['status'] == 'updated'): ?>
        <div class="alert alert-info alert-dismissible fade show mb-4 border-0 shadow-sm rounded-4" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i> Data pengguna berhasil diperbarui!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($_GET['status'] == 'deleted'): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4 border-0 shadow-sm rounded-4" role="alert">
            <i class="bi bi-trash-fill me-2"></i> Pengguna berhasil dihapus dari sistem!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="card border-0 shadow-sm animate-fade-in" style="animation-delay: 0.1s;">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Nama</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Cabang Ditugaskan</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($u['nama']) ?></td>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td>
                            <?php $badgeRole = $u['role'] == 'admin' ? 'danger' : 'info'; ?>
                            <span class="badge bg-<?= $badgeRole ?> bg-opacity-10 text-<?= $badgeRole ?> rounded-pill px-3 py-1 text-uppercase" style="font-size: 0.65rem; font-weight: 700;">
                                <?= $u['role'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['role'] == 'admin'): ?>
                                <span class="text-muted small italic">Akses Global</span>
                            <?php else: ?>
                                <span class="fw-500"><?= htmlspecialchars($u['nama_cabang'] ?: 'Semua Cabang') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <button class="btn btn-sm btn-light text-primary btn-edit me-1" 
                                    data-id="<?= $u['id'] ?>"
                                    data-nama="<?= htmlspecialchars($u['nama']) ?>"
                                    data-username="<?= htmlspecialchars($u['username']) ?>"
                                    data-role="<?= $u['role'] ?>"
                                    data-cabang="<?= $u['id_cabang'] ?>"
                                    title="Edit"><i class="fas fa-edit"></i> Edit</button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus user ini?')">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" name="hapus" class="btn btn-sm btn-light text-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow-lg" style="border-radius: 28px;">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-800 m-0"><i class="bi bi-person-plus-fill text-primary me-2"></i> Tambah Pengguna Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Nama Lengkap</label>
                    <input type="text" name="nama" class="form-control" placeholder="Nama lengkap..." required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Username..." required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Password..." required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Role</label>
                    <select name="role" class="form-select" onchange="toggleCabang(this)">
                        <option value="teknisi">Teknisi</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="mb-3" id="fieldCabang">
                    <label class="form-label small fw-bold">Cabang Ditugaskan (Khusus Teknisi)</label>
                    <select name="id_cabang" class="form-select">
                        <option value="">Semua Cabang</option>
                        <?php foreach ($cabangs as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= $c['nama_cabang'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 p-4">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                <button type="submit" name="tambah" class="btn btn-primary px-4">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow-lg" style="border-radius: 28px;">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-800 m-0"><i class="bi bi-pencil-fill text-primary me-2"></i> Edit Pengguna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Nama Lengkap</label>
                    <input type="text" name="nama" id="edit_nama" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Username</label>
                    <input type="text" name="username" id="edit_username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Password Baru (Kosongkan jika tidak ingin diubah)</label>
                    <input type="password" name="password" id="edit_password" class="form-control" placeholder="Biarkan kosong jika tidak diubah...">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Role</label>
                    <select name="role" id="edit_role" class="form-select" onchange="toggleCabangEdit(this)">
                        <option value="teknisi">Teknisi</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="mb-3" id="fieldCabangEdit">
                    <label class="form-label small fw-bold">Cabang Ditugaskan (Khusus Teknisi)</label>
                    <select name="id_cabang" id="edit_id_cabang" class="form-select">
                        <option value="">Semua Cabang</option>
                        <?php foreach ($cabangs as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= $c['nama_cabang'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 p-4">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                <button type="submit" name="update" class="btn btn-primary px-4">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleCabang(select) {
    const field = document.getElementById('fieldCabang');
    if (select.value === 'admin') {
        field.style.display = 'none';
    } else {
        field.style.display = 'block';
    }
}

function toggleCabangEdit(select) {
    const field = document.getElementById('fieldCabangEdit');
    if (select.value === 'admin') {
        field.style.display = 'none';
    } else {
        field.style.display = 'block';
    }
}

document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const nama = this.getAttribute('data-nama');
        const username = this.getAttribute('data-username');
        const role = this.getAttribute('data-role');
        const cabang = this.getAttribute('data-cabang');

        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nama').value = nama;
        document.getElementById('edit_username').value = username;
        document.getElementById('edit_role').value = role;
        document.getElementById('edit_password').value = '';

        const fieldCabang = document.getElementById('fieldCabangEdit');
        const selectCabang = document.getElementById('edit_id_cabang');
        
        selectCabang.value = cabang || '';
        
        if (role === 'admin') {
            fieldCabang.style.display = 'none';
        } else {
            fieldCabang.style.display = 'block';
        }

        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    });
});
</script>
