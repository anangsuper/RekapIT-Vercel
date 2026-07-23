<?php
class HelpdeskTicket {
    private $conn;
    private $table = "helpdesk_tickets";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function generateTicketNumber() {
        $prefix = "TKT-" . date("Ymd") . "-";
        $stmt = $this->conn->prepare("SELECT nomor_tiket FROM " . $this->table . " WHERE nomor_tiket LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$prefix . '%']);
        $last = $stmt->fetchColumn();
        
        if ($last) {
            $num = (int)substr($last, -3) + 1;
        } else {
            $num = 1;
        }
        return $prefix . str_pad($num, 3, "0", STR_PAD_LEFT);
    }

    public function create($data) {
        $data['nomor_tiket'] = $this->generateTicketNumber();
        $fields = implode(", ", array_keys($data));
        $placeholders = ":" . implode(", :", array_keys($data));
        $query = "INSERT INTO " . $this->table . " ($fields) VALUES ($placeholders)";
        $stmt = $this->conn->prepare($query);
        if ($stmt->execute($data)) {
            return $data['nomor_tiket'];
        }
        return false;
    }

    public function getByTicketNumber($ticketNumber) {
        $query = "SELECT t.*, c.nama_cabang, d.nama_divisi, a.nama_aset 
                  FROM " . $this->table . " t
                  LEFT JOIN cabang c ON t.id_cabang = c.id
                  LEFT JOIN divisi d ON t.id_divisi = d.id
                  LEFT JOIN assets a ON t.asset_id = a.id
                  WHERE t.nomor_tiket = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$ticketNumber]);
        return $stmt->fetch();
    }

    public function getAll($status = null, $id_cabang = null, $search = null) {
        $query = "SELECT t.*, c.nama_cabang, d.nama_divisi, a.nama_aset 
                  FROM " . $this->table . " t
                  LEFT JOIN cabang c ON t.id_cabang = c.id
                  LEFT JOIN divisi d ON t.id_divisi = d.id
                  LEFT JOIN assets a ON t.asset_id = a.id
                  WHERE 1=1";
        $params = [];
        if ($status) {
            $query .= " AND t.status = :status";
            $params['status'] = $status;
        }
        if ($id_cabang) {
            $query .= " AND t.id_cabang = :id_cabang";
            $params['id_cabang'] = $id_cabang;
        }
        if ($search) {
            $query .= " AND (t.nomor_tiket LIKE :search OR t.nama_pelapor LIKE :search OR t.kode_aset LIKE :search OR a.nama_aset LIKE :search OR t.keluhan LIKE :search)";
            $params['search'] = "%$search%";
        }
        $query .= " ORDER BY CASE WHEN t.status = 'Menunggu' THEN 1 WHEN t.status = 'Diproses' THEN 2 ELSE 3 END, t.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updateStatus($id, $status, $teknisi = null, $tindakan = null) {
        $query = "UPDATE " . $this->table . " SET status = :status";
        $params = ['status' => $status, 'id' => $id];
        
        if ($teknisi !== null) {
            $query .= ", teknisi_penanggung_jawab = :teknisi";
            $params['teknisi'] = $teknisi;
        }
        if ($tindakan !== null) {
            $query .= ", tindakan_teknisi = :tindakan";
            $params['tindakan'] = $tindakan;
        }
        
        $query .= " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }

    public function countPending() {
        $stmt = $this->conn->query("SELECT COUNT(*) FROM " . $this->table . " WHERE status = 'Menunggu'");
        return (int)$stmt->fetchColumn();
    }
}
?>
