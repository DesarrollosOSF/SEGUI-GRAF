/**
 * Vista previa de archivos
 * SEGUI-GRAF - Sistema de Seguimiento Gráfico
 */

document.addEventListener('DOMContentLoaded', function() {
    // Agregar preview a enlaces de archivos
    const archivoLinks = document.querySelectorAll('.archivo-link');
    
    archivoLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (!href) return;
        
        const extension = href.split('.').pop().toLowerCase();
        const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension);
        const isPDF = extension === 'pdf';
        
        if (isImage || isPDF) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                showPreview(href, extension, isImage);
            });
        }
    });
});

function showPreview(url, extension, isImage) {
    // Crear overlay
    const overlay = document.createElement('div');
    overlay.className = 'file-preview-overlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    `;
    
    // Crear contenedor
    const container = document.createElement('div');
    container.style.cssText = `
        max-width: 90%;
        max-height: 90%;
        position: relative;
    `;
    
    // Crear contenido
    let content;
    if (isImage) {
        content = document.createElement('img');
        content.src = url;
        content.style.cssText = `
            max-width: 100%;
            max-height: 90vh;
            object-fit: contain;
            border-radius: 8px;
        `;
    } else if (extension === 'pdf') {
        content = document.createElement('iframe');
        content.src = url;
        content.style.cssText = `
            width: 80vw;
            height: 90vh;
            border: none;
            border-radius: 8px;
        `;
    }
    
    // Botón cerrar
    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '✕';
    closeBtn.style.cssText = `
        position: absolute;
        top: -40px;
        right: 0;
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    container.appendChild(content);
    container.appendChild(closeBtn);
    overlay.appendChild(container);
    document.body.appendChild(overlay);
    
    // Cerrar al hacer click
    const close = () => {
        overlay.style.opacity = '0';
        setTimeout(() => overlay.remove(), 300);
    };
    
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay || e.target === closeBtn) {
            close();
        }
    });
    
    // Cerrar con ESC
    const escHandler = (e) => {
        if (e.key === 'Escape') {
            close();
            document.removeEventListener('keydown', escHandler);
        }
    };
    document.addEventListener('keydown', escHandler);
}

