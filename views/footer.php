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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Move all modals to body to ensure they are on the top stacking context
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        document.body.appendChild(modal);
    });

    // Sidebar Toggler (Desktop & Mobile)
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const sidebar = document.getElementById('sidebarContainer');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (window.innerWidth >= 992) {
                // Desktop Toggle (Hide/Show)
                document.body.classList.toggle('sidebar-hidden');
                localStorage.setItem('sidebar-hidden-pref', document.body.classList.contains('sidebar-hidden') ? 'true' : 'false');
            } else {
                // Mobile Drawer Toggle
                sidebar.classList.toggle('show');
            }
        });
        
        document.addEventListener('click', function(e) {
            if (window.innerWidth < 992) {
                if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
    }

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
});
</script>

</body>
</html>
