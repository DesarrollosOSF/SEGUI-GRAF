<?php
require_once 'config/config.php';
requireAuth();

$db = new Database();
$conn = $db->getConnection();

$formato = $_GET['formato'] ?? 'excel';
$filtro_estado = $_GET['filtro_estado'] ?? '';
$filtro_prioridad = $_GET['filtro_prioridad'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Construir query con filtros
$where_conditions = [];
$params = [];

if (!isAdmin()) {
    $where_conditions[] = "s.usuario_id = ?";
    $params[] = $_SESSION['user_id'];
}

if (!empty($filtro_estado)) {
    $where_conditions[] = "s.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_prioridad)) {
    $where_conditions[] = "s.prioridad = ?";
    $params[] = $filtro_prioridad;
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

// Obtener solicitudes
$sql = "
    SELECT s.*, u.nombre_completo as usuario_nombre
    FROM solicitudes s
    " . (isAdmin() ? "LEFT JOIN usuarios u ON s.usuario_id = u.id" : "") . "
    {$where_clause}
    ORDER BY s.fecha_solicitud DESC
";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll();

if ($formato === 'excel' || $formato === 'csv') {
    // Exportar a Excel/CSV
    $delimiter = $formato === 'csv' ? ',' : "\t";
    $filename = 'solicitudes_' . date('Y-m-d') . ($formato === 'csv' ? '.csv' : '.xls');
    
    header('Content-Type: text/' . ($formato === 'csv' ? 'csv' : 'plain'));
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // BOM para Excel
    echo "\xEF\xBB\xBF";
    
    // Encabezados
    $headers = ['ID', 'Título', 'Solicitante', 'Fecha Solicitud', 'Fecha Estimada', 'Prioridad', 'Estado', 'Tipo Uso', 'Tipo Requerimiento'];
    echo implode($delimiter, $headers) . "\n";
    
    // Datos
    foreach ($solicitudes as $sol) {
        $row = [
            $sol['id'],
            '"' . str_replace('"', '""', $sol['titulo']) . '"',
            '"' . str_replace('"', '""', $sol['usuario_nombre'] ?? '') . '"',
            formatDate($sol['fecha_solicitud'], 'd/m/Y H:i'),
            formatDate($sol['fecha_estimada_entrega']),
            $sol['prioridad'],
            $sol['estado'],
            $sol['tipo_uso'],
            $sol['tipo_requerimiento'] ?? ''
        ];
        echo implode($delimiter, $row) . "\n";
    }
    exit;
} elseif ($formato === 'pdf') {
    // Exportar a PDF (HTML simple que se puede convertir a PDF)
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <link rel="icon" type="image/svg+xml" href="<?php echo url('favicon.svg'); ?>">
        <title>Reporte de Solicitudes - <?php echo date('d/m/Y'); ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #2563eb; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f3f4f6; font-weight: bold; }
            tr:nth-child(even) { background-color: #f9fafb; }
        </style>
    </head>
    <body>
        <h1>Reporte de Solicitudes</h1>
        <p><strong>Fecha de generación:</strong> <?php echo date('d/m/Y H:i'); ?></p>
        <p><strong>Total de solicitudes:</strong> <?php echo count($solicitudes); ?></p>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <?php if (isAdmin()): ?><th>Solicitante</th><?php endif; ?>
                    <th>Fecha Solicitud</th>
                    <th>Fecha Estimada</th>
                    <th>Prioridad</th>
                    <th>Estado</th>
                    <th>Tipo Uso</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($solicitudes as $sol): ?>
                    <tr>
                        <td>#<?php echo $sol['id']; ?></td>
                        <td><?php echo htmlspecialchars($sol['titulo']); ?></td>
                        <?php if (isAdmin()): ?>
                            <td><?php echo htmlspecialchars($sol['usuario_nombre'] ?? ''); ?></td>
                        <?php endif; ?>
                        <td><?php echo formatDate($sol['fecha_solicitud'], 'd/m/Y H:i'); ?></td>
                        <td><?php echo formatDate($sol['fecha_estimada_entrega']); ?></td>
                        <td><?php echo $sol['prioridad']; ?></td>
                        <td><?php echo $sol['estado']; ?></td>
                        <td><?php echo $sol['tipo_uso']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <script>
            window.onload = function() {
                window.print();
            };
        </script>
    </body>
    </html>
    <?php
    exit;
} else {
    redirect('index.php');
}
?>

