<?php
/**
 * Protección CSRF (Cross-Site Request Forgery)
 * SEGUI-GRAF - Sistema de Seguimiento Gráfico
 */

/**
 * Generar token CSRF
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Obtener token CSRF actual
 */
function getCSRFToken() {
    return generateCSRFToken();
}

/**
 * Validar token CSRF
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verificar token CSRF en POST
 */
function verifyCSRFToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            http_response_code(403);
            die('Error de seguridad: Token CSRF inválido. Por favor, recargue la página e intente nuevamente.');
        }
    }
}

/**
 * Generar campo hidden para formulario
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCSRFToken()) . '">';
}
?>

