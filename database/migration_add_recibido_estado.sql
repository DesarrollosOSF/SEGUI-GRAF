-- Migración: Agregar estado "Recibido" a la tabla solicitudes
-- Ejecutar este script si ya tienes una base de datos existente

-- Paso 1: Modificar el ENUM para incluir "Recibido"
ALTER TABLE solicitudes 
MODIFY COLUMN estado ENUM('Recibido', 'Pendiente de aprobación', 'Aprobada', 'Cancelada', 'En proceso', 'Completada') 
NOT NULL DEFAULT 'Recibido';

-- Paso 2: Actualizar las solicitudes existentes que tienen "Pendiente de aprobación" a "Recibido"
-- (Opcional: Solo si quieres cambiar el estado de las solicitudes existentes)
-- UPDATE solicitudes SET estado = 'Recibido' WHERE estado = 'Pendiente de aprobación';

