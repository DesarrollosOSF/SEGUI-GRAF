<?php
/**
 * Configuración de conexión a la base de datos
 * SEGUI-GRAF - Sistema de Seguimiento Gráfico
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'osfcomco_seguigraf';
    private $username = '';
    private $password = '';
    private $conn;

    public function __construct() {
        $serverHost = $_SERVER['HTTP_HOST'] ?? '';
        $isLocal = in_array($serverHost, ['localhost', '127.0.0.1'], true)
            || stripos($serverHost, 'localhost:') === 0
            || stripos($serverHost, '127.0.0.1:') === 0;

        if ($isLocal) {
            // Entorno local (XAMPP)
            $this->username = 'root';
            $this->password = '';
            return;
        }

        // Entorno productivo
        $this->username = 'osfcomco_Seguigraf_bd';
        $this->password = 'jF9h,=0Ko2sG]j3?V4';
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 30
                )
            );

            // Configurar timeout de transacciones
            $this->conn->exec("SET SESSION innodb_lock_wait_timeout = 30");
            $this->conn->exec("SET SESSION wait_timeout = 30");
        } catch (PDOException $e) {
            error_log("Error de conexión: " . $e->getMessage());
            die("Error de conexión a la base de datos. Por favor, contacte al administrador.");
        }

        return $this->conn;
    }
}
