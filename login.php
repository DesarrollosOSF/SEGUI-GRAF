<?php
require_once 'config/config.php';
require_once 'includes/rate_limiter.php';

// Si ya está logueado, redirigir
if (isLoggedIn()) {
    redirect('index.php');
}

$rateLimiter = new RateLimiter();
$error = '';
$blocked = false;
$remainingTime = 0;

// Verificar si está bloqueado
if ($rateLimiter->isBlocked()) {
    $remainingTime = $rateLimiter->getRemainingLockoutTime();
    $blocked = $remainingTime > 0;
    
    if ($blocked) {
        $minutes = ceil($remainingTime / 60);
        $error = "Demasiados intentos fallidos. Por favor, espere {$minutes} minuto(s) antes de intentar nuevamente.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$blocked) {
    verifyCSRFToken();
    
    $usuario = sanitize($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($usuario) || empty($password)) {
        $error = 'Por favor, complete todos los campos';
        $rateLimiter->recordAttempt($usuario, false);
        Logger::warning('Intento de login con campos vacíos', ['usuario' => $usuario]);
    } else {
        if (iniciarSesion($usuario, $password)) {
            $rateLimiter->recordAttempt($usuario, true);
            Logger::info('Login exitoso', ['usuario' => $usuario]);
            redirect('index.php');
        } else {
            $rateLimiter->recordAttempt($usuario, false);
            Logger::warning('Intento de login fallido', ['usuario' => $usuario]);
            $error = 'Usuario o contraseña incorrectos';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - SEGUI-GRAF</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo url('favicon.svg'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/style.css'); ?>">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>SEGUI-GRAF</h1>
                <p>Sistema de Seguimiento Gráfico</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="" class="login-form">
                <?php echo csrfField(); ?>
                <div class="form-group">
                    <label for="usuario">Usuario</label>
                    <input type="text" id="usuario" name="usuario" required autofocus <?php echo $blocked ? 'disabled' : ''; ?>>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required <?php echo $blocked ? 'disabled' : ''; ?>>
                </div>

                <button type="submit" class="btn btn-primary btn-block" <?php echo $blocked ? 'disabled' : ''; ?>>Iniciar Sesión</button>
            </form>
        </div>
    </div>
</body>
</html>

