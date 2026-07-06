<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

/**
 * maintenance_pdf.php
 * Script untuk menghasilkan laporan PDF kegiatan maintenance IT menggunakan FPDF.
 * Disesuaikan menggunakan background logo.jpg sebagai kop/letterhead A4 penuh.
 */

define('SKIP_DB_SYNC', true);
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

class PDF extends FPDF
{
    public $cabangName;
    public $tahun;
    public $namaBulan;
    public $bulanNum;

    function setInfo($cabangName, $tahun, $namaBulan, $bulanNum) {
        $this->cabangName = $cabangName;
        $this->tahun = $tahun;
        $this->namaBulan = $namaBulan;
        $this->bulanNum = $bulanNum;
    }

    // Header
    function Header()
    {
        // 1. Gambar Background Kop A4 Penuh (logo.jpg)
        $bgPath = __DIR__ . '/../logo.jpg';
        if (file_exists($bgPath)) {
            $this->Image($bgPath, 0, 0, 210, 297);
        }

        // 2. Teks Judul Kop (Indentasi ke kanan menghindari logo di kiri atas)
        $this->SetY(15);
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(30, 50, 80); // Deep steel blue
        $this->Cell(38); // Geser kanan
        $this->Cell(0, 6, 'LAPORAN KEGIATAN MAINTENANCE IT', 0, 1, 'C');
        
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(38); // Geser kanan
        $this->Cell(0, 6, 'KANTOR CABANG ' . strtoupper($this->cabangName), 0, 1, 'C');
        
        // Nomor Dokumen Format Romawi
        $romans = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];
        $romanMonth = $romans[(int)$this->bulanNum] ?? 'I';
        
        $this->SetFont('Arial', '', 9.5);
        $this->SetTextColor(80, 85, 95);
        $this->Cell(38); // Geser kanan
        $this->Cell(0, 5, 'No : 001/INT/LAP/MNT/IT/BANK.MITRA/' . $romanMonth . '/' . $this->tahun, 0, 1, 'C');
        
        // Jarak dari header ke konten utama
        $this->SetY(48);
    }

    // Footer
    function Footer()
    {
        // Nomor halaman di bagian bawah tengah (posisi Y = -15)
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . ' dari {nb}', 0, 0, 'C');
    }
}

// 1. Ambil Parameter URL
$id_cabang = $_GET['id_cabang'] ?? null;
$bulan = $_GET['bulan'] ?? null;
$tahun = $_GET['tahun'] ?? null;

if (!$id_cabang || !$bulan || !$tahun) {
    die("Parameter tidak lengkap.");
}

$indonesianMonths = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$namaBulan = $indonesianMonths[$bulan] ?? date('F', mktime(0, 0, 0, $bulan, 10));

// 2. Fetch Data dari Database
try {
    $id_cabang = (int)$id_cabang;
    $bulan = (int)$bulan;
    $tahun = (int)$tahun;

    // Nama Cabang
    $stmtCabang = $conn->prepare("SELECT nama_cabang FROM cabang WHERE id = :id_cabang");
    $stmtCabang->bindValue(':id_cabang', $id_cabang, PDO::PARAM_INT);
    $stmtCabang->execute();
    $cabang = $stmtCabang->fetch();
    $nama_cabang = $cabang['nama_cabang'] ?? 'Unknown';

    // Data Detail Maintenance (untuk masalah/temuan)
    $sql = "SELECT m.*, a.nama_aset, a.kode_aset, kr.nama_karyawan, d.nama_divisi 
            FROM maintenance m
            JOIN assets a ON m.asset_id = a.id
            LEFT JOIN karyawan kr ON a.id_karyawan = kr.id
            LEFT JOIN divisi d ON a.id_divisi = d.id
            WHERE a.id_cabang = :id_cabang AND MONTH(m.tanggal) = :bulan AND YEAR(m.tanggal) = :tahun
            ORDER BY m.tanggal ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':id_cabang', $id_cabang, PDO::PARAM_INT);
    $stmt->bindValue(':bulan', $bulan, PDO::PARAM_INT);
    $stmt->bindValue(':tahun', $tahun, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ambil data statistik ringkasan per-kategori perangkat
    $stmtDevices = $conn->prepare("
        SELECT k.nama_kategori, COUNT(*) as jumlah 
        FROM maintenance m 
        JOIN assets a ON m.asset_id = a.id 
        JOIN kategori_aset k ON a.id_kategori = k.id 
        WHERE a.id_cabang = :id_cabang 
          AND MONTH(m.tanggal) = :bulan 
          AND YEAR(m.tanggal) = :tahun 
        GROUP BY k.nama_kategori
    ");
    $stmtDevices->bindValue(':id_cabang', $id_cabang, PDO::PARAM_INT);
    $stmtDevices->bindValue(':bulan', $bulan, PDO::PARAM_INT);
    $stmtDevices->bindValue(':tahun', $tahun, PDO::PARAM_INT);
    $stmtDevices->execute();
    $deviceCounts = $stmtDevices->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error database: " . $e->getMessage());
}

// 3. Generate Laporan PDF
$pdf = new PDF('P', 'mm', 'A4');
$pdf->setInfo($nama_cabang, $tahun, $namaBulan, $bulan);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 28); // Berikan margin bawah yang cukup agar tidak menimpa footer surat
$pdf->AliasNbPages();
$pdf->AddPage();

// 3.1 Teks Paragraf Pembuka Laporan
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(30, 30, 30);

$introText = "Bersama ini kami sampaikan laporan kegiatan maintenance IT Kantor Cabang " . $nama_cabang . " selama periode " . $namaBulan . " " . $tahun . ", sebagai bentuk pemeliharaan rutin untuk menjaga stabilitas dan keamanan sistem teknologi informasi di lingkungan kerja. Kegiatan maintenance di kantor cabang dilakukan secara rutin untuk menjaga kelancaran operasional sistem IT, meliputi:";

$pdf->MultiCell(0, 5, $introText, 0, 'J');
$pdf->Ln(2);

// Daftar Kegiatan Bulanan
$activities = [
    "Update CBS",
    "Pemeriksaan UPS & Jaringan Listrik",
    "Maintenance PC & Printer",
    "Update Windows",
    "Update Antivirus",
    "Clear Chance"
];
foreach ($activities as $act) {
    $pdf->Cell(8);
    $pdf->Cell(5, 5, '-', 0, 0, 'L');
    $pdf->Cell(0, 5, $act, 0, 1, 'L');
}
$pdf->Ln(4);

// 3.2 Tabel Perangkat
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(30, 50, 80);
$pdf->Cell(0, 5, 'Perangkat', 0, 1, 'L');
$pdf->Ln(1);

// Set Headings Tabel Perangkat
$pdf->SetFillColor(99, 178, 222); // Biru muda cerah sesuai referensi gambar
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 9.5);
$pdf->Cell(15, 7, 'No', 1, 0, 'C', true);
$pdf->Cell(80, 7, 'Jenis', 1, 0, 'C', true);
$pdf->Cell(30, 7, 'Jumlah', 1, 1, 'C', true);

// Set Data Tabel Perangkat
$pdf->SetTextColor(30, 30, 30);
$pdf->SetFont('Arial', '', 9.5);
$no = 1;
if (!empty($deviceCounts)) {
    foreach ($deviceCounts as $dc) {
        $pdf->Cell(15, 6, $no++, 1, 0, 'C');
        $pdf->Cell(80, 6, $dc['nama_kategori'], 1, 0, 'L');
        $pdf->Cell(30, 6, $dc['jumlah'], 1, 1, 'C');
    }
} else {
    $pdf->Cell(15, 6, '1', 1, 0, 'C');
    $pdf->Cell(80, 6, 'Perangkat PC & Printer', 1, 0, 'L');
    $pdf->Cell(30, 6, '0', 1, 1, 'C');
}
$pdf->Ln(5);

// 3.3 Daftar Masalah / Temuan
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(30, 50, 80);
$pdf->Cell(0, 5, 'Ditemukan beberapa masalah berupa :', 0, 1, 'L');
$pdf->Ln(1);

$pdf->SetTextColor(30, 30, 30);
$pdf->SetFont('Arial', '', 9.5);
$issueNo = 1;
$hasIssues = false;

foreach ($data as $d) {
    $status = $d['status'] ?? 'Baik';
    $temuan = trim($d['temuan'] ?? '');
    $tindakan = trim($d['tindakan'] ?? '');
    
    if ($status !== 'Baik' || (!empty($temuan) && strtolower($temuan) !== 'baik' && strtolower($temuan) !== 'aman' && strtolower($temuan) !== 'ok')) {
        $hasIssues = true;
        
        // Print nomor & deskripsi temuan
        $issueText = $issueNo . ". " . $d['nama_aset'] . " (" . $d['kode_aset'] . "): " . $temuan;
        $pdf->MultiCell(0, 5, $issueText, 0, 'L');
        
        // Print tindakan yang diperlukan
        $pdf->Cell(5);
        $pdf->SetFont('Arial', 'I', 9.5);
        $pdf->Cell(20, 5, 'Diperlukan : ', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9.5);
        $pdf->Cell(0, 5, $tindakan ?: 'Pengecekan dan perbaikan unit.', 0, 1, 'L');
        
        // Print status perbaikan
        $pdf->Cell(5);
        $pdf->SetFont('Arial', 'I', 9.5);
        $pdf->Cell(20, 5, 'Status : ', 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 9.5);
        $pdf->Cell(0, 5, $status, 0, 1, 'L');
        $pdf->SetFont('Arial', '', 9.5);
        
        $pdf->Ln(1.5);
        $issueNo++;
    }
}

if (!$hasIssues) {
    $pdf->Cell(5);
    $pdf->Cell(0, 5, 'Tidak ditemukan kendala atau temuan masalah berarti (semua perangkat dalam kondisi baik/aman).', 0, 1, 'L');
    $pdf->Ln(2);
}
$pdf->Ln(3);

// 3.4 Teks Paragraf Penutup Laporan
$pdf->SetFont('Arial', '', 10);
$closingText = "Demikian laporan ini kami sampaikan sebagai bentuk pertanggungjawaban dan dokumentasi kegiatan pemeliharaan sistem TI di Kantor Cabang " . $nama_cabang . ". Atas perhatian dan dukungan Bapak/Ibu Pimpinan, kami ucapkan terima kasih.";
$pdf->MultiCell(0, 5, $closingText, 0, 'J');
$pdf->Ln(6);

// 3.5 Bagian Tanda Tangan
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(30, 50, 80);
$pdf->Cell(0, 5, 'PT BPR Mitratama Arthabuana', 0, 1, 'C');
$pdf->SetFont('Arial', '', 9.5);
$pdf->SetTextColor(30, 30, 30);
$pdf->Cell(0, 5, date('d ') . $namaBulan . date(' Y'), 0, 1, 'C');
$pdf->Ln(8);

// Ambil data penandatangan dari parameter URL
$dibuat_nama = isset($_GET['dibuat_nama']) ? trim($_GET['dibuat_nama']) : '';
$dibuat_jabatan = isset($_GET['dibuat_jabatan']) ? trim($_GET['dibuat_jabatan']) : 'Staff MIS & IT';
$mengetahui_nama = isset($_GET['mengetahui_nama']) ? trim($_GET['mengetahui_nama']) : '';
$mengetahui_jabatan = isset($_GET['mengetahui_jabatan']) ? trim($_GET['mengetahui_jabatan']) : 'Kepala Cabang';
$menyetujui_nama = isset($_GET['menyetujui_nama']) ? trim($_GET['menyetujui_nama']) : '';
$menyetujui_jabatan = isset($_GET['menyetujui_jabatan']) ? trim($_GET['menyetujui_jabatan']) : 'Direktur Operasional';

$teknisi = $data[0]['teknisi'] ?? 'Staff MIS & IT';
if (empty($dibuat_nama)) {
    $dibuat_nama = $teknisi;
}

$pdf->Cell(60, 5, 'Membuat,', 0, 0, 'C');
$pdf->Cell(60, 5, 'Mengetahui,', 0, 0, 'C');
$pdf->Cell(60, 5, 'Menyetujui,', 0, 1, 'C');
$pdf->Ln(18);

// Nama Penandatangan di-Underline
$pdf->SetFont('Arial', 'BU', 9.5);
$pdf->Cell(60, 5, $dibuat_nama, 0, 0, 'C');
$pdf->Cell(60, 5, $mengetahui_nama ?: '-', 0, 0, 'C');
$pdf->Cell(60, 5, $menyetujui_nama ?: '-', 0, 1, 'C');

// Jabatan Penandatangan
$pdf->SetFont('Arial', '', 8.5);
$pdf->Cell(60, 4, $dibuat_jabatan, 0, 0, 'C');
$pdf->Cell(60, 4, $mengetahui_jabatan, 0, 0, 'C');
$pdf->Cell(60, 4, $menyetujui_jabatan, 0, 1, 'C');

$pdf->Output();
