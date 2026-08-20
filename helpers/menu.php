<?php
/**
 * Helper Konfigurasi Menu Sidebar Navigation Rekap IT
 */

if (!function_exists('getSidebarMenuGrouped')) {
    function getSidebarMenuGrouped($currentPage, $pendingTicketCount = 0) {
        return [
            'MONITORING' => [
                [
                    'page' => 'dashboard',
                    'url' => 'index.php?page=dashboard',
                    'label' => 'Dashboard',
                    'icon' => 'bi bi-grid-1x2-fill',
                    'role' => null
                ],
                [
                    'page' => 'logs',
                    'url' => 'index.php?page=logs',
                    'label' => 'Log Aktivitas',
                    'icon' => 'bi bi-clock-history',
                    'role' => 'admin'
                ]
            ],
            'MANAJEMEN ASET' => [
                [
                    'page' => 'inventaris',
                    'url' => 'index.php?page=inventaris',
                    'label' => 'Data Inventaris Aset',
                    'icon' => 'bi bi-laptop',
                    'role' => null
                ],
                [
                    'page' => 'mutasi',
                    'url' => 'index.php?page=mutasi',
                    'label' => 'Mutasi & Riwayat Aset',
                    'icon' => 'bi bi-arrow-left-right',
                    'role' => 'admin'
                ],
                [
                    'page' => 'cetak_kartu',
                    'url' => 'index.php?page=cetak_kartu',
                    'label' => 'Cetak Kartu & Label',
                    'icon' => 'bi bi-card-heading',
                    'role' => 'admin'
                ]
            ],
            'PERAWATAN & PERBAIKAN' => [
                [
                    'page' => 'maintenance',
                    'url' => 'index.php?page=maintenance&sub=history',
                    'label' => 'Maintenance Rutin',
                    'icon' => 'bi bi-calendar-check',
                    'role' => null
                ],
                [
                    'page' => 'perbaikan',
                    'url' => 'index.php?page=perbaikan',
                    'label' => 'Tiket Perbaikan (Repair)',
                    'icon' => 'bi bi-tools',
                    'role' => null
                ],
                [
                    'page' => 'audit',
                    'url' => 'index.php?page=audit',
                    'label' => 'Audit Fisik Aset',
                    'icon' => 'bi bi-shield-check',
                    'role' => 'admin'
                ],
                [
                    'page' => 'sparepart',
                    'url' => 'index.php?page=sparepart',
                    'label' => 'Stok Suku Cadang (Sparepart)',
                    'icon' => 'bi bi-cpu-fill',
                    'role' => null
                ]
            ],
            'HELPDESK & TIKET' => [
                [
                    'page' => 'tiket_helpdesk',
                    'url' => 'index.php?page=tiket_helpdesk',
                    'label' => 'Tiket Helpdesk',
                    'icon' => 'bi bi-headset',
                    'badge' => ($pendingTicketCount > 0) ? $pendingTicketCount : null,
                    'badge_class' => 'bg-danger',
                    'role' => null
                ]
            ],
            'LAPORAN & ANALISTIK' => [
                [
                    'page' => 'laporan_maintenance',
                    'url' => 'index.php?page=laporan_maintenance',
                    'label' => 'Report Bulanan',
                    'icon' => 'bi bi-file-earmark-bar-graph',
                    'role' => null
                ],
                [
                    'page' => 'laporan',
                    'url' => 'index.php?page=laporan',
                    'label' => 'Ekspor Data Excel',
                    'icon' => 'bi bi-file-earmark-excel',
                    'role' => 'admin'
                ]
            ],
            'MASTER DATA & SISTEM' => [
                [
                    'page' => 'kategori',
                    'url' => 'index.php?page=kategori',
                    'label' => 'Kategori Aset',
                    'icon' => 'bi bi-tags',
                    'role' => 'admin'
                ],
                [
                    'page' => 'cabang',
                    'url' => 'index.php?page=cabang',
                    'label' => 'Data Cabang',
                    'icon' => 'bi bi-building',
                    'role' => 'admin'
                ],
                [
                    'page' => 'divisi',
                    'url' => 'index.php?page=divisi',
                    'label' => 'Data Divisi',
                    'icon' => 'bi bi-people',
                    'role' => 'admin'
                ],
                [
                    'page' => 'karyawan',
                    'url' => 'index.php?page=karyawan',
                    'label' => 'Data Karyawan',
                    'icon' => 'bi bi-person-badge',
                    'role' => 'admin'
                ],
                [
                    'page' => 'pengguna',
                    'url' => 'index.php?page=pengguna',
                    'label' => 'Manajemen Pengguna',
                    'icon' => 'bi bi-person-gear',
                    'role' => 'admin'
                ]
            ]
        ];
    }
}
