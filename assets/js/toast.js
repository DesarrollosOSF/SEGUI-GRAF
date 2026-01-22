/**
 * Sistema de notificaciones Toast
 * SEGUI-GRAF - Sistema de Seguimiento Gráfico
 */

class Toast {
    static show(message, type = 'info', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        
        // Estilos
        Object.assign(toast.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '1rem 1.5rem',
            borderRadius: '8px',
            boxShadow: '0 4px 6px rgba(0,0,0,0.1)',
            zIndex: '10000',
            animation: 'slideIn 0.3s ease-out',
            maxWidth: '400px',
            wordWrap: 'break-word'
        });
        
        // Colores según tipo
        const colors = {
            success: { bg: '#10b981', color: '#fff' },
            error: { bg: '#ef4444', color: '#fff' },
            warning: { bg: '#f59e0b', color: '#fff' },
            info: { bg: '#3b82f6', color: '#fff' }
        };
        
        const color = colors[type] || colors.info;
        toast.style.backgroundColor = color.bg;
        toast.style.color = color.color;
        
        document.body.appendChild(toast);
        
        // Auto-remover
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }, duration);
        
        // Click para cerrar
        toast.addEventListener('click', () => {
            toast.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        });
    }
    
    static success(message, duration) {
        this.show(message, 'success', duration);
    }
    
    static error(message, duration) {
        this.show(message, 'error', duration);
    }
    
    static warning(message, duration) {
        this.show(message, 'warning', duration);
    }
    
    static info(message, duration) {
        this.show(message, 'info', duration);
    }
}

// Agregar animaciones CSS si no existen
if (!document.getElementById('toast-styles')) {
    const style = document.createElement('style');
    style.id = 'toast-styles';
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

