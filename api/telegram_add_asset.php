<?php
require_once __DIR__ . '/../config/database.php';

// Fetch options for select fields
$categories = $conn->query("SELECT id, nama_kategori FROM kategori_aset ORDER BY nama_kategori ASC")->fetchAll();
$branches = $conn->query("SELECT id, nama_cabang FROM cabang ORDER BY nama_cabang ASC")->fetchAll();
$divisions = $conn->query("SELECT id, nama_divisi FROM divisi ORDER BY nama_divisi ASC")->fetchAll();
$employees = $conn->query("SELECT id, nama_karyawan, id_cabang FROM karyawan ORDER BY nama_karyawan ASC")->fetchAll();

$success = false;
$error = '';
$newCode = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode = trim($_POST['kode_aset'] ?? '');
    $nama = trim($_POST['nama_aset'] ?? '');
    $sn = trim($_POST['serial_number'] ?? '');
    $id_kategori = !empty($_POST['id_kategori']) ? intval($_POST['id_kategori']) : null;
    $merk = trim($_POST['merk'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $id_cabang = !empty($_POST['id_cabang']) ? intval($_POST['id_cabang']) : null;
    $id_divisi = !empty($_POST['id_divisi']) ? intval($_POST['id_divisi']) : null;
    $id_karyawan = !empty($_POST['id_karyawan']) ? intval($_POST['id_karyawan']) : null;
    $spesifikasi = trim($_POST['spesifikasi'] ?? '');

    if (empty($kode) || empty($nama) || empty($id_cabang)) {
        $error = 'Kode, Nama, dan Cabang wajib diisi!';
    } else {
        // Check duplicate code
        $dupStmt = $conn->prepare("SELECT id FROM assets WHERE kode_aset = ?");
        $dupStmt->execute([$kode]);
        if ($dupStmt->fetch()) {
            $error = "Kode Aset '{$kode}' sudah terdaftar!";
        } else {
            $conn->beginTransaction();
            try {
                // Insert asset
                $insertStmt = $conn->prepare("INSERT INTO assets (kode_aset, nama_aset, serial_number, id_kategori, merk, model, id_cabang, id_divisi, id_karyawan, spesifikasi, kondisi, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Baik', datetime('now', 'localtime'))");
                $insertStmt->execute([$kode, $nama, $sn, $id_kategori, $merk, $model, $id_cabang, $id_divisi, $id_karyawan, $spesifikasi]);
                
                // Get name details for telegram message broadcast
                $catName = '';
                if ($id_kategori) {
                    $cVal = $conn->query("SELECT nama_kategori FROM kategori_aset WHERE id = $id_kategori")->fetchColumn();
                    $catName = $cVal ?: '';
                }
                $brName = '';
                if ($id_cabang) {
                    $brVal = $conn->query("SELECT nama_cabang FROM cabang WHERE id = $id_cabang")->fetchColumn();
                    $brName = $brVal ?: '';
                }
                $divName = '';
                if ($id_divisi) {
                    $divVal = $conn->query("SELECT nama_divisi FROM divisi WHERE id = $id_divisi")->fetchColumn();
                    $divName = $divVal ?: '';
                }
                $usrName = '';
                if ($id_karyawan) {
                    $usrVal = $conn->query("SELECT nama_karyawan FROM karyawan WHERE id = $id_karyawan")->fetchColumn();
                    $usrName = $usrVal ?: '';
                }

                $conn->commit();
                $success = true;
                $newCode = $kode;

                // Send security notification to Telegram
                $token = getenv('TELEGRAM_BOT_TOKEN') ?: ($_ENV['TELEGRAM_BOT_TOKEN'] ?? ($_SERVER['TELEGRAM_BOT_TOKEN'] ?? ''));
                $chatId = getenv('TELEGRAM_CHAT_ID') ?: ($_ENV['TELEGRAM_CHAT_ID'] ?? ($_SERVER['TELEGRAM_CHAT_ID'] ?? ''));
                if (!empty($token) && !empty($chatId)) {
                    $url = "https://api.telegram.org/bot" . $token . "/sendMessage";
                    $messageText = "➕ *ASET BARU DITAMBAHKAN VIA TELEGRAM WEBAPP*\n\n"
                                 . "*• Kode Aset:* `{$kode}`\n"
                                 . "*• Nama Aset:* {$nama}\n"
                                 . "*• Serial Number:* `{$sn}`\n"
                                 . "*• Kategori:* {$catName}\n"
                                 . "*• Merk/Model:* {$merk} / {$model}\n"
                                 . "*• Cabang:* {$brName}\n"
                                 . "*• Divisi:* {$divName}\n"
                                 . "*• Pengguna:* {$usrName}\n"
                                 . "*• Kondisi:* 🟢 Baik";
                                 
                    $postData = [
                        'chat_id' => $chatId,
                        'text' => $messageText,
                        'parse_mode' => 'Markdown'
                    ];
                    $options = [
                        'http' => [
                            'method'  => 'POST',
                            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                            'content' => http_build_query($postData),
                            'timeout' => 5
                        ],
                        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
                    ];
                    @file_get_contents($url, false, stream_context_create($options));
                }
            } catch (Exception $e) {
                $conn->rollBack();
                $error = "Gagal database: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Aset</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap");
        
        body {
            font-family: "Plus Jakarta Sans", sans-serif;
            background-color: #0b0f19;
            color: #f8fafc;
            padding: 16px;
            font-size: 0.9rem;
        }
        .form-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(15px);
        }
        .form-control, .form-select {
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #ffffff;
            border-radius: 12px;
            padding: 10px 14px;
        }
        .form-control:focus, .form-select:focus {
            background-color: rgba(255, 255, 255, 0.08);
            border-color: #6366f1;
            color: #ffffff;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
        }
        .form-label {
            font-weight: 600;
            color: #94a3b8;
            margin-bottom: 6px;
        }
        .btn-primary {
            background-color: #6366f1;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 700;
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            background-color: #4f46e5;
        }
        .success-box {
            text-align: center;
            padding: 40px 20px;
        }
        .success-icon {
            font-size: 4rem;
            color: #10b981;
            animation: bounce 0.6s ease-out;
        }
        @keyframes bounce {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.1); }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php if ($success): ?>
            <div class="success-box animate-fade-in">
                <i class="bi bi-check-circle-fill success-icon mb-3 d-block"></i>
                <h4 class="fw-bold">Aset Berhasil Disimpan!</h4>
                <p class="text-muted small">Kode Aset: <strong class="text-white"><?= htmlspecialchars($newCode) ?></strong></p>
                <p class="text-muted small mb-4">Aset telah terdaftar di database RekapIT secara real-time.</p>
                <button class="btn btn-outline-light w-100" onclick="closeWebApp()" style="border-radius: 12px;">Selesai & Tutup</button>
            </div>
        <?php else: ?>
            <div class="form-card">
                <h5 class="fw-bold text-center mb-3">➕ TAMBAH ASET BARU</h5>
                <?php if ($error): ?>
                    <div class="alert alert-danger border-0 small py-2.5 rounded-3 mb-3" style="background: rgba(239, 68, 68, 0.1); color: #f87171;">
                        <i class="bi bi-exclamation-triangle me-1.5"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form id="addAssetForm" method="POST">
                    <div class="mb-3">
                        <label class="form-label small">Kategori</label>
                        <select name="id_kategori" id="id_kategori" class="form-select" onchange="autoGenerateCode(this.value)">
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nama_kategori']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Kode Aset <span class="text-danger">*</span></label>
                        <input type="text" name="kode_aset" id="kode_aset" class="form-control" required placeholder="Pilih Kategori untuk Auto-generate">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Nama Aset / Perangkat <span class="text-danger">*</span></label>
                        <input type="text" name="nama_aset" class="form-control" required placeholder="Contoh: MacBook Pro M3 Max">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Serial Number (SN)</label>
                        <input type="text" name="serial_number" class="form-control" placeholder="Contoh: SN12345678">
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label small">Merk</label>
                            <input type="text" name="merk" class="form-control" placeholder="Contoh: Apple">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label small">Model / Seri</label>
                            <input type="text" name="model" class="form-control" placeholder="Contoh: A2918">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Kantor Cabang <span class="text-danger">*</span></label>
                        <select name="id_cabang" id="id_cabang" class="form-select" required onchange="filterKaryawan(this.value)">
                            <option value="">-- Pilih Cabang --</option>
                            <?php foreach ($branches as $br): ?>
                                <option value="<?= $br['id'] ?>"><?= htmlspecialchars($br['nama_cabang']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Divisi</label>
                        <select name="id_divisi" class="form-select">
                            <option value="">-- Pilih Divisi --</option>
                            <?php foreach ($divisions as $div): ?>
                                <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['nama_divisi']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Pengguna (Karyawan)</label>
                        <select name="id_karyawan" id="id_karyawan" class="form-select">
                            <option value="">-- Pilih Karyawan --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>" data-cabang="<?= $emp['id_cabang'] ?>"><?= htmlspecialchars($emp['nama_karyawan']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small">Spesifikasi</label>
                        <textarea name="spesifikasi" class="form-control" rows="2" placeholder="Detail RAM, SSD, Processor..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-2">💾 Simpan Aset Baru</button>
                    <button type="button" class="btn btn-outline-secondary w-100" onclick="closeWebApp()">Batal</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Init Telegram WebApp SDK
        Telegram.WebApp.ready();
        Telegram.WebApp.expand();

        function closeWebApp() {
            Telegram.WebApp.close();
        }

        // Auto code generation logic
        function autoGenerateCode(catId) {
            if (!catId) return;
            fetch(`generate_asset_code.php?kategori_id=${catId}`)
                .then(r => r.json())
                .then(d => {
                    if (d.success && d.code) {
                        document.getElementById('kode_aset').value = d.code;
                    }
                });
        }

        // Dropdown dynamic employee filtering
        function filterKaryawan(cabangId) {
            const selectEl = document.getElementById('id_karyawan');
            const options = selectEl.querySelectorAll('option');
            selectEl.value = "";
            options.forEach(opt => {
                const optCabang = opt.getAttribute('data-cabang');
                if (!optCabang) {
                    opt.style.display = 'block';
                } else {
                    opt.style.display = (optCabang == cabangId) ? 'block' : 'none';
                }
            });
        }
    </script>
</body>
</html>
