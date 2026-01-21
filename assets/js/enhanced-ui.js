/**
 * SKYNUSA ACADEMY - Enhanced UI Components
 * Modern Interactive Elements
 */

// Toast Notification System
const toast = {
    container: null,
    
    init() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            this.container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 99999;
                display: flex;
                flex-direction: column;
                gap: 10px;
                max-width: 400px;
            `;
            document.body.appendChild(this.container);
        }
    },
    
    show(message, type = 'info', duration = 4000) {
        this.init();
        
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };
        
        const colors = {
            success: { bg: '#d1fae5', text: '#065f46', border: '#10b981' },
            error: { bg: '#fee2e2', text: '#991b1b', border: '#ef4444' },
            warning: { bg: '#fef3c7', text: '#92400e', border: '#f59e0b' },
            info: { bg: '#dbeafe', text: '#1e40af', border: '#3b82f6' }
        };
        
        const color = colors[type] || colors.info;
        
        const toastEl = document.createElement('div');
        toastEl.style.cssText = `
            background: ${color.bg};
            color: ${color.text};
            padding: 16px 20px;
            border-radius: 12px;
            border-left: 4px solid ${color.border};
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
            animation: slideInRight 0.3s ease-out;
            font-weight: 500;
        `;
        
        toastEl.innerHTML = `
            <span style="font-size: 20px;">${icons[type]}</span>
            <span style="flex: 1;">${message}</span>
            <button onclick="this.parentElement.remove()" style="
                background: none;
                border: none;
                color: ${color.text};
                font-size: 20px;
                cursor: pointer;
                opacity: 0.6;
                padding: 0;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
            ">×</button>
        `;
        
        this.container.appendChild(toastEl);
        
        setTimeout(() => {
            toastEl.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => toastEl.remove(), 300);
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

// Add animations
const style = document.createElement('style');
style.textContent = `
@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

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

// Confirm Dialog
async function confirm(options) {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 99998;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.2s ease;
        `;
        
        const modal = document.createElement('div');
        modal.style.cssText = `
            background: white;
            border-radius: 16px;
            padding: 0;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: scaleIn 0.3s ease;
        `;
        
        const type = options.type || 'info';
        const colors = {
            danger: '#ef4444',
            warning: '#f59e0b',
            info: '#3b82f6',
            success: '#10b981'
        };
        
        modal.innerHTML = `
            <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                <h3 style="font-size: 20px; font-weight: 700; color: #1a1a1a; margin-bottom: 12px;">
                    ${options.title || 'Confirm'}
                </h3>
                <p style="color: #6b7280; line-height: 1.6;">
                    ${options.message || 'Are you sure?'}
                </p>
            </div>
            <div style="padding: 20px; display: flex; gap: 12px; justify-content: flex-end;">
                <button id="cancel-btn" style="
                    padding: 10px 20px;
                    border: none;
                    border-radius: 8px;
                    background: #e5e7eb;
                    color: #4b5563;
                    font-weight: 600;
                    cursor: pointer;
                    font-size: 14px;
                ">${options.cancelText || 'Cancel'}</button>
                <button id="confirm-btn" style="
                    padding: 10px 20px;
                    border: none;
                    border-radius: 8px;
                    background: ${colors[type]};
                    color: white;
                    font-weight: 600;
                    cursor: pointer;
                    font-size: 14px;
                ">${options.confirmText || 'Confirm'}</button>
            </div>
        `;
        
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';
        
        const cleanup = (result) => {
            overlay.style.animation = 'fadeOut 0.2s ease';
            setTimeout(() => {
                overlay.remove();
                document.body.style.overflow = '';
                resolve(result);
            }, 200);
        };
        
        modal.querySelector('#cancel-btn').onclick = () => cleanup(false);
        modal.querySelector('#confirm-btn').onclick = () => cleanup(true);
        overlay.onclick = (e) => {
            if (e.target === overlay) cleanup(false);
        };
    });
}

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            if (alert.parentElement) {
                alert.style.transition = 'all 0.3s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateX(100%)';
                setTimeout(() => alert.remove(), 300);
            }
        }, 5000);
    });
});

// Add fade animations
const fadeStyle = document.createElement('style');
fadeStyle.textContent = `
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
`;
document.head.appendChild(fadeStyle);

// Export for global use
window.toast = toast;
window.confirm = confirm;