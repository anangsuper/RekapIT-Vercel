<?php
class KategoriAset {
    private $conn;
    private $table = "kategori_aset";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $stmt = $this->conn->prepare("SELECT * FROM " . $this->table . " ORDER BY nama_kategori ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table . " (nama_kategori) VALUES (:nama_kategori)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($data);
    }

    public function update($id, $data) {
        $query = "UPDATE " . $this->table . " SET nama_kategori = :nama_kategori WHERE id = :id";
        $data['id'] = $id;
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($data);
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM " . $this->table . " WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
?>