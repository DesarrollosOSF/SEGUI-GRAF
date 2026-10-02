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
 * Notifica al solicitante y a los demás administradores (excepto quien realiza el cambio).
 */
function notificarCambioEstado($conn, $solicitud_id, $estado_anterior, $estado_nuevo, $usuario_solicitante_id, $admin_id) {
    $titulo = "Estado de solicitud actualizado";
    $mensaje = "La solicitud #{$solicitud_id} cambió de estado: {$estado_anterior} → {$estado_nuevo}";
    $enlace = url("ver_solicitud.php?id={$solicitud_id}");

    $ya_notificados = [];

    // 1. Notificar al usuario solicitante (si no es quien hizo el cambio)
    if (!empty($usuario_solicitante_id) && (int)$usuario_solicitante_id !== (int)$admin_id) {
        crearNotificacion($conn, $usuario_solicitante_id, 'estado_cambio', $titulo, $mensaje, $enlace);
        $ya_notificados[] = (int)$usuario_solicitante_id;
    }

    // 2. Notificar a los demás administradores para seguimiento oportuno
    $ya_notificados[] = (int)$admin_id;
    $admins = obtenerIdsAdministradores($conn, $admin_id);
    foreach ($admins as $admin_notif_id) {
        if (in_array((int)$admin_notif_id, $ya_notificados, true)) {
            continue;
        }
        crearNotificacion($conn, $admin_notif_id, 'estado_cambio', $titulo, $mensaje, $enlace);
        $ya_notificados[] = (int)$admin_notif_id;
    }
}

/**
 * Obtener IDs de administradores activos, opcionalmente excluyendo a un usuario.
 */
function obtenerIdsAdministradores($conn, $excluir_id = null) {
    if (!empty($excluir_id)) {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE perfil = 'Administrador' AND activo = 1 AND id != ?");
        $stmt->execute([(int)$excluir_id]);
    } else {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE perfil = 'Administrador' AND activo = 1");
        $stmt->execute();
    }
    return array_map('intval', array_column($stmt->fetchAll(), 'id'));
}

/**
 * Notificar a todos los administradores (excepto al actor) sobre una nueva solicitud.
 */
function notificarNuevaSolicitud($conn, $solicitud_id, $creador_id, $titulo_solicitud = '') {
    $titulo = "Nueva solicitud recibida";
    $mensaje = "Se ha creado la solicitud #{$solicitud_id}" . ($titulo_solicitud !== '' ? ": " . $titulo_solicitud : '');
    $enlace = url("ver_solicitud.php?id={$solicitud_id}");

    $admins = obtenerIdsAdministradores($conn, $creador_id);
    foreach ($admins as $admin_id) {
        crearNotificacion($conn, $admin_id, 'nueva_solicitud', $titulo, $mensaje, $enlace);
    }
}

/**
 * Notificar cualquier actualización de una solicitud (prioridad, observaciones,
 * fecha de entrega, archivo final, URL, etc.) al solicitante y a los administradores.
 */
function notificarActualizacionSolicitud($conn, $solicitud_id, $detalle_cambio, $solicitante_id, $actor_id) {
    $titulo = "Solicitud actualizada";
    $mensaje = "La solicitud #{$solicitud_id} fue actualizada: {$detalle_cambio}";
    $enlace = url("ver_solicitud.php?id={$solicitud_id}");

    $ya_notificados = [(int)$actor_id];

    if (!empty($solicitante_id) && !in_array((int)$solicitante_id, $ya_notificados, true)) {
        crearNotificacion($conn, $solicitante_id, 'solicitud_actualizada', $titulo, $mensaje, $enlace);
        $ya_notificados[] = (int)$solicitante_id;
    }

    $admins = obtenerIdsAdministradores($conn, $actor_id);
    foreach ($admins as $admin_id) {
        if (in_array((int)$admin_id, $ya_notificados, true)) {
            continue;
        }
        crearNotificacion($conn, $admin_id, 'solicitud_actualizada', $titulo, $mensaje, $enlace);
        $ya_notificados[] = (int)$admin_id;
    }
}

/**
 * Notificar un nuevo comentario al solicitante y a todos los administradores
 * (excepto al autor), evitando duplicados.
 */
function notificarComentarioConAdmins($conn, $solicitud_id, $comentario_id, $autor_id, $solicitante_id, $titulo_solicitud = '') {
    $titulo = "Nuevo comentario en solicitud";
    $mensaje = "Hay un nuevo comentario en la solicitud #{$solicitud_id}" . ($titulo_solicitud !== '' ? ": " . $titulo_solicitud : '');
    $enlace = url("ver_solicitud.php?id={$solicitud_id}#comentario-{$comentario_id}");

    $ya_notificados = [(int)$autor_id];

    if (!empty($solicitante_id) && !in_array((int)$solicitante_id, $ya_notificados, true)) {
        crearNotificacion($conn, $solicitante_id, 'comentario', $titulo, $mensaje, $enlace);
        $ya_notificados[] = (int)$solicitante_id;
    }

    $admins = obtenerIdsAdministradores($conn, $autor_id);
    foreach ($admins as $admin_id) {
        if (in_array((int)$admin_id, $ya_notificados, true)) {
            continue;
        }
        crearNotificacion($conn, $admin_id, 'comentario', $titulo, $mensaje, $enlace);
        $ya_notificados[] = (int)$admin_id;
    }
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

