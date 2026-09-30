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
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit();
    }
    $safe = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta http-equiv="refresh" content="0;url=' . $safe . '"><title>Redirigiendo…</title></head><body><p>Redirigiendo… Si no avanza, <a href="' . $safe . '">pulse aquí</a>.</p></body></html>';
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
    $tiposPermitidos = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_DOC_TYPES, ALLOWED_ARCHIVE_TYPES, ALLOWED_AUDIO_TYPES);
    return in_array($tipo, $tiposPermitidos);
}
/**
 * Obtener extensión de archivo
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Mensaje legible para códigos de error de subida de PHP ($_FILES['...']['error'])
 */
function mensajeErrorSubidaPHP($codigo) {
    switch ((int) $codigo) {
        case UPLOAD_ERR_OK:
            return '';
        case UPLOAD_ERR_INI_SIZE:
            return 'El archivo supera upload_max_filesize en php.ini (límite del servidor).';
        case UPLOAD_ERR_FORM_SIZE:
            return 'El archivo supera el tamaño máximo permitido por el formulario.';
        case UPLOAD_ERR_PARTIAL:
            return 'El archivo se subió solo parcialmente (intente de nuevo).';
        case UPLOAD_ERR_NO_FILE:
            return 'No se envió ningún archivo.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Falta carpeta temporal en el servidor.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'No se pudo escribir el archivo en disco.';
        case UPLOAD_ERR_EXTENSION:
            return 'Una extensión de PHP detuvo la subida.';
        default:
            return 'Error de subida (código ' . (int) $codigo . ').';
    }
}

/**
 * Convierte $_FILES['campo'] en lista de arrays listos para FileHandler (un archivo por elemento).
 */
function normalizarArchivosRequest($campo) {
    if (!isset($_FILES[$campo]) || !is_array($_FILES[$campo])) {
        return [];
    }
    $f = $_FILES[$campo];
    if (!isset($f['name']) || !is_array($f['name'])) {
        if (empty($f['name'])) {
            return [];
        }
        return [[
            'name' => $f['name'],
            'type' => $f['type'] ?? '',
            'tmp_name' => $f['tmp_name'] ?? '',
            'error' => $f['error'] ?? UPLOAD_ERR_NO_FILE,
            'size' => $f['size'] ?? 0,
        ]];
    }
    $lista = [];
    foreach ($f['name'] as $i => $nombre) {
        if ($nombre === '' || $nombre === null) {
            continue;
        }
        $lista[] = [
            'name' => $f['name'][$i],
            'type' => $f['type'][$i] ?? '',
            'tmp_name' => $f['tmp_name'][$i] ?? '',
            'error' => $f['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $f['size'][$i] ?? 0,
        ];
    }
    return $lista;
}
