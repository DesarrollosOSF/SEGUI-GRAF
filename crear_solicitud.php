<?php
require_once 'config/config.php';
requireAuth();

require_once 'includes/notifications.php';

$db = new Database();
$conn = $db->getConnection();

$volver_url = obtenerUrlVolverTareas();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFToken();
    
    Logger::audit('Crear solicitud', $_SESSION['user_id'], [
        'accion' => 'nueva_solicitud'
    ]);
    
    $titulo = sanitize($_POST['titulo'] ?? '');
    $descripcion = sanitize($_POST['descripcion'] ?? '');
    $fecha_estimada = $_POST['fecha_estimada_entrega'] ?? '';
    $tipo_uso = $_POST['tipo_uso'] ?? '';
    $fecha_publicacion = $_POST['fecha_publicacion'] ?? null;
    $tipo_requerimiento = sanitize($_POST['tipo_requerimiento'] ?? '');
    // Si seleccionó "Otro", usar el valor del campo de texto
    if ($tipo_requerimiento === 'Otro' && !empty($_POST['tipo_requerimiento_otro'])) {
        $tipo_requerimiento = sanitize($_POST['tipo_requerimiento_otro']);
    }
    $prioridad = $_POST['prioridad'] ?? PRIORIDAD_BAJA;

    // Validaciones
    if (empty($titulo) || empty($descripcion) || empty($fecha_estimada) || empty($tipo_uso)) {
        $error = 'Por favor, complete todos los campos obligatorios';
    } else {
        try {
            $conn->beginTransaction();

            // Insertar solicitud
            $stmt = $conn->prepare("
                INSERT INTO solicitudes 
                (usuario_id, titulo, descripcion, fecha_estimada_entrega, tipo_uso, fecha_publicacion, tipo_requerimiento, prioridad, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $titulo,
                $descripcion,
                $fecha_estimada,
                $tipo_uso,
                $fecha_publicacion ?: null,
                $tipo_requerimiento ?: null,
                $prioridad,
                ESTADO_RECIBIDO
            ]);

            $solicitud_id = $conn->lastInsertId();

            // Procesar archivos adjuntos (usar la misma conexión de la transacción)
            $listaArchivos = normalizarArchivosRequest('archivo');
            $erroresAdjuntos = [];
            if (!empty($listaArchivos)) {
                require_once 'includes/file_handler.php';
                $fileHandler = new FileHandler($conn);

                foreach ($listaArchivos as $archivoItem) {
                    $nombreMostrar = $archivoItem['name'];
                    if ((int) $archivoItem['error'] !== UPLOAD_ERR_OK) {
                        $msg = mensajeErrorSubidaPHP($archivoItem['error']);
                        if ($msg !== '') {
                            $erroresAdjuntos[] = $nombreMostrar . ': ' . $msg;
                        }
                        Logger::warning('Subida rechazada por PHP', [
                            'archivo' => $nombreMostrar,
                            'codigo' => $archivoItem['error'],
                        ]);
                        continue;
                    }
                    try {
                        $fileHandler->uploadFile($solicitud_id, $archivoItem);
                    } catch (Exception $fileError) {
                        $erroresAdjuntos[] = $nombreMostrar . ': ' . $fileError->getMessage();
                        Logger::error('Error al guardar adjunto al crear solicitud', [
                            'solicitud_id' => $solicitud_id,
                            'archivo' => $nombreMostrar,
                            'error' => $fileError->getMessage(),
                        ]);
                    }
                }
            }

            // Registrar en historial
            $stmt = $conn->prepare("
                INSERT INTO historial_estados (solicitud_id, estado_anterior, estado_nuevo, usuario_id, observacion)
                VALUES (?, NULL, ?, ?, 'Solicitud creada')
            ");
            $stmt->execute([$solicitud_id, ESTADO_RECIBIDO, $_SESSION['user_id']]);

            $conn->commit();
            Logger::info('Solicitud creada', [
                'solicitud_id' => $solicitud_id,
                'usuario' => $_SESSION['user_id'],
                'titulo' => $titulo
            ]);
            // Notificar a los administradores sobre la nueva solicitud
            try {
                notificarNuevaSolicitud($conn, $solicitud_id, $_SESSION['user_id'], $titulo);
            } catch (Exception $e) {
                Logger::error('Error al notificar nueva solicitud a administradores', [
                    'solicitud_id' => $solicitud_id,
                    'error' => $e->getMessage()
                ]);
            }
            $urlVer = 'ver_solicitud.php?id=' . $solicitud_id . '&success=' . urlencode('Solicitud creada exitosamente');
            if (!empty($erroresAdjuntos)) {
                $urlVer .= '&adjuntos_aviso=' . urlencode(implode(' ', $erroresAdjuntos));
            }
            redirect($urlVer);
        } catch (PDOException $e) {
            // Asegurarse de hacer rollback en caso de error de BD
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            Logger::error('Error de base de datos al crear solicitud', [
                'usuario' => $_SESSION['user_id'],
                'error' => $e->getMessage()
            ]);
            $error = 'Error al crear la solicitud. Por favor, intente nuevamente.';
        } catch (Exception $e) {
            // Asegurarse de hacer rollback en caso de cualquier error
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            Logger::error('Error general al crear solicitud', [
                'usuario' => $_SESSION['user_id'],
                'error' => $e->getMessage()
            ]);
            $error = 'Error al crear la solicitud: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Solicitud - SEGUI-GRAF</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo url('favicon.svg'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/style.css'); ?>">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container">
        <div class="page-header">
            <h1>Nueva Solicitud de Pieza Gráfica</h1>
            <a href="<?php echo $volver_url; ?>" class="btn btn-secondary">Volver</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="form-container">
            <?php echo csrfField(); ?>
            <div class="form-section">
                <h2>Información General</h2>
                
                <div class="form-group">
                    <label for="titulo">Título de la Solicitud <span class="required">*</span></label>
                    <input type="text" id="titulo" name="titulo" required maxlength="200">
                </div>

                <div class="form-group">
                    <label for="descripcion">Descripción Detallada <span class="required">*</span></label>
                    <textarea id="descripcion" name="descripcion" rows="5" required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="fecha_estimada_entrega">Fecha Estimada de Entrega <span class="required">*</span></label>
                        <input type="date" id="fecha_estimada_entrega" name="fecha_estimada_entrega" required value="<?php echo date('Y-m-d', strtotime('+5 weekdays')); ?>">
                        <small class="form-help">Por defecto: 5 días hábiles desde hoy</small>
                    </div>

                    <div class="form-group">
                        <label for="prioridad">Prioridad <span class="required">*</span></label>
                        <select id="prioridad" name="prioridad" required>
                            <option value="<?php echo PRIORIDAD_BAJA; ?>">🟢 Prioridad Baja – Regular</option>
                            <option value="<?php echo PRIORIDAD_MEDIA; ?>">🟡 Prioridad Media – Programada</option>
                            <option value="<?php echo PRIORIDAD_ALTA; ?>">🔴 Alta Prioridad – Urgente</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2>Tipo de Uso</h2>
                
                <div class="form-group">
                    <label for="tipo_uso">Tipo de Uso <span class="required">*</span></label>
                    <select id="tipo_uso" name="tipo_uso" required>
                        <option value="">Seleccione...</option>
                        <option value="Uso interno">Uso interno</option>
                        <option value="Uso externo">Uso externo</option>
                        <option value="Uso mixto">Uso mixto</option>
                    </select>
                </div>

                <div class="form-group" id="fecha_publicacion_group" style="display: none;">
                    <label for="fecha_publicacion">Fecha de Publicación</label>
                    <input type="date" id="fecha_publicacion" name="fecha_publicacion">
                </div>

                <div class="form-group">
                    <label for="tipo_requerimiento">Tipo de Requerimiento</label>
                    <select id="tipo_requerimiento" name="tipo_requerimiento">
                        <option value="">Seleccione un tipo...</option>
                        <option value="Afiche">Afiche</option>
                        <option value="Banner">Banner</option>
                        <option value="Brochure">Brochure</option>
                        <option value="Flyer">Flyer</option>
                        <option value="Infografía">Infografía</option>
                        <option value="Logo">Logo</option>
                        <option value="Presentación">Presentación</option>
                        <option value="Post para Redes Sociales">Post para Redes Sociales</option>
                        <option value="Tarjeta de Presentación">Tarjeta de Presentación</option>
                        <option value="Volante">Volante</option>
                        <option value="Otro">Otro</option>
                    </select>
                    <input type="text" id="tipo_requerimiento_otro" name="tipo_requerimiento_otro" placeholder="Especifique otro tipo" maxlength="100" style="display: none; margin-top: 0.5rem;">
                </div>
            </div>

            <div class="form-section">
                <h2>Archivos Adjuntos (Opcional)</h2>
                
                <div class="form-group">
                    <label for="archivo">Seleccionar Archivos</label>
                    <input type="file" id="archivo" name="archivo[]" multiple accept="image/*,audio/*,.pdf,.doc,.docx,.ppt,.pptx,.pptm,.ppsx,.pps,.xls,.xlsx,.zip,.rar,.mp3,.wav,.ogg,.m4a,.aac,.flac,.opus,.webm">
                    <small class="form-help">Formatos permitidos: JPG, PNG, GIF, WEBP, PDF, Word, Excel (XLS, XLSX), PowerPoint (PPT, PPTX, PPSX, PPTM, etc.), ZIP, RAR, Audio (MP3, WAV, OGG, M4A, AAC, FLAC, OPUS, WEBM). Máximo 50MB por archivo. Si falla la subida, revise los límites de PHP (upload_max_filesize / post_max_size) en el servidor.</small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Crear Solicitud</button>
                <a href="<?php echo $volver_url; ?>" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="<?php echo url('assets/js/crear_solicitud.js'); ?>"></script>
</body>
</html>

