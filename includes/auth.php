<?php
/**
 * Funciones de autenticación
 * SEGUI-GRAF - Sistema de Seguimiento Gráfico
 */

/**
 * Verificar credenciales de usuario
 */
function verificarCredenciales($usuario, $password) {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT id, usuario, password, nombre_completo, email, perfil FROM usuarios WHERE usuario = ? AND activo = 1");
    $stmt->execute([$usuario]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }

    return false;
}

/**
 * Iniciar sesión
 */
function iniciarSesion($usuario, $password) {
    $user = verificarCredenciales($usuario, $password);
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['usuario'] = $user['usuario'];
        $_SESSION['nombre_completo'] = $user['nombre_completo'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['perfil'] = $user['perfil'];
        return true;
    }
    
    return false;
}

/**
 * Cerrar sesión
 */
function cerrarSesion() {
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}

/**
 * Requerir autenticación
 */
function requireAuth() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

/**
 * Requerir rol de administrador
 */
function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        redirect('index.php');
    }
}
?>

