<?php
class Asset {
    private $conn;
    private $table = "assets";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAssetsAvailableForMaintenance($month, $year) {
        $query = "SELECT a.*, k.nama_kategori, c.nama_cabang, d.nama_divisi, kr.nama_karyawan 
                  FROM " . $this->table . " a
                  LEFT JOIN kategori_aset k ON a.id_kategori = k.id
                  LEFT JOIN cabang c ON a.id_cabang = c.id
                  LEFT JOIN divisi d ON a.id_divisi = d.id
                  LEFT JOIN karyawan kr ON a.id_karyawan = kr.id
                  WHERE a.id NOT IN (
                      SELECT asset_id 
                      FROM maintenance 
                      WHERE MONTH(tanggal) = :month AND YEAR(tanggal) = :year
                  )
                  ORDER BY a.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['month' => $month, 'year' => $year]);
        return $stmt->fetchAll();
    }

    public function getAll($id_cabang = null) {
        $query = "SELECT a.*, k.nama_kategori, c.nama_cabang, d.nama_divisi, kr.nama_karyawan 
                  FROM " . $this->table . " a
                  LEFT JOIN kategori_aset k ON a.id_kategori = k.id
                  LEFT JOIN cabang c ON a.id_cabang = c.id
                  LEFT JOIN divisi d ON a.id_divisi = d.id
                  LEFT JOIN karyawan kr ON a.id_karyawan = kr.id";
        
        if ($id_cabang) {
            $query .= " WHERE a.id_cabang = :id_cabang";
        }
        
        $query .= " ORDER BY a.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        if ($id_cabang) {
            $stmt->bindParam(':id_cabang', $id_cabang);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $query = "SELECT a.*, k.nama_kategori, c.nama_cabang, d.nama_divisi, kr.nama_karyawan 
                  FROM " . $this->table . " a
                  LEFT JOIN kategori_aset k ON a.id_kategori = k.id
                  LEFT JOIN cabang c ON a.id_cabang = c.id
                  LEFT JOIN divisi d ON a.id_divisi = d.id
                  LEFT JOIN karyawan kr ON a.id_karyawan = kr.id
                  WHERE a.id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function create($data) {
        $fields = implode(", ", array_keys($data));
        $placeholders = ":" . implode(", :", array_keys($data));
        $query = "INSERT INTO " . $this->table . " ($fields) VALUES ($placeholders)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($data);
    }

    public function update($id, $data, $user_id = null) {
        $old_asset = $this->getById($id);
        
        $sets = "";
        foreach ($data as $key => $value) {
            $sets .= "$key = :$key, ";
            
            // Log history if changed
            if ($user_id && isset($old_asset[$key]) && $old_asset[$key] != $value) {
                require_once 'AssetHistory.php';
                $history = new AssetHistory($this->conn);
                $history->create([
                    'asset_id' => $id,
                    'user_id' => $user_id,
                    'field_changed' => $key,
                    'old_value' => $old_asset[$key],
                    'new_value' => $value
                ]);
            }
        }
        $sets = rtrim($sets, ", ");
        $query = "UPDATE " . $this->table . " SET $sets WHERE id = :id";
        $data['id'] = $id;
        $stmt = $this->conn->prepare($query);
        
        try {
            return $stmt->execute($data);
        } catch (PDOException $e) {
            error_log("ERROR: Asset::update failed for ID $id. Error: " . $e->getMessage());
            return false;
        }
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM " . $this->table . " WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getStats() {
        $stats = [];
        $stats['total'] = $this->conn->query("SELECT COUNT(*) FROM assets")->fetchColumn();
        $stats['baik'] = $this->conn->query("SELECT COUNT(*) FROM assets WHERE kondisi = 'Baik'")->fetchColumn();
        $stats['rusak_ringan'] = $this->conn->query("SELECT COUNT(*) FROM assets WHERE kondisi = 'Rusak Ringan'")->fetchColumn();
        $stats['rusak_berat'] = $this->conn->query("SELECT COUNT(*) FROM assets WHERE kondisi = 'Rusak Berat'")->fetchColumn();
        return $stats;
    }

    public function countAll($id_cabang = null, $kondisi = null, $search = null) {
        $query = "SELECT COUNT(*) FROM " . $this->table . " a
                  LEFT JOIN karyawan kr ON a.id_karyawan = kr.id
                  WHERE 1=1";
        if ($id_cabang) {
            $query .= " AND a.id_cabang = :id_cabang";
        }
        if ($kondisi) {
            if ($kondisi === 'rusak') {
                $query .= " AND a.kondisi IN ('Rusak Ringan', 'Rusak Berat')";
            } else {
                $query .= " AND a.kondisi = :kondisi";
            }
        }
        if ($search) {
            $query .= " AND (a.kode_aset LIKE :search OR a.nama_aset LIKE :search OR a.serial_number LIKE :search OR a.merk LIKE :search OR a.model LIKE :search OR kr.nama_karyawan LIKE :search)";
        }
        $stmt = $this->conn->prepare($query);
        if ($id_cabang) {
            $stmt->bindValue(':id_cabang', $id_cabang, PDO::PARAM_INT);
        }
        if ($kondisi && $kondisi !== 'rusak') {
            $stmt->bindValue(':kondisi', $kondisi, PDO::PARAM_STR);
        }
        if ($search) {
            $searchTerm = "%$search%";
            $stmt->bindValue(':search', $searchTerm, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getPaginated($limit, $offset, $id_cabang = null, $kondisi = null, $search = null) {
        $query = "SELECT a.*, k.nama_kategori, c.nama_cabang, d.nama_divisi, kr.nama_karyawan 
                  FROM " . $this->table . " a
                  LEFT JOIN kategori_aset k ON a.id_kategori = k.id
                  LEFT JOIN cabang c ON a.id_cabang = c.id
                  LEFT JOIN divisi d ON a.id_divisi = d.id
                  LEFT JOIN karyawan kr ON a.id_karyawan = kr.id
                  WHERE 1=1";
        
        if ($id_cabang) {
            $query .= " AND a.id_cabang = :id_cabang";
        }
        if ($kondisi) {
            if ($kondisi === 'rusak') {
                $query .= " AND a.kondisi IN ('Rusak Ringan', 'Rusak Berat')";
            } else {
                $query .= " AND a.kondisi = :kondisi";
            }
        }
        if ($search) {
            $query .= " AND (a.kode_aset LIKE :search OR a.nama_aset LIKE :search OR a.serial_number LIKE :search OR a.merk LIKE :search OR a.model LIKE :search OR kr.nama_karyawan LIKE :search)";
        }
        
        $query .= " ORDER BY a.created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        if ($id_cabang) {
            $stmt->bindValue(':id_cabang', $id_cabang, PDO::PARAM_INT);
        }
        if ($kondisi && $kondisi !== 'rusak') {
            $stmt->bindValue(':kondisi', $kondisi, PDO::PARAM_STR);
        }
        if ($search) {
            $searchTerm = "%$search%";
            $stmt->bindValue(':search', $searchTerm, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
?>
