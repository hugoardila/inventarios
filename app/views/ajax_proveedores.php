<?php
session_start();
header('Content-Type: application/json');

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Incluir clase de permisos
require_once '../app/Lib/Permissions.php';

require_once 'funciones.php';

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'guardar':
        // Verificar permisos para crear/editar proveedores
        if (!Permissions::canCreate('proveedores') && !Permissions::canEdit('proveedores')) {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para realizar esta acción']);
            exit();
        }
        
        $datos = [
            'nombre' => $_POST['nombre'] ?? '',
            'nit' => $_POST['nit'] ?? '',
            'contacto' => $_POST['contacto'] ?? '',
            'telefono' => $_POST['telefono'] ?? '',
            'email' => $_POST['email'] ?? '',
            'direccion' => $_POST['direccion'] ?? '',
            'ciudad' => $_POST['ciudad'] ?? '',
            'pais' => $_POST['pais'] ?? 'Colombia',
            'activo' => 1
        ];
        
        $resultado = guardarProveedor($datos);
        echo json_encode($resultado);
        break;
        
    case 'eliminar':
        // Verificar permisos para eliminar proveedores
        if (!Permissions::canDelete('proveedores')) {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para eliminar proveedores']);
            exit();
        }
        
        $id = $_POST['id'] ?? 0;
        $resultado = eliminarRegistro('proveedores', $id);
        echo json_encode($resultado);
        break;
        
    case 'obtener':
        $id = $_POST['id'] ?? 0;
        try {
            $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id = ?");
            $stmt->execute([$id]);
            $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($proveedor) {
                echo json_encode(['success' => true, 'proveedor' => $proveedor]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Proveedor no encontrado']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    case 'actualizar':
        $id = $_POST['proveedor_id'] ?? 0;
        $datos = [
            'nombre' => $_POST['nombre'] ?? '',
            'nit' => $_POST['nit'] ?? '',
            'contacto' => $_POST['contacto'] ?? '',
            'telefono' => $_POST['telefono'] ?? '',
            'email' => $_POST['email'] ?? '',
            'direccion' => $_POST['direccion'] ?? '',
            'ciudad' => $_POST['ciudad'] ?? '',
            'pais' => $_POST['pais'] ?? 'Colombia'
        ];
        
        $resultado = actualizarProveedor($id, $datos);
        echo json_encode($resultado);
        break;
        
    case 'exportar':
        $filtros = [
            'ciudad' => $_POST['ciudad'] ?? '',
            'pais' => $_POST['pais'] ?? ''
        ];
        
        $resultado = exportarProveedores($filtros);
        echo json_encode($resultado);
        break;
        
    case 'obtener_productos':
        $id = $_POST['id'] ?? 0;
        $resultado = obtenerProductosProveedor($id);
        echo json_encode($resultado);
        break;
        
    case 'obtener_compras':
        $id = $_POST['id'] ?? 0;
        $resultado = obtenerComprasProveedor($id);
        echo json_encode($resultado);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

function actualizarProveedor($id, $datos) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "UPDATE proveedores SET 
                nombre = ?, nit = ?, contacto = ?, telefono = ?, 
                email = ?, direccion = ?, ciudad = ?, pais = ?, 
                actualizado_en = NOW() 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $datos['nombre'], $datos['nit'], $datos['contacto'], $datos['telefono'],
            $datos['email'], $datos['direccion'], $datos['ciudad'], $datos['pais'], $id
        ]);
        
        return ['success' => true, 'message' => 'Proveedor actualizado correctamente'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function exportarProveedores($filtros) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $where = "WHERE p.activo = 1";
        $params = [];
        
        if (!empty($filtros['ciudad'])) {
            $where .= " AND p.ciudad = ?";
            $params[] = $filtros['ciudad'];
        }
        
        if (!empty($filtros['pais'])) {
            $where .= " AND p.pais = ?";
            $params[] = $filtros['pais'];
        }
        
        $sql = "SELECT p.*, 
                       COUNT(pr.id) as total_productos,
                       SUM(pr.stock * pr.costo) as valor_inventario
                FROM proveedores p 
                LEFT JOIN productos pr ON p.id = pr.proveedor_id 
                $where 
                GROUP BY p.id 
                ORDER BY p.nombre";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generar archivo CSV
        $timestamp = date('Y-m-d_H-i-s');
        $archivo = "../temp/proveedores_$timestamp.csv";
        
        if (!is_dir('../temp')) {
            mkdir('../temp', 0755, true);
        }
        
        $handle = fopen($archivo, 'w');
        fputcsv($handle, ['ID', 'Nombre', 'NIT', 'Contacto', 'Teléfono', 'Email', 'Dirección', 'Ciudad', 'País', 'Total Productos', 'Valor Inventario']);
        
        foreach ($proveedores as $proveedor) {
            fputcsv($handle, [
                $proveedor['id'],
                $proveedor['nombre'],
                $proveedor['nit'],
                $proveedor['contacto'],
                $proveedor['telefono'],
                $proveedor['email'],
                $proveedor['direccion'],
                $proveedor['ciudad'],
                $proveedor['pais'],
                $proveedor['total_productos'],
                $proveedor['valor_inventario'] ?? 0
            ]);
        }
        fclose($handle);
        
        return ['success' => true, 'message' => 'Proveedores exportados correctamente', 'archivo' => basename($archivo)];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function obtenerProductosProveedor($id) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Obtener datos del proveedor
        $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id = ?");
        $stmt->execute([$id]);
        $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$proveedor) {
            return ['success' => false, 'message' => 'Proveedor no encontrado'];
        }
        
        // Obtener productos del proveedor
        $stmt = $pdo->prepare("SELECT p.*, c.nombre as categoria, m.nombre as marca
                               FROM productos p 
                               LEFT JOIN categorias c ON p.categoria_id = c.id 
                               LEFT JOIN marcas m ON p.marca_id = m.id 
                               WHERE p.proveedor_id = ? AND p.estado = 'activo' 
                               ORDER BY p.nombre");
        $stmt->execute([$id]);
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Estadísticas
        $stmt = $pdo->prepare("SELECT 
                                     COUNT(*) as total_productos,
                                     SUM(stock) as total_stock,
                                     SUM(stock * costo) as valor_inventario,
                                     AVG(costo) as costo_promedio
                              FROM productos 
                              WHERE proveedor_id = ? AND estado = 'activo'");
        $stmt->execute([$id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'success' => true, 
            'proveedor' => $proveedor,
            'productos' => $productos,
            'estadisticas' => $stats
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function obtenerComprasProveedor($id) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Obtener datos del proveedor
        $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id = ?");
        $stmt->execute([$id]);
        $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$proveedor) {
            return ['success' => false, 'message' => 'Proveedor no encontrado'];
        }
        
        // Obtener compras del proveedor
        $stmt = $pdo->prepare("SELECT c.*, 
                                     GROUP_CONCAT(CONCAT(p.nombre, ' (', ci.cantidad, ')') SEPARATOR ', ') as productos
                              FROM compras c 
                              LEFT JOIN compra_items ci ON c.id = ci.compra_id 
                              LEFT JOIN productos p ON ci.producto_id = p.id 
                              WHERE c.proveedor_id = ? 
                              GROUP BY c.id 
                              ORDER BY c.fecha_compra DESC");
        $stmt->execute([$id]);
        $compras = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Estadísticas
        $stmt = $pdo->prepare("SELECT 
                                     COUNT(*) as total_compras,
                                     SUM(total) as total_comprado,
                                     AVG(total) as promedio_compra,
                                     MIN(fecha_compra) as primera_compra,
                                     MAX(fecha_compra) as ultima_compra
                              FROM compras 
                              WHERE proveedor_id = ?");
        $stmt->execute([$id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'success' => true, 
            'proveedor' => $proveedor,
            'compras' => $compras,
            'estadisticas' => $stats
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}
?>
