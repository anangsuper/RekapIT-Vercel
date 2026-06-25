<?php
require_once __DIR__ . '/../models/Repair.php';
require_once __DIR__ . '/../models/ActivityLog.php';

class RepairController {
    private $model;
    private $logModel;

    public function __construct($db) { 
        $this->model = new Repair($db); 
        $this->logModel = new ActivityLog($db);
    }

    public function index() { return $this->model->getAll(); }
    public function store($data) { 
        $result = $this->model->create($data); 
        if ($result) {
            $this->logModel->add($_SESSION['user_id'], 'LAPOR_RUSAK', "Melaporkan kerusakan aset ID: " . $data['asset_id']);
        }
        return $result;
    }
    public function update($id, $data) { 
        $result = $this->model->updateStatus($id, $data); 
        if ($result) {
            $this->logModel->add($_SESSION['user_id'], 'UPDATE_PERBAIKAN', "Update perbaikan ID: $id (" . $data['status'] . ")");
        }
        return $result;
    }
}
?>
