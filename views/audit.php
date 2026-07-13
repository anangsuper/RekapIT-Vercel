<?php
require_once 'models/Asset.php';
require_once 'models/Audit.php';
require_once 'models/ActivityLog.php';
require_once 'models/Cabang.php';

$assetModel = new Asset($conn);
$auditModel = new Audit($conn);
$logModel = new ActivityLog($conn);
$cabangModel = new Cabang($conn);

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
$cabangs = $cabangModel->getAll();

// Calculate Stats
$totalAudit = count($audits);
$totalSesuai = 0;
$totalSelisih = 0;
foreach ($audits as $au) {
    if ($au['status_verifikasi'] === 'Sesuai') {
        $totalSesuai++;
    } else {
        $totalSelisih++;
    }
}
$matchRate = $totalAudit > 0 ? round(($totalSesuai / $totalAudit) * 100) : 100;
?>

<div class="container-fluid animate-fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center">
            <div class="bg-success bg-opacity-10 p-2.5 rounded-3 me-3 text-success">
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
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4 animate-fade-in" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Audit berhasil disimpan dan kondisi aset telah diperbarui.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4 animate-fade-in" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- KPI Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
                <div class="card-body p-4 text-white position-relative">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3.5rem; transform: translate(10%, -10%);">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <span class="small fw-bold opacity-75">TOTAL AUDIT FISIK</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $totalAudit ?></h3>
                    <small class="opacity-70 d-block mt-2">Seluruh riwayat audit perangkat</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <div class="card-body p-4 text-white position-relative">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3.5rem; transform: translate(10%, -10%);">
                        <i class="bi bi-patch-check"></i>
                    </div>
                    <span class="small fw-bold opacity-75">PERSENTASE SESUAI</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $matchRate ?>%</h3>
                    <small class="opacity-70 d-block mt-2"><?= $totalSesuai ?> Berhasil Terverifikasi Sesuai</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                <div class="card-body p-4 text-white position-relative">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3.5rem; transform: translate(10%, -10%);">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <span class="small fw-bold opacity-75">ALERT SELISIH</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $totalSelisih ?> Kasus</h3>
                    <small class="opacity-70 d-block mt-2">Ketidaksesuaian data & fisik lapangan</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Search filter box -->
    <div class="card border-0 shadow-sm mb-4 rounded-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label small fw-bold text-muted">🔍 Cari Log Audit</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="auditSearch" class="form-control bg-light border-0" placeholder="Cari Kode Aset, Nama Aset, Lokasi, Catatan, Auditor..." onkeyup="filterAudits()">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label d-block mb-2">&nbsp;</label>
                    <button class="btn btn-outline-secondary w-100 fw-bold py-2 shadow-sm rounded-3" onclick="resetFilters()">
                        <i class="bi bi-x-circle me-1.5"></i>Reset Pencarian
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Audits Log Card Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <!-- Desktop Layout -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4">Aset</th>
                            <th>Tanggal Audit</th>
                            <th>Kondisi Fisik</th>
                            <th>Temuan & Catatan</th>
                            <th>Hasil Verifikasi</th>
                            <th class="pe-4 text-end">Auditor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($audits)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-shield-slash fs-2 d-block mb-2"></i> Belum ada riwayat audit aset.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($audits as $au): ?>
                            <tr class="audit-row" data-search="<?= htmlspecialchars(strtolower($au['nama_aset'] . ' ' . $au['kode_aset'] . ' ' . ($au['lokasi_fisik'] ?? '') . ' ' . ($au['catatan'] ?? '') . ' ' . $au['auditor'])) ?>">
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($au['nama_aset']) ?></div>
                                    <span class="badge bg-primary bg-opacity-10 text-primary mt-1 small" style="font-size: 0.72rem;"><?= htmlspecialchars($au['kode_aset']) ?></span>
                                </td>
                                <td>
                                    <div class="small fw-bold text-dark"><i class="bi bi-calendar-event text-muted me-1.5"></i><?= date('d M Y', strtotime($au['tanggal_audit'])) ?></div>
                                </td>
                                <td>
                                    <?php 
                                    $bg = 'success';
                                    if ($au['kondisi_fisik'] == 'Rusak Ringan') $bg = 'warning';
                                    if ($au['kondisi_fisik'] == 'Rusak Berat') $bg = 'danger';
                                    ?>
                                    <span class="badge bg-<?= $bg ?> bg-opacity-10 text-<?= $bg ?> rounded-pill px-3 py-1.5" style="font-size: 0.68rem; font-weight: 700;">
                                        <?= strtoupper($au['kondisi_fisik']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark"><i class="bi bi-geo-alt text-muted me-1"></i><?= htmlspecialchars($au['lokasi_fisik'] ?: 'Sesuai Data') ?></div>
                                    <div class="text-muted small mt-0.5" style="font-size: 0.75rem;"><?= htmlspecialchars($au['catatan'] ?: '-') ?></div>
                                </td>
                                <td>
                                    <?php if($au['status_verifikasi'] == 'Sesuai'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1.5" style="font-size: 0.68rem; font-weight: 700;">
                                            <i class="bi bi-check-circle-fill me-1"></i> SESUAI
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-1.5" style="font-size: 0.68rem; font-weight: 700;">
                                            <i class="bi bi-exclamation-circle-fill me-1"></i> SELISIH
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 text-end">
                                    <div class="small fw-bold text-dark"><i class="bi bi-person-fill text-muted me-1"></i><?= htmlspecialchars($au['auditor']) ?></div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Layout -->
            <div class="d-block d-md-none p-3" id="mobileAuditContainer">
                <?php foreach ($audits as $au): ?>
                    <div class="card border p-3.5 mb-3 rounded-3 shadow-sm mobile-audit-card" data-search="<?= htmlspecialchars(strtolower($au['nama_aset'] . ' ' . $au['kode_aset'] . ' ' . ($au['lokasi_fisik'] ?? '') . ' ' . ($au['catatan'] ?? '') . ' ' . $au['auditor'])) ?>">
                        <div class="d-flex justify-content-between align-items-start mb-2.5">
                            <div>
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded px-2.5 py-1 small fw-bold"><?= htmlspecialchars($au['kode_aset']) ?></span>
                                <h6 class="fw-bold text-dark mt-2 mb-0.5"><?= htmlspecialchars($au['nama_aset']) ?></h6>
                            </div>
                            <?php if($au['status_verifikasi'] == 'Sesuai'): ?>
                                <span class="badge bg-success bg-opacity-10 text-success rounded px-2.5 py-1.5 small font-bold">SESUAI</span>
                            <?php else: ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger rounded px-2.5 py-1.5 small font-bold">SELISIH</span>
                            <?php endif; ?>
                        </div>

                        <div class="p-2.5 bg-light rounded-3 mb-2.5 small row g-2">
                            <div class="col-6"><span class="text-muted text-xs">Lokasi Temuan:</span><br><strong><?= htmlspecialchars($au['lokasi_fisik'] ?: 'Sesuai Data') ?></strong></div>
                            <div class="col-6"><span class="text-muted text-xs">Kondisi Fisik:</span><br><strong><?= htmlspecialchars($au['kondisi_fisik']) ?></strong></div>
                        </div>

                        <div class="row g-2 mb-1">
                            <div class="col-6 small"><span class="text-muted">Tanggal:</span><br><strong><?= date('d M Y', strtotime($au['tanggal_audit'])) ?></strong></div>
                            <div class="col-6 small"><span class="text-muted">Auditor:</span><br><strong><?= htmlspecialchars($au['auditor']) ?></strong></div>
                            <div class="col-12 small"><span class="text-muted">Catatan:</span><br><span class="text-dark fw-medium"><?= htmlspecialchars($au['catatan'] ?: '-') ?></span></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
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
                        <!-- Filter Cabang Asal -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Filter Cabang Aset</label>
                            <select id="audit_filter_cabang" class="form-select bg-light border-0">
                                <option value="">-- Semua Cabang --</option>
                                <?php foreach ($cabangs as $c): ?>
                                    <option value="<?= htmlspecialchars($c['nama_cabang']) ?>"><?= htmlspecialchars($c['nama_cabang']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Pencarian Nama / Kode Aset -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Cari Nama / Kode Aset</label>
                            <input type="text" id="audit_search_asset_input" class="form-control bg-light border-0" placeholder="Ketik nama atau kode...">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-muted">Pilih Aset untuk Diaudit</label>
                            <select name="asset_id" id="audit_asset_id" class="form-select bg-light border-0" required>
                                <option value="">-- Pilih Aset --</option>
                                <?php foreach ($assets as $a): ?>
                                    <option value="<?= $a['id'] ?>" 
                                            data-cabang-name="<?= htmlspecialchars($a['nama_cabang']) ?>"
                                            data-kondisi="<?= $a['kondisi'] ?>"
                                            data-lokasi="<?= htmlspecialchars($a['nama_cabang'] . ' - ' . $a['nama_divisi']) ?>">
                                        [<?= $a['kode_aset'] ?>] <?= htmlspecialchars($a['nama_aset']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <div class="p-3 rounded-4 bg-light border border-dashed text-muted small">
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
                            <label class="form-label small fw-bold text-muted">Nama Auditor</label>
                            <input type="text" class="form-control bg-light border-0" value="<?= htmlspecialchars($_SESSION['nama']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Tanggal Audit</label>
                            <input type="date" name="tanggal_audit" class="form-control bg-light border-0" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Kondisi Fisik Saat Ini</label>
                            <select name="kondisi_fisik" class="form-select bg-light border-0" required>
                                <option value="Baik">Baik</option>
                                <option value="Rusak Ringan">Rusak Ringan</option>
                                <option value="Rusak Berat">Rusak Berat</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Lokasi Temuan Fisik</label>
                            <input type="text" name="lokasi_fisik" class="form-control bg-light border-0" placeholder="Contoh: Meja Admin Lt.2">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-muted">Catatan Audit</label>
                            <input type="text" name="catatan" class="form-control bg-light border-0" placeholder="Tambahkan catatan jika ada selisih kondisi / penempatan">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 shadow-sm" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                    <button type="submit" name="proses_audit" class="btn btn-success px-4 text-white shadow-sm fw-bold">Simpan Hasil Audit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Bind selected asset details to info panel
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

// Smart Filter Aset inside Audit Modal
function filterAuditAssetOptions() {
    const selectedCabangName = document.getElementById('audit_filter_cabang').value;
    const query = document.getElementById('audit_search_asset_input').value.toLowerCase();
    const selectAsset = document.getElementById('audit_asset_id');
    const options = selectAsset.querySelectorAll('option');

    options.forEach(option => {
        if (option.value === "") {
            option.style.display = 'block';
            return;
        }

        const text = option.textContent.toLowerCase();
        const cabangName = option.getAttribute('data-cabang-name');
        
        const matchQuery = text.includes(query);
        const matchBranch = (selectedCabangName === "" || cabangName === selectedCabangName);

        if (matchQuery && matchBranch) {
            option.style.display = 'block';
        } else {
            option.style.display = 'none';
        }
    });
}

document.getElementById('audit_filter_cabang').addEventListener('change', function() {
    filterAuditAssetOptions();
    document.getElementById('audit_asset_id').value = "";
    document.getElementById('info_kondisi_sistem').innerText = "-";
    document.getElementById('info_lokasi_terdaftar').innerText = "-";
});

document.getElementById('audit_search_asset_input').addEventListener('input', filterAuditAssetOptions);

// Client search query filter
function filterAudits() {
    const query = document.getElementById('auditSearch').value.toLowerCase();
    
    // Desktop rows
    const rows = document.querySelectorAll('.audit-row');
    rows.forEach(row => {
        const searchVal = row.getAttribute('data-search');
        if (searchVal.includes(query)) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });

    // Mobile cards
    const cards = document.querySelectorAll('.mobile-audit-card');
    cards.forEach(card => {
        const searchVal = card.getAttribute('data-search');
        if (searchVal.includes(query)) {
            card.style.display = "";
        } else {
            card.style.display = "none";
        }
    });
}

function resetFilters() {
    document.getElementById('auditSearch').value = "";
    filterAudits();
}
</script>

<style>
    .fw-800 { font-weight: 800; }
    .fw-500 { font-weight: 500; }
    .border-dashed { border-style: dashed !important; }
    .text-xs { font-size: 0.78rem; }
</style>
