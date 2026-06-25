<?php
class Audit {
    private $conn;
    private $table = "audits";

    public function __construct($db) {
        $this->conn = $db;
        $this->ensureTableExists();
    }

    private function ensureTableExists() {
        $sql = "CREATE TABLE IF NOT EXISTS " . $this->table . " (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id INT NOT NULL,
            user_id INT NOT NULL,
            tanggal_audit DATE NOT NULL,
            kondisi_dilaporkan ENUM('Baik', 'Rusak Ringan', 'Rusak Berat'),
            kondisi_fisik ENUM('Baik', 'Rusak Ringan', 'Rusak Berat') NOT NULL,
            lokasi_fisik VARCHAR(100),
            catatan TEXT,
            status_verifikasi ENUM('Sesuai', 'Tidak Sesuai') DEFAULT 'Sesuai',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->conn->exec($sql);
    }

    public function getAll() {
        $query = "SELECT au.*, a.nama_aset, a.kode_aset, u.nama as auditor
                  FROM " . $this->table . " au
                  JOIN assets a ON au.asset_id = a.id
                  JOIN users u ON au.user_id = u.id
                  ORDER BY au.tanggal_audit DESC, au.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create($data) {
        try {
            $this->conn->beginTransaction();

            // 1. Simpan data audit
            $fields = implode(", ", array_keys($data));
            $placeholders = ":" . implode(", :", array_keys($data));
            $query = "INSERT INTO " . $this->table . " ($fields) VALUES ($placeholders)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute($data);

            // 2. Update kondisi aset jika audit menunjukkan perubahan
            $updateQuery = "UPDATE assets SET kondisi = :kondisi WHERE id = :id";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->execute([
                'kondisi' => $data['kondisi_fisik'],
                'id' => $data['asset_id']
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
