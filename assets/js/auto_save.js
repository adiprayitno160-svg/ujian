/**
 * Auto Save Functionality
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

const AutoSave = {
    interval: null,
    saveInterval: 30000, // 30 seconds
    isSaving: false,
    indicator: null,
    
    init: function() {
        this.createIndicator();
        this.startAutoSave();
        this.setupBlurSave();
        this.setupBeforeUnload();
    },
    
    createIndicator: function() {
        const indicator = document.createElement('div');
        indicator.className = 'auto-save-indicator';
        indicator.id = 'autoSaveIndicator';
        indicator.innerHTML = '<i class="fas fa-check-circle me-2"></i><span>Tersimpan</span>';
        document.body.appendChild(indicator);
        this.indicator = indicator;
    },
    
    showIndicator: function(message, type = 'saved') {
        if (!this.indicator) return;
        
        const icon = type === 'saving' ? 'fa-spinner fa-spin' : 'fa-check-circle';
        this.indicator.innerHTML = `<i class="fas ${icon} me-2"></i><span>${message}</span>`;
        this.indicator.className = `auto-save-indicator show ${type}`;
        
        if (type === 'saved') {
            setTimeout(() => {
                this.indicator.classList.remove('show');
            }, 2000);
        }
    },
    
    startAutoSave: function() {
        this.interval = setInterval(() => {
            this.save();
        }, this.saveInterval);
    },
    
    stopAutoSave: function() {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
    },
    
    setupBlurSave: function() {
        // Save when leaving a question
        // Use focusout event instead of blur (focusout bubbles, blur doesn't)
        document.addEventListener('focusout', (e) => {
            // Check if target exists and is a DOM element
            if (e.target && e.target.nodeType === 1) {
                // Check if it's an input element we care about
                const tagName = e.target.tagName.toLowerCase();
                const type = e.target.type ? e.target.type.toLowerCase() : '';
                
                if ((tagName === 'input' && (type === 'radio' || type === 'checkbox' || type === 'text')) || 
                    tagName === 'textarea') {
                    // Small delay to allow other handlers to process first
                    setTimeout(() => {
                        this.save();
                    }, 100);
                }
            }
        }, true);
        
        // Also handle change events for immediate feedback
        document.addEventListener('change', (e) => {
            if (e.target && e.target.nodeType === 1) {
                const tagName = e.target.tagName.toLowerCase();
                const type = e.target.type ? e.target.type.toLowerCase() : '';
                
                if ((tagName === 'input' && (type === 'radio' || type === 'checkbox')) || 
                    tagName === 'textarea') {
                    this.save();
                }
            }
        }, true);
    },
    
    setupBeforeUnload: function() {
        window.addEventListener('beforeunload', () => {
            // Save one last time before leaving
            this.save(true);
        });
    },
    
    save: function(silent = false) {
        if (this.isSaving) return;
        
        // Check if UJAN is available
        if (typeof UJAN === 'undefined' || !UJAN || !UJAN.ajax) {
            console.warn('UJAN.ajax is not available, skipping save');
            return;
        }
        
        const sesiId = document.querySelector('[data-sesi-id]')?.getAttribute('data-sesi-id');
        const ujianId = document.querySelector('[data-ujian-id]')?.getAttribute('data-ujian-id');
        
        if (!sesiId || !ujianId) return;
        
        this.isSaving = true;
        
        if (!silent) {
            this.showIndicator('Menyimpan...', 'saving');
        }
        
        // Collect all answers
        const answers = this.collectAnswers();
        
        // Send to server
        UJAN.ajax('/UJAN/api/auto_save.php', {
            sesi_id: sesiId,
            ujian_id: ujianId,
            answers: JSON.stringify(answers)
        }, 'POST', (response) => {
            this.isSaving = false;
            
            if (response.success) {
                if (!silent) {
                    this.showIndicator('Tersimpan', 'saved');
                }
                
                // Update last saved time
                if (response.last_saved_at) {
                    const lastSavedEl = document.getElementById('lastSavedTime');
                    if (lastSavedEl) {
                        lastSavedEl.textContent = 'Terakhir disimpan: ' + response.last_saved_at;
                    }
                }
            } else {
                console.error('Auto-save failed:', response.message);
            }
        });
    },
    
    collectAnswers: function() {
        const answers = {};
        
        // Collect radio/checkbox answers
        document.querySelectorAll('input[type="radio"]:checked, input[type="checkbox"]:checked').forEach(input => {
            const soalId = input.getAttribute('data-soal-id');
            if (soalId) {
                if (!answers[soalId]) {
                    answers[soalId] = [];
                }
                answers[soalId].push(input.value);
            }
        });
        
        // Collect text/textarea answers
        document.querySelectorAll('textarea[data-soal-id], input[type="text"][data-soal-id]').forEach(input => {
            const soalId = input.getAttribute('data-soal-id');
            if (soalId) {
                answers[soalId] = input.value;
            }
        });
        
        return answers;
    },
    
    // Save to localStorage as backup
    saveToLocalStorage: function() {
        const sesiId = document.querySelector('[data-sesi-id]')?.getAttribute('data-sesi-id');
        if (!sesiId) return;
        
        const answers = this.collectAnswers();
        localStorage.setItem('ujian_answers_' + sesiId, JSON.stringify({
            answers: answers,
            timestamp: new Date().toISOString()
        }));
    },
    
    // Load from localStorage
    loadFromLocalStorage: function(sesiId) {
        const saved = localStorage.getItem('ujian_answers_' + sesiId);
        if (!saved) return null;
        
        try {
            const data = JSON.parse(saved);
            return data.answers;
        } catch (e) {
            console.error('Error loading from localStorage:', e);
            return null;
        }
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Wait for UJAN to be available
    function initAutoSave() {
        if (typeof UJAN === 'undefined' || !UJAN) {
            // Wait a bit more if UJAN is not yet available
            setTimeout(initAutoSave, 100);
            return;
        }
        
        if (document.querySelector('[data-sesi-id]')) {
            AutoSave.init();
            
            // Also save to localStorage periodically
            setInterval(() => {
                AutoSave.saveToLocalStorage();
            }, 10000); // Every 10 seconds
        }
    }
    
    // Start initialization
    initAutoSave();
});

