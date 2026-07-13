<?php
define('SKIP_DB_SYNC', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Asset.php';
require_once __DIR__ . '/../models/Maintenance.php';
require_once __DIR__ . '/../models/Repair.php';

$tgl_mulai = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tgl_selesai = $_GET['tgl_selesai'] ?? date('Y-m-d');
$id_cabang = $_GET['id_cabang'] ?? '';

// Filename
$filename = "Laporan_IT_" . date('Ymd_His') . ".xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Helper to execute query and return statement for iterative fetching
function getQueryStmt($conn, $table, $id_cabang = null, $tgl_mulai = null, $tgl_selesai = null) {
    if ($table == 'assets') {
        $query = "SELECT a.*, k.nama_kategori, c.nama_cabang, d.nama_divisi, kr.nama_karyawan 
                  FROM assets a
                  LEFT JOIN kategori_aset k ON a.id_kategori = k.id
                  LEFT JOIN cabang c ON a.id_cabang = c.id
                  LEFT JOIN divisi d ON a.id_divisi = d.id
                  LEFT JOIN karyawan kr ON a.id_karyawan = kr.id";
        if ($id_cabang) $query .= " WHERE a.id_cabang = :id_cabang";
        $stmt = $conn->prepare($query);
        if ($id_cabang) $stmt->bindValue(':id_cabang', (int)$id_cabang, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    } elseif ($table == 'maintenance') {
        $query = "SELECT m.*, a.kode_aset, a.nama_aset 
                  FROM maintenance m 
                  JOIN assets a ON m.asset_id = a.id
                  WHERE m.tanggal BETWEEN :tgl_mulai AND :tgl_selesai";
        if ($id_cabang) $query .= " AND a.id_cabang = :id_cabang";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':tgl_mulai', $tgl_mulai);
        $stmt->bindParam(':tgl_selesai', $tgl_selesai);
        if ($id_cabang) $stmt->bindValue(':id_cabang', (int)$id_cabang, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    } elseif ($table == 'repairs') {
        $query = "SELECT r.*, a.kode_aset, a.nama_aset 
                  FROM repairs r 
                  JOIN assets a ON r.asset_id = a.id
                  WHERE r.tanggal_mulai BETWEEN :tgl_mulai AND :tgl_selesai";
        if ($id_cabang) $query .= " AND a.id_cabang = :id_cabang";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':tgl_mulai', $tgl_mulai);
        $stmt->bindParam(':tgl_selesai', $tgl_selesai);
        if ($id_cabang) $stmt->bindValue(':id_cabang', (int)$id_cabang, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }
    return null;
}
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
        <?php 
        $stmt = getQueryStmt($conn, 'assets', $id_cabang);
        $i = 1;
        while ($a = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
        <tr>
            <td><?= $i++ ?></td>
            <td><?= $a['kode_aset'] ?></td>
            <td><?= $a['nama_aset'] ?></td>
            <td><?= $a['nama_kategori'] ?></td>
            <td><?= $a['nama_cabang'] ?></td>
            <td><?= $a['nama_divisi'] ?></td>
            <td><?= $a['kondisi'] ?></td>
        </tr>
        <?php endwhile; ?>
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
        <?php 
        $stmt = getQueryStmt($conn, 'maintenance', $id_cabang, $tgl_mulai, $tgl_selesai);
        $i = 1;
        while ($m = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
        <tr>
            <td><?= $i++ ?></td>
            <td><?= $m['tanggal'] ?></td>
            <td><?= $m['kode_aset'] ?></td>
            <td><?= $m['nama_aset'] ?></td>
            <td><?= $m['teknisi'] ?></td>
            <td><?= $m['temuan'] ?></td>
            <td><?= $m['tindakan'] ?></td>
        </tr>
        <?php endwhile; ?>
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
        <?php 
        $stmt = getQueryStmt($conn, 'repairs', $id_cabang, $tgl_mulai, $tgl_selesai);
        $i = 1;
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
        <tr>
            <td><?= $i++ ?></td>
            <td><?= $r['nama_aset'] ?> (<?= $r['kode_aset'] ?>)</td>
            <td><?= $r['keluhan'] ?></td>
            <td><?= $r['tindakan'] ?></td>
            <td><?= $r['status'] ?></td>
            <td><?= $r['biaya'] ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
