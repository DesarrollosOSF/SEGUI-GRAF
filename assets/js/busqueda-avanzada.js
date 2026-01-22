/**
 * Búsqueda avanzada con autocompletado
 * SEGUI-GRAF - Sistema de Seguimiento Gráfico
 */

document.addEventListener('DOMContentLoaded', function() {
    const busquedaInput = document.querySelector('input[name="busqueda"]');
    if (!busquedaInput) return;
    
    let timeout;
    const suggestionsContainer = document.createElement('div');
    suggestionsContainer.className = 'search-suggestions';
    suggestionsContainer.style.cssText = `
        position: absolute;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        max-height: 300px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
        width: 100%;
        margin-top: 4px;
    `;
    
    const searchSection = busquedaInput.closest('.search-section');
    if (searchSection) {
        searchSection.style.position = 'relative';
        searchSection.appendChild(suggestionsContainer);
    }
    
    // Búsqueda con debounce
    busquedaInput.addEventListener('input', function() {
        clearTimeout(timeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            suggestionsContainer.style.display = 'none';
            return;
        }
        
        timeout = setTimeout(() => {
            // Aquí se podría hacer una búsqueda AJAX
            // Por ahora solo ocultamos si hay menos de 2 caracteres
        }, 300);
    });
    
    // Cerrar sugerencias al hacer click fuera
    document.addEventListener('click', function(e) {
        if (!searchSection.contains(e.target)) {
            suggestionsContainer.style.display = 'none';
        }
    });
});

