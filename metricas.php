<?php
require_once 'config/config.php';
requireAdmin();

$db = new Database();
$conn = $db->getConnection();

require_once 'includes/metrics.php';

// Obtener métricas generales
$metricas = getGeneralMetrics($conn, $_SESSION['user_id']);

// Obtener métricas por prioridad
$stmt = $conn->query("
    SELECT 
        s.prioridad,
        COUNT(*) as total,
        SUM(CASE WHEN s.estado = 'Completada' THEN 1 ELSE 0 END) as completadas,
        AVG(m.tiempo_total_horas) as tiempo_promedio
    FROM solicitudes s
    LEFT JOIN metricas_solicitudes m ON s.id = m.solicitud_id
    GROUP BY s.prioridad
");
$metricas_prioridad = $stmt->fetchAll();

// Obtener solicitudes recientes completadas
$stmt = $conn->prepare("
    SELECT s.*, u.nombre_completo as usuario_nombre, m.*
    FROM solicitudes s
    LEFT JOIN usuarios u ON s.usuario_id = u.id
    LEFT JOIN metricas_solicitudes m ON s.id = m.solicitud_id
    WHERE s.estado = 'Completada'
    ORDER BY s.fecha_completada DESC
    LIMIT 10
");
$stmt->execute();
$solicitudes_completadas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Métricas y Seguimiento - SEGUI-GRAF</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo url('favicon.svg'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/style.css'); ?>">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container">
        <div class="page-header">
            <h1>Métricas y Seguimiento</h1>
            <a href="<?php echo url('index.php'); ?>" class="btn btn-secondary">Volver</a>
        </div>

        <!-- Métricas Generales -->
        <div class="metrics-section">
            <h2>Métricas Generales</h2>
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-icon">📊</div>
                    <div class="metric-value"><?php echo $metricas['total']; ?></div>
                    <div class="metric-label">Total Solicitudes</div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon">✅</div>
                    <div class="metric-value"><?php echo $metricas['completadas']; ?></div>
                    <div class="metric-label">Completadas</div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon">⏱️</div>
                    <div class="metric-value"><?php echo $metricas['tiempo_respuesta_promedio']; ?>h</div>
                    <div class="metric-label">Tiempo Respuesta Promedio</div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon">⚡</div>
                    <div class="metric-value"><?php echo $metricas['tiempo_proceso_promedio']; ?>h</div>
                    <div class="metric-label">Tiempo Proceso Promedio</div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon">🎯</div>
                    <div class="metric-value"><?php echo $metricas['porcentaje_cumplimiento']; ?>%</div>
                    <div class="metric-label">Cumplimiento de Entrega</div>
                </div>
            </div>
        </div>

        <!-- Métricas por Prioridad -->
        <div class="metrics-section">
            <h2>Métricas por Prioridad</h2>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Prioridad</th>
                            <th>Total</th>
                            <th>Completadas</th>
                            <th>Tiempo Promedio (horas)</th>
                            <th>% Completadas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($metricas_prioridad as $metrica): ?>
                            <tr>
                                <td>
                                    <span class="badge <?php echo getPrioridadClass($metrica['prioridad']); ?>">
                                        <?php echo getPrioridadIcon($metrica['prioridad']); ?> 
                                        <?php echo $metrica['prioridad']; ?>
                                    </span>
                                </td>
                                <td><?php echo $metrica['total']; ?></td>
                                <td><?php echo $metrica['completadas']; ?></td>
                                <td><?php echo round($metrica['tiempo_promedio'] ?: 0, 2); ?></td>
                                <td>
                                    <?php 
                                    $porcentaje = $metrica['total'] > 0 
                                        ? round(($metrica['completadas'] / $metrica['total']) * 100, 2) 
                                        : 0;
                                    echo $porcentaje . '%';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Solicitudes Completadas Recientes -->
        <div class="metrics-section">
            <h2>Solicitudes Completadas Recientes</h2>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Solicitante</th>
                            <th>Fecha Completada</th>
                            <th>Tiempo Total</th>
                            <th>Cumplimiento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($solicitudes_completadas)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No hay solicitudes completadas</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($solicitudes_completadas as $sol): ?>
                                <tr>
                                    <td>#<?php echo $sol['id']; ?></td>
                                    <td><?php echo sanitize($sol['titulo']); ?></td>
                                    <td><?php echo sanitize($sol['usuario_nombre']); ?></td>
                                    <td><?php echo formatDateTime($sol['fecha_completada']); ?></td>
                                    <td><?php echo round($sol['tiempo_total_horas'] ?: 0, 2); ?>h</td>
                                    <td>
                                        <?php if ($sol['cumplimiento_entrega']): ?>
                                            <span class="badge estado-completada">✅ Cumplida</span>
                                        <?php else: ?>
                                            <span class="badge estado-cancelada">❌ Retrasada</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>

