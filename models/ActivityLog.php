<?php
class ActivityLog {
    private $db;
    public function __construct($db) { $this->db = $db; }

    public function add($userId, $action, $description) {
        $sql = "INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)";
        $result = $this->db->prepare($sql)->execute([$userId, $action, $description]);
        
        if ($result && $action !== 'LOGIN') {
            // Get Actor Name
            $actor = 'Sistem';
            if ($userId) {
                $stmt = $this->db->prepare("SELECT nama FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                if ($user) {
                    $actor = $user['nama'];
                }
            }
            
            // Send telegram alert
            require_once __DIR__ . '/../helpers/notification.php';
            $msg = "📝 *LOG AKTIVITAS SISTEM*\n\n"
                 . "*• Aktor:* {$actor}\n"
                 . "*• Aksi:* {$action}\n"
                 . "*• Keterangan:* {$description}\n"
                 . "*• Waktu:* " . date('d M Y, H:i:s');
            sendTelegramNotification($msg);
        }
        return $result;
    }

    public function getRecent($limit = 10) {
        $sql = "SELECT l.*, u.nama 
                FROM activity_logs l 
                LEFT JOIN users u ON l.user_id = u.id 
                ORDER BY l.id DESC 
                LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
