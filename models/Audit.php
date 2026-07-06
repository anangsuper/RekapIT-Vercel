<?php
class Audit {
    private $conn;
    private $table = "audits";

    public function __construct($db) {
        $this->conn = $db;
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
