<?php
ob_start();
session_start();
require_once __DIR__ . '/config/database.php';

// Jika URL menyatakan timeout atau logout, bersihkan session & cookie agar tidak terjadi redirect loop (hanya untuk request GET)
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

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
if (isset($_GET['reason']) && $_GET['reason'] === 'timeout') {
    $error = 'Sesi Anda telah berakhir karena tidak ada aktivitas.';
}

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama']     = $user['nama'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['id_cabang'] = $user['id_cabang']; // Simpan id_cabang
            $_SESSION['last_activity'] = time(); // Reset inactivity timer on successful login

            // Log Login
            require_once __DIR__ . '/models/ActivityLog.php';
            $logModel = new ActivityLog($conn);
            $logModel->add($user['id'], 'LOGIN', 'User berhasil login ke sistem.');

            if (function_exists('save_session_to_cookie')) {
                save_session_to_cookie();
            }
            header('Location: index.php');
            exit();
        } else {
            $error = 'Username atau password salah!';
        }
    } else {
        $error = 'Harap isi semua field!';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Rekap IT</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%236366f1'><path d='M19 9h2V7h-2V5c0-1.1-.9-2-2-2h-2V1h-2v2h-2V1H9v2H7c-1.1 0-2 .9-2 2v2H3v2h2v2H3v2h2v2H3v2h2v2c0 1.1.9 2 2 2h2v2h2v-2h2v2h2v-2h2c1.1 0 2-.9 2-2v-2h2v-2h-2v-2h2v-2h-2V9zm-2 8H7V5h10v12zm-8-9h6v6H9V8z'/></svg>">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Anton&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #6366f1; /* Indigo */
            --primary-hover: #4f46e5;
            --accent-color: #a855f7;  /* Purple */
            --secondary-color: #06b6d4; /* Cyan */
            --bg-body: #05070f;
            --text-main: #f8fafc;
            --text-muted: #64748b;
            --glass-bg: rgba(7, 10, 22, 0.75);
            --glass-border: rgba(255, 255, 255, 0.05);
            --neon-glow: rgba(99, 102, 241, 0.15);
        }

        body {
            background: var(--bg-body);
            background-image: 
                radial-gradient(circle at 15% 20%, rgba(99, 102, 241, 0.1) 0%, transparent 45%),
                radial-gradient(circle at 85% 80%, rgba(6, 182, 212, 0.1) 0%, transparent 45%),
                radial-gradient(circle at 50% 50%, rgba(168, 85, 247, 0.05) 0%, transparent 50%);
            color: var(--text-main);
            letter-spacing: -0.01em;
            font-family: "Plus Jakarta Sans", sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        /* Tech grid lines overlay */
        body::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: 
                linear-gradient(rgba(99, 102, 241, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(99, 102, 241, 0.02) 1px, transparent 1px);
            background-size: 45px 45px;
            background-position: center;
            z-index: 1;
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
            z-index: 2;
            pointer-events: none;
            opacity: 0.35;
        }

        /* Ambient Blobs */
        .bg-blobs {
            position: absolute;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
            z-index: 3;
            display: block !important;
        }
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(130px);
            opacity: 0.22;
        }
        .blob-1 {
            top: 15%;
            left: 10%;
            width: 350px;
            height: 350px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.3) 0%, rgba(99, 102, 241, 0) 70%);
            animation: float-1 16s infinite ease-in-out;
        }
        .blob-2 {
            bottom: 15%;
            right: 10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(168, 85, 247, 0.3) 0%, rgba(168, 85, 247, 0) 70%);
            animation: float-2 20s infinite ease-in-out;
            animation-delay: -4s;
        }
        .blob-3 {
            top: 50%;
            left: 45%;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(6, 182, 212, 0.2) 0%, rgba(6, 182, 212, 0) 70%);
            animation: float-3 18s infinite ease-in-out;
            animation-delay: -8s;
        }
        @keyframes float-1 {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.95); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        @keyframes float-2 {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(-40px, 40px) scale(0.9); }
            66% { transform: translate(40px, -30px) scale(1.15); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        @keyframes float-3 {
            0% { transform: translate(0px, 0px) scale(1); }
            50% { transform: translate(50px, 50px) scale(1.1); }
            100% { transform: translate(0px, 0px) scale(1); }
        }

        /* Glassmorphism Card with Border Gradient */
        .login-card {
            background: linear-gradient(var(--glass-bg), var(--glass-bg)) padding-box,
                        linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(168, 85, 247, 0.05) 50%, rgba(6, 182, 212, 0.2) 100%) border-box;
            border: 1px solid transparent;
            border-radius: 28px;
            padding: 44px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.8),
                        0 0 50px -10px rgba(99, 102, 241, 0.1);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            z-index: 10;
            position: relative;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .login-card:hover {
            background: linear-gradient(var(--glass-bg), var(--glass-bg)) padding-box,
                        linear-gradient(135deg, rgba(99, 102, 241, 0.5) 0%, rgba(168, 85, 247, 0.15) 50%, rgba(6, 182, 212, 0.5) 100%) border-box;
            box-shadow: 0 40px 80px -15px rgba(0, 0, 0, 0.9),
                        0 0 60px -10px rgba(99, 102, 241, 0.2);
        }

        /* Corner Brackets Accents */
        .corner-bracket {
            position: absolute;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(6, 182, 212, 0.4);
            pointer-events: none;
            transition: all 0.3s ease;
        }
        .cb-tl { top: 12px; left: 12px; border-right: none; border-bottom: none; }
        .cb-tr { top: 12px; right: 12px; border-left: none; border-bottom: none; }
        .cb-bl { bottom: 12px; left: 12px; border-right: none; border-top: none; }
        .cb-br { bottom: 12px; right: 12px; border-left: none; border-top: none; }

        .login-card:hover .corner-bracket {
            border-color: rgba(99, 102, 241, 0.8);
            width: 20px;
            height: 20px;
        }

        .brand-wrapper {
            margin-bottom: 32px;
            text-align: center;
        }

        .brand-icon {
            width: 64px;
            height: 64px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 18px;
            position: relative;
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
            animation: pulse-ring 4s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse-ring {
            0%, 100% {
                box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4), 0 0 0 0px rgba(99, 102, 241, 0.3);
            }
            50% {
                box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4), 0 0 0 10px rgba(99, 102, 241, 0);
            }
        }

        .brand-icon i {
            color: #ffffff;
            font-size: 1.7rem;
            z-index: 5;
        }

        .brand-name {
            font-weight: 800;
            font-size: 2.4rem;
            background: linear-gradient(135deg, #ffffff 0%, #cbd5e1 50%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 6px;
            letter-spacing: -1.5px;
            cursor: default;
            transition: all 0.3s ease;
        }

        .brand-tagline {
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .form-label {
            color: #cbd5e1;
            font-weight: 600;
            font-size: 0.8rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .input-group-custom {
            background: rgba(10, 15, 30, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px;
            display: flex;
            align-items: center;
            position: relative;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
        }

        .input-group-custom:focus-within {
            background: rgba(10, 15, 30, 0.7);
            border-color: rgba(6, 182, 212, 0.5);
            box-shadow: 0 0 20px rgba(6, 182, 212, 0.15);
        }

        /* Glowing indicator bottom-bar */
        .input-group-custom::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            transition: width 0.3s ease;
            transform: translateX(-50%);
        }

        .input-group-custom:focus-within::after {
            width: 100%;
        }

        .input-group-icon {
            padding: 0 12px 0 18px;
            color: #64748b;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            transition: color 0.3s ease;
        }

        .input-group-custom:focus-within .input-group-icon {
            color: var(--secondary-color);
        }

        .input-control-custom {
            color: #ffffff !important;
            background: transparent !important;
            border: none !important;
            padding: 14px 18px 14px 0px;
            font-size: 0.95rem;
            flex: 1;
            outline: none;
            z-index: 5;
        }

        .input-control-custom::placeholder {
            color: rgba(148, 163, 184, 0.35);
        }

        .input-control-custom:-webkit-autofill,
        .input-control-custom:-webkit-autofill:hover,
        .input-control-custom:-webkit-autofill:focus {
            -webkit-text-fill-color: #ffffff !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        .password-toggle {
            padding: 0 18px;
            background: transparent;
            border: none;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: color 0.2s ease;
            z-index: 5;
        }

        .password-toggle:hover {
            color: #ffffff;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%) !important;
            border-radius: 16px;
            color: white !important;
            padding: 15px;
            font-weight: 700;
            font-size: 0.95rem;
            border: none !important;
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.25) !important;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.45) !important;
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert-custom {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            border-radius: 16px;
            padding: 14px 18px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        /* Monospace Retro Terminal Credentials Card */
        .hint-box {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(190, 242, 100, 0.15);
            border-radius: 16px;
            padding: 18px;
            margin-top: 32px;
            text-align: left;
            position: relative;
            overflow: hidden;
            font-family: 'Fira Code', 'Courier New', monospace;
            box-shadow: inset 0 0 15px rgba(0, 0, 0, 0.6);
        }

        .hint-box::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(190, 242, 100, 0.3), transparent);
            animation: scan-line 4s linear infinite;
        }

        @keyframes scan-line {
            0% { transform: translateY(-10px); }
            100% { transform: translateY(120px); }
        }

        .hint-title {
            font-size: 0.72rem;
            font-weight: 700;
            color: #bef264;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .hint-title::before {
            content: "";
            display: inline-block;
            width: 6px;
            height: 6px;
            background-color: #bef264;
            border-radius: 50%;
            animation: blink 1s infinite alternate;
        }

        @keyframes blink {
            0% { opacity: 0.2; }
            100% { opacity: 1; }
        }

        .hint-text {
            font-size: 0.76rem;
            color: #a3e635;
            margin: 0;
            line-height: 1.4;
        }

        .hint-text code {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            padding: 1px 4px;
            font-family: inherit;
        }

        /* 3D Model Canvas Container */
        #canvas-container {
            background: transparent;
            border: none;
            border-radius: 0;
            overflow: visible;
            width: 100%;
            height: 100%;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #gltf-loader {
            pointer-events: none;
            z-index: 20;
            transition: opacity 0.5s ease;
        }
        
        #gltf-loader .text-muted {
            color: #94a3b8 !important;
        }
    </style>
</head>
<body>

<!-- Background Blobs -->
<div class="bg-blobs">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>
</div>

<div class="d-flex align-items-center justify-content-center w-100 flex-wrap gap-5 py-5 px-3" style="min-height: 100vh; position: relative; z-index: 10;">
    
    <!-- 3D Model Area -->
    <div class="d-none d-lg-block" style="width: 480px; height: 480px;">
        <div id="canvas-container"> 
            <!-- Loader -->
            <div id="gltf-loader" class="position-absolute top-50 start-50 translate-middle text-center d-flex flex-column align-items-center justify-content-center">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Memuat model...</span>
                </div>
                <div class="text-muted fw-semibold" style="font-size: 0.8rem; letter-spacing: 0.05em; text-transform: uppercase;">MEMUAT MODEL 3D...</div>
            </div>
            <!-- Canvas will be loaded here dynamically -->
        </div>
    </div>

    <div class="login-card">
        <!-- Sci-Fi Corner Brackets -->
        <div class="corner-bracket cb-tl"></div>
        <div class="corner-bracket cb-tr"></div>
        <div class="corner-bracket cb-bl"></div>
        <div class="corner-bracket cb-br"></div>

        <div class="brand-wrapper">
            <div class="brand-icon">
                <i class="fa-solid fa-laptop-code"></i>
            </div>
            <h1 class="brand-name">Rekap IT</h1>
            <p class="brand-tagline">Sistem Manajemen Aset & Maintenance</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-custom">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" autocomplete="off">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <div class="input-group-custom">
                    <span class="input-group-icon">
                        <i class="bi bi-person"></i>
                    </span>
                    <input type="text" name="username" class="input-control-custom" id="username" placeholder="Masukkan username" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="input-group-custom">
                    <span class="input-group-icon">
                        <i class="bi bi-shield-lock"></i>
                    </span>
                    <input type="password" name="password" class="input-control-custom" id="password" placeholder="Masukkan password" required>
                    <button type="button" class="password-toggle" id="togglePassword">
                        <i class="bi bi-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" name="login" class="btn btn-login w-100">
                Masuk ke Sistem <i class="bi bi-arrow-right-short ms-1" style="font-size: 1.15rem; vertical-align: middle;"></i>
            </button>
        </form>

        <div class="hint-box">
            <div class="hint-title">Lupa Password?</div>
            <p class="hint-text">
                Silakan hubungi <strong style="color: #ffffff; text-shadow: 0 0 10px rgba(255,255,255,0.3);">Administrator IT</strong> untuk melakukan reset kata sandi Anda melalui database Google Sheets.
            </p>
        </div>
    </div>
</div>

<script>
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');
    const toggleIcon = document.querySelector('#toggleIcon');

    togglePassword.addEventListener('click', function (e) {
        // toggle the type attribute
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        // toggle the eye / eye slash icon
        toggleIcon.classList.toggle('bi-eye');
        toggleIcon.classList.toggle('bi-eye-slash');
    });

    /* Mouse Follow Shadow Effect */
    const brandName = document.querySelector('.brand-name');
    const shadowOffset = 25;

    function createShadow(e) {
        const { offsetWidth: width, offsetHeight: height } = brandName;
        let { offsetX: x, offsetY: y } = e;

        if (this !== e.target) {
            x = x + e.target.offsetLeft;
            y = y + e.target.offsetTop;
        }

        const xWalk = Math.round((x / width * shadowOffset) - (shadowOffset / 2));
        const yWalk = Math.round((y / height * shadowOffset) - (shadowOffset / 2));

        brandName.style.textShadow = `
            ${xWalk}px ${yWalk}px 12px rgba(99, 102, 241, 0.45)
        `;
    }

    brandName.addEventListener('mousemove', createShadow);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Three.js Library & GLTF Loader -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const container = document.getElementById('canvas-container');
        if (!container) return;
        if (window.getComputedStyle(container).display === 'none') return;

        // Initialize Three.js scene, camera, renderer
        const scene = new THREE.Scene();

        // Create camera
        const camera = new THREE.PerspectiveCamera(45, container.clientWidth / container.clientHeight, 0.1, 100);
        camera.position.set(0, 0, 8);

        // Create renderer with alpha (transparent background) and antialiasing
        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setSize(container.clientWidth, container.clientHeight);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.shadowMap.enabled = true;
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.2;
        container.appendChild(renderer.domElement);

        // Add Ambient light for soft global lighting
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.7);
        scene.add(ambientLight);

        // Main Directional light for shadows/highlights
        const dirLight1 = new THREE.DirectionalLight(0xffffff, 1.5);
        dirLight1.position.set(5, 10, 7);
        scene.add(dirLight1);

        const dirLight2 = new THREE.DirectionalLight(0xffffff, 0.5);
        dirLight2.position.set(-5, -5, -5);
        scene.add(dirLight2);

        // Tech theme glowing accent lights (blue and purple)
        const blueLight = new THREE.PointLight(0x4361ee, 8, 15);
        blueLight.position.set(-3, 2, 3);
        scene.add(blueLight);

        const purpleLight = new THREE.PointLight(0x7209b7, 8, 15);
        purpleLight.position.set(3, -2, -3);
        scene.add(purpleLight);

        // Add OrbitControls for interactive 3D navigation
        const controls = new THREE.OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true;
        controls.dampingFactor = 0.05;
        controls.enableZoom = true;
        controls.maxPolarAngle = Math.PI / 2 + 0.1; // Restrict camera from going too far below the floor
        controls.autoRotate = true;
        controls.autoRotateSpeed = 1.5; // Smooth slow rotation

        const loaderSpinner = document.getElementById('gltf-loader');

        // Load GLTF / GLB model
        const loader = new THREE.GLTFLoader();
        let loadedModel;

        loader.load(
            'assets/retro_computer_-_pc_low_poly_3d_model.glb',
            function (gltf) {
                loadedModel = gltf.scene;

                // Robust auto-scaling and auto-centering
                const box = new THREE.Box3().setFromObject(loadedModel);
                const center = box.getCenter(new THREE.Vector3());
                const size = box.getSize(new THREE.Vector3());

                // Offset model to be centered at origin (0,0,0)
                loadedModel.position.x += (loadedModel.position.x - center.x);
                loadedModel.position.y += (loadedModel.position.y - center.y);
                loadedModel.position.z += (loadedModel.position.z - center.z);

                // Compute ideal camera distance based on model's dimensions
                const maxDim = Math.max(size.x, size.y, size.z);
                const fov = camera.fov * (Math.PI / 180);
                let cameraDistance = Math.abs(maxDim / 2 / Math.tan(fov / 2));
                cameraDistance *= 1.4; // Add visual padding/margin

                // Position camera & look at center
                camera.position.set(cameraDistance * 0.7, cameraDistance * 0.5, cameraDistance * 1.1);
                camera.lookAt(0, 0, 0);

                // Configure control boundaries based on computed distance
                controls.target.set(0, 0, 0);
                controls.maxDistance = cameraDistance * 2.2;
                controls.minDistance = cameraDistance * 0.4;

                // Enable shadow casting and standard material properties for all meshes
                loadedModel.traverse(function (node) {
                    if (node.isMesh) {
                        node.castShadow = true;
                        node.receiveShadow = true;
                        if (node.material) {
                            node.material.roughness = 0.3;
                            node.material.metalness = 0.7;
                        }
                    }
                });

                scene.add(loadedModel);

                // Fade out and remove loading spinner smoothly
                if (loaderSpinner) {
                    loaderSpinner.style.opacity = '0';
                    setTimeout(() => loaderSpinner.style.display = 'none', 500);
                }
            },
            // Loader progress
            function (xhr) {
                if (xhr.lengthComputable && loaderSpinner) {
                    const percent = Math.round((xhr.loaded / xhr.total) * 100);
                    const label = loaderSpinner.querySelector('.text-muted');
                    if (label) label.textContent = `MEMUAT MODEL 3D (${percent}%)`;
                }
            },
            // Loader error
            function (error) {
                console.error('Error loading GLTF model:', error);
                if (loaderSpinner) {
                    loaderSpinner.innerHTML = '<div class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill fs-3 d-block mb-2"></i> GAGAL MEMUAT MODEL 3D</div>';
                }
            }
        );

        // Animation rendering loop
        function animate() {
            requestAnimationFrame(animate);
            controls.update();
            renderer.render(scene, camera);
        }
        animate();

        // Responsive handling on window resize
        window.addEventListener('resize', function () {
            if (!container) return;
            const width = container.clientWidth;
            const height = container.clientHeight;
            camera.aspect = width / height;
            camera.updateProjectionMatrix();
            renderer.setSize(width, height);
        });
    });
</script>
</body>
</html>
