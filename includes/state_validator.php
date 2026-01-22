<?php
/**
 * Validador de transiciones de estado
 * SEGUI-GRAF - Sistema de Seguimiento Gráfico
 */

class StateValidator {
    /**
     * Transiciones válidas de estado
     */
    private static $validTransitions = [
        'Recibido' => ['Pendiente de aprobación', 'Cancelada'],
        'Pendiente de aprobación' => ['Aprobada', 'Cancelada', 'Recibido'],
        'Aprobada' => ['En proceso', 'Cancelada', 'Pendiente de aprobación'],
        'En proceso' => ['Completada', 'Cancelada', 'Aprobada'],
        'Completada' => [], // Estado final - no se puede cambiar
        'Cancelada' => [] // Estado final - no se puede cambiar
    ];

    /**
     * Verificar si una transición de estado es válida
     * 
     * @param string $estado_anterior Estado actual
     * @param string $estado_nuevo Estado al que se quiere cambiar
     * @return bool True si la transición es válida
     */
    public static function isValidTransition($estado_anterior, $estado_nuevo) {
        // Si es el mismo estado, es válido (no hay cambio)
        if ($estado_anterior === $estado_nuevo) {
            return true;
        }

        // Verificar que el estado anterior existe
        if (!isset(self::$validTransitions[$estado_anterior])) {
            return false;
        }

        // Verificar que el estado nuevo está en las transiciones permitidas
        return in_array($estado_nuevo, self::$validTransitions[$estado_anterior]);
    }

    /**
     * Obtener estados válidos desde un estado actual
     * 
     * @param string $estado_actual Estado actual
     * @return array Array de estados válidos
     */
    public static function getValidStates($estado_actual) {
        if (!isset(self::$validTransitions[$estado_actual])) {
            return [];
        }

        // Incluir el estado actual (para mantenerlo)
        $validStates = [$estado_actual];
        
        // Agregar estados de transición válidos
        $validStates = array_merge($validStates, self::$validTransitions[$estado_actual]);
        
        return array_unique($validStates);
    }

    /**
     * Validar transición y lanzar excepción si es inválida
     * 
     * @param string $estado_anterior Estado actual
     * @param string $estado_nuevo Estado al que se quiere cambiar
     * @throws Exception Si la transición no es válida
     */
    public static function validateTransition($estado_anterior, $estado_nuevo) {
        if (!self::isValidTransition($estado_anterior, $estado_nuevo)) {
            throw new Exception(
                "Transición de estado inválida: No se puede cambiar de '{$estado_anterior}' a '{$estado_nuevo}'"
            );
        }
    }

    /**
     * Verificar si un estado es final (no permite más cambios)
     * 
     * @param string $estado Estado a verificar
     * @return bool True si es estado final
     */
    public static function isFinalState($estado) {
        return isset(self::$validTransitions[$estado]) && 
               empty(self::$validTransitions[$estado]);
    }

    /**
     * Obtener mensaje de error descriptivo para transición inválida
     * 
     * @param string $estado_anterior Estado actual
     * @param string $estado_nuevo Estado intentado
     * @return string Mensaje de error
     */
    public static function getErrorMessage($estado_anterior, $estado_nuevo) {
        if (self::isFinalState($estado_anterior)) {
            return "La solicitud está en estado '{$estado_anterior}' y no puede ser modificada.";
        }

        $validStates = self::getValidStates($estado_anterior);
        $validStatesList = implode(', ', array_diff($validStates, [$estado_anterior]));
        
        return "No se puede cambiar de '{$estado_anterior}' a '{$estado_nuevo}'. " .
               "Estados válidos desde '{$estado_anterior}': {$validStatesList}";
    }
}
?>

