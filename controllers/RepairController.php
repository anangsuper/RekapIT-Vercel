<?php
require_once __DIR__ . '/../models/Repair.php';
require_once __DIR__ . '/../models/ActivityLog.php';

class RepairController {
    private $model;
    private $logModel;
    private $db; // Add this property

    public function __construct($db) { 
        $this->db = $db; // Store the db connection
        $this->model = new Repair($db); 
        $this->logModel = new ActivityLog($db);
    }

    public function index() { return $this->model->getAll(); }
    public function getPaginated($limit, $offset, $id_cabang = null, $search = null) {
        return $this->model->getPaginated($limit, $offset, $id_cabang, $search);
    }
    public function countAll($id_cabang = null, $search = null) {
        return $this->model->countAll($id_cabang, $search);
    }
    public function store($data) { 
        $result = $this->model->create($data); 
        if ($result) {
            $this->logModel->add($_SESSION['user_id'], 'LAPOR_RUSAK', "Melaporkan kerusakan aset ID: " . $data['asset_id']);
        }
        return $result;
    }
    public function update($id, $data) {
        $repairDetails = $this->model->getById($id);
        
        $result = $this->model->update($id, $data); 
        
        // JIKA STATUS JADI SELESAI, UPDATE KONDISI ASET MENJADI BAIK
        if ($result && isset($data['status']) && strcasecmp(trim($data['status']), 'Selesai') === 0 && $repairDetails) {
            $assetId = $repairDetails['asset_id'];
            
            require_once __DIR__ . '/../models/Asset.php';
            $assetModel = new Asset($this->db);
            $assetModel->update($assetId, ['kondisi' => 'Baik'], $_SESSION['user_id'] ?? null);
            
            error_log("DEBUG: Update kondisi aset $assetId ke Baik via Asset Model.");
        }
        
        if ($result) {
            $this->logModel->add($_SESSION['user_id'], 'UPDATE_PERBAIKAN', "Update perbaikan ID: $id (" . $data['status'] . ")");
        }
        return $result;
    }

    // getRepairById no longer needed if using getById directly
}
?>
