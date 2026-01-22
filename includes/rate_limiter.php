<?php
/**
 * Rate Limiting para prevenir ataques de fuerza bruta
 * SEGUI-GRAF - Sistema de Seguimiento Gráfico
 */

class RateLimiter {
    private $conn;
    private $maxAttempts = 5;
    private $timeWindow = 900; // 15 minutos en segundos
    private $lockoutTime = 1800; // 30 minutos de bloqueo

    public function __construct($conn = null) {
        if ($conn === null) {
            $db = new Database();
            $this->conn = $db->getConnection();
        } else {
            $this->conn = $conn;
        }
        
        // Crear tabla si no existe
        $this->createTableIfNotExists();
    }

    /**
     * Crear tabla de intentos de login
     */
    private function createTableIfNotExists() {
        $sql = "
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                username VARCHAR(50) NULL,
                attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                success TINYINT(1) DEFAULT 0,
                INDEX idx_ip_time (ip_address, attempt_time),
                INDEX idx_ip (ip_address)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $this->conn->exec($sql);
    }

    /**
     * Obtener IP del cliente
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Registrar intento de login
     */
    public function recordAttempt($username = null, $success = false) {
        $ip = $this->getClientIP();
        $stmt = $this->conn->prepare("
            INSERT INTO login_attempts (ip_address, username, success)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$ip, $username, $success ? 1 : 0]);
    }

    /**
     * Verificar si la IP está bloqueada
     */
    public function isBlocked() {
        $ip = $this->getClientIP();
        
        // Contar intentos fallidos en la ventana de tiempo
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as attempts
            FROM login_attempts
            WHERE ip_address = ?
            AND success = 0
            AND attempt_time >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$ip, $this->timeWindow]);
        $result = $stmt->fetch();
        
        return $result['attempts'] >= $this->maxAttempts;
    }

    /**
     * Obtener tiempo restante de bloqueo
     */
    public function getRemainingLockoutTime() {
        $ip = $this->getClientIP();
        
        $stmt = $this->conn->prepare("
            SELECT MAX(attempt_time) as last_attempt
            FROM login_attempts
            WHERE ip_address = ?
            AND success = 0
            AND attempt_time >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$ip, $this->lockoutTime]);
        $result = $stmt->fetch();
        
        if ($result && $result['last_attempt']) {
            $lastAttempt = new DateTime($result['last_attempt']);
            $now = new DateTime();
            $diff = $now->getTimestamp() - $lastAttempt->getTimestamp();
            $remaining = $this->lockoutTime - $diff;
            
            return max(0, $remaining);
        }
        
        return 0;
    }

    /**
     * Limpiar intentos antiguos (mantenimiento)
     */
    public function cleanOldAttempts() {
        $stmt = $this->conn->prepare("
            DELETE FROM login_attempts
            WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
    }
}
?>

