<?php
require_once 'models/Asset.php';
require_once 'models/Audit.php';
require_once 'models/ActivityLog.php';

$assetModel = new Asset($conn);
$auditModel = new Audit($conn);
$logModel = new ActivityLog($conn);

// Proses Form Audit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['proses_audit'])) {
    $asset_id = $_POST['asset_id'];
    $currentAsset = $assetModel->getById($asset_id);
    
    // Tentukan status verifikasi
    $status_verifikasi = ($currentAsset['kondisi'] == $_POST['kondisi_fisik']) ? 'Sesuai' : 'Tidak Sesuai';

    $data = [
        'asset_id' => $asset_id,
        'user_id' => $_SESSION['user_id'],
        'tanggal_audit' => $_POST['tanggal_audit'],
        'kondisi_dilaporkan' => $currentAsset['kondisi'],
        'kondisi_fisik' => $_POST['kondisi_fisik'],
        'lokasi_fisik' => $_POST['lokasi_fisik'],
        'catatan' => $_POST['catatan'],
        'status_verifikasi' => $status_verifikasi
    ];

    if ($auditModel->create($data)) {
        $logModel->add($_SESSION['user_id'], 'Audit Aset', "Audit fisik dilakukan untuk aset " . $currentAsset['nama_aset']);
        header("Location: index.php?page=audit&status=success");
        exit();
    } else {
        $error = "Gagal memproses audit aset.";
    }
}

$audits = $auditModel->getAll();
$assets = $assetModel->getAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4 animate-fade-in">
    <div class="d-flex align-items-center">
        <div class="bg-success bg-opacity-10 p-2 rounded-3 me-3 text-success">
            <i class="bi bi-shield-check fs-4"></i>
        </div>
        <div>
            <h4 class="fw-800 m-0">Audit Fisik Aset</h4>
            <p class="text-muted small m-0">Verifikasi kondisi dan lokasi perangkat secara periodik</p>
        </div>
    </div>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAudit">
        <i class="bi bi-plus-lg me-2"></i> Mulai Audit Baru
    </button>
</div>

<?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> Audit berhasil disimpan dan kondisi aset telah diperbarui.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm animate-fade-in" style="animation-delay: 0.1s;">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Aset</th>
                        <th>Tanggal Audit</th>
                        <th>Kondisi Fisik</th>
                        <th>Lokasi Temuan</th>
                        <th>Verifikasi</th>
                        <th class="pe-4 text-end">Auditor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($audits)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada riwayat audit aset.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($audits as $au): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold"><?= $au['nama_aset'] ?></div>
                            <div class="small text-primary fw-bold" style="font-size: 0.7rem;"><?= $au['kode_aset'] ?></div>
                        </td>
                        <td>
                            <div class="small fw-bold"><?= date('d M Y', strtotime($au['tanggal_audit'])) ?></div>
                        </td>
                        <td>
                            <?php 
                            $bg = 'success';
                            if ($au['kondisi_fisik'] == 'Rusak Ringan') $bg = 'warning';
                            if ($au['kondisi_fisik'] == 'Rusak Berat') $bg = 'danger';
                            ?>
                            <span class="badge bg-<?= $bg ?> bg-opacity-10 text-<?= $bg ?> rounded-pill px-3 py-2" style="font-size: 0.65rem;">
                                <?= strtoupper($au['kondisi_fisik']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="small fw-bold"><?= $au['lokasi_fisik'] ?: 'Sesuai Data' ?></div>
                            <div class="text-muted" style="font-size: 0.65rem;"><?= $au['catatan'] ?: '-' ?></div>
                        </td>
                        <td>
                            <?php if($au['status_verifikasi'] == 'Sesuai'): ?>
                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2" style="font-size: 0.65rem;">
                                    <i class="bi bi-check-circle-fill me-1"></i> SESUAI
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-2" style="font-size: 0.65rem;">
                                    <i class="bi bi-exclamation-circle-fill me-1"></i> SELISIH
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="pe-4 text-end">
                            <div class="small fw-500"><?= $au['auditor'] ?></div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Audit -->
<div class="modal fade" id="modalAudit" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 28px;">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-800 m-0"><i class="bi bi-shield-check text-success me-2"></i> Form Audit Fisik Aset</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-4">Lakukan verifikasi lapangan untuk memastikan data sistem sesuai dengan kondisi fisik perangkat.</p>
                    
                    <div class="row g-4">
                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Pilih Aset untuk Diaudit</label>
                            <select name="asset_id" id="audit_asset_id" class="form-select" required>
                                <option value="">-- Pilih Aset --</option>
                                <?php foreach ($assets as $a): ?>
                                    <option value="<?= $a['id'] ?>" 
                                            data-kondisi="<?= $a['kondisi'] ?>"
                                            data-lokasi="<?= $a['nama_cabang'] ?> - <?= $a['nama_divisi'] ?>">
                                        [<?= $a['kode_aset'] ?>] <?= $a['nama_aset'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <div class="p-3 rounded-4 bg-light bg-opacity-50 border border-dashed text-muted small">
                                <div class="row">
                                    <div class="col-md-6">
                                        <i class="bi bi-info-circle me-1"></i> Kondisi di Sistem: <span id="info_kondisi_sistem" class="fw-bold text-dark">-</span>
                                    </div>
                                    <div class="col-md-6">
                                        <i class="bi bi-geo-alt me-1"></i> Lokasi Terdaftar: <span id="info_lokasi_terdaftar" class="fw-bold text-dark">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-2 opacity-25">

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nama Auditor</label>
                            <input type="text" class="form-control" value="<?= $_SESSION['nama'] ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tanggal Audit</label>
                            <input type="date" name="tanggal_audit" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Kondisi Fisik Saat Ini</label>
                            <select name="kondisi_fisik" class="form-select" required>
                                <option value="Baik">Baik</option>
                                <option value="Rusak Ringan">Rusak Ringan</option>
                                <option value="Rusak Berat">Rusak Berat</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Lokasi Temuan Fisik</label>
                            <input type="text" name="lokasi_fisik" class="form-control" placeholder="Contoh: Meja Admin Lt.2">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Catatan Audit</label>
                            <input type="text" name="catatan" class="form-control" placeholder="Tambahkan catatan jika ada selisih">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                    <button type="submit" name="proses_audit" class="btn btn-success px-4 text-white">Simpan Hasil Audit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('audit_asset_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    if (this.value) {
        document.getElementById('info_kondisi_sistem').innerText = selectedOption.getAttribute('data-kondisi');
        document.getElementById('info_lokasi_terdaftar').innerText = selectedOption.getAttribute('data-lokasi');
    } else {
        document.getElementById('info_kondisi_sistem').innerText = "-";
        document.getElementById('info_lokasi_terdaftar').innerText = "-";
    }
});
</script>

<style>
    .fw-800 { font-weight: 800; }
    .fw-500 { font-weight: 500; }
    .border-dashed { border-style: dashed !important; }
</style>
