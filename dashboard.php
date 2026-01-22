<?php
require_once 'config/config.php';
requireAdmin();

$db = new Database();
$conn = $db->getConnection();

// Datos para gráficos
// Solicitudes por mes (últimos 6 meses)
$stmt = $conn->query("
    SELECT 
        DATE_FORMAT(fecha_solicitud, '%Y-%m') as mes,
        COUNT(*) as total
    FROM solicitudes
    WHERE fecha_solicitud >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY mes
    ORDER BY mes ASC
");
$solicitudes_por_mes = $stmt->fetchAll();

// Distribución por estado
$stmt = $conn->query("
    SELECT estado, COUNT(*) as total
    FROM solicitudes
    GROUP BY estado
");
$distribucion_estado = $stmt->fetchAll();

// Solicitudes por prioridad
$stmt = $conn->query("
    SELECT prioridad, COUNT(*) as total
    FROM solicitudes
    GROUP BY prioridad
");
$solicitudes_prioridad = $stmt->fetchAll();

// Solicitudes por usuario (top 5)
$stmt = $conn->query("
    SELECT u.nombre_completo, COUNT(s.id) as total
    FROM solicitudes s
    LEFT JOIN usuarios u ON s.usuario_id = u.id
    GROUP BY u.id, u.nombre_completo
    ORDER BY total DESC
    LIMIT 5
");
$solicitudes_usuario = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SEGUI-GRAF</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo url('favicon.svg'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/style.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container">
        <div class="page-header">
            <h1>Dashboard</h1>
            <a href="<?php echo url('index.php'); ?>" class="btn btn-secondary">Volver</a>
        </div>

        <!-- Estadísticas rápidas -->
        <div class="stats-grid" style="margin-bottom: 2rem;">
            <?php
            $total_solicitudes = $conn->query("SELECT COUNT(*) FROM solicitudes")->fetchColumn();
            $solicitudes_mes = $conn->query("SELECT COUNT(*) FROM solicitudes WHERE fecha_solicitud >= DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn();
            $solicitudes_completadas_mes = $conn->query("SELECT COUNT(*) FROM solicitudes WHERE estado = 'Completada' AND fecha_completada >= DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn();
            $tiempo_promedio = $conn->query("
                SELECT AVG(TIMESTAMPDIFF(HOUR, fecha_solicitud, fecha_completada)) as promedio
                FROM solicitudes 
                WHERE estado = 'Completada' AND fecha_completada IS NOT NULL
            ")->fetchColumn();
            ?>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_solicitudes; ?></div>
                <div class="stat-label">Total Solicitudes</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);">
                <div class="stat-value"><?php echo $solicitudes_mes; ?></div>
                <div class="stat-label">Este Mes</div>
            </div>
            <div class="stat-card stat-completada">
                <div class="stat-value"><?php echo $solicitudes_completadas_mes; ?></div>
                <div class="stat-label">Completadas (Mes)</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);">
                <div class="stat-value"><?php echo $tiempo_promedio ? number_format($tiempo_promedio / 24, 1) : '0'; ?></div>
                <div class="stat-label">Días Promedio</div>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Gráfico: Solicitudes por Mes -->
            <div class="dashboard-card">
                <h2>Solicitudes por Mes (Últimos 6 meses)</h2>
                <canvas id="chartMes"></canvas>
            </div>

            <!-- Gráfico: Distribución por Estado -->
            <div class="dashboard-card">
                <h2>Distribución por Estado</h2>
                <canvas id="chartEstado"></canvas>
            </div>

            <!-- Gráfico: Solicitudes por Prioridad -->
            <div class="dashboard-card">
                <h2>Solicitudes por Prioridad</h2>
                <canvas id="chartPrioridad"></canvas>
            </div>

            <!-- Gráfico: Top 5 Usuarios -->
            <div class="dashboard-card">
                <h2>Top 5 Usuarios con Más Solicitudes</h2>
                <canvas id="chartUsuarios"></canvas>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script>
        // Datos para gráficos
        const datosMes = {
            labels: <?php echo json_encode(array_column($solicitudes_por_mes, 'mes')); ?>,
            data: <?php echo json_encode(array_column($solicitudes_por_mes, 'total')); ?>
        };

        const datosEstado = {
            labels: <?php echo json_encode(array_column($distribucion_estado, 'estado')); ?>,
            data: <?php echo json_encode(array_column($distribucion_estado, 'total')); ?>
        };

        const datosPrioridad = {
            labels: <?php echo json_encode(array_column($solicitudes_prioridad, 'prioridad')); ?>,
            data: <?php echo json_encode(array_column($solicitudes_prioridad, 'total')); ?>
        };

        const datosUsuarios = {
            labels: <?php echo json_encode(array_column($solicitudes_usuario, 'nombre_completo')); ?>,
            data: <?php echo json_encode(array_column($solicitudes_usuario, 'total')); ?>
        };

        // Gráfico de barras: Solicitudes por mes
        new Chart(document.getElementById('chartMes'), {
            type: 'bar',
            data: {
                labels: datosMes.labels,
                datasets: [{
                    label: 'Solicitudes',
                    data: datosMes.data,
                    backgroundColor: 'rgba(37, 99, 235, 0.8)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1.5,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Gráfico circular: Distribución por estado
        new Chart(document.getElementById('chartEstado'), {
            type: 'doughnut',
            data: {
                labels: datosEstado.labels,
                datasets: [{
                    data: datosEstado.data,
                    backgroundColor: [
                        'rgba(100, 116, 139, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1.2
            }
        });

        // Gráfico de barras horizontales: Prioridad
        new Chart(document.getElementById('chartPrioridad'), {
            type: 'bar',
            data: {
                labels: datosPrioridad.labels,
                datasets: [{
                    label: 'Solicitudes',
                    data: datosPrioridad.data,
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(16, 185, 129, 0.8)'
                    ]
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1.3,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { beginAtZero: true }
                }
            }
        });

        // Gráfico de barras: Top usuarios
        new Chart(document.getElementById('chartUsuarios'), {
            type: 'bar',
            data: {
                labels: datosUsuarios.labels,
                datasets: [{
                    label: 'Solicitudes',
                    data: datosUsuarios.data,
                    backgroundColor: 'rgba(139, 92, 246, 0.8)',
                    borderColor: 'rgba(139, 92, 246, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1.5,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>
</body>
</html>

