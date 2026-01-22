<?php
require_once 'config/config.php';
requireAdmin();

$db = new Database();
$conn = $db->getConnection();

$error = '';
$success = '';

// Eliminar usuario
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $usuario_id = (int)$_GET['eliminar'];
    
    // No permitir eliminar el propio usuario
    if ($usuario_id == $_SESSION['user_id']) {
        $error = 'No puede eliminar su propio usuario';
    } else {
        try {
            $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $success = 'Usuario eliminado exitosamente';
        } catch (Exception $e) {
            $error = 'Error al eliminar el usuario: ' . $e->getMessage();
        }
    }
}

// Obtener todos los usuarios
$stmt = $conn->query("
    SELECT u.*, 
           COUNT(s.id) as total_solicitudes
    FROM usuarios u
    LEFT JOIN solicitudes s ON u.id = s.usuario_id
    GROUP BY u.id
    ORDER BY u.fecha_registro DESC
");
$usuarios = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - SEGUI-GRAF</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo url('favicon.svg'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/style.css'); ?>">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container">
        <div class="page-header">
            <h1>Gestión de Usuarios</h1>
            <a href="<?php echo url('crear_usuario.php'); ?>" class="btn btn-primary">Nuevo Usuario</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Nombre Completo</th>
                        <th>Email</th>
                        <th>Perfil</th>
                        <th>Estado</th>
                        <th>Solicitudes</th>
                        <th>Fecha Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="9" class="text-center">No hay usuarios registrados</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td>#<?php echo $usuario['id']; ?></td>
                                <td><strong><?php echo sanitize($usuario['usuario']); ?></strong></td>
                                <td><?php echo sanitize($usuario['nombre_completo']); ?></td>
                                <td><?php echo sanitize($usuario['email']); ?></td>
                                <td>
                                    <span class="badge <?php echo $usuario['perfil'] === 'Administrador' ? 'badge-admin' : 'badge-user'; ?>">
                                        <?php echo $usuario['perfil']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($usuario['activo']): ?>
                                        <span class="badge estado-completada">Activo</span>
                                    <?php else: ?>
                                        <span class="badge estado-cancelada">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $usuario['total_solicitudes']; ?></td>
                                <td><?php echo formatDate($usuario['fecha_registro'], 'd/m/Y'); ?></td>
                                <td>
                                    <a href="<?php echo url('editar_usuario.php?id=' . $usuario['id']); ?>" class="btn btn-sm btn-info">Editar</a>
                                    <?php if ($usuario['id'] != $_SESSION['user_id']): ?>
                                        <a href="<?php echo url('usuarios.php?eliminar=' . $usuario['id']); ?>" 
                                           class="btn btn-sm btn-secondary" 
                                           onclick="return confirm('¿Está seguro de eliminar este usuario? Esta acción no se puede deshacer.');">Eliminar</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>

