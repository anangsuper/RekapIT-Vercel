<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Asset.php';
require_once __DIR__ . '/../models/Maintenance.php';
require_once __DIR__ . '/../models/Repair.php';

$assetModel = new Asset($conn);
$maintenanceModel = new Maintenance($conn);
$repairModel = new Repair($conn);

$tgl_mulai = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tgl_selesai = $_GET['tgl_selesai'] ?? date('Y-m-d');
$id_cabang = $_GET['id_cabang'] ?? '';

// Filename
$filename = "Laporan_IT_" . date('Ymd_His') . ".xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

$assets = $assetModel->getAll($id_cabang);
$maintenances = $maintenanceModel->getAll($id_cabang, $tgl_mulai, $tgl_selesai);
$repairs = $repairModel->getAll($id_cabang, $tgl_mulai, $tgl_selesai);
?>

<style>
    .text-center { text-align: center; }
    .header { font-weight: bold; background-color: #f2f2f2; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #000; padding: 5px; }
</style>

<h3>LAPORAN OPERASIONAL IT</h3>
<p>Periode: <?= $tgl_mulai ?> s/d <?= $tgl_selesai ?></p>

<h4>1. DATA ASET</h4>
<table>
    <thead>
        <tr class="header">
            <th>No</th>
            <th>Kode Aset</th>
            <th>Nama Aset</th>
            <th>Kategori</th>
            <th>Cabang</th>
            <th>Divisi</th>
            <th>Kondisi</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($assets as $i => $a): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><?= $a['kode_aset'] ?></td>
            <td><?= $a['nama_aset'] ?></td>
            <td><?= $a['nama_kategori'] ?></td>
            <td><?= $a['nama_cabang'] ?></td>
            <td><?= $a['nama_divisi'] ?></td>
            <td><?= $a['kondisi'] ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h4>2. DATA MAINTENANCE</h4>
<table>
    <thead>
        <tr class="header">
            <th>No</th>
            <th>Tanggal</th>
            <th>Kode Aset</th>
            <th>Nama Aset</th>
            <th>Teknisi</th>
            <th>Temuan</th>
            <th>Tindakan</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($maintenances as $i => $m): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><?= $m['tanggal'] ?></td>
            <td><?= $m['kode_aset'] ?></td>
            <td><?= $m['nama_aset'] ?></td>
            <td><?= $m['teknisi'] ?></td>
            <td><?= $m['temuan'] ?></td>
            <td><?= $m['tindakan'] ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h4>3. DATA PERBAIKAN</h4>
<table>
    <thead>
        <tr class="header">
            <th>No</th>
            <th>Asset</th>
            <th>Keluhan</th>
            <th>Tindakan</th>
            <th>Status</th>
            <th>Biaya</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($repairs as $i => $r): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><?= $r['nama_aset'] ?> (<?= $r['kode_aset'] ?>)</td>
            <td><?= $r['keluhan'] ?></td>
            <td><?= $r['tindakan'] ?></td>
            <td><?= $r['status'] ?></td>
            <td><?= $r['biaya'] ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
