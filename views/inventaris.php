<?php
require_once 'models/Asset.php';
require_once 'models/KategoriAset.php';
require_once 'models/Cabang.php';
require_once 'models/Divisi.php';
require_once 'models/Karyawan.php';
require_once 'models/ActivityLog.php';

$assetModel = new Asset($conn);
$kategoriModel = new KategoriAset($conn);
$cabangModel = new Cabang($conn);
$divisiModel = new Divisi($conn);
$karyawanModel = new Karyawan($conn);
$logModel = new ActivityLog($conn);

// Proses Hapus
if (isset($_POST['hapus'])) {
    $id = $_POST['id'];
    $currentAsset = $assetModel->getById($id);
    if ($assetModel->delete($id)) {
        if($currentAsset) $logModel->add($_SESSION['user_id'], 'Hapus Aset', "Menghapus aset: " . $currentAsset['nama_aset'] . " (" . $currentAsset['kode_aset'] . ")");
        header("Location: index.php?page=inventaris&status=deleted");
        exit();
    }
}

// Batasi akses cabang untuk teknisi
$id_cabang_filter = ($_SESSION['role'] === 'teknisi') ? $_SESSION['id_cabang'] : (isset($_GET['filter_cabang']) ? $_GET['filter_cabang'] : null);

// Pagination logic
$limit = 10;
$pageNumber = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($pageNumber - 1) * $limit;

$totalAssets = $assetModel->countAll($id_cabang_filter);
$totalPages = ceil($totalAssets / $limit);

$assets = $assetModel->getPaginated($limit, $offset, $id_cabang_filter);
$kategoris = $kategoriModel->getAll();
$cabangs = $cabangModel->getAll();
$divisis = $divisiModel->getAll();
$karyawans = $karyawanModel->getAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah'])) {
    $data = [
        'kode_aset' => $_POST['kode_aset'],
        'nama_aset' => $_POST['nama_aset'],
        'serial_number' => $_POST['serial_number'],
        'id_kategori' => $_POST['id_kategori'],
        'merk' => $_POST['merk'],
        'model' => $_POST['model'],
        'id_cabang' => $_POST['id_cabang'],
        'id_divisi' => $_POST['id_divisi'],
        'id_karyawan' => $_POST['id_karyawan'],
        'kondisi' => $_POST['kondisi']
    ];
    if ($assetModel->create($data)) {
        $logModel->add($_SESSION['user_id'], 'Tambah Aset', "Menambahkan aset baru: " . $data['nama_aset'] . " (" . $data['kode_aset'] . ")");
        header("Location: index.php?page=inventaris&status=success");
        exit();
    }
}

// Proses Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $id = $_POST['id'];
    $data = [
        'kode_aset' => $_POST['kode_aset'],
        'nama_aset' => $_POST['nama_aset'],
        'serial_number' => $_POST['serial_number'],
        'id_kategori' => $_POST['id_kategori'],
        'merk' => $_POST['merk'],
        'model' => $_POST['model'],
        'id_cabang' => $_POST['id_cabang'],
        'id_divisi' => $_POST['id_divisi'],
        'id_karyawan' => $_POST['id_karyawan'],
        'kondisi' => $_POST['kondisi']
    ];
    if ($assetModel->update($id, $data)) {
        $logModel->add($_SESSION['user_id'], 'Update Aset', "Memperbarui aset: " . $data['nama_aset'] . " (" . $data['kode_aset'] . ")");
        header("Location: index.php?page=inventaris&status=updated");
        exit();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 animate-fade-in">
    <div class="d-flex align-items-center">
        <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3 text-primary">
            <i class="bi bi-laptop fs-4"></i>
        </div>
        <div>
            <h4 class="fw-800 m-0">Asset Inventory</h4>
            <p class="text-muted small m-0">Manage and track company hardware</p>
        </div>
    </div>
    <div class="d-flex align-items-center">
        <form method="GET" action="index.php" class="me-3">
            <input type="hidden" name="page" value="inventaris">
            <div class="input-group input-group-sm shadow-sm" style="width: 250px;">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-filter text-primary"></i></span>
                <select name="filter_cabang" class="form-select border-start-0" onchange="this.form.submit()">
                    <option value="">All Branches</option>
                    <?php foreach ($cabangs as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($id_cabang_filter == $c['id']) ? 'selected' : '' ?>>
                            <?= $c['nama_cabang'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="bi bi-plus-lg me-2"></i> Add Asset
        </button>
    </div>
</div>

<?php if (isset($_GET['status'])): ?>
    <?php if ($_GET['status'] == 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4 border-0 shadow-sm rounded-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Aset berhasil didaftarkan!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($_GET['status'] == 'updated'): ?>
        <div class="alert alert-info alert-dismissible fade show mb-4 border-0 shadow-sm rounded-4" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i> Data aset berhasil diperbarui!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($_GET['status'] == 'deleted'): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4 border-0 shadow-sm rounded-4" role="alert">
            <i class="bi bi-trash-fill me-2"></i> Aset berhasil dihapus!
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
                        <th class="ps-4">Asset Code</th>
                        <th>Device Details</th>
                        <th>Location</th>
                        <th>Assignee</th>
                        <th>Condition</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($assets)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No assets found matching criteria.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($assets as $a): ?>
                    <tr>
                        <td class="ps-4">
                            <span class="badge fw-bold" style="font-size: 0.75rem; <?= get_branch_badge_style($a['id_cabang']) ?>">
                                <?= $a['kode_aset'] ?>
                            </span>
                        </td>
                        <td>
                            <div class="fw-bold"><?= $a['nama_aset'] ?></div>
                            <div class="small text-muted"><?= $a['nama_kategori'] ?> &bull; <?= $a['merk'] ?> <?= $a['model'] ?></div>
                        </td>
                        <td>
                            <div class="small fw-bold text-dark"><?= $a['nama_cabang'] ?></div>
                            <div class="small text-muted" style="font-size: 0.7rem;"><?= $a['nama_divisi'] ?></div>
                        </td>
                        <td>
                            <?php if ($a['id_karyawan']): ?>
                                <div class="d-flex align-items-center">
                                    <img src="https://ui-avatars.com/api/?name=<?= $a['nama_karyawan'] ?>&background=random&size=24" class="rounded-circle me-2">
                                    <span class="small fw-500"><?= $a['nama_karyawan'] ?></span>
                                </div>
                            <?php else: ?>
                                <span class="badge bg-light text-muted fw-normal">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $bg = 'success';
                            if ($a['kondisi'] == 'Rusak Ringan') $bg = 'warning';
                            if ($a['kondisi'] == 'Rusak Berat') $bg = 'danger';
                            ?>
                            <span class="badge bg-<?= $bg ?> bg-opacity-10 text-<?= $bg ?> rounded-pill px-3 py-2" style="font-size: 0.65rem;">
                                <i class="bi bi-circle-fill me-1" style="font-size: 0.4rem; vertical-align: middle;"></i> <?= strtoupper($a['kondisi']) ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm rounded-circle" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                    <li><a class="dropdown-item py-2 btn-edit" href="#" 
                                           data-id="<?= $a['id'] ?>"
                                           data-kode="<?= $a['kode_aset'] ?>"
                                           data-nama="<?= $a['nama_aset'] ?>"
                                           data-sn="<?= $a['serial_number'] ?>"
                                           data-kategori="<?= $a['id_kategori'] ?>"
                                           data-merk="<?= $a['merk'] ?>"
                                           data-model="<?= $a['model'] ?>"
                                           data-cabang="<?= $a['id_cabang'] ?>"
                                           data-divisi="<?= $a['id_divisi'] ?>"
                                           data-karyawan="<?= $a['id_karyawan'] ?>"
                                           data-kondisi="<?= $a['kondisi'] ?>">
                                        <i class="bi bi-pencil me-2 text-warning"></i> Edit Asset</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" onsubmit="return confirm('Hapus aset ini secara permanen?')">
                                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                            <button type="submit" name="hapus" class="dropdown-item py-2 text-danger">
                                                <i class="bi bi-trash me-2"></i> Delete
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= getPaginationControls($pageNumber, $totalPages, 'index.php?page=inventaris'.($id_cabang_filter ? '&filter_cabang='.$id_cabang_filter : '')) ?>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 28px;">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-800 m-0"><i class="bi bi-plus-circle-fill text-primary me-2"></i> New Asset Registration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-4">Please provide the complete technical specifications and assignment details for the new asset.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Asset Code</label>
                            <input type="text" name="kode_aset" class="form-control" placeholder="AST-001" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Serial Number</label>
                            <input type="text" name="serial_number" class="form-control" placeholder="SN12345678">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Asset Name</label>
                            <input type="text" name="nama_aset" class="form-control" placeholder="ThinkPad X1 Carbon" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Category</label>
                            <select name="id_kategori" class="form-select">
                                <?php foreach ($kategoris as $k): ?>
                                    <option value="<?= $k['id'] ?>"><?= $k['nama_kategori'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Brand</label>
                            <input type="text" name="merk" class="form-control" placeholder="Lenovo">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Model</label>
                            <input type="text" name="model" class="form-control" placeholder="2024 Gen 11">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Branch</label>
                            <select name="id_cabang" id="select_cabang" class="form-select" required>
                                <option value="">-- Select Branch --</option>
                                <?php foreach ($cabangs as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= $c['nama_cabang'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Division</label>
                            <select name="id_divisi" id="select_divisi" class="form-select">
                                <option value="">-- Select Division --</option>
                                <?php foreach ($divisis as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= $d['nama_divisi'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Assign To</label>
                            <select name="id_karyawan" id="select_karyawan" class="form-select">
                                <option value="">-- Unassigned --</option>
                                <?php foreach ($karyawans as $kr): ?>
                                    <option value="<?= $kr['id'] ?>" data-cabang="<?= $kr['id_cabang'] ?>"><?= $kr['nama_karyawan'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Condition</label>
                            <select name="kondisi" class="form-select">
                                <option value="Baik">Baik (Excellent)</option>
                                <option value="Rusak Ringan">Rusak Ringan (Minor Issue)</option>
                                <option value="Rusak Berat">Rusak Berat (Major Issue)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius: 12px;">Cancel</button>
                    <button type="submit" name="tambah" class="btn btn-primary px-4">Register Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 28px;">
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-800 m-0"><i class="bi bi-pencil-square text-warning me-2"></i> Update Asset Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Asset Code</label>
                            <input type="text" name="kode_aset" id="edit_kode" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Serial Number</label>
                            <input type="text" name="serial_number" id="edit_sn" class="form-control">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Asset Name</label>
                            <input type="text" name="nama_aset" id="edit_nama" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Category</label>
                            <select name="id_kategori" id="edit_kategori" class="form-select">
                                <?php foreach ($kategoris as $k): ?>
                                    <option value="<?= $k['id'] ?>"><?= $k['nama_kategori'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Brand</label>
                            <input type="text" name="merk" id="edit_merk" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Model</label>
                            <input type="text" name="model" id="edit_model" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Branch</label>
                            <select name="id_cabang" id="edit_select_cabang" class="form-select" required>
                                <option value="">-- Select Branch --</option>
                                <?php foreach ($cabangs as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= $c['nama_cabang'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Division</label>
                            <select name="id_divisi" id="edit_select_divisi" class="form-select">
                                <option value="">-- Select Division --</option>
                                <?php foreach ($divisis as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= $d['nama_divisi'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Assign To</label>
                            <select name="id_karyawan" id="edit_select_karyawan" class="form-select">
                                <option value="">-- Unassigned --</option>
                                <?php foreach ($karyawans as $kr): ?>
                                    <option value="<?= $kr['id'] ?>" data-cabang="<?= $kr['id_cabang'] ?>"><?= $kr['nama_karyawan'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Condition</label>
                            <select name="kondisi" id="edit_kondisi" class="form-select">
                                <option value="Baik">Baik (Excellent)</option>
                                <option value="Rusak Ringan">Rusak Ringan (Minor Issue)</option>
                                <option value="Rusak Berat">Rusak Berat (Major Issue)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius: 12px;">Cancel</button>
                    <button type="submit" name="update" class="btn btn-warning px-4">Update Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function filterKaryawan(cabangSelectId, karyawanSelectId) {
    const selectedCabangId = document.getElementById(cabangSelectId).value;
    const selectKaryawan = document.getElementById(karyawanSelectId);
    const options = selectKaryawan.querySelectorAll('option');

    options.forEach(option => {
        const cabangId = option.getAttribute('data-cabang');
        if (!cabangId) {
            option.style.display = 'block';
        } else {
            option.style.display = (cabangId === selectedCabangId) ? 'block' : 'none';
        }
    });
}

document.getElementById('select_cabang').addEventListener('change', function() {
    filterKaryawan('select_cabang', 'select_karyawan');
    document.getElementById('select_karyawan').value = "";
});

document.getElementById('edit_select_cabang').addEventListener('change', function() {
    filterKaryawan('edit_select_cabang', 'edit_select_karyawan');
});

document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const id = this.getAttribute('data-id');
        const kode = this.getAttribute('data-kode');
        const nama = this.getAttribute('data-nama');
        const sn = this.getAttribute('data-sn');
        const kategori = this.getAttribute('data-kategori');
        const merk = this.getAttribute('data-merk');
        const model = this.getAttribute('data-model');
        const cabang = this.getAttribute('data-cabang');
        const divisi = this.getAttribute('data-divisi');
        const karyawan = this.getAttribute('data-karyawan');
        const kondisi = this.getAttribute('data-kondisi');

        document.getElementById('edit_id').value = id;
        document.getElementById('edit_kode').value = kode;
        document.getElementById('edit_nama').value = nama;
        document.getElementById('edit_sn').value = sn;
        document.getElementById('edit_kategori').value = kategori;
        document.getElementById('edit_merk').value = merk;
        document.getElementById('edit_model').value = model;
        document.getElementById('edit_select_cabang').value = cabang;
        document.getElementById('edit_select_divisi').value = divisi;
        
        // Filter karyawan first
        filterKaryawan('edit_select_cabang', 'edit_select_karyawan');
        document.getElementById('edit_select_karyawan').value = karyawan || "";
        
        document.getElementById('edit_kondisi').value = kondisi;

        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    });
});
</script>

<style>
    .fw-800 { font-weight: 800; }
    .fw-500 { font-weight: 500; }
    .dropdown-item i { width: 20px; }
    .cursor-pointer { cursor: pointer; }
</style>
