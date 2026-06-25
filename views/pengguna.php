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
    checkAccess('admin'); // Memastikan hanya admin yang bisa menambah user
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

$users = $userModel->getAll();
$cabangs = $cabangModel->getAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold">Manajemen Pengguna</h4>
    <?php if (hasRole('admin')): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">Tambah Pengguna</button>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
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
                    <td class="ps-4"><?= $u['nama'] ?></td>
                    <td><?= $u['username'] ?></td>
                    <td><span class="badge bg-<?= $u['role'] == 'admin' ? 'danger' : 'info' ?>"><?= $u['role'] ?></span></td>
                    <td><?= $u['nama_cabang'] ?: 'Semua Cabang' ?></td>
                    <td class="text-end pe-4">
                        <form method="POST" onsubmit="return confirm('Hapus user ini?')">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button type="submit" name="hapus" class="btn btn-sm btn-danger">Hapus</button>
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
        <form method="POST" class="modal-content">
            <div class="modal-header"><h5>Tambah Pengguna Baru</h5></div>
            <div class="modal-body">
                <div class="mb-3"><label>Nama</label><input type="text" name="nama" class="form-control" required></div>
                <div class="mb-3"><label>Username</label><input type="text" name="username" class="form-control" required></div>
                <div class="mb-3"><label>Password</label><input type="password" name="password" class="form-control" required></div>
                <div class="mb-3">
                    <label>Role</label>
                    <select name="role" class="form-select" onchange="toggleCabang(this)">
                        <option value="teknisi">Teknisi</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="mb-3" id="fieldCabang">
                    <label>Cabang (Khusus Teknisi)</label>
                    <select name="id_cabang" class="form-select">
                        <option value="">Semua Cabang</option>
                        <?php foreach ($cabangs as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= $c['nama_cabang'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="tambah" class="btn btn-primary">Simpan</button>
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
</script>
