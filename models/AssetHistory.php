<?php
class AssetHistory {
    private $conn;
    private $table = "asset_history";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table . " (asset_id, user_id, field_changed, old_value, new_value) 
                  VALUES (:asset_id, :user_id, :field_changed, :old_value, :new_value)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($data);
    }

    public function logChange($asset_id, $user_id, $field, $old, $new) {
        return $this->create([
            'asset_id' => $asset_id,
            'user_id' => $user_id,
            'field_changed' => $field,
            'old_value' => $old,
            'new_value' => $new
        ]);
    }
}
?>
