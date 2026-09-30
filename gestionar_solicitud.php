<?php
require_once 'config/config.php';
requireAdmin();

require_once 'includes/notifications.php';

$db = new Database();
$conn = $db->getConnection();

$id = $_GET['id'] ?? 0;

// Obtener solicitud
$stmt = $conn->prepare("
    SELECT s.*, u.nombre_completo as usuario_nombre
    FROM solicitudes s
    LEFT JOIN usuarios u ON s.usuario_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$solicitud = $stmt->fetch();

if (!$solicitud) {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFToken();
    
    Logger::audit('Gestionar solicitud', $_SESSION['user_id'], [
        'solicitud_id' => $id,
        'accion' => 'actualizar_estado'
    ]);
    
    $estado = $_POST['estado'] ?? '';
    $prioridad_ajustada = $_POST['prioridad_ajustada'] ?? null;
    $justificacion = sanitize($_POST['justificacion_prioridad'] ?? '');
    $observaciones = sanitize($_POST['observaciones'] ?? '');
    $url_drive = sanitize($_POST['url_drive'] ?? '');
    $cambiar_fecha_entrega = $_POST['cambiar_fecha_entrega'] ?? '0';
    $nueva_fecha_estimada_entrega = $_POST['nueva_fecha_estimada_entrega'] ?? '';

    if (empty($estado)) {
        $error = 'Debe seleccionar un estado';
    } elseif ($cambiar_fecha_entrega === '1' && empty($nueva_fecha_estimada_entrega)) {
        $error = 'Debe seleccionar la nueva fecha estimada de entrega';
    } else {
        // Validar que si se completa la solicitud, debe haber archivo final O URL de drive
        if ($estado === ESTADO_COMPLETADA) {
            $tiene_archivo_nuevo = !empty($_FILES['archivo_final']['name']) && $_FILES['archivo_final']['error'] === UPLOAD_ERR_OK;
            $tiene_url_nueva = !empty($url_drive);
            
            // Verificar si ya existe un archivo final (si se está re-completando)
            $stmt = $conn->prepare("SELECT COUNT(*) FROM archivos_adjuntos WHERE solicitud_id = ?");
            $stmt->execute([$id]);
            $num_archivos = $stmt->fetchColumn();
            
            // Verificar si ya existe URL de drive
            $stmt = $conn->prepare("SELECT url_drive FROM solicitudes WHERE id = ?");
            $stmt->execute([$id]);
            $solicitud_actual = $stmt->fetch();
            $tiene_url_existente = !empty($solicitud_actual['url_drive']);
            
            // Si no hay archivo nuevo, no hay URL nueva, y no hay archivo/URL existente, es error
            if (!$tiene_archivo_nuevo && !$tiene_url_nueva && $num_archivos == 0 && !$tiene_url_existente) {
                $error = 'Debe subir el archivo final (Pieza Gráfica Completada) o proporcionar una URL de Drive para completar la solicitud';
            }
            
            // Validar URL si se proporciona
            if ($tiene_url_nueva && !filter_var($url_drive, FILTER_VALIDATE_URL)) {
                $error = 'La URL proporcionada no es válida';
            }
        }
        
        if (empty($error)) {
        try {
            $conn->beginTransaction();
            
            // Procesar archivo final si se completa la solicitud (hasta 100MB)
            if ($estado === ESTADO_COMPLETADA && !empty($_FILES['archivo_final']['name'])) {
                require_once 'includes/file_handler.php';
                $fileHandler = new FileHandler($conn);
                
                if (isset($_FILES['archivo_final']['error']) && $_FILES['archivo_final']['error'] === UPLOAD_ERR_OK) {
                    // Usar tamaño máximo de 100MB para archivos finales
                    $fileHandler->uploadFile($id, [
                        'name' => $_FILES['archivo_final']['name'],
                        'type' => $_FILES['archivo_final']['type'] ?? '',
                        'tmp_name' => $_FILES['archivo_final']['tmp_name'],
                        'size' => $_FILES['archivo_final']['size'] ?? 0,
                        'error' => $_FILES['archivo_final']['error']
                    ], 100 * 1024 * 1024); // 100MB
                }
            }

            $estado_anterior = $solicitud['estado'];
            $fecha_actual = date('Y-m-d H:i:s');

            // Actualizar solicitud
            $update_fields = ['estado = ?'];
            $params = [$estado];

            if ($prioridad_ajustada !== '' && $prioridad_ajustada !== null && $prioridad_ajustada !== $solicitud['prioridad']) {
                $update_fields[] = 'prioridad = ?';
                $update_fields[] = 'prioridad_ajustada = ?';
                $update_fields[] = 'justificacion_prioridad = ?';
                $params[] = $prioridad_ajustada;
                $params[] = $prioridad_ajustada;
                $params[] = $justificacion;
            }

            if ($observaciones) {
                $update_fields[] = 'observaciones = ?';
                $params[] = $observaciones;
            }

            if ($cambiar_fecha_entrega === '1' && !empty($nueva_fecha_estimada_entrega)) {
                $update_fields[] = 'fecha_estimada_entrega = ?';
                $params[] = $nueva_fecha_estimada_entrega;
            }

            // Guardar URL de drive (puede estar vacía para eliminarla)
            if ($estado === ESTADO_COMPLETADA) {
                $update_fields[] = 'url_drive = ?';
                $params[] = $url_drive; // Puede estar vacío
            }

            // Actualizar fechas según estado
            if ($estado === ESTADO_APROBADA && !$solicitud['fecha_asignacion']) {
                $update_fields[] = 'fecha_asignacion = ?';
                $update_fields[] = 'administrador_id = ?';
                $params[] = $fecha_actual;
                $params[] = $_SESSION['user_id'];
            }

            if ($estado === ESTADO_EN_PROCESO && !$solicitud['fecha_inicio_proceso']) {
                $update_fields[] = 'fecha_inicio_proceso = ?';
                $params[] = $fecha_actual;
            }

            if ($estado === ESTADO_COMPLETADA && !$solicitud['fecha_completada']) {
                $update_fields[] = 'fecha_completada = ?';
                $params[] = $fecha_actual;
            }

            $params[] = $id;

            $sql = "UPDATE solicitudes SET " . implode(', ', $update_fields) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

            // Determinar texto de observación del historial según lo que cambió
            $cambio_estado = ($estado_anterior !== $estado);
            $cambio_fecha_entrega = ($cambiar_fecha_entrega === '1' && !empty($nueva_fecha_estimada_entrega));

            if ($cambio_estado && $cambio_fecha_entrega) {
                $obs_historial = $observaciones ?: 'Cambio de estado y de fecha estimada de entrega';
            } elseif ($cambio_fecha_entrega) {
                $obs_historial = 'Cambio de fecha estimada de entrega';
            } elseif ($cambio_estado) {
                $obs_historial = $observaciones ?: 'Cambio de estado';
            } else {
                $obs_historial = $observaciones ?: 'Actualización';
            }

            // Registrar en historial
            $stmt = $conn->prepare("
                INSERT INTO historial_estados (solicitud_id, estado_anterior, estado_nuevo, usuario_id, observacion)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$id, $estado_anterior, $estado, $_SESSION['user_id'], $obs_historial]);

            // Notificar cambio de estado al usuario solicitante
            if ($estado_anterior !== $estado) {
                notificarCambioEstado($conn, $id, $estado_anterior, $estado, $solicitud['usuario_id'], $_SESSION['user_id']);
            }

            // Actualizar métricas
            if ($estado === ESTADO_COMPLETADA) {
                require_once 'includes/metrics.php';
                updateMetrics($conn, $id);
            }

                $conn->commit();
                Logger::info('Solicitud actualizada', [
                    'solicitud_id' => $id,
                    'estado_anterior' => $estado_anterior,
                    'estado_nuevo' => $estado,
                    'usuario' => $_SESSION['user_id']
                ]);
                redirect('ver_solicitud.php?id=' . $id . '&success=' . urlencode('Solicitud actualizada exitosamente'));
        } catch (PDOException $e) {
            // Asegurarse de hacer rollback en caso de error de BD
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            Logger::error('Error de base de datos al actualizar solicitud', [
                'solicitud_id' => $id,
                'error' => $e->getMessage()
            ]);
            $error = 'Error al actualizar la solicitud. Por favor, intente nuevamente.';
        } catch (Exception $e) {
            // Asegurarse de hacer rollback en caso de cualquier error
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            Logger::error('Error general al actualizar solicitud', [
                'solicitud_id' => $id,
                'error' => $e->getMessage()
            ]);
            $error = 'Error al actualizar la solicitud: ' . $e->getMessage();
        }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Solicitud #<?php echo $id; ?> - SEGUI-GRAF</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo url('favicon.svg'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/style.css'); ?>">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container">
        <div class="page-header">
            <h1>Gestionar Solicitud #<?php echo $id; ?></h1>
            <a href="<?php echo url('ver_solicitud.php?id=' . $id); ?>" class="btn btn-secondary">Volver</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
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
                    <p><strong>Solicitante:</strong> <?php echo sanitize($solicitud['usuario_nombre']); ?></p>
                    <p><strong>Descripción:</strong> <?php echo nl2br(sanitize($solicitud['descripcion'])); ?></p>
                    <p><strong>Fecha Estimada de Entrega:</strong> <?php echo formatDate($solicitud['fecha_estimada_entrega']); ?></p>
                </div>
            </div>

            <form method="POST" class="form-container" enctype="multipart/form-data">
                <?php echo csrfField(); ?>
                <div class="form-section">
                    <h2>Gestión de Solicitud</h2>

                    <div class="form-group">
                        <label for="estado">Estado <span class="required">*</span></label>
                        <select id="estado" name="estado" required>
                            <option value="Recibido" <?php echo ($solicitud['estado'] === 'Recibido' || empty($solicitud['estado'])) ? 'selected' : ''; ?>>Recibido</option>
                            <option value="Pendiente de aprobación" <?php echo $solicitud['estado'] === 'Pendiente de aprobación' ? 'selected' : ''; ?>>Pendiente de aprobación</option>
                            <option value="Aprobada" <?php echo $solicitud['estado'] === 'Aprobada' ? 'selected' : ''; ?>>Aprobada</option>
                            <option value="En proceso" <?php echo $solicitud['estado'] === 'En proceso' ? 'selected' : ''; ?>>En proceso</option>
                            <option value="Completada" <?php echo $solicitud['estado'] === 'Completada' ? 'selected' : ''; ?>>Completada</option>
                            <option value="Cancelada" <?php echo $solicitud['estado'] === 'Cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="prioridad_ajustada">Ajustar Prioridad (Opcional)</label>
                        <select id="prioridad_ajustada" name="prioridad_ajustada">
                            <option value="">Mantener prioridad actual</option>
                            <option value="<?php echo PRIORIDAD_ALTA; ?>">🔴 Alta Prioridad – Urgente</option>
                            <option value="<?php echo PRIORIDAD_MEDIA; ?>">🟡 Prioridad Media – Programada</option>
                            <option value="<?php echo PRIORIDAD_BAJA; ?>">🟢 Prioridad Baja – Regular</option>
                        </select>
                    </div>

                    <div class="form-group" id="justificacion_group" style="display: none;">
                        <label for="justificacion_prioridad">Justificación del Cambio de Prioridad</label>
                        <textarea id="justificacion_prioridad" name="justificacion_prioridad" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="observaciones">Observaciones</label>
                        <textarea id="observaciones" name="observaciones" rows="4"></textarea>
                    </div>

                    <div class="form-group form-group-highlight" id="cambiar_fecha_entrega_block" style="<?php echo ($solicitud['estado'] ?? '') === 'En proceso' ? '' : 'display: none;'; ?>">
                        <label>¿Necesita cambiar la fecha de entrega?</label>
                        <div class="radio-group">
                            <?php $cambiar_fecha_val = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['cambiar_fecha_entrega'] ?? '0') : '0'; ?>
                            <label class="radio-label">
                                <input type="radio" name="cambiar_fecha_entrega" value="0" <?php echo $cambiar_fecha_val === '0' ? 'checked' : ''; ?>> No
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="cambiar_fecha_entrega" value="1" <?php echo $cambiar_fecha_val === '1' ? 'checked' : ''; ?>> Sí
                            </label>
                        </div>
                    </div>

                    <div class="form-group" id="nueva_fecha_entrega_group" style="display: none;">
                        <div class="form-row form-row-2cols">
                            <div class="form-group">
                                <label for="fecha_estimada_actual">Fecha estimada de entrega actual</label>
                                <input type="text" id="fecha_estimada_actual" value="<?php echo htmlspecialchars(formatDate($solicitud['fecha_estimada_entrega'])); ?>" readonly class="input-readonly">
                            </div>
                            <div class="form-group">
                                <label for="nueva_fecha_estimada_entrega">Nueva fecha estimada de entrega</label>
                                <input type="date" id="nueva_fecha_estimada_entrega" name="nueva_fecha_estimada_entrega" value="<?php echo !empty($_POST['nueva_fecha_estimada_entrega']) ? htmlspecialchars($_POST['nueva_fecha_estimada_entrega']) : ''; ?>" min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group" id="archivo_final_group" style="display: none;">
                        <label for="archivo_final">Archivo Final (Pieza Gráfica Completada)</label>
                        <input type="file" id="archivo_final" name="archivo_final" accept="*/*">
                        <small class="form-help">Suba la pieza gráfica final para que el usuario pueda descargarla. Cualquier formato. Máximo 100MB.</small>
                    </div>

                    <div class="form-group" id="url_drive_group" style="display: none;">
                        <label for="url_drive">URL de Drive (Para archivos mayores a 100MB)</label>
                        <input type="url" id="url_drive" name="url_drive" value="<?php echo htmlspecialchars($solicitud['url_drive'] ?? ''); ?>" placeholder="https://drive.google.com/file/d/..." style="width: 100%;">
                        <small class="form-help">Si el archivo es mayor a 100MB, proporcione una URL de Google Drive u otro servicio de almacenamiento. El archivo o la URL son obligatorios al completar la solicitud.</small>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    <a href="<?php echo url('ver_solicitud.php?id=' . $id); ?>" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="<?php echo url('assets/js/gestionar_solicitud.js'); ?>"></script>
</body>
</html>

