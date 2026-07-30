<?php
ob_start();
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/HelpdeskTicket.php';
require_once __DIR__ . '/models/HelpdeskComment.php';
require_once __DIR__ . '/models/Cabang.php';
require_once __DIR__ . '/models/Divisi.php';
require_once __DIR__ . '/models/Asset.php';
require_once __DIR__ . '/helpers/notification.php';

// Auth Check: Must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: helpdesk_login.php');
    exit();
}

$ticketModel = new HelpdeskTicket($conn);
$commentModel = new HelpdeskComment($conn);
$cabangModel = new Cabang($conn);
$divisiModel = new Divisi($conn);
$assetModel = new Asset($conn);

$branches = $cabangModel->getAll();
$divisis = $divisiModel->getAll();
$assets = $assetModel->getAll();

$userNama = $_SESSION['nama'] ?? 'Karyawan';
$userRole = $_SESSION['role'] ?? 'karyawan';
$userId = $_SESSION['user_id'];
$userCabangId = $_SESSION['id_cabang'] ?? null;

// Handle Diskusi / Comment Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_diskusi'])) {
    $ticket_id = intval($_POST['ticket_id']);
    $pesan = trim($_POST['pesan_diskusi'] ?? '');
    $nomor_tiket = trim($_POST['nomor_tiket'] ?? '');

    if ($ticket_id && !empty($pesan)) {
        $commentModel->addComment($ticket_id, $userId, $userNama, $userRole, $pesan);

        $tgMsg = "💬 *BALASAN TIKET HELPDESK* (`#{$nomor_tiket}`)\n\n"
               . "*• Dari:* {$userNama} (" . ucfirst($userRole) . ")\n"
               . "*• Pesan:* {$pesan}\n"
               . "*• Waktu:* " . date('d M Y, H:i:s');
        sendTelegramNotification($tgMsg);

        header("Location: helpdesk.php?tab=cek&nomor_tiket=" . urlencode($nomor_tiket) . "&diskusi=success");
        exit();
    }
}

$successTicket = null;
$searchedTicket = null;
$searchError = null;

// Handle Form Submission (Buat Tiket Baru)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_tiket'])) {
    $nama_pelapor = $userNama;
    $kontak_pelapor = trim($_POST['kontak_pelapor'] ?? '');
    $id_cabang = !empty($_POST['id_cabang']) ? intval($_POST['id_cabang']) : ($userCabangId ?? null);
    $id_divisi = !empty($_POST['id_divisi']) ? intval($_POST['id_divisi']) : null;
    $asset_id = !empty($_POST['asset_id']) ? intval($_POST['asset_id']) : null;
    $kode_aset = trim($_POST['kode_aset'] ?? '');
    $prioritas = $_POST['prioritas'] ?? 'Biasa';
    $keluhan = trim($_POST['keluhan'] ?? '');

    if (!empty($keluhan)) {
        $ticket_no = $ticketModel->create([
            'nama_pelapor' => $nama_pelapor,
            'kontak_pelapor' => $kontak_pelapor,
            'id_cabang' => $id_cabang,
            'id_divisi' => $id_divisi,
            'asset_id' => $asset_id,
            'kode_aset' => $kode_aset,
            'prioritas' => $prioritas,
            'keluhan' => $keluhan
        ]);

        if ($ticket_no) {
            $cabangNama = 'Semua Cabang';
            if ($id_cabang) {
                foreach ($branches as $b) {
                    if ($b['id'] == $id_cabang) {
                        $cabangNama = $b['nama_cabang'];
                        break;
                    }
                }
            }

            $prioEmoji = match($prioritas) {
                'Darurat' => '🔴 [DARURAT]',
                'Penting' => '🟡 [PENTING]',
                default => '🔵 [BIASA]'
            };

            $tgMsg = "🎫 *LAPORAN HELPDESK BARU*\n\n"
                   . "*• No. Tiket:* `#{$ticket_no}`\n"
                   . "*• Pelapor:* {$nama_pelapor}\n"
                   . "*• Kontak:* " . ($kontak_pelapor ?: '-') . "\n"
                   . "*• Cabang:* {$cabangNama}\n"
                   . "*• Perangkat:* " . ($kode_aset ?: 'Aset Umum') . "\n"
                   . "*• Prioritas:* {$prioEmoji}\n"
                   . "*• Keluhan:* {$keluhan}\n\n"
                   . "📌 _Silakan tindak lanjuti melalui Dashboard Helpdesk Admin._";
            sendTelegramNotification($tgMsg);

            header("Location: helpdesk.php?success_no=" . urlencode($ticket_no));
            exit();
        }
    }
}

if (isset($_GET['success_no'])) {
    $successTicket = $_GET['success_no'];
}

// Cek tiket pencarian
if (isset($_GET['nomor_tiket']) && !empty($_GET['nomor_tiket'])) {
    $num = trim($_GET['nomor_tiket']);
    $searchedTicket = $ticketModel->getByTicketNumber($num);
    if (!$searchedTicket) {
        $searchError = "Nomor tiket [{$num}] tidak ditemukan. Pastikan nomor tiket sudah benar.";
    }
}

// Fetch My Tickets
if (in_array($userRole, ['admin', 'teknisi'])) {
    $myTickets = $ticketModel->getAll();
} else {
    $myTickets = $ticketModel->getByReporterName($userNama);
}

// Stats for user
$myTotal = count($myTickets);
$myPending = count(array_filter($myTickets, fn($t) => $t['status'] === 'Menunggu'));
$myProcess = count(array_filter($myTickets, fn($t) => $t['status'] === 'Diproses'));
$myDone = count(array_filter($myTickets, fn($t) => $t['status'] === 'Selesai'));

$activeTab = $_GET['tab'] ?? (isset($_GET['nomor_tiket']) ? 'cek' : ($successTicket ? 'mytickets' : 'lapor'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Helpdesk Karyawan - Rekap IT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-main: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.75);
            --card-border: rgba(255, 255, 255, 0.1);
            --text-main: #f8fafc;
            --text-soft: #94a3b8;
            --accent-color: #6366f1;
            --accent-hover: #4f46e5;
        }

        body.light-theme {
            --bg-main: #f8fafc;
            --card-bg: #ffffff;
            --card-border: rgba(0, 0, 0, 0.08);
            --text-main: #0f172a;
            --text-soft: #64748b;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            margin: 0;
            padding-bottom: 50px;
        }

        .navbar-helpdesk {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--card-border);
            padding: 16px 24px;
        }

        .nav-tab-btn {
            border: 1px solid var(--card-border);
            background: rgba(255, 255, 255, 0.04);
            color: var(--text-soft);
            border-radius: 14px;
            padding: 10px 20px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        body.light-theme .nav-tab-btn {
            background: #ffffff;
            color: #64748b;
            border-color: #e2e8f0;
        }

        .nav-tab-btn.active, .nav-tab-btn:hover {
            background: var(--accent-color);
            color: #ffffff !important;
            border-color: var(--accent-color);
            box-shadow: 0 8px 16px -4px rgba(99, 102, 241, 0.4);
        }

        .card-custom {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.2);
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 20px;
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--card-border);
            color: var(--text-main);
            border-radius: 12px;
            padding: 12px 16px;
        }

        body.light-theme .form-control, body.light-theme .form-select {
            background: #fff;
            border-color: #cbd5e1;
            color: #0f172a;
        }

        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--accent-color);
            color: var(--text-main);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
        }

        .table-custom {
            color: var(--text-main);
        }

        .table-custom th {
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-soft);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--card-border);
            padding: 14px 16px;
        }

        .table-custom td {
            border-bottom: 1px solid var(--card-border);
            padding: 16px;
            vertical-align: middle;
        }

        .theme-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid var(--card-border);
            color: var(--text-main);
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar-helpdesk mb-4 sticky-top">
        <div class="container-fluid max-w-1200 mx-auto d-flex justify-content-between align-items-center flex-wrap gap-3" style="max-width: 1100px;">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-15 p-2.5 rounded-3 text-primary" style="background: rgba(99, 102, 241, 0.15);">
                    <i class="bi bi-headset fs-4 text-primary"></i>
                </div>
                <div>
                    <h5 class="fw-800 m-0">Portal Helpdesk IT</h5>
                    <small class="text-soft mb-0">Layanan Pelaporan Kendala IT Perusahaan</small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <button class="theme-btn me-1" onclick="toggleTheme()" title="Ubah Tema">
                    <i class="bi bi-moon-stars" id="themeIcon"></i>
                </button>

                <div class="d-flex align-items-center gap-2.5 ps-3 border-start" style="border-color: var(--card-border) !important;">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($userNama) ?>&background=6366f1&color=fff" class="rounded-circle border" width="38" height="38">
                    <div class="d-none d-sm-block">
                        <div class="fw-bold small m-0"><?= htmlspecialchars($userNama) ?></div>
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2 py-0.5" style="font-size: 0.65rem;"><?= ucfirst($userRole) ?></span>
                    </div>
                </div>

                <?php if (in_array($userRole, ['admin', 'teknisi'])): ?>
                    <a href="index.php?page=tiket_helpdesk" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold">
                        <i class="bi bi-speedometer2 me-1"></i> Admin Panel
                    </a>
                <?php endif; ?>

                <a href="logout.php?redirect=helpdesk" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold">
                    <i class="bi bi-box-arrow-right me-1"></i> Keluar
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container-fluid mx-auto" style="max-width: 1100px;">
        <!-- KPI Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <small class="text-soft fw-bold d-block mb-1">TOTAL TIKET SAYA</small>
                    <h3 class="fw-800 m-0 text-primary"><?= $myTotal ?></h3>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <small class="text-soft fw-bold d-block mb-1">MENUNGGU (PENDING)</small>
                    <h3 class="fw-800 m-0 text-warning"><?= $myPending ?></h3>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <small class="text-soft fw-bold d-block mb-1">SEDANG DIPROSES</small>
                    <h3 class="fw-800 m-0 text-info"><?= $myProcess ?></h3>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <small class="text-soft fw-bold d-block mb-1">SELESAI DITANGANI</small>
                    <h3 class="fw-800 m-0 text-success"><?= $myDone ?></h3>
                </div>
            </div>
        </div>

        <!-- Success Alert Banner -->
        <?php if ($successTicket): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-4 p-4 mb-4 text-center animate-fade-in" role="alert">
                <i class="bi bi-check-circle-fill fs-1 text-success d-block mb-2"></i>
                <h5 class="fw-800 mb-1">Laporan Tiket Berhasil Dikirim!</h5>
                <p class="small mb-3">Nomor Tiket Anda: <strong class="fs-4 text-primary">#<?= htmlspecialchars($successTicket) ?></strong></p>
                <p class="small text-soft mb-3">Tiket Anda telah tersimpan dan siap ditindaklanjuti oleh teknisi IT.</p>
                <button onclick="switchTab('mytickets')" class="btn btn-primary px-4 fw-bold rounded-3">
                    <i class="bi bi-list-task me-1"></i> Lihat Tiket Saya
                </button>
            </div>
        <?php endif; ?>

        <!-- Tab Navigation Buttons -->
        <div class="d-flex gap-2 mb-4 flex-wrap">
            <button class="nav-tab-btn <?= $activeTab === 'lapor' ? 'active' : '' ?>" id="btn-tab-lapor" onclick="switchTab('lapor')">
                <i class="bi bi-plus-circle me-1.5"></i> Buat Tiket Baru
            </button>
            <button class="nav-tab-btn <?= $activeTab === 'mytickets' ? 'active' : '' ?>" id="btn-tab-mytickets" onclick="switchTab('mytickets')">
                <i class="bi bi-person-workspace me-1.5"></i> Tiket Saya (<?= $myTotal ?>)
            </button>
            <button class="nav-tab-btn <?= $activeTab === 'cek' ? 'active' : '' ?>" id="btn-tab-cek" onclick="switchTab('cek')">
                <i class="bi bi-search me-1.5"></i> Cari No. Tiket
            </button>
        </div>

        <!-- TAB 1: FORM BUAT TIKET -->
        <div id="tab-lapor" style="display: <?= $activeTab === 'lapor' ? 'block' : 'none' ?>;">
            <div class="card-custom p-4 p-md-5 mb-4">
                <h5 class="fw-800 mb-1"><i class="bi bi-pencil-square text-primary me-2"></i>Form Laporan Kendala IT</h5>
                <p class="text-soft small mb-4">Isi keluhan masalah komputer, jaringan, printer, atau software yang Anda alami.</p>

                <form method="POST">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-soft">Nama Pelapor</label>
                            <input type="text" class="form-control bg-opacity-10" value="<?= htmlspecialchars($userNama) ?>" readonly disabled style="opacity: 0.8;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-soft">No. HP / WhatsApp (Opsional)</label>
                            <input type="text" name="kontak_pelapor" class="form-control" placeholder="Contoh: 081234567890">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-soft">Kantor Cabang</label>
                            <select name="id_cabang" class="form-select">
                                <option value="">Semua Cabang</option>
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?= $b['id'] ?>" <?= ($userCabangId == $b['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($b['nama_cabang']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-soft">Divisi / Bagian</label>
                            <select name="id_divisi" class="form-select">
                                <option value="">-- Pilih Divisi --</option>
                                <?php foreach ($divisis as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nama_divisi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-soft">Pilih Perangkat / Kode Aset (Opsional)</label>
                            <select name="asset_id" class="form-select" onchange="updateKodeAset(this)">
                                <option value="">-- Pilih Perangkat Rusak --</option>
                                <?php foreach ($assets as $a): ?>
                                    <option value="<?= $a['id'] ?>" data-kode="<?= htmlspecialchars($a['kode_aset'] . ' - ' . $a['nama_barang']) ?>">
                                        <?= htmlspecialchars($a['kode_aset'] . ' - ' . $a['nama_barang']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="kode_aset" id="input_kode_aset">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-soft">Tingkat Prioritas</label>
                            <select name="prioritas" class="form-select">
                                <option value="Biasa" selected>🔵 Biasa (Bisa ditunggu)</option>
                                <option value="Penting">🟡 Penting (Mengganggu pekerjaan)</option>
                                <option value="Darurat">🔴 Darurat (Pekerjaan terhenti total)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-soft">Deskripsi Keluhan / Kendala <span class="text-danger">*</span></label>
                        <textarea name="keluhan" class="form-control" rows="4" placeholder="Jelaskan secara rinci kendala perangkat yang Anda alami..." required></textarea>
                    </div>

                    <button type="submit" name="kirim_tiket" class="btn btn-primary px-5 py-3 fw-bold rounded-3">
                        <i class="bi bi-send-fill me-2"></i> Kirim Laporan Tiket
                    </button>
                </form>
            </div>
        </div>

        <!-- TAB 2: TIKET SAYA -->
        <div id="tab-mytickets" style="display: <?= $activeTab === 'mytickets' ? 'block' : 'none' ?>;">
            <div class="card-custom p-4 mb-4">
                <h5 class="fw-800 mb-1"><i class="bi bi-list-task text-primary me-2"></i>Riwayat Tiket Saya</h5>
                <p class="text-soft small mb-4">Daftar keluhan yang pernah Anda sampaikan beserta status dan catatan teknisi.</p>

                <?php if (empty($myTickets)): ?>
                    <div class="text-center py-5 text-soft">
                        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                        <p class="mb-0">Anda belum pernah membuat tiket kendala IT.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>No. Tiket</th>
                                    <th>Tanggal</th>
                                    <th>Perangkat / Keluhan</th>
                                    <th>Prioritas</th>
                                    <th>Status</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myTickets as $mt): 
                                    $stBadge = match($mt['status']) {
                                        'Menunggu' => 'bg-warning text-dark',
                                        'Diproses' => 'bg-info text-white',
                                        'Selesai' => 'bg-success text-white',
                                        'Ditolak' => 'bg-danger text-white',
                                        default => 'bg-secondary text-white'
                                    };
                                ?>
                                    <tr>
                                        <td><code class="fw-bold text-primary">#<?= htmlspecialchars($mt['nomor_tiket']) ?></code></td>
                                        <td class="small text-soft"><?= date('d/m/Y H:i', strtotime($mt['created_at'])) ?></td>
                                        <td>
                                            <div class="fw-bold small mb-0"><?= htmlspecialchars($mt['kode_aset'] ?: 'Kendala Umum') ?></div>
                                            <div class="small text-soft text-truncate" style="max-width: 250px;"><?= htmlspecialchars($mt['keluhan']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary bg-opacity-20 text-soft rounded-pill px-2.5 py-1" style="font-size: 0.75rem;">
                                                <?= htmlspecialchars($mt['prioritas']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $stBadge ?> rounded-pill px-3 py-1.5 fw-bold" style="font-size: 0.75rem;">
                                                <?= htmlspecialchars($mt['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="helpdesk.php?tab=cek&nomor_tiket=<?= urlencode($mt['nomor_tiket']) ?>" class="btn btn-sm btn-outline-primary rounded-3 px-3">
                                                <i class="bi bi-eye me-1"></i> Detail & Balas
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB 3: CEK TIKET DETAIL & DISKUSI -->
        <div id="tab-cek" style="display: <?= $activeTab === 'cek' ? 'block' : 'none' ?>;">
            <div class="card-custom p-4 mb-4">
                <form method="GET" action="helpdesk.php" class="row g-3 align-items-center">
                    <input type="hidden" name="tab" value="cek">
                    <div class="col-md-9">
                        <div class="position-relative">
                            <i class="bi bi-search position-absolute top-50 start-3 translate-middle-y text-soft" style="left: 16px;"></i>
                            <input type="text" name="nomor_tiket" class="form-control ps-5" placeholder="Masukkan nomor tiket (Contoh: TKT-20260723-001)..." value="<?= htmlspecialchars($_GET['nomor_tiket'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold rounded-3">
                            <i class="bi bi-search me-1"></i> Cari Tiket
                        </button>
                    </div>
                </form>
            </div>

            <?php if ($searchError): ?>
                <div class="alert alert-warning border-0 shadow-sm rounded-4 p-3 mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($searchError) ?>
                </div>
            <?php endif; ?>

            <?php if ($searchedTicket): 
                $t = $searchedTicket;
                $stBadge = match($t['status']) {
                    'Menunggu' => 'bg-warning text-dark',
                    'Diproses' => 'bg-info text-white',
                    'Selesai' => 'bg-success text-white',
                    'Ditolak' => 'bg-danger text-white',
                    default => 'bg-secondary text-white'
                };
            ?>
                <div class="card-custom p-4 p-md-5 mb-5">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 pb-3 border-bottom" style="border-color: var(--card-border) !important;">
                        <div>
                            <span class="badge bg-primary bg-opacity-15 text-primary rounded-pill px-3 py-1.5 fw-bold mb-2">
                                #<?= htmlspecialchars($t['nomor_tiket']) ?>
                            </span>
                            <h5 class="fw-800 m-0">Detail Status & Percakapan Tiket</h5>
                        </div>
                        <span class="badge <?= $stBadge ?> rounded-pill px-3.5 py-2 fw-bold fs-6">
                            <?= htmlspecialchars($t['status']) ?>
                        </span>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <small class="text-soft fw-bold d-block mb-1">DATA PELAPOR</small>
                            <h6 class="fw-bold m-0"><?= htmlspecialchars($t['nama_pelapor']) ?></h6>
                            <p class="text-soft small mb-0"><?= htmlspecialchars($t['nama_cabang'] ?? 'Cabang N/A') ?></p>
                        </div>
                        <div class="col-md-6">
                            <small class="text-soft fw-bold d-block mb-1">PERANGKAT & TANGGAL</small>
                            <h6 class="fw-bold m-0"><?= htmlspecialchars($t['kode_aset'] ?: 'Perangkat N/A') ?></h6>
                            <p class="text-soft small mb-0">Dilaporkan: <?= date('d M Y, H:i', strtotime($t['created_at'])) ?> WIB</p>
                        </div>
                    </div>

                    <div class="mb-4">
                        <small class="text-soft fw-bold d-block mb-1">KELUHAN AWAL</small>
                        <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.03); border: 1px solid var(--card-border);">
                            <?= nl2br(htmlspecialchars($t['keluhan'])) ?>
                        </div>
                    </div>

                    <div class="p-3 rounded-3 mb-4" style="background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.2);">
                        <small class="text-soft fw-bold d-block mb-1">TINDAKAN TEKNISI</small>
                        <p class="small mb-0"><strong><?= htmlspecialchars($t['teknisi_penanggung_jawab'] ?: 'Tim IT') ?>:</strong> <?= nl2br(htmlspecialchars($t['tindakan_teknisi'] ?: 'Sedang dalam antrean penanganan.')) ?></p>
                    </div>

                    <!-- Diskusi Thread -->
                    <div class="mt-4 pt-3 border-top" style="border-color: var(--card-border) !important;">
                        <h6 class="fw-bold mb-3"><i class="bi bi-chat-left-text text-primary me-2"></i>Diskusi & Catatan Tambahan</h6>
                        <?php 
                        $ticketComments = $commentModel->getByTicketId($t['id']);
                        ?>
                        <?php if (empty($ticketComments)): ?>
                            <p class="small text-soft mb-3 fst-italic">Belum ada percakapan tambahan pada tiket ini.</p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3 mb-4">
                                <?php foreach ($ticketComments as $tc): 
                                    $isTech = in_array($tc['sender_role'], ['admin', 'teknisi']);
                                    $bubbleBg = $isTech ? 'background: rgba(99, 102, 241, 0.12); border: 1px solid rgba(99, 102, 241, 0.25);' : 'background: rgba(255, 255, 255, 0.04); border: 1px solid var(--card-border);';
                                    $badgeColor = $isTech ? 'bg-primary text-white' : 'bg-secondary text-white';
                                ?>
                                    <div class="p-3 rounded-3" style="max-width: 90%; <?= $isTech ? 'margin-left: auto;' : 'margin-right: auto;' ?> <?= $bubbleBg ?>">
                                        <div class="d-flex justify-content-between align-items-center mb-1 gap-3">
                                            <small class="fw-bold"><?= htmlspecialchars($tc['sender_name']) ?> <span class="badge <?= $badgeColor ?> rounded-pill ms-1" style="font-size: 0.65rem;"><?= ucfirst($tc['sender_role']) ?></span></small>
                                            <small class="text-soft" style="font-size: 0.7rem;"><?= date('d/m H:i', strtotime($tc['created_at'])) ?></small>
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
                                <button type="submit" name="kirim_diskusi" class="btn btn-primary px-4 fw-bold rounded-end-3">
                                    <i class="bi bi-send me-1"></i> Kirim Pesan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const savedTheme = localStorage.getItem('theme-pref') || 'dark';
        if (savedTheme === 'light') {
            document.body.classList.add('light-theme');
            document.getElementById('themeIcon').className = 'bi bi-sun-fill';
        }

        function toggleTheme() {
            document.body.classList.toggle('light-theme');
            const isLight = document.body.classList.contains('light-theme');
            localStorage.setItem('theme-pref', isLight ? 'light' : 'dark');
            document.getElementById('themeIcon').className = isLight ? 'bi bi-sun-fill' : 'bi bi-moon-stars';
        }

        function updateKodeAset(select) {
            const opt = select.options[select.selectedIndex];
            document.getElementById('input_kode_aset').value = opt.getAttribute('data-kode') || '';
        }

        function switchTab(tabName) {
            document.getElementById('tab-lapor').style.display = (tabName === 'lapor') ? 'block' : 'none';
            document.getElementById('tab-mytickets').style.display = (tabName === 'mytickets') ? 'block' : 'none';
            document.getElementById('tab-cek').style.display = (tabName === 'cek') ? 'block' : 'none';

            document.getElementById('btn-tab-lapor').classList.toggle('active', tabName === 'lapor');
            document.getElementById('btn-tab-mytickets').classList.toggle('active', tabName === 'mytickets');
            document.getElementById('btn-tab-cek').classList.toggle('active', tabName === 'cek');
        }
    </script>
</body>
</html>
