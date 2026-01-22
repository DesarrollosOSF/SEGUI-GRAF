-- Base de datos para SEGUI-GRAF - Sistema de Seguimiento Gráfico
-- Crear base de datos
CREATE DATABASE IF NOT EXISTS osfcomco_seguigraf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE osfcomco_seguigraf;

-- Tabla de usuarios
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    perfil ENUM('Administrador', 'Usuario') NOT NULL DEFAULT 'Usuario',
    activo TINYINT(1) DEFAULT 1,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario),
    INDEX idx_perfil (perfil)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de solicitudes
CREATE TABLE solicitudes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    fecha_solicitud DATETIME DEFAULT CURRENT_TIMESTAMP,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NOT NULL,
    fecha_estimada_entrega DATE NOT NULL,
    tipo_uso ENUM('Uso interno', 'Uso externo') NOT NULL,
    fecha_publicacion DATE NULL,
    tipo_requerimiento VARCHAR(100) NULL,
    prioridad ENUM('Alta Prioridad – Urgente', 'Prioridad Media – Programada', 'Prioridad Baja – Regular') NOT NULL DEFAULT 'Prioridad Baja – Regular',
    prioridad_ajustada ENUM('Alta Prioridad – Urgente', 'Prioridad Media – Programada', 'Prioridad Baja – Regular') NULL,
    justificacion_prioridad TEXT NULL,
    estado ENUM('Recibido', 'Pendiente de aprobación', 'Aprobada', 'Cancelada', 'En proceso', 'Completada') NOT NULL DEFAULT 'Recibido',
    administrador_id INT NULL,
    fecha_asignacion DATETIME NULL,
    fecha_inicio_proceso DATETIME NULL,
    fecha_completada DATETIME NULL,
    observaciones TEXT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (administrador_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_estado (estado),
    INDEX idx_prioridad (prioridad),
    INDEX idx_fecha_solicitud (fecha_solicitud)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de archivos adjuntos
CREATE TABLE archivos_adjuntos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta VARCHAR(500) NOT NULL,
    tipo_mime VARCHAR(100) NOT NULL,
    tamaño INT NOT NULL,
    tamaño_comprimido INT NULL,
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (solicitud_id) REFERENCES solicitudes(id) ON DELETE CASCADE,
    INDEX idx_solicitud (solicitud_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de seguimiento de estados
CREATE TABLE historial_estados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT NOT NULL,
    estado_anterior VARCHAR(50) NULL,
    estado_nuevo VARCHAR(50) NOT NULL,
    usuario_id INT NOT NULL,
    observacion TEXT NULL,
    fecha_cambio DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (solicitud_id) REFERENCES solicitudes(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_solicitud (solicitud_id),
    INDEX idx_fecha (fecha_cambio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de métricas y tiempos
CREATE TABLE metricas_solicitudes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT NOT NULL UNIQUE,
    tiempo_respuesta_horas DECIMAL(10,2) NULL,
    tiempo_proceso_horas DECIMAL(10,2) NULL,
    tiempo_total_horas DECIMAL(10,2) NULL,
    cumplimiento_entrega TINYINT(1) NULL,
    dias_anticipacion INT NULL,
    FOREIGN KEY (solicitud_id) REFERENCES solicitudes(id) ON DELETE CASCADE,
    INDEX idx_solicitud (solicitud_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de comentarios
CREATE TABLE comentarios_solicitudes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT NOT NULL,
    usuario_id INT NOT NULL,
    comentario TEXT NOT NULL,
    fecha_comentario DATETIME DEFAULT CURRENT_TIMESTAMP,
    leido TINYINT(1) DEFAULT 0,
    FOREIGN KEY (solicitud_id) REFERENCES solicitudes(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_solicitud (solicitud_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_fecha (fecha_comentario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de notificaciones
CREATE TABLE notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    mensaje TEXT NOT NULL,
    enlace VARCHAR(500) NULL,
    leida TINYINT(1) DEFAULT 0,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_leida (leida),
    INDEX idx_fecha (fecha_creacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar usuario administrador por defecto
-- Contraseña: admin123 (debe cambiarse en producción)
INSERT INTO usuarios (usuario, password, nombre_completo, email, perfil) VALUES
('admin', '$2y$10$gRpe5N.0QFHgXMwW8TFTtuYfvhs3GC/PwLQ1gdNV6lwJlRKHE79IW', 'Administrador del Sistema', 'admin@osfcomco_seguigraf.com', 'Administrador');

-- Insertar usuario de prueba
-- Contraseña: usuario123
INSERT INTO usuarios (usuario, password, nombre_completo, email, perfil) VALUES
('usuario', '$2y$10$B0LzvOqdSXn6vwhF8WgONurbuQAIk5nQA3OLQ8EgBwWnUHkUabbmC', 'Usuario de Prueba', 'usuario@osfcomco_seguigraf.com', 'Usuario');

