<?php
/**
 * Configuración general del sistema
 * SEGUI-GRAF - Sistema de Seguimiento Gráfico
 */

// Configuración de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Cambiar a 1 en producción con HTTPS
session_start();

// Zona horaria
date_default_timezone_set('America/Bogota');

// Configuración de rutas
define('BASE_PATH', dirname(__DIR__));

// Detectar automáticamente la ruta base del proyecto
function getBaseUrl() {
    // Si no hay variables de servidor (CLI), retornar URL por defecto
    if (!isset($_SERVER['HTTP_HOST']) || !isset($_SERVER['SCRIPT_NAME'])) {
        return 'http://localhost/';
    }
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    // Obtener el directorio del script actual
    $script = $_SERVER['SCRIPT_NAME'];
    $path = dirname($script);
    
    // Limpiar la ruta (eliminar barras dobles y normalizar)
    $path = str_replace('\\', '/', $path);
    $path = rtrim($path, '/');
    
    // Si está en la raíz del servidor, retornar solo /
    if ($path === '.' || $path === '' || $path === '/') {
        return $protocol . '://' . $host . '/';
    }
    
    // Asegurar que la ruta comience con /
    if (substr($path, 0, 1) !== '/') {
        $path = '/' . $path;
    }
    
    return $protocol . '://' . $host . $path . '/';
}

// Definir BASE_URL
define('BASE_URL', getBaseUrl());
define('UPLOAD_PATH', BASE_PATH . '/uploads/');
define('UPLOAD_URL', BASE_URL . 'uploads/');

// Configuración de archivos
// Límite lógico de la app; en php.ini upload_max_filesize y post_max_size deben ser >= este valor
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_DOC_TYPES', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/x-mspowerpoint',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/excel',
    'application/x-excel',
    'application/x-msexcel',
]);
define('ALLOWED_ARCHIVE_TYPES', ['application/zip', 'application/x-zip-compressed', 'application/vnd.rar', 'application/x-rar-compressed', 'application/x-rar']);
define('ALLOWED_AUDIO_TYPES', [
    'audio/mpeg',
    'audio/mp3',
    'audio/wav',
    'audio/x-wav',
    'audio/wave',
    'audio/ogg',
    'audio/mp4',
    'audio/x-m4a',
    'audio/m4a',
    'audio/aac',
    'audio/flac',
    'audio/x-flac',
    'audio/webm',
    'audio/opus',
]);

// Configuración de compresión de imágenes
define('IMAGE_QUALITY', 85);
define('MAX_IMAGE_WIDTH', 1920);
define('MAX_IMAGE_HEIGHT', 1080);

// Configuración de prioridades
define('PRIORIDAD_ALTA', 'Alta Prioridad – Urgente');
define('PRIORIDAD_MEDIA', 'Prioridad Media – Programada');
define('PRIORIDAD_BAJA', 'Prioridad Baja – Regular');

// Configuración de estados
define('ESTADO_RECIBIDO', 'Recibido');
define('ESTADO_PENDIENTE', 'Pendiente de aprobación');
define('ESTADO_APROBADA', 'Aprobada');
define('ESTADO_CANCELADA', 'Cancelada');
define('ESTADO_EN_PROCESO', 'En proceso');
define('ESTADO_COMPLETADA', 'Completada');

// Incluir archivos necesarios
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/logger.php';

// Inicializar logger
Logger::init();
