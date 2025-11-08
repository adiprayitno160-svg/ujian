/**
 * Exam Security Functions
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Enhanced with Anti Tab Switch and Browser Lock
 */

const ExamSecurity = {
    tabSwitchCount: 0,
    warningCount: 0,
    maxWarnings: 3,
    maxTabSwitches: 3,
    isFullscreen: false,
    isLocked: false,
    lastActivity: Date.now(),
    checkInterval: null,
    fullscreenCheckInterval: null,
    
    init: function() {
        this.lockBrowser();
        this.detectTabSwitch();
        this.preventCopyPaste();
        this.detectScreenshot();
        this.enableFullscreen();
        this.preventNavigation();
        this.detectIdle();
        this.detectDeveloperTools();
        this.detectMultipleWindows();
        this.startPeriodicCheck();
        this.startFullscreenMonitor();
    },
    
    lockBrowser: function() {
        this.isLocked = true;
        
        // Prevent context menu
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }, true);
        
        // Prevent drag and drop
        document.addEventListener('dragstart', (e) => {
            e.preventDefault();
            return false;
        }, true);
        
        // Prevent text selection
        document.addEventListener('selectstart', (e) => {
            e.preventDefault();
            return false;
        }, true);
        
        // Prevent F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+U
        document.addEventListener('keydown', (e) => {
            // F12
            if (e.key === 'F12') {
                e.preventDefault();
                e.stopPropagation();
                this.showWarning('Developer tools tidak diizinkan!');
                return false;
            }
            
            // Ctrl+Shift+I (DevTools)
            if (e.ctrlKey && e.shiftKey && e.key === 'I') {
                e.preventDefault();
                e.stopPropagation();
                this.showWarning('Developer tools tidak diizinkan!');
                return false;
            }
            
            // Ctrl+Shift+J (Console)
            if (e.ctrlKey && e.shiftKey && e.key === 'J') {
                e.preventDefault();
                e.stopPropagation();
                this.showWarning('Developer tools tidak diizinkan!');
                return false;
            }
            
            // Ctrl+U (View Source)
            if (e.ctrlKey && e.key === 'u') {
                e.preventDefault();
                e.stopPropagation();
                this.showWarning('View source tidak diizinkan!');
                return false;
            }
            
            // Ctrl+Shift+C (Inspect Element)
            if (e.ctrlKey && e.shiftKey && e.key === 'C') {
                e.preventDefault();
                e.stopPropagation();
                this.showWarning('Inspect element tidak diizinkan!');
                return false;
            }
        }, true);
    },
    
    detectTabSwitch: function() {
        let lastVisibilityChange = Date.now();
        
        document.addEventListener('visibilitychange', () => {
            const now = Date.now();
            
            if (document.hidden) {
                this.tabSwitchCount++;
                const timeHidden = now - lastVisibilityChange;
                
                this.logSecurityEvent('tab_switch', `Tab/window switched (Count: ${this.tabSwitchCount})`);
                
                // Show immediate warning
                this.showWarning(`Peringatan ${this.tabSwitchCount}/${this.maxTabSwitches}: Jangan beralih tab atau window!`);
                
                // Auto-submit if too many switches
                if (this.tabSwitchCount >= this.maxTabSwitches) {
                    this.autoSubmit(`Terlalu banyak tab switch (${this.tabSwitchCount}x)`);
                    return;
                }
                
                // Log to server immediately
                this.sendSecurityLog('tab_switch', {
                    count: this.tabSwitchCount,
                    time_hidden: timeHidden
                });
            } else {
                // Tab is visible again
                const timeHidden = now - lastVisibilityChange;
                if (timeHidden > 1000) { // More than 1 second
                    this.logSecurityEvent('tab_return', `Returned to tab after ${timeHidden}ms`);
                }
            }
            
            lastVisibilityChange = now;
        });
        
        window.addEventListener('blur', () => {
            this.logSecurityEvent('window_blur', 'Window lost focus');
            this.warningCount++;
            
            if (this.warningCount >= this.maxWarnings) {
                this.autoSubmit('Terlalu banyak kehilangan fokus window');
            }
        });
        
        window.addEventListener('focus', () => {
            this.logSecurityEvent('window_focus', 'Window gained focus');
            this.lastActivity = Date.now();
        });
        
        // Detect Alt+Tab (Windows) or Cmd+Tab (Mac)
        document.addEventListener('keydown', (e) => {
            if ((e.altKey && e.key === 'Tab') || (e.metaKey && e.key === 'Tab')) {
                e.preventDefault();
                this.showWarning('Mengganti aplikasi tidak diizinkan!');
                this.logSecurityEvent('app_switch_attempt', 'Attempted to switch application');
            }
        });
    },
    
    preventCopyPaste: function() {
        // Prevent right-click
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            this.showWarning('Copy-paste tidak diizinkan!');
            return false;
        });
        
        // Prevent copy
        document.addEventListener('copy', (e) => {
            e.preventDefault();
            this.showWarning('Copy tidak diizinkan!');
            return false;
        });
        
        // Prevent paste
        document.addEventListener('paste', (e) => {
            e.preventDefault();
            this.showWarning('Paste tidak diizinkan!');
            return false;
        });
        
        // Prevent cut
        document.addEventListener('cut', (e) => {
            e.preventDefault();
            this.showWarning('Cut tidak diizinkan!');
            return false;
        });
        
        // Prevent keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl+C, Ctrl+V, Ctrl+A, Ctrl+X, Ctrl+S
            if (e.ctrlKey && (e.key === 'c' || e.key === 'v' || e.key === 'a' || e.key === 'x' || e.key === 's')) {
                e.preventDefault();
                this.showWarning('Shortcut keyboard tidak diizinkan!');
                return false;
            }
            
            // F12 (Developer Tools)
            if (e.key === 'F12') {
                e.preventDefault();
                this.showWarning('Developer tools tidak diizinkan!');
                return false;
            }
        });
        
        // Prevent text selection
        document.addEventListener('selectstart', (e) => {
            e.preventDefault();
            return false;
        });
    },
    
    detectScreenshot: function() {
        // Detect print screen (limited browser support)
        document.addEventListener('keydown', (e) => {
            if (e.key === 'PrintScreen') {
                e.preventDefault();
                this.logSecurityEvent('screenshot_attempt', 'Screenshot attempt detected');
                this.showWarning('Screenshot tidak diizinkan!');
            }
        });
    },
    
    enableFullscreen: function() {
        // Request fullscreen if supported
        const requestFullscreen = () => {
            const elem = document.documentElement;
            
            if (elem.requestFullscreen) {
                return elem.requestFullscreen();
            } else if (elem.webkitRequestFullscreen) {
                return elem.webkitRequestFullscreen();
            } else if (elem.mozRequestFullScreen) {
                return elem.mozRequestFullScreen();
            } else if (elem.msRequestFullscreen) {
                return elem.msRequestFullscreen();
            }
            return Promise.reject('Fullscreen not supported');
        };
        
        // Try to enter fullscreen
        requestFullscreen().then(() => {
            this.isFullscreen = true;
            this.logSecurityEvent('fullscreen_entered', 'Entered fullscreen mode');
        }).catch((err) => {
            console.log('Fullscreen not available:', err);
            this.showWarning('Mode fullscreen diperlukan untuk ujian. Silakan aktifkan fullscreen secara manual.');
        });
        
        // Monitor fullscreen changes
        const fullscreenEvents = ['fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'MSFullscreenChange'];
        fullscreenEvents.forEach(event => {
            document.addEventListener(event, () => {
                const isFullscreen = !!(document.fullscreenElement || 
                                       document.webkitFullscreenElement || 
                                       document.mozFullScreenElement || 
                                       document.msFullscreenElement);
                
                if (!isFullscreen && this.isFullscreen) {
                    this.warningCount++;
                    this.showWarning(`Peringatan ${this.warningCount}/${this.maxWarnings}: Jangan keluar dari fullscreen mode!`);
                    this.logSecurityEvent('fullscreen_exit', 'Exited fullscreen mode');
                    
                    // Try to re-enter fullscreen immediately
                    setTimeout(() => {
                        requestFullscreen().catch(() => {
                            if (this.warningCount >= this.maxWarnings) {
                                this.autoSubmit('Terlalu banyak keluar dari fullscreen mode');
                            }
                        });
                    }, 500);
                } else if (isFullscreen) {
                    this.isFullscreen = true;
                }
            });
        });
    },
    
    startFullscreenMonitor: function() {
        this.fullscreenCheckInterval = setInterval(() => {
            const isFullscreen = !!(document.fullscreenElement || 
                                   document.webkitFullscreenElement || 
                                   document.mozFullScreenElement || 
                                   document.msFullscreenElement);
            
            if (!isFullscreen && this.isFullscreen) {
                this.warningCount++;
                this.showWarning(`Peringatan: Harap tetap dalam fullscreen mode!`);
                
                // Try to re-enter
                const elem = document.documentElement;
                if (elem.requestFullscreen) {
                    elem.requestFullscreen();
                } else if (elem.webkitRequestFullscreen) {
                    elem.webkitRequestFullscreen();
                } else if (elem.mozRequestFullScreen) {
                    elem.mozRequestFullScreen();
                }
                
                if (this.warningCount >= this.maxWarnings) {
                    this.autoSubmit('Terlalu banyak keluar dari fullscreen mode');
                }
            }
        }, 2000); // Check every 2 seconds
    },
    
    preventNavigation: function() {
        // Prevent back button
        window.addEventListener('popstate', (e) => {
            window.history.pushState(null, null, window.location.href);
            this.showWarning('Navigasi mundur tidak diizinkan!');
            this.logSecurityEvent('back_button_attempt', 'Attempted to use back button');
        });
        
        // Prevent back button on load
        window.history.pushState(null, null, window.location.href);
        
        // Prevent page unload without confirmation
        window.addEventListener('beforeunload', (e) => {
            // Only show warning if exam is not submitted
            const submitForm = document.getElementById('submitExamForm');
            if (!submitForm || !submitForm.dataset.submitted) {
                e.preventDefault();
                e.returnValue = 'Apakah Anda yakin ingin meninggalkan halaman? Semua jawaban yang belum disimpan akan hilang!';
                this.logSecurityEvent('page_unload_attempt', 'Attempted to leave page');
                return e.returnValue;
            }
        });
        
        // Prevent closing window/tab
        window.addEventListener('unload', () => {
            this.logSecurityEvent('window_closed', 'Window/tab closed');
        });
    },
    
    detectDeveloperTools: function() {
        let devtools = {
            open: false,
            orientation: null
        };
        
        const checkDevTools = () => {
            const widthThreshold = window.outerWidth - window.innerWidth > 160;
            const heightThreshold = window.outerHeight - window.innerHeight > 160;
            
            if (widthThreshold || heightThreshold) {
                if (!devtools.open) {
                    devtools.open = true;
                    this.warningCount++;
                    this.showWarning('Developer tools terdeteksi! Harap tutup developer tools.');
                    this.logSecurityEvent('devtools_opened', 'Developer tools opened');
                    
                    if (this.warningCount >= this.maxWarnings) {
                        this.autoSubmit('Developer tools terdeteksi');
                    }
                }
            } else {
                devtools.open = false;
            }
        };
        
        // Check periodically
        setInterval(checkDevTools, 1000);
        
        // Also check on resize
        window.addEventListener('resize', checkDevTools);
    },
    
    detectMultipleWindows: function() {
        // Store reference to this window
        const windowName = 'exam_window_' + Date.now();
        window.name = windowName;
        
        // Check for multiple windows
        setInterval(() => {
            try {
                // Try to access other windows (will fail if same-origin policy blocks)
                if (window.opener && !window.opener.closed) {
                    this.logSecurityEvent('multiple_windows', 'Multiple windows detected');
                    this.showWarning('Multiple windows terdeteksi! Harap tutup window lain.');
                }
            } catch (e) {
                // Cross-origin or other error - ignore
            }
        }, 5000);
    },
    
    detectIdle: function() {
        let idleTime = 0;
        const idleInterval = setInterval(() => {
            idleTime += 30; // 30 seconds
            
            if (idleTime >= 60) { // 1 minute
                this.showWarning('Tidak ada aktivitas terdeteksi. Silakan lanjutkan ujian!');
                this.logSecurityEvent('idle_detected', 'User idle for ' + idleTime + ' seconds');
            }
            
            if (idleTime >= 180) { // 3 minutes
                this.autoSubmit('Terlalu lama tidak aktif');
                clearInterval(idleInterval);
            }
        }, 30000); // Check every 30 seconds
        
        // Reset idle time on activity
        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, () => {
                idleTime = 0;
            });
        });
    },
    
    startPeriodicCheck: function() {
        setInterval(() => {
            this.checkSecurity();
        }, 10000); // Every 10 seconds
    },
    
    checkSecurity: function() {
        // Check if still in fullscreen
        if (!document.fullscreenElement && this.isFullscreen) {
            this.showWarning('Harap tetap dalam fullscreen mode!');
        }
        
        // Send security check to server
        const sesiId = document.querySelector('[data-sesi-id]')?.getAttribute('data-sesi-id');
        if (sesiId) {
            UJAN.ajax('/UJAN/api/security_check.php', {
                sesi_id: sesiId
            }, 'POST', (response) => {
                if (!response.success && response.auto_submit) {
                    this.autoSubmit(response.message || 'Pelanggaran keamanan terdeteksi');
                }
            });
        }
    },
    
    showWarning: function(message) {
        this.warningCount++;
        
        // Show warning toast
        if (typeof showToast === 'function') {
            showToast(message, 'warning');
        } else {
            alert(message);
        }
        
        // Log to server
        this.logSecurityEvent('warning', message);
        
        if (this.warningCount >= this.maxWarnings) {
            this.autoSubmit('Terlalu banyak peringatan');
        }
    },
    
    logSecurityEvent: function(action, description) {
        const sesiId = document.querySelector('[data-sesi-id]')?.getAttribute('data-sesi-id');
        const ujianId = document.querySelector('[data-ujian-id]')?.getAttribute('data-ujian-id');
        
        if (sesiId && ujianId) {
            this.sendSecurityLog(action, {
                description: description,
                tab_switch_count: this.tabSwitchCount,
                warning_count: this.warningCount,
                is_fullscreen: this.isFullscreen,
                timestamp: new Date().toISOString()
            });
        }
    },
    
    sendSecurityLog: function(action, data) {
        const sesiId = document.querySelector('[data-sesi-id]')?.getAttribute('data-sesi-id');
        const ujianId = document.querySelector('[data-ujian-id]')?.getAttribute('data-ujian-id');
        
        if (!sesiId || !ujianId) return;
        
        // Use fetch for better error handling
        const baseUrl = typeof base_url === 'function' ? base_url('') : (window.location.origin + '/UJAN/');
        fetch(baseUrl + 'api/security_check.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                sesi_id: sesiId,
                ujian_id: ujianId,
                action: action,
                description: typeof data === 'string' ? data : JSON.stringify(data)
            })
        }).then(response => response.json())
        .then(result => {
            if (result.auto_submit) {
                this.autoSubmit(result.message || 'Pelanggaran keamanan terdeteksi');
            }
        }).catch(error => {
            console.error('Security log error:', error);
        });
    },
    
    autoSubmit: function(reason) {
        // Stop all monitoring
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
        }
        if (this.fullscreenCheckInterval) {
            clearInterval(this.fullscreenCheckInterval);
        }
        
        // Show final warning
        if (typeof showToast === 'function') {
            showToast('Ujian otomatis diselesaikan: ' + reason, 'error');
        } else {
            alert('Ujian otomatis diselesaikan: ' + reason);
        }
        
        // Log final event
        this.logSecurityEvent('auto_submit', reason);
        
        // Wait a moment for log to be sent, then submit
        setTimeout(() => {
            const submitForm = document.getElementById('submitExamForm');
            if (submitForm) {
                submitForm.dataset.submitted = 'true';
                submitForm.submit();
            } else {
                // Redirect to submit page
                const sesiId = document.querySelector('[data-sesi-id]')?.getAttribute('data-sesi-id');
                if (sesiId) {
                    window.location.href = base_url('siswa/ujian/submit.php?sesi_id=' + sesiId + '&auto=1&reason=' + encodeURIComponent(reason));
                }
            }
        }, 1000);
    },
    
    destroy: function() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
        }
        if (this.fullscreenCheckInterval) {
            clearInterval(this.fullscreenCheckInterval);
        }
        this.isLocked = false;
    }
};

// Initialize on exam page
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('[data-sesi-id]') && document.querySelector('.exam-container')) {
        ExamSecurity.init();
    }
});

