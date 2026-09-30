<?php
require_once 'config/config.php';
requireAuth();

require_once 'includes/notifications.php';

$db = new Database();
$conn = $db->getConnection();

// Marcar como leída
if (isset($_GET['marcar_leida']) && is_numeric($_GET['marcar_leida'])) {
    marcarNotificacionLeida($conn, (int)$_GET['marcar_leida']);
    redirect('notificaciones.php');
}

// Marcar todas como leídas
if (isset($_GET['marcar_todas'])) {
    marcarTodasLeidas($conn, $_SESSION['user_id']);
    redirect('notificaciones.php');
}

// Obtener notificaciones
$notificaciones = obtenerNotificaciones($conn, $_SESSION['user_id']);
$notificaciones_no_leidas = contarNotificacionesNoLeidas($conn, $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones - SEGUI-GRAF</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo url('favicon.svg'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/style.css'); ?>">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container">
        <div class="page-header">
            <h1>Notificaciones</h1>
            <?php if ($notificaciones_no_leidas > 0): ?>
                <a href="<?php echo url('notificaciones.php?marcar_todas=1'); ?>" class="btn btn-secondary">Marcar todas como leídas</a>
            <?php endif; ?>
        </div>

        <div class="notifications-list">
            <?php if (empty($notificaciones)): ?>
                <div class="alert alert-info">
                    <p>No tienes notificaciones</p>
                </div>
            <?php else: ?>
                <?php foreach ($notificaciones as $notif): ?>
                    <div class="notification-item <?php echo $notif['leida'] ? '' : 'unread'; ?>">
                        <div class="notification-content">
                            <div class="notification-header">
                                <h3><?php echo sanitize($notif['titulo']); ?></h3>
                                <span class="notification-date"><?php echo formatDateTime($notif['fecha_creacion']); ?></span>
                            </div>
                            <p class="notification-message"><?php echo nl2br(sanitize($notif['mensaje'])); ?></p>
                            <?php if ($notif['enlace']): ?>
                                <a href="<?php echo $notif['enlace']; ?>" class="btn btn-sm btn-primary">Ver más</a>
                            <?php endif; ?>
                        </div>
                        <?php if (!$notif['leida']): ?>
                            <a href="<?php echo url('notificaciones.php?marcar_leida=' . $notif['id']); ?>" class="notification-mark" title="Marcar como leída">✓</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>

