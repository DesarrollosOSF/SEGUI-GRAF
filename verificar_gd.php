<?php
/**
 * Script para verificar la extensión GD
 * Puede eliminarse después de verificar
 */

echo "<h1>Verificación de Extensión GD</h1>";

if (extension_loaded('gd')) {
    echo "<p style='color: green;'>✅ La extensión GD está instalada y habilitada</p>";
    
    echo "<h2>Funciones disponibles:</h2>";
    echo "<ul>";
    $funciones = [
        'imagecreatefromjpeg',
        'imagecreatefrompng',
        'imagecreatefromgif',
        'imagecreatefromwebp',
        'imagejpeg',
        'imagepng',
        'imagegif',
        'imagewebp'
    ];
    
    foreach ($funciones as $func) {
        $status = function_exists($func) ? '✅' : '❌';
        echo "<li>$status $func</li>";
    }
    echo "</ul>";
    
    echo "<h2>Información de GD:</h2>";
    $info = gd_info();
    echo "<pre>";
    print_r($info);
    echo "</pre>";
    
} else {
    echo "<p style='color: red;'>❌ La extensión GD NO está instalada o habilitada</p>";
    echo "<h2>Para habilitar GD en XAMPP:</h2>";
    echo "<ol>";
    echo "<li>Abrir el archivo php.ini (ubicado en C:\\xampp\\php\\php.ini)</li>";
    echo "<li>Buscar la línea: <code>;extension=gd</code></li>";
    echo "<li>Eliminar el punto y coma (;) al inicio para descomentarla: <code>extension=gd</code></li>";
    echo "<li>Guardar el archivo</li>";
    echo "<li>Reiniciar Apache en el panel de control de XAMPP</li>";
    echo "</ol>";
    echo "<p><strong>Nota:</strong> El sistema funcionará sin GD, pero las imágenes no se optimizarán automáticamente.</p>";
}
?>

