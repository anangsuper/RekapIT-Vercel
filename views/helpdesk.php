<?php
require_once 'models/HelpdeskTicket.php';
require_once 'models/HelpdeskComment.php';
require_once 'models/Cabang.php';
require_once 'models/Divisi.php';
require_once 'models/Asset.php';
require_once 'helpers/notification.php';

$ticketModel = new HelpdeskTicket($conn);
$commentModel = new HelpdeskComment($conn);
$cabangModel = new Cabang($conn);
$divisiModel = new Divisi($conn);
$assetModel = new Asset($conn);

$branches = $cabangModel->getAll();
$divisis = $divisiModel->getAll();
$assets = $assetModel->getAll();

$loginError = null;

// Handle Inline Helpdesk Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['helpdesk_login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama']     = $user['nama'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['id_cabang'] = $user['id_cabang'];
            $_SESSION['last_activity'] = time();

            header("Location: index.php?page=helpdesk");
            exit();
        } else {
            $loginError = "Username atau password yang Anda masukkan salah.";
        }
    } else {
        $loginError = "Username dan password wajib diisi.";
    }
}

// Check Login State
$isLoggedIn = isset($_SESSION['user_id']);
$userNama = $_SESSION['nama'] ?? '';
$userRole = $_SESSION['role'] ?? '';

// Handle Diskusi / Comment Reply
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_diskusi'])) {
    $ticket_id = intval($_POST['ticket_id']);
    $pesan = trim($_POST['pesan_diskusi'] ?? '');
    $nomor_tiket = trim($_POST['nomor_tiket'] ?? '');

    if ($ticket_id && !empty($pesan)) {
        $commentModel->addComment($ticket_id, $_SESSION['user_id'] ?? null, $userNama, $userRole, $pesan);

        $tgMsg = "💬 *BALASAN TIKET HELPDESK* (`#{$nomor_tiket}`)\n\n"
               . "*• Dari:* {$userNama} (" . ucfirst($userRole) . ")\n"
               . "*• Pesan:* {$pesan}\n"
               . "*• Waktu:* " . date('d M Y, H:i:s');
        sendTelegramNotification($tgMsg);

        header("Location: index.php?page=helpdesk&cek_tiket=1&nomor_tiket=" . urlencode($nomor_tiket) . "&diskusi=success");
        exit();
    }
}

$successTicket = null;
$searchedTicket = null;
$searchError = null;

// Handle Form Submission (Only when logged in)
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_tiket'])) {
    $nama_pelapor = $userNama;
    $kontak_pelapor = trim($_POST['kontak_pelapor'] ?? '');
    $id_cabang = !empty($_POST['id_cabang']) ? intval($_POST['id_cabang']) : ($_SESSION['id_cabang'] ?? null);
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

// Fetch My Tickets for logged-in user
$myTickets = [];
if ($isLoggedIn) {
    if (hasRole(['admin', 'teknisi'])) {
        $myTickets = $ticketModel->getAll();
    } else {
        $myTickets = $ticketModel->getByReporterName($userNama);
    }
}
?>

<?php if (!$isLoggedIn): ?>
    <!-- 1. LOGIN SCREEN FOR UNAUTHENTICATED USERS -->
    <div class="container animate-fade-in my-5" style="max-width: 440px;">
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-5">
            <div class="card-body p-4 p-md-5 text-center">
                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex p-3 mb-3">
                    <i class="bi bi-headset fs-2"></i>
                </div>
                <h4 class="fw-800 mb-1">Login Helpdesk IT</h4>
                <p class="text-muted small mb-4">Masuk dengan akun Anda untuk membuat tiket & memantau status perbaikan.</p>

                <?php if ($loginError): ?>
                    <div class="alert alert-danger border-0 rounded-3 p-3 mb-3 text-start small" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-1"></i> <?= htmlspecialchars($loginError) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3 text-start">
                        <label class="form-label small fw-bold text-muted">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-person text-muted"></i></span>
                            <input type="text" name="username" class="form-control border-start-0 ps-0" placeholder="Masukkan username..." required>
                        </div>
                    </div>
                    <div class="mb-4 text-start">
                        <label class="form-label small fw-bold text-muted">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-lock text-muted"></i></span>
                            <input type="password" name="password" class="form-control border-start-0 ps-0" placeholder="Masukkan password..." required>
                        </div>
                    </div>
                    <button type="submit" name="helpdesk_login" class="btn btn-primary w-100 py-2.5 fw-bold rounded-3">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Login ke Helpdesk
                    </button>
                </form>

                <div class="mt-4 p-3 rounded-3 text-start border" style="background: rgba(255,255,255,0.03); font-size: 0.78rem;">
                    <strong class="text-primary d-block mb-1"><i class="bi bi-info-circle me-1"></i> Akun Login Demo:</strong>
                    <span class="text-muted d-block">• Username: <code class="text-dark fw-bold">karyawan</code></span>
                    <span class="text-muted d-block">• Password: <code class="text-dark fw-bold">password</code></span>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- 2. LOGGED-IN HELPDESK DASHBOARD -->
    <div class="container-fluid animate-fade-in max-w-1200 mx-auto mb-5" style="max-width: 1050px;">
        <!-- Welcome User Bar -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 d-flex flex-row justify-content-between align-items-center flex-wrap gap-3" style="background: var(--card-bg);">
            <div class="d-flex align-items-center gap-3">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($userNama) ?>&background=4361ee&color=fff" class="rounded-circle border" width="48" height="48">
                <div>
                    <h5 class="fw-800 m-0">Selamat Datang, <?= htmlspecialchars($userNama) ?></h5>
                    <p class="text-muted small m-0">Login sebagai: <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1 fw-bold"><?= ucfirst($userRole) ?></span></p>
                </div>
            </div>
            <a href="logout.php?redirect=helpdesk" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold">
                <i class="bi bi-box-arrow-right me-1"></i> Keluar
            </a>
        </div>

        <!-- Success Banner -->
        <?php if ($successTicket): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-4 p-4 mb-4 animate-fade-in text-center" role="alert">
                <i class="bi bi-check-circle-fill fs-2 d-block mb-2 text-success"></i>
                <h5 class="fw-800 mb-1">Laporan Tiket Berhasil Dikirim!</h5>
                <p class="small mb-3">Nomor Tiket Anda: <strong class="fs-5 text-primary">#<?= htmlspecialchars($successTicket) ?></strong></p>
                <p class="small text-muted mb-3">Tiket Anda telah tersimpan di akun Anda dan siap ditangani tim IT.</p>
                <button onclick="showTab('mytickets')" class="btn btn-primary px-4">
                    <i class="bi bi-list-task me-1"></i> Lihat Tiket Saya
                </button>
            </div>
        <?php endif; ?>

        <!-- Main Navigation Tabs -->
        <ul class="nav nav-pills nav-justified gap-2 mb-4 p-1 rounded-4 card border-0 shadow-sm" style="background: var(--card-bg);">
            <li class="nav-item">
                <button class="nav-link rounded-3 fw-bold py-2.5 active" id="tab-lapor-btn" onclick="showTab('lapor')">
                    <i class="bi bi-pencil-square me-2"></i> Buat Tiket Baru
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link rounded-3 fw-bold py-2.5" id="tab-mytickets-btn" onclick="showTab('mytickets')">
                    <i class="bi bi-list-stars me-2"></i> Tiket Saya (<?= count($myTickets) ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link rounded-3 fw-bold py-2.5" id="tab-cek-btn" onclick="showTab('cek')">
                    <i class="bi bi-search me-2"></i> Cari No. Tiket
                </button>
            </li>
        </ul>

        <!-- Tab 1: Form Buat Tiket -->
        <div id="tab-lapor">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
                <div class="card-header border-0 bg-transparent p-4 pb-0">
                    <h5 class="fw-800 m-0"><i class="bi bi-file-earmark-plus text-primary me-2"></i>Formulir Pelaporan Kendala IT</h5>
                    <p class="text-muted small m-0 mt-1">Laporan akan secara otomatis dicatat atas nama akun Anda (<?= htmlspecialchars($userNama) ?>).</p>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Nama Pelapor / Karyawan</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($userNama) ?>" readonly disabled style="background: rgba(255,255,255,0.05);">
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
                                        <option value="<?= $b['id'] ?>" <?= (($_SESSION['id_cabang'] ?? null) == $b['id']) ? 'selected' : '' ?>><?= htmlspecialchars($b['nama_cabang']) ?></option>
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

        <!-- Tab 2: Tiket Saya -->
        <div id="tab-mytickets" style="display: none;">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
                <div class="card-header border-0 bg-transparent p-4 pb-0">
                    <h5 class="fw-800 m-0"><i class="bi bi-ticket-perforated text-primary me-2"></i>Daftar Tiket Laporan Saya</h5>
                    <p class="text-muted small m-0 mt-1">Berikut adalah daftar seluruh tiket keluhan yang telah Anda laporkan.</p>
                </div>
                <div class="card-body p-0 mt-3">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">No. Tiket</th>
                                    <th>Tanggal</th>
                                    <th>Perangkat</th>
                                    <th>Prioritas</th>
                                    <th>Keluhan</th>
                                    <th>Status</th>
                                    <th class="pe-4">Tindakan Teknisi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($myTickets)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <div class="bg-light bg-opacity-10 text-secondary rounded-circle d-inline-flex p-3 mb-3">
                                                <i class="bi bi-inbox fs-3"></i>
                                            </div>
                                            <p class="small fw-semibold mb-0">Anda belum pernah membuat tiket keluhan.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($myTickets as $mt): 
                                        $mtStatusBadge = match($mt['status']) {
                                            'Menunggu' => 'bg-warning text-dark',
                                            'Diproses' => 'bg-info text-white',
                                            'Selesai' => 'bg-success text-white',
                                            'Ditolak' => 'bg-danger text-white',
                                            default => 'bg-secondary text-white'
                                        };
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <strong class="text-primary">#<?= htmlspecialchars($mt['nomor_tiket']) ?></strong>
                                        </td>
                                        <td>
                                            <div class="small text-muted"><?= date('d/m/Y H:i', strtotime($mt['created_at'])) ?></div>
                                        </td>
                                        <td>
                                            <span class="small fw-semibold"><?= htmlspecialchars($mt['kode_aset'] ?: 'Perangkat N/A') ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">
                                                <?= htmlspecialchars($mt['prioritas']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="small text-truncate" style="max-width: 180px;" title="<?= htmlspecialchars($mt['keluhan']) ?>">
                                                <?= htmlspecialchars($mt['keluhan']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?= $mtStatusBadge ?> rounded-pill px-2.5 py-1" style="font-size: 0.75rem;">
                                                <?= htmlspecialchars($mt['status']) ?>
                                            </span>
                                        </td>
                                        <td class="pe-4">
                                            <small class="text-muted d-block">
                                                <strong><?= htmlspecialchars($mt['teknisi_penanggung_jawab'] ?: '-') ?></strong>: 
                                                <?= htmlspecialchars($mt['tindakan_teknisi'] ?: 'Belum ada tindakan') ?>
                                            </small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 3: Cek Status Tiket -->
        <div id="tab-cek" style="display: none;">
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
            ?>
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5 animate-fade-in">
                    <div class="card-header border-0 bg-transparent p-4 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1.5 fw-bold mb-2">
                                #<?= htmlspecialchars($t['nomor_tiket']) ?>
                            </span>
                            <h5 class="fw-800 m-0">Detail Status Tiket Helpdesk</h5>
                        </div>
                        <span class="badge <?= $statusClass ?> rounded-pill px-3 py-2 fw-bold">
                            <?= htmlspecialchars($t['status']) ?>
                        </span>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <small class="text-muted fw-bold d-block mb-1">DATA PELAPOR</small>
                                <h6 class="fw-bold m-0"><?= htmlspecialchars($t['nama_pelapor']) ?></h6>
                                <p class="text-muted small mb-0"><?= htmlspecialchars($t['nama_cabang'] ?? 'Cabang N/A') ?></p>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted fw-bold d-block mb-1">PERANGKAT & TANGGAL</small>
                                <h6 class="fw-bold m-0"><?= htmlspecialchars($t['kode_aset'] ?: 'Perangkat N/A') ?></h6>
                                <p class="text-muted small mb-0">Dilaporkan: <?= date('d M Y, H:i', strtotime($t['created_at'])) ?> WIB</p>
                            </div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted fw-bold d-block mb-1">KELUHAN</small>
                            <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.03); border: 1px solid var(--card-border);">
                                <?= nl2br(htmlspecialchars($t['keluhan'])) ?>
                            </div>
                        </div>
                        <div class="p-3 rounded-3 bg-light border mb-4">
                            <small class="text-muted fw-bold d-block mb-1">TINDAKAN TEKNISI</small>
                            <p class="small mb-0"><strong><?= htmlspecialchars($t['teknisi_penanggung_jawab'] ?: 'Tim IT') ?>:</strong> <?= nl2br(htmlspecialchars($t['tindakan_teknisi'] ?: 'Sedang dalam antrean penanganan.')) ?></p>
                        </div>

                        <!-- Diskusi & Catatan Tambahan Thread -->
                        <div class="mt-4 pt-3 border-top">
                            <h6 class="fw-bold mb-3"><i class="bi bi-chat-left-text text-primary me-2"></i>Diskusi & Catatan Tambahan</h6>
                            <?php 
                            $ticketComments = $commentModel->getByTicketId($t['id']);
                            ?>
                            <?php if (empty($ticketComments)): ?>
                                <p class="small text-muted mb-3 fst-italic">Belum ada percakapan tambahan pada tiket ini.</p>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-2.5 mb-4">
                                    <?php foreach ($ticketComments as $tc): 
                                        $isTech = in_array($tc['sender_role'], ['admin', 'teknisi']);
                                        $bubbleBg = $isTech ? 'background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.2);' : 'background: rgba(255, 255, 255, 0.04); border: 1px solid var(--card-border);';
                                        $badgeColor = $isTech ? 'bg-primary text-white' : 'bg-secondary text-white';
                                    ?>
                                        <div class="p-3 rounded-3" style="max-width: 90%; <?= $isTech ? 'margin-left: auto;' : 'margin-right: auto;' ?> <?= $bubbleBg ?>">
                                            <div class="d-flex justify-content-between align-items-center mb-1 gap-3">
                                                <small class="fw-bold"><?= htmlspecialchars($tc['sender_name']) ?> <span class="badge <?= $badgeColor ?> rounded-pill ms-1" style="font-size: 0.65rem;"><?= ucfirst($tc['sender_role']) ?></span></small>
                                                <small class="text-muted" style="font-size: 0.7rem;"><?= date('d/m H:i', strtotime($tc['created_at'])) ?></small>
                                            </div>
                                            <div class="small mb-0" style="word-break: break-word;"><?= nl2br(htmlspecialchars($tc['message'])) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Form Balasan Diskusi -->
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                <input type="hidden" name="nomor_tiket" value="<?= htmlspecialchars($t['nomor_tiket']) ?>">
                                <div class="input-group">
                                    <input type="text" name="pesan_diskusi" class="form-control" placeholder="Tuliskan pesan balasan / pertanyaan tambahan..." required>
                                    <button type="submit" name="kirim_diskusi" class="btn btn-primary px-4 fw-bold">
                                        <i class="bi bi-send me-1"></i> Kirim Pesan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
function showTab(tabName) {
    const lapor = document.getElementById('tab-lapor');
    const mytickets = document.getElementById('tab-mytickets');
    const cek = document.getElementById('tab-cek');

    if (lapor) lapor.style.display = (tabName === 'lapor') ? 'block' : 'none';
    if (mytickets) mytickets.style.display = (tabName === 'mytickets') ? 'block' : 'none';
    if (cek) cek.style.display = (tabName === 'cek') ? 'block' : 'none';

    document.getElementById('tab-lapor-btn')?.classList.toggle('active', tabName === 'lapor');
    document.getElementById('tab-mytickets-btn')?.classList.toggle('active', tabName === 'mytickets');
    document.getElementById('tab-cek-btn')?.classList.toggle('active', tabName === 'cek');
}

function toggleManualAssetInput(select) {
    const box = document.getElementById('manualAssetBox');
    if (box) {
        box.style.display = (select.value === 'manual') ? 'block' : 'none';
    }
}
</script>
