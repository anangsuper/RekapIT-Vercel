<?php

require_once __DIR__ . "/../models/Asset.php";
require_once __DIR__ . "/../models/ActivityLog.php";
require_once __DIR__ . "/../models/AssetHistory.php";

class AssetController
{
    private $assetModel;
    private $logModel;
    private $historyModel;
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        $this->assetModel = new Asset($db);
        $this->logModel = new ActivityLog($db);
        $this->historyModel = new AssetHistory($db);
    }

    public function index()
    {
        return $this->assetModel->getAll();
    }

    public function store($data, $files)
    {
        checkAccess(['admin']);
        try {
            $foto = '';

            if (isset($files['foto']) && $files['foto']['error'] === 0) {
                $uploadDir = __DIR__ . '/../uploads/';
                
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileName = time() . '_' . basename($files['foto']['name']);
                $target = $uploadDir . $fileName;

                if (move_uploaded_file($files['foto']['tmp_name'], $target)) {
                    $foto = 'uploads/' . $fileName;
                } else {
                    error_log("Gagal memindahkan file ke: " . $target);
                }
            }

            $data['foto'] = $foto;
            $result = $this->assetModel->create($data);
            
            if ($result) {
                $this->logModel->add($_SESSION['user_id'], 'TAMBAH_ASET', "Menambah aset baru: " . $data['kode'] . " (" . $data['nama'] . ")");
            }
            
            return $result;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                return "duplicate";
            }
            throw $e;
        }
    }

    public function update($id, $data, $files)
    {
        checkAccess(['admin']);
        $existing = $this->assetModel->getById($id);
        $foto = $existing['foto'];

        if (
            isset($files['foto']) &&
            $files['foto']['error'] === 0
        ) {
            $fileName = time() . '_' . basename($files['foto']['name']);
            $target = __DIR__ . '/../uploads/' . $fileName;

            if (move_uploaded_file($files['foto']['tmp_name'], $target)) {
                $foto = 'uploads/' . $fileName;
            }
        }

        $data['foto'] = $foto;
        $result = $this->assetModel->update($id, $data, $_SESSION['user_id']);
        
        if ($result) {
            $this->logModel->add($_SESSION['user_id'], 'UPDATE_ASET', "Memperbarui data aset: " . $data['kode']);
        }

        return $result;
    }

    public function destroy($id)
    {
        checkAccess(['admin']);
        $asset = $this->assetModel->getById($id);
        $result = $this->assetModel->delete($id);
        
        if ($result) {
            $this->logModel->add($_SESSION['user_id'], 'HAPUS_ASET', "Menghapus aset: " . $asset['kode_aset']);
        }
        
        return $result;
    }
}
?>
