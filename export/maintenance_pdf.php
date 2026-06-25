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

// Check for DomPDF
if (!file_exists('../vendor/autoload.php')) {
    die("<h4>Library DomPDF tidak ditemukan!</h4>
         <p>Silakan jalankan perintah berikut di terminal Anda:</p>
         <code>composer require dompdf/dompdf</code>
         <br><br>
         <button onclick='window.history.back()' class='btn btn-secondary'>Kembali</button>");
}

require_once '../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$months = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$namaBulan = $months[$selected_bulan];

$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: "Helvetica", sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #444; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 20px; text-transform: uppercase; }
        .header p { margin: 5px 0 0; font-size: 14px; color: #666; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 5px 0; }
        .section-title { background: #f4f4f4; padding: 8px; font-weight: bold; margin-bottom: 15px; border-left: 4px solid #0d6efd; }
        .stats-grid { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .stats-grid td { width: 25%; padding: 15px; border: 1px solid #ddd; text-align: center; }
        .stats-grid .label { font-size: 10px; color: #666; display: block; margin-bottom: 5px; }
        .stats-grid .value { font-size: 18px; font-weight: bold; color: #0d6efd; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .data-table th, .data-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .data-table th { background: #f8f9fa; }
        .footer-table { width: 100%; margin-top: 50px; }
        .footer-table td { width: 50%; text-align: center; }
        .signature-space { height: 80px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>CHECKLIST MAINTENANCE PC BULANAN</h1>
        <p>Rekap IT - Sistem Manajemen Aset & Maintenance</p>
    </div>

    <table class="info-table">
        <tr>
            <td width="15%">Periode</td>
            <td width="35%">: <strong>' . $namaBulan . ' ' . $selected_tahun . '</strong></td>
            <td width="15%">Kantor/Cabang</td>
            <td width="35%">: <strong>' . $branchName . '</strong></td>
        </tr>
        <tr>
            <td>Petugas IT</td>
            <td>: <strong>Hafizh</strong></td>
            <td>Dokumen No.</td>
            <td>: <strong>MIS/' . $selected_bulan . '/' . $selected_tahun . '</strong></td>
        </tr>
    </table>

    <div class="section-title">RINGKASAN STATISTIK</div>
    <table class="stats-grid">
        <tr>
            <td>
                <span class="label">Total Asset</span>
                <span class="value">' . $stats['total_asset'] . '</span>
            </td>
            <td>
                <span class="label">Total Maintenance</span>
                <span class="value">' . $stats['total_maintenance'] . '</span>
            </td>
            <td>
                <span class="label">Penyelesaian</span>
                <span class="value">' . $stats['persentase'] . '%</span>
            </td>
            <td>
                <span class="label">Total Temuan</span>
                <span class="value">' . $stats['total_temuan'] . '</span>
            </td>
        </tr>
    </table>

    <div class="section-title">RINGKASAN PER DIVISI</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Divisi</th>
                <th align="center">Total Perangkat</th>
                <th align="center">Selesai</th>
                <th align="center">Belum Selesai</th>
            </tr>
        </thead>
        <tbody>';
        foreach ($summaryDivisi as $sd) {
            $html .= '<tr>
                <td>' . ($sd['nama_divisi'] ?? 'Tanpa Divisi') . '</td>
                <td align="center">' . $sd['total_perangkat'] . '</td>
                <td align="center">' . $sd['selesai'] . '</td>
                <td align="center">' . $sd['belum'] . '</td>
            </tr>';
        }
$html .= '
        </tbody>
    </table>

    <div class="section-title">DETAIL CHECKLIST MAINTENANCE</div>
    <table class="data-table">
        <thead>
            <tr>
                <th width="12%">Tanggal</th>
                <th width="15%">Kode Aset</th>
                <th width="20%">User (Nama)</th>
                <th width="38%">Aksi / Tindakan</th>
                <th width="15%">Status</th>
            </tr>
        </thead>
        <tbody>';
        foreach ($detailedMaintenance as $dm) {
            $html .= '<tr>
                <td>' . date('d/m/y', strtotime($dm['tanggal'])) . '</td>
                <td><strong>' . $dm['kode_aset'] . '</strong></td>
                <td>' . ($dm['nama_karyawan'] ?? '-') . '</td>
                <td><small>' . ($dm['tindakan'] ?: 'Pengecekan Rutin') . '</small></td>
                <td align="center"><span style="color: green; font-weight: bold;">OK</span></td>
            </tr>';
        }
        if (empty($detailedMaintenance)) {
            $html .= '<tr><td colspan="5" align="center">Tidak ada data maintenance untuk periode ini.</td></tr>';
        }
$html .= '
        </tbody>
    </table>

    <div class="section-title">KESIMPULAN & REKOMENDASI</div>
    <div style="padding: 10px; border: 1px solid #ddd; background: #fff;">
        <p><strong>Kesimpulan:</strong></p>
        <p style="line-height: 1.5;">' . generateConclusionPdf($stats, $selected_bulan, $selected_tahun, $branchName) . '</p>
    </div>

    <table class="footer-table">
        <tr>
            <td>
                <p>Mengetahui,</p>
                <p><strong>Kepala Cabang / Operasional</strong></p>
                <div class="signature-space"></div>
                <p>( __________________________ )</p>
            </td>
            <td>
                <p>Dilaporkan Oleh,</p>
                <p><strong>Petugas MIS & IT</strong></p>
                <div class="signature-space"></div>
                <p><strong>Hafizh</strong><br>MIS & IT Department</p>
            </td>
        </tr>
    </table>
</body>
</html>';

function generateConclusionPdf($stats, $bulan, $tahun, $branchName) {
    $months = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];
    $namaBulan = $months[$bulan];
    return "Pada bulan $namaBulan $tahun telah dilakukan maintenance terhadap {$stats['total_maintenance']} perangkat dari total {$stats['total_asset']} perangkat yang terdaftar di $branchName. Tingkat penyelesaian maintenance mencapai {$stats['persentase']}%. Sebagian besar perangkat dalam kondisi baik dan antivirus aktif. Beberapa perangkat memerlukan tindak lanjut untuk optimalisasi performa dan pembaruan sistem keamanan.";
}

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Laporan_Maintenance_" . $branchName . ".pdf", ["Attachment" => false]);
