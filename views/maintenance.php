<?php
require_once 'models/Maintenance.php';
require_once 'models/Asset.php';
require_once 'models/Cabang.php';

$maintenanceModel = new Maintenance($conn);
$assetModel = new Asset($conn);
$cabangModel = new Cabang($conn);

$sub = $_GET['sub'] ?? 'history';

// Handle form submissions based on sub-page
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['tambah']) && $sub === 'history') {
        $data = [
            'asset_id' => $_POST['asset_id'],
            'tanggal' => $_POST['tanggal'],
            'teknisi' => $_POST['teknisi'],
            'temuan' => $_POST['temuan'],
            'tindakan' => $_POST['tindakan'],
            'rekomendasi' => $_POST['rekomendasi'],
            'id_detail_jadwal' => null
        ];
        if ($maintenanceModel->create($data)) {
            header("Location: index.php?page=maintenance&status=success");
            exit();
        }
    } elseif (isset($_POST['proses_massal']) && $sub === 'massal') {
        $selected_assets = $_POST['asset_ids'] ?? [];
        if (!empty($selected_assets)) {
            $commonData = [
                'tanggal' => $_POST['tanggal'],
                'teknisi' => $_POST['teknisi'],
                'temuan' => $_POST['temuan'],
                'tindakan' => $_POST['tindakan'],
                'rekomendasi' => $_POST['rekomendasi']
            ];
            if ($maintenanceModel->createBulk($selected_assets, $commonData)) {
                header("Location: index.php?page=maintenance&sub=history&status=mass_success");
                exit();
            }
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
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Date</th>
                        <th>Asset Information</th>
                        <th>Technician</th>
                        <th>Maintenance Details</th>
                        <th class="text-end pe-4">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($maintenances)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No maintenance records found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($maintenances as $m): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold"><?= date('d M Y', strtotime($m['tanggal'])) ?></div>
                            <div class="small text-muted" style="font-size: 0.65rem;">Logged: <?= date('H:i', strtotime($m['created_at'])) ?></div>
                        </td>
                        <td>
                            <div class="fw-bold text-primary"><?= $m['kode_aset'] ?></div>
                            <div class="small text-muted"><?= $m['nama_aset'] ?></div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-light rounded-circle p-1 me-2"><i class="bi bi-person text-secondary"></i></div>
                                <span class="small fw-500"><?= $m['teknisi'] ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="small fw-bold">Findings:</div>
                            <div class="small text-muted text-truncate" style="max-width: 250px;"><?= $m['temuan'] ?: 'No issues noted.' ?></div>
                        </td>
                        <td class="text-end pe-4">
                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2" style="font-size: 0.65rem;">
                                COMPLETED
                            </span>
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
<div class="card p-4 mb-4">
    <form method="GET" action="index.php" class="row g-3">
        <input type="hidden" name="page" value="maintenance">
        <input type="hidden" name="sub" value="massal">
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
    </form>
</div>

<?php if ($id_cabang): ?>
<form method="POST">
    <div class="row">
        <div class="col-md-8">
            <div class="card p-4">
                <h5 class="fw-bold mb-3">Daftar Komputer / Aset</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="40"><input type="checkbox" id="checkAll" class="form-check-input"></th>
                                <th>Kode Aset</th>
                                <th>Nama Aset</th>
                                <th>Kondisi</th>
                                <th>Pemegang</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($assets)): ?>
                                <tr><td colspan="5" class="text-center">Tidak ada aset di cabang ini.</td></tr>
                            <?php else: ?>
                                <?php foreach ($assets as $a): ?>
                                <tr>
                                    <td><input type="checkbox" name="asset_ids[]" value="<?= $a['id'] ?>" class="form-check-input asset-checkbox"></td>
                                    <td><span class="badge bg-light text-dark"><?= $a['kode_aset'] ?></span></td>
                                    <td><strong><?= $a['nama_aset'] ?></strong></td>
                                    <td><?= $a['kondisi'] ?></td>
                                    <td><?= $a['nama_karyawan'] ?? '-' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-4 sticky-top" style="top: 100px; z-index: 1;">
                <h5 class="fw-bold mb-3">Detail Maintenance</h5>
                <div class="mb-3">
                    <label class="form-label">Tanggal</label>
                    <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Teknisi</label>
                    <input type="text" name="teknisi" class="form-control" placeholder="Nama Teknisi" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Temuan</label>
                    <textarea name="temuan" class="form-control" rows="2" placeholder="Sama untuk semua aset"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tindakan</label>
                    <textarea name="tindakan" class="form-control" rows="2" placeholder="Sama untuk semua aset"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Rekomendasi</label>
                    <textarea name="rekomendasi" class="form-control" rows="2"></textarea>
                </div>
                <hr>
                <div id="selection-count" class="mb-3 small fw-bold text-primary">0 Aset Terpilih</div>
                <button type="submit" name="proses_massal" class="btn btn-success w-100 py-2 fw-bold" id="btnSubmit" disabled>
                    <i class="bi bi-save me-2"></i> PROSES MAINTENANCE
                </button>
            </div>
        </div>
    </div>
</form>

<script>
    const checkAll = document.getElementById('checkAll');
    const checkboxes = document.querySelectorAll('.asset-checkbox');
    const countLabel = document.getElementById('selection-count');
    const btnSubmit = document.getElementById('btnSubmit');

    function updateCount() {
        const checkedCount = document.querySelectorAll('.asset-checkbox:checked').length;
        countLabel.innerText = checkedCount + " Aset Terpilih";
        btnSubmit.disabled = checkedCount === 0;
    }

    if (checkAll) {
        checkAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateCount();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateCount);
    });
</script>
<?php endif; ?>
<?php endif; ?>
