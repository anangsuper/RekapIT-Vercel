<?php
// helpers/ui.php

function get_branch_badge_style($id_cabang) {
    // Consistent color mapping based on ID
    $hash = crc32((string)$id_cabang);
    $colors = ['#6366f1', '#8b5cf6', '#ec4899', '#3b82f6', '#06b6d4', '#10b981', '#f59e0b'];
    $color = $colors[$hash % count($colors)];
    return "background-color: $color; color: #fff; padding: 0.35rem 0.7rem; border-radius: 10px; font-weight: 700; font-size: 0.72rem; letter-spacing: 0.02em;";
}
?>
