/**
 * Scripts para gestionar solicitud
 */

document.addEventListener('DOMContentLoaded', function() {
    const prioridadAjustada = document.getElementById('prioridad_ajustada');
    const justificacionGroup = document.getElementById('justificacion_group');

    if (prioridadAjustada && justificacionGroup) {
        prioridadAjustada.addEventListener('change', function() {
            if (this.value && this.value !== '') {
                justificacionGroup.style.display = 'block';
                document.getElementById('justificacion_prioridad').required = true;
            } else {
                justificacionGroup.style.display = 'none';
                document.getElementById('justificacion_prioridad').required = false;
            }
        });
    }

    // Mostrar campo de archivo final y URL de drive cuando se selecciona "Completada"
    const estado = document.getElementById('estado');
    const archivoFinalGroup = document.getElementById('archivo_final_group');
    const archivoFinal = document.getElementById('archivo_final');
    const urlDriveGroup = document.getElementById('url_drive_group');
    const urlDrive = document.getElementById('url_drive');

    if (estado && archivoFinalGroup && archivoFinal && urlDriveGroup && urlDrive) {
        estado.addEventListener('change', function() {
            if (this.value === 'Completada') {
                archivoFinalGroup.style.display = 'block';
                urlDriveGroup.style.display = 'block';
                // El archivo no es obligatorio si hay URL, y viceversa
                // La validación se hace en el servidor
            } else {
                archivoFinalGroup.style.display = 'none';
                urlDriveGroup.style.display = 'none';
                archivoFinal.required = false;
                urlDrive.required = false;
            }
        });

        // Verificar estado inicial
        if (estado.value === 'Completada') {
            archivoFinalGroup.style.display = 'block';
            urlDriveGroup.style.display = 'block';
        }
    }
});

