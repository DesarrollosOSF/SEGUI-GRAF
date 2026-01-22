<?php
/**
 * Script de instalación rápida
 * SEGUI-GRAF - Sistema de Seguimiento Gráfico
 */

// Verificar si ya está instalado
if (file_exists('config/installed.flag')) {
    die('El sistema ya está instalado. Elimine el archivo config/installed.flag para reinstalar.');
}

$errors = [];
$success = [];

// Verificar extensiones PHP
$required_extensions = ['pdo', 'pdo_mysql', 'gd', 'mbstring'];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $errors[] = "Extensión PHP requerida no encontrada: $ext";
    } else {
        $success[] = "Extensión $ext: OK";
    }
}

// Verificar permisos de directorio uploads
if (!is_dir('uploads')) {
    if (!mkdir('uploads', 0755, true)) {
        $errors[] = "No se pudo crear el directorio uploads/";
    } else {
        $success[] = "Directorio uploads/ creado";
    }
} else {
    if (!is_writable('uploads')) {
        $errors[] = "El directorio uploads/ no tiene permisos de escritura";
    } else {
        $success[] = "Directorio uploads/ tiene permisos correctos";
    }
}

// Procesar formulario de instalación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $host = $_POST['host'] ?? 'localhost';
    $db_name = $_POST['db_name'] ?? 'seguigraf';
    $username = $_POST['username'] ?? 'root';
    $password = $_POST['password'] ?? '';
    
    try {
        // Conectar a MySQL
        $conn = new PDO("mysql:host=$host", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Crear base de datos si no existe
        $conn->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->exec("USE `$db_name`");
        
        // Leer y ejecutar schema
        $schema = file_get_contents('database/schema.sql');
        // Remover la línea CREATE DATABASE y USE si existen
        $schema = preg_replace('/CREATE DATABASE.*?;/i', '', $schema);
        $schema = preg_replace('/USE.*?;/i', '', $schema);
        
        // Ejecutar queries
        $queries = array_filter(array_map('trim', explode(';', $schema)));
        foreach ($queries as $query) {
            if (!empty($query)) {
                $conn->exec($query);
            }
        }
        
        // Actualizar config/database.php
        $config_content = file_get_contents('config/database.php');
        $config_content = str_replace("private \$host = 'localhost';", "private \$host = '$host';", $config_content);
        $config_content = str_replace("private \$db_name = 'seguigraf';", "private \$db_name = '$db_name';", $config_content);
        $config_content = str_replace("private \$username = 'root';", "private \$username = '$username';", $config_content);
        $config_content = str_replace("private \$password = '';", "private \$password = '" . addslashes($password) . "';", $config_content);
        file_put_contents('config/database.php', $config_content);
        
        // Crear archivo de instalación completada
        file_put_contents('config/installed.flag', date('Y-m-d H:i:s'));
        
        $success[] = "Instalación completada exitosamente!";
        $instalado = true;
        
    } catch (PDOException $e) {
        $errors[] = "Error de base de datos: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación - SEGUI-GRAF</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo url('favicon.svg'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/style.css'); ?>">
    <style>
        .install-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
        }
        .check-item {
            padding: 0.5rem;
            margin: 0.25rem 0;
        }
        .check-ok {
            color: var(--success-color);
        }
        .check-error {
            color: var(--danger-color);
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="login-box">
            <div class="login-header">
                <h1>Instalación de SEGUI-GRAF</h1>
                <p>Sistema de Seguimiento Gráfico</p>
            </div>

            <?php if (!empty($success) && !isset($instalado)): ?>
                <div class="alert alert-success">
                    <strong>Verificaciones:</strong><br>
                    <?php foreach ($success as $msg): ?>
                        <div class="check-item check-ok">✓ <?php echo htmlspecialchars($msg); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>Errores encontrados:</strong><br>
                    <?php foreach ($errors as $error): ?>
                        <div class="check-item check-error">✗ <?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($instalado)): ?>
                <div class="alert alert-success">
                    <h3>¡Instalación Completada!</h3>
                    <p>El sistema ha sido instalado correctamente.</p>
                    <p><strong>Credenciales por defecto:</strong></p>
                    <ul>
                        <li><strong>Administrador:</strong> admin / admin123</li>
                        <li><strong>Usuario:</strong> usuario / usuario123</li>
                    </ul>
                    <p style="margin-top: 1rem;">
                        <a href="<?php echo url('login.php'); ?>" class="btn btn-primary">Ir al Login</a>
                    </p>
                    <p style="margin-top: 1rem; font-size: 0.875rem; color: var(--text-muted);">
                        ⚠️ Por seguridad, elimine este archivo (install.php) después de la instalación.
                    </p>
                </div>
            <?php else: ?>
                <form method="POST" class="login-form">
                    <h3 style="margin-bottom: 1rem;">Configuración de Base de Datos</h3>
                    
                    <div class="form-group">
                        <label for="host">Servidor MySQL</label>
                        <input type="text" id="host" name="host" value="localhost" required>
                    </div>

                    <div class="form-group">
                        <label for="db_name">Nombre de Base de Datos</label>
                        <input type="text" id="db_name" name="db_name" value="seguigraf" required>
                    </div>

                    <div class="form-group">
                        <label for="username">Usuario MySQL</label>
                        <input type="text" id="username" name="username" value="root" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña MySQL</label>
                        <input type="password" id="password" name="password">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" <?php echo !empty($errors) ? 'disabled' : ''; ?>>Instalar Sistema</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

