<?php
class Mutation {
    private $conn;
    private $table = "asset_mutations";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $query = "SELECT m.*, a.nama_aset, a.kode_aset, 
                         c1.nama_cabang as cabang_lama, c2.nama_cabang as cabang_baru,
                         d1.nama_divisi as divisi_lama, d2.nama_divisi as divisi_baru,
                         k1.nama_karyawan as karyawan_lama, k2.nama_karyawan as karyawan_baru,
                         u.nama as pelaksana
                  FROM " . $this->table . " m
                  JOIN assets a ON m.asset_id = a.id
                  LEFT JOIN cabang c1 ON m.id_cabang_lama = c1.id
                  LEFT JOIN cabang c2 ON m.id_cabang_baru = c2.id
                  LEFT JOIN divisi d1 ON m.id_divisi_lama = d1.id
                  LEFT JOIN divisi d2 ON m.id_divisi_baru = d2.id
                  LEFT JOIN karyawan k1 ON m.id_karyawan_lama = k1.id
                  LEFT JOIN karyawan k2 ON m.id_karyawan_baru = k2.id
                  LEFT JOIN users u ON m.user_id = u.id
                  ORDER BY m.id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countAll($search = null) {
        $query = "SELECT COUNT(m.id) FROM " . $this->table . " m
                  JOIN assets a ON m.asset_id = a.id
                  LEFT JOIN karyawan k2 ON m.id_karyawan_baru = k2.id
                  WHERE 1=1";
        if ($search) {
            $query .= " AND (a.kode_aset LIKE :search OR a.nama_aset LIKE :search OR k2.nama_karyawan LIKE :search)";
        }
        $stmt = $this->conn->prepare($query);
        if ($search) {
            $searchTerm = "%$search%";
            $stmt->bindValue(':search', $searchTerm, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getPaginated($limit, $offset, $search = null) {
        $query = "SELECT m.*, a.nama_aset, a.kode_aset, 
                         c1.nama_cabang as cabang_lama, c2.nama_cabang as cabang_baru,
                         d1.nama_divisi as divisi_lama, d2.nama_divisi as divisi_baru,
                         k1.nama_karyawan as karyawan_lama, k2.nama_karyawan as karyawan_baru,
                         u.nama as pelaksana
                  FROM " . $this->table . " m
                  JOIN assets a ON m.asset_id = a.id
                  LEFT JOIN cabang c1 ON m.id_cabang_lama = c1.id
                  LEFT JOIN cabang c2 ON m.id_cabang_baru = c2.id
                  LEFT JOIN divisi d1 ON m.id_divisi_lama = d1.id
                  LEFT JOIN divisi d2 ON m.id_divisi_baru = d2.id
                  LEFT JOIN karyawan k1 ON m.id_karyawan_lama = k1.id
                  LEFT JOIN karyawan k2 ON m.id_karyawan_baru = k2.id
                  LEFT JOIN users u ON m.user_id = u.id
                  WHERE 1=1";
        if ($search) {
            $query .= " AND (a.kode_aset LIKE :search OR a.nama_aset LIKE :search OR k2.nama_karyawan LIKE :search)";
        }
        $query .= " ORDER BY m.id DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->conn->prepare($query);
        if ($search) {
            $searchTerm = "%$search%";
            $stmt->bindValue(':search', $searchTerm, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create($data) {
        try {
            $this->conn->beginTransaction();

            // 1. Catat riwayat mutasi
            $fields = implode(", ", array_keys($data));
            $placeholders = ":" . implode(", :", array_keys($data));
            $query = "INSERT INTO " . $this->table . " ($fields) VALUES ($placeholders)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute($data);

            // 2. Update status/lokasi aset di tabel assets
            $updateQuery = "UPDATE assets SET 
                            id_cabang = :id_cabang, 
                            id_divisi = :id_divisi, 
                            id_karyawan = :id_karyawan 
                            WHERE id = :asset_id";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->execute([
                'id_cabang' => $data['id_cabang_baru'],
                'id_divisi' => $data['id_divisi_baru'],
                'id_karyawan' => $data['id_karyawan_baru'],
                'asset_id' => $data['asset_id']
            ]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
}
?>
