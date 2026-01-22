<?php
/**
 * Clase para manejo de archivos con optimización
 * SEGUI-GRAF - Sistema de Seguimiento Gráfico
 */

class FileHandler {
    private $conn;

    public function __construct($conn = null) {
        // Usar conexión proporcionada o crear una nueva
        if ($conn !== null) {
            $this->conn = $conn;
        } else {
            $db = new Database();
            $this->conn = $db->getConnection();
        }
        
        // Crear directorio de uploads si no existe
        if (!file_exists(UPLOAD_PATH)) {
            mkdir(UPLOAD_PATH, 0755, true);
        }
    }

    /**
     * Subir y optimizar archivo
     * @param int $solicitud_id ID de la solicitud
     * @param array $file Array con información del archivo
     * @param int $max_size Tamaño máximo en bytes (por defecto MAX_FILE_SIZE)
     */
    public function uploadFile($solicitud_id, $file, $max_size = null) {
        // Validaciones
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir el archivo');
        }

        // Usar tamaño máximo personalizado o el por defecto
        $tamaño_maximo = $max_size !== null ? $max_size : MAX_FILE_SIZE;
        
        if (!isset($file['size']) || $file['size'] > $tamaño_maximo) {
            $tamaño_mb = round($tamaño_maximo / (1024 * 1024));
            throw new Exception("El archivo excede el tamaño máximo permitido ({$tamaño_mb}MB)");
        }

        if (!isset($file['type']) || !validarTipoArchivo($file['type'])) {
            throw new Exception('Tipo de archivo no permitido');
        }

        if (!isset($file['name']) || empty($file['name'])) {
            throw new Exception('Nombre de archivo no válido');
        }

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Archivo temporal no válido');
        }

        $nombre_original = $file['name'];
        $extension = getFileExtension($nombre_original);
        
        // Validar extensión real del archivo
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'psd', 'ai', 'eps', 'svg'];
        if (!in_array(strtolower($extension), $allowedExtensions)) {
            throw new Exception('Extensión de archivo no permitida: ' . $extension);
        }
        
        // Validar contenido real del archivo (magic bytes)
        $this->validateFileContent($file['tmp_name'], $extension);
        
        $nombre_archivo = $this->generateFileName($solicitud_id, $extension);
        $ruta_completa = UPLOAD_PATH . $nombre_archivo;

        // Mover archivo temporal
        if (!move_uploaded_file($file['tmp_name'], $ruta_completa)) {
            throw new Exception('Error al guardar el archivo');
        }

        $tamaño_original = $file['size'];
        $tamaño_comprimido = $tamaño_original;

        // Optimizar si es imagen y GD está disponible
        if (in_array($file['type'], ALLOWED_IMAGE_TYPES) && extension_loaded('gd')) {
            $tamaño_comprimido = $this->optimizeImage($ruta_completa, $file['type']);
        }

        // Guardar en base de datos
        $stmt = $this->conn->prepare("
            INSERT INTO archivos_adjuntos 
            (solicitud_id, nombre_original, nombre_archivo, ruta, tipo_mime, tamaño, tamaño_comprimido)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $solicitud_id,
            $nombre_original,
            $nombre_archivo,
            $ruta_completa,
            $file['type'],
            $tamaño_original,
            $tamaño_comprimido
        ]);

        return $this->conn->lastInsertId();
    }

    /**
     * Optimizar imagen
     */
    private function optimizeImage($ruta, $tipo_mime) {
        // Verificar que GD esté disponible
        if (!extension_loaded('gd')) {
            return filesize($ruta);
        }

        try {
            // Cargar imagen según tipo
            $imagen = null;
            switch ($tipo_mime) {
                case 'image/jpeg':
                    if (function_exists('imagecreatefromjpeg')) {
                        $imagen = @imagecreatefromjpeg($ruta);
                    }
                    break;
                case 'image/png':
                    if (function_exists('imagecreatefrompng')) {
                        $imagen = @imagecreatefrompng($ruta);
                    }
                    break;
                case 'image/gif':
                    if (function_exists('imagecreatefromgif')) {
                        $imagen = @imagecreatefromgif($ruta);
                    }
                    break;
                case 'image/webp':
                    if (function_exists('imagecreatefromwebp')) {
                        $imagen = @imagecreatefromwebp($ruta);
                    }
                    break;
                default:
                    return filesize($ruta);
            }

            // Si no se pudo cargar la imagen, retornar tamaño original
            if (!$imagen || !is_resource($imagen)) {
                return filesize($ruta);
            }

            // Obtener dimensiones originales
            $ancho_original = imagesx($imagen);
            $alto_original = imagesy($imagen);

            // Calcular nuevas dimensiones si exceden el máximo
            $ancho = $ancho_original;
            $alto = $alto_original;

            if ($ancho > MAX_IMAGE_WIDTH || $alto > MAX_IMAGE_HEIGHT) {
                $ratio = min(MAX_IMAGE_WIDTH / $ancho, MAX_IMAGE_HEIGHT / $alto);
                $ancho = (int)($ancho * $ratio);
                $alto = (int)($alto * $ratio);
            }

            // Redimensionar si es necesario
            if ($ancho != $ancho_original || $alto != $alto_original) {
                $imagen_nueva = imagecreatetruecolor($ancho, $alto);
                
                // Preservar transparencia para PNG
                if ($tipo_mime === 'image/png') {
                    imagealphablending($imagen_nueva, false);
                    imagesavealpha($imagen_nueva, true);
                    $transparente = imagecolorallocatealpha($imagen_nueva, 255, 255, 255, 127);
                    imagefill($imagen_nueva, 0, 0, $transparente);
                }

                imagecopyresampled($imagen_nueva, $imagen, 0, 0, 0, 0, $ancho, $alto, $ancho_original, $alto_original);
                imagedestroy($imagen);
                $imagen = $imagen_nueva;
            }

            // Guardar imagen optimizada
            $ruta_webp = str_replace(['.jpg', '.jpeg', '.png', '.gif'], '.webp', $ruta);
            
            // Intentar guardar como WebP (mejor compresión)
            if (function_exists('imagewebp')) {
                imagewebp($imagen, $ruta_webp, IMAGE_QUALITY);
                imagedestroy($imagen);
                
                // Si WebP es más pequeño, usar WebP; si no, mantener original optimizado
                if (file_exists($ruta_webp) && filesize($ruta_webp) < filesize($ruta)) {
                    unlink($ruta);
                    rename($ruta_webp, $ruta);
                    return filesize($ruta);
                } else {
                    if (file_exists($ruta_webp)) {
                        unlink($ruta_webp);
                    }
                }
            }

            // Guardar en formato original optimizado
            switch ($tipo_mime) {
                case 'image/jpeg':
                    if (function_exists('imagejpeg')) {
                        imagejpeg($imagen, $ruta, IMAGE_QUALITY);
                    }
                    break;
                case 'image/png':
                    if (function_exists('imagepng')) {
                        imagepng($imagen, $ruta, 9);
                    }
                    break;
                case 'image/gif':
                    if (function_exists('imagegif')) {
                        imagegif($imagen, $ruta);
                    }
                    break;
            }

            imagedestroy($imagen);
            return filesize($ruta);

        } catch (Exception $e) {
            error_log("Error al optimizar imagen: " . $e->getMessage());
            return filesize($ruta);
        }
    }

    /**
     * Validar contenido real del archivo (magic bytes)
     */
    private function validateFileContent($tmpPath, $extension) {
        if (!file_exists($tmpPath)) {
            throw new Exception('Archivo temporal no encontrado');
        }

        $handle = @fopen($tmpPath, 'rb');
        if (!$handle) {
            throw new Exception('No se pudo leer el archivo');
        }

        $firstBytes = @fread($handle, 8);
        @fclose($handle);

        if ($firstBytes === false) {
            return; // Si no se puede leer, continuar (validación básica ya hecha)
        }

        $magicBytes = [
            'jpg' => ["\xFF\xD8\xFF"],
            'jpeg' => ["\xFF\xD8\xFF"],
            'png' => ["\x89\x50\x4E\x47"],
            'gif' => ["\x47\x49\x46\x38"],
            'pdf' => ["%PDF"],
            'zip' => ["\x50\x4B\x03\x04"], // Para docx, que son ZIP
        ];

        $ext = strtolower($extension);
        if (isset($magicBytes[$ext])) {
            $valid = false;
            foreach ($magicBytes[$ext] as $magic) {
                if (substr($firstBytes, 0, strlen($magic)) === $magic) {
                    $valid = true;
                    break;
                }
            }
            if (!$valid) {
                throw new Exception('El contenido del archivo no coincide con su extensión');
            }
        }
    }

    /**
     * Generar nombre único para archivo
     */
    private function generateFileName($solicitud_id, $extension) {
        return 'sol_' . $solicitud_id . '_' . time() . '_' . uniqid() . '.' . $extension;
    }

    /**
     * Eliminar archivo
     */
    public function deleteFile($archivo_id) {
        $stmt = $this->conn->prepare("SELECT ruta FROM archivos_adjuntos WHERE id = ?");
        $stmt->execute([$archivo_id]);
        $archivo = $stmt->fetch();

        if ($archivo && file_exists($archivo['ruta'])) {
            unlink($archivo['ruta']);
        }

        $stmt = $this->conn->prepare("DELETE FROM archivos_adjuntos WHERE id = ?");
        $stmt->execute([$archivo_id]);
    }
}
?>

