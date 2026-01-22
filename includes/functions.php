<?php
/**
 * Funciones auxiliares del sistema
 * SEGUI-GRAF - Sistema de Seguimiento Gráfico
 */

/**
 * Verificar si el usuario está autenticado
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['usuario']);
}

/**
 * Verificar si el usuario es administrador
 */
function isAdmin() {
    return isLoggedIn() && $_SESSION['perfil'] === 'Administrador';
}

/**
 * Generar URL completa
 */
function url($path = '') {
    // Si la URL ya es completa (http:// o https://), retornarla tal cual
    if (preg_match('/^https?:\/\//', $path)) {
        return $path;
    }
    
    // Limpiar la ruta
    $path = ltrim($path, '/');
    
    // Retornar URL completa
    return BASE_URL . $path;
}

/**
 * Redirigir a una página
 */
function redirect($url) {
    // Si la URL no es completa, generar la URL completa
    if (!preg_match('/^https?:\/\//', $url)) {
        $url = url($url);
    }
    header("Location: " . $url);
    exit();
}

/**
 * Sanitizar entrada de datos
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Formatear fecha para mostrar
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '-';
    $dateObj = new DateTime($date);
    return $dateObj->format($format);
}

/**
 * Formatear fecha y hora para mostrar
 */
function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    if (empty($datetime)) return '-';
    $dateObj = new DateTime($datetime);
    return $dateObj->format($format);
}

/**
 * Obtener clase CSS para prioridad
 */
function getPrioridadClass($prioridad) {
    $classes = [
        'Alta Prioridad – Urgente' => 'prioridad-alta',
        'Prioridad Media – Programada' => 'prioridad-media',
        'Prioridad Baja – Regular' => 'prioridad-baja'
    ];
    return $classes[$prioridad] ?? 'prioridad-baja';
}

/**
 * Obtener icono para prioridad
 */
function getPrioridadIcon($prioridad) {
    $icons = [
        'Alta Prioridad – Urgente' => '🔴',
        'Prioridad Media – Programada' => '🟡',
        'Prioridad Baja – Regular' => '🟢'
    ];
    return $icons[$prioridad] ?? '🟢';
}

/**
 * Obtener clase CSS para estado
 */
function getEstadoClass($estado) {
    $classes = [
        'Recibido' => 'estado-recibido',
        'Pendiente de aprobación' => 'estado-pendiente',
        'Aprobada' => 'estado-aprobada',
        'Cancelada' => 'estado-cancelada',
        'En proceso' => 'estado-proceso',
        'Completada' => 'estado-completada'
    ];
    return $classes[$estado] ?? 'estado-pendiente';
}

/**
 * Calcular días entre dos fechas
 */
function calcularDias($fecha1, $fecha2) {
    $date1 = new DateTime($fecha1);
    $date2 = new DateTime($fecha2);
    $diff = $date1->diff($date2);
    return $diff->days;
}

/**
 * Calcular horas entre dos fechas
 */
function calcularHoras($fecha1, $fecha2) {
    $date1 = new DateTime($fecha1);
    $date2 = new DateTime($fecha2);
    $diff = $date1->diff($date2);
    return ($diff->days * 24) + $diff->h + ($diff->i / 60);
}

/**
 * Validar formato de archivo
 */
function validarTipoArchivo($tipo) {
    $tiposPermitidos = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_DOC_TYPES);
    return in_array($tipo, $tiposPermitidos);
}

/**
 * Obtener extensión de archivo
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}
?>

