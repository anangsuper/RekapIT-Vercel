<?php
class Mutation {
    private $conn;
    private $table = "asset_mutations";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getById($id) {
        $query = "SELECT m.*, a.nama_aset, a.kode_aset, 
                         c1.nama_cabang as cabang_lama, c2.nama_cabang as cabang_baru,
                         d1.nama_divisi as divisi_lama, d2.nama_divisi as divisi_baru,
                         k1.nama_karyawan as karyawan_lama, k2.nama_karyawan as karyawan_baru,
                         u.nama as pelaksana, u2.nama as penyetujui
                  FROM " . $this->table . " m
                  JOIN assets a ON m.asset_id = a.id
                  LEFT JOIN cabang c1 ON m.id_cabang_lama = c1.id
                  LEFT JOIN cabang c2 ON m.id_cabang_baru = c2.id
                  LEFT JOIN divisi d1 ON m.id_divisi_lama = d1.id
                  LEFT JOIN divisi d2 ON m.id_divisi_baru = d2.id
                  LEFT JOIN karyawan k1 ON m.id_karyawan_lama = k1.id
                  LEFT JOIN karyawan k2 ON m.id_karyawan_baru = k2.id
                  LEFT JOIN users u ON m.user_id = u.id
                  LEFT JOIN users u2 ON m.approved_by = u2.id
                  WHERE m.id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getAll($status = null) {
        $query = "SELECT m.*, a.nama_aset, a.kode_aset, 
                         c1.nama_cabang as cabang_lama, c2.nama_cabang as cabang_baru,
                         d1.nama_divisi as divisi_lama, d2.nama_divisi as divisi_baru,
                         k1.nama_karyawan as karyawan_lama, k2.nama_karyawan as karyawan_baru,
                         u.nama as pelaksana, u2.nama as penyetujui
                  FROM " . $this->table . " m
                  JOIN assets a ON m.asset_id = a.id
                  LEFT JOIN cabang c1 ON m.id_cabang_lama = c1.id
                  LEFT JOIN cabang c2 ON m.id_cabang_baru = c2.id
                  LEFT JOIN divisi d1 ON m.id_divisi_lama = d1.id
                  LEFT JOIN divisi d2 ON m.id_divisi_baru = d2.id
                  LEFT JOIN karyawan k1 ON m.id_karyawan_lama = k1.id
                  LEFT JOIN karyawan k2 ON m.id_karyawan_baru = k2.id
                  LEFT JOIN users u ON m.user_id = u.id
                  LEFT JOIN users u2 ON m.approved_by = u2.id
                  WHERE 1=1";
        if ($status) {
            $query .= " AND m.status = :status";
        }
        $query .= " ORDER BY m.id DESC";
        $stmt = $this->conn->prepare($query);
        if ($status) {
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countAll($search = null, $status = null) {
        $query = "SELECT COUNT(m.id) FROM " . $this->table . " m
                  JOIN assets a ON m.asset_id = a.id
                  LEFT JOIN karyawan k2 ON m.id_karyawan_baru = k2.id
                  WHERE 1=1";
        if ($search) {
            $query .= " AND (a.kode_aset LIKE :search OR a.nama_aset LIKE :search OR k2.nama_karyawan LIKE :search)";
        }
        if ($status) {
            $query .= " AND m.status = :status";
        }
        $stmt = $this->conn->prepare($query);
        if ($search) {
            $searchTerm = "%$search%";
            $stmt->bindValue(':search', $searchTerm, PDO::PARAM_STR);
        }
        if ($status) {
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getPaginated($limit, $offset, $search = null, $status = null) {
        $query = "SELECT m.*, a.nama_aset, a.kode_aset, 
                         c1.nama_cabang as cabang_lama, c2.nama_cabang as cabang_baru,
                         d1.nama_divisi as divisi_lama, d2.nama_divisi as divisi_baru,
                         k1.nama_karyawan as karyawan_lama, k2.nama_karyawan as karyawan_baru,
                         u.nama as pelaksana, u2.nama as penyetujui
                  FROM " . $this->table . " m
                  JOIN assets a ON m.asset_id = a.id
                  LEFT JOIN cabang c1 ON m.id_cabang_lama = c1.id
                  LEFT JOIN cabang c2 ON m.id_cabang_baru = c2.id
                  LEFT JOIN divisi d1 ON m.id_divisi_lama = d1.id
                  LEFT JOIN divisi d2 ON m.id_divisi_baru = d2.id
                  LEFT JOIN karyawan k1 ON m.id_karyawan_lama = k1.id
                  LEFT JOIN karyawan k2 ON m.id_karyawan_baru = k2.id
                  LEFT JOIN users u ON m.user_id = u.id
                  LEFT JOIN users u2 ON m.approved_by = u2.id
                  WHERE 1=1";
        if ($search) {
            $query .= " AND (a.kode_aset LIKE :search OR a.nama_aset LIKE :search OR k2.nama_karyawan LIKE :search)";
        }
        if ($status) {
            $query .= " AND m.status = :status";
        }
        $query .= " ORDER BY m.id DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->conn->prepare($query);
        if ($search) {
            $searchTerm = "%$search%";
            $stmt->bindValue(':search', $searchTerm, PDO::PARAM_STR);
        }
        if ($status) {
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create($data) {
        try {
            if (!isset($data['status'])) {
                $data['status'] = 'Disetujui';
            }

            $this->conn->beginTransaction();

            // 1. Catat riwayat mutasi
            $fields = implode(", ", array_keys($data));
            $placeholders = ":" . implode(", :", array_keys($data));
            $query = "INSERT INTO " . $this->table . " ($fields) VALUES ($placeholders)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute($data);

            // 2. Jika disetujui langsung, update lokasi aset di tabel assets
            if ($data['status'] === 'Disetujui') {
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
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ERROR: Mutation::create failed: " . $e->getMessage());
            return false;
        }
    }

    public function approve($id, $user_id) {
        $mutation = $this->getById($id);
        if (!$mutation || $mutation['status'] === 'Disetujui') return false;

        try {
            $this->conn->beginTransaction();

            // 1. Update status mutasi
            $stmt = $this->conn->prepare("UPDATE " . $this->table . " SET status = 'Disetujui', approved_by = ? WHERE id = ?");
            $stmt->execute([$user_id, $id]);

            // 2. Update lokasi fisik di tabel assets
            $updateStmt = $this->conn->prepare("UPDATE assets SET id_cabang = ?, id_divisi = ?, id_karyawan = ? WHERE id = ?");
            $updateStmt->execute([
                $mutation['id_cabang_baru'],
                $mutation['id_divisi_baru'],
                $mutation['id_karyawan_baru'],
                $mutation['asset_id']
            ]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ERROR: Mutation::approve failed: " . $e->getMessage());
            return false;
        }
    }

    public function reject($id, $user_id, $alasan_penolakan) {
        $mutation = $this->getById($id);
        if (!$mutation) return false;

        try {
            $stmt = $this->conn->prepare("UPDATE " . $this->table . " SET status = 'Ditolak', approved_by = ?, alasan_penolakan = ? WHERE id = ?");
            return $stmt->execute([$user_id, $alasan_penolakan, $id]);
        } catch (Exception $e) {
            error_log("ERROR: Mutation::reject failed: " . $e->getMessage());
            return false;
        }
    }
}
?>
