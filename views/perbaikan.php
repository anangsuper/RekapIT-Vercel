<?php
require_once 'controllers/RepairController.php';
require_once 'models/Asset.php';
require_once 'models/Cabang.php';
require_once 'models/Sparepart.php';
require_once 'models/Repair.php';

// Self-healing database check for penggunaan_sparepart
$conn->exec("CREATE TABLE IF NOT EXISTS penggunaan_sparepart (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_repair INTEGER NOT NULL,
    id_sparepart INTEGER NOT NULL,
    jumlah INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);");

$repairController = new RepairController($conn);
$assetModel = new Asset($conn);
$cabangModel = new Cabang($conn);
$sparepartModel = new Sparepart($conn);

$id_cabang = $_GET['id_cabang'] ?? '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : null;

// Pagination logic
$limit = 10;
$pageNumber = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($pageNumber - 1) * $limit;

$totalRepairs = $repairController->countAll($id_cabang ?: null, $search_query);
$totalPages = ceil($totalRepairs / $limit);

$repairs = $repairController->getPaginated($limit, $offset, $id_cabang ?: null, $search_query);
$paginationUrl = "index.php?page=perbaikan";
if ($id_cabang) $paginationUrl .= "&id_cabang=" . urlencode($id_cabang);
if ($search_query) $paginationUrl .= "&search=" . urlencode($search_query);

$cabangs = $cabangModel->getAll();
$assets = $assetModel->getAll();
$spareparts = $sparepartModel->getAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah'])) {
    $asset_id = trim($_POST['asset_id'] ?? '');
    if ($asset_id === '') {
        header("Location: index.php?page=perbaikan&status=error_empty_asset");
        exit();
    }
    $data = [
        'asset_id' => $asset_id,
        'keluhan' => $_POST['keluhan']
    ];
    if ($repairController->store($data)) {
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
    if ($repairController->update($id, $data)) {
        // Process sparepart usage if selected
        if (!empty($_POST['id_sparepart']) && !empty($_POST['jumlah_sparepart'])) {
            $id_sp = $_POST['id_sparepart'];
            $qty = (int)$_POST['jumlah_sparepart'];
            
            $repairModel = new Repair($conn);
            $repairModel->addSparepart($id, $id_sp, $qty);
            
            // Auto-decrement sparepart stock
            $sparepartModel->updateStok($id_sp, -$qty);
        }
        header("Location: index.php?page=perbaikan&status=updated");
        exit();
    }
}
?>
<?php if (isset($_GET['status'])): ?>
    <?php if ($_GET['status'] == 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4 animate-fade-in" role="alert" style="background: rgba(16, 185, 129, 0.1); color: #065f46;">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div>
                    <strong>Berhasil!</strong> Tiket perbaikan baru telah berhasil dibuat.
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'updated'): ?>
        <div class="alert alert-info alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4 animate-fade-in" role="alert" style="background: rgba(59, 130, 246, 0.1); color: #1e3a8a;">
            <div class="d-flex align-items-center">
                <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                <div>
                    <strong>Berhasil!</strong> Detail tiket perbaikan telah diperbarui.
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'error_empty_asset'): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4 animate-fade-in" role="alert" style="background: rgba(239, 68, 68, 0.1); color: #7f1d1d;">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div>
                    <strong>Gagal!</strong> Silakan pilih aset bermasalah terlebih dahulu.
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
<?php endif; ?>

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

    <!-- Search & Filter Card -->
    <div class="card border-0 shadow-sm mb-4 rounded-4 animate-fade-in">
        <div class="card-body p-4">
            <form method="GET" action="index.php" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="perbaikan">
                
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted">🏢 Filter Kantor Cabang</label>
                    <select name="id_cabang" class="form-select bg-light border-0" onchange="this.form.submit()">
                        <option value="">-- Semua Cabang --</option>
                        <?php foreach ($cabangs as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($id_cabang == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nama_cabang']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted">🔍 Cari Tiket Perbaikan</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control bg-light border-0" placeholder="Cari Kode Aset, Nama Aset, Keluhan, Status..." value="<?= htmlspecialchars($search_query ?? '') ?>">
                        <?php if ($search_query): ?>
                            <a href="index.php?page=perbaikan&id_cabang=<?= urlencode($id_cabang) ?>" class="btn btn-light border-0 d-flex align-items-center text-danger"><i class="bi bi-x-circle-fill"></i></a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary d-none">Cari</button>
                    <a href="index.php?page=perbaikan" class="btn btn-outline-secondary w-100 fw-bold py-2 shadow-sm rounded-3">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset Filter
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Filter Condition Badge -->
    <?php if ($id_cabang || $search_query): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4 border-0 shadow-sm rounded-4 d-flex justify-content-between align-items-center animate-fade-in" role="alert">
            <div class="m-0 small">
                <i class="bi bi-filter-circle-fill text-warning me-2 fs-5"></i> 
                Menampilkan tiket perbaikan aktif untuk: 
                <?php if ($id_cabang): ?>
                    <span class="badge bg-primary rounded-pill px-2.5 py-1">Cabang ID: <?= $id_cabang ?></span>
                <?php endif; ?>
                <?php if ($search_query): ?>
                    <span class="badge bg-info text-dark rounded-pill px-2.5 py-1">Kata Kunci: "<?= htmlspecialchars($search_query) ?>"</span>
                <?php endif; ?>
            </div>
            <a href="index.php?page=perbaikan" class="btn btn-sm btn-light border-0 shadow-sm rounded-pill px-3 py-1.5 fw-bold"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset</a>
        </div>
    <?php endif; ?>

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
                            <?php 
                            $repairModelInstance = new Repair($conn);
                            $usedSp = $repairModelInstance->getSpareparts($r['id']);
                            if (!empty($usedSp)): 
                            ?>
                                <div class="mt-1.5 d-flex flex-wrap gap-1">
                                    <?php foreach ($usedSp as $usp): ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary rounded px-2 py-0.5" style="font-size: 0.7rem; font-weight: 600;">
                                            ⚙️ <?= $usp['nama_sparepart'] ?> (<?= $usp['jumlah'] ?>)
                                        </span>
                                    <?php endforeach; ?>
                                </div>
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
        
        <!-- Pagination Controls -->
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white border-top-0 pt-2 pb-4 d-flex justify-content-center">
            <?= getPaginationControls($pageNumber, $totalPages, $paginationUrl) ?>
        </div>
        <?php endif; ?>
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
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Gunakan Sparepart (Opsional)</label>
                        <div class="row g-2">
                            <div class="col-8">
                                <select name="id_sparepart" class="form-select bg-light border-0">
                                    <option value="">-- Tanpa Ganti Sparepart --</option>
                                    <?php foreach ($spareparts as $sp): ?>
                                        <option value="<?= $sp['id'] ?>">
                                            <?= htmlspecialchars($sp['nama_sparepart']) ?> (Stok: <?= $sp['stok'] ?> <?= htmlspecialchars($sp['satuan']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-4">
                                <input type="number" name="jumlah_sparepart" class="form-control bg-light border-0" value="1" min="1" placeholder="Qty">
                            </div>
                        </div>
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
            <form method="POST" onsubmit="return validateTambahForm()">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-800 m-0"><i class="bi bi-plus-circle-fill text-primary me-2"></i> Buat Tiket Perbaikan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Pilih Cabang Aset</label>
                        <select id="branchSelect" class="form-select shadow-sm mb-2" onchange="filterAssetsByBranch()">
                            <option value="">Semua Cabang</option>
                            <?php foreach ($cabangs as $c): ?>
                                <option value="<?= $c['nama_cabang'] ?>"><?= $c['nama_cabang'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="form-label small fw-bold mt-2">Pilih Aset Bermasalah</label>
                        <!-- Custom Searchable Dropdown with colored condition badges -->
                        <div class="dropdown custom-select-dropdown position-relative" id="assetDropdownContainer">
                            <input type="hidden" name="asset_id" id="selectedAssetId" required>
                            <button class="form-select text-start w-100 shadow-sm d-flex justify-content-between align-items-center" type="button" id="assetDropdownTrigger" data-bs-toggle="dropdown" aria-expanded="false" style="background-color: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 16px; min-height: 46px;">
                                <span class="text-muted" id="selectedAssetLabel">Pilih Aset Bermasalah...</span>
                            </button>
                            <div class="dropdown-menu w-100 p-3 shadow-lg border-0" aria-labelledby="assetDropdownTrigger" style="border-radius: 16px; max-height: 350px; overflow-y: auto; z-index: 1100; background: #fff;">
                                <div class="mb-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                                        <input type="text" class="form-control border-start-0" id="assetSearchInput" placeholder="Cari kode, nama, pemegang, atau cabang..." onkeyup="filterCustomAssets()">
                                    </div>
                                </div>
                                <div class="custom-assets-list" style="max-height: 240px; overflow-y: auto;">
                                    <?php foreach ($assets as $a): ?>
                                        <?php
                                            $condColor = 'success';
                                            if ($a['kondisi'] == 'Rusak Ringan') $condColor = 'warning';
                                            if ($a['kondisi'] == 'Rusak Berat') $condColor = 'danger';
                                        ?>
                                        <button type="button" class="dropdown-item custom-asset-item d-flex justify-content-between align-items-center border-bottom py-2 px-2 text-start" 
                                                data-id="<?= $a['id'] ?>" 
                                                data-branch="<?= htmlspecialchars($a['nama_cabang']) ?>"
                                                data-kode="<?= htmlspecialchars($a['kode_aset']) ?>"
                                                data-nama="<?= htmlspecialchars($a['nama_aset']) ?>"
                                                data-kondisi="<?= htmlspecialchars($a['kondisi']) ?>"
                                                data-pemegang="<?= htmlspecialchars($a['nama_karyawan'] ?? 'Tidak ada') ?>"
                                                data-spesifikasi="<?= htmlspecialchars($a['spesifikasi'] ?? '-') ?>"
                                                data-merk="<?= htmlspecialchars($a['merk'] ?? '-') ?>"
                                                data-model="<?= htmlspecialchars($a['model'] ?? '-') ?>"
                                                data-search="<?= htmlspecialchars(strtolower($a['kode_aset'] . ' ' . $a['nama_aset'] . ' ' . ($a['nama_karyawan'] ?? 'Unassigned') . ' ' . $a['nama_cabang'])) ?>"
                                                onclick="selectCustomAsset(this)"
                                                style="border-radius: 8px; border: none; background: transparent; width: 100%; transition: background-color 0.2s;">
                                            <div>
                                                <div class="fw-bold text-dark mb-0" style="font-size: 0.85rem;">
                                                    <span class="badge bg-primary bg-opacity-10 text-primary me-1" style="font-size: 0.7rem; padding: 2px 6px;"><?= $a['kode_aset'] ?></span>
                                                    <?= htmlspecialchars($a['nama_aset']) ?>
                                                </div>
                                                <div class="text-muted" style="font-size: 0.7rem; margin-top: 2px;">
                                                    <i class="bi bi-person me-1"></i><?= htmlspecialchars($a['nama_karyawan'] ?? 'Unassigned') ?> &bull; 
                                                    <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($a['nama_cabang']) ?>
                                                </div>
                                            </div>
                                            <span class="badge bg-<?= $condColor ?> bg-opacity-10 text-<?= $condColor ?> rounded-pill px-2 py-1" style="font-size: 0.65rem; font-weight: 700;">
                                                <?= strtoupper($a['kondisi']) ?>
                                            </span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="selectedAssetDetails" class="mt-3 mb-3 p-3 rounded-4" style="display:none; background-color: #f8fafc; border: 1px dashed #cbd5e1;"></div>
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
// Existing script logic
function selectCustomAsset(element) {
    // Clone selection text to display in button
    var labelHtml = element.querySelector("div").innerHTML;
    var id = element.getAttribute("data-id");
    
    // Set values
    document.getElementById("selectedAssetId").value = id;
    document.getElementById("selectedAssetLabel").innerHTML = labelHtml;

    // Show Details
    var kode = element.getAttribute("data-kode");
    var nama = element.getAttribute("data-nama");
    var kondisi = element.getAttribute("data-kondisi");
    var pemegang = element.getAttribute("data-pemegang");
    var spek = element.getAttribute("data-spesifikasi");
    var merk = element.getAttribute("data-merk");
    var model = element.getAttribute("data-model");
    
    var condColor = 'success';
    if (kondisi === 'Rusak Ringan') condColor = 'warning';
    if (kondisi === 'Rusak Berat') condColor = 'danger';
    
    var detailsHtml = `
        <div class="row g-2 text-dark" style="font-size: 0.8rem;">
            <div class="col-6"><strong>Kode Aset:</strong> ${kode}</div>
            <div class="col-6"><strong>Kondisi:</strong> <span class="badge bg-${condColor} bg-opacity-10 text-${condColor} rounded-pill">${kondisi}</span></div>
            <div class="col-6"><strong>Spesifikasi:</strong> ${spek}</div>
            <div class="col-6"><strong>Merk/Model:</strong> ${merk} ${model}</div>
            <div class="col-12"><strong>Pemegang:</strong> ${pemegang}</div>
        </div>
    `;
    var detailsDiv = document.getElementById("selectedAssetDetails");
    detailsDiv.innerHTML = detailsHtml;
    detailsDiv.style.display = "block";
    
    // Hide dropdown
    var dropdownTrigger = document.getElementById("assetDropdownTrigger");
    var dropdown = bootstrap.Dropdown.getInstance(dropdownTrigger);
    if (!dropdown) {
        dropdown = new bootstrap.Dropdown(dropdownTrigger);
    }
    dropdown.hide();
}

function filterCustomAssets() {
    var query = document.getElementById("assetSearchInput").value.toLowerCase();
    var items = document.querySelectorAll(".custom-asset-item");
    var selectedBranch = document.getElementById("branchSelect").value;
    
    items.forEach(function(item) {
        var searchText = item.getAttribute("data-search");
        var itemBranch = item.getAttribute("data-branch");
        
        var matchSearch = searchText.includes(query);
        var matchBranch = (selectedBranch === "" || itemBranch === selectedBranch);
        
        if (matchSearch && matchBranch) {
            item.style.setProperty("display", "flex", "important");
        } else {
            item.style.setProperty("display", "none", "important");
        }
    });
}

function filterAssetsByBranch() {
    // Reset search query
    document.getElementById("assetSearchInput").value = "";
    // Re-filter asset list
    filterCustomAssets();
    // Clear selection
    document.getElementById("selectedAssetId").value = "";
    document.getElementById("selectedAssetLabel").innerHTML = '<span class="text-muted">Pilih Aset Bermasalah...</span>';
    document.getElementById("selectedAssetDetails").style.display = "none";
}

function validateTambahForm() {
    var assetId = document.getElementById("selectedAssetId").value;
    if (!assetId || assetId.trim() === "") {
        alert("Peringatan: Silakan pilih aset bermasalah terlebih dahulu!");
        return false;
    }
    return true;
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

<?php if (isset($_GET['asset_id'])): ?>
document.addEventListener("DOMContentLoaded", function() {
    var assetId = "<?= (int)$_GET['asset_id'] ?>";
    var item = document.querySelector('.custom-asset-item[data-id="' + assetId + '"]');
    if (item) {
        // Select custom asset
        selectCustomAsset(item);
        
        // Open modal
        var myModal = new bootstrap.Modal(document.getElementById('modalTambah'));
        myModal.show();
    }
});
<?php endif; ?>
</script>

<style>
    .fw-800 { font-weight: 800; }
    .fw-500 { font-weight: 500; }
    
    .custom-asset-item {
        border-bottom: 1px solid #f1f5f9 !important;
    }
    .custom-asset-item:last-child {
        border-bottom: none !important;
    }
    .custom-asset-item:hover {
        background-color: #f8fafc !important;
    }
</style>
