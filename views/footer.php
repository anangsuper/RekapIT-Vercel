        </div>
    </div>
</div>

<!-- Modal Peringatan Auto-Logout -->
<div class="modal fade" id="timeoutWarningModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true" style="z-index: 1080;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg animate-fade-in" style="border-radius: 24px;">
            <div class="modal-body text-center p-4">
                <div class="text-warning mb-3">
                    <i class="bi bi-exclamation-triangle-fill animate-pulse" style="font-size: 3rem; color: #f59e0b;"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Sesi Hampir Berakhir</h5>
                <p class="text-muted small mb-4">
                    Anda terdeteksi tidak aktif. Sesi Anda akan berakhir otomatis dalam <span id="timeout-countdown-number" class="fw-bold text-danger fs-5">30</span> detik.
                </p>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-primary py-2.5 fw-bold" onclick="keepSessionAlive()" style="border-radius: 12px;">Tetap Masuk</button>
                    <a href="logout.php" class="btn btn-light text-danger py-2.5 fw-semibold" style="border-radius: 12px;">Keluar Sekarang</a>
                </div>
            </div>
        </div>
    </div>
<!-- Modal Panduan & Tutorial Penggunaan Website -->
<div class="modal fade" id="modalGlobalHelp" tabindex="-1" aria-labelledby="modalGlobalHelpLabel" aria-hidden="true" style="z-index: 1075;">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; background: var(--modal-bg);">
            <div class="modal-header border-0 p-4 pb-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-4 text-primary">
                        <i class="bi bi-journal-bookmark-fill fs-3"></i>
                    </div>
                    <div>
                        <h4 class="fw-800 m-0 text-dark" id="modalGlobalHelpLabel">Panduan Penggunaan Website <span class="badge bg-primary bg-opacity-10 text-primary fs-6 ms-2 fw-bold">Rekap IT</span></h4>
                        <p class="text-muted small m-0">Petunjuk praktis & tata cara penggunaan fitur-fitur pada Rekap IT - Asset Management & Helpdesk.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- Navigation Tabs -->
                <ul class="nav nav-pills nav-fill bg-light p-1.5 rounded-4 mb-4 border" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active rounded-3 py-2.5 fw-bold" id="tab-help-dashboard" data-bs-toggle="pill" data-bs-target="#help-dashboard" type="button" role="tab">
                            <i class="bi bi-speedometer2 me-1.5"></i> Ringkasan & Dashboard
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-3 py-2.5 fw-bold" id="tab-help-asset" data-bs-toggle="pill" data-bs-target="#help-asset" type="button" role="tab">
                            <i class="bi bi-laptop me-1.5"></i> Manajemen Aset & Kartu
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-3 py-2.5 fw-bold" id="tab-help-maint" data-bs-toggle="pill" data-bs-target="#help-maint" type="button" role="tab">
                            <i class="bi bi-tools me-1.5"></i> Maintenance & Servis
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-3 py-2.5 fw-bold" id="tab-help-helpdesk" data-bs-toggle="pill" data-bs-target="#help-helpdesk" type="button" role="tab">
                            <i class="bi bi-headset me-1.5"></i> Helpdesk Tiket
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-3 py-2.5 fw-bold" id="tab-help-master" data-bs-toggle="pill" data-bs-target="#help-master" type="button" role="tab">
                            <i class="bi bi-gear-fill me-1.5"></i> Master Data & Export
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="helpModalTabContent">
                    <!-- Tab 1: Dashboard -->
                    <div class="tab-pane fade show active" id="help-dashboard" role="tabpanel">
                        <div class="card border-0 bg-light rounded-4 p-4 mb-3">
                            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-bar-chart-line-fill me-2"></i>Fungsi Utama Dashboard</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="p-3 bg-white rounded-3 border h-100">
                                        <div class="fw-bold text-dark mb-1"><i class="bi bi-pie-chart text-info me-2"></i>Statistik Total & Kondisi</div>
                                        <p class="small text-muted mb-0">Menampilkan jumlah aset total, perbaikan aktif, estimasi biaya bulan ini, serta aset yang memerlukan tindakan (Rusak Ringan/Rusak Berat).</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-white rounded-3 border h-100">
                                        <div class="fw-bold text-dark mb-1"><i class="bi bi-bell-fill text-warning me-2"></i>Notifikasi Pemeliharaan (H-7)</div>
                                        <p class="small text-muted mb-0">Memberikan pengingat otomatis untuk jadwal maintenance aset yang mendekati jatuh tempo dalam 7 hari ke depan.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 2: Asset Management & Cards -->
                    <div class="tab-pane fade" id="help-asset" role="tabpanel">
                        <div class="card border-0 bg-light rounded-4 p-4 mb-3">
                            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-card-heading me-2"></i>Pengelolaan Aset IT & Cetak Kartu ATM (CR80)</h6>
                            <ol class="small text-muted ps-3 mb-0" style="line-height: 1.8;">
                                <li><b>Tambah Aset Baru:</b> Masuk ke menu <span class="badge bg-primary">Data Aset</span>, klik <b>+ Tambah Aset</b>. Isi Kode Aset, Nama, Merk/Model, Kategori, Cabang, Divisi, dan Karyawan Penanggung Jawab.</li>
                                <li><b>Cetak Kartu Inventaris:</b> Masuk ke menu <span class="badge bg-primary">Cetak Kartu</span>. Centang kartu yang ingin dicetak atau impor langsung dari daftar Aset IT.</li>
                                <li><b>Pratinjau Kartu Fisik (Live Preview):</b> Klik tombol <b>Cetak Kartu Pilihan</b> untuk membuka modal interaktif. Pilih grid A4 (8, 10, atau 12 kartu/lembar), lalu gunakan tab <b>Pratinjau Kartu Fisik</b> untuk melihat hasil tampilan fisik kartu ATM (CR80).</li>
                                <li><b>Ekspor & Cetak:</b> Klik <b>Cetak Sekarang</b> untuk mencetak ke kertas A4 / PDF, atau klik <b>Ekspor Word (.doc)</b> untuk mendownload file MS Word.</li>
                                <li><b>Mutasi Aset:</b> Masuk ke menu <span class="badge bg-primary">Mutasi Aset</span> untuk memindahkan aset antar cabang, divisi, atau karyawan dengan mencatat tanggal & keterangan mutasi.</li>
                            </ol>
                        </div>
                    </div>

                    <!-- Tab 3: Maintenance & Repairs -->
                    <div class="tab-pane fade" id="help-maint" role="tabpanel">
                        <div class="card border-0 bg-light rounded-4 p-4 mb-3">
                            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-tools me-2"></i>Pemeliharaan Berkala & Servis/Sparepart</h6>
                            <ul class="small text-muted ps-3 mb-0" style="line-height: 1.8;">
                                <li><b>Maintenance Rutin:</b> Masuk ke <span class="badge bg-primary">Pemeliharaan (Maintenance)</span> untuk mencatat pembersihan hardware, penanganan virus, dan pengecekan fisik berkala per aset.</li>
                                <li><b>Maintenance Massal:</b> Gunakan menu <span class="badge bg-primary">Maintenance Massal</span> untuk mencatat tindakan maintenance sekaligus untuk banyak unit aset dalam 1 formulir.</li>
                                <li><b>Servis Perbaikan (Repairs):</b> Jika perangkat mengalami kerusakan, catat pada menu <span class="badge bg-primary">Perbaikan (Repairs)</span> lengkap dengan biaya servis, teknisi, dan penggunaan stok <b>Sparepart</b>.</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Tab 4: Helpdesk -->
                    <div class="tab-pane fade" id="help-helpdesk" role="tabpanel">
                        <div class="card border-0 bg-light rounded-4 p-4 mb-3">
                            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-headset me-2"></i>Sistem Helpdesk & Tiket Pengaduan</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="p-3 bg-white rounded-3 border h-100">
                                        <div class="fw-bold text-dark mb-1"><i class="bi bi-person-fill text-success me-2"></i>Untuk Karyawan / Pelapor</div>
                                        <p class="small text-muted mb-0">Buka halaman portal Helpdesk, isi Nama, Kontak, Cabang/Divisi, dan deskripsi keluhan. Anda akan mendapatkan Nomor Tiket otomatis (misal: <code>TK-20260811-001</code>) untuk melacak progress penanganan.</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-white rounded-3 border h-100">
                                        <div class="fw-bold text-dark mb-1"><i class="bi bi-person-gear text-primary me-2"></i>Untuk Admin / Teknisi IT</div>
                                        <p class="small text-muted mb-0">Masuk ke menu <span class="badge bg-primary">Tiket Helpdesk</span>. Teknisi dapat memperbarui status tiket (Menunggu -> Proses -> Selesai), membalas komentar langsung kepada pelapor, serta menerima notifikasi bot Telegram otomatis.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 5: Master Data & Reports -->
                    <div class="tab-pane fade" id="help-master" role="tabpanel">
                        <div class="card border-0 bg-light rounded-4 p-4 mb-3">
                            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-file-earmark-excel me-2"></i>Pengelolaan Master Data & Laporan Export</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="p-3 bg-white rounded-3 border h-100">
                                        <div class="fw-bold text-dark mb-1"><i class="bi bi-database me-2"></i>Master Data Cabang & Karyawan</div>
                                        <p class="small text-muted mb-0">Kelola master data Cabang, Divisi, Karyawan, dan Pengguna Akun pada grup menu <b>MASTER DATA</b>. Semua data terhubung otomatis saat memasukkan Aset atau Tiket Helpdesk.</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-white rounded-3 border h-100">
                                        <div class="fw-bold text-dark mb-1"><i class="bi bi-download text-success me-2"></i>Export Laporan Bulanan</div>
                                        <p class="small text-muted mb-0">Gunakan menu <span class="badge bg-primary">Report Bulanan</span> & <span class="badge bg-primary">Export Excel</span> untuk mencetak rekapitulasi maintenance bulanan dan mendownload seluruh data aset ke dalam format spreadsheet Excel.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

<!-- Modal Live Camera QR Code / Barcode Scanner -->
<div class="modal fade" id="modalCameraScanner" tabindex="-1" aria-labelledby="modalCameraScannerLabel" aria-hidden="true" style="z-index: 1076;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; background: var(--modal-bg);">
            <div class="modal-header border-0 p-4 pb-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary bg-opacity-10 p-2.5 rounded-4 text-primary">
                        <i class="bi bi-qr-code-scan fs-3"></i>
                    </div>
                    <div>
                        <h5 class="fw-800 m-0 text-dark" id="modalCameraScannerLabel">Scan Stiker Aset Kamera</h5>
                        <p class="text-muted small m-0">Arahkan kamera Laptop / HP ke QR Code atau Barcode stiker aset.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4 text-center">
                <div id="reader" style="width: 100%; min-height: 280px; background: #000; border-radius: 16px; overflow: hidden; position: relative;" class="shadow-sm"></div>
                <div id="scanner-result-msg" class="mt-3 small text-muted">
                    <i class="bi bi-camera-fill me-1"></i> Membuka kamera...
                </div>
            </div>

            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-secondary px-4 py-2 fw-bold" data-bs-dismiss="modal" style="border-radius: 12px;">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let html5QrCodeScanner = null;

    const scannerModalEl = document.getElementById('modalCameraScanner');
    if (scannerModalEl) {
        scannerModalEl.addEventListener('shown.bs.modal', function () {
            const resultMsg = document.getElementById('scanner-result-msg');
            if (resultMsg) resultMsg.innerHTML = '<span class="text-primary fw-bold"><i class="bi bi-eye-fill me-1"></i> Kamera Aktif. Arahkan ke Stiker QR Code...</span>';
            
            if (!html5QrCodeScanner) {
                html5QrCodeScanner = new Html5Qrcode("reader");
            }
            
            const config = { fps: 10, qrbox: { width: 220, height: 220 } };
            html5QrCodeScanner.start({ facingMode: "environment" }, config, onScanSuccess, onScanFailure)
                .catch(err => {
                    if (resultMsg) resultMsg.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i> Gagal membuka kamera. Pastikan izin kamera telah diberikan di browser.</span>';
                });
        });

        scannerModalEl.addEventListener('hidden.bs.modal', function () {
            if (html5QrCodeScanner && html5QrCodeScanner.isScanning) {
                html5QrCodeScanner.stop().then(() => {
                    console.log("Scanner stopped.");
                }).catch(err => console.error(err));
            }
        });
    }

    function onScanSuccess(decodedText, decodedResult) {
        if (html5QrCodeScanner && html5QrCodeScanner.isScanning) {
            html5QrCodeScanner.stop();
        }
        const resultMsg = document.getElementById('scanner-result-msg');
        if (resultMsg) resultMsg.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> QR Terdeteksi: "${decodedText}"! Mengarahkan...</span>`;
        
        setTimeout(() => {
            window.location.href = `index.php?page=inventaris&search=${encodeURIComponent(decodedText)}`;
        }, 600);
    }

    function onScanFailure(error) {
        // Silent frame scanning
    }
    // Move all modals to body to ensure they are on the top stacking context
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        document.body.appendChild(modal);
    });

    // Sidebar Toggler (Desktop & Mobile)
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const sidebar = document.getElementById('sidebarContainer');
    const overlay = document.getElementById('sidebarOverlay');

    function toggleMobileSidebar() {
        if (sidebar) sidebar.classList.toggle('show');
        if (overlay) overlay.classList.toggle('show');
    }

    function hideMobileSidebar() {
        if (sidebar) sidebar.classList.remove('show');
        if (overlay) overlay.classList.remove('show');
    }

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (window.innerWidth >= 992) {
                // Desktop Toggle (Hide/Show)
                document.body.classList.toggle('sidebar-hidden');
                localStorage.setItem('sidebar-hidden-pref', document.body.classList.contains('sidebar-hidden') ? 'true' : 'false');
            } else {
                // Mobile Drawer Toggle
                toggleMobileSidebar();
            }
        });
        
        if (overlay) {
            overlay.addEventListener('click', hideMobileSidebar);
        }

        document.addEventListener('click', function(e) {
            if (window.innerWidth < 992) {
                if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                    hideMobileSidebar();
                }
            }
        });
    }

    // Collapsible Sidebar Menu Sections (Slide Up / Slide Down)
    const collapseHeadings = document.querySelectorAll('.sidebar-heading[data-bs-toggle="collapse-sidebar"]');
    collapseHeadings.forEach(heading => {
        const targetSelector = heading.getAttribute('data-target');
        const targetNav = document.querySelector(targetSelector);
        
        if (targetNav) {
            const savedState = localStorage.getItem('sidebar-collapse-' + targetSelector);
            const hasActiveChild = targetNav.querySelector('.sidebar-link.active') !== null;
            
            // If active link is inside, force open section; otherwise restore saved state
            if (hasActiveChild) {
                heading.classList.remove('collapsed');
                targetNav.classList.remove('collapsed');
            } else if (savedState === 'collapsed') {
                heading.classList.add('collapsed');
                targetNav.classList.add('collapsed');
            }

            heading.addEventListener('click', function(e) {
                e.preventDefault();
                const isCollapsed = targetNav.classList.toggle('collapsed');
                heading.classList.toggle('collapsed', isCollapsed);
                localStorage.setItem('sidebar-collapse-' + targetSelector, isCollapsed ? 'collapsed' : 'expanded');
            });
        }
    });

    // Restore desktop sidebar preference on load
    if (window.innerWidth >= 992) {
        const isHidden = localStorage.getItem('sidebar-hidden-pref') === 'true';
        if (isHidden) {
            document.body.classList.add('sidebar-hidden');
        }
    }

    // Maintain Sidebar Scroll Position
    if (sidebar) {
        const savedScroll = localStorage.getItem('sidebar-scroll');
        if (savedScroll !== null) {
            sidebar.scrollTop = parseInt(savedScroll, 10);
        }

        sidebar.querySelectorAll('.sidebar-link').forEach(link => {
            link.addEventListener('click', function() {
                localStorage.setItem('sidebar-scroll', sidebar.scrollTop);
            });
        });

        window.addEventListener('beforeunload', function() {
            localStorage.setItem('sidebar-scroll', sidebar.scrollTop);
        });
    }

    // Live Clock Update
    function updateClock() {
        const clockEl = document.getElementById('realtime-clock');
        if (clockEl) {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
            clockEl.textContent = now.toLocaleDateString('id-ID', options);
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Auto-logout warning countdown after 4.5 minutes of inactivity, logout at 5 minutes
    <?php if (isset($_SESSION['user_id'])): ?>
    let lastActivity = Date.now();
    const maxIdleTime = 5 * 60 * 1000; // 5 minutes
    const warningThreshold = 4.5 * 60 * 1000; // 4 minutes 30 seconds (30s warning)
    
    let warningModalInstance = null;
    let countdownInterval = null;
    
    // Reset timer on user activity events
    const activityEvents = ['mousemove', 'keypress', 'mousedown', 'touchstart', 'scroll'];
    activityEvents.forEach(evt => {
        document.addEventListener(evt, resetTimer, true);
    });
    
    function resetTimer() {
        // Do not reset while countdown modal is shown (requires explicit button click)
        const modalEl = document.getElementById('timeoutWarningModal');
        if (modalEl && modalEl.classList.contains('show')) {
            return;
        }
        lastActivity = Date.now();
    }
    
    // Check idle state every 1 second
    const sessionCheckInterval = setInterval(checkSessionIdle, 1000);
    
    function checkSessionIdle() {
        const elapsed = Date.now() - lastActivity;
        
        if (elapsed >= maxIdleTime) {
            clearInterval(sessionCheckInterval);
            if (countdownInterval) clearInterval(countdownInterval);
            window.location.href = 'logout.php?reason=timeout';
        } else if (elapsed >= warningThreshold) {
            const modalEl = document.getElementById('timeoutWarningModal');
            if (modalEl && !modalEl.classList.contains('show')) {
                if (!warningModalInstance) {
                    // Initialize Bootstrap Modal if not already done
                    warningModalInstance = new bootstrap.Modal(modalEl);
                }
                warningModalInstance.show();
                
                let secondsLeft = Math.ceil((maxIdleTime - elapsed) / 1000);
                const countEl = document.getElementById('timeout-countdown-number');
                if (countEl) countEl.textContent = secondsLeft;
                
                if (countdownInterval) clearInterval(countdownInterval);
                countdownInterval = setInterval(() => {
                    secondsLeft--;
                    if (countEl) countEl.textContent = secondsLeft;
                    if (secondsLeft <= 0) {
                        clearInterval(countdownInterval);
                    }
                }, 1000);
            }
        }
    }
    
    // Keep session alive trigger (button click)
    window.keepSessionAlive = function() {
        if (warningModalInstance) {
            warningModalInstance.hide();
        }
        if (countdownInterval) {
            clearInterval(countdownInterval);
        }
        lastActivity = Date.now();
        
        // Refresh server session cookie via health check ping
        fetch('api/health.php')
            .then(res => res.json())
            .then(data => console.log("Keep alive ping success", data))
            .catch(err => console.error("Keep alive ping error", err));
    };
    <?php endif; ?>

    // Theme Toggle Handler
    const themeToggleBtn = document.getElementById('theme-toggle-btn');
    const themeToggleIcon = document.getElementById('theme-toggle-icon');
    
    if (themeToggleBtn && themeToggleIcon) {
        // Set initial icon on load
        const currentTheme = localStorage.getItem('theme-pref') || 'dark';
        if (currentTheme === 'light') {
            themeToggleIcon.className = 'bi bi-sun';
        } else {
            themeToggleIcon.className = 'bi bi-moon-stars';
        }
        
        themeToggleBtn.addEventListener('click', function() {
            document.body.classList.toggle('light-theme');
            const theme = document.body.classList.contains('light-theme') ? 'light' : 'dark';
            localStorage.setItem('theme-pref', theme);
            
            // Update icon
            if (theme === 'light') {
                themeToggleIcon.className = 'bi bi-sun';
            } else {
                themeToggleIcon.className = 'bi bi-moon-stars';
            }
        });
    }
});
</script>

</body>
</html>
