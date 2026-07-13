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
            
            // Telegram Alert
            require_once __DIR__ . '/../helpers/notification.php';
            require_once __DIR__ . '/../models/Asset.php';
            $assetModel = new Asset($this->db);
            $asset = $assetModel->getById($data['asset_id']);
            $namaAset = $asset ? $asset['nama_aset'] : "Aset ID: " . $data['asset_id'];
            $kodeAset = $asset ? $asset['kode_aset'] : "-";
            
            // Set priority classification tag
            $priorityEmoji = "🚨";
            if ($asset && strcasecmp($asset['kondisi'], 'Rusak Berat') === 0) {
                $priorityEmoji = "🔴 *[KRITIS]*";
            } elseif ($asset && strcasecmp($asset['kondisi'], 'Rusak Ringan') === 0) {
                $priorityEmoji = "🟡 *[WARNING]*";
            }
            
            $appUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            
            $msg = "{$priorityEmoji} *TIKET PERBAIKAN BARU*\n\n"
                 . "*• Aset:* " . $namaAset . " (" . $kodeAset . ")\n"
                 . "*• Masalah:* " . ($data['keluhan'] ?? '-') . "\n"
                 . "*• Pelapor:* " . ($_SESSION['nama'] ?? 'Sistem') . "\n\n"
                 . "🔗 [Buka Tiket Perbaikan]({$appUrl}/index.php?page=perbaikan)";
            sendTelegramNotification($msg);
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
            
            // Telegram Alert
            require_once __DIR__ . '/../helpers/notification.php';
            require_once __DIR__ . '/../models/Asset.php';
            $assetModel = new Asset($this->db);
            $assetId = $repairDetails ? $repairDetails['asset_id'] : null;
            $asset = $assetId ? $assetModel->getById($assetId) : null;
            $namaAset = $asset ? $asset['nama_aset'] : "Aset ID: " . $assetId;
            $kodeAset = $asset ? $asset['kode_aset'] : "-";
            
            $appUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            
            $msg = "🛠 *UPDATE TIKET PERBAIKAN*\n\n"
                 . "*• Aset:* " . $namaAset . " (" . $kodeAset . ")\n"
                 . "*• Status Baru:* " . ($data['status'] ?? '-') . "\n"
                 . "*• Solusi/Tindakan:* " . ($data['tindakan'] ?? '-') . "\n"
                 . "*• Diperbarui Oleh:* " . ($_SESSION['nama'] ?? 'Sistem') . "\n\n"
                 . "🔗 [Buka Tiket Perbaikan]({$appUrl}/index.php?page=perbaikan)";
            sendTelegramNotification($msg);
        }
        return $result;
    }

    // getRepairById no longer needed if using getById directly
}
?>
