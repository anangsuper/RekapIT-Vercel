<?php
ob_start();
session_start();
require_once __DIR__ . '/config/database.php';

// Jika URL menyatakan timeout atau logout, bersihkan session & cookie
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['reason']) || isset($_GET['logged_out']))) {
    $_SESSION = [];
    if (isset($_COOKIE['REKAPIT_SESSION'])) {
        unset($_COOKIE['REKAPIT_SESSION']);
    }
    $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if (!headers_sent()) {
        setcookie('REKAPIT_SESSION', '', time() - 3600, '/', '', $isSecure, true);
    }
}

// Jika sudah login, redirect ke helpdesk
if (isset($_SESSION['user_id'])) {
    header('Location: index.php?page=helpdesk');
    exit();
}

$error = '';
if (isset($_GET['reason']) && $_GET['reason'] === 'timeout') {
    $error = 'Sesi Helpdesk Anda telah berakhir karena tidak ada aktivitas.';
}

if (isset($_POST['login_helpdesk'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama']     = $user['nama'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['id_cabang'] = $user['id_cabang'];
            $_SESSION['last_activity'] = time();

            // Log Login if ActivityLog exists
            if (file_exists(__DIR__ . '/models/ActivityLog.php')) {
                require_once __DIR__ . '/models/ActivityLog.php';
                $logModel = new ActivityLog($conn);
                require_once __DIR__ . '/helpers/notification.php';
                $device = getDeviceDetails();
                $ip = getClientIP();
                $logModel->add($user['id'], 'LOGIN_HELPDESK', "User login ke Helpdesk dari {$device} (IP: {$ip}).");
            }

            if (function_exists('save_session_to_cookie')) {
                save_session_to_cookie();
            }

            header('Location: index.php?page=helpdesk');
            exit();
        } else {
            $error = 'Username atau password yang Anda masukkan salah!';
        }
    } else {
        $error = 'Username dan password wajib diisi!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Portal Helpdesk - Rekap IT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-main: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --card-border: rgba(255, 255, 255, 0.1);
            --text-main: #f8fafc;
            --text-soft: #94a3b8;
            --accent-color: #6366f1;
            --accent-hover: #4f46e5;
        }

        body.light-theme {
            --bg-main: #f1f5f9;
            --card-bg: rgba(255, 255, 255, 0.9);
            --card-border: rgba(0, 0, 0, 0.08);
            --text-main: #0f172a;
            --text-soft: #64748b;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        .bg-blobs {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
            pointer-events: none;
        }

        .blob {
            position: absolute;
            filter: blur(80px);
            opacity: 0.35;
            border-radius: 50%;
        }

        .blob-1 {
            top: -10%;
            left: -10%;
            width: 450px;
            height: 450px;
            background: #6366f1;
        }

        .blob-2 {
            bottom: -10%;
            right: -10%;
            width: 450px;
            height: 450px;
            background: #06b6d4;
        }

        .login-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            width: 100%;
            max-width: 440px;
            padding: 40px 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .brand-icon {
            width: 64px;
            height: 64px;
            background: rgba(99, 102, 241, 0.15);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: var(--accent-color);
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--card-border);
            color: var(--text-main);
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.95rem;
        }

        body.light-theme .form-control {
            background: #fff;
            border-color: #cbd5e1;
            color: #0f172a;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--accent-color);
            color: var(--text-main);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--accent-color) 0%, var(--accent-hover) 100%);
            border: none;
            color: #fff;
            border-radius: 12px;
            padding: 13px;
            font-weight: 700;
            font-size: 1rem;
            width: 100%;
            transition: all 0.2s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(99, 102, 241, 0.4);
            color: #fff;
        }

        .demo-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 14px 18px;
            font-size: 0.82rem;
        }

        .theme-toggle {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid var(--card-border);
            color: var(--text-main);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="bg-blobs">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <button class="theme-toggle" id="themeBtn" onclick="toggleTheme()" title="Ubah Tema">
        <i class="bi bi-moon-stars" id="themeIcon"></i>
    </button>

    <div class="login-card">
        <div class="brand-icon">
            <i class="bi bi-headset fs-2"></i>
        </div>

        <div class="text-center mb-4">
            <h4 class="fw-800 m-0">Portal Helpdesk IT</h4>
            <p class="text-soft small mt-1 mb-0">Login akun karyawan untuk menyampaikan keluhan & mengecek tiket perbaikan.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 rounded-3 p-3 mb-4 text-start small d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['logged_out'])): ?>
            <div class="alert alert-success border-0 rounded-3 p-3 mb-4 text-start small d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <div>Anda telah berhasil keluar dari akun Helpdesk.</div>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold text-soft">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0 text-soft" style="border-color: var(--card-border);"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" class="form-control border-start-0 ps-0" placeholder="Masukkan username..." required>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-bold text-soft">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0 text-soft" style="border-color: var(--card-border);"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control border-start-0 ps-0" placeholder="Masukkan password..." required>
                </div>
            </div>

            <button type="submit" name="login_helpdesk" class="btn btn-submit mb-4">
                <i class="bi bi-box-arrow-in-right me-2"></i> Login ke Helpdesk
            </button>
        </form>

        <div class="demo-box mb-4">
            <strong class="text-primary d-block mb-1"><i class="bi bi-info-circle me-1"></i> Akun Login Demo Karyawan:</strong>
            <div class="text-soft">• Username: <code class="fw-bold">karyawan</code></div>
            <div class="text-soft">• Password: <code class="fw-bold">password</code></div>
        </div>

        <div class="text-center">
            <a href="login.php" class="text-soft small text-decoration-none hover-underline">
                <i class="bi bi-shield-lock me-1"></i> Login Admin / Teknisi IT
            </a>
        </div>
    </div>

    <script>
        const savedTheme = localStorage.getItem('theme-pref') || 'dark';
        if (savedTheme === 'light') {
            document.body.classList.add('light-theme');
            document.getElementById('themeIcon').className = 'bi bi-sun-fill';
        }

        function toggleTheme() {
            document.body.classList.toggle('light-theme');
            const isLight = document.body.classList.contains('light-theme');
            localStorage.setItem('theme-pref', isLight ? 'light' : 'dark');
            document.getElementById('themeIcon').className = isLight ? 'bi bi-sun-fill' : 'bi bi-moon-stars';
        }
    </script>
</body>
</html>
