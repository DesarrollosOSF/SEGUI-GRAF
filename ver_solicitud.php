<?php
require_once 'config/config.php';
requireAuth();

require_once 'includes/comments.php';
require_once 'includes/notifications.php';

$db = new Database();
$conn = $db->getConnection();

$id = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Procesar nuevo comentario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_comentario'])) {
    verifyCSRFToken();
    
    $comentario = $_POST['comentario'] ?? '';
    if (!empty($comentario)) {
        try {
            $comentario_id = agregarComentario($conn, $id, $_SESSION['user_id'], $comentario);
            
            Logger::audit('Agregar comentario', $_SESSION['user_id'], [
                'solicitud_id' => $id,
                'comentario_id' => $comentario_id
            ]);
            
            // Obtener el otro usuario (admin o usuario solicitante)
            $stmt = $conn->prepare("SELECT usuario_id, administrador_id FROM solicitudes WHERE id = ?");
            $stmt->execute([$id]);
            $sol = $stmt->fetch();
            
            // Determinar a quién notificar
            if ($_SESSION['user_id'] == $sol['usuario_id']) {
                // Si comenta el usuario solicitante, notificar al administrador asignado
                $otro_usuario_id = $sol['administrador_id'];
            } else {
                // Si comenta el administrador, notificar al usuario solicitante
                $otro_usuario_id = $sol['usuario_id'];
            }
            
            // Solo notificar si hay otro usuario válido
            if ($otro_usuario_id) {
                notificarNuevoComentario($conn, $id, $comentario_id, $_SESSION['user_id'], $otro_usuario_id);
            }
            
            redirect('ver_solicitud.php?id=' . $id . '&success=' . urlencode('Comentario agregado exitosamente'));
        } catch (Exception $e) {
            Logger::error('Error al agregar comentario', [
                'solicitud_id' => $id,
                'error' => $e->getMessage()
            ]);
            $error = 'Error al agregar comentario: ' . $e->getMessage();
        }
    }
}

// Obtener solicitud
$stmt = $conn->prepare("
    SELECT s.*, u.nombre_completo as usuario_nombre, u.email as usuario_email,
           a.nombre_completo as admin_nombre
    FROM solicitudes s
    LEFT JOIN usuarios u ON s.usuario_id = u.id
    LEFT JOIN usuarios a ON s.administrador_id = a.id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$solicitud = $stmt->fetch();

if (!$solicitud) {
    redirect('index.php');
}

// Verificar permisos
if (!isAdmin() && $solicitud['usuario_id'] != $_SESSION['user_id']) {
    redirect('index.php');
}

// Obtener archivos adjuntos
$stmt = $conn->prepare("SELECT * FROM archivos_adjuntos WHERE solicitud_id = ? ORDER BY fecha_subida");
$stmt->execute([$id]);
$archivos = $stmt->fetchAll();

// Obtener historial
$stmt = $conn->prepare("
    SELECT h.*, u.nombre_completo as usuario_nombre
    FROM historial_estados h
    LEFT JOIN usuarios u ON h.usuario_id = u.id
    WHERE h.solicitud_id = ?
    ORDER BY h.fecha_cambio DESC
");
$stmt->execute([$id]);
$historial = $stmt->fetchAll();

// Obtener comentarios
$comentarios = obtenerComentarios($conn, $id);

// Marcar comentarios como leídos
marcarComentariosLeidos($conn, $id, $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Solicitud #<?php echo $id; ?> - SEGUI-GRAF</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo url('favicon.svg'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/style.css'); ?>">
    <script src="<?php echo url('assets/js/file-preview.js'); ?>" defer></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container">
        <div class="page-header">
            <h1>Solicitud #<?php echo $id; ?></h1>
            <a href="<?php echo url('index.php'); ?>" class="btn btn-secondary">Volver</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success" id="success-message" style="animation: slideIn 0.3s;">
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
            <script>
                setTimeout(() => {
                    const msg = document.getElementById('success-message');
                    if (msg) msg.style.animation = 'slideOut 0.3s';
                }, 3000);
            </script>
        <?php endif; ?>

        <div class="solicitud-detail">
            <div class="detail-card">
                <div class="detail-header">
                    <h2><?php echo sanitize($solicitud['titulo']); ?></h2>
                    <div class="detail-badges">
                        <span class="badge <?php echo getPrioridadClass($solicitud['prioridad']); ?>">
                            <?php echo getPrioridadIcon($solicitud['prioridad']); ?> 
                            <?php echo $solicitud['prioridad']; ?>
                        </span>
                        <span class="badge <?php echo getEstadoClass($solicitud['estado']); ?>">
                            <?php echo $solicitud['estado']; ?>
                        </span>
                    </div>
                </div>

                <div class="detail-body">
                    <div class="detail-section">
                        <h3>Información General</h3>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Solicitante:</label>
                                <span><?php echo sanitize($solicitud['usuario_nombre']); ?></span>
                            </div>
                            <div class="detail-item">
                                <label>Fecha de Solicitud:</label>
                                <span><?php echo formatDateTime($solicitud['fecha_solicitud']); ?></span>
                            </div>
                            <div class="detail-item">
                                <label>Fecha Estimada de Entrega:</label>
                                <span><?php echo formatDate($solicitud['fecha_estimada_entrega']); ?></span>
                            </div>
                            <div class="detail-item">
                                <label>Tipo de Uso:</label>
                                <span><?php echo $solicitud['tipo_uso']; ?></span>
                            </div>
                            <?php if ($solicitud['fecha_publicacion']): ?>
                                <div class="detail-item">
                                    <label>Fecha de Publicación:</label>
                                    <span><?php echo formatDate($solicitud['fecha_publicacion']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($solicitud['tipo_requerimiento']): ?>
                                <div class="detail-item">
                                    <label>Tipo de Requerimiento:</label>
                                    <span><?php echo sanitize($solicitud['tipo_requerimiento']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($solicitud['admin_nombre']): ?>
                                <div class="detail-item">
                                    <label>Asignado a:</label>
                                    <span><?php echo sanitize($solicitud['admin_nombre']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h3>Descripción</h3>
                        <p class="description-text"><?php echo nl2br(sanitize($solicitud['descripcion'])); ?></p>
                    </div>

                    <?php if ($solicitud['justificacion_prioridad']): ?>
                        <div class="detail-section">
                            <h3>Justificación de Prioridad</h3>
                            <p class="description-text"><?php echo nl2br(sanitize($solicitud['justificacion_prioridad'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($solicitud['observaciones']): ?>
                        <div class="detail-section">
                            <h3>Observaciones</h3>
                            <p class="description-text"><?php echo nl2br(sanitize($solicitud['observaciones'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($archivos)): ?>
                        <div class="detail-section">
                            <h3>Archivos Adjuntos</h3>
                            <div class="archivos-list">
                                <?php 
                                // Separar archivos iniciales del archivo final
                                $archivos_iniciales = [];
                                $archivo_final = null;
                                
                                if ($solicitud['estado'] === 'Completada' && !empty($archivos)) {
                                    // El archivo final es el último subido (por fecha) cuando la solicitud está completada
                                    // Ordenar por fecha de subida descendente y tomar el primero
                                    usort($archivos, function($a, $b) {
                                        return strtotime($b['fecha_subida']) - strtotime($a['fecha_subida']);
                                    });
                                    $archivo_final = $archivos[0];
                                    
                                    // Los demás archivos son iniciales
                                    for ($i = 1; $i < count($archivos); $i++) {
                                        $archivos_iniciales[] = $archivos[$i];
                                    }
                                    } else {
                                    // Si no está completada, todos son archivos iniciales
                                    $archivos_iniciales = $archivos;
                                }
                                ?>
                                
                                <?php if (!empty($archivos_iniciales)): ?>
                                    <h4 style="margin-bottom: 0.75rem; font-size: 0.875rem; color: var(--text-muted);">Archivos de Referencia:</h4>
                                    <?php foreach ($archivos_iniciales as $archivo): ?>
                                        <div class="archivo-item">
                                            <a href="<?php echo UPLOAD_URL . $archivo['nombre_archivo']; ?>" target="_blank" class="archivo-link">
                                                📎 <?php echo sanitize($archivo['nombre_original']); ?>
                                            </a>
                                            <span class="archivo-size">
                                                <?php echo number_format($archivo['tamaño'] / 1024, 2); ?> KB
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <?php if ($archivo_final || !empty($solicitud['url_drive'])): ?>
                                    <h4 style="margin-top: 1.5rem; margin-bottom: 0.75rem; font-size: 0.875rem; color: var(--success-color); font-weight: 600;">✨ Pieza Gráfica Final:</h4>
                                    
                                    <?php if ($archivo_final): ?>
                                        <div class="archivo-item" style="border: 2px solid var(--success-color); background: #d1fae5; display: flex; align-items: center; justify-content: space-between; padding: 1rem; margin-bottom: 1rem;">
                                            <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
                                            <a href="<?php echo UPLOAD_URL . $archivo_final['nombre_archivo']; ?>" target="_blank" class="archivo-link" style="font-weight: 600; color: var(--success-color);">
                                                ✅ <?php echo sanitize($archivo_final['nombre_original']); ?>
                                            </a>
                                            <span class="archivo-size">
                                                <?php echo number_format($archivo_final['tamaño'] / 1024, 2); ?> KB
                                            </span>
                                            </div>
                                            <a href="<?php echo url('descargar_archivo.php?id=' . $archivo_final['id']); ?>" class="btn btn-primary btn-sm" style="margin-left: 1rem; white-space: nowrap;">
                                                📥 Descargar
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($solicitud['url_drive'])): ?>
                                        <div class="archivo-item" style="border: 2px solid var(--success-color); background: #d1fae5; display: flex; align-items: center; justify-content: space-between; padding: 1rem;">
                                            <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
                                                <a href="<?php echo htmlspecialchars($solicitud['url_drive']); ?>" target="_blank" rel="noopener noreferrer" class="archivo-link" style="font-weight: 600; color: var(--success-color);">
                                                    🔗 Archivo en Drive (Archivo grande)
                                                </a>
                                            </div>
                                            <a href="<?php echo htmlspecialchars($solicitud['url_drive']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm" style="margin-left: 1rem; white-space: nowrap;">
                                                🔗 Abrir Drive
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($historial)): ?>
                        <div class="detail-section">
                            <h3>Historial de Cambios</h3>
                            <div class="historial-list">
                                <?php foreach ($historial as $item): ?>
                                    <div class="historial-item">
                                        <div class="historial-header">
                                            <span class="historial-usuario"><?php echo sanitize($item['usuario_nombre']); ?></span>
                                            <span class="historial-fecha"><?php echo formatDateTime($item['fecha_cambio']); ?></span>
                                        </div>
                                        <div class="historial-content">
                                            <?php if ($item['estado_anterior']): ?>
                                                <span class="badge"><?php echo $item['estado_anterior']; ?></span> →
                                            <?php endif; ?>
                                            <span class="badge"><?php echo $item['estado_nuevo']; ?></span>
                                            <?php if ($item['observacion']): ?>
                                                <p class="historial-obs"><?php echo sanitize($item['observacion']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Comentarios -->
                    <div class="detail-section" id="comentarios-section">
                        <h3>Comentarios</h3>
                        
                        <!-- Formulario de nuevo comentario -->
                        <form method="POST" class="comentario-form">
                            <?php echo csrfField(); ?>
                            <div class="form-group">
                                <textarea name="comentario" rows="3" placeholder="Escribe un comentario..." required class="form-control"></textarea>
                            </div>
                            <button type="submit" name="nuevo_comentario" class="btn btn-primary btn-sm">Agregar Comentario</button>
                        </form>

                        <!-- Lista de comentarios -->
                        <div class="comentarios-list" style="margin-top: 1.5rem;">
                            <?php if (empty($comentarios)): ?>
                                <p class="text-muted">No hay comentarios aún. Sé el primero en comentar.</p>
                            <?php else: ?>
                                <?php foreach ($comentarios as $comentario): ?>
                                    <div class="comentario-item" id="comentario-<?php echo $comentario['id']; ?>">
                                        <div class="comentario-header">
                                            <div class="comentario-usuario">
                                                <strong><?php echo sanitize($comentario['usuario_nombre']); ?></strong>
                                                <span class="badge <?php echo $comentario['usuario_perfil'] === 'Administrador' ? 'badge-admin' : 'badge-user'; ?>" style="margin-left: 0.5rem;">
                                                    <?php echo $comentario['usuario_perfil']; ?>
                                                </span>
                                            </div>
                                            <span class="comentario-fecha"><?php echo formatDateTime($comentario['fecha_comentario']); ?></span>
                                        </div>
                                        <div class="comentario-content">
                                            <?php echo nl2br(sanitize($comentario['comentario'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>

