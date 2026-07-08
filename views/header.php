<?php
require_once 'models/Maintenance.php';
$mModel = new Maintenance($conn);
$notifications = $mModel->getUpcomingNotifications(7);
$notifCount = count($notifications);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php
    $base_path = '/';
    if (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1')) {
        $script_name = $_SERVER['SCRIPT_NAME'];
        $base_dir = str_replace(basename($script_name), '', $script_name);
        $base_path = '/' . trim($base_dir, '/') . '/';
        if ($base_path === '//') $base_path = '/';
    }
    ?>
    <base href="<?= htmlspecialchars($base_path) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap IT - Asset Management</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%236366f1'><path d='M19 9h2V7h-2V5c0-1.1-.9-2-2-2h-2V1h-2v2h-2V1H9v2H7c-1.1 0-2 .9-2 2v2H3v2h2v2H3v2h2v2H3v2h2v2c0 1.1.9 2 2 2h2v2h2v-2h2v2h2v-2h2c1.1 0 2-.9 2-2v-2h2v-2h-2v-2h2v-2h-2V9zm-2 8H7V5h10v12zm-8-9h6v6H9V8z'/></svg>">

    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        @import url("https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap");

        :root {
            /* Theme: Dark (Default) */
            --bg-body: #05070f;
            --text-main: #f8fafc;
            --text-soft: #94a3b8;
            --text-muted: #64748b;
            --sidebar-bg: rgba(10, 15, 30, 0.85);
            --sidebar-border: rgba(255, 255, 255, 0.06);
            --sidebar-hover: rgba(255, 255, 255, 0.05);
            --card-bg: rgba(10, 15, 30, 0.65);
            --card-border: rgba(255, 255, 255, 0.06);
            --card-border-hover: rgba(99, 102, 241, 0.4);
            --card-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            --card-shadow-hover: 0 20px 45px rgba(99, 102, 241, 0.12);
            --primary-color: #6366f1;
            --primary-hover: #4f46e5;
            --primary-light: rgba(99, 102, 241, 0.1);
            --secondary-color: #06b6d4;
            --secondary-hover: #0891b2;
            --table-tr-bg: rgba(10, 15, 30, 0.45);
            --table-tr-hover: rgba(99, 102, 241, 0.06);
            --grid-color: rgba(99, 102, 241, 0.02);
            --scanline-opacity: 0.12;
            --topbar-bg: rgba(7, 10, 22, 0.7);
            --topbar-border: rgba(255, 255, 255, 0.05);
            --input-bg: rgba(10, 15, 30, 0.5);
            --input-border: rgba(255, 255, 255, 0.06);
            --input-focus-border: #6366f1;
            --input-focus-shadow: rgba(99, 102, 241, 0.25);
            --text-dark: #f8fafc;
            --surface: rgba(10, 15, 30, 0.7);
            --surface-soft: rgba(255, 255, 255, 0.02);
            --line: rgba(255, 255, 255, 0.06);
            --dropdown-bg: rgba(10, 15, 30, 0.95);
            --select-option-bg: #0b0f19;
            --modal-bg: rgba(10, 15, 30, 0.95);
            --btn-text-color: #ffffff;
            --blob-opacity: 0.18;
            --sidebar-width: 260px;
        }

        body.light-theme {
            /* Theme: Light */
            --bg-body: #f1f5f9;
            --text-main: #0f172a;
            --text-soft: #475569;
            --text-muted: #94a3b8;
            --sidebar-bg: rgba(255, 255, 255, 0.85);
            --sidebar-border: rgba(99, 102, 241, 0.08);
            --sidebar-hover: rgba(99, 102, 241, 0.04);
            --card-bg: rgba(255, 255, 255, 0.75);
            --card-border: rgba(99, 102, 241, 0.1);
            --card-border-hover: rgba(99, 102, 241, 0.5);
            --card-shadow: 0 10px 25px rgba(99, 102, 241, 0.04);
            --card-shadow-hover: 0 15px 35px rgba(99, 102, 241, 0.12);
            --primary-color: #4f46e5;
            --primary-hover: #3730a3;
            --primary-light: rgba(79, 70, 229, 0.08);
            --secondary-color: #0891b2;
            --secondary-hover: #0369a1;
            --table-tr-bg: rgba(255, 255, 255, 0.65);
            --table-tr-hover: rgba(99, 102, 241, 0.04);
            --grid-color: rgba(99, 102, 241, 0.04);
            --scanline-opacity: 0.04;
            --topbar-bg: rgba(255, 255, 255, 0.75);
            --topbar-border: rgba(99, 102, 241, 0.08);
            --input-bg: rgba(255, 255, 255, 0.8);
            --input-border: rgba(99, 102, 241, 0.1);
            --input-focus-border: #4f46e5;
            --input-focus-shadow: rgba(79, 70, 229, 0.15);
            --text-dark: #0f172a;
            --surface: rgba(255, 255, 255, 0.85);
            --surface-soft: rgba(0, 0, 0, 0.02);
            --line: rgba(99, 102, 241, 0.1);
            --dropdown-bg: #ffffff;
            --select-option-bg: #ffffff;
            --modal-bg: rgba(255, 255, 255, 0.95);
            --btn-text-color: #0f172a;
            --blob-opacity: 0.06;
        }

        /* Smooth scrollbar styling */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.02);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--text-muted);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-soft);
        }

        body {
            font-family: "Plus Jakarta Sans", sans-serif;
            background: var(--bg-body);
            color: var(--text-main);
            letter-spacing: -0.01em;
            min-height: 100vh;
            overflow-x: hidden;
            line-height: 1.6;
            position: relative;
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* Tech grid lines overlay */
        body::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: 
                linear-gradient(var(--grid-color) 1px, transparent 1px),
                linear-gradient(90deg, var(--grid-color) 1px, transparent 1px);
            background-size: 45px 45px;
            background-position: center;
            z-index: -2;
            pointer-events: none;
        }

        /* Ambient scanline overlay */
        body::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(rgba(18, 24, 38, 0) 50%, rgba(0, 0, 0, 0.12) 50%), 
                        linear-gradient(90deg, rgba(18, 24, 38, 0) 50%, rgba(0, 0, 0, 0.12) 50%);
            background-size: 4px 4px;
            z-index: -1;
            pointer-events: none;
            opacity: var(--scanline-opacity);
        }

        /* Ambient background blobs for main content */
        .main-bg-blobs {
            position: fixed;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
            z-index: -3;
        }
        .main-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(140px);
            opacity: var(--blob-opacity);
            transition: opacity 0.3s ease;
        }
        .main-blob-1 {
            top: -10%;
            left: 20%;
            width: 400px;
            height: 400px;
            background: rgba(99, 102, 241, 0.25);
        }
        .main-blob-2 {
            bottom: -10%;
            right: 10%;
            width: 450px;
            height: 450px;
            background: rgba(6, 182, 212, 0.2);
        }

        /* Sidebar styling */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            z-index: 1030;
            padding: 24px 16px;
            overflow-y: auto;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-brand {
            margin-bottom: 28px;
            padding: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(168, 85, 247, 0.05) 100%);
            border: 1px solid var(--sidebar-border);
            border-radius: 18px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .sidebar-brand h4 {
            font-weight: 800;
            letter-spacing: -0.8px;
            margin: 0;
            font-size: 1.15rem;
            background: linear-gradient(135deg, #ffffff 0%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        body.light-theme .sidebar-brand h4 {
            background: linear-gradient(135deg, #0f172a 0%, #475569 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .sidebar-heading {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-soft);
            font-weight: 700;
            margin: 18px 0 8px 10px;
            opacity: 0.8;
        }

        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            color: var(--text-soft);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            border-radius: 12px;
            transition: all 0.2s ease;
        }

        .sidebar-link:hover {
            color: var(--text-dark);
            background: var(--sidebar-hover);
        }

        .sidebar-link.active {
            color: #ffffff !important;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%) !important;
            box-shadow: 0 4px 15px -3px rgba(99, 102, 241, 0.4);
        }
        
        .sidebar-link.active i {
            color: #ffffff !important;
        }

        .sidebar-link i {
            font-size: 1.1rem;
        }

        /* Top Header Bar */
        .top-header-bar {
            background: var(--topbar-bg);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-bottom: 1px solid var(--topbar-border);
            padding: 16px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-left: var(--sidebar-width);
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .top-bar-title {
            color: var(--text-dark) !important;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 40px;
            min-height: calc(100vh - 72px);
            position: relative;
            z-index: 10;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Cards & Glass overrides */
        .card {
            background: var(--card-bg) !important;
            border: 1px solid var(--card-border) !important;
            border-radius: 20px !important;
            box-shadow: var(--card-shadow) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            color: var(--text-main) !important;
            position: relative;
        }

        .card:hover {
            box-shadow: var(--card-shadow-hover) !important;
            border-color: var(--card-border-hover) !important;
        }

        .card-body, .card-header, .card-footer {
            background: transparent !important;
            border-color: var(--card-border) !important;
            color: inherit !important;
        }

        .card[style*="linear-gradient"],
        .lux-card[style*="linear-gradient"] {
            background: var(--card-bg) !important;
            color: var(--text-main) !important;
        }

        .card[style*="linear-gradient"] .text-white,
        .lux-card[style*="linear-gradient"] .text-white,
        .card[style*="linear-gradient"] .text-white *,
        .lux-card[style*="linear-gradient"] .text-white * {
            color: var(--text-main) !important;
        }

        .text-dark {
            color: var(--text-dark) !important;
        }

        .text-muted {
            color: var(--text-soft) !important;
        }

        /* Form elements */
        .form-control, .form-select {
            background-color: var(--input-bg) !important;
            border: 1px solid var(--input-border) !important;
            border-radius: 12px;
            padding: 10px 16px;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-main) !important;
            transition: all 0.2s ease;
        }
        .form-select option,
        select option {
            background-color: var(--select-option-bg) !important;
            color: var(--text-main) !important;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--input-focus-border) !important;
            box-shadow: 0 0 0 4px var(--input-focus-shadow) !important;
            background-color: var(--input-bg) !important;
        }

        .form-control::placeholder {
            color: var(--text-muted);
            opacity: 0.6;
        }

        /* Buttons styling */
        .btn {
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%) !important;
            border: none !important;
            color: #ffffff !important;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3) !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.45) !important;
        }

        .btn-secondary,
        .btn-outline-secondary {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid var(--card-border) !important;
            color: var(--text-main) !important;
        }

        body.light-theme .btn-secondary,
        body.light-theme .btn-outline-secondary {
            background: #e2e8f0 !important;
            border: 1px solid #cbd5e1 !important;
        }

        .btn-outline-danger {
            border-color: rgba(239, 68, 68, 0.3) !important;
            color: #ef4444 !important;
            background: rgba(239, 68, 68, 0.05) !important;
        }

        /* SaaS Table Layout */
        .table-responsive {
            border-radius: 16px;
            overflow: visible;
        }

        .table {
            border-collapse: separate;
            border-spacing: 0 10px;
            margin-top: -10px;
            width: 100%;
            background: transparent !important;
        }

        .table tbody tr {
            background-color: var(--table-tr-bg) !important;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .table tbody tr td {
            background-color: var(--table-tr-bg) !important;
            border-top: 1px solid var(--card-border) !important;
            border-bottom: 1px solid var(--card-border) !important;
            padding: 16px 20px !important;
            vertical-align: middle;
            color: var(--text-main) !important;
            font-size: 0.85rem;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .table tbody tr td:first-child {
            border-left: 1px solid var(--card-border) !important;
            border-top-left-radius: 14px;
            border-bottom-left-radius: 14px;
        }

        .table tbody tr td:last-child {
            border-right: 1px solid var(--card-border) !important;
            border-top-right-radius: 14px;
            border-bottom-right-radius: 14px;
        }

        .table tbody tr:hover td {
            background-color: var(--table-tr-hover) !important;
            border-top-color: var(--primary-color) !important;
            border-bottom-color: var(--primary-color) !important;
        }

        .table tbody tr:hover td:first-child {
            border-left-color: var(--primary-color) !important;
        }

        .table tbody tr:hover td:last-child {
            border-right-color: var(--primary-color) !important;
        }

        .table thead th {
            background: var(--table-tr-bg) !important;
            border-top: 1px solid var(--card-border) !important;
            border-bottom: 1px solid var(--card-border) !important;
            color: var(--text-soft) !important;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 14px 20px !important;
        }

        .table thead th:first-child {
            border-left: 1px solid var(--card-border) !important;
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
        }

        .table thead th:last-child {
            border-right: 1px solid var(--card-border) !important;
            border-top-right-radius: 12px;
            border-bottom-right-radius: 12px;
        }

        /* Modal styling */
        .modal-content {
            background: var(--modal-bg) !important;
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid var(--card-border) !important;
            border-radius: 24px;
            box-shadow: var(--card-shadow) !important;
            color: var(--text-main) !important;
        }

        .dropdown-menu {
            background: var(--dropdown-bg) !important;
            border: 1px solid var(--card-border) !important;
            border-radius: 16px !important;
            box-shadow: var(--card-shadow) !important;
        }

        .dropdown-item {
            color: var(--text-main) !important;
        }

        .dropdown-item:hover {
            background-color: var(--sidebar-hover) !important;
            color: var(--text-dark) !important;
        }

        /* Bell shake animation */
        @keyframes bell-shake {
            0%, 100% { transform: rotate(0); }
            10%, 30%, 50%, 70%, 90% { transform: rotate(12deg); }
            20%, 40%, 60%, 80% { transform: rotate(-12deg); }
        }
        .animate-bell {
            animation: bell-shake 2.5s cubic-bezier(.36,.07,.19,.97) both;
            animation-iteration-count: infinite;
            transform-origin: top center;
            display: inline-block;
        }

        .notif-scroll-container::-webkit-scrollbar {
            width: 5px;
        }
        .notif-scroll-container::-webkit-scrollbar-track {
            background: transparent;
        }
        .notif-scroll-container::-webkit-scrollbar-thumb {
            background: var(--text-muted);
            border-radius: 10px;
        }

        /* Page transition & typography helper animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.45s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        .fw-800 { font-weight: 800; }
        .fw-700 { font-weight: 700; }
        .fw-600 { font-weight: 600; }

        /* Sidebar Toggle styles for Desktop */
        body.sidebar-hidden .sidebar {
            transform: translateX(-260px);
        }
        body.sidebar-hidden .top-header-bar {
            margin-left: 0;
        }
        body.sidebar-hidden .main-content {
            margin-left: 0;
        }

        .sidebar-toggle-btn {
            color: var(--text-soft) !important;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .sidebar-toggle-btn:hover {
            color: var(--primary-color) !important;
            transform: scale(1.08);
        }

        /* Responsive Mobile Layout overrides */
        @media (max-width: 991.98px) {
            .sidebar-toggle-btn {
                color: var(--text-dark) !important;
            }
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .top-header-bar {
                margin-left: 0;
                padding: 12px 20px;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: var(--sidebar-bg);
                border-bottom: 1px solid var(--sidebar-border);
                color: var(--text-main);
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            }
            .top-header-bar .top-bar-title {
                color: var(--text-dark) !important;
            }
            .top-header-bar .text-muted {
                color: var(--text-soft) !important;
            }
            .main-content {
                margin-left: 0;
                padding: 20px 16px;
                padding-top: 88px;
            }
        }
        
        /* Mobile Screen Layout Optimization */
        @media (max-width: 767.98px) {
            /* Enable card scroll fallback for wide tables to prevent overflow */
            .card-body {
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
            }
            
            /* Compact table sizes on mobile */
            table {
                font-size: 0.82rem !important;
            }
            th, td {
                padding: 10px 12px !important;
                white-space: nowrap;
            }
            
            /* Responsive layout for flex dashboard headers */
            .main-content .d-flex.justify-content-between.align-items-center.mb-4 {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 12px !important;
            }
            .main-content .d-flex.justify-content-between.align-items-center.mb-4 > div {
                width: 100% !important;
            }
            .main-content .d-flex.justify-content-between.align-items-center.mb-4 > .d-flex {
                width: 100% !important;
                flex-direction: column !important;
                gap: 8px !important;
            }
            .main-content .d-flex.justify-content-between.align-items-center.mb-4 > .d-flex > a,
            .main-content .d-flex.justify-content-between.align-items-center.mb-4 > .d-flex > button {
                width: 100% !important;
                justify-content: center !important;
                display: flex !important;
                align-items: center !important;
            }

            /* Collapse card headers with flex actions */
            .card-header.d-flex, .card-header .d-flex {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 10px !important;
            }
            .card-header.d-flex > *, .card-header .d-flex > * {
                width: 100% !important;
            }

            /* Optimize sub-navigation tabs for mobile swiping */
            .nav-tabs {
                flex-wrap: nowrap !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
                border-bottom: 1px solid var(--line) !important;
            }
            .nav-tabs .nav-link {
                white-space: nowrap !important;
            }
            
            /* Optimize inputs and buttons for mobile tap targets */
            .form-control, .form-select, .btn {
                padding: 10px 14px !important;
                font-size: 0.88rem !important;
                border-radius: 12px !important;
            }
            
            /* Search filter form alignment on mobile */
            .row.g-3.align-items-end {
                flex-direction: column !important;
                align-items: stretch !important;
            }
            .row.g-3.align-items-end > [class*="col-"] {
                width: 100% !important;
            }

            /* Surgical mobile column stacking for form/grid layout fields */
            .row.g-3 > [class*="col-md-"], 
            .row.g-3 > [class*="col-lg-"], 
            .row.g-3 > [class*="col-xl-"] {
                width: 100% !important;
            }

            /* Make modal content scrollable to prevent bottom button cutoff */
            .modal-body {
                max-height: 70vh !important;
                overflow-y: auto !important;
            }
        }

        @media (max-width: 575.98px) {
            /* Maximize container space on extra small screens */
            .main-content {
                padding: 15px 10px !important;
                padding-top: 80px !important;
            }
            .card {
                border-radius: 12px !important;
            }
            .card-body {
                padding: 15px 12px !important;
            }
            .page-title {
                font-size: 1.25rem !important;
            }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .spin-animation {
            animation: spin 1s linear infinite;
            display: inline-block;
        }

        /* Dynamic Theme Overrides for hardcoded elements */
        body:not(.light-theme) .bg-white {
            background-color: var(--card-bg) !important;
            color: var(--text-main) !important;
        }
        body:not(.light-theme) .card-header.bg-white,
        body:not(.light-theme) .card-footer.bg-white {
            background-color: transparent !important;
        }
        body:not(.light-theme) .bg-light {
            background-color: rgba(255, 255, 255, 0.03) !important;
            color: var(--text-main) !important;
        }
        body:not(.light-theme) .text-dark {
            color: var(--text-dark) !important;
        }
        body:not(.light-theme) .text-black {
            color: var(--text-main) !important;
        }
        body:not(.light-theme) .border,
        body:not(.light-theme) .border-top,
        body:not(.light-theme) .border-bottom,
        body:not(.light-theme) .border-start,
        body:not(.light-theme) .border-end {
            border-color: var(--card-border) !important;
        }
        body:not(.light-theme) input.bg-white,
        body:not(.light-theme) select.bg-white,
        body:not(.light-theme) textarea.bg-white {
            background-color: var(--input-bg) !important;
            border-color: var(--input-border) !important;
            color: var(--text-main) !important;
        }
        body:not(.light-theme) *[style*="background-color: #fff"],
        body:not(.light-theme) *[style*="background-color: #ffffff"],
        body:not(.light-theme) *[style*="background: #fff"],
        body:not(.light-theme) *[style*="background: #ffffff"],
        body:not(.light-theme) *[style*="background: white"],
        body:not(.light-theme) *[style*="background-color: white"] {
            background-color: var(--card-bg) !important;
            background: var(--card-bg) !important;
            color: var(--text-main) !important;
        }
        body:not(.light-theme) .alert-success {
            background-color: rgba(34, 197, 94, 0.1) !important;
            border-color: rgba(34, 197, 94, 0.25) !important;
            color: #4ade80 !important;
        }
        body:not(.light-theme) .alert-danger {
            background-color: rgba(239, 68, 68, 0.1) !important;
            border-color: rgba(239, 68, 68, 0.25) !important;
            color: #fca5a5 !important;
        }
        body:not(.light-theme) .alert-warning {
            background-color: rgba(245, 158, 11, 0.1) !important;
            border-color: rgba(245, 158, 11, 0.25) !important;
            color: #fde047 !important;
        }
        body:not(.light-theme) .alert-info {
            background-color: rgba(59, 130, 246, 0.1) !important;
            border-color: rgba(59, 130, 246, 0.25) !important;
            color: #93c5fd !important;
        }
        body:not(.light-theme) .table-light,
        body:not(.light-theme) .table-light th,
        body:not(.light-theme) .table-light td {
            background-color: var(--sidebar-hover) !important;
            color: var(--text-main) !important;
            border-color: var(--card-border) !important;
        }
    </style>
</head>
<body>
<script>
    // Inline script to prevent theme flashing
    const savedTheme = localStorage.getItem('theme-pref') || 'dark';
    if (savedTheme === 'light') {
        document.body.classList.add('light-theme');
    }
</script>

<!-- Ambient Background Blobs -->
<div class="main-bg-blobs">
    <div class="main-blob main-blob-1"></div>
    <div class="main-blob main-blob-2"></div>
</div>

<!-- Left Sidebar Navigation -->
<div class="sidebar" id="sidebarContainer">
    <a class="sidebar-brand" href="index.php?page=dashboard">
        <i class="fas fa-microchip text-primary fs-4"></i>
        <h4>REKAP IT</h4>
    </a>

    <div class="sidebar-heading">MONITORING</div>
    <ul class="sidebar-nav">
        <li>
            <a href="index.php?page=dashboard" class="sidebar-link <?= ($page == 'dashboard') ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>
        </li>
        <?php if (hasRole('admin')): ?>
        <li>
            <a href="index.php?page=logs" class="sidebar-link <?= ($page == 'logs') ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i> Log Aktivitas
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-heading">OPERASIONAL</div>
    <ul class="sidebar-nav">
        <li>
            <a href="index.php?page=maintenance&sub=history" class="sidebar-link <?= ($page == 'maintenance') ? 'active' : '' ?>">
                <i class="bi bi-calendar-check"></i> Maintenance Aset
            </a>
        </li>
        <li>
            <a href="index.php?page=perbaikan" class="sidebar-link <?= ($page == 'perbaikan') ? 'active' : '' ?>">
                <i class="bi bi-tools"></i> Tiket Perbaikan
            </a>
        </li>
        <li>
            <a href="index.php?page=sparepart" class="sidebar-link <?= ($page == 'sparepart') ? 'active' : '' ?>">
                <i class="bi bi-cpu-fill"></i> Suku Cadang (Sparepart)
            </a>
        </li>
        <?php if (hasRole('admin')): ?>
        <li>
            <a href="index.php?page=audit" class="sidebar-link <?= ($page == 'audit') ? 'active' : '' ?>">
                <i class="bi bi-shield-check"></i> Audit Fisik
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-heading">MANAJEMEN ASET</div>
    <ul class="sidebar-nav">
        <li>
            <a href="index.php?page=inventaris" class="sidebar-link <?= ($page == 'inventaris') ? 'active' : '' ?>">
                <i class="bi bi-laptop"></i> Data Aset
            </a>
        </li>
        <li>
            <a href="index.php?page=kategori" class="sidebar-link <?= ($page == 'kategori') ? 'active' : '' ?>">
                <i class="bi bi-tags"></i> Kategori Aset
            </a>
        </li>
        <li>
            <a href="index.php?page=mutasi" class="sidebar-link <?= ($page == 'mutasi') ? 'active' : '' ?>">
                <i class="bi bi-arrow-left-right"></i> Mutasi Aset
            </a>
        </li>
    </ul>

    <?php if (hasRole('admin')): ?>
    <div class="sidebar-heading">MASTER DATA</div>
    <ul class="sidebar-nav">
        <li>
            <a href="index.php?page=cabang" class="sidebar-link <?= ($page == 'cabang') ? 'active' : '' ?>">
                <i class="bi bi-building"></i> Cabang
            </a>
        </li>
        <li>
            <a href="index.php?page=divisi" class="sidebar-link <?= ($page == 'divisi') ? 'active' : '' ?>">
                <i class="bi bi-people"></i> Divisi
            </a>
        </li>
        <li>
            <a href="index.php?page=karyawan" class="sidebar-link <?= ($page == 'karyawan') ? 'active' : '' ?>">
                <i class="bi bi-person-badge"></i> Karyawan
            </a>
        </li>
        <li>
            <a href="index.php?page=pengguna" class="sidebar-link <?= ($page == 'pengguna') ? 'active' : '' ?>">
                <i class="bi bi-person-gear"></i> Pengguna
            </a>
        </li>
    </ul>

    <div class="sidebar-heading">LAPORAN</div>
    <ul class="sidebar-nav">
        <li>
            <a href="index.php?page=laporan_maintenance" class="sidebar-link <?= ($page == 'laporan_maintenance') ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph"></i> Report Bulanan
            </a>
        </li>
        <li>
            <a href="index.php?page=laporan" class="sidebar-link <?= ($page == 'laporan') ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-excel"></i> Export Excel
            </a>
        </li>
    </ul>

    <div class="sidebar-heading">INTEGRASI / API</div>
    <ul class="sidebar-nav">
        <li>
            <a href="api/test_telegram.php" target="_blank" class="sidebar-link">
                <i class="bi bi-telegram text-info"></i> Telegram Diagnostics
            </a>
        </li>
        <li>
            <a href="api/telegram_add_asset.php" target="_blank" class="sidebar-link">
                <i class="bi bi-phone text-success"></i> WebApp Telegram Form
            </a>
        </li>
        <li>
            <a href="test_db.php" target="_blank" class="sidebar-link">
                <i class="bi bi-database-check text-warning"></i> Test Database Sync
            </a>
        </li>
    </ul>
    <?php endif; ?>

    <!-- Panduan & Instruksi Penggunaan Sistem -->
    <div class="mt-4 p-3 rounded-4" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.06);">
        <h6 class="fw-bold text-white mb-2" style="font-size: 0.78rem;">
            <i class="bi bi-journal-bookmark text-primary me-2"></i>Panduan Sistem
        </h6>
        <p class="text-muted mb-0" style="font-size: 0.72rem; line-height: 1.4;">
            Ikuti langkah-langkah berikut untuk mengoptimalkan pengelolaan aset IT Anda:
        </p>
        <ul class="text-muted p-0 ps-3 mt-2 mb-0" style="font-size: 0.7rem; line-height: 1.45;">
            <li class="mb-1">Lakukan audit kondisi aset secara berkala.</li>
            <li class="mb-1">Catat maintenance massal pada jadwal rutin.</li>
            <li class="mb-1">Buat tiket perbaikan jika terdeteksi kerusakan.</li>
            <li>Ekspor laporan bulanan untuk arsip & audit.</li>
        </ul>
    </div>
</div>

<!-- Floating Top Header Bar -->
<div class="top-header-bar">
    <div class="d-flex align-items-center gap-3">
        <!-- Sidebar Toggle -->
        <button class="btn btn-sm btn-link p-0 border-0 sidebar-toggle-btn" id="sidebarToggleBtn">
            <i class="bi bi-justify fs-3"></i>
        </button>
        <div>
            <h5 class="m-0 fw-bold top-bar-title text-dark"><?= ucwords(str_replace('_', ' ', $page)) ?></h5>
            <small class="text-muted d-none d-sm-block">Sistem Manajemen Aset IT</small>
        </div>
    </div>

    <div class="d-flex align-items-center gap-3">
        <!-- Manual Sync Button -->
        <button id="manual-sync-btn" class="btn btn-outline-primary btn-sm px-3 py-1.5 d-flex align-items-center gap-2" style="font-size: 0.75rem; border-radius: 10px; height: 36px; border: 1px solid var(--sidebar-border); background: var(--input-bg); color: var(--text-main);" onclick="triggerManualSync()">
            <i class="bi bi-arrow-repeat" id="sync-icon"></i>
            <span id="sync-text">Sync</span>
        </button>

        <!-- Theme Toggle Button -->
        <button id="theme-toggle-btn" class="btn btn-outline-secondary btn-sm p-0 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--sidebar-border); background: var(--input-bg); color: var(--text-soft);" title="Ubah Tema">
            <i class="bi bi-moon-stars" id="theme-toggle-icon" style="font-size: 0.95rem;"></i>
        </button>

        <!-- Clock (Hidden on Mobile) -->
        <div class="text-end d-none d-md-block me-2">
            <div class="small fw-bold text-dark" id="realtime-clock">Loading time...</div>
            <div class="text-muted" style="font-size: 0.7rem;">Status: <span class="text-success fw-bold">Online</span></div>
        </div>

        <script>
        function triggerManualSync() {
            const btn = document.getElementById('manual-sync-btn');
            const icon = document.getElementById('sync-icon');
            const text = document.getElementById('sync-text');
            
            if (btn.disabled) return;
            
            btn.disabled = true;
            icon.classList.add('spin-animation');
            text.innerText = 'Syncing...';
            
            // Redirect to the same page with sync_now=1 to run sync in the active container
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('sync_now', '1');
            window.location.href = currentUrl.toString();
        }
        </script>

        <!-- Notifications -->
        <div class="dropdown">
            <button class="nav-link position-relative p-2 border-0 bg-transparent text-muted opacity-75 hover-opacity-100 transition-all" data-bs-toggle="dropdown" aria-expanded="false" id="notifDropdown">
                <i class="bi bi-bell fs-5 <?= ($notifCount > 0) ? 'animate-bell' : '' ?>"></i>
                <?php if ($notifCount > 0): ?>
                    <span class="position-absolute top-1 start-75 translate-middle p-1 bg-danger border border-2 border-light rounded-circle">
                        <span class="visually-hidden">New alerts</span>
                    </span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 p-0 overflow-hidden" style="width: 320px; border-radius: 16px; z-index: 1050; background: var(--dropdown-bg);">
                <li class="px-4 py-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="m-0 fw-bold text-dark"><i class="bi bi-bell-fill text-primary me-2"></i>Notifikasi</h6>
                        <small class="text-muted"><?= $notifCount ?> jadwal perlu tindakan</small>
                    </div>
                </li>
                <div class="notif-scroll-container" style="max-height: 280px; overflow-y: auto;">
                    <?php if ($notifCount === 0): ?>
                        <li class="px-4 py-5 text-center text-muted">
                            <div class="bg-success bg-opacity-10 text-success rounded-circle d-inline-flex p-3 mb-3">
                                <i class="bi bi-shield-check fs-3"></i>
                            </div>
                            <p class="small fw-semibold mb-0">Semua Terkendali!</p>
                            <small class="text-muted d-block mt-1">Tidak ada jadwal maintenance mendesak.</small>
                        </li>
                    <?php else: ?>
                        <?php foreach ($notifications as $n): 
                            $today = new DateTime(date('Y-m-d'));
                            $target = new DateTime(date('Y-m-d', strtotime($n['tanggal'])));
                            $diff = $today->diff($target)->days;
                            $is_past = $target < $today;
                            
                            if ($is_past) {
                                $timeText = "Terlewat";
                                $timeBadge = "bg-danger";
                            } elseif ($diff === 0) {
                                $timeText = "Hari ini";
                                $timeBadge = "bg-danger";
                            } elseif ($diff === 1) {
                                $timeText = "Besok";
                                $timeBadge = "bg-warning text-dark";
                            } else {
                                $timeText = "H-" . $diff;
                                $timeBadge = "bg-primary bg-opacity-10 text-primary";
                            }
                        ?>
                            <li class="border-bottom">
                                <a class="dropdown-item px-4 py-3 text-dark transition-all d-flex align-items-start gap-3" href="index.php?page=maintenance" style="white-space: normal;">
                                    <div class="bg-primary bg-opacity-10 p-2 rounded-3 text-primary d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; flex-shrink: 0;">
                                        <i class="bi bi-pc-display"></i>
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="fw-bold small text-dark mb-0 text-truncate"><?= $n['kode_aset'] ?></div>
                                        <div class="text-muted small text-truncate mb-2"><?= $n['nama_aset'] ?></div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted small" style="font-size: 0.75rem;"><i class="bi bi-calendar-event me-1"></i><?= date('d M Y', strtotime($n['tanggal'])) ?></span>
                                            <span class="badge <?= $timeBadge ?> rounded-pill px-2 py-0.5" style="font-size: 0.7rem; font-weight: 700;"><?= $timeText ?></span>
                                        </div>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <li class="px-4 py-2 bg-light border-top text-center">
                    <a href="index.php?page=maintenance" class="text-primary text-decoration-none small fw-bold d-block py-1 hover-underline">
                        Lihat Semua Jadwal <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </li>
            </ul>
        </div>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
            <button class="btn btn-link p-0 border-0 bg-transparent dropdown-toggle d-flex align-items-center gap-2 text-decoration-none" data-bs-toggle="dropdown">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nama']) ?>&background=4361ee&color=fff" class="rounded-circle border" width="34">
                <span class="small fw-bold text-dark d-none d-lg-inline"><?= $_SESSION['nama'] ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 rounded-3" style="min-width: 180px;">
                <li class="px-3 py-2 border-bottom mb-2 bg-light">
                    <div class="small text-muted">Signed in as</div>
                    <div class="fw-bold text-dark"><?= $_SESSION['username'] ?></div>
                </li>
                <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Main Content Area -->
<div class="main-content">
    <div class="content-body">
        <div class="animate-fade-in">
