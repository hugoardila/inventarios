<?php
session_start();
header('Content-Type: application/json');

// Verificar si está logueado y es admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$action = $_POST['action'] ?? '';

if ($action === 'obtener_auditoria') {
    $filtro_usuario = $_POST['filtro_usuario'] ?? '';
    $filtro_tabla = $_POST['filtro_tabla'] ?? '';
    $filtro_accion = $_POST['filtro_accion'] ?? '';
    $filtro_fecha_desde = $_POST['filtro_fecha_desde'] ?? '';
    $filtro_fecha_hasta = $_POST['filtro_fecha_hasta'] ?? '';
    $limite = intval($_POST['limite'] ?? 100);
    $offset = intval($_POST['offset'] ?? 0);
    
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '', [
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
        ]);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Construir query con filtros
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filtro_usuario)) {
            $where[] = "u.id = ?";
            $params[] = $filtro_usuario;
        }
        
        if (!empty($filtro_tabla)) {
            $where[] = "a.tabla = ?";
            $params[] = $filtro_tabla;
        }
        
        if (!empty($filtro_accion)) {
            $where[] = "a.accion = ?";
            $params[] = $filtro_accion;
        }
        
        if (!empty($filtro_fecha_desde)) {
            $where[] = "DATE(a.creado_en) >= ?";
            $params[] = $filtro_fecha_desde;
        }
        
        if (!empty($filtro_fecha_hasta)) {
            $where[] = "DATE(a.creado_en) <= ?";
            $params[] = $filtro_fecha_hasta;
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Obtener total de registros
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM auditoria a
            LEFT JOIN usuarios u ON a.usuario_id = u.id
            WHERE $whereClause
        ");
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Obtener registros (LIMIT y OFFSET deben ser enteros, no parámetros preparados)
        $limite = intval($limite);
        $offset = intval($offset);
        
        $stmt = $pdo->prepare("
            SELECT 
                a.id,
                a.accion,
                a.tabla,
                a.registro_id,
                a.datos_anteriores,
                a.datos_nuevos,
                a.ip,
                a.user_agent,
                a.creado_en,
                u.id as usuario_id,
                u.username,
                u.nombre,
                u.apellido
            FROM auditoria a
            LEFT JOIN usuarios u ON a.usuario_id = u.id
            WHERE $whereClause
            ORDER BY a.creado_en DESC
            LIMIT $limite OFFSET $offset
        ");
        $stmt->execute($params);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener listas para filtros
        $stmt = $pdo->query("SELECT DISTINCT tabla FROM auditoria ORDER BY tabla");
        $tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $pdo->query("SELECT DISTINCT accion FROM auditoria ORDER BY accion");
        $acciones = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $pdo->query("
            SELECT DISTINCT u.id, u.username, u.nombre, u.apellido
            FROM auditoria a
            LEFT JOIN usuarios u ON a.usuario_id = u.id
            WHERE u.id IS NOT NULL
            ORDER BY u.nombre, u.apellido
        ");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'registros' => $registros,
            'total' => $total,
            'tablas' => $tablas,
            'acciones' => $acciones,
            'usuarios' => $usuarios
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}
?>

