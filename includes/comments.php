<?php
/**
 * Funciones para manejo de comentarios
 * SEGUI-GRAF - Sistema de Seguimiento Gráfico
 */

/**
 * Agregar comentario a una solicitud
 */
function agregarComentario($conn, $solicitud_id, $usuario_id, $comentario) {
    $stmt = $conn->prepare("
        INSERT INTO comentarios_solicitudes (solicitud_id, usuario_id, comentario)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$solicitud_id, $usuario_id, sanitize($comentario)]);
    return $conn->lastInsertId();
}

/**
 * Obtener comentarios de una solicitud
 */
function obtenerComentarios($conn, $solicitud_id) {
    $stmt = $conn->prepare("
        SELECT c.*, u.nombre_completo as usuario_nombre, u.perfil as usuario_perfil
        FROM comentarios_solicitudes c
        LEFT JOIN usuarios u ON c.usuario_id = u.id
        WHERE c.solicitud_id = ?
        ORDER BY c.fecha_comentario ASC
    ");
    $stmt->execute([$solicitud_id]);
    return $stmt->fetchAll();
}

/**
 * Marcar comentarios como leídos
 */
function marcarComentariosLeidos($conn, $solicitud_id, $usuario_id) {
    $stmt = $conn->prepare("
        UPDATE comentarios_solicitudes 
        SET leido = 1 
        WHERE solicitud_id = ? AND usuario_id != ?
    ");
    $stmt->execute([$solicitud_id, $usuario_id]);
}

/**
 * Contar comentarios no leídos de una solicitud
 */
function contarComentariosNoLeidos($conn, $solicitud_id, $usuario_id) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM comentarios_solicitudes 
        WHERE solicitud_id = ? AND usuario_id != ? AND leido = 0
    ");
    $stmt->execute([$solicitud_id, $usuario_id]);
    return $stmt->fetchColumn();
}
?>

