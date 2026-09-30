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

        if (!isset($file['name']) || empty($file['name'])) {
            throw new Exception('Nombre de archivo no válido');
        }

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Archivo temporal no válido');
        }

        $nombre_original = $file['name'];
        $extensionRaw = strtolower(getFileExtension($nombre_original));
        // Variantes de nombre que Windows/Office usan a veces; se normalizan para validar y guardar
        $extensionAliases = [
            'powerpoint' => 'pptx',
            'ppsx' => 'pptx',
            'pptm' => 'pptx',
            'pps' => 'ppt',
        ];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'pptm', 'ppsx', 'pps', 'powerpoint', 'xls', 'xlsx', 'psd', 'ai', 'eps', 'svg', 'zip', 'rar', 'mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac', 'opus', 'webm'];

        if (!in_array($extensionRaw, $allowedExtensions, true)) {
            throw new Exception('Extensión de archivo no permitida: ' . $extensionRaw);
        }
        $extension = $extensionAliases[$extensionRaw] ?? $extensionRaw;

        // Para zip/rar, Office (Word/PowerPoint/Excel) y audio: validar por extensión + magic bytes.
        // En producción el MIME de Office y de algunos audios suele variar según el servidor.
        $esArchivoComprimido = in_array($extension, ['zip', 'rar']);
        $esOfficeConMagicBytes = in_array($extension, ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx']);
        $esAudio = in_array($extension, ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac', 'opus', 'webm'], true);
        if ($esArchivoComprimido || $esOfficeConMagicBytes || $esAudio) {
            $this->validateFileContent($file['tmp_name'], $extension);
        } else {
            // Para el resto: exigir MIME permitido
            if (!isset($file['type']) || !validarTipoArchivo($file['type'])) {
                throw new Exception('Tipo de archivo no permitido');
            }
        }

        $tipoMime = isset($file['type']) ? $file['type'] : 'application/octet-stream';
        if ($extension === 'zip') {
            $tipoMime = 'application/zip';
        } elseif ($extension === 'rar') {
            $tipoMime = 'application/x-rar-compressed';
        } elseif ($extension === 'ppt') {
            $tipoMime = 'application/vnd.ms-powerpoint';
        } elseif ($extension === 'pptx') {
            $tipoMime = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
        } elseif ($extension === 'xls') {
            $tipoMime = 'application/vnd.ms-excel';
        } elseif ($extension === 'xlsx') {
            $tipoMime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } elseif ($extension === 'mp3') {
            $tipoMime = 'audio/mpeg';
        } elseif ($extension === 'wav') {
            $tipoMime = 'audio/wav';
        } elseif ($extension === 'ogg' || $extension === 'opus') {
            $tipoMime = 'audio/ogg';
        } elseif ($extension === 'm4a') {
            $tipoMime = 'audio/mp4';
        } elseif ($extension === 'aac') {
            $tipoMime = 'audio/aac';
        } elseif ($extension === 'flac') {
            $tipoMime = 'audio/flac';
        } elseif ($extension === 'webm') {
            $tipoMime = 'audio/webm';
        }
        
        $nombre_archivo = $this->generateFileName($solicitud_id, $extension);
        $ruta_completa = UPLOAD_PATH . $nombre_archivo;

        // Mover archivo temporal
        if (!move_uploaded_file($file['tmp_name'], $ruta_completa)) {
            throw new Exception('Error al guardar el archivo');
        }

        $tamaño_original = $file['size'];
        $tamaño_comprimido = $tamaño_original;

        // Optimizar si es imagen y GD está disponible
        if (in_array($tipoMime, ALLOWED_IMAGE_TYPES) && extension_loaded('gd')) {
            $tamaño_comprimido = $this->optimizeImage($ruta_completa, $tipoMime);
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
            $tipoMime,
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

        $firstBytes = @fread($handle, 12);
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
            'zip' => ["\x50\x4B\x03\x04", "\x50\x4B\x05\x06", "\x50\x4B\x07\x08"],
            'rar' => ["Rar!\x1A\x07"],
            'doc' => ["\xD0\xCF\x11\xE0"],
            'docx' => ["\x50\x4B\x03\x04", "\x50\x4B\x05\x06", "\x50\x4B\x07\x08"],
            'ppt' => ["\xD0\xCF\x11\xE0"],
            'pptx' => ["\x50\x4B\x03\x04", "\x50\x4B\x05\x06", "\x50\x4B\x07\x08"],
            'xls' => ["\xD0\xCF\x11\xE0"],
            'xlsx' => ["\x50\x4B\x03\x04", "\x50\x4B\x05\x06", "\x50\x4B\x07\x08"],

            'mp3' => ["ID3", "\xFF\xFB", "\xFF\xF3", "\xFF\xF2", "\xFF\xFA", "\xFF\xF1"],
            'wav' => ["RIFF"],
            'ogg' => ["OggS"],
            'opus' => ["OggS"],
            'flac' => ["fLaC"],
            'webm' => ["\x1A\x45\xDF\xA3"],
        ];

        $ext = strtolower($extension);
        if ($ext === 'm4a' || $ext === 'aac') {
            // Contenedor MP4/M4A: "ftyp" en offset 4; AAC en bruto (ADTS): 0xFFF1 / 0xFFF9
            $esM4a = strlen($firstBytes) >= 8 && substr($firstBytes, 4, 4) === 'ftyp';
            $esAac = strlen($firstBytes) >= 2 && (
                substr($firstBytes, 0, 2) === "\xFF\xF1" ||
                substr($firstBytes, 0, 2) === "\xFF\xF9" ||
                substr($firstBytes, 0, 2) === "\xFF\xF8" ||
                substr($firstBytes, 0, 2) === "\xFF\xFA"
            );
            if (!$esM4a && !$esAac) {
                throw new Exception('El contenido del archivo no coincide con su extensión');
            }
            return;
        }

        if (isset($magicBytes[$ext])) {
            $valid = false;
            foreach ($magicBytes[$ext] as $magic) {
                if (substr($firstBytes, 0, strlen($magic)) === $magic) {
                    $valid = true;
                    break;
                }
            }
            // WAV debe contener también "WAVE" en offset 8
            if ($valid && $ext === 'wav' && (strlen($firstBytes) < 12 || substr($firstBytes, 8, 4) !== 'WAVE')) {
                $valid = false;
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
