/**
 * Scripts para crear solicitud
 */

document.addEventListener('DOMContentLoaded', function() {
    const tipoUso = document.getElementById('tipo_uso');
    const fechaPublicacionGroup = document.getElementById('fecha_publicacion_group');

    if (tipoUso && fechaPublicacionGroup) {
        tipoUso.addEventListener('change', function() {
            if (this.value === 'Uso externo') {
                fechaPublicacionGroup.style.display = 'block';
                document.getElementById('fecha_publicacion').required = true;
            } else {
                fechaPublicacionGroup.style.display = 'none';
                document.getElementById('fecha_publicacion').required = false;
            }
        });
    }

    // Validar fecha estimada
    const fechaEstimada = document.getElementById('fecha_estimada_entrega');
    if (fechaEstimada) {
        const hoy = new Date().toISOString().split('T')[0];
        fechaEstimada.setAttribute('min', hoy);
        
        // Si no tiene valor, calcular 5 días hábiles desde hoy
        if (!fechaEstimada.value) {
            const fecha = new Date();
            let diasAgregados = 0;
            let diasHábiles = 0;
            
            while (diasHábiles < 5) {
                fecha.setDate(fecha.getDate() + 1);
                diasAgregados++;
                const diaSemana = fecha.getDay();
                // 0 = domingo, 6 = sábado
                if (diaSemana !== 0 && diaSemana !== 6) {
                    diasHábiles++;
                }
            }
            
            fechaEstimada.value = fecha.toISOString().split('T')[0];
        }
    }

    // Manejar campo "Otro" en tipo de requerimiento
    const tipoRequerimiento = document.getElementById('tipo_requerimiento');
    const tipoRequerimientoOtro = document.getElementById('tipo_requerimiento_otro');
    
    if (tipoRequerimiento && tipoRequerimientoOtro) {
        tipoRequerimiento.addEventListener('change', function() {
            if (this.value === 'Otro') {
                tipoRequerimientoOtro.style.display = 'block';
                tipoRequerimientoOtro.required = true;
            } else {
                tipoRequerimientoOtro.style.display = 'none';
                tipoRequerimientoOtro.required = false;
                tipoRequerimientoOtro.value = '';
            }
        });
    }
});

