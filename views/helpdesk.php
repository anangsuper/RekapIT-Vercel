<?php
require_once 'models/HelpdeskTicket.php';
require_once 'models/Cabang.php';
require_once 'models/Divisi.php';
require_once 'models/Asset.php';
require_once 'helpers/notification.php';

$ticketModel = new HelpdeskTicket($conn);
$cabangModel = new Cabang($conn);
$divisiModel = new Divisi($conn);
$assetModel = new Asset($conn);

$branches = $cabangModel->getAll();
$divisis = $divisiModel->getAll();
$assets = $assetModel->getAll();

$successTicket = null;
$searchedTicket = null;
$searchError = null;

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_tiket'])) {
    $nama_pelapor = trim($_POST['nama_pelapor'] ?? '');
    $kontak_pelapor = trim($_POST['kontak_pelapor'] ?? '');
    $id_cabang = !empty($_POST['id_cabang']) ? intval($_POST['id_cabang']) : null;
    $id_divisi = !empty($_POST['id_divisi']) ? intval($_POST['id_divisi']) : null;
    $asset_id = !empty($_POST['asset_id']) ? intval($_POST['asset_id']) : null;
    $kode_aset_manual = trim($_POST['kode_aset_manual'] ?? '');
    $prioritas = $_POST['prioritas'] ?? 'Biasa';
    $keluhan = trim($_POST['keluhan'] ?? '');

    $kode_aset = '-';
    if ($asset_id) {
        $selectedAsset = array_filter($assets, fn($a) => $a['id'] == $asset_id);
        if (!empty($selectedAsset)) {
            $first = reset($selectedAsset);
            $kode_aset = $first['kode_aset'] . ' - ' . $first['nama_aset'];
        }
    } elseif ($kode_aset_manual) {
        $kode_aset = $kode_aset_manual;
    }

    if ($nama_pelapor && $keluhan) {
        $ticketData = [
            'nama_pelapor' => $nama_pelapor,
            'kontak_pelapor' => $kontak_pelapor,
            'id_cabang' => $id_cabang,
            'id_divisi' => $id_divisi,
            'asset_id' => $asset_id,
            'kode_aset' => $kode_aset,
            'prioritas' => $prioritas,
            'keluhan' => $keluhan,
            'status' => 'Menunggu'
        ];

        $ticketNum = $ticketModel->create($ticketData);
        if ($ticketNum) {
            $successTicket = $ticketNum;

            // Send Telegram Alert
            $cabangName = '-';
            if ($id_cabang) {
                foreach ($branches as $b) {
                    if ($b['id'] == $id_cabang) { $cabangName = $b['nama_cabang']; break; }
                }
            }

            $prioEmoji = match($prioritas) {
                'Darurat' => '🔴',
                'Penting' => '🟡',
                default => '🔵'
            };

            $tgMsg = "📩 *TIKET HELPDESK BARU* (`#{$ticketNum}`)\n\n"
                   . "*• Pelapor:* {$nama_pelapor}\n"
                   . "*• Cabang:* {$cabangName}\n"
                   . "*• Perangkat:* `{$kode_aset}`\n"
                   . "*• Prioritas:* {$prioEmoji} {$prioritas}\n"
                   . "*• Keluhan:* {$keluhan}\n"
                   . "*• Waktu:* " . date('d M Y, H:i:s');
            
            sendTelegramNotification($tgMsg);
        }
    }
}

// Handle Ticket Search
if (isset($_GET['cek_tiket']) && !empty($_GET['nomor_tiket'])) {
    $num = trim($_GET['nomor_tiket']);
    $searchedTicket = $ticketModel->getByTicketNumber($num);
    if (!$searchedTicket) {
        $searchError = "Nomor tiket [$num] tidak ditemukan. Pastikan nomor tiket sudah benar.";
    }
}
?>

<div class="container-fluid animate-fade-in max-w-1200 mx-auto" style="max-width: 1000px;">
    <!-- Page Header -->
    <div class="text-center my-4">
        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex p-3 mb-2">
            <i class="bi bi-headset fs-2"></i>
        </div>
        <h3 class="fw-800 m-0">Portal Helpdesk IT</h3>
        <p class="text-muted small">Laporkan kendala perangkat IT Anda atau cek status penanganan tiket perbaikan.</p>
    </div>

    <!-- Success Banner -->
    <?php if ($successTicket): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 p-4 mb-4 animate-fade-in text-center" role="alert">
            <i class="bi bi-check-circle-fill fs-2 d-block mb-2 text-success"></i>
            <h5 class="fw-800 mb-1">Laporan Tiket Berhasil Dikirim!</h5>
            <p class="small mb-3">Nomor Tiket Anda: <strong class="fs-5 text-primary">#<?= htmlspecialchars($successTicket) ?></strong></p>
            <p class="small text-muted mb-3">Simpan nomor tiket ini untuk memantau status perbaikan oleh tim IT.</p>
            <a href="index.php?page=helpdesk&cek_tiket=1&nomor_tiket=<?= urlencode($successTicket) ?>" class="btn btn-primary px-4">
                <i class="bi bi-search me-1"></i> Cek Status Tiket Ini
            </a>
        </div>
    <?php endif; ?>

    <!-- Main Navigation Tabs -->
    <ul class="nav nav-pills nav-justified gap-2 mb-4 p-1 rounded-4 card border-0 shadow-sm" style="background: var(--card-bg);">
        <li class="nav-item">
            <button class="nav-link rounded-3 fw-bold py-2.5 <?= (!isset($_GET['cek_tiket'])) ? 'active' : '' ?>" id="tab-lapor-btn" onclick="showTab('lapor')">
                <i class="bi bi-pencil-square me-2"></i> Buat Tiket Baru
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link rounded-3 fw-bold py-2.5 <?= (isset($_GET['cek_tiket'])) ? 'active' : '' ?>" id="tab-cek-btn" onclick="showTab('cek')">
                <i class="bi bi-search me-2"></i> Cek Status Tiket
            </button>
        </li>
    </ul>

    <!-- Tab 1: Form Buat Tiket -->
    <div id="tab-lapor" style="<?= (isset($_GET['cek_tiket'])) ? 'display: none;' : '' ?>">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
            <div class="card-header border-0 bg-transparent p-4 pb-0">
                <h5 class="fw-800 m-0"><i class="bi bi-file-earmark-plus text-primary me-2"></i>Formulir Pelaporan Kendala IT</h5>
                <p class="text-muted small m-0 mt-1">Isi data di bawah ini secara akurat agar tim IT dapat memproses laporan Anda dengan cepat.</p>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Nama Pelapor / Karyawan <span class="text-danger">*</span></label>
                            <input type="text" name="nama_pelapor" class="form-control" placeholder="Contoh: Ahmad Fauzi" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">No. WhatsApp / HP (Opsional)</label>
                            <input type="text" name="kontak_pelapor" class="form-control" placeholder="Contoh: 081234567890">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Kantor Cabang</label>
                            <select name="id_cabang" class="form-select">
                                <option value="">-- Pilih Cabang --</option>
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nama_cabang']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Divisi / Unit Kerja</label>
                            <select name="id_divisi" class="form-select">
                                <option value="">-- Pilih Divisi --</option>
                                <?php foreach ($divisis as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nama_divisi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold text-muted">Pilih Perangkat Bermasalah</label>
                            <select name="asset_id" id="assetSelect" class="form-select" onchange="toggleManualAssetInput(this)">
                                <option value="">-- Pilih dari Daftar Perangkat --</option>
                                <?php foreach ($assets as $a): ?>
                                    <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['kode_aset']) ?> - <?= htmlspecialchars($a['nama_aset']) ?> (<?= htmlspecialchars($a['nama_cabang'] ?? 'Cabang N/A') ?>)</option>
                                <?php endforeach; ?>
                                <option value="manual">-- Lainnya / Ketik Manual --</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Prioritas Kendala</label>
                            <select name="prioritas" class="form-select">
                                <option value="Biasa">🔵 Biasa (Normal)</option>
                                <option value="Penting">🟡 Penting (Menghambat Kerja)</option>
                                <option value="Darurat">🔴 Darurat (Sistem/Unit Mati Total)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3" id="manualAssetBox" style="display: none;">
                        <label class="form-label small fw-bold text-muted">Nama / Kode Perangkat Manual</label>
                        <input type="text" name="kode_aset_manual" class="form-control" placeholder="Contoh: PRINTER EPSON RUANG AO">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">Detail Keluhan / Kendala <span class="text-danger">*</span></label>
                        <textarea name="keluhan" class="form-control" rows="4" placeholder="Jelaskan kendala secara singkat dan jelas..." required></textarea>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" name="kirim_tiket" class="btn btn-primary px-4 py-2.5">
                            <i class="bi bi-send me-2"></i> Kirim Laporan Tiket
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tab 2: Cek Status Tiket -->
    <div id="tab-cek" style="<?= (!isset($_GET['cek_tiket'])) ? 'display: none;' : '' ?>">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-body p-4">
                <form method="GET" action="index.php" class="row g-3 align-items-center">
                    <input type="hidden" name="page" value="helpdesk">
                    <input type="hidden" name="cek_tiket" value="1">
                    <div class="col-md-9">
                        <div class="position-relative">
                            <i class="bi bi-ticket-perforated position-absolute top-50 start-3 translate-middle-y text-muted" style="left: 14px;"></i>
                            <input type="text" name="nomor_tiket" class="form-control ps-5" placeholder="Masukkan nomor tiket (Contoh: TKT-20260723-001)..." value="<?= htmlspecialchars($_GET['nomor_tiket'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100 py-2.5">
                            <i class="bi bi-search me-1"></i> Cari Tiket
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($searchError): ?>
            <div class="alert alert-warning border-0 shadow-sm rounded-4 p-3 mb-4 animate-fade-in" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($searchError) ?>
            </div>
        <?php endif; ?>

        <?php if ($searchedTicket): 
            $t = $searchedTicket;
            $statusClass = match($t['status']) {
                'Menunggu' => 'bg-warning text-dark',
                'Diproses' => 'bg-info text-white',
                'Selesai' => 'bg-success text-white',
                'Ditolak' => 'bg-danger text-white',
                default => 'bg-secondary text-white'
            };
            $statusIcon = match($t['status']) {
                'Menunggu' => 'bi-hourglass-split',
                'Diproses' => 'bi-gear-wide-connected',
                'Selesai' => 'bi-check-circle-fill',
                'Ditolak' => 'bi-x-circle-fill',
                default => 'bi-info-circle'
            };
        ?>
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5 animate-fade-in">
                <div class="card-header border-0 bg-transparent p-4 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1.5 fw-bold mb-2">
                            #<?= htmlspecialchars($t['nomor_tiket']) ?>
                        </span>
                        <h5 class="fw-800 m-0">Detail Status Tiket Helpdesk</h5>
                    </div>
                    <span class="badge <?= $statusClass ?> rounded-pill px-3 py-2 fw-bold d-flex align-items-center gap-1.5">
                        <i class="bi <?= $statusIcon ?>"></i> <?= htmlspecialchars($t['status']) ?>
                    </span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <small class="text-muted fw-bold d-block mb-1">DATA PELAPOR</small>
                            <h6 class="fw-bold m-0"><?= htmlspecialchars($t['nama_pelapor']) ?></h6>
                            <p class="text-muted small mb-0"><?= htmlspecialchars($t['nama_cabang'] ?? 'Cabang N/A') ?> <?= $t['nama_divisi'] ? '• ' . htmlspecialchars($t['nama_divisi']) : '' ?></p>
                            <?php if ($t['kontak_pelapor']): ?>
                                <small class="text-muted"><i class="bi bi-whatsapp me-1"></i><?= htmlspecialchars($t['kontak_pelapor']) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted fw-bold d-block mb-1">PERANGKAT & TANGGAL</small>
                            <h6 class="fw-bold m-0"><?= htmlspecialchars($t['kode_aset'] ?: 'Perangkat N/A') ?></h6>
                            <p class="text-muted small mb-0"><i class="bi bi-calendar3 me-1"></i>Dilaporkan: <?= date('d M Y, H:i', strtotime($t['created_at'])) ?> WIB</p>
                        </div>
                    </div>

                    <hr class="my-3 opacity-15">

                    <div class="mb-4">
                        <small class="text-muted fw-bold d-block mb-1">KELUHAN / KENDALA DILAPORKAN</small>
                        <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.03); border: 1px solid var(--card-border);">
                            <?= nl2br(htmlspecialchars($t['keluhan'])) ?>
                        </div>
                    </div>

                    <?php if ($t['status'] === 'Diproses' || $t['status'] === 'Selesai'): ?>
                        <div class="p-3.5 rounded-4 border" style="background: rgba(99, 102, 241, 0.05); border-color: rgba(99, 102, 241, 0.2) !important;">
                            <h6 class="fw-bold text-primary mb-2"><i class="bi bi-tools me-2"></i>Tindakan Tim IT</h6>
                            <p class="small mb-2"><strong>Teknisi:</strong> <?= htmlspecialchars($t['teknisi_penanggung_jawab'] ?: 'Tim IT') ?></p>
                            <p class="small mb-0"><strong>Tindakan / Penanganan:</strong> <?= nl2br(htmlspecialchars($t['tindakan_teknisi'] ?: 'Sedang dalam proses penanganan oleh tim IT.')) ?></p>
                        </div>
                    <?php elseif ($t['status'] === 'Ditolak'): ?>
                        <div class="p-3.5 rounded-4 border" style="background: rgba(239, 68, 68, 0.05); border-color: rgba(239, 68, 68, 0.2) !important;">
                            <h6 class="fw-bold text-danger mb-2"><i class="bi bi-x-circle me-2"></i>Tiket Ditolak / Dibatalkan</h6>
                            <p class="small mb-0"><strong>Keterangan:</strong> <?= nl2br(htmlspecialchars($t['tindakan_teknisi'] ?: 'Laporan tidak valid atau telah diselesaikan secara terpisah.')) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function showTab(tabName) {
    document.getElementById('tab-lapor').style.display = (tabName === 'lapor') ? 'block' : 'none';
    document.getElementById('tab-cek').style.display = (tabName === 'cek') ? 'block' : 'none';

    document.getElementById('tab-lapor-btn').classList.toggle('active', tabName === 'lapor');
    document.getElementById('tab-cek-btn').classList.toggle('active', tabName === 'cek');
}

function toggleManualAssetInput(select) {
    const box = document.getElementById('manualAssetBox');
    if (select.value === 'manual') {
        box.style.display = 'block';
    } else {
        box.style.display = 'none';
    }
}
</script>
