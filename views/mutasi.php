<?php
require_once 'models/Asset.php';
require_once 'models/Mutation.php';
require_once 'models/Cabang.php';
require_once 'models/Divisi.php';
require_once 'models/Karyawan.php';
require_once 'models/ActivityLog.php';

$assetModel = new Asset($conn);
$mutationModel = new Mutation($conn);
$cabangModel = new Cabang($conn);
$divisiModel = new Divisi($conn);
$karyawanModel = new Karyawan($conn);
$logModel = new ActivityLog($conn);

// Proses Form Mutasi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['proses_mutasi'])) {
    $asset_id = $_POST['asset_id'];
    $currentAsset = $assetModel->getById($asset_id);
    
    $data = [
        'asset_id' => $asset_id,
        'user_id' => $_SESSION['user_id'],
        'id_cabang_lama' => $currentAsset['id_cabang'],
        'id_divisi_lama' => $currentAsset['id_divisi'],
        'id_karyawan_lama' => $currentAsset['id_karyawan'],
        'id_cabang_baru' => $_POST['id_cabang_baru'],
        'id_divisi_baru' => $_POST['id_divisi_baru'],
        'id_karyawan_baru' => $_POST['id_karyawan_baru'],
        'tanggal_mutasi' => $_POST['tanggal_mutasi'],
        'keterangan' => $_POST['keterangan']
    ];

    if ($mutationModel->create($data)) {
        $logModel->add($_SESSION['user_id'], 'Mutasi Aset', "Mutasi aset " . $currentAsset['nama_aset'] . " (" . $currentAsset['kode_aset'] . ")");
        header("Location: index.php?page=mutasi&status=success");
        exit();
    } else {
        $error = "Gagal memproses mutasi aset.";
    }
}

$mutations = $mutationModel->getAll();
$assets = $assetModel->getAll();
$cabangs = $cabangModel->getAll();
$divisis = $divisiModel->getAll();
$karyawans = $karyawanModel->getAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4 animate-fade-in">
    <div class="d-flex align-items-center">
        <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3 text-primary">
            <i class="bi bi-arrow-left-right fs-4"></i>
        </div>
        <div>
            <h4 class="fw-800 m-0">Mutasi Aset</h4>
            <p class="text-muted small m-0">Kelola riwayat perpindahan dan penugasan perangkat</p>
        </div>
    </div>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalMutasi">
        <i class="bi bi-plus-lg me-2"></i> Baru Mutasi
    </button>
</div>

<?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> Mutasi aset berhasil diproses dan tercatat.
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
                        <th>Dari (Lokasi/Pemegang)</th>
                        <th>Ke (Lokasi/Pemegang)</th>
                        <th>Tanggal</th>
                        <th>Pelaksana</th>
                        <th class="pe-4 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($mutations)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada riwayat mutasi aset.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($mutations as $m): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold"><?= $m['nama_aset'] ?></div>
                            <div class="small text-primary fw-bold" style="font-size: 0.7rem;"><?= $m['kode_aset'] ?></div>
                        </td>
                        <td>
                            <div class="small fw-bold text-danger opacity-75"><?= $m['cabang_lama'] ?></div>
                            <div class="small text-muted"><?= $m['karyawan_lama'] ?: 'Unassigned' ?></div>
                        </td>
                        <td>
                            <div class="small fw-bold text-success"><?= $m['cabang_baru'] ?></div>
                            <div class="small text-muted"><?= $m['karyawan_baru'] ?: 'Unassigned' ?></div>
                        </td>
                        <td>
                            <div class="small fw-bold"><?= date('d M Y', strtotime($m['tanggal_mutasi'])) ?></div>
                            <div class="text-muted" style="font-size: 0.65rem;"><?= $m['keterangan'] ?: '-' ?></div>
                        </td>
                        <td>
                            <div class="small fw-500"><?= $m['pelaksana'] ?></div>
                        </td>
                        <td class="pe-4 text-end">
                            <button class="btn btn-light btn-sm rounded-circle shadow-sm" title="Lihat Detail">
                                <i class="bi bi-info-circle text-primary"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Mutasi -->
<div class="modal fade" id="modalMutasi" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 28px;">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-800 m-0"><i class="bi bi-arrow-left-right text-primary me-2"></i> Form Mutasi Aset</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-4">Pilih aset yang akan dimutasi dan tentukan lokasi atau pemegang baru.</p>
                    
                    <div class="row g-4">
                        <!-- Pilih Aset -->
                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Pilih Aset</label>
                            <select name="asset_id" id="mutasi_asset_id" class="form-select" required>
                                <option value="">-- Pilih Aset --</option>
                                <?php foreach ($assets as $a): ?>
                                    <option value="<?= $a['id'] ?>" 
                                            data-cabang="<?= $a['nama_cabang'] ?>" 
                                            data-divisi="<?= $a['nama_divisi'] ?>" 
                                            data-karyawan="<?= $a['nama_karyawan'] ?: 'Unassigned' ?>">
                                        <?= $a['kode_aset'] ?> - <?= $a['nama_aset'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Info Aset Sekarang -->
                        <div class="col-md-12">
                            <div class="p-3 rounded-4 bg-light bg-opacity-50 border border-dashed text-muted small">
                                <i class="bi bi-info-circle me-2"></i> Lokasi Saat Ini: 
                                <span id="info_lokasi_lama" class="fw-bold text-dark">Pilih aset terlebih dahulu</span>
                            </div>
                        </div>

                        <hr class="my-2 opacity-25">
                        <div class="col-md-12 py-0"><h6 class="fw-bold m-0"><i class="bi bi-geo-alt-fill text-success me-1"></i> Lokasi/Penugasan Baru</h6></div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Cabang Baru</label>
                            <select name="id_cabang_baru" id="mutasi_cabang_baru" class="form-select" required>
                                <option value="">-- Pilih Cabang --</option>
                                <?php foreach ($cabangs as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= $c['nama_cabang'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Divisi Baru</label>
                            <select name="id_divisi_baru" class="form-select" required>
                                <option value="">-- Pilih Divisi --</option>
                                <?php foreach ($divisis as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= $d['nama_divisi'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Karyawan Baru</label>
                            <select name="id_karyawan_baru" id="mutasi_karyawan_baru" class="form-select">
                                <option value="">-- Unassigned --</option>
                                <?php foreach ($karyawans as $kr): ?>
                                    <option value="<?= $kr['id'] ?>" data-cabang="<?= $kr['id_cabang'] ?>"><?= $kr['nama_karyawan'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tanggal Mutasi</label>
                            <input type="date" name="tanggal_mutasi" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Keterangan / Alasan</label>
                            <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Perpindahan tugas / Promosi">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                    <button type="submit" name="proses_mutasi" class="btn btn-primary px-4">Proses Mutasi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Show current location when asset is selected
document.getElementById('mutasi_asset_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    if (this.value) {
        const cabang = selectedOption.getAttribute('data-cabang');
        const divisi = selectedOption.getAttribute('data-divisi');
        const karyawan = selectedOption.getAttribute('data-karyawan');
        document.getElementById('info_lokasi_lama').innerText = `${cabang} - ${divisi} (${karyawan})`;
    } else {
        document.getElementById('info_lokasi_lama').innerText = "Pilih aset terlebih dahulu";
    }
});

// Smart Filter Karyawan based on Branch
document.getElementById('mutasi_cabang_baru').addEventListener('change', function() {
    const selectedCabangId = this.value;
    const selectKaryawan = document.getElementById('mutasi_karyawan_baru');
    const options = selectKaryawan.querySelectorAll('option');

    options.forEach(option => {
        const cabangId = option.getAttribute('data-cabang');
        if (!cabangId) {
            option.style.display = 'block';
        } else {
            option.style.display = (cabangId === selectedCabangId) ? 'block' : 'none';
        }
    });
    selectKaryawan.value = "";
});
</script>

<style>
    .fw-800 { font-weight: 800; }
    .fw-500 { font-weight: 500; }
    .border-dashed { border-style: dashed !important; }
</style>
