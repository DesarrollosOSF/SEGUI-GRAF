<?php
/**
 * Funciones para manejo de notificaciones
 * SEGUI-GRAF - Sistema de Seguimiento Gráfico
 */

/**
 * Crear notificación
 */
function crearNotificacion($conn, $usuario_id, $tipo, $titulo, $mensaje, $enlace = null) {
    $stmt = $conn->prepare("
        INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, enlace)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $usuario_id,
        $tipo,
        sanitize($titulo),
        sanitize($mensaje),
        $enlace
    ]);
    return $conn->lastInsertId();
}

/**
 * Obtener notificaciones de un usuario
 */
function obtenerNotificaciones($conn, $usuario_id, $solo_no_leidas = false) {
    $sql = "
        SELECT * FROM notificaciones 
        WHERE usuario_id = ?
    ";
    if ($solo_no_leidas) {
        $sql .= " AND leida = 0";
    }
    $sql .= " ORDER BY fecha_creacion DESC LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$usuario_id]);
    return $stmt->fetchAll();
}

/**
 * Contar notificaciones no leídas
 */
function contarNotificacionesNoLeidas($conn, $usuario_id) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM notificaciones 
        WHERE usuario_id = ? AND leida = 0
    ");
    $stmt->execute([$usuario_id]);
    return $stmt->fetchColumn();
}

/**
 * Marcar notificación como leída
 */
function marcarNotificacionLeida($conn, $notificacion_id) {
    $stmt = $conn->prepare("
        UPDATE notificaciones SET leida = 1 WHERE id = ?
    ");
    $stmt->execute([$notificacion_id]);
}

/**
 * Marcar todas las notificaciones como leídas
 */
function marcarTodasLeidas($conn, $usuario_id) {
    $stmt = $conn->prepare("
        UPDATE notificaciones SET leida = 1 WHERE usuario_id = ?
    ");
    $stmt->execute([$usuario_id]);
}

/**
 * Notificar cambio de estado
 */
function notificarCambioEstado($conn, $solicitud_id, $estado_anterior, $estado_nuevo, $usuario_solicitante_id, $admin_id) {
    $titulo = "Estado de solicitud actualizado";
    $mensaje = "La solicitud #{$solicitud_id} cambió de estado: {$estado_anterior} → {$estado_nuevo}";
    $enlace = url("ver_solicitud.php?id={$solicitud_id}");
    
    crearNotificacion($conn, $usuario_solicitante_id, 'estado_cambio', $titulo, $mensaje, $enlace);
}

/**
 * Notificar nuevo comentario
 */
function notificarNuevoComentario($conn, $solicitud_id, $comentario_id, $usuario_comentario_id, $otro_usuario_id) {
    $stmt = $conn->prepare("SELECT titulo FROM solicitudes WHERE id = ?");
    $stmt->execute([$solicitud_id]);
    $solicitud = $stmt->fetch();
    
    $titulo = "Nuevo comentario en solicitud";
    $mensaje = "Hay un nuevo comentario en la solicitud: " . sanitize($solicitud['titulo']);
    $enlace = url("ver_solicitud.php?id={$solicitud_id}#comentario-{$comentario_id}");
    
    crearNotificacion($conn, $otro_usuario_id, 'comentario', $titulo, $mensaje, $enlace);
}
?>

