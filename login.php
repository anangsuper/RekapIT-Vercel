<?php
session_start();
require_once 'config/database.php';

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';

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

            // Log Login
            require_once 'models/ActivityLog.php';
            $logModel = new ActivityLog($conn);
            $logModel->add($user['id'], 'LOGIN', 'User berhasil login ke sistem.');

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
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-hover: #3f37c9;
            --primary-light: rgba(67, 97, 238, 0.08);
            --bg-body: #0f172a; /* Dark tech slate background */
            --glass-bg: rgba(30, 41, 59, 0.7); /* Deep slate-800 with transparency */
            --glass-border: rgba(255, 255, 255, 0.08);
            --text-muted: #94a3b8;
            --text-main: #f8fafc;
        }

        body {
            font-family: "Plus Jakarta Sans", sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            margin: 0;
            padding: 20px;
        }

        /* Ambient Glowing Background Blobs */
        .bg-blobs {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            overflow: hidden;
        }

        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(120px);
            opacity: 0.35;
            animation: float 20s infinite ease-in-out;
        }

        .blob-1 {
            top: -10%;
            left: -10%;
            width: 500px;
            height: 500px;
            background: #4361ee;
            animation-delay: 0s;
        }

        .blob-2 {
            bottom: -10%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: #7209b7;
            animation-delay: -5s;
        }

        .blob-3 {
            top: 40%;
            left: 50%;
            width: 300px;
            height: 300px;
            background: #4cc9f0;
            opacity: 0.15;
            animation-delay: -10s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) scale(1);
            }
            50% {
                transform: translateY(-40px) scale(1.15);
            }
        }

        /* Modern Glass Card */
        .login-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 28px;
            padding: 45px 40px;
            width: 100%;
            max-width: 460px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: cardAppear 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            z-index: 10;
        }

        @keyframes cardAppear {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Logo and Header */
        .brand-wrapper {
            margin-bottom: 35px;
            text-align: center;
        }

        .brand-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--primary-color), #7209b7);
            margin-bottom: 16px;
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.3);
        }

        .brand-icon i {
            font-size: 1.8rem;
            color: #fff;
        }

        .brand-name {
            font-weight: 800;
            font-size: 1.75rem;
            background: linear-gradient(to right, #ffffff, #cbd5e1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .brand-tagline {
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 400;
        }

        /* Input Styles */
        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #cbd5e1;
            margin-bottom: 8px;
        }

        .input-group-custom {
            position: relative;
            background: rgba(15, 23, 42, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            display: flex;
            align-items: center;
            transition: all 0.25s ease;
        }

        .input-group-custom:focus-within {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.15);
            background: rgba(15, 23, 42, 0.6);
        }

        .input-group-icon {
            padding: 0 15px 0 18px;
            color: var(--text-muted);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .input-control-custom {
            background: transparent !important;
            border: none !important;
            color: #fff !important;
            padding: 14px 18px 14px 0px;
            font-size: 0.95rem;
            flex: 1;
            outline: none;
            width: 100%;
        }

        .input-control-custom::placeholder {
            color: #64748b;
        }

        /* Hide Default Autofill Background on Chrome */
        .input-control-custom:-webkit-autofill,
        .input-control-custom:-webkit-autofill:hover,
        .input-control-custom:-webkit-autofill:focus {
            -webkit-text-fill-color: #fff !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        .password-toggle {
            padding: 0 18px;
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            transition: color 0.2s;
            display: flex;
            align-items: center;
        }

        .password-toggle:hover {
            color: #fff;
        }

        /* Button styling */
        .btn-login {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            border: none;
            color: white;
            padding: 14px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px -5px rgba(67, 97, 238, 0.35);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px -5px rgba(67, 97, 238, 0.5);
            background: linear-gradient(135deg, #4f46e5, #3730a3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* Alert styling */
        .alert-custom {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #fca5a5;
            border-radius: 14px;
            padding: 12px 16px;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            animation: shake 0.4s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }

        /* Account Hint Box */
        .hint-box {
            background: rgba(255, 255, 255, 0.02);
            border: 1px dashed rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            padding: 12px;
            margin-top: 30px;
            text-align: center;
        }

        .hint-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .hint-text {
            font-size: 0.8rem;
            color: #94a3b8;
            margin: 0;
        }

        .hint-text code {
            color: #38bdf8;
            font-weight: 600;
            background: rgba(56, 189, 248, 0.1);
            padding: 2px 6px;
            border-radius: 4px;
        }

        /* 3D Model Canvas Container */
        #canvas-container {
            background: rgba(30, 41, 59, 0.35);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
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

<div class="d-flex align-items-center justify-content-center w-100 h-100 flex-wrap gap-5">
    
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

        <form method="POST" autocomplete="off">
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
            'assets/smol_ame_in_an_upcycled_terrarium_hololiveen.glb',
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