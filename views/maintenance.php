<?php
require_once 'models/Maintenance.php';
require_once 'models/Asset.php';
require_once 'models/Cabang.php';

$maintenanceModel = new Maintenance($conn);
$assetModel = new Asset($conn);
$cabangModel = new Cabang($conn);

$sub = $_GET['sub'] ?? 'history';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['tambah']) && $sub === 'history') {
        $data = [
            'asset_id' => $_POST['asset_id'],
            'tanggal' => $_POST['tanggal'],
            'teknisi' => $_POST['teknisi'],
            'temuan' => $_POST['temuan'],
            'tindakan' => $_POST['tindakan'],
            'rekomendasi' => $_POST['rekomendasi'],
            'status' => $_POST['status'], // Added
            'id_detail_jadwal' => null
        ];
        if ($maintenanceModel->create($data)) {
            header("Location: index.php?page=maintenance&status=success");
            exit();
        }
    } elseif (isset($_POST['proses_massal_final']) && $sub === 'massal') {
        // Final Processing
        $asset_ids = $_POST['asset_ids'] ?? [];
        $conn->beginTransaction();
        try {
            foreach ($asset_ids as $id) {
                $data = [
                    'asset_id' => $id,
                    'tanggal' => $_POST['tanggal'][$id],
                    'teknisi' => $_POST['teknisi'][$id],
                    'temuan' => $_POST['temuan'][$id],
                    'tindakan' => $_POST['tindakan'][$id],
                    'rekomendasi' => $_POST['rekomendasi'][$id],
                    'status' => $_POST['status'][$id],
                    'id_detail_jadwal' => null
                ];
                $maintenanceModel->create($data);
            }
            $conn->commit();
            header("Location: index.php?page=maintenance&sub=history&status=mass_success");
            exit();
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Gagal memproses maintenance massal: " . $e->getMessage();
        }
    }
}

// Prepare data
$maintenances = $maintenanceModel->getAll();
$assetsAvailable = $assetModel->getAssetsAvailableForMaintenance(date('m'), date('Y'));
$cabangs = $cabangModel->getAll();
$id_cabang = $_GET['id_cabang'] ?? '';
$assets = $id_cabang ? $assetModel->getAll($id_cabang) : [];
?>

<div class="d-flex justify-content-between align-items-center mb-4 animate-fade-in">
    <div>
        <h4 class="fw-800 m-0">Maintenance</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php?page=maintenance&sub=history" class="text-decoration-none <?= $sub === 'history' ? 'fw-bold text-primary' : 'text-muted' ?>">History</a></li>
                <li class="breadcrumb-item"><a href="index.php?page=maintenance&sub=massal" class="text-decoration-none <?= $sub === 'massal' ? 'fw-bold text-primary' : 'text-muted' ?>">Massal</a></li>
            </ol>
        </nav>
    </div>
    <?php if ($sub === 'history'): ?>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
        <i class="bi bi-plus-lg me-2"></i> Log Check
    </button>
    <?php endif; ?>
</div>

<?php if ($sub === 'history'): ?>
<!-- History Content -->
<div class="card border-0 shadow-sm animate-fade-in">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Tanggal</th>
                        <th>Aset</th>
                        <th>Teknisi</th>
                        <th>Temuan / Kondisi</th>
                        <th>Tindakan</th>
                        <th class="text-end pe-4">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($maintenances)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada riwayat maintenance.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($maintenances as $m): 
                        // Logic untuk menentukan badge kondisi
                        $is_bad = (!empty($m['temuan']) && strtolower($m['temuan']) !== 'baik' && strtolower($m['temuan']) !== 'normal');
                    ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?= date('d M Y', strtotime($m['tanggal'])) ?></div>
                        </td>
                        <td>
                            <div class="fw-bold text-primary"><?= $m['kode_aset'] ?></div>
                            <div class="small text-muted"><?= $m['nama_aset'] ?></div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill"><?= $m['teknisi'] ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="badge <?= $is_bad ? 'bg-danger' : 'bg-success' ?> bg-opacity-10 text-<?= $is_bad ? 'danger' : 'success' ?> rounded-pill">
                                <?= $m['temuan'] ?: 'Normal' ?>
                            </span>
                        </td>
                        <td>
                            <div class="text-truncate" style="max-width: 200px;" title="<?= $m['tindakan'] ?>">
                                <?= $m['tindakan'] ?: '-' ?>
                            </div>
                        </td>
                        <td class="text-end pe-4">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#detail<?= $m['id'] ?>">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    </tr>
                    <tr class="collapse" id="detail<?= $m['id'] ?>">
                        <td colspan="6" class="bg-light p-3">
                            <div class="row small">
                                <div class="col-md-4"><strong>Tindakan:</strong><br><?= $m['tindakan'] ?></div>
                                <div class="col-md-4"><strong>Rekomendasi:</strong><br><?= $m['rekomendasi'] ?: '-' ?></div>
                                <div class="col-md-4 text-muted"><em>Dicatat pada: <?= $m['created_at'] ?></em></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Massal Content -->
<?php 
$stage = $_POST['stage'] ?? 'select';
$selected_ids = $_POST['asset_ids'] ?? [];
?>

<form method="GET" action="index.php" class="card p-4 mb-4">
    <input type="hidden" name="page" value="maintenance">
    <input type="hidden" name="sub" value="massal">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-bold">Pilih Cabang untuk Maintenance</label>
            <div class="input-group">
                <select name="id_cabang" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Pilih Cabang --</option>
                    <?php foreach ($cabangs as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($id_cabang == $c['id']) ? 'selected' : '' ?>><?= $c['nama_cabang'] ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Muat Aset</button>
            </div>
        </div>
    </div>
</form>

<form method="POST">
    <?php if ($id_cabang && $stage === 'select'): ?>
        <input type="hidden" name="stage" value="select">
        <div class="card p-4">
            <h5 class="fw-bold mb-3">Daftar Komputer / Aset</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="40"><input type="checkbox" id="checkAll" class="form-check-input"></th>
                            <th>Kode Aset</th>
                            <th>Nama Aset</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assets as $a): ?>
                        <tr>
                            <td><input type="checkbox" name="asset_ids[]" value="<?= $a['id'] ?>" class="form-check-input asset-checkbox"></td>
                            <td><?= $a['kode_aset'] ?></td>
                            <td><?= $a['nama_aset'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" name="stage" value="review" class="btn btn-primary" id="btnNext" disabled>Lanjut ke Edit Detail</button>
        </div>
    <?php elseif ($stage === 'review'): ?>
        <input type="hidden" name="stage" value="review">
        <?php foreach ($selected_ids as $id): ?>
            <input type="hidden" name="asset_ids[]" value="<?= $id ?>">
        <?php endforeach; ?>
        <h5 class="fw-bold mb-4">Edit Detail Maintenance Aset Terpilih</h5>
        
        <!-- Apply to All -->
        <div class="card p-3 mb-4 bg-light border-0">
            <h6 class="fw-bold mb-3">Terapkan ke Semua Aset:</h6>
            <div class="row g-3">
                <div class="col-md-2"><input type="date" id="all_tanggal" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                <div class="col-md-2"><input type="text" id="all_teknisi" class="form-control" placeholder="Nama Teknisi"></div>
                <div class="col-md-2"><select id="all_status" class="form-select"><option value="Baik">Baik</option><option value="Perlu Perbaikan">Perlu Perbaikan</option><option value="Rusak">Rusak</option></select></div>
                <div class="col-md-2"><input type="text" id="all_temuan" class="form-control" placeholder="Temuan"></div>
                <div class="col-md-2"><input type="text" id="all_tindakan" class="form-control" placeholder="Tindakan"></div>
                <div class="col-md-2"><input type="text" id="all_rekomendasi" class="form-control" placeholder="Rekomendasi"></div>
                <div class="col-md-12"><button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="applyToAll()">Terapkan ke Semua</button></div>
            </div>
        </div>

        <?php foreach ($selected_ids as $id): 
            $a = $assetModel->getById($id); ?>
            <div class="card p-4 mb-3 border-0 shadow-sm asset-row">
                <h6 class="fw-bold text-primary mb-3"><?= $a['nama_aset'] ?> (<?= $a['kode_aset'] ?>)</h6>
                <input type="hidden" name="asset_ids[]" value="<?= $id ?>">
                <div class="row g-3">
                    <div class="col-md-2"><input type="date" name="tanggal[<?= $id ?>]" class="form-control row-tanggal" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="col-md-2"><input type="text" name="teknisi[<?= $id ?>]" class="form-control row-teknisi" placeholder="Teknisi" required></div>
                    <div class="col-md-2">
                        <select name="status[<?= $id ?>]" class="form-select row-status">
                            <option value="Baik">Baik</option>
                            <option value="Perlu Perbaikan">Perlu Perbaikan</option>
                            <option value="Rusak">Rusak</option>
                        </select>
                    </div>
                    <div class="col-md-3"><input type="text" name="temuan[<?= $id ?>]" class="form-control row-temuan" placeholder="Temuan"></div>
                    <div class="col-md-3"><input type="text" name="tindakan[<?= $id ?>]" class="form-control row-tindakan" placeholder="Tindakan"></div>
                    <div class="col-md-2"><input type="text" name="rekomendasi[<?= $id ?>]" class="form-control row-rekomendasi" placeholder="Rekomendasi"></div>
                </div>
            </div>
        <?php endforeach; ?>
        <button type="submit" name="proses_massal_final" class="btn btn-success btn-lg px-5">Simpan Semua</button>

        <script>
            function applyToAll() {
                const fields = ['tanggal', 'teknisi', 'status', 'temuan', 'tindakan', 'rekomendasi'];
                fields.forEach(field => {
                    const allVal = document.getElementById('all_' + field).value;
                    if (allVal) {
                        document.querySelectorAll('.row-' + field).forEach(el => el.value = allVal);
                    }
                });
            }
        </script>
    <?php endif; ?>


</form>

<script>
    const checkAll = document.getElementById('checkAll');
    const checkboxes = document.querySelectorAll('.asset-checkbox');
    const btnNext = document.getElementById('btnNext');

    if (checkAll) {
        checkAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            btnNext.disabled = !this.checked;
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            btnNext.disabled = document.querySelectorAll('.asset-checkbox:checked').length === 0;
        });
    });
</script>
<?php endif; ?>

