<?php
require_once 'config/config.php';
requireAdmin();

$db = new Database();
$conn = $db->getConnection();

$id = $_GET['id'] ?? 0;

// Obtener usuario
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    redirect('usuarios.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFToken();
    
    $usuario_nuevo = sanitize($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $nombre_completo = sanitize($_POST['nombre_completo'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $perfil = $_POST['perfil'] ?? 'Usuario';
    $activo = isset($_POST['activo']) ? 1 : 0;

    // Validaciones
    if (empty($usuario_nuevo) || empty($nombre_completo) || empty($email)) {
        $error = 'Por favor, complete todos los campos obligatorios';
    } elseif (!empty($password) && $password !== $password_confirm) {
        $error = 'Las contraseñas no coinciden';
    } elseif (!empty($password) && strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El email no es válido';
    } else {
        try {
            // Verificar si el usuario ya existe (excluyendo el actual)
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
            $stmt->execute([$usuario_nuevo, $id]);
            if ($stmt->fetch()) {
                $error = 'El nombre de usuario ya existe';
            } else {
                // Verificar si el email ya existe (excluyendo el actual)
                $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                $stmt->execute([$email, $id]);
                if ($stmt->fetch()) {
                    $error = 'El email ya está registrado';
                } else {
                    // Actualizar usuario
                    if (!empty($password)) {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("
                            UPDATE usuarios 
                            SET usuario = ?, password = ?, nombre_completo = ?, email = ?, perfil = ?, activo = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$usuario_nuevo, $password_hash, $nombre_completo, $email, $perfil, $activo, $id]);
                    } else {
                        $stmt = $conn->prepare("
                            UPDATE usuarios 
                            SET usuario = ?, nombre_completo = ?, email = ?, perfil = ?, activo = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$usuario_nuevo, $nombre_completo, $email, $perfil, $activo, $id]);
                    }
                    
                    $success = 'Usuario actualizado exitosamente';
                    redirect('usuarios.php');
                }
            }
        } catch (Exception $e) {
            $error = 'Error al actualizar el usuario: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario - SEGUI-GRAF</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo url('favicon.svg'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/style.css'); ?>">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container">
        <div class="page-header">
            <h1>Editar Usuario</h1>
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
                    <input type="text" id="usuario" name="usuario" value="<?php echo sanitize($usuario['usuario']); ?>" required maxlength="50" autocomplete="username">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Nueva Contraseña</label>
                        <input type="password" id="password" name="password" minlength="6" autocomplete="new-password">
                        <small class="form-help">Dejar en blanco para mantener la contraseña actual</small>
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">Confirmar Nueva Contraseña</label>
                        <input type="password" id="password_confirm" name="password_confirm" minlength="6" autocomplete="new-password">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2>Información Personal</h2>
                
                <div class="form-group">
                    <label for="nombre_completo">Nombre Completo <span class="required">*</span></label>
                    <input type="text" id="nombre_completo" name="nombre_completo" value="<?php echo sanitize($usuario['nombre_completo']); ?>" required maxlength="100">
                </div>

                <div class="form-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" value="<?php echo sanitize($usuario['email']); ?>" required maxlength="100">
                </div>
            </div>

            <div class="form-section">
                <h2>Configuración</h2>
                
                <div class="form-group">
                    <label for="perfil">Perfil <span class="required">*</span></label>
                    <select id="perfil" name="perfil" required>
                        <option value="Usuario" <?php echo $usuario['perfil'] === 'Usuario' ? 'selected' : ''; ?>>Usuario</option>
                        <option value="Administrador" <?php echo $usuario['perfil'] === 'Administrador' ? 'selected' : ''; ?>>Administrador</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="activo" value="1" <?php echo $usuario['activo'] ? 'checked' : ''; ?>> Usuario Activo
                    </label>
                    <small class="form-help">Los usuarios inactivos no pueden iniciar sesión</small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                <a href="<?php echo url('usuarios.php'); ?>" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>

