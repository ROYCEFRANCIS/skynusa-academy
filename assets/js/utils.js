/**
 * SKYNUSA ACADEMY - UTILITY FUNCTIONS
 * Interactive Features & Helper Functions
 */

// ==============================================
// TOAST NOTIFICATIONS
// ==============================================

const Toast = {
    container: null,
    
    init() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
    },
    
    show(message, type = 'info', duration = 3000) {
        this.init();
        
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-icon">${icons[type]}</div>
            <div class="toast-content">
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>
        `;
        
        this.container.appendChild(toast);
        
        // Auto remove
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },
    
    success(message, duration) {
        this.show(message, 'success', duration);
    },
    
    error(message, duration) {
        this.show(message, 'error', duration);
    },
    
    warning(message, duration) {
        this.show(message, 'warning', duration);
    },
    
    info(message, duration) {
        this.show(message, 'info', duration);
    }
};

// Add slideOutRight animation
const style = document.createElement('style');
style.textContent = `
@keyframes slideOutRight {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(100%);
    }
}
`;
document.head.appendChild(style);

// ==============================================
// MODAL SYSTEM
// ==============================================

const Modal = {
    open(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    },
    
    close(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    },
    
    confirm(title, message, onConfirm, onCancel) {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.innerHTML = `
            <div class="modal" style="max-width: 400px;">
                <div class="modal-header">
                    <h3 class="modal-title">${title}</h3>
                </div>
                <div class="modal-body">
                    <p>${message}</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="this.closest('.modal-overlay').remove(); document.body.style.overflow = '';">
                        Cancel
                    </button>
                    <button class="btn btn-primary" id="modal-confirm-btn">
                        Confirm
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';
        
        // Close on overlay click
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.remove();
                document.body.style.overflow = '';
                if (onCancel) onCancel();
            }
        });
        
        // Confirm button
        document.getElementById('modal-confirm-btn').addEventListener('click', () => {
            overlay.remove();
            document.body.style.overflow = '';
            if (onConfirm) onConfirm();
        });
    }
};

// ==============================================
// FORM VALIDATION
// ==============================================

const FormValidator = {
    validate(formId) {
        const form = document.getElementById(formId);
        if (!form) return false;
        
        let isValid = true;
        const inputs = form.querySelectorAll('[required]');
        
        inputs.forEach(input => {
            this.clearError(input);
            
            if (!input.value.trim()) {
                this.showError(input, 'This field is required');
                isValid = false;
            } else if (input.type === 'email' && !this.isValidEmail(input.value)) {
                this.showError(input, 'Please enter a valid email');
                isValid = false;
            } else if (input.type === 'tel' && !this.isValidPhone(input.value)) {
                this.showError(input, 'Please enter a valid phone number');
                isValid = false;
            }
        });
        
        return isValid;
    },
    
    showError(input, message) {
        input.classList.add('error');
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'form-error';
        errorDiv.textContent = message;
        
        input.parentElement.appendChild(errorDiv);
    },
    
    clearError(input) {
        input.classList.remove('error');
        const errorDiv = input.parentElement.querySelector('.form-error');
        if (errorDiv) errorDiv.remove();
    },
    
    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    },
    
    isValidPhone(phone) {
        return /^[\d\s\-\+\(\)]{10,}$/.test(phone);
    }
};

// ==============================================
// LOADING STATES
// ==============================================

const Loading = {
    show() {
        if (document.getElementById('loading-overlay')) return;
        
        const overlay = document.createElement('div');
        overlay.id = 'loading-overlay';
        overlay.className = 'loading-overlay';
        overlay.innerHTML = '<div class="spinner spinner-lg"></div>';
        
        document.body.appendChild(overlay);
    },
    
    hide() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) overlay.remove();
    },
    
    button(buttonElement, isLoading) {
        if (isLoading) {
            buttonElement.disabled = true;
            buttonElement.classList.add('btn-loading');
            buttonElement.setAttribute('data-original-text', buttonElement.textContent);
        } else {
            buttonElement.disabled = false;
            buttonElement.classList.remove('btn-loading');
            const originalText = buttonElement.getAttribute('data-original-text');
            if (originalText) buttonElement.textContent = originalText;
        }
    }
};

// ==============================================
// SEARCH & FILTER
// ==============================================

const Search = {
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    filterTable(searchValue, tableId, columns = []) {
        const table = document.getElementById(tableId);
        if (!table) return;
        
        const rows = table.querySelectorAll('tbody tr');
        const search = searchValue.toLowerCase();
        
        rows.forEach(row => {
            let text = '';
            
            if (columns.length > 0) {
                columns.forEach(colIndex => {
                    const cell = row.cells[colIndex];
                    if (cell) text += cell.textContent.toLowerCase() + ' ';
                });
            } else {
                text = row.textContent.toLowerCase();
            }
            
            row.style.display = text.includes(search) ? '' : 'none';
        });
    }
};

// ==============================================
// COPY TO CLIPBOARD
// ==============================================

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        Toast.success('Copied to clipboard!');
    }).catch(() => {
        Toast.error('Failed to copy');
    });
}

// ==============================================
// DATE FORMATTING
// ==============================================

const DateFormatter = {
    format(date, format = 'dd MMM yyyy') {
        const d = new Date(date);
        const day = String(d.getDate()).padStart(2, '0');
        const month = d.toLocaleString('default', { month: 'short' });
        const year = d.getFullYear();
        
        return format
            .replace('dd', day)
            .replace('MMM', month)
            .replace('yyyy', year);
    },
    
    relative(date) {
        const now = new Date();
        const past = new Date(date);
        const diffMs = now - past;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins} minutes ago`;
        if (diffHours < 24) return `${diffHours} hours ago`;
        if (diffDays < 7) return `${diffDays} days ago`;
        
        return this.format(date);
    }
};

// ==============================================
// DARK MODE TOGGLE
// ==============================================

const DarkMode = {
    init() {
        const isDark = localStorage.getItem('darkMode') === 'true';
        if (isDark) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    },
    
    toggle() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        
        if (isDark) {
            document.documentElement.removeAttribute('data-theme');
            localStorage.setItem('darkMode', 'false');
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('darkMode', 'true');
        }
    }
};

// Initialize dark mode on page load
document.addEventListener('DOMContentLoaded', () => {
    DarkMode.init();
});

// ==============================================
// CONFIRM DELETE
// ==============================================

function confirmDelete(message = 'Are you sure you want to delete this item?') {
    return new Promise((resolve) => {
        Modal.confirm(
            'Confirm Delete',
            message,
            () => resolve(true),
            () => resolve(false)
        );
    });
}

// ==============================================
// AUTO SAVE FORM
// ==============================================

const AutoSave = {
    timer: null,
    
    enable(formId, saveFunction, delay = 2000) {
        const form = document.getElementById(formId);
        if (!form) return;
        
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            input.addEventListener('input', () => {
                clearTimeout(this.timer);
                this.showSaving();
                
                this.timer = setTimeout(() => {
                    saveFunction();
                    this.showSaved();
                }, delay);
            });
        });
    },
    
    showSaving() {
        let indicator = document.getElementById('autosave-indicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'autosave-indicator';
            indicator.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 8px 16px;
                background: #fef3c7;
                color: #92400e;
                border-radius: 8px;
                font-size: 13px;
                font-weight: 600;
                z-index: 9999;
            `;
            document.body.appendChild(indicator);
        }
        indicator.textContent = '💾 Saving...';
        indicator.style.display = 'block';
    },
    
    showSaved() {
        const indicator = document.getElementById('autosave-indicator');
        if (indicator) {
            indicator.textContent = '✅ Saved';
            indicator.style.background = '#d1fae5';
            indicator.style.color = '#065f46';
            
            setTimeout(() => {
                indicator.style.display = 'none';
            }, 2000);
        }
    }
};

// ==============================================
// IMAGE PREVIEW
// ==============================================

function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const preview = document.getElementById(previewId);
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

// ==============================================
// PAGINATION
// ==============================================

const Pagination = {
    itemsPerPage: 10,
    currentPage: 1,
    
    init(tableId, itemsPerPage = 10) {
        this.itemsPerPage = itemsPerPage;
        this.currentPage = 1;
        this.render(tableId);
    },
    
    render(tableId) {
        const table = document.getElementById(tableId);
        if (!table) return;
        
        const rows = Array.from(table.querySelectorAll('tbody tr'));
        const totalPages = Math.ceil(rows.length / this.itemsPerPage);
        
        // Hide all rows
        rows.forEach(row => row.style.display = 'none');
        
        // Show current page rows
        const start = (this.currentPage - 1) * this.itemsPerPage;
        const end = start + this.itemsPerPage;
        rows.slice(start, end).forEach(row => row.style.display = '');
        
        // Render pagination controls
        this.renderControls(tableId, totalPages);
    },
    
    renderControls(tableId, totalPages) {
        let controls = document.getElementById(`${tableId}-pagination`);
        
        if (!controls) {
            controls = document.createElement('div');
            controls.id = `${tableId}-pagination`;
            controls.className = 'pagination-controls';
            controls.style.cssText = `
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 8px;
                margin-top: 20px;
            `;
            document.getElementById(tableId).parentElement.appendChild(controls);
        }
        
        controls.innerHTML = '';
        
        // Previous button
        const prevBtn = this.createButton('‹', this.currentPage > 1, () => {
            this.currentPage--;
            this.render(tableId);
        });
        controls.appendChild(prevBtn);
        
        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (
                i === 1 ||
                i === totalPages ||
                (i >= this.currentPage - 1 && i <= this.currentPage + 1)
            ) {
                const pageBtn = this.createButton(i, true, () => {
                    this.currentPage = i;
                    this.render(tableId);
                }, i === this.currentPage);
                controls.appendChild(pageBtn);
            } else if (
                i === this.currentPage - 2 ||
                i === this.currentPage + 2
            ) {
                const dots = document.createElement('span');
                dots.textContent = '...';
                dots.style.padding = '8px';
                controls.appendChild(dots);
            }
        }
        
        // Next button
        const nextBtn = this.createButton('›', this.currentPage < totalPages, () => {
            this.currentPage++;
            this.render(tableId);
        });
        controls.appendChild(nextBtn);
    },
    
    createButton(text, enabled, onClick, active = false) {
        const btn = document.createElement('button');
        btn.textContent = text;
        btn.className = 'btn btn-sm';
        btn.style.cssText = `
            min-width: 36px;
            padding: 8px 12px;
            ${active ? 'background: linear-gradient(135deg, #667eea, #764ba2); color: white;' : ''}
        `;
        
        if (!enabled) {
            btn.disabled = true;
            btn.style.opacity = '0.5';
        } else {
            btn.addEventListener('click', onClick);
        }
        
        return btn;
    }
};

// ==============================================
// EXPORT TO CSV
// ==============================================

function exportTableToCSV(tableId, filename = 'export.csv') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = Array.from(table.querySelectorAll('tr'));
    const csv = rows.map(row => {
        const cells = Array.from(row.querySelectorAll('th, td'));
        return cells.map(cell => `"${cell.textContent.trim()}"`).join(',');
    }).join('\n');
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    window.URL.revokeObjectURL(url);
    
    Toast.success('Table exported successfully!');
}

// ==============================================
// PRINT PAGE
// ==============================================

function printElement(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Print</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
                th { background: #f5f5f5; font-weight: bold; }
                @media print {
                    button { display: none; }
                }
            </style>
        </head>
        <body>
            ${element.innerHTML}
            <script>
                window.onload = function() {
                    window.print();
                    window.onafterprint = function() {
                        window.close();
                    };
                };
            </script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// ==============================================
// GLOBAL ERROR HANDLER
// ==============================================

window.addEventListener('error', (event) => {
    console.error('Global error:', event.error);
    Toast.error('An error occurred. Please try again.');
});

// ==============================================
// SMOOTH SCROLL
// ==============================================

function smoothScroll(targetId) {
    const element = document.getElementById(targetId);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// ==============================================
// INITIALIZE ON DOM READY
// ==============================================

document.addEventListener('DOMContentLoaded', () => {
    // Close alerts
    document.querySelectorAll('.alert-close').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.closest('.alert').remove();
        });
    });
    
    // Auto-hide alerts after 5 seconds
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            if (alert.parentElement) {
                alert.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => alert.remove(), 300);
            }
        }, 5000);
    });
    
    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
    });
    
    // Prevent form resubmission on page refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        Toast,
        Modal,
        FormValidator,
        Loading,
        Search,
        DateFormatter,
        DarkMode,
        AutoSave,
        Pagination
    };
}