<?php
require_once 'models/HelpdeskTicket.php';
require_once 'models/HelpdeskComment.php';
require_once 'models/Cabang.php';
require_once 'helpers/notification.php';

$ticketModel = new HelpdeskTicket($conn);
$commentModel = new HelpdeskComment($conn);
$cabangModel = new Cabang($conn);

$branches = $cabangModel->getAll();

// Handle Balasan Pesan dari Admin / Teknisi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_diskusi_admin'])) {
    $ticket_id = intval($_POST['ticket_id']);
    $pesan = trim($_POST['pesan_diskusi'] ?? '');
    $nomor_tiket = trim($_POST['nomor_tiket'] ?? '');

    if ($ticket_id && !empty($pesan)) {
        $commentModel->addComment($ticket_id, $_SESSION['user_id'] ?? null, $_SESSION['nama'] ?? 'Teknisi IT', $_SESSION['role'] ?? 'teknisi', $pesan);

        $tgMsg = "💬 *BALASAN TEKNISI IT* (`#{$nomor_tiket}`)\n\n"
               . "*• Dari:* " . ($_SESSION['nama'] ?? 'Teknisi IT') . " (Teknisi)\n"
               . "*• Pesan:* {$pesan}\n"
               . "*• Waktu:* " . date('d M Y, H:i:s');
        sendTelegramNotification($tgMsg);

        header("Location: index.php?page=tiket_helpdesk&status=comment_added");
        exit();
    }
}

// Handle Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ticket_status'])) {
    $id = intval($_POST['id']);
    $status = $_POST['status'];
    $teknisi = trim($_POST['teknisi'] ?? '');
    $tindakan = trim($_POST['tindakan_teknisi'] ?? '');

    if ($ticketModel->updateStatus($id, $status, $teknisi, $tindakan)) {
        header("Location: index.php?page=tiket_helpdesk&status=updated");
        exit();
    }
}

// Filtering
$statusFilter = $_GET['filter_status'] ?? null;
$cabangFilter = !empty($_GET['filter_cabang']) ? intval($_GET['filter_cabang']) : null;
$searchQuery = $_GET['search'] ?? null;

$tickets = $ticketModel->getAll($statusFilter, $cabangFilter, $searchQuery);

// KPI Stats
$allTickets = $ticketModel->getAll();
$pendingCount = count(array_filter($allTickets, fn($t) => $t['status'] === 'Menunggu'));
$processCount = count(array_filter($allTickets, fn($t) => $t['status'] === 'Diproses'));
$doneCount = count(array_filter($allTickets, fn($t) => $t['status'] === 'Selesai'));
?>

<div class="container-fluid animate-fade-in">
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center">
            <div class="bg-primary bg-opacity-10 p-2.5 rounded-3 me-3 text-primary">
                <i class="bi bi-headset fs-4"></i>
            </div>
            <div>
                <h4 class="fw-800 m-0">Manajemen Tiket Helpdesk</h4>
                <p class="text-muted small m-0">Kelola dan tindak lanjuti laporan kendala IT yang disampaikan oleh karyawan.</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary shadow-sm" id="btnCopyHelpdeskLink" onclick="copyHelpdeskLink()">
                <i class="bi bi-link-45deg me-1"></i> Salin Link Portal Karyawan
            </button>
            <a href="index.php?page=helpdesk" target="_blank" class="btn btn-outline-primary shadow-sm">
                <i class="bi bi-box-arrow-up-right me-1"></i> Buka Portal Publik
            </a>
        </div>
    </div>

    <!-- Alert Notification -->
    <?php if (isset($_GET['status']) && $_GET['status'] === 'updated'): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 p-3 mb-4 d-flex align-items-center justify-content-between animate-fade-in" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <span class="small fw-semibold">Status tiket helpdesk berhasil diperbarui!</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- KPI Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); text-color: #fff;">
                <div class="card-body p-3.5 text-white">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3rem; transform: translate(10%, -10%);">
                        <i class="bi bi-ticket-perforated"></i>
                    </div>
                    <span class="small fw-bold opacity-75 text-xs">TOTAL TIKET</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= count($allTickets) ?></h3>
                    <small class="opacity-70 d-block mt-1">Seluruh laporan masuk</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); text-color: #fff;">
                <div class="card-body p-3.5 text-white">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3rem; transform: translate(10%, -10%);">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <span class="small fw-bold opacity-75 text-xs">MENUNGGU (PENDING)</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $pendingCount ?></h3>
                    <small class="opacity-70 d-block mt-1">Perlu penanganan teknisi</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); text-color: #fff;">
                <div class="card-body p-3.5 text-white">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3rem; transform: translate(10%, -10%);">
                        <i class="bi bi-gear-wide-connected"></i>
                    </div>
                    <span class="small fw-bold opacity-75 text-xs">SEDANG DIPROSES</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $processCount ?></h3>
                    <small class="opacity-70 d-block mt-1">Dalam proses perbaikan</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); text-color: #fff;">
                <div class="card-body p-3.5 text-white">
                    <div class="position-absolute top-0 end-0 p-3 opacity-20" style="font-size: 3rem; transform: translate(10%, -10%);">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <span class="small fw-bold opacity-75 text-xs">TIKET SELESAI</span>
                    <h3 class="fw-800 mb-0 mt-1"><?= $doneCount ?></h3>
                    <small class="opacity-70 d-block mt-1">Selesai ditangani</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <!-- Table Toolbar & Filters -->
        <div class="p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-3" style="border-color: var(--card-border) !important;">
            <form method="GET" action="index.php" class="d-flex align-items-center gap-2 flex-grow-1 flex-md-grow-0">
                <input type="hidden" name="page" value="tiket_helpdesk">
                
                <div class="position-relative flex-grow-1" style="min-width: 220px;">
                    <i class="bi bi-search position-absolute top-50 start-3 translate-middle-y text-muted" style="left: 12px; pointer-events: none;"></i>
                    <input type="text" name="search" class="form-control ps-5" placeholder="Cari pelapor/tiket..." value="<?= htmlspecialchars($searchQuery ?? '') ?>" style="font-size: 0.85rem; height: 38px;">
                </div>

                <select name="filter_status" class="form-select" style="width: 160px; font-size: 0.85rem; height: 38px;" onchange="this.form.submit()">
                    <option value="">-- Semua Status --</option>
                    <option value="Menunggu" <?= ($statusFilter === 'Menunggu') ? 'selected' : '' ?>>Menunggu</option>
                    <option value="Diproses" <?= ($statusFilter === 'Diproses') ? 'selected' : '' ?>>Diproses</option>
                    <option value="Selesai" <?= ($statusFilter === 'Selesai') ? 'selected' : '' ?>>Selesai</option>
                    <option value="Ditolak" <?= ($statusFilter === 'Ditolak') ? 'selected' : '' ?>>Ditolak</option>
                </select>

                <select name="filter_cabang" class="form-select" style="width: 170px; font-size: 0.85rem; height: 38px;" onchange="this.form.submit()">
                    <option value="">-- Semua Cabang --</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= ($cabangFilter == $b['id']) ? 'selected' : '' ?>><?= htmlspecialchars($b['nama_cabang']) ?></option>
                    <?php endforeach; ?>
                </select>

                <a href="index.php?page=tiket_helpdesk" class="btn btn-secondary p-2" title="Reset Filter" style="height: 38px; width: 38px; display: inline-flex; align-items: center; justify-content: center;">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">No. Tiket</th>
                            <th>Pelapor & Cabang</th>
                            <th>Perangkat Bermasalah</th>
                            <th>Prioritas</th>
                            <th>Keluhan</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Aksi Penanganan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tickets)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <div class="bg-light bg-opacity-10 text-secondary rounded-circle d-inline-flex p-3 mb-3">
                                        <i class="bi bi-ticket-perforated fs-3"></i>
                                    </div>
                                    <p class="small fw-semibold mb-0">Belum ada tiket helpdesk yang cocok.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tickets as $t): 
                                $prioBadge = match($t['prioritas']) {
                                    'Darurat' => 'bg-danger text-white',
                                    'Penting' => 'bg-warning text-dark',
                                    default => 'bg-info bg-opacity-10 text-info'
                                };
                                $statusBadge = match($t['status']) {
                                    'Menunggu' => 'bg-warning text-dark',
                                    'Diproses' => 'bg-info text-white',
                                    'Selesai' => 'bg-success text-white',
                                    'Ditolak' => 'bg-danger text-white',
                                    default => 'bg-secondary text-white'
                                };
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <strong class="text-primary">#<?= htmlspecialchars($t['nomor_tiket']) ?></strong>
                                    <div class="small text-muted" style="font-size: 0.75rem;"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></div>
                                </td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($t['nama_pelapor']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($t['nama_cabang'] ?? 'Cabang N/A') ?></small>
                                </td>
                                <td>
                                    <span class="small fw-semibold"><?= htmlspecialchars($t['kode_aset'] ?: 'Perangkat N/A') ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= $prioBadge ?> rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">
                                        <?= htmlspecialchars($t['prioritas']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($t['keluhan']) ?>">
                                        <?= htmlspecialchars($t['keluhan']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= $statusBadge ?> rounded-pill px-2.5 py-1" style="font-size: 0.75rem;">
                                        <?= htmlspecialchars($t['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-info rounded-3 px-2.5 py-1.5 me-1" onclick='openDiscussionModal(<?= json_encode($t) ?>, <?= json_encode($commentModel->getByTicketId($t['id'])) ?>)'>
                                        <i class="bi bi-chat-left-text me-1"></i> Diskusi
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary rounded-3 px-2.5 py-1.5" onclick='openProcessModal(<?= json_encode($t) ?>)'>
                                        <i class="bi bi-pencil-square me-1"></i> Update Status
                                    </button>
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

<!-- Modal Update Status Tiket -->
<div class="modal fade" id="modalProcessTicket" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <input type="hidden" name="id" id="ticket_id">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-800 m-0"><i class="bi bi-tools text-primary me-2"></i>Penanganan Tiket <span id="ticket_number_title" class="text-primary"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Status Tiket</label>
                        <select name="status" id="ticket_status" class="form-select" required>
                            <option value="Menunggu">🟡 Menunggu (Pending)</option>
                            <option value="Diproses">🔵 Diproses (Sedang Ditangani)</option>
                            <option value="Selesai">🟢 Selesai (Perbaikan Tuntas)</option>
                            <option value="Ditolak">🔴 Ditolak / Dibatalkan</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Teknisi Penanggung Jawab</label>
                        <input type="text" name="teknisi" id="ticket_teknisi" class="form-control" placeholder="Nama teknisi penanggung jawab..." value="<?= htmlspecialchars($_SESSION['nama'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Tindakan & Catatan Perbaikan</label>
                        <textarea name="tindakan_teknisi" id="ticket_tindakan" class="form-control" rows="3" placeholder="Tuliskan tindakan perbaikan atau alasan jika ditolak..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="update_ticket_status" class="btn btn-primary px-4">Simpan Perubahan</button>
                </div>
<!-- Modal Diskusi Tiket Admin -->
<div class="modal fade" id="modalDiscussion" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px;">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-800 m-0"><i class="bi bi-chat-left-text text-primary me-2"></i>Diskusi & Catatan Tiket <span id="disc_ticket_number" class="text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="p-3 rounded-3 mb-3" style="background: rgba(255,255,255,0.03); border: 1px solid var(--card-border);">
                    <small class="text-muted fw-bold d-block mb-1">KELUHAN / LAPORAN AWAL:</small>
                    <div id="disc_ticket_keluhan" class="small fw-semibold"></div>
                </div>

                <div id="disc_comments_container" class="d-flex flex-column gap-2.5 mb-3" style="max-height: 280px; overflow-y: auto;">
                    <!-- Filled by JS -->
                </div>

                <form method="POST" class="mt-3">
                    <input type="hidden" name="ticket_id" id="disc_ticket_id">
                    <input type="hidden" name="nomor_tiket" id="disc_nomor_tiket">
                    <div class="input-group">
                        <input type="text" name="pesan_diskusi" class="form-control" placeholder="Balas ke karyawan / tulis catatan teknisi..." required>
                        <button type="submit" name="kirim_diskusi_admin" class="btn btn-primary px-4 fw-bold">
                            <i class="bi bi-send me-1"></i> Balas Pesan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openProcessModal(ticket) {
    document.getElementById('ticket_id').value = ticket.id;
    document.getElementById('ticket_number_title').innerText = '#' + ticket.nomor_tiket;
    document.getElementById('ticket_status').value = ticket.status;
    document.getElementById('ticket_teknisi').value = ticket.teknisi_penanggung_jawab || '<?= htmlspecialchars($_SESSION['nama'] ?? '') ?>';
    document.getElementById('ticket_tindakan').value = ticket.tindakan_teknisi || '';

    new bootstrap.Modal(document.getElementById('modalProcessTicket')).show();
}

function openDiscussionModal(ticket, comments) {
    document.getElementById('disc_ticket_id').value = ticket.id;
    document.getElementById('disc_nomor_tiket').value = ticket.nomor_tiket;
    document.getElementById('disc_ticket_number').innerText = '#' + ticket.nomor_tiket;
    document.getElementById('disc_ticket_keluhan').innerText = ticket.keluhan;

    const container = document.getElementById('disc_comments_container');
    container.innerHTML = '';

    if (!comments || comments.length === 0) {
        container.innerHTML = '<p class="small text-muted mb-0 fst-italic">Belum ada diskusi atau balasan pesan.</p>';
    } else {
        comments.forEach(c => {
            const isTech = (c.sender_role === 'admin' || c.sender_role === 'teknisi');
            const align = isTech ? 'margin-left: auto;' : 'margin-right: auto;';
            const bg = isTech ? 'background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.25);' : 'background: rgba(255,255,255,0.04); border: 1px solid var(--card-border);';
            const badge = isTech ? 'bg-primary text-white' : 'bg-secondary text-white';

            const div = document.createElement('div');
            div.className = 'p-3 rounded-3';
            div.style.cssText = `max-width: 88%; ${align} ${bg}`;
            div.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-1 gap-3">
                    <small class="fw-bold">${escapeHtml(c.sender_name)} <span class="badge ${badge} rounded-pill ms-1" style="font-size: 0.65rem;">${c.sender_role}</span></small>
                    <small class="text-muted" style="font-size: 0.7rem;">${c.created_at}</small>
                </div>
                <div class="small mb-0" style="word-break: break-word;">${escapeHtml(c.message)}</div>
            `;
            container.appendChild(div);
        });
    }

    new bootstrap.Modal(document.getElementById('modalDiscussion')).show();
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function copyHelpdeskLink() {
    const helpdeskUrl = window.location.origin + window.location.pathname + '?page=helpdesk';
    navigator.clipboard.writeText(helpdeskUrl).then(() => {
        const btn = document.getElementById('btnCopyHelpdeskLink');
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check2 me-1 text-success"></i> Link Tersalin!';
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-success', 'text-white');
        setTimeout(() => {
            btn.innerHTML = oldHtml;
            btn.classList.remove('btn-success', 'text-white');
            btn.classList.add('btn-outline-secondary');
        }, 2000);
    });
}
</script>
