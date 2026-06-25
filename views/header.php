<?php
require_once 'models/Maintenance.php';
$mModel = new Maintenance($conn);
$notifications = $mModel->getUpcomingNotifications(7);
$notifCount = count($notifications);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap IT - Asset Management</title>

    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        @import url("https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap");

        :root {
            --primary-color: #4361ee;
            --primary-light: rgba(67, 97, 238, 0.1);
            --secondary-color: #3f37c9;
            /* Vibrant gradient background */
            --bg-body: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            --navbar-bg: #0f172a;
            --navbar-text: #94a3b8;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --card-shadow-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --glass-bg: rgba(255, 255, 255, 0.85);
        }

        body {
            font-family: "Plus Jakarta Sans", sans-serif;
            background: var(--bg-body);
            background-attachment: fixed;
            min-height: 100vh;
            color: #1e293b;
            letter-spacing: -0.01em;
            padding-top: 90px;
            overflow-x: hidden;
            line-height: 1.6;
        }
        /* Navbar Modern */
        .navbar-custom {
            background: var(--navbar-bg);
            padding: 12px 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            z-index: 3000; /* Increased to ensure it's above dashboard content */
        }

        .modal {
            z-index: 1060 !important;
        }
        .modal-backdrop {
            z-index: 1050 !important;
        }

        .navbar-brand h4 {
            font-weight: 800;
            letter-spacing: -0.5px;
            margin: 0;
            font-size: 1.4rem;
            background: linear-gradient(to right, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-link {
            color: var(--navbar-text) !important;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 10px 15px !important;
            border-radius: 10px;
            transition: 0.2s;
        }

        .nav-link:hover, .nav-link.active {
            color: #fff !important;
            background: rgba(255, 255, 255, 0.05);
        }

        .nav-link.active {
            background: var(--primary-color) !important;
            box-shadow: 0 8px 15px -5px rgba(67, 97, 238, 0.4);
        }

        .dropdown-menu {
            background: var(--navbar-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            padding: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .dropdown-item {
            color: var(--navbar-text);
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 0.875rem;
            transition: 0.2s;
        }

        .dropdown-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
        }

        /* Main Content */
        .main-content {
            padding: 30px 15px;
            min-height: calc(100vh - 85px);
        }

        .page-header {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 20px 30px;
            margin-bottom: 30px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: var(--card-shadow);
        }

        /* Global UI Elements */
        .card {
            border: 1px solid rgba(0, 0, 0, 0.03);
            border-radius: 24px;
            box-shadow: var(--card-shadow);
            background: #fff;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 10px 24px;
            border-radius: 14px;
            font-weight: 600;
        }

        /* Animations - Simplified without transforms on large wrappers */
        .animate-fade-in {
            animation: fadeInSimple 0.4s ease-out forwards;
        }

        @keyframes fadeInSimple {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (max-width: 991.98px) {
            body { padding-top: 70px; }
            .main-content { padding: 15px; }
            .navbar-collapse { 
                background: var(--navbar-bg); 
                padding: 15px; 
                border-radius: 15px; 
                margin-top: 10px; 
            }
        }
    </style>
</head>

<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
    <div class="container">
        <a class="navbar-brand" href="index.php?page=dashboard">
            <h4><i class="fas fa-microchip me-2"></i> REKAP IT</h4>
        </a>
        
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4 gap-1">
                <li class="nav-item">
                    <a href="index.php?page=dashboard" class="nav-link <?= ($page == 'dashboard') ? 'active' : '' ?>">
                        <i class="bi bi-grid-1x2-fill me-1"></i> Dashboard
                    </a>
                </li>

                <?php if (hasRole('admin')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($page, ['cabang', 'divisi', 'karyawan']) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-database me-1"></i> Data Master
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="index.php?page=cabang">Cabang</a></li>
                        <li><a class="dropdown-item" href="index.php?page=divisi">Divisi</a></li>
                        <li><a class="dropdown-item" href="index.php?page=karyawan">Karyawan</a></li>
                        <li><a class="dropdown-item" href="index.php?page=pengguna">Pengguna</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($page, ['kategori', 'inventaris', 'mutasi']) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-laptop me-1"></i> Aset
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="index.php?page=inventaris">Data Aset</a></li>
                        <li><a class="dropdown-item" href="index.php?page=kategori">Kategori</a></li>
                        <li><a class="dropdown-item" href="index.php?page=mutasi">Mutasi</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= ($page == 'maintenance') ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-calendar-check me-1"></i> Maintenance
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="index.php?page=maintenance&sub=history">History Maintenance</a></li>
                        <li><a class="dropdown-item" href="index.php?page=maintenance&sub=massal">Bulk Maintenance</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($page, ['perbaikan', 'sparepart']) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-tools me-1"></i> Perbaikan
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="index.php?page=perbaikan">Tiket Perbaikan</a></li>
                        <li><a class="dropdown-item" href="index.php?page=sparepart">Sparepart</a></li>
                    </ul>
                </li>

                <?php if (hasRole('admin')): ?>
                <li class="nav-item">
                    <a href="index.php?page=audit" class="nav-link <?= ($page == 'audit') ? 'active' : '' ?>">
                        <i class="bi bi-shield-check me-1"></i> Audit
                    </a>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($page, ['laporan', 'logs', 'laporan_maintenance']) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-file-earmark-bar-graph me-1"></i> Laporan
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="index.php?page=laporan">Export Excel</a></li>
                        <li><a class="dropdown-item" href="index.php?page=laporan_maintenance">Laporan Maintenance</a></li>
                        <li><a class="dropdown-item" href="index.php?page=logs">Log Aktivitas</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>

            <div class="navbar-nav align-items-center">
                <!-- Notifications -->
                <div class="dropdown me-3">
                    <button class="nav-link position-relative p-2 border-0 bg-transparent" data-bs-toggle="dropdown" aria-expanded="false" id="notifDropdown">
                        <i class="bi bi-bell fs-5"></i>
                        <?php if ($notifCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-dark" style="font-size: 0.6rem;">
                                <?= $notifCount ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 animate-slide-down" style="width: 320px; border-radius: 20px; overflow: hidden; z-index: 9999;">
                        <li class="px-4 py-3 border-bottom bg-light">
                            <h6 class="m-0 fw-bold text-dark">Notifikasi Maintenance</h6>
                            <small class="text-muted"><?= $notifCount ?> jadwal dalam 7 hari kedepan</small>
                        </li>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php if ($notifCount === 0): ?>
                                <li class="px-4 py-3 text-muted small">Tidak ada jadwal maintenance</li>
                            <?php else: ?>
                                <?php foreach ($notifications as $n): ?>
                                    <li class="border-bottom">
                                        <a class="dropdown-item px-4 py-3 text-dark transition-hover" href="index.php?page=maintenance">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3">
                                                    <i class="bi bi-tools text-primary"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold small text-truncate"><?= $n['kode_aset'] ?> - <?= $n['nama_aset'] ?></div>
                                                    <small class="text-muted"><?= date('d M Y', strtotime($n['tanggal'])) ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <li class="px-4 py-2 border-top bg-light text-center">
                            <a href="index.php?page=maintenance" class="text-primary text-decoration-none small fw-bold">Lihat Semua</a>
                        </li>
                    </ul>
                </div>

                <!-- User Profile -->
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nama']) ?>&background=4361ee&color=fff" class="rounded-circle" width="32">
                        <span class="small fw-bold text-white d-none d-xl-inline"><?= $_SESSION['nama'] ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="px-3 py-2 border-bottom border-secondary mb-2">
                            <div class="small text-muted">Signed in as</div>
                            <div class="fw-bold text-white"><?= $_SESSION['username'] ?></div>
                        </li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<div class="main-content container">
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h4 class="m-0 fw-bold"><?= ucwords(str_replace('_', ' ', $page)) ?></h4>
            <div class="small text-muted d-none d-sm-block">Sistem Manajemen Aset IT</div>
        </div>
        <div class="d-none d-md-flex align-items-center gap-3">
            <div class="text-end">
                <div class="small fw-bold" id="realtime-clock">Loading time...</div>
                <div class="text-muted" style="font-size: 0.7rem;">Status: <span class="text-success fw-bold">Online</span></div>
            </div>
            <!-- Notifications moved out -->
        </div>
    </div>

<style>
    .animate-slide-down {
        animation: slideDown 0.3s ease-out forwards;
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .transition-hover { transition: all 0.2s ease; }
    .transition-hover:hover { background-color: #f8fafc; }
</style>


    <div class="content-body">
        <div class="animate-fade-in">
<script>
    function updateClock() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
        document.getElementById('realtime-clock').textContent = now.toLocaleDateString('id-ID', options);
    }
    setInterval(updateClock, 1000);
    updateClock();
</script>
