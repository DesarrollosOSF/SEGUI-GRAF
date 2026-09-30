<?php
if (isLoggedIn()) {
    require_once 'includes/notifications.php';
    $db = new Database();
    $conn = $db->getConnection();
    $notificaciones_count = contarNotificacionesNoLeidas($conn, $_SESSION['user_id']);
}
?>
<header class="main-header">
    <div class="header-content">
        <div class="logo">
            <h2>SEGUI-GRAF</h2>
        </div>
        <nav class="main-nav">
            <a href="<?php echo url('index.php'); ?>" class="nav-link">Inicio</a>
            <?php if (!isAdmin()): ?>
                <a href="<?php echo url('crear_solicitud.php'); ?>" class="nav-link">Nueva Solicitud</a>
            <?php else: ?>
                <a href="<?php echo url('usuarios.php'); ?>" class="nav-link">Usuarios</a>
                <a href="<?php echo url('dashboard.php'); ?>" class="nav-link">Dashboard</a>
                <a href="<?php echo url('metricas.php'); ?>" class="nav-link">Métricas</a>
            <?php endif; ?>
        </nav>
        <div class="user-menu">
            <?php if (isLoggedIn() && $notificaciones_count > 0): ?>
                <a href="<?php echo url('notificaciones.php'); ?>" class="notifications-badge" title="Notificaciones">
                    🔔 <span class="badge-count"><?php echo $notificaciones_count; ?></span>
                </a>
            <?php endif; ?>
            <span class="user-name"><?php echo sanitize($_SESSION['nombre_completo']); ?></span>
            <span class="user-badge <?php echo isAdmin() ? 'badge-admin' : 'badge-user'; ?>">
                <?php echo $_SESSION['perfil']; ?>
            </span>
            <a href="<?php echo url('logout.php'); ?>" class="btn btn-sm btn-secondary">Salir</a>
        </div>
    </div>
</header>

