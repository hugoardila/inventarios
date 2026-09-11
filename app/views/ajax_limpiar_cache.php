<?php
session_start();
header('Content-Type: application/json');

// Verificar si está logueado y es admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$action = $_POST['action'] ?? '';

if ($action === 'limpiar_cache') {
    $resultados = [];
    $totalEliminados = 0;
    $totalEspacio = 0;
    $errores = [];
    
    // Directorios a limpiar
    $directorios = [
        'temp' => __DIR__ . '/../temp/',
        'uploads_temp' => __DIR__ . '/../public/uploads/temp/',
        'reports_temp' => __DIR__ . '/../public/reports/temp/',
        'logs_old' => __DIR__ . '/../logs/'
    ];
    
    foreach ($directorios as $nombre => $directorio) {
        if (!is_dir($directorio)) {
            // Intentar crear el directorio si no existe
            @mkdir($directorio, 0755, true);
            continue;
        }
        
        $archivosEliminados = 0;
        $espacioLiberado = 0;
        
        try {
            $archivos = glob($directorio . '*');
            
            foreach ($archivos as $archivo) {
                if (is_file($archivo)) {
                    $tamaño = filesize($archivo);
                    if (unlink($archivo)) {
                        $archivosEliminados++;
                        $espacioLiberado += $tamaño;
                    } else {
                        $errores[] = "No se pudo eliminar: " . basename($archivo);
                    }
                } elseif (is_dir($archivo)) {
                    // Eliminar directorios vacíos o con archivos antiguos
                    $archivosSub = glob($archivo . '/*');
                    $todosEliminados = true;
                    foreach ($archivosSub as $subArchivo) {
                        if (is_file($subArchivo)) {
                            $tamaño = filesize($subArchivo);
                            if (unlink($subArchivo)) {
                                $archivosEliminados++;
                                $espacioLiberado += $tamaño;
                            } else {
                                $todosEliminados = false;
                            }
                        }
                    }
                    if ($todosEliminados && is_dir($archivo)) {
                        @rmdir($archivo);
                    }
                }
            }
            
            $resultados[$nombre] = [
                'archivos' => $archivosEliminados,
                'espacio' => $espacioLiberado
            ];
            
            $totalEliminados += $archivosEliminados;
            $totalEspacio += $espacioLiberado;
            
        } catch (Exception $e) {
            $errores[] = "Error en $nombre: " . $e->getMessage();
        }
    }
    
    // Limpiar cache de OPcache si está habilitado
    $opcacheLimpio = false;
    if (function_exists('opcache_reset')) {
        if (opcache_reset()) {
            $opcacheLimpio = true;
        }
    }
    
    // Formatear espacio liberado
    $espacioFormateado = formatBytes($totalEspacio);
    
    $mensaje = "Cache limpiado correctamente. ";
    $mensaje .= "Se eliminaron $totalEliminados archivo(s) liberando $espacioFormateado.";
    if ($opcacheLimpio) {
        $mensaje .= " OPcache también fue limpiado.";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'detalles' => $resultados,
        'total_archivos' => $totalEliminados,
        'total_espacio' => $totalEspacio,
        'espacio_formateado' => $espacioFormateado,
        'opcache_limpiado' => $opcacheLimpio,
        'errores' => $errores
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>

