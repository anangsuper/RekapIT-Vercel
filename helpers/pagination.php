<?php
function getPaginationControls($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) return '';
    
    $html = '<nav aria-label="Page navigation" class="mt-4"><ul class="pagination justify-content-center pagination-sm">';
    
    // Previous
    $disabledPrev = ($currentPage <= 1) ? 'disabled' : '';
    $html .= '<li class="page-item '.$disabledPrev.'"><a class="page-link rounded-start-pill" href="'.$baseUrl.'&p='.($currentPage-1).'">Prev</a></li>';

    // Numbers (Simplified for now, can be enhanced for many pages)
    for($i=1; $i<=$totalPages; $i++) {
        $active = ($currentPage == $i) ? 'active' : '';
        $html .= '<li class="page-item '.$active.'"><a class="page-link" href="'.$baseUrl.'&p='.$i.'">'.$i.'</a></li>';
    }

    // Next
    $disabledNext = ($currentPage >= $totalPages) ? 'disabled' : '';
    $html .= '<li class="page-item '.$disabledNext.'"><a class="page-link rounded-end-pill" href="'.$baseUrl.'&p='.($currentPage+1).'">Next</a></li>';
    
    $html .= '</ul></nav>';
    return $html;
}
?>
