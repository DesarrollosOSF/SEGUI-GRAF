<?php
require_once 'config/config.php';
requireAdmin();

$db = new Database();
$conn = $db->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFToken();
    
    $usuario = sanitize($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $nombre_completo = sanitize($_POST['nombre_completo'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $perfil = $_POST['perfil'] ?? 'Usuario';
    $activo = isset($_POST['activo']) ? 1 : 0;

    // Validaciones
    if (empty($usuario) || empty($password) || empty($nombre_completo) || empty($email)) {
        $error = 'Por favor, complete todos los campos obligatorios';
    } elseif ($password !== $password_confirm) {
        $error = 'Las contraseñas no coinciden';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El email no es válido';
    } else {
        try {
            // Verificar si el usuario ya existe
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ?");
            $stmt->execute([$usuario]);
            if ($stmt->fetch()) {
                $error = 'El nombre de usuario ya existe';
            } else {
                // Verificar si el email ya existe
                $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'El email ya está registrado';
                } else {
                    // Crear usuario
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("
                        INSERT INTO usuarios (usuario, password, nombre_completo, email, perfil, activo)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$usuario, $password_hash, $nombre_completo, $email, $perfil, $activo]);
                    
                    $success = 'Usuario creado exitosamente';
                    redirect('usuarios.php');
                }
            }
        } catch (Exception $e) {
            $error = 'Error al crear el usuario: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Usuario - SEGUI-GRAF</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo url('favicon.svg'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/style.css'); ?>">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container">
        <div class="page-header">
            <h1>Crear Nuevo Usuario</h1>
            <a href="<?php echo url('usuarios.php'); ?>" class="btn btn-secondary">Volver</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" class="form-container">
            <?php echo csrfField(); ?>
            <div class="form-section">
                <h2>Información de Acceso</h2>
                
                <div class="form-group">
                    <label for="usuario">Nombre de Usuario <span class="required">*</span></label>
                    <input type="text" id="usuario" name="usuario" required maxlength="50" autocomplete="username">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Contraseña <span class="required">*</span></label>
                        <input type="password" id="password" name="password" required minlength="6" autocomplete="new-password">
                        <small class="form-help">Mínimo 6 caracteres</small>
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">Confirmar Contraseña <span class="required">*</span></label>
                        <input type="password" id="password_confirm" name="password_confirm" required minlength="6" autocomplete="new-password">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2>Información Personal</h2>
                
                <div class="form-group">
                    <label for="nombre_completo">Nombre Completo <span class="required">*</span></label>
                    <input type="text" id="nombre_completo" name="nombre_completo" required maxlength="100">
                </div>

                <div class="form-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required maxlength="100">
                </div>
            </div>

            <div class="form-section">
                <h2>Configuración</h2>
                
                <div class="form-group">
                    <label for="perfil">Perfil <span class="required">*</span></label>
                    <select id="perfil" name="perfil" required>
                        <option value="Usuario">Usuario</option>
                        <option value="Administrador">Administrador</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="activo" value="1" checked> Usuario Activo
                    </label>
                    <small class="form-help">Los usuarios inactivos no pueden iniciar sesión</small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Crear Usuario</button>
                <a href="<?php echo url('usuarios.php'); ?>" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>

