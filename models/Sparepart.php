<?php
class Sparepart {
    private $conn;
    private $table = "sparepart";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $stmt = $this->conn->prepare("SELECT * FROM " . $this->table . " ORDER BY nama_sparepart ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table . " (nama_sparepart, kode_sparepart, stok, satuan) 
                  VALUES (:nama_sparepart, :kode_sparepart, :stok, :satuan)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($data);
    }

    public function updateStok($id, $jumlah) {
        $query = "UPDATE " . $this->table . " SET stok = stok + :jumlah WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $id, 'jumlah' => $jumlah]);
    }

    public function update($id, $data) {
        $query = "UPDATE " . $this->table . " SET 
                  nama_sparepart = :nama_sparepart, 
                  kode_sparepart = :kode_sparepart, 
                  stok = :stok, 
                  satuan = :satuan 
                  WHERE id = :id";
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
