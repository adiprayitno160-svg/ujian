    <?php if (is_logged_in() && !isset($hide_navbar)): ?>
            </div>
        </div>
    </div>
    
    <!-- Sidebar Toggle Button (Mobile) -->
    <?php if (is_logged_in() && !isset($hide_navbar)): ?>
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
        <i class="fas fa-bars"></i>
    </button>
    <?php endif; ?>
    <?php else: ?>
    </main>
    <?php endif; ?>
    
    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 10000;">
        <div id="toastNotification" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto">Notification</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body"></div>
        </div>
    </div>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery (optional, for compatibility) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Chart.js (for analytics) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo asset_url('js/main.js'); ?>"></script>
    
    <?php if (isset($custom_js)): ?>
        <?php foreach ($custom_js as $js): ?>
            <script src="<?php echo asset_url('js/' . $js . '.js'); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <script>
        // Show toast notification
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toastNotification');
            const toastBody = toast.querySelector('.toast-body');
            const toastHeader = toast.querySelector('.toast-header strong');
            
            toastBody.textContent = message;
            
            // Set color based on type
            toast.className = 'toast';
            if (type === 'success') toast.classList.add('text-bg-success');
            else if (type === 'error' || type === 'danger') toast.classList.add('text-bg-danger');
            else if (type === 'warning') toast.classList.add('text-bg-warning');
            else toast.classList.add('text-bg-info');
            
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
        }
        
        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert[data-auto-hide]');
            alerts.forEach(function(alert) {
                const delay = parseInt(alert.getAttribute('data-auto-hide')) || 3000;
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, delay);
            });
            
            // Sidebar Toggle
            const sidebar = document.getElementById('sidebar');
            const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const mainContent = document.querySelector('.main-content');
            
            // Check localStorage for sidebar state
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (sidebarCollapsed && sidebar) {
                sidebar.classList.add('collapsed');
                if (mainContent) mainContent.classList.add('sidebar-collapsed');
            }
            
            // Toggle sidebar function
            function toggleSidebar() {
                if (sidebar) {
                    sidebar.classList.toggle('collapsed');
                    if (mainContent) mainContent.classList.toggle('sidebar-collapsed');
                    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                }
            }
            
            // Mobile toggle
            function toggleMobileSidebar() {
                if (sidebar) {
                    sidebar.classList.toggle('show');
                    if (sidebarOverlay) sidebarOverlay.classList.toggle('show');
                }
            }
            
            // Desktop toggle button
            if (sidebarToggleBtn) {
                sidebarToggleBtn.addEventListener('click', function() {
                    // Check if mobile view
                    if (window.innerWidth <= 768) {
                        toggleMobileSidebar();
                    } else {
                        toggleSidebar();
                    }
                });
            }
            
            // Mobile toggle button (fixed position)
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', toggleMobileSidebar);
            }
            
            // Close sidebar when clicking overlay
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', toggleMobileSidebar);
            }
            
            // Close sidebar on window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768 && sidebar) {
                    sidebar.classList.remove('show');
                    if (sidebarOverlay) sidebarOverlay.classList.remove('show');
                }
            });
        });
    </script>
    
    <?php if (isset($page_scripts)): ?>
        <?php echo $page_scripts; ?>
    <?php endif; ?>
</body>
</html>

