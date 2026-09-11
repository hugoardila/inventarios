<?php
session_start();
header('Content-Type: application/json');

// Verificar si está logueado y es admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$action = $_POST['action'] ?? '';

if ($action === 'obtener_logs') {
    $tipo = $_POST['tipo'] ?? 'activity';
    $logs = [];
    
    try {
        switch ($tipo) {
            case 'activity':
                $logs = obtenerLogsActividad();
                break;
            case 'security':
                $logs = obtenerLogsSeguridad();
                break;
            case 'errors':
                $logs = obtenerLogsErrores();
                break;
            case 'auditoria':
                $logs = obtenerLogsAuditoria();
                break;
            case 'fe':
                $logs = obtenerLogsFE();
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Tipo de log no válido']);
                exit();
        }
        
        echo json_encode([
            'success' => true,
            'logs' => $logs,
            'total' => count($logs)
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} elseif ($action === 'limpiar_logs') {
    try {
        $resultado = limpiarLogsAntiguos();
        echo json_encode($resultado);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

function obtenerLogsActividad() {
    $logFile = __DIR__ . '/../logs/activity.log';
    $logs = [];
    
    if (!file_exists($logFile)) {
        return $logs;
    }
    
    $lineas = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lineas = array_slice($lineas, -100); // Últimas 100 líneas
    
    foreach (array_reverse($lineas) as $linea) {
        // Formato: [timestamp] [level] [User:userId] [IP:ip] mensaje
        if (preg_match('/\[([^\]]+)\] \[([^\]]+)\] \[User:([^\]]+)\] \[IP:([^\]]+)\](.*)/', $linea, $matches)) {
            $logs[] = [
                'fecha' => $matches[1] ?? '',
                'nivel' => $matches[2] ?? '',
                'usuario' => $matches[3] ?? '',
                'ip' => $matches[4] ?? '',
                'mensaje' => trim($matches[5] ?? '')
            ];
        }
    }
    
    return $logs;
}

function obtenerLogsSeguridad() {
    $logFile = __DIR__ . '/../logs/security.log';
    $logs = [];
    
    if (!file_exists($logFile)) {
        return $logs;
    }
    
    $lineas = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lineas = array_slice($lineas, -100); // Últimas 100 líneas
    
    foreach (array_reverse($lineas) as $linea) {
        $data = json_decode($linea, true);
        if ($data) {
            $logs[] = [
                'timestamp' => $data['timestamp'] ?? '',
                'ip' => $data['ip'] ?? '',
                'input' => $data['input'] ?? '',
                'reason' => $data['reason'] ?? ''
            ];
        }
    }
    
    return $logs;
}

function obtenerLogsErrores() {
    $logFile = __DIR__ . '/../logs/php_errors.log';
    $logs = [];
    
    if (!file_exists($logFile)) {
        return $logs;
    }
    
    $lineas = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lineas = array_slice($lineas, -100); // Últimas 100 líneas
    
    foreach (array_reverse($lineas) as $linea) {
        // Formato típico de error PHP: [date] tipo: mensaje in archivo:linea
        if (preg_match('/\[([^\]]+)\]\s+([^:]+):\s+(.+?)(?:\s+in\s+([^:]+):(\d+))?/i', $linea, $matches)) {
            $logs[] = [
                'fecha' => $matches[1] ?? '',
                'tipo' => trim($matches[2] ?? ''),
                'mensaje' => trim($matches[3] ?? ''),
                'archivo' => ($matches[4] ?? '') . (isset($matches[5]) ? ':' . $matches[5] : '')
            ];
        } else {
            // Si no coincide el patrón, agregar la línea completa
            $logs[] = [
                'fecha' => date('Y-m-d H:i:s'),
                'tipo' => 'Error',
                'mensaje' => $linea,
                'archivo' => 'N/A'
            ];
        }
    }
    
    return $logs;
}

function obtenerLogsAuditoria() {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '', [
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
        ]);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->query("
            SELECT a.*, u.username as usuario 
            FROM auditoria a 
            LEFT JOIN usuarios u ON a.usuario_id = u.id 
            ORDER BY a.creado_en DESC 
            LIMIT 100
        ");
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = null;
        
        return $resultados;
    } catch (PDOException $e) {
        return [];
    }
}

function obtenerLogsFE() {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '', [
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
        ]);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->query("
            SELECT * 
            FROM fe_log 
            ORDER BY creado_en DESC 
            LIMIT 100
        ");
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = null;
        
        return $resultados;
    } catch (PDOException $e) {
        return [];
    }
}

function limpiarLogsAntiguos() {
    $archivosEliminados = 0;
    $errores = [];
    
    // Limpiar logs de archivos (más de 30 días)
    $directorioLogs = __DIR__ . '/../logs/';
    $archivos = glob($directorioLogs . '*.log');
    $hace30Dias = time() - (30 * 24 * 60 * 60);
    
    foreach ($archivos as $archivo) {
        if (is_file($archivo) && filemtime($archivo) < $hace30Dias) {
            if (unlink($archivo)) {
                $archivosEliminados++;
            } else {
                $errores[] = "No se pudo eliminar: " . basename($archivo);
            }
        }
    }
    
    // Limpiar logs de BD (más de 90 días)
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("DELETE FROM auditoria WHERE creado_en < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $stmt->execute();
        $registrosBD = $stmt->rowCount();
        $stmt = null;
        
        $stmt = $pdo->prepare("DELETE FROM fe_log WHERE creado_en < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $stmt->execute();
        $registrosFE = $stmt->rowCount();
        $stmt = null;
        
        $mensaje = "Logs limpiados correctamente. ";
        $mensaje .= "Se eliminaron $archivosEliminados archivo(s) y $registrosBD registro(s) de auditoría y $registrosFE registro(s) de FE.";
        
        return [
            'success' => true,
            'message' => $mensaje,
            'archivos_eliminados' => $archivosEliminados,
            'registros_auditoria' => $registrosBD,
            'registros_fe' => $registrosFE,
            'errores' => $errores
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Error al limpiar logs de BD: ' . $e->getMessage()
        ];
    }
}
?>

