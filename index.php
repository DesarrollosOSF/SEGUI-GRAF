<?php
require_once 'config/config.php';
requireAuth();

$db = new Database();
$conn = $db->getConnection();

// Parámetros de búsqueda y filtros
$busqueda = sanitize($_GET['busqueda'] ?? '');
$filtro_estado = $_GET['filtro_estado'] ?? '';
$filtro_prioridad = $_GET['filtro_prioridad'] ?? '';
$filtro_usuario = $_GET['filtro_usuario'] ?? '';

// Fechas por defecto: mes actual
$fecha_desde_default = date('Y-m-01'); // Primer día del mes actual
$fecha_hasta_default = date('Y-m-t'); // Último día del mes actual

$fecha_desde = $_GET['fecha_desde'] ?? $fecha_desde_default;
$fecha_hasta = $_GET['fecha_hasta'] ?? $fecha_hasta_default;

// Paginación
$pagina_actual = max(1, (int)($_GET['pagina'] ?? 1));
$registros_por_pagina = 15;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Construir query base
$where_conditions = [];
$params = [];

if (!isAdmin()) {
    $where_conditions[] = "s.usuario_id = ?";
    $params[] = $_SESSION['user_id'];
}

if (!empty($busqueda)) {
    // Búsqueda mejorada: título, descripción y comentarios
    $where_conditions[] = "(s.titulo LIKE ? OR s.descripcion LIKE ? OR s.observaciones LIKE ? OR EXISTS (
        SELECT 1 FROM comentarios_solicitudes c 
        WHERE c.solicitud_id = s.id AND c.comentario LIKE ?
    ))";
    $busqueda_param = "%{$busqueda}%";
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
}

if (!empty($filtro_estado)) {
    $where_conditions[] = "s.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_prioridad)) {
    $where_conditions[] = "s.prioridad = ?";
    $params[] = $filtro_prioridad;
}

if (isAdmin() && !empty($filtro_usuario)) {
    $where_conditions[] = "s.usuario_id = ?";
    $params[] = (int)$filtro_usuario;
}

if (!empty($fecha_desde)) {
    $where_conditions[] = "s.fecha_solicitud >= ?";
    $params[] = $fecha_desde . ' 00:00:00';
}

if (!empty($fecha_hasta)) {
    $where_conditions[] = "s.fecha_solicitud <= ?";
    $params[] = $fecha_hasta . ' 23:59:59';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Contar total de registros
$count_sql = "
    SELECT COUNT(DISTINCT s.id)
    FROM solicitudes s
    " . (isAdmin() ? "LEFT JOIN usuarios u ON s.usuario_id = u.id" : "") . "
    {$where_clause}
";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total_registros = $count_stmt->fetchColumn();
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Obtener solicitudes con paginación
$sql = "
    SELECT s.*, " . (isAdmin() ? "u.nombre_completo as usuario_nombre," : "") . "
           COUNT(a.id) as num_archivos
    FROM solicitudes s
    " . (isAdmin() ? "LEFT JOIN usuarios u ON s.usuario_id = u.id" : "") . "
    LEFT JOIN archivos_adjuntos a ON s.id = a.solicitud_id
    {$where_clause}
    GROUP BY s.id
    ORDER BY 
        CASE s.prioridad
            WHEN 'Alta Prioridad – Urgente' THEN 1
            WHEN 'Prioridad Media – Programada' THEN 2
            WHEN 'Prioridad Baja – Regular' THEN 3
        END,
        s.fecha_solicitud DESC
    LIMIT ? OFFSET ?
";
$params[] = $registros_por_pagina;
$params[] = $offset;
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll();

// Obtener lista de usuarios para filtro (solo admin)
$usuarios_lista = [];
if (isAdmin()) {
    $stmt = $conn->query("SELECT id, nombre_completo FROM usuarios ORDER BY nombre_completo");
    $usuarios_lista = $stmt->fetchAll();
}

// Estadísticas
$user_id = (int)$_SESSION['user_id'];
if (isAdmin()) {
    $stats = [
        'total' => $conn->query("SELECT COUNT(*) FROM solicitudes")->fetchColumn(),
        'recibidas' => $conn->query("SELECT COUNT(*) FROM solicitudes WHERE estado = 'Recibido'")->fetchColumn(),
        'pendientes' => $conn->query("SELECT COUNT(*) FROM solicitudes WHERE estado = 'Pendiente de aprobación'")->fetchColumn(),
        'en_proceso' => $conn->query("SELECT COUNT(*) FROM solicitudes WHERE estado = 'En proceso'")->fetchColumn(),
        'completadas' => $conn->query("SELECT COUNT(*) FROM solicitudes WHERE estado = 'Completada'")->fetchColumn(),
    ];
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM solicitudes WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    $stats['total'] = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM solicitudes WHERE usuario_id = ? AND estado = 'Recibido'");
    $stmt->execute([$user_id]);
    $stats['recibidas'] = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM solicitudes WHERE usuario_id = ? AND estado = 'Pendiente de aprobación'");
    $stmt->execute([$user_id]);
    $stats['pendientes'] = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM solicitudes WHERE usuario_id = ? AND estado = 'En proceso'");
    $stmt->execute([$user_id]);
    $stats['en_proceso'] = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM solicitudes WHERE usuario_id = ? AND estado = 'Completada'");
    $stmt->execute([$user_id]);
    $stats['completadas'] = $stmt->fetchColumn();
}

$page_title = isAdmin() ? 'Tablero de Gestión' : 'Mis Solicitudes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SEGUI-GRAF</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo url('favicon.svg'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/style.css'); ?>">
    <script src="<?php echo url('assets/js/toast.js'); ?>" defer></script>
    <script src="<?php echo url('assets/js/file-preview.js'); ?>" defer></script>
    <script src="<?php echo url('assets/js/busqueda-avanzada.js'); ?>" defer></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container">
        <div class="page-header">
            <h1><?php echo $page_title; ?></h1>
            <?php if (!isAdmin()): ?>
                <a href="<?php echo url('crear_solicitud.php'); ?>" class="btn btn-primary">Nueva Solicitud</a>
            <?php endif; ?>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Solicitudes</div>
            </div>
            <?php if (isset($stats['recibidas'])): ?>
            <div class="stat-card" style="background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); border-left: 4px solid #6366f1;">
                <div class="stat-value"><?php echo $stats['recibidas']; ?></div>
                <div class="stat-label">Recibidas</div>
            </div>
            <?php endif; ?>
            <div class="stat-card stat-pendiente">
                <div class="stat-value"><?php echo $stats['pendientes']; ?></div>
                <div class="stat-label">Pendientes</div>
            </div>
            <div class="stat-card stat-proceso">
                <div class="stat-value"><?php echo $stats['en_proceso']; ?></div>
                <div class="stat-label">En Proceso</div>
            </div>
            <div class="stat-card stat-completada">
                <div class="stat-value"><?php echo $stats['completadas']; ?></div>
                <div class="stat-label">Completadas</div>
            </div>
        </div>

        <!-- Búsqueda y Filtros -->
        <form method="GET" action="" class="search-filters-form">
            <div class="search-section">
                <div class="form-group" style="flex: 1; margin-bottom: 0;">
                    <input type="text" name="busqueda" placeholder="Buscar por título o descripción..." 
                           value="<?php echo htmlspecialchars($busqueda); ?>" class="form-control">
                </div>
                <button type="submit" class="btn btn-primary">Buscar</button>
                <a href="<?php echo url('exportar.php?' . http_build_query($_GET) . '&formato=excel'); ?>" class="btn btn-secondary">Exportar Excel</a>
                <a href="<?php echo url('exportar.php?' . http_build_query($_GET) . '&formato=pdf'); ?>" class="btn btn-secondary" target="_blank">Exportar PDF</a>
                <?php 
                // Mostrar botón limpiar solo si hay filtros activos (no solo los por defecto)
                $hay_filtros_activos = !empty($busqueda) || 
                                      !empty($filtro_estado) || 
                                      !empty($filtro_prioridad) || 
                                      !empty($filtro_usuario) || 
                                      ($fecha_desde != $fecha_desde_default) || 
                                      ($fecha_hasta != $fecha_hasta_default);
                if ($hay_filtros_activos): ?>
                    <a href="<?php echo url('index.php'); ?>" class="btn btn-secondary">Limpiar</a>
                <?php endif; ?>
            </div>
            
            <div class="filters">
                <?php if (isAdmin()): ?>
                    <select name="filtro_usuario" class="form-control">
                        <option value="">Todos los usuarios</option>
                        <?php foreach ($usuarios_lista as $usr): ?>
                            <option value="<?php echo $usr['id']; ?>" <?php echo $filtro_usuario == $usr['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($usr['nombre_completo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                
                <select name="filtro_estado" class="form-control">
                    <option value="">Todos los estados</option>
                    <option value="Recibido" <?php echo $filtro_estado === 'Recibido' ? 'selected' : ''; ?>>Recibido</option>
                    <option value="Pendiente de aprobación" <?php echo $filtro_estado === 'Pendiente de aprobación' ? 'selected' : ''; ?>>Pendiente de aprobación</option>
                    <option value="Aprobada" <?php echo $filtro_estado === 'Aprobada' ? 'selected' : ''; ?>>Aprobada</option>
                    <option value="En proceso" <?php echo $filtro_estado === 'En proceso' ? 'selected' : ''; ?>>En proceso</option>
                    <option value="Completada" <?php echo $filtro_estado === 'Completada' ? 'selected' : ''; ?>>Completada</option>
                    <option value="Cancelada" <?php echo $filtro_estado === 'Cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                </select>
                
                <select name="filtro_prioridad" class="form-control">
                    <option value="">Todas las prioridades</option>
                    <option value="Alta Prioridad – Urgente" <?php echo $filtro_prioridad === 'Alta Prioridad – Urgente' ? 'selected' : ''; ?>>Alta Prioridad</option>
                    <option value="Prioridad Media – Programada" <?php echo $filtro_prioridad === 'Prioridad Media – Programada' ? 'selected' : ''; ?>>Prioridad Media</option>
                    <option value="Prioridad Baja – Regular" <?php echo $filtro_prioridad === 'Prioridad Baja – Regular' ? 'selected' : ''; ?>>Prioridad Baja</option>
                </select>
                
                <input type="date" name="fecha_desde" placeholder="Desde" 
                       value="<?php echo htmlspecialchars($fecha_desde); ?>" class="form-control">
                <input type="date" name="fecha_hasta" placeholder="Hasta" 
                       value="<?php echo htmlspecialchars($fecha_hasta); ?>" class="form-control">
            </div>
        </form>
        
        <?php if ($total_registros > 0): ?>
            <div class="results-info">
                Mostrando <?php echo count($solicitudes); ?> de <?php echo $total_registros; ?> solicitudes
            </div>
        <?php endif; ?>

        <!-- Tabla de solicitudes -->
        <div class="table-container">
            <table class="data-table" <?php echo isAdmin() ? '' : 'data-no-admin="1"'; ?>>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Título</th>
                        <?php if (isAdmin()): ?>
                            <th>Solicitante</th>
                        <?php endif; ?>
                        <th class="hide-mobile">Fecha Solicitud</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th class="hide-mobile">Fecha Estimada</th>
                        <th class="hide-tablet">Archivos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($solicitudes)): ?>
                        <tr>
                            <td colspan="<?php echo isAdmin() ? '9' : '8'; ?>" class="text-center">No hay solicitudes registradas</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($solicitudes as $sol): ?>
                            <tr data-estado="<?php echo $sol['estado']; ?>" data-prioridad="<?php echo $sol['prioridad']; ?>">
                                <td><strong>#<?php echo $sol['id']; ?></strong></td>
                                <td>
                                    <strong><?php echo sanitize($sol['titulo']); ?></strong>
                                    <div class="table-mobile-info hide-desktop" style="margin-top: 0.25rem; font-size: 0.75rem; color: var(--text-muted);">
                                        <?php echo formatDate($sol['fecha_solicitud'], 'd/m/Y'); ?> • 
                                        <?php echo formatDate($sol['fecha_estimada_entrega']); ?>
                                        <?php if ($sol['num_archivos'] > 0): ?>
                                            • <?php echo $sol['num_archivos']; ?> archivo(s)
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php if (isAdmin()): ?>
                                    <td><?php echo sanitize($sol['usuario_nombre']); ?></td>
                                <?php endif; ?>
                                <td class="hide-mobile"><?php echo formatDate($sol['fecha_solicitud'], 'd/m/Y H:i'); ?></td>
                                <td>
                                    <span class="badge <?php echo getPrioridadClass($sol['prioridad']); ?>">
                                        <?php echo getPrioridadIcon($sol['prioridad']); ?> 
                                        <span class="hide-mobile"><?php echo $sol['prioridad']; ?></span>
                                        <span class="show-mobile-only"><?php 
                                            echo $sol['prioridad'] === 'Alta Prioridad – Urgente' ? 'Alta' : 
                                                ($sol['prioridad'] === 'Prioridad Media – Programada' ? 'Media' : 'Baja');
                                        ?></span>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo getEstadoClass($sol['estado']); ?>">
                                        <?php echo $sol['estado']; ?>
                                    </span>
                                </td>
                                <td class="hide-mobile"><?php echo formatDate($sol['fecha_estimada_entrega']); ?></td>
                                <td class="hide-tablet">
                                    <?php if ($sol['num_archivos'] > 0): ?>
                                        <span class="badge"><?php echo $sol['num_archivos']; ?> archivo(s)</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.25rem; flex-wrap: wrap;">
                                        <a href="<?php echo url('ver_solicitud.php?id=' . $sol['id']); ?>" class="btn btn-sm btn-info">Ver</a>
                                        <?php if (isAdmin() && $sol['estado'] !== 'Cancelada' && $sol['estado'] !== 'Completada'): ?>
                                            <a href="<?php echo url('gestionar_solicitud.php?id=' . $sol['id']); ?>" class="btn btn-sm btn-primary">Gestionar</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación Mejorada -->
        <?php if ($total_paginas > 1): ?>
            <div class="pagination" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; flex-wrap: wrap; margin-top: 2rem; padding: 1rem; background: var(--card-bg); border-radius: var(--radius); box-shadow: var(--shadow);">
                <?php
                $query_params = $_GET;
                unset($query_params['pagina']);
                $base_url = url('index.php') . (empty($query_params) ? '' : '?' . http_build_query($query_params));
                $separator = empty($query_params) ? '?' : '&';
                ?>
                
                <?php if ($pagina_actual > 1): ?>
                    <a href="<?php echo $base_url . $separator . 'pagina=' . ($pagina_actual - 1); ?>" class="btn btn-sm btn-secondary">« Anterior</a>
                <?php else: ?>
                    <span class="btn btn-sm btn-secondary pagination-disabled">« Anterior</span>
                <?php endif; ?>
                
                <span class="pagination-info">
                    Página <strong><?php echo $pagina_actual; ?></strong> de <strong><?php echo $total_paginas; ?></strong>
                    <span class="pagination-total">(<?php echo $total_registros; ?> total)</span>
                </span>
                
                <?php
                // Mostrar números de página (máximo 5 números visibles)
                $inicio = max(1, $pagina_actual - 2);
                $fin = min($total_paginas, $pagina_actual + 2);
                
                if ($inicio > 1): ?>
                    <a href="<?php echo $base_url . $separator . 'pagina=1'; ?>" class="btn btn-sm btn-secondary">1</a>
                    <?php if ($inicio > 2): ?>
                        <span class="pagination-ellipsis">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $inicio; $i <= $fin; $i++): ?>
                    <?php if ($i == $pagina_actual): ?>
                        <span class="btn btn-sm btn-primary pagination-current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo $base_url . $separator . 'pagina=' . $i; ?>" class="btn btn-sm btn-secondary"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($fin < $total_paginas): ?>
                    <?php if ($fin < $total_paginas - 1): ?>
                        <span class="pagination-ellipsis">...</span>
                    <?php endif; ?>
                    <a href="<?php echo $base_url . $separator . 'pagina=' . $total_paginas; ?>" class="btn btn-sm btn-secondary"><?php echo $total_paginas; ?></a>
                <?php endif; ?>
                
                <?php if ($pagina_actual < $total_paginas): ?>
                    <a href="<?php echo $base_url . $separator . 'pagina=' . ($pagina_actual + 1); ?>" class="btn btn-sm btn-secondary">Siguiente »</a>
                <?php else: ?>
                    <span class="btn btn-sm btn-secondary pagination-disabled">Siguiente »</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>

