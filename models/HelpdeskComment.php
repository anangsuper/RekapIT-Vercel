<?php
class HelpdeskComment {
    private $conn;
    private $table = "helpdesk_comments";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function addComment($ticketId, $userId, $senderName, $senderRole, $message) {
        $query = "INSERT INTO " . $this->table . " (ticket_id, user_id, sender_name, sender_role, message) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$ticketId, $userId, $senderName, $senderRole, $message]);
    }

    public function getByTicketId($ticketId) {
        $query = "SELECT * FROM " . $this->table . " WHERE ticket_id = ? ORDER BY created_at ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }
}
?>
