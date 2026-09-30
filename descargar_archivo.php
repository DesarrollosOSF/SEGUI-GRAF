<?php

if (!ob_get_level()) {
    ob_start();
}

require_once 'config/config.php';

requireAuth();

$db = new Database();
$conn = $db->getConnection();

/*
|--------------------------------------------------------------------------
| VALIDAR ID
|--------------------------------------------------------------------------
*/

$archivo_id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($archivo_id <= 0) {

    http_response_code(400);
    exit('ID inválido');
}

/*
|--------------------------------------------------------------------------
| CONSULTAR ARCHIVO
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        a.*,
        s.usuario_id
    FROM archivos_adjuntos a
    INNER JOIN solicitudes s
        ON a.solicitud_id = s.id
    WHERE a.id = ?
";

$stmt = $conn->prepare($sql);

$stmt->execute([$archivo_id]);

$archivo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$archivo) {

    http_response_code(404);
    exit('Archivo no encontrado');
}

/*
|--------------------------------------------------------------------------
| VALIDAR PERMISOS
|--------------------------------------------------------------------------
*/

if (
    !isAdmin() &&
    (int)$archivo['usuario_id'] !== (int)$_SESSION['user_id']
) {

    http_response_code(403);
    exit('Sin permisos');
}

/*
|--------------------------------------------------------------------------
| RUTA ARCHIVO
|--------------------------------------------------------------------------
*/

$ruta = $archivo['ruta'];

if (
    empty($ruta) ||
    !file_exists($ruta) ||
    !is_file($ruta)
) {

    http_response_code(404);
    exit('Archivo no existe');
}

/*
|--------------------------------------------------------------------------
| LIMPIAR BUFFERS
|--------------------------------------------------------------------------
*/

while (ob_get_level()) {
    ob_end_clean();
}

/*
|--------------------------------------------------------------------------
| DESACTIVAR COMPRESION
|--------------------------------------------------------------------------
*/

@ini_set('zlib.output_compression', 'Off');

if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}

/*
|--------------------------------------------------------------------------
| MIME TYPE REAL
|--------------------------------------------------------------------------
*/

$finfo = finfo_open(FILEINFO_MIME_TYPE);

$mime = finfo_file($finfo, $ruta);

finfo_close($finfo);

if (!$mime) {
    $mime = 'application/octet-stream';
}

/*
|--------------------------------------------------------------------------
| NOMBRE ARCHIVO
|--------------------------------------------------------------------------
*/

$nombre = $archivo['nombre_original'];

$nombre_ascii = preg_replace(
    '/[^A-Za-z0-9._-]/',
    '_',
    $nombre
);

if (!$nombre_ascii) {
    $nombre_ascii = 'archivo';
}

/*
|--------------------------------------------------------------------------
| HEADERS
|--------------------------------------------------------------------------
*/

if (headers_sent($archivo_headers, $linea_headers)) {
    http_response_code(500);
    exit('No se pudo iniciar la descarga. Ya se enviaron cabeceras desde ' . $archivo_headers . ':' . $linea_headers);
}

$disposition = 'attachment; filename="' . $nombre_ascii . '"; filename*=UTF-8\'\'' . rawurlencode($nombre);

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: ' . $disposition);
header('Content-Transfer-Encoding: binary');
header('X-Content-Type-Options: nosniff');
header('Expires: 0');
header('Cache-Control: private, no-transform, no-store, must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($ruta));

/*
|--------------------------------------------------------------------------
| LIMPIAR OUTPUT
|--------------------------------------------------------------------------
*/

if (ob_get_level() > 0) {
    ob_flush();
}
flush();

/*
|--------------------------------------------------------------------------
| ENVIAR ARCHIVO
|--------------------------------------------------------------------------
*/

readfile($ruta);
exit;