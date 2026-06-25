<?php
class Maintenance {
    private $conn;
    private $table = "maintenance";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll($id_cabang = null, $tgl_mulai = null, $tgl_selesai = null) {
        $query = "SELECT m.*, a.nama_aset, a.kode_aset 
                  FROM " . $this->table . " m
                  JOIN assets a ON m.asset_id = a.id
                  WHERE 1=1";
        
        if ($id_cabang) $query .= " AND a.id_cabang = :id_cabang";
        if ($tgl_mulai && $tgl_selesai) $query .= " AND m.tanggal BETWEEN :tgl_mulai AND :tgl_selesai";
        
        $query .= " ORDER BY m.tanggal DESC";
        $stmt = $this->conn->prepare($query);
        
        if ($id_cabang) $stmt->bindParam(':id_cabang', $id_cabang);
        if ($tgl_mulai && $tgl_selesai) {
            $stmt->bindParam(':tgl_mulai', $tgl_mulai);
            $stmt->bindParam(':tgl_selesai', $tgl_selesai);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table . " (asset_id, tanggal, teknisi, temuan, tindakan, rekomendasi, status, id_detail_jadwal) 
                  VALUES (:asset_id, :tanggal, :teknisi, :temuan, :tindakan, :rekomendasi, :status, :id_detail_jadwal)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($data);
    }

    public function createBulk($asset_ids, $commonData) {
        $query = "INSERT INTO " . $this->table . " (asset_id, tanggal, teknisi, temuan, tindakan, rekomendasi, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        
        $this->conn->beginTransaction();
        try {
            foreach ($asset_ids as $id) {
                $stmt->execute([
                    $id,
                    $commonData['tanggal'],
                    $commonData['teknisi'],
                    $commonData['temuan'],
                    $commonData['tindakan'],
                    $commonData['rekomendasi'],
                    $commonData['status']
                ]);
            }
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    public function addPhoto($id_maintenance, $path, $tipe) {
        $query = "INSERT INTO foto_maintenance (id_maintenance, path_foto, tipe) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id_maintenance, $path, $tipe]);
    }

    public function getPhotos($id_maintenance) {
        $query = "SELECT * FROM foto_maintenance WHERE id_maintenance = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id_maintenance]);
        return $stmt->fetchAll();
    }

    public function getReportStats($id_cabang, $bulan, $tahun) {
        $stats = [];
        
        // Total Asset di Cabang
        $queryTotal = "SELECT COUNT(*) FROM assets WHERE id_cabang = :id_cabang";
        $stmt = $this->conn->prepare($queryTotal);
        $stmt->execute(['id_cabang' => $id_cabang]);
        $stats['total_asset'] = $stmt->fetchColumn();

        // Total Komputer (Kategori like 'Komputer' or 'Laptop' or 'PC')
        $queryKomputer = "SELECT COUNT(*) FROM assets a 
                          JOIN kategori_aset k ON a.id_kategori = k.id 
                          WHERE a.id_cabang = :id_cabang 
                          AND (k.nama_kategori LIKE '%Komputer%' OR k.nama_kategori LIKE '%PC%' OR k.nama_kategori LIKE '%Laptop%')";
        $stmt = $this->conn->prepare($queryKomputer);
        $stmt->execute(['id_cabang' => $id_cabang]);
        $stats['total_komputer'] = $stmt->fetchColumn();

        // Total Maintenance Bulan Ini
        $queryMaint = "SELECT COUNT(DISTINCT asset_id) FROM maintenance m
                       JOIN assets a ON m.asset_id = a.id
                       WHERE a.id_cabang = :id_cabang 
                       AND MONTH(m.tanggal) = :bulan AND YEAR(m.tanggal) = :tahun";
        $stmt = $this->conn->prepare($queryMaint);
        $stmt->execute(['id_cabang' => $id_cabang, 'bulan' => $bulan, 'tahun' => $tahun]);
        $stats['total_maintenance'] = $stmt->fetchColumn();

        // Total Temuan (Maintenance with non-empty temuan)
        $queryTemuan = "SELECT COUNT(*) FROM maintenance m
                        JOIN assets a ON m.asset_id = a.id
                        WHERE a.id_cabang = :id_cabang 
                        AND MONTH(m.tanggal) = :bulan AND YEAR(m.tanggal) = :tahun
                        AND (m.temuan IS NOT NULL AND m.temuan != '')";
        $stmt = $this->conn->prepare($queryTemuan);
        $stmt->execute(['id_cabang' => $id_cabang, 'bulan' => $bulan, 'tahun' => $tahun]);
        $stats['total_temuan'] = $stmt->fetchColumn();

        // Total Perbaikan Bulan Ini
        $queryRepair = "SELECT COUNT(*) FROM repairs r
                        JOIN assets a ON r.asset_id = a.id
                        WHERE a.id_cabang = :id_cabang 
                        AND MONTH(r.tanggal_mulai) = :bulan AND YEAR(r.tanggal_mulai) = :tahun";
        $stmt = $this->conn->prepare($queryRepair);
        $stmt->execute(['id_cabang' => $id_cabang, 'bulan' => $bulan, 'tahun' => $tahun]);
        $stats['total_perbaikan'] = $stmt->fetchColumn();

        $stats['total_selesai'] = $stats['total_maintenance'];
        $stats['total_belum'] = $stats['total_asset'] - $stats['total_selesai'];
        $stats['persentase'] = ($stats['total_asset'] > 0) ? round(($stats['total_selesai'] / $stats['total_asset']) * 100, 2) : 0;

        return $stats;
    }

    public function getSummaryPerDivisi($id_cabang, $bulan, $tahun) {
        $query = "SELECT d.nama_divisi, 
                  COUNT(a.id) as total_perangkat,
                  SUM(CASE WHEN m.id IS NOT NULL THEN 1 ELSE 0 END) as selesai,
                  SUM(CASE WHEN m.id IS NULL THEN 1 ELSE 0 END) as belum
                  FROM assets a
                  LEFT JOIN divisi d ON a.id_divisi = d.id
                  LEFT JOIN (
                      SELECT asset_id, id FROM maintenance 
                      WHERE MONTH(tanggal) = :bulan AND YEAR(tanggal) = :tahun
                  ) m ON a.id = m.asset_id
                  WHERE a.id_cabang = :id_cabang
                  GROUP BY a.id_divisi, d.nama_divisi";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['id_cabang' => $id_cabang, 'bulan' => $bulan, 'tahun' => $tahun]);
        return $stmt->fetchAll();
    }

    public function getDetailedByMonth($id_cabang, $bulan, $tahun) {
        $query = "SELECT m.*, a.nama_aset, a.kode_aset, k.nama_karyawan 
                  FROM " . $this->table . " m
                  JOIN assets a ON m.asset_id = a.id
                  LEFT JOIN karyawan k ON a.id_karyawan = k.id
                  WHERE a.id_cabang = :id_cabang 
                  AND MONTH(m.tanggal) = :bulan AND YEAR(m.tanggal) = :tahun
                  ORDER BY m.tanggal ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['id_cabang' => $id_cabang, 'bulan' => $bulan, 'tahun' => $tahun]);
        return $stmt->fetchAll();
    }

    public function getTopFindings($id_cabang, $bulan, $tahun) {
        $query = "SELECT temuan, COUNT(*) as jumlah 
                  FROM maintenance m
                  JOIN assets a ON m.asset_id = a.id
                  WHERE a.id_cabang = :id_cabang 
                  AND MONTH(m.tanggal) = :bulan AND YEAR(m.tanggal) = :tahun
                  AND temuan IS NOT NULL AND temuan != ''
                  GROUP BY temuan
                  ORDER BY jumlah DESC LIMIT 10";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['id_cabang' => $id_cabang, 'bulan' => $bulan, 'tahun' => $tahun]);
        return $stmt->fetchAll();
    }

    public function getUpcomingNotifications($days = 7) {
        // Find maintenance tasks scheduled within the next $days days
        $query = "SELECT m.*, a.nama_aset, a.kode_aset 
                  FROM " . $this->table . " m
                  JOIN assets a ON m.asset_id = a.id
                  WHERE m.tanggal BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL :days DAY)
                  ORDER BY m.tanggal ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getYearlyStats($id_cabang, $tahun) {
        $query = "SELECT MONTH(tanggal) as bulan, COUNT(*) as jumlah 
                  FROM maintenance m
                  JOIN assets a ON m.asset_id = a.id
                  WHERE a.id_cabang = :id_cabang AND YEAR(tanggal) = :tahun
                  GROUP BY MONTH(tanggal)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['id_cabang' => $id_cabang, 'tahun' => $tahun]);
        return $stmt->fetchAll();
    }
}