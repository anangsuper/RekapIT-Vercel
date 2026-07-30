<?php
// helpers/ui.php

function get_branch_badge_style($id_cabang) {
    // Consistent color mapping based on ID
    $hash = crc32((string)$id_cabang);
    $colors = ['#6366f1', '#8b5cf6', '#ec4899', '#3b82f6', '#06b6d4', '#10b981', '#f59e0b'];
    $color = $colors[$hash % count($colors)];
    return "background-color: $color; color: #fff; padding: 0.35rem 0.7rem; border-radius: 10px; font-weight: 700; font-size: 0.72rem; letter-spacing: 0.02em;";
}

/**
 * Format date string into Indonesian format
 * Example: 2026-07-30 -> 30 Juli 2026 (or 30 Jul 2026 if short)
 */
function format_tanggal_indonesia($date, $short = false) {
    if (!$date) return '-';
    $time = is_numeric($date) ? $date : strtotime($date);
    if (!$time) return $date;
    
    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $monthsShort = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'
    ];
    
    $dayNum = date('j', $time);
    $monthNum = intval(date('n', $time));
    $year = date('Y', $time);
    
    $monthName = $short ? $monthsShort[$monthNum] : $months[$monthNum];
    
    return "$dayNum $monthName $year";
}
?>
