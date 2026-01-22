<?php
/**
 * Script para descargar archivos adjuntos
 * SEGUI-GRAF - Sistema de Seguimiento Gráfico
 * 
 * Este script sirve los archivos sin alterarlos, preservando archivos vectorizados
 */

require_once 'config/config.php';
requireAuth();

$db = new Database();
$conn = $db->getConnection();

// Obtener ID del archivo
$archivo_id = $_GET['id'] ?? 0;

if (empty($archivo_id)) {
    http_response_code(400);
    die('ID de archivo no proporcionado');
}

// Obtener información del archivo y la solicitud
$stmt = $conn->prepare("
    SELECT a.*, s.usuario_id, s.administrador_id
    FROM archivos_adjuntos a
    INNER JOIN solicitudes s ON a.solicitud_id = s.id
    WHERE a.id = ?
");
$stmt->execute([$archivo_id]);
$archivo = $stmt->fetch();

if (!$archivo) {
    http_response_code(404);
    die('Archivo no encontrado');
}

// Verificar permisos: solo el usuario solicitante o un administrador pueden descargar
if (!isAdmin() && $archivo['usuario_id'] != $_SESSION['user_id']) {
    http_response_code(403);
    die('No tiene permiso para descargar este archivo');
}

// Verificar que el archivo existe físicamente
$ruta_archivo = $archivo['ruta'];
if (!file_exists($ruta_archivo)) {
    http_response_code(404);
    die('El archivo no existe en el servidor');
}

// Obtener el nombre original del archivo
$nombre_original = $archivo['nombre_original'];

// Limpiar cualquier salida previa
if (ob_get_level()) {
    ob_end_clean();
}

// Configurar headers para descarga
header('Content-Type: ' . $archivo['tipo_mime']);
header('Content-Disposition: attachment; filename="' . addslashes($nombre_original) . '"');
header('Content-Length: ' . filesize($ruta_archivo));
header('Cache-Control: must-revalidate');
header('Pragma: public');

// Leer y enviar el archivo sin alterarlo
readfile($ruta_archivo);
exit;
?>

