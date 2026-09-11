<?php
session_start();
header('Content-Type: application/json');

// Verificar si está logueado y es admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$action = $_POST['action'] ?? '';

if ($action === 'obtener_roles') {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '', [
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
        ]);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->query("
            SELECT id, nombre, descripcion, permisos, activo
            FROM roles
            ORDER BY nombre
        ");
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decodificar JSON de permisos
        foreach ($roles as &$rol) {
            if ($rol['permisos']) {
                $rol['permisos'] = json_decode($rol['permisos'], true);
            } else {
                $rol['permisos'] = [];
            }
        }
        
        // Definir módulos disponibles
        $modulos = [
            'usuarios' => 'Usuarios',
            'productos' => 'Productos',
            'ventas' => 'Ventas',
            'compras' => 'Compras',
            'clientes' => 'Clientes',
            'proveedores' => 'Proveedores',
            'reportes' => 'Reportes',
            'configuracion' => 'Configuración',
            'categorias' => 'Categorías',
            'marcas' => 'Marcas',
            'inventario' => 'Inventario'
        ];
        
        $acciones = ['create', 'read', 'update', 'delete'];
        
        echo json_encode([
            'success' => true,
            'roles' => $roles,
            'modulos' => $modulos,
            'acciones' => $acciones
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} elseif ($action === 'guardar_permisos') {
    $rol_id = intval($_POST['rol_id'] ?? 0);
    $permisos = $_POST['permisos'] ?? '{}';
    
    if ($rol_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de rol no válido']);
        exit();
    }
    
    try {
        // Validar JSON
        $permisosArray = json_decode($permisos, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['success' => false, 'message' => 'JSON de permisos inválido']);
            exit();
        }
        
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Verificar que el rol existe
        $stmt = $pdo->prepare("SELECT id, nombre FROM roles WHERE id = ?");
        $stmt->execute([$rol_id]);
        $rol = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$rol) {
            echo json_encode(['success' => false, 'message' => 'Rol no encontrado']);
            exit();
        }
        
        // Actualizar permisos
        $stmt = $pdo->prepare("UPDATE roles SET permisos = ?, actualizado_en = NOW() WHERE id = ?");
        $stmt->execute([$permisos, $rol_id]);
        
        // Registrar en log
        require_once __DIR__ . '/../../config/config.php';
        if (function_exists('logActivity')) {
            logActivity("Permisos actualizados para rol: {$rol['nombre']}", 'INFO', $_SESSION['user_id']);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Permisos guardados correctamente para el rol: ' . $rol['nombre']
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

