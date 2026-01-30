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
    const nuevaFechaEntregaGroup = document.getElementById('nueva_fecha_entrega_group');
    const nuevaFechaInput = document.getElementById('nueva_fecha_estimada_entrega');
    const cambiarFechaEntregaBlock = document.getElementById('cambiar_fecha_entrega_block');

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
            // Mostrar bloque "¿Necesita cambiar la fecha de entrega?" solo cuando estado es "En proceso"
            if (cambiarFechaEntregaBlock) {
                if (this.value === 'En proceso') {
                    cambiarFechaEntregaBlock.style.display = 'block';
                } else {
                    cambiarFechaEntregaBlock.style.display = 'none';
                    if (nuevaFechaEntregaGroup) {
                        nuevaFechaEntregaGroup.style.display = 'none';
                        if (nuevaFechaInput) {
                            nuevaFechaInput.required = false;
                            nuevaFechaInput.value = '';
                        }
                    }
                    var noRadio = document.querySelector('input[name="cambiar_fecha_entrega"][value="0"]');
                    if (noRadio) noRadio.checked = true;
                }
            }
        });

        // Verificar estado inicial
        if (estado.value === 'Completada') {
            archivoFinalGroup.style.display = 'block';
            urlDriveGroup.style.display = 'block';
        }
        if (cambiarFechaEntregaBlock) {
            cambiarFechaEntregaBlock.style.display = (estado.value === 'En proceso') ? 'block' : 'none';
        }
    }

    // Mostrar/ocultar bloque de nueva fecha de entrega
    const cambiarFechaRadios = document.querySelectorAll('input[name="cambiar_fecha_entrega"]');

    if (cambiarFechaRadios.length && nuevaFechaEntregaGroup) {
        function toggleNuevaFechaEntrega() {
            const quiereCambiar = document.querySelector('input[name="cambiar_fecha_entrega"]:checked');
            if (quiereCambiar && quiereCambiar.value === '1') {
                nuevaFechaEntregaGroup.style.display = 'block';
                if (nuevaFechaInput) nuevaFechaInput.required = true;
            } else {
                nuevaFechaEntregaGroup.style.display = 'none';
                if (nuevaFechaInput) {
                    nuevaFechaInput.required = false;
                    nuevaFechaInput.value = '';
                }
            }
        }
        cambiarFechaRadios.forEach(function(radio) {
            radio.addEventListener('change', toggleNuevaFechaEntrega);
        });
        toggleNuevaFechaEntrega();
    }
});

