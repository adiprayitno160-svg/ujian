/**
 * Exam Security Functions
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Enhanced with Anti Tab Switch and Browser Lock
 */

const ExamSecurity = {
    tabSwitchCount: 0,
    warningCount: 0,
    maxWarnings: 10, // Increased from 3 to 10 - more lenient
    maxTabSwitches: 10, // Increased from 5 to 10 - much more lenient
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
    isDialogShowing: false,
    pageLoadTime: Date.now(), // Track when page was loaded
    gracePeriod: 20000, // Increased from 10 to 20 seconds grace period after page load
    
    init: function() {
        // Initialize page load time for grace period
        this.pageLoadTime = Date.now();
        
        // DISABLED - All anti-fraud functions disabled
        // this.lockBrowser();
        // this.detectTabSwitch();
        // this.preventCopyPaste();
        // this.detectScreenshot();
        // this.enableFullscreen(); // DISABLED - Fullscreen mode tidak diwajibkan
        // this.preventNavigation();
        // this.detectIdle();
        // this.detectDeveloperTools();
        // this.detectMultipleWindows();
        // this.startPeriodicCheck();
        // this.startFullscreenMonitor();
        this.addWatermark();
        // this.monitorScreenChanges();
        // this.trackMouseMovements();
        // this.trackKeyboardPatterns();
        // this.trackAnswerTimings();
        // this.monitorIPChanges();
        // this.detectBrowserExtensions();
        // this.detectRemoteDesktop();
        // this.validateSessionPeriodically();
        // this.detectAutomationTools();
        
        // Mobile-specific anti-fraud functions (Android + iOS)
        this.initMobileSecurity();
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
        
        let isFullscreenTransition = false;
        let ignoreNextVisibilityChange = false;
        let visibilityChangeTimeout = null;
        let consecutiveVisibilityChanges = 0; // Track rapid consecutive changes
        let lastVisibilityState = !document.hidden; // Track previous state
        
        // Track fullscreen changes to ignore false positives
        document.addEventListener('fullscreenchange', () => {
            isFullscreenTransition = true;
            ignoreNextVisibilityChange = true;
            setTimeout(() => {
                isFullscreenTransition = false;
                ignoreNextVisibilityChange = false;
            }, 5000); // Increased to 5 seconds after fullscreen change for more leniency
        });
        
        // Track if dialog/alert is showing (to ignore visibility changes from dialogs)
        const self = this;
        let dialogTimeout = null;
        
        // Override alert/confirm to track when they're shown
        const originalAlert = window.alert;
        const originalConfirm = window.confirm;
        
        window.alert = function(...args) {
            self.isDialogShowing = true;
            if (dialogTimeout) clearTimeout(dialogTimeout);
            dialogTimeout = setTimeout(() => {
                self.isDialogShowing = false;
            }, 5000); // Increased to 5 seconds for dialogs - more lenient
            return originalAlert.apply(window, args);
        };
        
        window.confirm = function(...args) {
            self.isDialogShowing = true;
            if (dialogTimeout) clearTimeout(dialogTimeout);
            dialogTimeout = setTimeout(() => {
                self.isDialogShowing = false;
            }, 5000); // Increased to 5 seconds for dialogs - more lenient
            return originalConfirm.apply(window, args);
        };
        
        document.addEventListener('visibilitychange', () => {
            const now = Date.now();
            const timeSinceLastChange = now - lastVisibilityChange;
            const timeSincePageLoad = now - this.pageLoadTime;
            const currentState = !document.hidden;
            
            // Ignore during grace period (first 10 seconds after page load)
            if (timeSincePageLoad < this.gracePeriod) {
                lastVisibilityChange = now;
                lastVisibilityState = currentState;
                return;
            }
            
            // Ignore rapid visibility changes (less than 1000ms) - likely false positives from system events
            // Increased from 500ms to 1000ms for more leniency
            if (timeSinceLastChange < 1000) {
                consecutiveVisibilityChanges++;
                // If we have too many rapid changes, it's likely a system event, not user action
                // Increased threshold from 3 to 5
                if (consecutiveVisibilityChanges > 5) {
                    // Reset counter and ignore this change
                    consecutiveVisibilityChanges = 0;
                    lastVisibilityChange = now;
                    lastVisibilityState = currentState;
                    return;
                }
            } else {
                // Reset counter if changes are not rapid
                consecutiveVisibilityChanges = 0;
            }
            
            // Ignore if it's a fullscreen transition
            if (ignoreNextVisibilityChange || isFullscreenTransition) {
                lastVisibilityChange = now;
                lastVisibilityState = currentState;
                return;
            }
            
            // Ignore if dialog/alert is showing (they cause visibility changes)
            if (this.isDialogShowing) {
                lastVisibilityChange = now;
                lastVisibilityState = currentState;
                return;
            }
            
            // Ignore if state hasn't actually changed (browser quirk)
            if (currentState === lastVisibilityState) {
                return;
            }
            
            if (document.hidden) {
                // Clear any pending timeout
                if (visibilityChangeTimeout) {
                    clearTimeout(visibilityChangeTimeout);
                }
                
                // Only count if hidden for more than 5000ms (5 seconds) to filter out dialogs, notifications, brief flashes
                // Increased from 2 seconds to 5 seconds for much more leniency
                visibilityChangeTimeout = setTimeout(() => {
                    if (document.hidden && !this.isDialogShowing) {
                        // Double check - make sure it's still hidden and no dialog
                        const actualTimeHidden = Date.now() - lastVisibilityChange;
                        
                        // Only count if actually hidden for significant time (> 5000ms = 5 seconds)
                        // This ensures we only catch real tab switches, not brief system events
                        if (actualTimeHidden > 5000) {
                            this.tabSwitchCount++;
                            
                            this.logSecurityEvent('tab_switch', `Tab/window switched (Count: ${this.tabSwitchCount}, Duration: ${actualTimeHidden}ms)`);
                            
                            // RESET ALL ANSWERS when tab switch detected
                            this.resetAllAnswers(`Tab switch terdeteksi (${this.tabSwitchCount}x)`);
                            
                            // Show warning
                            this.showWarning(`Peringatan ${this.tabSwitchCount}/${this.maxTabSwitches}: Jangan beralih tab atau window! Semua jawaban telah di-reset.`);
                            
                            // Redirect to login if too many switches
                            if (this.tabSwitchCount >= this.maxTabSwitches) {
                                // Tab switch is fraud
                                this.redirectToLogin(`Terlalu banyak tab switch (${this.tabSwitchCount}x)`, 'tab_switch', true);
                                return;
                            }
                            
                            // Log to server
                            this.sendSecurityLog('tab_switch', {
                                count: this.tabSwitchCount,
                                time_hidden: actualTimeHidden
                            });
                        }
                    }
                }, 5000); // Wait 5 seconds before counting (increased from 2 seconds for more leniency)
            } else {
                // Tab is visible again - clear timeout
                if (visibilityChangeTimeout) {
                    clearTimeout(visibilityChangeTimeout);
                    visibilityChangeTimeout = null;
                }
                
                const timeHidden = now - lastVisibilityChange;
                if (timeHidden > 5000) { // More than 5 seconds (increased from 2 seconds)
                    this.logSecurityEvent('tab_return', `Returned to tab after ${timeHidden}ms`);
                }
            }
            
            lastVisibilityChange = now;
            lastVisibilityState = currentState;
        });
        
        // Window blur detection disabled - too many false positives
        // Only log for tracking, but don't count as fraud
        window.addEventListener('blur', () => {
            // Just log, don't count as warning or fraud
            this.logSecurityEvent('window_blur', 'Window lost focus (logged only)');
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
        // RELAXED - Fullscreen is optional, only suggestion, no enforcement
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
        
        // Try to enter fullscreen (optional, no error if fails)
        requestFullscreen().then(() => {
            this.isFullscreen = true;
            console.log('Fullscreen mode enabled');
        }).catch((err) => {
            // Just log, no warning - fullscreen is optional
            console.log('Fullscreen not available (optional):', err);
        });
        
        // Monitor fullscreen changes (informational only, no enforcement)
        const fullscreenEvents = ['fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'MSFullscreenChange'];
        fullscreenEvents.forEach(event => {
            document.addEventListener(event, () => {
                const isFullscreen = !!(document.fullscreenElement || 
                                       document.webkitFullscreenElement || 
                                       document.mozFullScreenElement || 
                                       document.msFullscreenElement);
                
                if (isFullscreen) {
                    this.isFullscreen = true;
                } else {
                    this.isFullscreen = false;
                }
                // No warnings, no redirects - just track state
            });
        });
    },
    
    startFullscreenMonitor: function() {
        // DISABLED - Fullscreen monitoring disabled (relaxed mode)
        // No enforcement, no warnings, no redirects
        return;
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
                // Redirect to login if too many attempts
                this.warningCount++;
                if (this.warningCount >= this.maxWarnings) {
                    // Navigation back is fraud
                    this.redirectToLogin('Terlalu banyak mencoba navigasi mundur', 'navigation_back', true);
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
                        // Developer tools is fraud
                        this.redirectToLogin('Developer tools terdeteksi', 'developer_tools', true);
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
            
            if (idleTime >= 120) { // 2 minutes (increased from 1 minute)
                this.showWarning('Tidak ada aktivitas terdeteksi. Silakan lanjutkan ujian!');
                this.logSecurityEvent('idle_detected', 'User idle for ' + idleTime + ' seconds');
            }
            
            if (idleTime >= 300) { // 5 minutes (increased from 3 minutes) - much more lenient
                // Idle timeout - treat as normal disruption (not fraud)
                this.redirectToLogin('Terlalu lama tidak aktif', 'idle_timeout', false);
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
        // Add delay before first check to avoid false positive on page load
        // Wait 30 seconds before starting periodic checks (increased from 15 seconds)
        setTimeout(() => {
            this.checkSecurity();
            
            // Then check every 20 seconds (increased from 10 seconds for more leniency)
            setInterval(() => {
                this.checkSecurity();
            }, 20000);
        }, 30000); // 30 seconds grace period (increased from 15 seconds)
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
                if (!response.success) {
                    // Only trigger fraud if it's a real fraud detection, not just validation error
                    if (response.fraud && response.requires_logout) {
                        // Fraud detected - force logout
                        this.redirectToLogin(response.message || 'Fraud terdeteksi', 'fraud_detection', true);
                    } else if (response.auto_submit && !response.rate_limit) {
                        // Legacy auto-submit (treat as fraud) - but ignore rate limit errors
                        this.redirectToLogin(response.message || 'Pelanggaran keamanan terdeteksi', 'server_validation', true);
                    }
                    // Ignore rate_limit errors - they're not fraud
                }
            }, (error) => {
                // Ignore network errors - don't treat as fraud
                console.error('Security check error:', error);
            });
        }
    },
    
    showWarning: function(message) {
        this.warningCount++;
        
        // Show warning toast (prefer toast over alert to avoid visibility change)
        if (typeof showToast === 'function') {
            showToast(message, 'warning');
        } else {
            // Use console.warn instead of alert to avoid triggering visibility change
            console.warn('Warning:', message);
            // Only use alert as last resort, and mark dialog as showing
            if (typeof window.alert === 'function') {
                // Set flag before alert
                if (this.isDialogShowing !== undefined) {
                    this.isDialogShowing = true;
                    setTimeout(() => {
                        this.isDialogShowing = false;
                    }, 2000);
                }
                alert(message);
            }
        }
        
        // Log to server
        this.logSecurityEvent('warning', message);
        
        if (this.warningCount >= this.maxWarnings) {
            // Max warnings reached is fraud
            this.redirectToLogin('Terlalu banyak peringatan', 'max_warnings', true);
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
                this.redirectToLogin(result.message || 'Pelanggaran keamanan terdeteksi', 'server_check');
            }
        }).catch(error => {
            console.error('Security log error:', error);
        });
    },
    
    /**
     * Redirect to login page due to security violation (FRAUD)
     * This is for fraud detection - answers reset, must relogin, time continues
     */
    redirectToLogin: function(reason, violationType = 'security_violation', isFraud = true) {
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
        
        // Get session info
        const sesiId = document.querySelector('[data-sesi-id]')?.getAttribute('data-sesi-id') || '';
        const ujianId = document.querySelector('[data-ujian-id]')?.getAttribute('data-ujian-id') || '';
        const baseUrl = typeof base_url === 'function' ? base_url('') : (window.location.origin + '/UJAN/');
        
        if (isFraud) {
            // FRAUD: Mark as fraud, reset answers, force logout
            // Show warning message
            const message = 'Fraud terdeteksi: ' + reason + '. Jawaban sudah di-reset. Anda harus login ulang. Waktu ujian terus berjalan.';
            if (typeof showToast === 'function') {
                showToast(message, 'error');
            } else {
                alert(message);
            }
            
            // Log final event
            this.logSecurityEvent('fraud_detected', reason);
            
            // Don't save answers for fraud - they will be reset
            // Mark as fraud and reset answers
            const formData = new FormData();
            formData.append('action', 'mark_fraud');
            formData.append('reason', reason);
            if (sesiId) formData.append('sesi_id', sesiId);
            if (ujianId) formData.append('ujian_id', ujianId);
            
            fetch(baseUrl + 'api/fraud_detection.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Force logout and redirect
                window.location.href = baseUrl + 'siswa/login.php?fraud=1&sesi_id=' + sesiId + '&reason=' + encodeURIComponent(reason);
            })
            .catch(error => {
                console.error('Fraud detection API error:', error);
                // Fallback: redirect to login directly
                window.location.href = baseUrl + 'siswa/login.php?fraud=1&sesi_id=' + sesiId + '&reason=' + encodeURIComponent(reason);
            });
        } else {
            // NORMAL DISRUPTION: Lock answers, allow resume with token
            const message = 'Gangguan terdeteksi: ' + reason + '. Jawaban sudah dikunci di server. Silakan login ulang dan minta token baru untuk melanjutkan.';
            if (typeof showToast === 'function') {
                showToast(message, 'warning');
            } else {
                alert(message);
            }
            
            // Log final event
            this.logSecurityEvent('normal_disruption', reason);
            
            // Save all answers before logout (auto-save)
            this.saveAllAnswers().then(() => {
                // Lock answers for normal disruption
                const formData = new FormData();
                formData.append('action', 'lock_answers');
                formData.append('reason', reason);
                if (sesiId) formData.append('sesi_id', sesiId);
                if (ujianId) formData.append('ujian_id', ujianId);
                
                fetch(baseUrl + 'api/fraud_detection.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // Redirect to login with disruption flag
                    window.location.href = baseUrl + 'siswa/login.php?disruption=1&sesi_id=' + sesiId + '&reason=' + encodeURIComponent(reason);
                })
                .catch(error => {
                    console.error('Lock answers API error:', error);
                    // Fallback: redirect to login directly
                    window.location.href = baseUrl + 'siswa/login.php?disruption=1&sesi_id=' + sesiId + '&reason=' + encodeURIComponent(reason);
                });
            });
        }
    },
    
    /**
     * Save all answers before logout
     */
    saveAllAnswers: function() {
        return new Promise((resolve) => {
            const sesiId = document.querySelector('[data-sesi-id]')?.getAttribute('data-sesi-id') || '';
            const ujianId = document.querySelector('[data-ujian-id]')?.getAttribute('data-ujian-id') || '';
            
            if (!sesiId || !ujianId) {
                resolve();
                return;
            }
            
            // Collect all answers from the form
            const answers = {};
            const form = document.getElementById('examForm');
            if (form) {
                const inputs = form.querySelectorAll('input[type="radio"]:checked, input[type="checkbox"]:checked, textarea, input[type="text"]');
                inputs.forEach(input => {
                    const soalId = input.name.match(/jawaban\[(\d+)\]/);
                    if (soalId) {
                        const id = soalId[1];
                        if (input.type === 'checkbox') {
                            if (!answers[id]) answers[id] = [];
                            answers[id].push(input.value);
                        } else {
                            answers[id] = input.value;
                        }
                    }
                });
            }
            
            // Send to auto-save API
            const baseUrl = typeof base_url === 'function' ? base_url('') : (window.location.origin + '/UJAN/');
            const formData = new FormData();
            formData.append('sesi_id', sesiId);
            formData.append('ujian_id', ujianId);
            formData.append('answers', JSON.stringify(answers));
            
            fetch(baseUrl + 'api/auto_save.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                resolve();
            })
            .catch(error => {
                console.error('Auto-save error:', error);
                resolve(); // Continue even if save fails
            });
        });
    },
    
    /**
     * Auto submit exam (for normal completion, not security violations)
     * This is kept for backward compatibility and normal exam completion
     */
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
                
                // More lenient - only warn after many changes
                if (this.screenChanges >= 10) {
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
        
        // Delay first IP check to avoid false positive on page load
        setTimeout(() => {
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
                            // IP change is fraud
                            this.redirectToLogin('Perubahan IP address terdeteksi', 'ip_change', true);
                        }
                    }).catch(() => {});
                }
            }, 60000);
        }, 30000); // Wait 30 seconds before first IP check
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
        // Delay first session validation to avoid false positive on page load
        setTimeout(() => {
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
                        this.redirectToLogin('Session tidak valid', 'session_invalid');
                    }
                }).catch(() => {
                    // Ignore network errors - don't treat as fraud
                    // this.logSecurityEvent('session_validation_error', 'Failed to validate session');
                });
            }, 30000);
        }, 20000); // Wait 20 seconds before first session validation
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
            // Automation tool is fraud
            this.redirectToLogin('Automation tool terdeteksi. Penggunaan tool otomatisasi tidak diizinkan.', 'automation_tool', true);
        }
        
        setInterval(() => {
            if (navigator.webdriver === true) {
                this.logSecurityEvent('automation_tool_detected', 'WebDriver still active');
                this.warningCount++;
                if (this.warningCount >= this.maxWarnings) {
                    // Automation tool repeated is fraud
                    this.redirectToLogin('Automation tool terdeteksi berulang kali', 'automation_tool_repeated', true);
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
    },
    
    // MOBILE-SPECIFIC ANTI-FRAUD FUNCTIONS (Android + iOS)
    isAndroid: function() { return /Android/i.test(navigator.userAgent); },
    isIOS: function() { return /iPhone|iPad|iPod/i.test(navigator.userAgent); },
    isMobile: function() { return this.isAndroid() || this.isIOS(); },
    detectMobileScreenRecording: function() {
        if (!this.isMobile()) return;
        setInterval(() => {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = 200; canvas.height = 200;
                const ctx = canvas.getContext('2d');
                ctx.font = '14px Arial';
                ctx.fillStyle = '#f60'; ctx.fillRect(125, 1, 62, 20);
                ctx.fillStyle = '#069'; ctx.fillText('Screen check', 2, 15);
                const fingerprint = canvas.toDataURL();
                if (this.lastCanvasFingerprint && this.lastCanvasFingerprint !== fingerprint) {
                    this.logSecurityEvent('mobile_screen_recording', 'Canvas fingerprint changed');
                    this.warningCount++;
                    if (this.warningCount >= this.maxWarnings) {
                        this.redirectToLogin('Screen recording terdeteksi', 'mobile_screen_recording', true);
                    }
                }
                this.lastCanvasFingerprint = fingerprint;
            } catch (e) {}
        }, 10000);
        if (navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
            const self = this;
            navigator.mediaDevices.getDisplayMedia = function() {
                self.logSecurityEvent('mobile_screen_recording', 'getDisplayMedia called');
                self.warningCount++;
                if (self.warningCount >= self.maxWarnings) {
                    self.redirectToLogin('Screen recording terdeteksi', 'mobile_screen_recording', true);
                }
                return Promise.reject('Screen recording not allowed');
            };
        }
    },
    detectMobileAppSwitch: function() {
        if (!this.isMobile()) return;
        let lastVisibilityChange = Date.now(), appSwitchCount = 0;
        document.addEventListener('visibilitychange', () => {
            const now = Date.now(), timeSinceLastChange = now - lastVisibilityChange;
            if (document.hidden) {
                appSwitchCount++;
                this.logSecurityEvent('mobile_app_switch', `App switched (Count: ${appSwitchCount})`);
                if (timeSinceLastChange < 2000 && appSwitchCount > 1) {
                    this.warningCount++;
                    this.showWarning(`Peringatan: Jangan beralih aplikasi! (${appSwitchCount}x)`);
                    if (appSwitchCount >= 5) {
                        this.redirectToLogin('Terlalu banyak beralih aplikasi', 'mobile_app_switch', true);
                    }
                }
            } else {
                const timeHidden = now - lastVisibilityChange;
                if (timeHidden > 5000) {
                    this.logSecurityEvent('mobile_app_return', `Returned after ${timeHidden}ms`);
                }
            }
            lastVisibilityChange = now;
        });
        window.addEventListener('blur', () => {
            appSwitchCount++;
            this.logSecurityEvent('mobile_window_blur', 'Window lost focus');
        });
    },
    detectMobileOrientationChange: function() {
        if (!this.isMobile()) return;
        let orientationChangeCount = 0, lastOrientationChange = Date.now();
        window.addEventListener('orientationchange', () => {
            const now = Date.now(), timeSinceLastChange = now - lastOrientationChange;
            orientationChangeCount++;
            this.logSecurityEvent('mobile_orientation_change', `Orientation changed (Count: ${orientationChangeCount})`);
            if (timeSinceLastChange < 3000 && orientationChangeCount > 2) {
                this.warningCount++;
                this.showWarning('Perubahan orientasi layar terlalu sering!');
                if (orientationChangeCount >= 5) {
                    this.redirectToLogin('Terlalu banyak perubahan orientasi', 'mobile_orientation_change', true);
                }
            }
            lastOrientationChange = now;
        });
        if (screen.orientation) {
            screen.orientation.addEventListener('change', () => {
                this.logSecurityEvent('mobile_orientation_change', `Screen orientation: ${screen.orientation.angle}°`);
            });
        }
        // iOS specific orientation detection
        if (this.isIOS()) {
            window.addEventListener('orientationchange', () => {
                this.logSecurityEvent('ios_orientation_change', 'iOS orientation changed');
            });
        }
    },
    detectMobileViewportChange: function() {
        if (!this.isMobile()) return;
        let initialViewport = { width: window.innerWidth, height: window.innerHeight, devicePixelRatio: window.devicePixelRatio || 1 };
        let viewportChangeCount = 0;
        window.addEventListener('resize', () => {
            const currentViewport = { width: window.innerWidth, height: window.innerHeight, devicePixelRatio: window.devicePixelRatio || 1 };
            const widthDiff = Math.abs(currentViewport.width - initialViewport.width);
            const heightDiff = Math.abs(currentViewport.height - initialViewport.height);
            const ratioDiff = Math.abs(currentViewport.devicePixelRatio - initialViewport.devicePixelRatio);
            if (widthDiff > 50 || heightDiff > 50 || ratioDiff > 0.1) {
                viewportChangeCount++;
                this.logSecurityEvent('mobile_viewport_change', `Viewport changed: ${currentViewport.width}x${currentViewport.height} (${viewportChangeCount}x)`);
                if (viewportChangeCount >= 3) {
                    this.warningCount++;
                    this.showWarning('Perubahan ukuran layar terdeteksi!');
                    if (viewportChangeCount >= 5) {
                        this.redirectToLogin('Terlalu banyak perubahan viewport', 'mobile_viewport_change', true);
                    }
                }
            }
        });
    },
    detectMobileNetworkChange: function() {
        if (!this.isMobile()) return;
        if ('connection' in navigator) {
            const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            if (connection) {
                let initialConnectionType = connection.effectiveType || connection.type || 'unknown', networkChangeCount = 0;
                connection.addEventListener('change', () => {
                    const currentType = connection.effectiveType || connection.type || 'unknown';
                    if (currentType !== initialConnectionType) {
                        networkChangeCount++;
                        this.logSecurityEvent('mobile_network_change', `Network changed: ${initialConnectionType} -> ${currentType} (${networkChangeCount}x)`);
                        this.warningCount++;
                        this.showWarning(`Perubahan koneksi jaringan terdeteksi: ${currentType}`);
                        if (networkChangeCount >= 3) {
                            this.redirectToLogin('Terlalu banyak perubahan jaringan', 'mobile_network_change', true);
                        }
                    }
                });
            }
        }
        let networkStateChangeCount = 0;
        window.addEventListener('online', () => { networkStateChangeCount++; this.logSecurityEvent('mobile_network_online', 'Network came online'); });
        window.addEventListener('offline', () => {
            networkStateChangeCount++;
            this.logSecurityEvent('mobile_network_offline', 'Network went offline');
            if (networkStateChangeCount >= 3) {
                this.warningCount++;
                this.showWarning('Koneksi jaringan terputus berulang kali!');
            }
        });
    },
    detectMobileBatteryChange: function() {
        if (!this.isMobile()) return;
        if ('getBattery' in navigator) {
            navigator.getBattery().then(battery => {
                let initialLevel = battery.level, initialCharging = battery.charging, batteryChangeCount = 0;
                battery.addEventListener('levelchange', () => {
                    const levelDiff = Math.abs(battery.level - initialLevel);
                    if (levelDiff > 0.2) {
                        batteryChangeCount++;
                        this.logSecurityEvent('mobile_battery_change', `Battery level changed: ${(battery.level * 100).toFixed(0)}%`);
                    }
                });
                battery.addEventListener('chargingchange', () => {
                    if (battery.charging !== initialCharging) {
                        batteryChangeCount++;
                        this.logSecurityEvent('mobile_battery_charging', `Charging state changed: ${battery.charging}`);
                        if (batteryChangeCount >= 2) {
                            this.warningCount++;
                            this.showWarning('Status pengisian baterai berubah!');
                        }
                    }
                });
            }).catch(() => {});
        }
    },
    detectMobileTouchPatterns: function() {
        if (!this.isMobile()) return;
        let touchEvents = [], suspiciousPatterns = [];
        document.addEventListener('touchstart', (e) => {
            const touch = e.touches[0];
            touchEvents.push({ x: touch.clientX, y: touch.clientY, time: Date.now(), type: 'start' });
            if (touchEvents.length > 20) touchEvents.shift();
            if (touchEvents.length >= 5) {
                const recent = touchEvents.slice(-5), distances = [];
                for (let i = 1; i < recent.length; i++) {
                    const dist = Math.sqrt(Math.pow(recent[i].x - recent[i-1].x, 2) + Math.pow(recent[i].y - recent[i-1].y, 2));
                    distances.push(dist);
                }
                const avgDist = distances.reduce((a, b) => a + b, 0) / distances.length;
                const variance = distances.reduce((sum, d) => sum + Math.pow(d - avgDist, 2), 0) / distances.length;
                if (variance < 100 && avgDist > 50) suspiciousPatterns.push('uniform_touch_pattern');
            }
            if (suspiciousPatterns.length >= 3) {
                this.logSecurityEvent('mobile_suspicious_touch', 'Suspicious touch pattern detected');
                this.warningCount++;
                if (this.warningCount >= this.maxWarnings) {
                    this.redirectToLogin('Pola sentuhan mencurigakan terdeteksi', 'mobile_touch_pattern', true);
                }
            }
        });
    },
    detectMobileBackground: function() {
        if (!this.isMobile()) return;
        let backgroundTime = 0, backgroundStartTime = null;
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                backgroundStartTime = Date.now();
                this.logSecurityEvent('mobile_background', 'App moved to background');
            } else {
                if (backgroundStartTime) {
                    backgroundTime = Date.now() - backgroundStartTime;
                    if (backgroundTime > 10000) {
                        this.warningCount++;
                        this.showWarning(`Aplikasi di background terlalu lama (${Math.round(backgroundTime/1000)}s)!`);
                        if (backgroundTime > 60000) {
                            this.redirectToLogin('Aplikasi di background terlalu lama', 'mobile_background', true);
                        }
                    }
                    backgroundStartTime = null;
                }
            }
        });
    },
    detectMobileOverlay: function() {
        if (!this.isMobile()) return;
        let overlayCheckCount = 0;
        setInterval(() => {
            if (!document.hasFocus()) {
                overlayCheckCount++;
                if (overlayCheckCount >= 3) {
                    this.logSecurityEvent('mobile_overlay', 'Possible overlay app detected');
                    this.warningCount++;
                    this.showWarning('Aplikasi overlay terdeteksi!');
                    if (overlayCheckCount >= 5) {
                        this.redirectToLogin('Aplikasi overlay terdeteksi', 'mobile_overlay', true);
                    }
                }
            } else {
                overlayCheckCount = 0;
            }
        }, 5000);
    },
    detectMobileDeveloperOptions: function() {
        if (!this.isMobile()) return;
        const devIndicators = [];
        if (navigator.webdriver === true) devIndicators.push('WebDriver detected');
        const unusualProps = ['webdriver', '__webdriver_script_fn', '__selenium_unwrapped', '__webdriver_evaluate'];
        unusualProps.forEach(prop => { if (prop in navigator) devIndicators.push(`${prop} detected`); });
        if (devIndicators.length > 0) {
            this.logSecurityEvent('mobile_developer_options', devIndicators.join(', '));
            this.warningCount++;
            if (this.warningCount >= this.maxWarnings) {
                this.redirectToLogin('Developer options terdeteksi', 'mobile_developer_options', true);
            }
        }
    },
    // iOS-SPECIFIC FUNCTIONS
    detectIOSScreenRecording: function() {
        if (!this.isIOS()) return;
        let recordingCheckCount = 0;
        setInterval(() => {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = 100; canvas.height = 100;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#000';
                ctx.fillRect(0, 0, 100, 100);
                const dataURL = canvas.toDataURL();
                if (this.lastIOSCanvasFingerprint && this.lastIOSCanvasFingerprint !== dataURL) {
                    recordingCheckCount++;
                    if (recordingCheckCount >= 2) {
                        this.logSecurityEvent('ios_screen_recording', 'Possible iOS screen recording detected');
                        this.warningCount++;
                        if (this.warningCount >= this.maxWarnings) {
                            this.redirectToLogin('Screen recording terdeteksi', 'ios_screen_recording', true);
                        }
                    }
                }
                this.lastIOSCanvasFingerprint = dataURL;
            } catch (e) {}
        }, 15000);
    },
    detectIOSSafariFeatures: function() {
        if (!this.isIOS()) return;
        const safariIndicators = [];
        if (window.webkit && window.webkit.messageHandlers) {
            safariIndicators.push('Safari Web Inspector detected');
        }
        if (navigator.standalone === false && window.matchMedia('(display-mode: standalone)').matches) {
            safariIndicators.push('Safari standalone mode detected');
        }
        if (safariIndicators.length > 0) {
            this.logSecurityEvent('ios_safari_features', safariIndicators.join(', '));
        }
    },
    detectIOSAppState: function() {
        if (!this.isIOS()) return;
        let appStateChangeCount = 0;
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                appStateChangeCount++;
                this.logSecurityEvent('ios_app_background', `iOS app went to background (Count: ${appStateChangeCount})`);
            } else {
                this.logSecurityEvent('ios_app_foreground', 'iOS app came to foreground');
            }
        });
        window.addEventListener('pagehide', () => {
            this.logSecurityEvent('ios_pagehide', 'iOS pagehide event (app may be closing)');
        });
        window.addEventListener('pageshow', () => {
            this.logSecurityEvent('ios_pageshow', 'iOS pageshow event');
        });
    },
    detectIOSNetworkStatus: function() {
        if (!this.isIOS()) return;
        if ('connection' in navigator) {
            const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            if (connection) {
                connection.addEventListener('change', () => {
                    const effectiveType = connection.effectiveType || 'unknown';
                    const downlink = connection.downlink || 0;
                    this.logSecurityEvent('ios_network_status', `iOS network: ${effectiveType}, downlink: ${downlink}Mbps`);
                });
            }
        }
    },
    
    initMobileSecurity: function() {
        if (!this.isMobile()) {
            console.log('Not mobile device, skipping mobile-specific security');
            return;
        }
        const deviceType = this.isAndroid() ? 'Android' : 'iOS';
        console.log(`Initializing ${deviceType}-specific security features`);
        this.detectMobileScreenRecording();
        this.detectMobileAppSwitch();
        this.detectMobileOrientationChange();
        this.detectMobileViewportChange();
        this.detectMobileNetworkChange();
        this.detectMobileBatteryChange();
        this.detectMobileTouchPatterns();
        this.detectMobileBackground();
        this.detectMobileOverlay();
        this.detectMobileDeveloperOptions();
        if (this.isIOS()) {
            this.detectIOSScreenRecording();
            this.detectIOSSafariFeatures();
            this.detectIOSAppState();
            this.detectIOSNetworkStatus();
        }
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


                }, 5000); // Wait 5 seconds before counting (increased from 2 seconds for more leniency)
            } else {
                // Tab is visible again - clear timeout
                if (visibilityChangeTimeout) {
                    clearTimeout(visibilityChangeTimeout);
                    visibilityChangeTimeout = null;
                }
                
                const timeHidden = now - lastVisibilityChange;
                if (timeHidden > 5000) { // More than 5 seconds (increased from 2 seconds)
                    this.logSecurityEvent('tab_return', `Returned to tab after ${timeHidden}ms`);
                }
            }
            
            lastVisibilityChange = now;
            lastVisibilityState = currentState;
        });
        
        // Window blur detection disabled - too many false positives
        // Only log for tracking, but don't count as fraud
        window.addEventListener('blur', () => {
            // Just log, don't count as warning or fraud
            this.logSecurityEvent('window_blur', 'Window lost focus (logged only)');
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
        // RELAXED - Fullscreen is optional, only suggestion, no enforcement
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
        
        // Try to enter fullscreen (optional, no error if fails)
        requestFullscreen().then(() => {
            this.isFullscreen = true;
            console.log('Fullscreen mode enabled');
        }).catch((err) => {
            // Just log, no warning - fullscreen is optional
            console.log('Fullscreen not available (optional):', err);
        });
        
        // Monitor fullscreen changes (informational only, no enforcement)
        const fullscreenEvents = ['fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'MSFullscreenChange'];
        fullscreenEvents.forEach(event => {
            document.addEventListener(event, () => {
                const isFullscreen = !!(document.fullscreenElement || 
                                       document.webkitFullscreenElement || 
                                       document.mozFullScreenElement || 
                                       document.msFullscreenElement);
                
                if (isFullscreen) {
                    this.isFullscreen = true;
                } else {
                    this.isFullscreen = false;
                }
                // No warnings, no redirects - just track state
            });
        });
    },
    
    startFullscreenMonitor: function() {
        // DISABLED - Fullscreen monitoring disabled (relaxed mode)
        // No enforcement, no warnings, no redirects
        return;
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
                // Redirect to login if too many attempts
                this.warningCount++;
                if (this.warningCount >= this.maxWarnings) {
                    // Navigation back is fraud
                    this.redirectToLogin('Terlalu banyak mencoba navigasi mundur', 'navigation_back', true);
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
                        // Developer tools is fraud
                        this.redirectToLogin('Developer tools terdeteksi', 'developer_tools', true);
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
            
            if (idleTime >= 120) { // 2 minutes (increased from 1 minute)
                this.showWarning('Tidak ada aktivitas terdeteksi. Silakan lanjutkan ujian!');
                this.logSecurityEvent('idle_detected', 'User idle for ' + idleTime + ' seconds');
            }
            
            if (idleTime >= 300) { // 5 minutes (increased from 3 minutes) - much more lenient
                // Idle timeout - treat as normal disruption (not fraud)
                this.redirectToLogin('Terlalu lama tidak aktif', 'idle_timeout', false);
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
        // Add delay before first check to avoid false positive on page load
        // Wait 30 seconds before starting periodic checks (increased from 15 seconds)
        setTimeout(() => {
            this.checkSecurity();
            
            // Then check every 20 seconds (increased from 10 seconds for more leniency)
            setInterval(() => {
                this.checkSecurity();
            }, 20000);
        }, 30000); // 30 seconds grace period (increased from 15 seconds)
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
                if (!response.success) {
                    // Only trigger fraud if it's a real fraud detection, not just validation error
                    if (response.fraud && response.requires_logout) {
                        // Fraud detected - force logout
                        this.redirectToLogin(response.message || 'Fraud terdeteksi', 'fraud_detection', true);
                    } else if (response.auto_submit && !response.rate_limit) {
                        // Legacy auto-submit (treat as fraud) - but ignore rate limit errors
                        this.redirectToLogin(response.message || 'Pelanggaran keamanan terdeteksi', 'server_validation', true);
                    }
                    // Ignore rate_limit errors - they're not fraud
                }
            }, (error) => {
                // Ignore network errors - don't treat as fraud
                console.error('Security check error:', error);
            });
        }
    },
    
    showWarning: function(message) {
        this.warningCount++;
        
        // Show warning toast (prefer toast over alert to avoid visibility change)
        if (typeof showToast === 'function') {
            showToast(message, 'warning');
        } else {
            // Use console.warn instead of alert to avoid triggering visibility change
            console.warn('Warning:', message);
            // Only use alert as last resort, and mark dialog as showing
            if (typeof window.alert === 'function') {
                // Set flag before alert
                if (this.isDialogShowing !== undefined) {
                    this.isDialogShowing = true;
                    setTimeout(() => {
                        this.isDialogShowing = false;
                    }, 2000);
                }
                alert(message);
            }
        }
        
        // Log to server
        this.logSecurityEvent('warning', message);
        
        if (this.warningCount >= this.maxWarnings) {
            // Max warnings reached is fraud
            this.redirectToLogin('Terlalu banyak peringatan', 'max_warnings', true);
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
                this.redirectToLogin(result.message || 'Pelanggaran keamanan terdeteksi', 'server_check');
            }
        }).catch(error => {
            console.error('Security log error:', error);
        });
    },
    
    /**
     * Redirect to login page due to security violation (FRAUD)
     * This is for fraud detection - answers reset, must relogin, time continues
     */
    redirectToLogin: function(reason, violationType = 'security_violation', isFraud = true) {
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
        
        // Get session info
        const sesiId = document.querySelector('[data-sesi-id]')?.getAttribute('data-sesi-id') || '';
        const ujianId = document.querySelector('[data-ujian-id]')?.getAttribute('data-ujian-id') || '';
        const baseUrl = typeof base_url === 'function' ? base_url('') : (window.location.origin + '/UJAN/');
        
        if (isFraud) {
            // FRAUD: Mark as fraud, reset answers, force logout
            // Show warning message
            const message = 'Fraud terdeteksi: ' + reason + '. Jawaban sudah di-reset. Anda harus login ulang. Waktu ujian terus berjalan.';
            if (typeof showToast === 'function') {
                showToast(message, 'error');
            } else {
                alert(message);
            }
            
            // Log final event
            this.logSecurityEvent('fraud_detected', reason);
            
            // Don't save answers for fraud - they will be reset
            // Mark as fraud and reset answers
            const formData = new FormData();
            formData.append('action', 'mark_fraud');
            formData.append('reason', reason);
            if (sesiId) formData.append('sesi_id', sesiId);
            if (ujianId) formData.append('ujian_id', ujianId);
            
            fetch(baseUrl + 'api/fraud_detection.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Force logout and redirect
                window.location.href = baseUrl + 'siswa/login.php?fraud=1&sesi_id=' + sesiId + '&reason=' + encodeURIComponent(reason);
            })
            .catch(error => {
                console.error('Fraud detection API error:', error);
                // Fallback: redirect to login directly
                window.location.href = baseUrl + 'siswa/login.php?fraud=1&sesi_id=' + sesiId + '&reason=' + encodeURIComponent(reason);
            });
        } else {
            // NORMAL DISRUPTION: Lock answers, allow resume with token
            const message = 'Gangguan terdeteksi: ' + reason + '. Jawaban sudah dikunci di server. Silakan login ulang dan minta token baru untuk melanjutkan.';
            if (typeof showToast === 'function') {
                showToast(message, 'warning');
            } else {
                alert(message);
            }
            
            // Log final event
            this.logSecurityEvent('normal_disruption', reason);
            
            // Save all answers before logout (auto-save)
            this.saveAllAnswers().then(() => {
                // Lock answers for normal disruption
                const formData = new FormData();
                formData.append('action', 'lock_answers');
                formData.append('reason', reason);
                if (sesiId) formData.append('sesi_id', sesiId);
                if (ujianId) formData.append('ujian_id', ujianId);
                
                fetch(baseUrl + 'api/fraud_detection.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // Redirect to login with disruption flag
                    window.location.href = baseUrl + 'siswa/login.php?disruption=1&sesi_id=' + sesiId + '&reason=' + encodeURIComponent(reason);
                })
                .catch(error => {
                    console.error('Lock answers API error:', error);
                    // Fallback: redirect to login directly
                    window.location.href = baseUrl + 'siswa/login.php?disruption=1&sesi_id=' + sesiId + '&reason=' + encodeURIComponent(reason);
                });
            });
        }
    },
    
    /**
     * Save all answers before logout
     */
    saveAllAnswers: function() {
        return new Promise((resolve) => {
            const sesiId = document.querySelector('[data-sesi-id]')?.getAttribute('data-sesi-id') || '';
            const ujianId = document.querySelector('[data-ujian-id]')?.getAttribute('data-ujian-id') || '';
            
            if (!sesiId || !ujianId) {
                resolve();
                return;
            }
            
            // Collect all answers from the form
            const answers = {};
            const form = document.getElementById('examForm');
            if (form) {
                const inputs = form.querySelectorAll('input[type="radio"]:checked, input[type="checkbox"]:checked, textarea, input[type="text"]');
                inputs.forEach(input => {
                    const soalId = input.name.match(/jawaban\[(\d+)\]/);
                    if (soalId) {
                        const id = soalId[1];
                        if (input.type === 'checkbox') {
                            if (!answers[id]) answers[id] = [];
                            answers[id].push(input.value);
                        } else {
                            answers[id] = input.value;
                        }
                    }
                });
            }
            
            // Send to auto-save API
            const baseUrl = typeof base_url === 'function' ? base_url('') : (window.location.origin + '/UJAN/');
            const formData = new FormData();
            formData.append('sesi_id', sesiId);
            formData.append('ujian_id', ujianId);
            formData.append('answers', JSON.stringify(answers));
            
            fetch(baseUrl + 'api/auto_save.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                resolve();
            })
            .catch(error => {
                console.error('Auto-save error:', error);
                resolve(); // Continue even if save fails
            });
        });
    },
    
    /**
     * Auto submit exam (for normal completion, not security violations)
     * This is kept for backward compatibility and normal exam completion
     */
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
        // DISABLED - Virtual machine detection removed due to too many false positives
        // This function is kept for compatibility but does nothing
        return;
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
                
                // More lenient - only warn after many changes
                if (this.screenChanges >= 10) {
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
                
                // More lenient - only warn if pattern is very suspicious
                if (variance < 50 && avgInterval < 100) {
                    this.logSecurityEvent('suspicious_keyboard_pattern', 'Uniform typing speed detected');
                    // Don't count as warning immediately - just log
                    // this.warningCount++;
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
        
        // Delay first IP check to avoid false positive on page load
        setTimeout(() => {
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
                            // IP change is fraud
                            this.redirectToLogin('Perubahan IP address terdeteksi', 'ip_change', true);
                        }
                    }).catch(() => {});
                }
            }, 60000);
        }, 30000); // Wait 30 seconds before first IP check
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
        // Delay first session validation to avoid false positive on page load
        setTimeout(() => {
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
                        this.redirectToLogin('Session tidak valid', 'session_invalid');
                    }
                }).catch(() => {
                    // Ignore network errors - don't treat as fraud
                    // this.logSecurityEvent('session_validation_error', 'Failed to validate session');
                });
            }, 30000);
        }, 20000); // Wait 20 seconds before first session validation
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
            // Automation tool is fraud
            this.redirectToLogin('Automation tool terdeteksi. Penggunaan tool otomatisasi tidak diizinkan.', 'automation_tool', true);
        }
        
        setInterval(() => {
            if (navigator.webdriver === true) {
                this.logSecurityEvent('automation_tool_detected', 'WebDriver still active');
                this.warningCount++;
                if (this.warningCount >= this.maxWarnings) {
                    // Automation tool repeated is fraud
                    this.redirectToLogin('Automation tool terdeteksi berulang kali', 'automation_tool_repeated', true);
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
    },
    
    // MOBILE-SPECIFIC ANTI-FRAUD FUNCTIONS (Android + iOS)
    isAndroid: function() { return /Android/i.test(navigator.userAgent); },
    isIOS: function() { return /iPhone|iPad|iPod/i.test(navigator.userAgent); },
    isMobile: function() { return this.isAndroid() || this.isIOS(); },
    detectMobileScreenRecording: function() {
        if (!this.isMobile()) return;
        setInterval(() => {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = 200; canvas.height = 200;
                const ctx = canvas.getContext('2d');
                ctx.font = '14px Arial';
                ctx.fillStyle = '#f60'; ctx.fillRect(125, 1, 62, 20);
                ctx.fillStyle = '#069'; ctx.fillText('Screen check', 2, 15);
                const fingerprint = canvas.toDataURL();
                if (this.lastCanvasFingerprint && this.lastCanvasFingerprint !== fingerprint) {
                    this.logSecurityEvent('mobile_screen_recording', 'Canvas fingerprint changed');
                    this.warningCount++;
                    if (this.warningCount >= this.maxWarnings) {
                        this.redirectToLogin('Screen recording terdeteksi', 'mobile_screen_recording', true);
                    }
                }
                this.lastCanvasFingerprint = fingerprint;
            } catch (e) {}
        }, 10000);
        if (navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
            const self = this;
            navigator.mediaDevices.getDisplayMedia = function() {
                self.logSecurityEvent('mobile_screen_recording', 'getDisplayMedia called');
                self.warningCount++;
                if (self.warningCount >= self.maxWarnings) {
                    self.redirectToLogin('Screen recording terdeteksi', 'mobile_screen_recording', true);
                }
                return Promise.reject('Screen recording not allowed');
            };
        }
    },
    detectMobileAppSwitch: function() {
        if (!this.isMobile()) return;
        let lastVisibilityChange = Date.now(), appSwitchCount = 0;
        document.addEventListener('visibilitychange', () => {
            const now = Date.now(), timeSinceLastChange = now - lastVisibilityChange;
            if (document.hidden) {
                appSwitchCount++;
                this.logSecurityEvent('mobile_app_switch', `App switched (Count: ${appSwitchCount})`);
                if (timeSinceLastChange < 2000 && appSwitchCount > 1) {
                    this.warningCount++;
                    this.showWarning(`Peringatan: Jangan beralih aplikasi! (${appSwitchCount}x)`);
                    if (appSwitchCount >= 5) {
                        this.redirectToLogin('Terlalu banyak beralih aplikasi', 'mobile_app_switch', true);
                    }
                }
            } else {
                const timeHidden = now - lastVisibilityChange;
                if (timeHidden > 5000) {
                    this.logSecurityEvent('mobile_app_return', `Returned after ${timeHidden}ms`);
                }
            }
            lastVisibilityChange = now;
        });
        window.addEventListener('blur', () => {
            appSwitchCount++;
            this.logSecurityEvent('mobile_window_blur', 'Window lost focus');
        });
    },
    detectMobileOrientationChange: function() {
        if (!this.isMobile()) return;
        let orientationChangeCount = 0, lastOrientationChange = Date.now();
        window.addEventListener('orientationchange', () => {
            const now = Date.now(), timeSinceLastChange = now - lastOrientationChange;
            orientationChangeCount++;
            this.logSecurityEvent('mobile_orientation_change', `Orientation changed (Count: ${orientationChangeCount})`);
            if (timeSinceLastChange < 3000 && orientationChangeCount > 2) {
                this.warningCount++;
                this.showWarning('Perubahan orientasi layar terlalu sering!');
                if (orientationChangeCount >= 5) {
                    this.redirectToLogin('Terlalu banyak perubahan orientasi', 'mobile_orientation_change', true);
                }
            }
            lastOrientationChange = now;
        });
        if (screen.orientation) {
            screen.orientation.addEventListener('change', () => {
                this.logSecurityEvent('mobile_orientation_change', `Screen orientation: ${screen.orientation.angle}°`);
            });
        }
        // iOS specific orientation detection
        if (this.isIOS()) {
            window.addEventListener('orientationchange', () => {
                this.logSecurityEvent('ios_orientation_change', 'iOS orientation changed');
            });
        }
    },
    detectMobileViewportChange: function() {
        if (!this.isMobile()) return;
        let initialViewport = { width: window.innerWidth, height: window.innerHeight, devicePixelRatio: window.devicePixelRatio || 1 };
        let viewportChangeCount = 0;
        window.addEventListener('resize', () => {
            const currentViewport = { width: window.innerWidth, height: window.innerHeight, devicePixelRatio: window.devicePixelRatio || 1 };
            const widthDiff = Math.abs(currentViewport.width - initialViewport.width);
            const heightDiff = Math.abs(currentViewport.height - initialViewport.height);
            const ratioDiff = Math.abs(currentViewport.devicePixelRatio - initialViewport.devicePixelRatio);
            if (widthDiff > 50 || heightDiff > 50 || ratioDiff > 0.1) {
                viewportChangeCount++;
                this.logSecurityEvent('mobile_viewport_change', `Viewport changed: ${currentViewport.width}x${currentViewport.height} (${viewportChangeCount}x)`);
                if (viewportChangeCount >= 3) {
                    this.warningCount++;
                    this.showWarning('Perubahan ukuran layar terdeteksi!');
                    if (viewportChangeCount >= 5) {
                        this.redirectToLogin('Terlalu banyak perubahan viewport', 'mobile_viewport_change', true);
                    }
                }
            }
        });
    },
    detectMobileNetworkChange: function() {
        if (!this.isMobile()) return;
        if ('connection' in navigator) {
            const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            if (connection) {
                let initialConnectionType = connection.effectiveType || connection.type || 'unknown', networkChangeCount = 0;
                connection.addEventListener('change', () => {
                    const currentType = connection.effectiveType || connection.type || 'unknown';
                    if (currentType !== initialConnectionType) {
                        networkChangeCount++;
                        this.logSecurityEvent('mobile_network_change', `Network changed: ${initialConnectionType} -> ${currentType} (${networkChangeCount}x)`);
                        this.warningCount++;
                        this.showWarning(`Perubahan koneksi jaringan terdeteksi: ${currentType}`);
                        if (networkChangeCount >= 3) {
                            this.redirectToLogin('Terlalu banyak perubahan jaringan', 'mobile_network_change', true);
                        }
                    }
                });
            }
        }
        let networkStateChangeCount = 0;
        window.addEventListener('online', () => { networkStateChangeCount++; this.logSecurityEvent('mobile_network_online', 'Network came online'); });
        window.addEventListener('offline', () => {
            networkStateChangeCount++;
            this.logSecurityEvent('mobile_network_offline', 'Network went offline');
            if (networkStateChangeCount >= 3) {
                this.warningCount++;
                this.showWarning('Koneksi jaringan terputus berulang kali!');
            }
        });
    },
    detectMobileBatteryChange: function() {
        if (!this.isMobile()) return;
        if ('getBattery' in navigator) {
            navigator.getBattery().then(battery => {
                let initialLevel = battery.level, initialCharging = battery.charging, batteryChangeCount = 0;
                battery.addEventListener('levelchange', () => {
                    const levelDiff = Math.abs(battery.level - initialLevel);
                    if (levelDiff > 0.2) {
                        batteryChangeCount++;
                        this.logSecurityEvent('mobile_battery_change', `Battery level changed: ${(battery.level * 100).toFixed(0)}%`);
                    }
                });
                battery.addEventListener('chargingchange', () => {
                    if (battery.charging !== initialCharging) {
                        batteryChangeCount++;
                        this.logSecurityEvent('mobile_battery_charging', `Charging state changed: ${battery.charging}`);
                        if (batteryChangeCount >= 2) {
                            this.warningCount++;
                            this.showWarning('Status pengisian baterai berubah!');
                        }
                    }
                });
            }).catch(() => {});
        }
    },
    detectMobileTouchPatterns: function() {
        if (!this.isMobile()) return;
        let touchEvents = [], suspiciousPatterns = [];
        document.addEventListener('touchstart', (e) => {
            const touch = e.touches[0];
            touchEvents.push({ x: touch.clientX, y: touch.clientY, time: Date.now(), type: 'start' });
            if (touchEvents.length > 20) touchEvents.shift();
            if (touchEvents.length >= 5) {
                const recent = touchEvents.slice(-5), distances = [];
                for (let i = 1; i < recent.length; i++) {
                    const dist = Math.sqrt(Math.pow(recent[i].x - recent[i-1].x, 2) + Math.pow(recent[i].y - recent[i-1].y, 2));
                    distances.push(dist);
                }
                const avgDist = distances.reduce((a, b) => a + b, 0) / distances.length;
                const variance = distances.reduce((sum, d) => sum + Math.pow(d - avgDist, 2), 0) / distances.length;
                if (variance < 100 && avgDist > 50) suspiciousPatterns.push('uniform_touch_pattern');
            }
            if (suspiciousPatterns.length >= 3) {
                this.logSecurityEvent('mobile_suspicious_touch', 'Suspicious touch pattern detected');
                this.warningCount++;
                if (this.warningCount >= this.maxWarnings) {
                    this.redirectToLogin('Pola sentuhan mencurigakan terdeteksi', 'mobile_touch_pattern', true);
                }
            }
        });
    },
    detectMobileBackground: function() {
        if (!this.isMobile()) return;
        let backgroundTime = 0, backgroundStartTime = null;
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                backgroundStartTime = Date.now();
                this.logSecurityEvent('mobile_background', 'App moved to background');
            } else {
                if (backgroundStartTime) {
                    backgroundTime = Date.now() - backgroundStartTime;
                    if (backgroundTime > 10000) {
                        this.warningCount++;
                        this.showWarning(`Aplikasi di background terlalu lama (${Math.round(backgroundTime/1000)}s)!`);
                        if (backgroundTime > 60000) {
                            this.redirectToLogin('Aplikasi di background terlalu lama', 'mobile_background', true);
                        }
                    }
                    backgroundStartTime = null;
                }
            }
        });
    },
    detectMobileOverlay: function() {
        if (!this.isMobile()) return;
        let overlayCheckCount = 0;
        setInterval(() => {
            if (!document.hasFocus()) {
                overlayCheckCount++;
                if (overlayCheckCount >= 3) {
                    this.logSecurityEvent('mobile_overlay', 'Possible overlay app detected');
                    this.warningCount++;
                    this.showWarning('Aplikasi overlay terdeteksi!');
                    if (overlayCheckCount >= 5) {
                        this.redirectToLogin('Aplikasi overlay terdeteksi', 'mobile_overlay', true);
                    }
                }
            } else {
                overlayCheckCount = 0;
            }
        }, 5000);
    },
    detectMobileDeveloperOptions: function() {
        if (!this.isMobile()) return;
        const devIndicators = [];
        if (navigator.webdriver === true) devIndicators.push('WebDriver detected');
        const unusualProps = ['webdriver', '__webdriver_script_fn', '__selenium_unwrapped', '__webdriver_evaluate'];
        unusualProps.forEach(prop => { if (prop in navigator) devIndicators.push(`${prop} detected`); });
        if (devIndicators.length > 0) {
            this.logSecurityEvent('mobile_developer_options', devIndicators.join(', '));
            this.warningCount++;
            if (this.warningCount >= this.maxWarnings) {
                this.redirectToLogin('Developer options terdeteksi', 'mobile_developer_options', true);
            }
        }
    },
    // iOS-SPECIFIC FUNCTIONS
    detectIOSScreenRecording: function() {
        if (!this.isIOS()) return;
        let recordingCheckCount = 0;
        setInterval(() => {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = 100; canvas.height = 100;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#000';
                ctx.fillRect(0, 0, 100, 100);
                const dataURL = canvas.toDataURL();
                if (this.lastIOSCanvasFingerprint && this.lastIOSCanvasFingerprint !== dataURL) {
                    recordingCheckCount++;
                    if (recordingCheckCount >= 2) {
                        this.logSecurityEvent('ios_screen_recording', 'Possible iOS screen recording detected');
                        this.warningCount++;
                        if (this.warningCount >= this.maxWarnings) {
                            this.redirectToLogin('Screen recording terdeteksi', 'ios_screen_recording', true);
                        }
                    }
                }
                this.lastIOSCanvasFingerprint = dataURL;
            } catch (e) {}
        }, 15000);
    },
    detectIOSSafariFeatures: function() {
        if (!this.isIOS()) return;
        const safariIndicators = [];
        if (window.webkit && window.webkit.messageHandlers) {
            safariIndicators.push('Safari Web Inspector detected');
        }
        if (navigator.standalone === false && window.matchMedia('(display-mode: standalone)').matches) {
            safariIndicators.push('Safari standalone mode detected');
        }
        if (safariIndicators.length > 0) {
            this.logSecurityEvent('ios_safari_features', safariIndicators.join(', '));
        }
    },
    detectIOSAppState: function() {
        if (!this.isIOS()) return;
        let appStateChangeCount = 0;
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                appStateChangeCount++;
                this.logSecurityEvent('ios_app_background', `iOS app went to background (Count: ${appStateChangeCount})`);
            } else {
                this.logSecurityEvent('ios_app_foreground', 'iOS app came to foreground');
            }
        });
        window.addEventListener('pagehide', () => {
            this.logSecurityEvent('ios_pagehide', 'iOS pagehide event (app may be closing)');
        });
        window.addEventListener('pageshow', () => {
            this.logSecurityEvent('ios_pageshow', 'iOS pageshow event');
        });
    },
    detectIOSNetworkStatus: function() {
        if (!this.isIOS()) return;
        if ('connection' in navigator) {
            const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            if (connection) {
                connection.addEventListener('change', () => {
                    const effectiveType = connection.effectiveType || 'unknown';
                    const downlink = connection.downlink || 0;
                    this.logSecurityEvent('ios_network_status', `iOS network: ${effectiveType}, downlink: ${downlink}Mbps`);
                });
            }
        }
    },
    
    initMobileSecurity: function() {
        if (!this.isMobile()) {
            console.log('Not mobile device, skipping mobile-specific security');
            return;
        }
        const deviceType = this.isAndroid() ? 'Android' : 'iOS';
        console.log(`Initializing ${deviceType}-specific security features`);
        this.detectMobileScreenRecording();
        this.detectMobileAppSwitch();
        this.detectMobileOrientationChange();
        this.detectMobileViewportChange();
        this.detectMobileNetworkChange();
        this.detectMobileBatteryChange();
        this.detectMobileTouchPatterns();
        this.detectMobileBackground();
        this.detectMobileOverlay();
        this.detectMobileDeveloperOptions();
        if (this.isIOS()) {
            this.detectIOSScreenRecording();
            this.detectIOSSafariFeatures();
            this.detectIOSAppState();
            this.detectIOSNetworkStatus();
        }
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

