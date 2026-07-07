<?php
require_once __DIR__ . '/../models/Maintenance.php';
require_once __DIR__ . '/../models/ActivityLog.php';

class MaintenanceController {
    private $model;
    private $logModel;
    private $db;

    public function __construct($db) { 
        $this->db = $db;
        $this->model = new Maintenance($db); 
        $this->logModel = new ActivityLog($db);
    }

    public function index() { return $this->model->getAll(); }
    public function store($data) { 
        $result = $this->model->create($data); 
        if ($result) {
            $this->logModel->add($_SESSION['user_id'], 'MAINTENANCE', "Mencatat maintenance untuk aset ID: " . $data['asset_id']);
            
            // Telegram Alert
            require_once __DIR__ . '/../helpers/notification.php';
            require_once __DIR__ . '/../models/Asset.php';
            $assetModel = new Asset($this->db);
            $asset = $assetModel->getById($data['asset_id']);
            $namaAset = $asset ? $asset['nama_aset'] : "Aset ID: " . $data['asset_id'];
            $kodeAset = $asset ? $asset['kode_aset'] : "-";
            
            $msg = "📋 *CHECKLIST MAINTENANCE ASET*\n\n"
                 . "*• Aset:* " . $namaAset . " (" . $kodeAset . ")\n"
                 . "*• Hasil/Temuan:* " . ($data['temuan'] ?? '-') . "\n"
                 . "*• Tindakan:* " . ($data['tindakan'] ?? '-') . "\n"
                 . "*• Status Aset:* " . ($data['status'] ?? '-') . "\n"
                 . "*• Teknisi:* " . ($data['teknisi'] ?? ($_SESSION['nama'] ?? 'Sistem'));
            sendTelegramNotification($msg);
        }
        return $result;
    }
}
?>
