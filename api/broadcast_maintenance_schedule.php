// Load session and database configuration
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Fetch upcoming maintenance schedules for next 14 days grouped by branch
$query = "SELECT m.tanggal, a.nama_aset, a.kode_aset, c.nama_cabang 
          FROM maintenance m
          JOIN assets a ON m.asset_id = a.id
          LEFT JOIN cabang c ON a.id_cabang = c.id
          WHERE m.tanggal >= date('now') AND m.tanggal <= date('now', '+14 day')
          ORDER BY c.nama_cabang ASC, m.tanggal ASC";

try {
    $stmt = $conn->query($query);
    $schedules = $stmt->fetchAll();
    
    if (empty($schedules)) {
        echo json_encode(['success' => false, 'error' => 'Tidak ada jadwal maintenance dalam 14 hari ke depan.']);
        exit();
    }
    
    // Group by branch name
    $grouped = [];
    foreach ($schedules as $s) {
        $branch = $s['nama_cabang'] ?: 'Tanpa Cabang';
        $grouped[$branch][] = $s;
    }
    
    // Format message
    $msg = "📅 *JADWAL MAINTENANCE KANTOR CABANG*\n"
         . "_Periode: 14 Hari ke Depan_\n\n";
         
    $totalCount = 0;
    foreach ($grouped as $branchName => $items) {
        $msg .= "🏢 *Cabang {$branchName}:*\n";
        foreach ($items as $item) {
            $formattedDate = date('d M Y', strtotime($item['tanggal']));
            $msg .= "• `{$item['kode_aset']}` - {$item['nama_aset']} ({$formattedDate})\n";
            $totalCount++;
        }
        $msg .= "\n";
    }
    
    $msg .= "_Total Jadwal: {$totalCount} Aset Perlu Diperiksa_";
    
    // Send Telegram Notification
    require_once __DIR__ . '/../helpers/notification.php';
    if (sendTelegramNotification($msg)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Gagal mengirim pesan ke Telegram API.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}
