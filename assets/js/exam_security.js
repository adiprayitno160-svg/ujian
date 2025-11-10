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
    initialIP: null,
    initialScreenSize: { width: 0, height: 0 },
    mouseMovements: [],
    keyboardPatterns: [],
    answerTimings: [],
    screenChanges: 0,
    sessionToken: null,
    
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
        this.addWatermark();
        this.detectVirtualMachine();
        this.monitorScreenChanges();
        this.trackMouseMovements();
        this.trackKeyboardPatterns();
        this.trackAnswerTimings();
        this.monitorIPChanges();
        this.detectBrowserExtensions();
        this.detectRemoteDesktop();
        this.validateSessionPeriodically();
        this.detectAutomationTools();
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
        // Store current URL and allowed patterns
        const currentUrl = window.location.href;
        const examBaseUrl = currentUrl.split('?')[0]; // URL tanpa query string
        this.allowedSubmitUrl = null; // Will be set when submit is clicked
        this.isSubmitting = false;
        this.allowNavigation = false; // Flag to allow navigation only when submitting
        
        // Prevent back button
        window.addEventListener('popstate', (e) => {
            if (!this.isSubmitting) {
                window.history.pushState(null, null, window.location.href);
                this.showWarning('Navigasi mundur tidak diizinkan!');
                this.logSecurityEvent('back_button_attempt', 'Attempted to use back button');
                // Auto-submit if too many attempts
                this.warningCount++;
                if (this.warningCount >= this.maxWarnings) {
                    this.autoSubmit('Terlalu banyak mencoba navigasi mundur');
                }
            }
        });
        
        // Prevent back button on load - push state to history
        window.history.pushState(null, null, window.location.href);
        
        // Monitor URL changes (less aggressive - only check on actual navigation)
        let lastUrl = window.location.href;
        let urlCheckInterval = null;
        
        // Store reference to check interval so we can clear it
        this.urlCheckInterval = urlCheckInterval;
        
        // Use a more reliable method: override pushState and replaceState
        const originalPushState = window.history.pushState;
        const originalReplaceState = window.history.replaceState;
        const self = this;
        
        // Store original methods for restoration
        window.history._originalPushState = originalPushState;
        window.history._originalReplaceState = originalReplaceState;
        
        window.history.pushState = function() {
            if (!self.isSubmitting && !self.allowNavigation) {
                const newUrl = arguments[2];
                if (newUrl && typeof newUrl === 'string') {
                    const isQuestionNav = newUrl.includes('siswa/ujian/take.php') || 
                                         newUrl.includes('siswa-ujian-take') ||
                                         newUrl.includes('take.php') ||
                                         (newUrl.includes('?id=') && newUrl.includes('&soal='));
                    const isSubmitNav = newUrl.includes('siswa/ujian/submit.php') || 
                                       newUrl.includes('siswa-ujian-submit') ||
                                       newUrl.includes('submit.php');
                    
                    if (!isQuestionNav && !isSubmitNav) {
                        self.warningCount++;
                        self.showWarning('Navigasi ke halaman lain tidak diizinkan!');
                        self.logSecurityEvent('unauthorized_navigation', `Attempted to navigate to: ${newUrl}`);
                        return; // Block the navigation
                    }
                }
            }
            return originalPushState.apply(window.history, arguments);
        };
        
        window.history.replaceState = function() {
            // Allow replaceState (used for question navigation)
            return originalReplaceState.apply(window.history, arguments);
        };
        
        // Prevent all link clicks that would navigate away
        document.addEventListener('click', (e) => {
            const target = e.target.closest('a');
            if (target && target.href) {
                const href = target.href;
                const isQuestionNav = href.includes('siswa/ujian/take.php') || 
                                     href.includes('siswa-ujian-take') ||
                                     href.includes('take.php?');
                const isSubmitNav = href.includes('siswa/ujian/submit.php') || 
                                   href.includes('siswa-ujian-submit') ||
                                   href.includes('submit.php');
                
                // Allow only question navigation within exam or submit (when allowed)
                if (!isQuestionNav && (!isSubmitNav || !this.isSubmitting)) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.showWarning('Tidak dapat membuka link saat sedang ujian!');
                    this.logSecurityEvent('link_click_blocked', `Blocked link: ${href}`);
                    return false;
                }
            }
        }, true);
        
        // Prevent form submission to other pages
        document.addEventListener('submit', (e) => {
            const form = e.target;
            const action = form.action || '';
            const isSubmitAction = action.includes('siswa/ujian/submit.php') || 
                                  action.includes('siswa-ujian-submit') ||
                                  action.includes('submit.php');
            const isSaveAnswer = action.includes('save_answer.php') || 
                                action.includes('api/save_answer');
            
            if (!isSubmitAction && !isSaveAnswer && !this.isSubmitting) {
                e.preventDefault();
                e.stopPropagation();
                this.showWarning('Tidak dapat submit form ke halaman lain saat sedang ujian!');
                this.logSecurityEvent('form_submit_blocked', `Blocked form action: ${action}`);
                return false;
            }
        }, true);
        
        // Prevent page unload without confirmation (except when submitting)
        window.addEventListener('beforeunload', (e) => {
            if (!this.isSubmitting && !this.allowNavigation) {
                e.preventDefault();
                e.returnValue = 'Apakah Anda yakin ingin meninggalkan halaman? Semua jawaban yang belum disimpan akan hilang dan ujian akan berakhir!';
                this.logSecurityEvent('page_unload_attempt', 'Attempted to leave page');
                return e.returnValue;
            }
        });
        
        // Prevent closing window/tab
        window.addEventListener('unload', () => {
            if (!this.isSubmitting) {
                this.logSecurityEvent('window_closed', 'Window/tab closed');
            }
        });
        
        // Intercept window.location.href assignment using a wrapper
        // Note: We can't directly override window.location, but we can catch assignments
        // The beforeunload and link/form handlers above should catch most navigation attempts
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
        // Mark as submitting to allow navigation
        this.isSubmitting = true;
        this.allowNavigation = true;
        
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
                // Redirect to submit page - allow this navigation
                const sesiId = document.querySelector('[data-sesi-id]')?.getAttribute('data-sesi-id');
                if (sesiId) {
                    const baseUrl = typeof base_url === 'function' ? base_url('') : (window.location.origin + '/UJAN/');
                    window.location.href = baseUrl + 'siswa/ujian/submit.php?sesi_id=' + sesiId + '&auto=1&reason=' + encodeURIComponent(reason);
                }
            }
        }, 1000);
    },
    
    allowSubmit: function() {
        // Allow navigation to submit page
        this.isSubmitting = true;
        this.allowNavigation = true;
        this.allowedSubmitUrl = window.location.origin + '/UJAN/siswa/ujian/submit.php';
    },
    
    addWatermark: function() {
        // Get student name from page
        const studentName = document.querySelector('[data-student-name]')?.getAttribute('data-student-name') || 
                           document.querySelector('[data-user-name]')?.getAttribute('data-user-name') || 
                           'Siswa';
        const studentId = document.querySelector('[data-student-id]')?.getAttribute('data-student-id') || 
                         document.querySelector('[data-user-id]')?.getAttribute('data-user-id') || '';
        
        // Create watermark element
        const watermark = document.createElement('div');
        watermark.id = 'exam-watermark';
        watermark.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9999;
            opacity: 0.05;
            font-size: 48px;
            font-weight: bold;
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            transform: rotate(-45deg);
            user-select: none;
            white-space: nowrap;
        `;
        watermark.textContent = `${studentName} - ${studentId}`;
        document.body.appendChild(watermark);
        
        // Add multiple watermarks across the page
        for (let i = 0; i < 20; i++) {
            const wm = watermark.cloneNode(true);
            wm.style.top = `${(i * 10)}%`;
            wm.style.left = `${(i % 5) * 20}%`;
            wm.style.transform = `rotate(-45deg) translateX(${i * 50}px)`;
            document.body.appendChild(wm);
        }
    },
    
    detectVirtualMachine: function() {
        // Check for VM indicators
        const checkVM = () => {
            const vmIndicators = [];
            
            // Check screen resolution (VMs often have standard resolutions)
            const width = screen.width;
            const height = screen.height;
            const commonVMResolutions = [
                {w: 1024, h: 768}, {w: 1280, h: 720}, {w: 1920, h: 1080},
                {w: 800, h: 600}, {w: 1366, h: 768}
            ];
            
            if (commonVMResolutions.some(r => r.w === width && r.h === height)) {
                vmIndicators.push('Common VM resolution detected');
            }
            
            // Check for VM user agents
            const ua = navigator.userAgent.toLowerCase();
            if (ua.includes('virtualbox') || ua.includes('vmware') || ua.includes('qemu')) {
                vmIndicators.push('VM user agent detected');
            }
            
            // Check hardware concurrency (VMs often have limited cores)
            if (navigator.hardwareConcurrency <= 2) {
                vmIndicators.push('Limited CPU cores (possible VM)');
            }
            
            // Check device memory (VMs often have limited RAM)
            if (navigator.deviceMemory && navigator.deviceMemory <= 4) {
                vmIndicators.push('Limited device memory (possible VM)');
            }
            
            if (vmIndicators.length > 0) {
                this.logSecurityEvent('vm_detected', vmIndicators.join(', '));
                this.showWarning('Virtual machine terdeteksi. Penggunaan VM tidak diizinkan untuk ujian.');
            }
        };
        
        // Check immediately and periodically
        checkVM();
        setInterval(checkVM, 30000); // Check every 30 seconds
    },
    
    monitorScreenChanges: function() {
        // Store initial screen size
        this.initialScreenSize = {
            width: window.innerWidth,
            height: window.innerHeight
        };
        
        // Monitor screen size changes
        window.addEventListener('resize', () => {
            const currentSize = {
                width: window.innerWidth,
                height: window.innerHeight
            };
            
            // Check if size changed significantly
            const widthDiff = Math.abs(currentSize.width - this.initialScreenSize.width);
            const heightDiff = Math.abs(currentSize.height - this.initialScreenSize.height);
            
            if (widthDiff > 100 || heightDiff > 100) {
                this.screenChanges++;
                this.logSecurityEvent('screen_resize', `Size changed: ${currentSize.width}x${currentSize.height}`);
                
                if (this.screenChanges >= 3) {
                    this.showWarning('Perubahan ukuran layar terdeteksi berulang kali!');
                    this.warningCount++;
                }
            }
        });
        
        // Monitor orientation changes
        window.addEventListener('orientationchange', () => {
            this.logSecurityEvent('orientation_change', `Orientation: ${screen.orientation?.angle || 'unknown'}`);
            this.showWarning('Perubahan orientasi layar terdeteksi!');
        });
    },
    
    trackMouseMovements: function() {
        let movementCount = 0;
        let lastMovementTime = Date.now();
        const suspiciousPatterns = [];
        
        document.addEventListener('mousemove', (e) => {
            movementCount++;
            const now = Date.now();
            const timeSinceLastMove = now - lastMovementTime;
            
            // Track mouse position
            this.mouseMovements.push({
                x: e.clientX,
                y: e.clientY,
                time: now,
                timeSinceLastMove: timeSinceLastMove
            });
            
            // Keep only last 100 movements
            if (this.mouseMovements.length > 100) {
                this.mouseMovements.shift();
            }
            
            // Detect suspicious patterns
            if (this.mouseMovements.length >= 5) {
                const recent = this.mouseMovements.slice(-5);
                const distances = [];
                for (let i = 1; i < recent.length; i++) {
                    const dist = Math.sqrt(
                        Math.pow(recent[i].x - recent[i-1].x, 2) +
                        Math.pow(recent[i].y - recent[i-1].y, 2)
                    );
                    distances.push(dist);
                }
                
                const avgDist = distances.reduce((a, b) => a + b, 0) / distances.length;
                const variance = distances.reduce((sum, d) => sum + Math.pow(d - avgDist, 2), 0) / distances.length;
                
                if (variance < 10 && avgDist > 50) {
                    suspiciousPatterns.push('uniform_mouse_movement');
                }
            }
            
            if (timeSinceLastMove > 0) {
                const speed = Math.sqrt(Math.pow(e.movementX, 2) + Math.pow(e.movementY, 2)) / timeSinceLastMove;
                if (speed > 10) {
                    suspiciousPatterns.push('too_fast_movement');
                }
            }
            
            lastMovementTime = now;
            
            if (movementCount % 50 === 0 && suspiciousPatterns.length > 0) {
                this.logSecurityEvent('suspicious_mouse_pattern', suspiciousPatterns.join(', '));
                suspiciousPatterns.length = 0;
            }
        });
    },
    
    trackKeyboardPatterns: function() {
        let keyPressCount = 0;
        let lastKeyTime = Date.now();
        const keyTimings = [];
        
        document.addEventListener('keydown', (e) => {
            keyPressCount++;
            const now = Date.now();
            const timeSinceLastKey = now - lastKeyTime;
            
            keyTimings.push({
                key: e.key,
                code: e.code,
                time: now,
                timeSinceLastKey: timeSinceLastKey
            });
            
            if (keyTimings.length > 50) {
                keyTimings.shift();
            }
            
            if (keyTimings.length >= 10) {
                const recent = keyTimings.slice(-10);
                const intervals = recent.slice(1).map((k, i) => k.time - recent[i].time);
                const avgInterval = intervals.reduce((a, b) => a + b, 0) / intervals.length;
                const variance = intervals.reduce((sum, i) => sum + Math.pow(i - avgInterval, 2), 0) / intervals.length;
                
                if (variance < 100 && avgInterval < 200) {
                    this.logSecurityEvent('suspicious_keyboard_pattern', 'Uniform typing speed detected');
                    this.warningCount++;
                }
            }
            
            if (timeSinceLastKey > 0 && timeSinceLastKey < 50) {
                this.logSecurityEvent('suspicious_keyboard_pattern', 'Too fast typing detected');
            }
            
            lastKeyTime = now;
        });
        
        this.keyboardPatterns = keyTimings;
    },
    
    trackAnswerTimings: function() {
        const originalSaveAnswer = window.saveAnswer;
        if (typeof originalSaveAnswer === 'function') {
            window.saveAnswer = () => {
                const soalId = document.querySelector('[data-soal-id]')?.getAttribute('data-soal-id');
                const timing = {
                    soal_id: soalId,
                    timestamp: Date.now(),
                    time_since_page_load: Date.now() - performance.timing.navigationStart
                };
                
                this.answerTimings.push(timing);
                
                if (this.answerTimings.length >= 2) {
                    const lastTwo = this.answerTimings.slice(-2);
                    const timeBetween = lastTwo[1].timestamp - lastTwo[0].timestamp;
                    
                    if (timeBetween < 5000) {
                        this.logSecurityEvent('suspicious_answer_timing', `Answer submitted too quickly: ${timeBetween}ms`);
                    }
                }
                
                return originalSaveAnswer.apply(this, arguments);
            };
        }
    },
    
    monitorIPChanges: function() {
        window.addEventListener('online', () => {
            this.logSecurityEvent('network_change', 'Network reconnected');
            this.showWarning('Perubahan koneksi jaringan terdeteksi!');
        });
        
        window.addEventListener('offline', () => {
            this.logSecurityEvent('network_change', 'Network disconnected');
            this.showWarning('Koneksi jaringan terputus!');
        });
        
        if ('connection' in navigator) {
            const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            if (connection) {
                connection.addEventListener('change', () => {
                    this.logSecurityEvent('network_change', `Connection type: ${connection.effectiveType}`);
                });
            }
        }
        
        setInterval(() => {
            const sesiId = document.querySelector('[data-sesi-id]')?.getAttribute('data-sesi-id');
            if (sesiId) {
                const baseUrl = typeof base_url === 'function' ? base_url('') : (window.location.origin + '/UJAN/');
                fetch(baseUrl + 'api/security_check.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        sesi_id: sesiId,
                        action: 'check_ip'
                    })
                }).then(r => r.json()).then(data => {
                    if (data.ip_changed) {
                        this.logSecurityEvent('ip_change', 'IP address changed during exam');
                        this.autoSubmit('Perubahan IP address terdeteksi');
                    }
                }).catch(() => {});
            }
        }, 60000);
    },
    
    detectBrowserExtensions: function() {
        const suspiciousExtensions = [];
        
        if (window.grammarly !== undefined) {
            suspiciousExtensions.push('Grammarly');
        }
        
        if (window._gaq !== undefined && document.querySelector('[data-lastpass-root]')) {
            suspiciousExtensions.push('LastPass');
        }
        
        if (window.google !== undefined && window.google.translate) {
            suspiciousExtensions.push('Google Translate');
        }
        
        if (suspiciousExtensions.length > 0) {
            this.logSecurityEvent('browser_extension_detected', suspiciousExtensions.join(', '));
            this.showWarning('Extension browser mencurigakan terdeteksi: ' + suspiciousExtensions.join(', '));
        }
        
        const observer = new MutationObserver(() => {
            const suspiciousElements = document.querySelectorAll('iframe[src*="extension"], script[src*="extension"]');
            if (suspiciousElements.length > 0) {
                this.logSecurityEvent('suspicious_element_detected', 'Extension-related elements found');
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    },
    
    detectRemoteDesktop: function() {
        const rdIndicators = [];
        
        const screenProps = {
            width: screen.width,
            height: screen.height,
            colorDepth: screen.colorDepth
        };
        
        if (screenProps.colorDepth < 24) {
            rdIndicators.push('Low color depth (possible RD)');
        }
        
        const ua = navigator.userAgent.toLowerCase();
        if (ua.includes('remote') || ua.includes('rdp') || ua.includes('vnc')) {
            rdIndicators.push('Remote desktop user agent');
        }
        
        if (screenProps.width > 3840 || screenProps.height > 2160) {
            rdIndicators.push('Unusually large screen (possible RD)');
        }
        
        if (rdIndicators.length > 0) {
            this.logSecurityEvent('remote_desktop_detected', rdIndicators.join(', '));
            this.showWarning('Remote desktop terdeteksi. Penggunaan remote desktop tidak diizinkan.');
        }
    },
    
    validateSessionPeriodically: function() {
        setInterval(() => {
            const sesiId = document.querySelector('[data-sesi-id]')?.getAttribute('data-sesi-id');
            if (!sesiId) return;
            
            const baseUrl = typeof base_url === 'function' ? base_url('') : (window.location.origin + '/UJAN/');
            fetch(baseUrl + 'api/security_check.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    sesi_id: sesiId,
                    action: 'validate_session'
                })
            }).then(r => r.json()).then(data => {
                if (!data.valid) {
                    this.logSecurityEvent('session_invalid', 'Session validation failed');
                    this.autoSubmit('Session tidak valid');
                }
            }).catch(() => {
                this.logSecurityEvent('session_validation_error', 'Failed to validate session');
            });
        }, 30000);
    },
    
    detectAutomationTools: function() {
        const automationIndicators = [];
        
        if (navigator.webdriver === true) {
            automationIndicators.push('WebDriver detected (Selenium/automation)');
        }
        
        if (window.navigator.webdriver || window.__puppeteer_evaluation__) {
            automationIndicators.push('Puppeteer detected');
        }
        
        if (window._phantom || window.callPhantom) {
            automationIndicators.push('PhantomJS detected');
        }
        
        if (window.playwright) {
            automationIndicators.push('Playwright detected');
        }
        
        const props = Object.getOwnPropertyNames(navigator);
        if (props.includes('webdriver') && navigator.webdriver) {
            automationIndicators.push('Automation tool detected');
        }
        
        if (automationIndicators.length > 0) {
            this.logSecurityEvent('automation_tool_detected', automationIndicators.join(', '));
            this.autoSubmit('Automation tool terdeteksi. Penggunaan tool otomatisasi tidak diizinkan.');
        }
        
        setInterval(() => {
            if (navigator.webdriver === true) {
                this.logSecurityEvent('automation_tool_detected', 'WebDriver still active');
                this.warningCount++;
                if (this.warningCount >= this.maxWarnings) {
                    this.autoSubmit('Automation tool terdeteksi berulang kali');
                }
            }
        }, 10000);
    },
    
    destroy: function() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
        }
        if (this.fullscreenCheckInterval) {
            clearInterval(this.fullscreenCheckInterval);
        }
        if (this.urlCheckInterval) {
            clearInterval(this.urlCheckInterval);
        }
        // Restore original history methods
        if (window.history._originalPushState) {
            window.history.pushState = window.history._originalPushState;
        }
        if (window.history._originalReplaceState) {
            window.history.replaceState = window.history._originalReplaceState;
        }
        this.isLocked = false;
    }
};

// Initialize on exam page
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on exam page (look for exam-wrapper or data-sesi-id)
    const examWrapper = document.querySelector('.exam-wrapper') || document.querySelector('[data-sesi-id]');
    if (examWrapper) {
        ExamSecurity.init();
        
        // Override submitExam function if it exists to allow navigation
        if (typeof window.submitExam === 'function') {
            const originalSubmitExam = window.submitExam;
            window.submitExam = function() {
                ExamSecurity.allowSubmit();
                return originalSubmitExam.apply(this, arguments);
            };
        }
        
        // Override goToSoal to ensure it's allowed
        if (typeof window.goToSoal === 'function') {
            const originalGoToSoal = window.goToSoal;
            window.goToSoal = function(num) {
                // Allow navigation within exam (question navigation)
                ExamSecurity.allowNavigation = true;
                const result = originalGoToSoal.apply(this, arguments);
                // Reset after a delay
                setTimeout(() => {
                    if (!ExamSecurity.isSubmitting) {
                        ExamSecurity.allowNavigation = false;
                    }
                }, 100);
                return result;
            };
        }
    }
});

