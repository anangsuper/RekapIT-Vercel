<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

/**
 * maintenance_pdf.php
 * Script untuk menghasilkan laporan PDF kegiatan maintenance IT menggunakan FPDF.
 * Mendukung Tipe 1 (Laporan Rinci / Checklist Detail) dan Tipe 2 (Laporan Ringkas / Kop Surat Bank Mitra).
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
    public $tipe; // '1' atau '2'

    function setInfo($cabangName, $tahun, $namaBulan, $bulanNum, $tipe) {
        $this->cabangName = $cabangName;
        $this->tahun = $tahun;
        $this->namaBulan = $namaBulan;
        $this->bulanNum = $bulanNum;
        $this->tipe = $tipe;
    }

    // Header
    function Header()
    {
        if ($this->tipe == '2') {
            // ================== HEADER TIPE 2 (KOP SURAT DENGAN BACKGROUND) ==================
            $bgPath = __DIR__ . '/../logo.jpg';
            if (file_exists($bgPath)) {
                $this->Image($bgPath, 0, 0, 210, 297);
            }

            // Teks Judul Kop (Indentasi ke kanan menghindari logo di kiri atas)
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
            
            $this->SetY(48); // Jarak awal konten
        } else {
            // ================== HEADER TIPE 1 (ORIGINAL PUTIH DENGAN LOGO KECIL) ==================
            $logoPath = __DIR__ . '/../assets/logo_bank_mitra.png';
            if (file_exists($logoPath)) {
                $this->Image($logoPath, 15, 10, 30);
                $this->SetY(12);
            }

            $this->SetFont('Arial', 'B', 14);
            $this->SetTextColor(30, 50, 80);
            $this->Cell(0, 8, 'LAPORAN KEGIATAN MAINTENANCE IT', 0, 1, 'C');
            
            $this->SetFont('Arial', 'B', 12);
            $this->SetTextColor(70, 80, 95);
            $this->Cell(0, 6, 'KANTOR CABANG ' . strtoupper($this->cabangName), 0, 1, 'C');

            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor(100, 110, 120);
            $this->Cell(0, 6, 'PERIODE: ' . strtoupper($this->namaBulan) . ' ' . $this->tahun, 0, 1, 'C');
            $this->Ln(4);
            
            $this->SetDrawColor(210, 220, 235);
            $this->SetLineWidth(0.6);
            $this->Line(15, $this->GetY(), 195, $this->GetY());
            $this->Ln(6);
        }
    }

    // Footer
    function Footer()
    {
        if ($this->tipe == '2') {
            // Footer Laporan Tipe 2 (Bersih, nomor halaman di Y = -15)
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(150, 150, 150);
            $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . ' dari {nb}', 0, 0, 'C');
        } else {
            // Footer Laporan Tipe 1 (Dengan panduan status)
            $this->SetY(-20);
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(120, 130, 140);
            $this->Cell(0, 5, 'Panduan Status: OK = Kondisi Baik  |  Warning = Perlu Perbaikan  |  Broken = Perangkat Rusak', 0, 1, 'C');
            
            $this->SetY(-15);
            $this->SetTextColor(150, 150, 150);
            $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . ' dari {nb}', 0, 0, 'C');
        }
    }

    // Helper FPDF untuk Tabel Bungkus Baris (Tipe 1)
    public $widths;
    public $aligns;

    function SetWidths($w) {
        $this->widths = $w;
    }

    function SetAligns($a) {
        $this->aligns = $a;
    }

    function Row($data, $fill = false, $statusColIndex = -1, $statusColor = null, $statusLabel = null) {
        $nb = 0;
        for($i = 0; $i < count($data); $i++) {
            $nb = max($nb, $this->NbLines($this->widths[$i], $data[$i]));
        }
        $h = 5 * $nb;
        $this->CheckPageBreak($h);
        for($i = 0; $i < count($data); $i++) {
            $w = $this->widths[$i];
            $a = isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
            $x = $this->GetX();
            $y = $this->GetY();
            
            $this->Rect($x, $y, $w, $h, $fill ? 'DF' : 'D');
            
            if ($i === $statusColIndex && $statusLabel !== null) {
                $this->SetTextColor($statusColor[0], $statusColor[1], $statusColor[2]);
                $this->SetFont('Arial', 'B', 8.5);
                $this->Cell($w, $h, $statusLabel, 0, 0, 'C');
                $this->SetTextColor(50, 55, 65);
                $this->SetFont('Arial', '', 9);
            } else {
                $this->MultiCell($w, 5, $data[$i], 0, $a);
            }
            $this->SetXY($x + $w, $y);
        }
        $this->Ln($h);
    }

    function CheckPageBreak($h) {
        if($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }
    }

    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw'];
        if($w == 0) $w = $this->w - $this->lMargin - $this->rMargin;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if($nb > 0 and $s[$nb - 1] == "\n") $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while($i < $nb) {
            $c = $s[$i];
            if($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if($c == ' ') $sep = $i;
            $l += $cw[$c];
            if($l > $wmax) {
                if($sep == -1) {
                    if($i == $j) $i++;
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }
}

// 1. Ambil Parameter URL
$id_cabang = $_GET['id_cabang'] ?? null;
$bulan = $_GET['bulan'] ?? null;
$tahun = $_GET['tahun'] ?? null;
$tipe = $_GET['tipe'] ?? '1'; // Default ke Tipe 1 jika tidak ditentukan

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

    // Data Detail Maintenance
    $sql = "SELECT m.*, a.nama_aset, a.kode_aset, kr.nama_karyawan, k.nama_kategori, d.nama_divisi 
            FROM maintenance m
            JOIN assets a ON m.asset_id = a.id
            LEFT JOIN karyawan kr ON a.id_karyawan = kr.id
            LEFT JOIN kategori_aset k ON a.id_kategori = k.id
            LEFT JOIN divisi d ON a.id_divisi = d.id
            WHERE a.id_cabang = :id_cabang AND MONTH(m.tanggal) = :bulan AND YEAR(m.tanggal) = :tahun
            ORDER BY m.tanggal ASC, kr.nama_karyawan ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':id_cabang', $id_cabang, PDO::PARAM_INT);
    $stmt->bindValue(':bulan', $bulan, PDO::PARAM_INT);
    $stmt->bindValue(':tahun', $tahun, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Hitung Ringkasan Statistik
    $stmtTotal = $conn->prepare("SELECT COUNT(*) FROM assets WHERE id_cabang = :id_cabang");
    $stmtTotal->bindValue(':id_cabang', $id_cabang, PDO::PARAM_INT);
    $stmtTotal->execute();
    $total_asset = $stmtTotal->fetchColumn();

    $unique_assets = [];
    foreach ($data as $d) {
        $unique_assets[$d['asset_id']] = true;
    }
    $total_selesai_unik = count($unique_assets);
    $total_selesai = $total_selesai_unik;
    $total_belum = max(0, $total_asset - $total_selesai_unik);
    $persentase = ($total_asset > 0) ? round(($total_selesai_unik / $total_asset) * 100, 2) : 0;

    $total_temuan = 0;
    foreach ($data as $d) {
        if (!empty($d['temuan']) && strtolower($d['temuan']) !== 'baik' && strtolower($d['temuan']) !== 'aman' && strtolower($d['temuan']) !== 'ok') {
            $total_temuan++;
        }
    }

    // Ambil data statistik ringkasan per-kategori perangkat (Hanya digunakan di Tipe 2)
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
$pdf->setInfo($nama_cabang, $tahun, $namaBulan, $bulan, $tipe);
$pdf->SetMargins(15, 15, 15);

if ($tipe == '2') {
    $pdf->SetAutoPageBreak(true, 28); // Margin bawah khusus Tipe 2 agar tidak menimpa kop footer
} else {
    $pdf->SetAutoPageBreak(true, 25);
}

$pdf->AliasNbPages();
$pdf->AddPage();

if ($tipe == '2') {
    // =========================================================================
    // ======================== TAMPILAN LAPORAN TIPE 2 ========================
    // =========================================================================

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

    // 3.3 Daftar Masalah / Temuan (Format Menyamping Horizontal!)
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
            
            // Format Menyamping Horizontal: 1. PC (AST-01): Ram Penuh - Diperlukan: Bersihkan Ram - Status: Perlu Perbaikan
            $tindakanText = $tindakan ?: 'Pengecekan unit.';
            $issueText = $issueNo . ". " . $d['nama_aset'] . " (" . $d['kode_aset'] . "): " . $temuan . " - Diperlukan: " . $tindakanText . " - Status: " . $status;
            
            $pdf->MultiCell(0, 5, $issueText, 0, 'L');
            $pdf->Ln(1);
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

} else {
    // =========================================================================
    // ======================== TAMPILAN LAPORAN TIPE 1 ========================
    // =========================================================================

    // 3.1. Tampilkan Ringkasan Statistik (KPI Cards style)
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(30, 50, 80);
    $pdf->Cell(0, 6, 'RINGKASAN STATISTIK MAINTENANCE', 0, 1, 'L');
    $pdf->Ln(2);

    $pdf->SetFillColor(240, 245, 250);
    $pdf->SetTextColor(50, 55, 65);
    $pdf->SetDrawColor(200, 215, 230);
    $pdf->SetLineWidth(0.3);
    $pdf->SetFont('Arial', 'B', 9);

    $pdf->Cell(36, 8, 'Total Asset', 1, 0, 'C', true);
    $pdf->Cell(36, 8, 'Selesai', 1, 0, 'C', true);
    $pdf->Cell(36, 8, 'Belum', 1, 0, 'C', true);
    $pdf->Cell(36, 8, 'Persentase', 1, 0, 'C', true);
    $pdf->Cell(36, 8, 'Total Temuan', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 9.5);
    $pdf->Cell(36, 8, $total_asset, 1, 0, 'C');
    $pdf->Cell(36, 8, $total_selesai, 1, 0, 'C');
    $pdf->Cell(36, 8, $total_belum, 1, 0, 'C');
    $pdf->Cell(36, 8, $persentase . '%', 1, 0, 'C');
    $pdf->Cell(36, 8, $total_temuan, 1, 1, 'C');
    $pdf->Ln(6);

    // 3.2. Detail Checklist Table
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(30, 50, 80);
    $pdf->Cell(0, 6, 'RINCIAN CHECKLIST PERANGKAT', 0, 1, 'L');
    $pdf->Ln(2);

    // Set table headers style
    $pdf->SetFillColor(224, 235, 245);
    $pdf->SetTextColor(30, 50, 80);
    $pdf->SetFont('Arial', 'B', 9);

    $pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
    $pdf->Cell(45, 8, 'Aset (Kode & Nama)', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'User (Pemegang) & Divisi', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Tanggal Check', 1, 0, 'C', true);
    $pdf->Cell(45, 8, 'Tindakan / Aksi', 1, 0, 'C', true);
    $pdf->Cell(15, 8, 'Status', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(50, 55, 65);

    $pdf->SetWidths([10, 45, 40, 25, 45, 15]);
    $pdf->SetAligns(['C', 'L', 'L', 'C', 'L', 'C']);

    $alternateFill = false;
    $no = 1;

    foreach ($data as $row) {
        if ($alternateFill) {
            $pdf->SetFillColor(248, 250, 253);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }

        $asetText = $row['kode_aset'] . ' - ' . $row['nama_aset'];
        $userText = ($row['nama_karyawan'] ?: 'Unassigned') . ' (' . ($row['nama_divisi'] ?: '-') . ')';
        $tgl = date('d/m/Y', strtotime($row['tanggal']));
        $tindakanRaw = $row['tindakan'];
        if (strpos($tindakanRaw, 'Checklist: ') !== false) {
            $parts = explode('. Tindakan: ', $tindakanRaw);
            $tindakan = isset($parts[1]) ? $parts[1] : 'Pengecekan Rutin';
        } else {
            $tindakan = $tindakanRaw ?: 'Pengecekan Rutin';
        }
        $status = $row['status'] ?? 'Baik';

        if ($status === 'Baik') {
            $statusColor = [40, 167, 69];
            $statusLabel = 'OK';
        } elseif ($status === 'Perlu Perbaikan') {
            $statusColor = [230, 140, 0];
            $statusLabel = 'Warning';
        } else {
            $statusColor = [220, 53, 69];
            $statusLabel = 'Broken';
        }
        
        $pdf->Row([$no++, $asetText, $userText, $tgl, $tindakan, ''], $alternateFill, 5, $statusColor, $statusLabel);
        $alternateFill = !$alternateFill;
    }
    $pdf->Ln(6);

    // 3.3. Temuan Masalah & Kendala (Format menyamping mendatar/horizontal!)
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(30, 50, 80);
    $pdf->Cell(0, 6, 'TEMUAN MASALAH & KENDALA', 0, 1, 'L');
    $pdf->Ln(2);

    $pdf->SetFont('Arial', '', 9.5);
    $pdf->SetTextColor(50, 55, 65);
    $hasTemuan = false;
    $issueNo = 1;

    foreach ($data as $d) {
        if (!empty($d['temuan']) && strtolower($d['temuan']) !== 'baik' && strtolower($d['temuan']) !== 'aman' && strtolower($d['temuan']) !== 'ok') {
            $tindakanText = $d['tindakan'] ?: 'Pengecekan unit.';
            // Format menyamping horizontal
            $issueText = $issueNo . ". " . $d['kode_aset'] . " (" . $d['nama_aset'] . "): " . $d['temuan'] . " - Diperlukan: " . $tindakanText . " - Status: " . $d['status'];
            $pdf->MultiCell(0, 5, $issueText, 0, 'L');
            $pdf->Ln(1);
            $hasTemuan = true;
            $issueNo++;
        }
    }
    if (!$hasTemuan) {
        $pdf->Cell(0, 6, 'Tidak ditemukan kendala atau temuan masalah berarti (semua perangkat dalam kondisi baik/aman).', 0, 1, 'L');
    }
    $pdf->Ln(8);
}

// =========================================================================
// ========================== BAGIAN TANDA TANGAN ==========================
// =========================================================================
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(30, 50, 80);
$pdf->Cell(0, 5, 'PT BPR Mitratama Arthabuana', 0, 1, 'C');
$pdf->SetTextColor(50, 55, 65);
$pdf->SetFont('Arial', '', 9.5);
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

// Tipe 2 menggunakan underline, Tipe 1 menggunakan Bold saja sesuai layout awal
if ($tipe == '2') {
    $pdf->SetFont('Arial', 'BU', 9.5);
} else {
    $pdf->SetFont('Arial', 'B', 9.5);
}
$pdf->Cell(60, 5, $dibuat_nama, 0, 0, 'C');
$pdf->Cell(60, 5, $mengetahui_nama ?: '-', 0, 0, 'C');
$pdf->Cell(60, 5, $menyetujui_nama ?: '-', 0, 1, 'C');

$pdf->SetFont('Arial', '', 8.5);
$pdf->Cell(60, 4, $dibuat_jabatan, 0, 0, 'C');
$pdf->Cell(60, 4, $mengetahui_jabatan, 0, 0, 'C');
$pdf->Cell(60, 4, $menyetujui_jabatan, 0, 1, 'C');

$pdf->Output();
