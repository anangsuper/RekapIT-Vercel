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
    $error = 'Sesi Anda telah berakhir karena tidak ada aktivitas selama 5 menit.';
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
        /* Minimal Gen Z refresh */
        :root {
            --primary-color: #111827;
            --primary-hover: #000000;
            --secondary-color: #bef264;
            --bg-body: #f6f7f9;
            --glass-bg: #ffffff;
            --glass-border: #e5e7eb;
            --text-muted: #6b7280;
            --text-main: #111827;
        }

        body {
            background: var(--bg-body);
            color: var(--text-main);
            letter-spacing: 0;
            font-family: "Plus Jakarta Sans", sans-serif;
            min-height: 100vh;
        }

        .bg-blobs {
            display: none !important;
        }

        .login-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 22px;
            padding: 36px;
            max-width: 430px;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
            z-index: 10;
        }

        .brand-wrapper {
            margin-bottom: 28px;
            text-align: center;
        }

        .brand-icon {
            width: 54px;
            height: 54px;
            border-radius: 18px;
            background: #111827;
            box-shadow: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .brand-icon i {
            color: #bef264;
            font-size: 1.5rem;
        }

        .brand-name {
            font-family: 'Anton', sans-serif;
            font-size: 4rem;
            color: #111827;
            text-shadow: 10px 10px 0 rgba(0, 0, 0, 0.1);
            margin-bottom: 6px;
            letter-spacing: 2px;
            cursor: default;
        }

        .brand-tagline {
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 400;
            margin-top: -10px;
        }

        .form-label {
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 8px;
        }

        .input-group-custom {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            display: flex;
            align-items: center;
        }

        .input-group-custom:focus-within {
            background: #ffffff;
            border-color: #111827;
            box-shadow: 0 0 0 4px rgba(17, 24, 39, 0.08);
        }

        .input-group-icon {
            padding: 0 15px 0 18px;
            color: var(--text-muted);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
        }

        .input-control-custom {
            color: #111827 !important;
            background: transparent !important;
            border: none !important;
            padding: 14px 18px 14px 0px;
            font-size: 0.95rem;
            flex: 1;
            outline: none;
        }

        .input-control-custom:-webkit-autofill,
        .input-control-custom:-webkit-autofill:hover,
        .input-control-custom:-webkit-autofill:focus {
            -webkit-text-fill-color: #111827 !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        .password-toggle {
            padding: 0 18px;
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .password-toggle:hover {
            color: #111827;
        }

        .btn-login {
            background: #111827;
            border-radius: 999px;
            box-shadow: none;
            color: white;
            padding: 14px;
            font-weight: 700;
            border: none;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            background: #000000;
            transform: translateY(-1px);
        }

        .alert-custom {
            background: #fef2f2;
            border: 1px solid #fee2e2;
            color: #991b1b;
            border-radius: 14px;
            padding: 12px 16px;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
        }

        .hint-box {
            background: #f9fafb;
            border: 1px dashed #d1d5db;
            border-radius: 16px;
            padding: 12px;
            margin-top: 30px;
            text-align: center;
        }

        .hint-text {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin: 0;
        }

        .hint-text code {
            color: #111827;
            background: #bef264;
            border-radius: 999px;
            padding: 3px 8px;
        }

        /* 3D Model Canvas Container */
        #canvas-container {
            background: #ffffff;
            border: 1px solid var(--glass-border);
            border-radius: 28px;
            overflow: hidden;
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
            <div class="hint-title">Informasi Akses Default</div>
            <p class="hint-text">
                Username: <code>admin</code> &bull; Password: <code>password</code>
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
    const shadowOffset = 50;

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
            ${xWalk}px ${yWalk}px 0 rgba(255, 255, 255, 0.2)
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
