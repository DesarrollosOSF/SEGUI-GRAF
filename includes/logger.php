<?php
/**
 * Sistema de Logging
 * SEGUI-GRAF - Sistema de Seguimiento Gráfico
 */

class Logger {
    const DEBUG = 0;
    const INFO = 1;
    const WARNING = 2;
    const ERROR = 3;
    const CRITICAL = 4;

    private static $logLevel = self::INFO;
    private static $logDir = null;

    /**
     * Inicializar logger
     */
    public static function init($logDir = null) {
        if ($logDir === null) {
            $logDir = BASE_PATH . '/logs';
        }
        self::$logDir = $logDir;
        
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * Escribir log
     */
    private static function writeLog($level, $message, $context = []) {
        if (self::$logDir === null) {
            self::init();
        }

        $levelNames = ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'];
        $levelName = $levelNames[$level] ?? 'UNKNOWN';

        if ($level < self::$logLevel) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] [{$levelName}] {$message}{$contextStr}" . PHP_EOL;

        $logFile = self::$logDir . '/app-' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);

        // También registrar errores críticos en error_log de PHP
        if ($level >= self::ERROR) {
            error_log($logMessage);
        }
    }

    /**
     * Log de debug
     */
    public static function debug($message, $context = []) {
        self::writeLog(self::DEBUG, $message, $context);
    }

    /**
     * Log de información
     */
    public static function info($message, $context = []) {
        self::writeLog(self::INFO, $message, $context);
    }

    /**
     * Log de advertencia
     */
    public static function warning($message, $context = []) {
        self::writeLog(self::WARNING, $message, $context);
    }

    /**
     * Log de error
     */
    public static function error($message, $context = []) {
        self::writeLog(self::ERROR, $message, $context);
    }

    /**
     * Log crítico
     */
    public static function critical($message, $context = []) {
        self::writeLog(self::CRITICAL, $message, $context);
    }

    /**
     * Log de auditoría (acciones importantes)
     */
    public static function audit($action, $userId, $details = []) {
        $message = "AUDIT: {$action} by user_id: {$userId}";
        self::writeLog(self::INFO, $message, array_merge(['type' => 'audit'], $details));
    }
}
?>

