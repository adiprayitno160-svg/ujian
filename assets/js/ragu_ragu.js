/**
 * Ragu-Ragu Functionality
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

const RaguRagu = {
    raguSoal: new Set(),
    
    init: function() {
        this.loadRaguSoal();
        this.setupMarkButtons();
        this.updateCounter();
        this.setupFilter();
    },
    
    loadRaguSoal: function() {
        // Load from server or localStorage
        const sesiId = document.querySelector('[data-sesi-id]')?.getAttribute('data-sesi-id');
        if (!sesiId) return;
        
        const saved = localStorage.getItem('ragu_soal_' + sesiId);
        if (saved) {
            try {
                const soalIds = JSON.parse(saved);
                this.raguSoal = new Set(soalIds);
            } catch (e) {
                console.error('Error loading ragu soal:', e);
            }
        }
        
        // Update UI
        this.raguSoal.forEach(soalId => {
            this.markAsRagu(soalId, false);
        });
    },
    
    setupMarkButtons: function() {
        document.querySelectorAll('[data-mark-ragu]').forEach(button => {
            button.addEventListener('click', (e) => {
                const soalId = button.getAttribute('data-mark-ragu');
                this.toggleRagu(soalId);
            });
        });
    },
    
    toggleRagu: function(soalId) {
        if (this.raguSoal.has(soalId)) {
            this.unmarkRagu(soalId);
        } else {
            this.markAsRagu(soalId);
        }
    },
    
    markAsRagu: function(soalId, save = true) {
        this.raguSoal.add(soalId);
        
        // Update UI
        const questionCard = document.querySelector(`[data-soal-id="${soalId}"]`);
        if (questionCard) {
            questionCard.classList.add('ragu');
            
            // Add badge
            let badge = questionCard.querySelector('.ragu-badge');
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'ragu-badge';
                badge.innerHTML = '<i class="fas fa-question-circle"></i> Ragu-ragu';
                questionCard.querySelector('.question-header')?.appendChild(badge);
            }
        }
        
        // Update button
        const button = document.querySelector(`[data-mark-ragu="${soalId}"]`);
        if (button) {
            button.classList.add('active');
            button.innerHTML = '<i class="fas fa-check"></i> Yakin';
        }
        
        if (save) {
            this.saveRaguSoal();
            this.updateCounter();
            this.sendToServer(soalId, 'ragu');
        }
    },
    
    unmarkRagu: function(soalId) {
        this.raguSoal.delete(soalId);
        
        // Update UI
        const questionCard = document.querySelector(`[data-soal-id="${soalId}"]`);
        if (questionCard) {
            questionCard.classList.remove('ragu');
            const badge = questionCard.querySelector('.ragu-badge');
            if (badge) badge.remove();
        }
        
        // Update button
        const button = document.querySelector(`[data-mark-ragu="${soalId}"]`);
        if (button) {
            button.classList.remove('active');
            button.innerHTML = '<i class="fas fa-question-circle"></i> Ragu-ragu';
        }
        
        this.saveRaguSoal();
        this.updateCounter();
        this.sendToServer(soalId, 'yakin');
    },
    
    saveRaguSoal: function() {
        const sesiId = document.querySelector('[data-sesi-id]')?.getAttribute('data-sesi-id');
        if (!sesiId) return;
        
        localStorage.setItem('ragu_soal_' + sesiId, JSON.stringify(Array.from(this.raguSoal)));
    },
    
    sendToServer: function(soalId, status) {
        const sesiId = document.querySelector('[data-sesi-id]')?.getAttribute('data-sesi-id');
        const ujianId = document.querySelector('[data-ujian-id]')?.getAttribute('data-ujian-id');
        
        if (!sesiId || !ujianId) return;
        
        UJAN.ajax('/UJAN/api/mark_ragu.php', {
            sesi_id: sesiId,
            ujian_id: ujianId,
            soal_id: soalId,
            status: status
        }, 'POST', (response) => {
            if (!response.success) {
                console.error('Failed to save ragu status:', response.message);
            }
        });
    },
    
    updateCounter: function() {
        const counter = document.getElementById('raguCounter');
        if (counter) {
            counter.textContent = this.raguSoal.size;
        }
        
        const summary = document.getElementById('raguSummary');
        if (summary) {
            if (this.raguSoal.size > 0) {
                summary.textContent = `Anda masih ragu pada ${this.raguSoal.size} soal`;
                summary.style.display = 'block';
            } else {
                summary.style.display = 'none';
            }
        }
    },
    
    setupFilter: function() {
        const filterBtn = document.getElementById('filterRagu');
        if (filterBtn) {
            let showRaguOnly = false;
            
            filterBtn.addEventListener('click', () => {
                showRaguOnly = !showRaguOnly;
                
                document.querySelectorAll('[data-soal-id]').forEach(card => {
                    const soalId = card.getAttribute('data-soal-id');
                    if (showRaguOnly) {
                        if (!this.raguSoal.has(soalId)) {
                            card.style.display = 'none';
                        } else {
                            card.style.display = 'block';
                        }
                    } else {
                        card.style.display = 'block';
                    }
                });
                
                filterBtn.textContent = showRaguOnly ? 'Tampilkan Semua' : 'Tampilkan Ragu-Ragu';
            });
        }
    },
    
    getRaguSoal: function() {
        return Array.from(this.raguSoal);
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('[data-sesi-id]')) {
        RaguRagu.init();
    }
});

