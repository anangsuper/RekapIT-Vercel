<?php
require_once '../config/database.php';
require_once '../models/Maintenance.php';

$selected_cabang = $_GET['id_cabang'] ?? '';
$selected_bulan = $_GET['bulan'] ?? date('m');
$selected_tahun = $_GET['tahun'] ?? date('Y');

if (!$selected_cabang) {
    die("Pilih cabang terlebih dahulu.");
}

$maintenanceModel = new Maintenance($conn);
$stats = $maintenanceModel->getReportStats($selected_cabang, $selected_bulan, $selected_tahun);
$summaryDivisi = $maintenanceModel->getSummaryPerDivisi($selected_cabang, $selected_bulan, $selected_tahun);
$detailedMaintenance = $maintenanceModel->getDetailedByMonth($selected_cabang, $selected_bulan, $selected_tahun);

// Fetch Branch Name
$stmt = $conn->prepare("SELECT nama_cabang FROM cabang WHERE id = ?");
$stmt->execute([$selected_cabang]);
$branchName = $stmt->fetchColumn();

$filename = "Laporan_Maintenance_" . str_replace(' ', '_', $branchName) . "_" . $selected_bulan . "_" . $selected_tahun . ".xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");

?>
<table border="1">
    <tr>
        <th colspan="4" style="font-size: 16px;">LAPORAN MAINTENANCE PC BULANAN</th>
    </tr>
    <tr>
        <th colspan="4">Cabang: <?= $branchName ?></th>
    </tr>
    <tr>
        <th colspan="4">Periode: <?= $selected_bulan ?> / <?= $selected_tahun ?></th>
    </tr>
    <tr><td colspan="4"></td></tr>
    <tr>
        <th colspan="4" align="left">RINGKASAN STATISTIK</th>
    </tr>
    <tr>
        <td>Total Asset</td>
        <td align="right"><?= $stats['total_asset'] ?></td>
        <td>Total Selesai</td>
        <td align="right"><?= $stats['total_selesai'] ?></td>
    </tr>
    <tr>
        <td>Total Komputer</td>
        <td align="right"><?= $stats['total_komputer'] ?></td>
        <td>Total Belum</td>
        <td align="right"><?= $stats['total_belum'] ?></td>
    </tr>
    <tr>
        <td>Persentase</td>
        <td align="right"><?= $stats['persentase'] ?>%</td>
        <td>Total Temuan</td>
        <td align="right"><?= $stats['total_temuan'] ?></td>
    </tr>
    <tr><td colspan="4"></td></tr>
    <tr>
        <th colspan="4" align="left">RINGKASAN PER DIVISI</th>
    </tr>
    <tr>
        <th>Divisi</th>
        <th>Total</th>
        <th>Selesai</th>
        <th>Belum</th>
    </tr>
    <?php foreach ($summaryDivisi as $sd): ?>
    <tr>
        <td><?= $sd['nama_divisi'] ?? 'Tanpa Divisi' ?></td>
        <td align="right"><?= $sd['total_perangkat'] ?></td>
        <td align="right"><?= $sd['selesai'] ?></td>
        <td align="right"><?= $sd['belum'] ?></td>
    </tr>
    <?php endforeach; ?>
    <tr><td colspan="4"></td></tr>
    <tr>
        <th colspan="5" align="left">DETAIL CHECKLIST MAINTENANCE</th>
    </tr>
    <tr>
        <th>Tanggal</th>
        <th>Kode Aset</th>
        <th>User (Nama)</th>
        <th>Aksi / Tindakan</th>
        <th>Status</th>
    </tr>
    <?php foreach ($detailedMaintenance as $dm): ?>
    <tr>
        <td><?= date('d/m/Y', strtotime($dm['tanggal'])) ?></td>
        <td><?= $dm['kode_aset'] ?></td>
        <td><?= $dm['nama_karyawan'] ?? '-' ?></td>
        <td><?= $dm['tindakan'] ?: 'Pengecekan Rutin' ?></td>
        <td align="center">OK</td>
    </tr>
    <?php endforeach; ?>
</table>
