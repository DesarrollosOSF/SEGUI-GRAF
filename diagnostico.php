<?php
/**
 * Página de diagnóstico para depurar login y sesión.
 * Uso: https://tu-dominio.com/diagnostico.php?key=SEGUIGRAF_DEBUG_2024
 * Cambiar la clave en la línea de abajo y eliminar este archivo en producción.
 */
define('DIAGNOSTICO_KEY', 'SEGUIGRAF_DEBUG_2024');

$key = $_GET['key'] ?? '';
if ($key !== DIAGNOSTICO_KEY) {
    header('HTTP/1.0 404 Not Found');
    exit('No encontrado');
}

require_once __DIR__ . '/config/config.php';

header('Content-Type: text/html; charset=utf-8');

$checks = [];
$phpVersion = PHP_VERSION;
$checks['PHP'] = $phpVersion;

$checks['DEBUG_SEGUIGRAF'] = defined('DEBUG_SEGUIGRAF') && DEBUG_SEGUIGRAF ? 'Sí' : 'No';

$checks['display_errors'] = ini_get('display_errors');
$checks['log_errors'] = ini_get('log_errors');

$checks['Sesión'] = session_status() === PHP_SESSION_ACTIVE ? 'Activa' : 'Inactiva';
if (session_status() === PHP_SESSION_ACTIVE) {
    $checks['session_id'] = session_id();
    $checks['session_data'] = isset($_SESSION['user_id']) ? 'Usuario logueado (id=' . $_SESSION['user_id'] . ')' : 'Sin usuario en sesión';
}

$checks['Cookies recibidas'] = empty($_COOKIE) ? 'Ninguna' : implode(', ', array_keys($_COOKIE));
$csrfCookieName = defined('CSRF_LOGIN_COOKIE') ? CSRF_LOGIN_COOKIE : 'csrf_login_token';
$checks['Cookie ' . $csrfCookieName] = isset($_COOKIE[$csrfCookieName]) ? 'Presente' : 'Ausente';

$checks['HTTPS detectado'] = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    ? 'Sí' : 'No';

$checks['BASE_URL'] = defined('BASE_URL') ? BASE_URL : 'No definido';

$dbOk = 'No';
$dbError = '';
try {
    $db = new Database();
    $conn = $db->getConnection();
    $dbOk = 'Sí';
    $stmt = $conn->query("SELECT COUNT(*) as c FROM usuarios WHERE activo = 1");
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $checks['Usuarios activos en BD'] = $r['c'] ?? '?';

    $checks['Tabla usuarios existe'] = 'Sí';
    $stmt = $conn->query("SELECT id, usuario, activo FROM usuarios LIMIT 5");
    $usuarios_lista = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $checks['Lista usuarios (login)'] = empty($usuarios_lista) ? 'Ninguno' : implode(', ', array_column($usuarios_lista, 'usuario'));

    $testLogin = 'No probado';
    $stmt = $conn->prepare("SELECT id, usuario, password, activo FROM usuarios WHERE usuario = ? AND activo = 1");
    $stmt->execute(['admin']);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        $hash = $admin['password'];
        $esBcrypt = (strlen($hash) === 60 && (strpos($hash, '$2y$') === 0 || strpos($hash, '$2a$') === 0));
        $checks['Formato hash (admin)'] = $esBcrypt ? 'bcrypt (correcto)' : 'No bcrypt (prefijo: ' . substr($hash, 0, 15) . '...) — use reset_admin_password.sql';
        $testLogin = password_verify('admin123', $hash) ? 'admin/admin123: OK' : 'admin existe pero contraseña admin123 no coincide';
    } else {
        $checks['Formato hash (admin)'] = 'N/A (no hay usuario admin activo)';
        $testLogin = 'Usuario admin no existe o está inactivo';
    }
    $checks['Prueba login (admin/admin123)'] = $testLogin;
} catch (Throwable $e) {
    $dbError = $e->getMessage();
    $checks['Base de datos'] = 'Error: ' . $dbError;
}
if ($dbOk === 'Sí' && empty($dbError)) {
    $checks['Base de datos'] = 'Conexión OK';
}

$rateLimiterOk = 'No';
try {
    require_once __DIR__ . '/includes/rate_limiter.php';
    $rl = new RateLimiter();
    $rateLimiterOk = 'Sí';
} catch (Throwable $e) {
    $rateLimiterOk = 'Error: ' . $e->getMessage();
}
$checks['Rate limiter'] = $rateLimiterOk;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico SEGUI-GRAF</title>
    <style>
        body { font-family: sans-serif; max-width: 700px; margin: 2rem auto; padding: 1rem; background: #f5f5f5; }
        h1 { color: #2563eb; }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8fafc; font-weight: 600; width: 40%; }
        .ok { color: #059669; }
        .error { color: #dc2626; }
        .warn { color: #d97706; }
        p { color: #64748b; font-size: 0.875rem; }
    </style>
</head>
<body>
    <h1>Diagnóstico SEGUI-GRAF</h1>
    <p>Use esta página solo para depurar. Elimínela o proteja la clave en producción.</p>
    <table>
        <tbody>
            <?php foreach ($checks as $label => $value): ?>
                <tr>
                    <th><?php echo htmlspecialchars($label); ?></th>
                    <td><?php echo htmlspecialchars($value); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p style="margin-top: 1.5rem;">Para activar mensajes de depuración en login: cree un archivo <code>.debug</code> en la raíz del proyecto con el contenido <code>1</code>, o defina la variable de entorno <code>SEGUIGRAF_DEBUG=1</code>.</p>
</body>
</html>
