<?php
class Karyawan {
    private $conn;
    private $table = "karyawan";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll($id_cabang = null) {
        $query = "SELECT k.*, c.nama_cabang, d.nama_divisi 
                  FROM " . $this->table . " k
                  LEFT JOIN cabang c ON k.id_cabang = c.id
                  LEFT JOIN divisi d ON k.id_divisi = d.id";
        
        if ($id_cabang) {
            $query .= " WHERE k.id_cabang = :id_cabang";
        }
        
        $query .= " ORDER BY k.nama_karyawan ASC";
        
        $stmt = $this->conn->prepare($query);
        if ($id_cabang) {
            $stmt->bindParam(':id_cabang', $id_cabang);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table . " (nama_karyawan, nip, id_cabang, id_divisi, jabatan) 
                  VALUES (:nama_karyawan, :nip, :id_cabang, :id_divisi, :jabatan)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($data);
    }

    public function isNipExists($nip) {
        if (empty($nip)) return false;
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM " . $this->table . " WHERE nip = ?");
        $stmt->execute([$nip]);
        return $stmt->fetchColumn() > 0;
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM " . $this->table . " WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
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
        return $stmt->execute($data);
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM " . $this->table . " WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
?>