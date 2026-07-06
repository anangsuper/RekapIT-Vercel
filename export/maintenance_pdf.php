<?php
/**
 * maintenance_pdf.php
 * Script untuk menghasilkan laporan PDF kegiatan maintenance IT menggunakan FPDF.
 * Disesuaikan agar sama dengan data dan tampilan pada halaman laporan_maintenance.php.
 */

define('SKIP_DB_SYNC', true);
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

class PDF extends FPDF
{
    public $cabangName;
    public $tahun;
    public $namaBulan;

    function setInfo($cabangName, $tahun, $namaBulan) {
        $this->cabangName = $cabangName;
        $this->tahun = $tahun;
        $this->namaBulan = $namaBulan;
    }

    // Header
    function Header()
    {
        // Logo jika ada
        $logoPath = __DIR__ . '/../assets/logo_bank_mitra.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 15, 10, 30);
            $this->SetY(12);
        }

        // Title Laporan
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(30, 50, 80); // Deep steel blue
        $this->Cell(0, 8, 'LAPORAN KEGIATAN MAINTENANCE IT', 0, 1, 'C');
        
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(70, 80, 95);
        $this->Cell(0, 6, 'KANTOR CABANG ' . strtoupper($this->cabangName), 0, 1, 'C');

        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(100, 110, 120);
        $this->Cell(0, 6, 'PERIODE: ' . strtoupper($this->namaBulan) . ' ' . $this->tahun, 0, 1, 'C');
        $this->Ln(4);
        
        // Modern decorative rule
        $this->SetDrawColor(210, 220, 235);
        $this->SetLineWidth(0.6);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(6);
    }

    // Page footer
    function Footer()
    {
        // Set Y position for legend (20mm from bottom)
        $this->SetY(-20);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(120, 130, 140);
        // Print the status legend
        $this->Cell(0, 5, 'Panduan Status: OK = Kondisi Baik  |  Warning = Perlu Perbaikan  |  Broken = Perangkat Rusak', 0, 1, 'C');
        
        // Print page number (12mm from bottom)
        $this->SetY(-15);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . ' dari {nb}', 0, 0, 'C');
    }

    public $widths;
    public $aligns;

    function SetWidths($w) {
        $this->widths = $w;
    }

    function SetAligns($a) {
        $this->aligns = $a;
    }

    function Row($data, $fill = false, $statusColIndex = -1, $statusColor = null, $statusLabel = null) {
        // Calculate row height based on maximum line count
        $nb = 0;
        for($i = 0; $i < count($data); $i++) {
            $nb = max($nb, $this->NbLines($this->widths[$i], $data[$i]));
        }
        $h = 5 * $nb;
        
        // Auto page break check
        $this->CheckPageBreak($h);
        
        // Draw each cell
        for($i = 0; $i < count($data); $i++) {
            $w = $this->widths[$i];
            $a = isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
            $x = $this->GetX();
            $y = $this->GetY();
            
            // Draw border & background
            $this->Rect($x, $y, $w, $h, $fill ? 'DF' : 'D');
            
            // Render text
            if ($i === $statusColIndex && $statusLabel !== null) {
                $this->SetTextColor($statusColor[0], $statusColor[1], $statusColor[2]);
                $this->SetFont('Arial', 'B', 8.5);
                $this->MultiCell($w, 5, $statusLabel, 0, 'C');
                $this->SetFont('Arial', '', 9);
                $this->SetTextColor(50, 55, 65);
            } else {
                // Add vertical padding for single line items to center vertically
                $numLines = $this->NbLines($w, $data[$i]);
                if ($numLines < $nb) {
                    $yOffset = (($nb - $numLines) * 5) / 2;
                    $this->SetXY($x, $y + $yOffset);
                    $this->MultiCell($w, 5, $data[$i], 0, $a);
                    $this->SetXY($x, $y);
                } else {
                    $this->MultiCell($w, 5, $data[$i], 0, $a);
                }
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
        if(!isset($this->CurrentFont))
            return 1;
        $cw = $this->CurrentFont['cw'];
        if($w == 0)
            $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', (string)$txt);
        $nb = strlen($s);
        if($nb > 0 && $s[$nb - 1] == "\n")
            $nb--;
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
            if($c == ' ')
                $sep = $i;
            $l += $cw[$c];
            if($l > $wmax) {
                if($sep == -1) {
                    if($i == $j)
                        $i++;
                }
                else
                    $i = $sep + 1;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            }
            else
                $i++;
        }
        return $nl;
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
    // Cast parameters to integer to ensure compatibility with SQLite user-defined functions and types
    $id_cabang = (int)$id_cabang;
    $bulan = (int)$bulan;
    $tahun = (int)$tahun;

    // Nama Cabang
    $stmtCabang = $conn->prepare("SELECT nama_cabang FROM cabang WHERE id = :id_cabang");
    $stmtCabang->bindValue(':id_cabang', $id_cabang, PDO::PARAM_INT);
    $stmtCabang->execute();
    $cabang = $stmtCabang->fetch();
    $nama_cabang = $cabang['nama_cabang'] ?? 'Unknown';

    // Data Maintenance
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

} catch (PDOException $e) {
    die("Error database: " . $e->getMessage());
}

// 3. Generate PDF
$pdf = new PDF('P', 'mm', 'A4');
$pdf->setInfo($nama_cabang, $tahun, $namaBulan);
$pdf->SetMargins(15, 15, 15);
$pdf->AliasNbPages();
$pdf->AddPage();

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
$pdf->SetFillColor(224, 235, 245); // Sleek modern light blue-grey
$pdf->SetTextColor(30, 50, 80);    // Contrast deep blue text
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
    // Toggle row background color
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

    // Color code status label
    if ($status === 'Baik') {
        $statusColor = [40, 167, 69]; // Green
        $statusLabel = 'OK';
    } elseif ($status === 'Perlu Perbaikan') {
        $statusColor = [230, 140, 0]; // Orange
        $statusLabel = 'Warning';
    } else {
        $statusColor = [220, 53, 69]; // Red
        $statusLabel = 'Broken';
    }
    
    // Draw wrapped row
    $pdf->Row([$no++, $asetText, $userText, $tgl, $tindakan, ''], $alternateFill, 5, $statusColor, $statusLabel);

    $alternateFill = !$alternateFill;
}
$pdf->Ln(6);

// 3.3. Temuan Masalah & Kendala
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(30, 50, 80);
$pdf->Cell(0, 6, 'TEMUAN MASALAH & KENDALA', 0, 1, 'L');
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 9.5);
$pdf->SetTextColor(50, 55, 65);
$hasTemuan = false;
foreach ($data as $d) {
    if (!empty($d['temuan']) && strtolower($d['temuan']) !== 'baik' && strtolower($d['temuan']) !== 'aman' && strtolower($d['temuan']) !== 'ok') {
        $pdf->MultiCell(0, 6, '- ' . $d['kode_aset'] . ' (' . $d['nama_aset'] . '): ' . $d['temuan'] . ' (Status: ' . $d['status'] . ')', 0, 'L');
        $hasTemuan = true;
    }
}
if (!$hasTemuan) {
    $pdf->Cell(0, 6, 'Tidak ditemukan kendala atau temuan masalah berarti (semua perangkat dalam kondisi baik/aman).', 0, 1, 'L');
}
$pdf->Ln(8);

// 3.4. Tanda Tangan
$pdf->SetTextColor(30, 50, 80);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, 'PT BPR Mitratama Arthabuana', 0, 1, 'C');
$pdf->SetTextColor(50, 55, 65);
$pdf->SetFont('Arial', '', 9.5);
$pdf->Cell(0, 6, 'Dibuat pada tanggal: ' . date('d ') . $namaBulan . date(' Y'), 0, 1, 'C');
$pdf->Ln(12);

$pdf->Cell(60, 6, 'Dibuat Oleh,', 0, 0, 'C');
$pdf->Cell(60, 6, 'Mengetahui,', 0, 0, 'C');
$pdf->Cell(60, 6, 'Menyetujui,', 0, 1, 'C');
$pdf->Ln(18);

$teknisi = $data[0]['teknisi'] ?? 'Staff MIS & IT';
$pdf->SetFont('Arial', 'B', 9.5);
$pdf->Cell(60, 6, $teknisi, 0, 0, 'C');
$pdf->Cell(60, 6, 'Kepala Cabang', 0, 0, 'C');
$pdf->Cell(60, 6, 'Direktur Operasional', 0, 1, 'C');

$pdf->Output();
