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
        // Verificar permisos para crear/editar clientes
        if (!Permissions::canCreate('clientes') && !Permissions::canEdit('clientes')) {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para realizar esta acción']);
            exit();
        }
        
        $datos = [
            'tipo_persona' => $_POST['tipo_persona'] ?? '',
            'tipo_documento' => $_POST['tipo_documento'] ?? '',
            'numero_documento' => $_POST['numero_documento'] ?? '',
            'dv' => $_POST['dv'] ?? '',
            'nombre' => $_POST['nombre'] ?? '',
            'regimen' => $_POST['regimen'] ?? '',
            'telefono' => $_POST['telefono'] ?? '',
            'email' => $_POST['email'] ?? '',
            'direccion' => $_POST['direccion'] ?? '',
            'activo' => 1
        ];
        
        $resultado = guardarCliente($datos);
        echo json_encode($resultado);
        break;
        
    case 'eliminar':
        // Verificar permisos para eliminar clientes
        if (!Permissions::canDelete('clientes')) {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para eliminar clientes']);
            exit();
        }
        
        $id = $_POST['id'] ?? 0;
        $resultado = eliminarRegistro('clientes', $id);
        echo json_encode($resultado);
        break;
        
    case 'obtener':
        $id = $_POST['id'] ?? 0;
        try {
            $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
            $stmt->execute([$id]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($cliente) {
                echo json_encode(['success' => true, 'cliente' => $cliente]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    case 'actualizar':
        $id = $_POST['cliente_id'] ?? 0;
        $datos = [
            'tipo_persona' => $_POST['tipo_persona'] ?? '',
            'tipo_documento' => $_POST['tipo_documento'] ?? '',
            'numero_documento' => $_POST['numero_documento'] ?? '',
            'dv' => $_POST['dv'] ?? '',
            'nombre' => $_POST['nombre'] ?? '',
            'regimen' => $_POST['regimen'] ?? '',
            'telefono' => $_POST['telefono'] ?? '',
            'email' => $_POST['email'] ?? '',
            'direccion' => $_POST['direccion'] ?? ''
        ];
        
        $resultado = actualizarCliente($id, $datos);
        echo json_encode($resultado);
        break;
        
    case 'exportar':
        $filtros = [
            'tipo_persona' => $_POST['tipo_persona'] ?? '',
            'regimen' => $_POST['regimen'] ?? '',
            'tipo_cliente' => $_POST['tipo_cliente'] ?? '',
            'ciudad' => $_POST['ciudad'] ?? ''
        ];
        
        $resultado = exportarClientes($filtros);
        echo json_encode($resultado);
        break;
        
    case 'obtener_historial':
        $id = $_POST['id'] ?? 0;
        $resultado = obtenerHistorialCliente($id);
        echo json_encode($resultado);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

function actualizarCliente($id, $datos) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "UPDATE clientes SET 
                tipo_persona = ?, tipo_documento = ?, numero_documento = ?, dv = ?, 
                nombre = ?, regimen = ?, telefono = ?, email = ?, direccion = ?, 
                actualizado_en = NOW() 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $datos['tipo_persona'], $datos['tipo_documento'], $datos['numero_documento'], 
            $datos['dv'], $datos['nombre'], $datos['regimen'], $datos['telefono'], 
            $datos['email'], $datos['direccion'], $id
        ]);
        
        return ['success' => true, 'message' => 'Cliente actualizado correctamente'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function exportarClientes($filtros) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $where = "WHERE c.activo = 1";
        $params = [];
        
        if (!empty($filtros['tipo_persona'])) {
            $where .= " AND c.tipo_persona = ?";
            $params[] = $filtros['tipo_persona'];
        }
        
        if (!empty($filtros['regimen'])) {
            $where .= " AND c.regimen = ?";
            $params[] = $filtros['regimen'];
        }
        
        if (!empty($filtros['tipo_cliente'])) {
            if ($filtros['tipo_cliente'] === 'premium') {
                $where .= " AND (SELECT SUM(total) FROM ventas WHERE cliente_id = c.id) > 1000000";
            } else {
                $where .= " AND (SELECT SUM(total) FROM ventas WHERE cliente_id = c.id) <= 1000000";
            }
        }
        
        $sql = "SELECT c.*, 
                       COUNT(v.id) as total_facturas,
                       SUM(v.total) as total_ventas,
                       MAX(v.fecha_venta) as ultima_compra
                FROM clientes c 
                LEFT JOIN ventas v ON c.id = v.cliente_id 
                $where 
                GROUP BY c.id 
                ORDER BY c.nombre";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generar archivo CSV
        $timestamp = date('Y-m-d_H-i-s');
        $archivo = "../temp/clientes_$timestamp.csv";
        
        if (!is_dir('../temp')) {
            mkdir('../temp', 0755, true);
        }
        
        $handle = fopen($archivo, 'w');
        fputcsv($handle, ['ID', 'Nombre', 'Tipo Persona', 'Documento', 'DV', 'Régimen', 'Teléfono', 'Email', 'Dirección', 'Total Facturas', 'Total Ventas', 'Última Compra']);
        
        foreach ($clientes as $cliente) {
            fputcsv($handle, [
                $cliente['id'],
                $cliente['nombre'],
                $cliente['tipo_persona'],
                $cliente['tipo_documento'] . ': ' . $cliente['numero_documento'],
                $cliente['dv'],
                $cliente['regimen'],
                $cliente['telefono'],
                $cliente['email'],
                $cliente['direccion'],
                $cliente['total_facturas'],
                $cliente['total_ventas'] ?? 0,
                $cliente['ultima_compra'] ? date('d/m/Y', strtotime($cliente['ultima_compra'])) : 'Sin compras'
            ]);
        }
        fclose($handle);
        
        return ['success' => true, 'message' => 'Clientes exportados correctamente', 'archivo' => basename($archivo)];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function obtenerHistorialCliente($id) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Obtener datos del cliente
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt->execute([$id]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cliente) {
            return ['success' => false, 'message' => 'Cliente no encontrado'];
        }
        
        // Obtener historial de ventas
        $stmt = $pdo->prepare("SELECT v.*, 
                                     GROUP_CONCAT(CONCAT(p.nombre, ' (', vi.cantidad, ')') SEPARATOR ', ') as productos
                              FROM ventas v 
                              LEFT JOIN venta_items vi ON v.id = vi.venta_id 
                              LEFT JOIN productos p ON vi.producto_id = p.id 
                              WHERE v.cliente_id = ? 
                              GROUP BY v.id 
                              ORDER BY v.fecha_venta DESC");
        $stmt->execute([$id]);
        $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Estadísticas
        $stmt = $pdo->prepare("SELECT 
                                     COUNT(*) as total_facturas,
                                     SUM(total) as total_ventas,
                                     AVG(total) as promedio_venta,
                                     MIN(fecha_venta) as primera_compra,
                                     MAX(fecha_venta) as ultima_compra
                              FROM ventas 
                              WHERE cliente_id = ?");
        $stmt->execute([$id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'success' => true, 
            'cliente' => $cliente,
            'ventas' => $ventas,
            'estadisticas' => $stats
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}
?>
