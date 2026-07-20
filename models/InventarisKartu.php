<?php
class InventarisKartu {
    private $conn;
    private $table = "inventaris_kartu";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = ?";
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

    public function update($id, $data) {
        $sets = "";
        foreach ($data as $key => $value) {
            $sets .= "$key = :$key, ";
        }
        $sets = rtrim($sets, ", ");
        $query = "UPDATE " . $this->table . " SET $sets WHERE id = :id";
        $data['id'] = $id;
        $stmt = $this->conn->prepare($query);
        try {
            return $stmt->execute($data);
        } catch (PDOException $e) {
            error_log("ERROR: InventarisKartu::update failed for ID $id. Error: " . $e->getMessage());
            return false;
        }
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM " . $this->table . " WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
?>
