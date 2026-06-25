<?php
require_once 'models/Repair.php';
require_once 'models/Asset.php';

$repairModel = new Repair($conn);
$assetModel = new Asset($conn);

$repairs = $repairModel->getAll();
$assets = $assetModel->getAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah'])) {
    $data = [
        'asset_id' => $_POST['asset_id'],
        'keluhan' => $_POST['keluhan']
    ];
    if ($repairModel->create($data)) {
        header("Location: index.php?page=perbaikan&status=success");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $id = $_POST['id'];
    $data = [
        'tindakan' => $_POST['tindakan'],
        'biaya' => $_POST['biaya'],
        'status' => $_POST['status'],
        'tanggal_selesai' => ($_POST['status'] == 'Selesai') ? date('Y-m-d') : null
    ];
    if ($repairModel->update($id, $data)) {
        header("Location: index.php?page=perbaikan&status=updated");
        exit();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 animate-fade-in">
    <div class="d-flex align-items-center">
        <div class="bg-warning bg-opacity-10 p-2 rounded-3 me-3 text-warning">
            <i class="bi bi-wrench-adjustable fs-4"></i>
        </div>
        <div>
            <h4 class="fw-800 m-0">Manajemen Perbaikan</h4>
            <p class="text-muted small m-0">Lacak kerusakan aset dan biaya perbaikan</p>
        </div>
    </div>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
        <i class="bi bi-plus-lg me-2"></i> Tiket Baru
    </button>
</div>

<div class="card border-0 shadow-sm animate-fade-in" style="animation-delay: 0.1s;">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Aset</th>
                        <th>Deskripsi Kerusakan</th>
                        <th>Status</th>
                        <th>Biaya</th>
                        <th>Tanggal Lapor</th>
                        <th class="text-end pe-4">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($repairs)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada tiket perbaikan ditemukan.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($repairs as $r): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-primary"><?= $r['kode_aset'] ?></div>
                            <div class="small text-muted"><?= $r['nama_aset'] ?></div>
                        </td>
                        <td>
                            <div class="small fw-500 text-dark"><?= $r['keluhan'] ?></div>
                            <?php if($r['tindakan']): ?>
                                <div class="mt-1 small text-muted fst-italic">Sol: <?= $r['tindakan'] ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $badge = 'warning';
                            $statusText = 'Dalam Proses';
                            if ($r['status'] == 'Selesai') { $badge = 'success'; $statusText = 'Selesai'; }
                            if ($r['status'] == 'Batal') { $badge = 'danger'; $statusText = 'Batal'; }
                            ?>
                            <span class="badge bg-<?= $badge ?> bg-opacity-10 text-<?= $badge ?> rounded-pill px-3 py-2" style="font-size: 0.65rem;">
                                <?= strtoupper($statusText) ?>
                            </span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark">Rp <?= number_format($r['biaya'], 0, ',', '.') ?></div>
                        </td>
                        <td>
                            <div class="small text-muted"><?= date('d/m/Y', strtotime($r['created_at'])) ?></div>
                        </td>
                        <td class="text-end pe-4">
                            <button class="btn btn-light btn-sm rounded-3 btn-edit px-3" 
                                    data-id="<?= $r['id'] ?>" 
                                    data-aset="<?= $r['nama_aset'] ?>" 
                                    data-keluhan="<?= $r['keluhan'] ?>"
                                    data-tindakan="<?= $r['tindakan'] ?>"
                                    data-biaya="<?= $r['biaya'] ?>"
                                    data-status="<?= $r['status'] ?>"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalUpdate">
                                <i class="bi bi-pencil-square me-1"></i> Perbarui
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Update -->
<div class="modal fade" id="modalUpdate" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 28px;">
            <form method="POST">
                <input type="hidden" name="id" id="update_id">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-800 m-0"><i class="bi bi-pencil-fill text-primary me-2"></i> Perbarui Detail Perbaikan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="bg-light p-3 rounded-4 mb-4 border border-white shadow-sm">
                        <div class="small text-muted mb-1">Informasi Aset:</div>
                        <div class="fw-bold" id="update_aset_text"></div>
                        <div class="small text-danger mt-2" id="update_keluhan_text"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Solusi / Tindakan</label>
                        <textarea name="tindakan" id="update_tindakan" class="form-control" rows="3" placeholder="Jelaskan perbaikan yang dilakukan..." required></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Biaya Perbaikan (Rp)</label>
                            <input type="number" name="biaya" id="update_biaya" class="form-control" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Status Baru</label>
                            <select name="status" id="update_status" class="form-select">
                                <option value="Proses">Dalam Proses</option>
                                <option value="Selesai">Selesai (Sukses)</option>
                                <option value="Batal">Batal (Tidak Bisa)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                    <button type="submit" name="update" class="btn btn-primary px-4">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 28px;">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-800 m-0"><i class="bi bi-plus-circle-fill text-primary me-2"></i> Buat Tiket Perbaikan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Pilih Aset Bermasalah</label>
                        <input type="text" id="searchInput" class="form-control shadow-sm mb-2" placeholder="Cari berdasarkan kode atau nama aset..." onkeyup="filterAssets()">
                        <select name="asset_id" id="assetSelect" class="form-select shadow-sm" required>
                            <?php foreach ($assets as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= $a['kode_aset'] ?> - <?= $a['nama_aset'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-bold">Keluhan Pengguna / Info Kerusakan</label>
                        <textarea name="keluhan" class="form-control shadow-sm" rows="4" placeholder="Jelaskan kerusakan sedetail mungkin..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary px-4">Buat Tiket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function filterAssets() {
    var input, filter, select, options, i, txtValue;
    input = document.getElementById("searchInput");
    filter = input.value.toUpperCase();
    select = document.getElementById("assetSelect");
    options = select.getElementsByTagName("option");
    for (i = 0; i < options.length; i++) {
        txtValue = options[i].textContent || options[i].innerText;
        if (txtValue.toUpperCase().indexOf(filter) > -1) {
            options[i].style.display = "";
        } else {
            options[i].style.display = "none";
        }
    }
}

document.querySelectorAll('.btn-edit').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('update_id').value = this.getAttribute('data-id');
        document.getElementById('update_aset_text').innerText = this.getAttribute('data-aset');
        document.getElementById('update_keluhan_text').innerText = "Masalah: " + this.getAttribute('data-keluhan');
        document.getElementById('update_tindakan').value = this.getAttribute('data-tindakan') || '';
        document.getElementById('update_biaya').value = this.getAttribute('data-biaya') || 0;
        document.getElementById('update_status').value = this.getAttribute('data-status');
    });
});
</script>

<style>
    .fw-800 { font-weight: 800; }
    .fw-500 { font-weight: 500; }
</style>
