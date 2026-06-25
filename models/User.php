<?php
class User {
    private $conn;
    private $table = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        // Menambahkan id_cabang untuk membatasi akses teknisi per cabang
        $query = "SELECT u.*, c.nama_cabang 
                  FROM " . $this->table . " u
                  LEFT JOIN cabang c ON u.id_cabang = c.id
                  ORDER BY u.nama ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table . " (nama, username, password, role, id_cabang) 
                  VALUES (:nama, :username, :password, :role, :id_cabang)";
        $stmt = $this->conn->prepare($query);
        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        return $stmt->execute($data);
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM " . $this->table . " WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
?>
