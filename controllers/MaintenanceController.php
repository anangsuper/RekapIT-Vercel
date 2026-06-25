<?php
require_once __DIR__ . '/../models/Maintenance.php';
require_once __DIR__ . '/../models/ActivityLog.php';

class MaintenanceController {
    private $model;
    private $logModel;

    public function __construct($db) { 
        $this->model = new Maintenance($db); 
        $this->logModel = new ActivityLog($db);
    }

    public function index() { return $this->model->getAll(); }
    public function store($data) { 
        $result = $this->model->create($data); 
        if ($result) {
            $this->logModel->add($_SESSION['user_id'], 'MAINTENANCE', "Mencatat maintenance untuk aset ID: " . $data['asset_id']);
        }
        return $result;
    }
}
?>
