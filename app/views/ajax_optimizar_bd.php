<?php
session_start();
header('Content-Type: application/json');

// Verificar si está logueado y es admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$action = $_POST['action'] ?? '';

if ($action === 'optimizar_bd') {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '', [
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
        ]);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Obtener todas las tablas usando fetchAll para evitar problemas de buffer
        $stmt = $pdo->query("SHOW TABLES");
        $resultadosTablas = $stmt->fetchAll(PDO::FETCH_NUM);
        $stmt = null; // Liberar el statement
        $tablas = array_column($resultadosTablas, 0);
        
        $resultados = [];
        $tablasOptimizadas = 0;
        $tablasConError = 0;
        $errores = [];
        
        foreach ($tablas as $tabla) {
            try {
                // Obtener información de la tabla antes de optimizar usando fetchAll
                $stmt = $pdo->prepare("SHOW TABLE STATUS WHERE Name = ?");
                $stmt->execute([$tabla]);
                $infoAntesArray = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt = null; // Liberar el statement
                
                $infoAntes = $infoAntesArray[0] ?? [];
                $tamañoAntes = ($infoAntes['Data_length'] ?? 0) + ($infoAntes['Index_length'] ?? 0);
                
                // Optimizar la tabla
                $pdo->exec("OPTIMIZE TABLE `$tabla`");
                
                // Obtener información después de optimizar usando fetchAll
                $stmt = $pdo->prepare("SHOW TABLE STATUS WHERE Name = ?");
                $stmt->execute([$tabla]);
                $infoDespuesArray = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt = null; // Liberar el statement
                
                $infoDespues = $infoDespuesArray[0] ?? [];
                $tamañoDespues = ($infoDespues['Data_length'] ?? 0) + ($infoDespues['Index_length'] ?? 0);
                
                $espacioLiberado = $tamañoAntes - $tamañoDespues;
                
                $resultados[$tabla] = [
                    'tamaño_antes' => $tamañoAntes,
                    'tamaño_despues' => $tamañoDespues,
                    'espacio_liberado' => $espacioLiberado > 0 ? $espacioLiberado : 0,
                    'filas' => $infoDespues['Rows'] ?? 0
                ];
                
                $tablasOptimizadas++;
                
            } catch (PDOException $e) {
                $tablasConError++;
                $errores[] = "Error en tabla $tabla: " . $e->getMessage();
                // Asegurarse de liberar cualquier statement pendiente
                if (isset($stmt)) {
                    $stmt = null;
                }
            }
        }
        
        // Calcular totales
        $totalEspacioLiberado = 0;
        foreach ($resultados as $resultado) {
            $totalEspacioLiberado += $resultado['espacio_liberado'];
        }
        
        $mensaje = "Base de datos optimizada correctamente. ";
        $mensaje .= "Se optimizaron $tablasOptimizadas tabla(s)";
        if ($totalEspacioLiberado > 0) {
            $mensaje .= " liberando " . formatBytes($totalEspacioLiberado);
        }
        if ($tablasConError > 0) {
            $mensaje .= ". $tablasConError tabla(s) tuvieron errores.";
        }
        
        echo json_encode([
            'success' => true,
            'message' => $mensaje,
            'detalles' => $resultados,
            'tablas_optimizadas' => $tablasOptimizadas,
            'tablas_con_error' => $tablasConError,
            'total_espacio_liberado' => $totalEspacioLiberado,
            'espacio_formateado' => formatBytes($totalEspacioLiberado),
            'errores' => $errores
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error de conexión a la base de datos: ' . $e->getMessage()
        ]);
    }
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

