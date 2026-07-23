<?php
require_once 'models/HelpdeskTicket.php';
require_once 'models/Cabang.php';

$ticketModel = new HelpdeskTicket($conn);
$cabangModel = new Cabang($conn);

$branches = $cabangModel->getAll();

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
        <a href="index.php?page=helpdesk" target="_blank" class="btn btn-outline-primary shadow-sm">
            <i class="bi bi-box-arrow-up-right me-2"></i> Buka Portal Helpdesk Publik
        </a>
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
                                    <button class="btn btn-sm btn-outline-primary rounded-3 px-3 py-1.5" onclick='openProcessModal(<?= json_encode($t) ?>)'>
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
            </form>
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
</script>
