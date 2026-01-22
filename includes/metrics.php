<?php
/**
 * Funciones para cálculo de métricas
 * SEGUI-GRAF - Sistema de Seguimiento Gráfico
 */

/**
 * Actualizar métricas de una solicitud
 */
function updateMetrics($conn, $solicitud_id) {
    // Obtener datos de la solicitud
    $stmt = $conn->prepare("
        SELECT fecha_solicitud, fecha_asignacion, fecha_inicio_proceso, 
               fecha_completada, fecha_estimada_entrega
        FROM solicitudes
        WHERE id = ?
    ");
    $stmt->execute([$solicitud_id]);
    $solicitud = $stmt->fetch();

    if (!$solicitud) {
        return;
    }

    $tiempo_respuesta = null;
    $tiempo_proceso = null;
    $tiempo_total = null;
    $cumplimiento_entrega = null;
    $dias_anticipacion = null;

    // Calcular tiempo de respuesta (desde solicitud hasta asignación/aprobación)
    if ($solicitud['fecha_asignacion']) {
        $tiempo_respuesta = calcularHoras($solicitud['fecha_solicitud'], $solicitud['fecha_asignacion']);
    }

    // Calcular tiempo de proceso (desde inicio hasta completada)
    if ($solicitud['fecha_inicio_proceso'] && $solicitud['fecha_completada']) {
        $tiempo_proceso = calcularHoras($solicitud['fecha_inicio_proceso'], $solicitud['fecha_completada']);
    }

    // Calcular tiempo total
    if ($solicitud['fecha_completada']) {
        $tiempo_total = calcularHoras($solicitud['fecha_solicitud'], $solicitud['fecha_completada']);
    }

    // Verificar cumplimiento de entrega
    if ($solicitud['fecha_completada'] && $solicitud['fecha_estimada_entrega']) {
        $fecha_completada = new DateTime($solicitud['fecha_completada']);
        $fecha_estimada = new DateTime($solicitud['fecha_estimada_entrega']);
        $cumplimiento_entrega = $fecha_completada <= $fecha_estimada ? 1 : 0;
        
        // Calcular días de anticipación o retraso
        $diff = $fecha_estimada->diff($fecha_completada);
        $dias_anticipacion = $fecha_completada <= $fecha_estimada 
            ? $diff->days 
            : -$diff->days;
    }

    // Insertar o actualizar métricas
    $stmt = $conn->prepare("
        INSERT INTO metricas_solicitudes 
        (solicitud_id, tiempo_respuesta_horas, tiempo_proceso_horas, tiempo_total_horas, 
         cumplimiento_entrega, dias_anticipacion)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            tiempo_respuesta_horas = VALUES(tiempo_respuesta_horas),
            tiempo_proceso_horas = VALUES(tiempo_proceso_horas),
            tiempo_total_horas = VALUES(tiempo_total_horas),
            cumplimiento_entrega = VALUES(cumplimiento_entrega),
            dias_anticipacion = VALUES(dias_anticipacion)
    ");
    $stmt->execute([
        $solicitud_id,
        $tiempo_respuesta,
        $tiempo_proceso,
        $tiempo_total,
        $cumplimiento_entrega,
        $dias_anticipacion
    ]);
}

/**
 * Obtener métricas generales
 */
function getGeneralMetrics($conn, $admin_id = null) {
    $metrics = [];

    // Total de solicitudes
    if ($admin_id) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM solicitudes WHERE administrador_id = ?");
        $stmt->execute([$admin_id]);
    } else {
        $stmt = $conn->query("SELECT COUNT(*) FROM solicitudes");
    }
    $metrics['total'] = $stmt->fetchColumn();

    // Solicitudes completadas
    if ($admin_id) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM solicitudes WHERE estado = 'Completada' AND administrador_id = ?");
        $stmt->execute([$admin_id]);
    } else {
        $stmt = $conn->query("SELECT COUNT(*) FROM solicitudes WHERE estado = 'Completada'");
    }
    $metrics['completadas'] = $stmt->fetchColumn();

    // Tiempo promedio de respuesta
    if ($admin_id) {
        $stmt = $conn->prepare("
            SELECT AVG(m.tiempo_respuesta_horas) 
            FROM metricas_solicitudes m
            INNER JOIN solicitudes s ON m.solicitud_id = s.id
            WHERE m.tiempo_respuesta_horas IS NOT NULL AND s.administrador_id = ?
        ");
        $stmt->execute([$admin_id]);
    } else {
        $stmt = $conn->query("
            SELECT AVG(m.tiempo_respuesta_horas) 
            FROM metricas_solicitudes m
            WHERE m.tiempo_respuesta_horas IS NOT NULL
        ");
    }
    $metrics['tiempo_respuesta_promedio'] = round($stmt->fetchColumn() ?: 0, 2);

    // Tiempo promedio de proceso
    if ($admin_id) {
        $stmt = $conn->prepare("
            SELECT AVG(m.tiempo_proceso_horas) 
            FROM metricas_solicitudes m
            INNER JOIN solicitudes s ON m.solicitud_id = s.id
            WHERE m.tiempo_proceso_horas IS NOT NULL AND s.administrador_id = ?
        ");
        $stmt->execute([$admin_id]);
    } else {
        $stmt = $conn->query("
            SELECT AVG(m.tiempo_proceso_horas) 
            FROM metricas_solicitudes m
            WHERE m.tiempo_proceso_horas IS NOT NULL
        ");
    }
    $metrics['tiempo_proceso_promedio'] = round($stmt->fetchColumn() ?: 0, 2);

    // Porcentaje de cumplimiento
    if ($admin_id) {
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(m.cumplimiento_entrega) as cumplidas
            FROM metricas_solicitudes m
            INNER JOIN solicitudes s ON m.solicitud_id = s.id
            WHERE m.cumplimiento_entrega IS NOT NULL AND s.administrador_id = ?
        ");
        $stmt->execute([$admin_id]);
    } else {
        $stmt = $conn->query("
            SELECT 
                COUNT(*) as total,
                SUM(m.cumplimiento_entrega) as cumplidas
            FROM metricas_solicitudes m
            WHERE m.cumplimiento_entrega IS NOT NULL
        ");
    }
    $cumplimiento = $stmt->fetch();
    $metrics['porcentaje_cumplimiento'] = $cumplimiento['total'] > 0 
        ? round(($cumplimiento['cumplidas'] / $cumplimiento['total']) * 100, 2)
        : 0;

    return $metrics;
}
?>

