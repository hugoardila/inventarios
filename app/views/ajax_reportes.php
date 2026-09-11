<?php
session_start();
header('Content-Type: application/json');

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$action = $_POST['action'] ?? '';

try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    switch ($action) {
        case 'obtener_clientes':
            $stmt = $pdo->query("SELECT id, nombre FROM clientes WHERE activo = 1 ORDER BY nombre");
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'clientes' => $clientes]);
            break;
            
        case 'generar_reporte':
            $tipo = $_POST['tipo'] ?? '';
            $fechaDesde = $_POST['fecha_desde'] ?? '';
            $fechaHasta = $_POST['fecha_hasta'] ?? '';
            $cliente = $_POST['cliente'] ?? '';
            $formato = $_POST['formato'] ?? 'pdf';
            
            $resultado = generarReporte($pdo, $tipo, $fechaDesde, $fechaHasta, $cliente, $formato);
            echo json_encode($resultado);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function generarReporte($pdo, $tipo, $fechaDesde, $fechaHasta, $cliente, $formato) {
    $datos = [];
    $nombreReporte = '';
    
    switch ($tipo) {
        case 'inventario-valorizado':
            $sql = "SELECT p.nombre, p.referencia, p.stock, p.costo, p.precio, 
                           (p.stock * p.costo) as valor_costo, (p.stock * p.precio) as valor_venta,
                           c.nombre as categoria, m.nombre as marca
                    FROM productos p 
                    LEFT JOIN categorias c ON p.categoria_id = c.id 
                    LEFT JOIN marcas m ON p.marca_id = m.id 
                    WHERE p.estado = 'activo' 
                    ORDER BY p.nombre";
            $datos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $nombreReporte = 'Inventario_Valorizado';
            break;
            
        case 'stock-bajo':
            $sql = "SELECT p.nombre, p.referencia, p.stock, p.stock_minimo, 
                           (p.stock_minimo - p.stock) as faltante, p.precio
                    FROM productos p 
                    WHERE p.stock <= p.stock_minimo AND p.estado = 'activo' 
                    ORDER BY faltante DESC";
            $datos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $nombreReporte = 'Stock_Bajo';
            break;
            
        case 'movimientos':
            $sql = "SELECT im.*, p.nombre as producto, u.nombre as usuario
                    FROM inventario_movimientos im 
                    LEFT JOIN productos p ON im.producto_id = p.id 
                    LEFT JOIN usuarios u ON im.usuario_id = u.id 
                    ORDER BY im.fecha_movimiento DESC 
                    LIMIT 100";
            $datos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $nombreReporte = 'Movimientos_Inventario';
            break;
            
        case 'kardex':
            $sql = "SELECT p.nombre, p.referencia, 
                           SUM(CASE WHEN im.tipo = 'entrada' THEN im.cantidad ELSE 0 END) as entradas,
                           SUM(CASE WHEN im.tipo = 'salida' THEN im.cantidad ELSE 0 END) as salidas,
                           p.stock as stock_actual
                    FROM productos p 
                    LEFT JOIN inventario_movimientos im ON p.id = im.producto_id 
                    WHERE p.estado = 'activo' 
                    GROUP BY p.id 
                    ORDER BY p.nombre";
            $datos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $nombreReporte = 'Kardex_Productos';
            break;
            
        case 'ventas-periodo':
            $where = "WHERE 1=1";
            if ($fechaDesde) $where .= " AND v.fecha_venta >= '$fechaDesde'";
            if ($fechaHasta) $where .= " AND v.fecha_venta <= '$fechaHasta'";
            if ($cliente) $where .= " AND v.cliente_id = $cliente";
            
            $sql = "SELECT v.numero_factura, v.fecha_venta, v.total, v.estado,
                           c.nombre as cliente, u.nombre as vendedor
                    FROM ventas v 
                    LEFT JOIN clientes c ON v.cliente_id = c.id 
                    LEFT JOIN usuarios u ON v.usuario_id = u.id 
                    $where 
                    ORDER BY v.fecha_venta DESC";
            $datos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $nombreReporte = 'Ventas_Periodo';
            break;
            
        case 'ventas-cliente':
            $sql = "SELECT c.nombre as cliente, COUNT(v.id) as total_facturas,
                           SUM(v.total) as total_ventas, AVG(v.total) as promedio_venta
                    FROM clientes c 
                    LEFT JOIN ventas v ON c.id = v.cliente_id 
                    WHERE c.activo = 1 
                    GROUP BY c.id 
                    ORDER BY total_ventas DESC";
            $datos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $nombreReporte = 'Ventas_Cliente';
            break;
            
        case 'ventas-producto':
            $sql = "SELECT p.nombre, p.referencia, 
                           SUM(vi.cantidad) as total_vendido,
                           SUM(vi.cantidad * vi.precio) as total_ventas,
                           AVG(vi.precio) as precio_promedio
                    FROM productos p 
                    LEFT JOIN venta_items vi ON p.id = vi.producto_id 
                    WHERE p.estado = 'activo' 
                    GROUP BY p.id 
                    ORDER BY total_vendido DESC";
            $datos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $nombreReporte = 'Ventas_Producto';
            break;
            
        case 'impuestos':
            $where = "WHERE 1=1";
            if ($fechaDesde) $where .= " AND v.fecha_venta >= '$fechaDesde'";
            if ($fechaHasta) $where .= " AND v.fecha_venta <= '$fechaHasta'";
            
            $sql = "SELECT v.fecha_venta, v.numero_factura, v.subtotal, v.iva, v.total,
                           c.nombre as cliente, c.tipo_documento, c.numero_documento
                    FROM ventas v 
                    LEFT JOIN clientes c ON v.cliente_id = c.id 
                    $where 
                    ORDER BY v.fecha_venta DESC";
            $datos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $nombreReporte = 'Impuestos_Retenciones';
            break;
            
        default:
            return ['success' => false, 'message' => 'Tipo de reporte no válido'];
    }
    
    // Generar archivo temporal
    $archivo = generarArchivoReporte($datos, $nombreReporte, $formato);
    
    if ($archivo) {
        return [
            'success' => true, 
            'message' => "Reporte '$nombreReporte' generado correctamente",
            'download_url' => $archivo,
            'registros' => count($datos)
        ];
    } else {
        return ['success' => false, 'message' => 'Error al generar el archivo'];
    }
}

function generarArchivoReporte($datos, $nombreReporte, $formato) {
    $timestamp = date('Y-m-d_H-i-s');
    $directorio = __DIR__ . '/../temp/';
    
    // Crear directorio si no existe
    if (!is_dir($directorio)) {
        if (!mkdir($directorio, 0755, true)) {
            return false;
        }
    }
    
    $archivo = $directorio . $nombreReporte . '_' . $timestamp;
    
    switch ($formato) {
        case 'csv':
            $archivo .= '.csv';
            $handle = fopen($archivo, 'w');
            
            if (!empty($datos)) {
                // Encabezados
                fputcsv($handle, array_keys($datos[0]));
                
                // Datos
                foreach ($datos as $fila) {
                    fputcsv($handle, $fila);
                }
            }
            fclose($handle);
            break;
            
        case 'excel':
            $archivo .= '.xlsx';
            // Generar archivo Excel simple
            $handle = fopen($archivo, 'w');
            if (!empty($datos)) {
                // Encabezados
                fputcsv($handle, array_keys($datos[0]));
                
                // Datos
                foreach ($datos as $fila) {
                    fputcsv($handle, $fila);
                }
            }
            fclose($handle);
            break;
            
        case 'pdf':
        default:
            $archivo .= '.pdf';
            // Generar archivo PDF simple
            $contenido = "REPORTE: " . $nombreReporte . "\n";
            $contenido .= "Fecha: " . date('Y-m-d H:i:s') . "\n";
            $contenido .= "Total registros: " . count($datos) . "\n\n";
            
            if (!empty($datos)) {
                $contenido .= "DATOS:\n";
                $contenido .= str_repeat("-", 80) . "\n";
                
                foreach ($datos as $fila) {
                    foreach ($fila as $campo => $valor) {
                        $contenido .= $campo . ": " . $valor . "\n";
                    }
                    $contenido .= str_repeat("-", 40) . "\n";
                }
            }
            
            file_put_contents($archivo, $contenido);
            break;
    }
    
    return 'temp/' . basename($archivo);
}
?>

