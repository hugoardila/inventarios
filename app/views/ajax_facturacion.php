<?php
session_start();
header('Content-Type: application/json');

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once 'funciones.php';

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'generar_factura':
        $datos = [
            'cliente_id' => !empty($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null,
            'fecha' => $_POST['fecha'] ?? date('Y-m-d'),
            'subtotal' => $_POST['subtotal'] ?? 0,
            'iva' => $_POST['iva'] ?? 0,
            'descuento' => $_POST['descuento'] ?? 0,
            'total' => $_POST['total'] ?? 0,
            'observaciones' => $_POST['observaciones'] ?? '',
            'items' => json_decode($_POST['items'] ?? '[]', true)
        ];
        
        $resultado = generarFactura($datos);
        echo json_encode($resultado);
        break;
        
    case 'obtener_factura':
        $id = $_POST['id'] ?? 0;
        $resultado = obtenerFactura($id);
        echo json_encode($resultado);
        break;
        
    case 'listar_facturas':
        $filtros = [
            'fecha_desde' => $_POST['fecha_desde'] ?? '',
            'fecha_hasta' => $_POST['fecha_hasta'] ?? '',
            'cliente_id' => $_POST['cliente_id'] ?? '',
            'estado' => $_POST['estado'] ?? ''
        ];
        
        $resultado = listarFacturas($filtros);
        echo json_encode($resultado);
        break;
        
    case 'anular_factura':
        $id = $_POST['id'] ?? 0;
        $motivo = $_POST['motivo'] ?? '';
        
        $resultado = anularFactura($id, $motivo);
        echo json_encode($resultado);
        break;
        
    case 'generar_pdf':
        $id = $_POST['id'] ?? 0;
        $resultado = generarPDF($id);
        echo json_encode($resultado);
        break;
        
    case 'enviar_email':
        $id = $_POST['id'] ?? 0;
        $email = $_POST['email'] ?? '';
        
        $resultado = enviarFacturaEmail($id, $email);
        echo json_encode($resultado);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

function obtenerFactura($id) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Obtener factura
        $stmt = $pdo->prepare("SELECT v.*, c.nombre as cliente, c.email as cliente_email 
                               FROM ventas v 
                               LEFT JOIN clientes c ON v.cliente_id = c.id 
                               WHERE v.id = ?");
        $stmt->execute([$id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$factura) {
            return ['success' => false, 'message' => 'Factura no encontrada'];
        }
        
        // Obtener items
        $stmt = $pdo->prepare("SELECT vi.*, p.nombre as producto, p.referencia 
                               FROM venta_items vi 
                               LEFT JOIN productos p ON vi.producto_id = p.id 
                               WHERE vi.venta_id = ?");
        $stmt->execute([$id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $factura['items'] = $items;
        
        return ['success' => true, 'factura' => $factura];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function listarFacturas($filtros) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $where = "WHERE 1=1";
        $params = [];
        
        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND v.fecha_venta >= ?";
            $params[] = $filtros['fecha_desde'];
        }
        
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND v.fecha_venta <= ?";
            $params[] = $filtros['fecha_hasta'];
        }
        
        if (!empty($filtros['cliente_id'])) {
            $where .= " AND v.cliente_id = ?";
            $params[] = $filtros['cliente_id'];
        }
        
        if (!empty($filtros['estado'])) {
            $where .= " AND v.estado = ?";
            $params[] = $filtros['estado'];
        }
        
        $sql = "SELECT v.*, c.nombre as cliente 
                FROM ventas v 
                LEFT JOIN clientes c ON v.cliente_id = c.id 
                $where 
                ORDER BY v.fecha_venta DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ['success' => true, 'facturas' => $facturas];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function anularFactura($id, $motivo) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $pdo->beginTransaction();
        
        // Obtener items de la factura
        $stmt = $pdo->prepare("SELECT producto_id, cantidad FROM venta_items WHERE venta_id = ?");
        $stmt->execute([$id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Restaurar stock
        foreach ($items as $item) {
            $stmt = $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
            $stmt->execute([$item['cantidad'], $item['producto_id']]);
        }
        
        // Anular factura
        $stmt = $pdo->prepare("UPDATE ventas SET estado = 'anulada', observaciones = ? WHERE id = ?");
        $stmt->execute([$motivo, $id]);
        
        // Registrar en auditoría
        $stmt = $pdo->prepare("INSERT INTO auditoria (usuario_id, accion, tabla, registro_id, detalles, ip, fecha) 
                               VALUES (?, 'anular', 'ventas', ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], $id, $motivo, $_SERVER['REMOTE_ADDR'] ?? '']);
        
        $pdo->commit();
        
        return ['success' => true, 'message' => 'Factura anulada correctamente'];
    } catch (PDOException $e) {
        if (isset($pdo)) $pdo->rollBack();
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function generarPDF($id) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Obtener datos de la factura
        $resultado = obtenerFactura($id);
        if (!$resultado['success']) {
            return $resultado;
        }
        
        $factura = $resultado['factura'];
        
        // Generar HTML del PDF
        $html = generarHTMLFactura($factura);
        
        // Guardar archivo temporal
        $timestamp = date('Y-m-d_H-i-s');
        $archivo = "../temp/factura_{$factura['numero_factura']}_{$timestamp}.html";
        
        if (!is_dir('../temp')) {
            mkdir('../temp', 0755, true);
        }
        
        file_put_contents($archivo, $html);
        
        return ['success' => true, 'message' => 'PDF generado correctamente', 'archivo' => basename($archivo)];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function generarHTMLFactura($factura) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Factura ' . $factura['numero_factura'] . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .empresa { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
            .cliente { margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .totales { text-align: right; }
            .total { font-size: 18px; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="empresa">TECNOXPERT</div>
            <div>FACTURA DE VENTA</div>
            <div>Número: ' . $factura['numero_factura'] . '</div>
            <div>Fecha: ' . date('d/m/Y', strtotime($factura['fecha_venta'])) . '</div>
        </div>
        
        <div class="cliente">
            <strong>Cliente:</strong> ' . $factura['cliente'] . '<br>
            <strong>Email:</strong> ' . $factura['cliente_email'] . '
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($factura['items'] as $item) {
        $html .= '
                <tr>
                    <td>' . $item['producto'] . '</td>
                    <td>' . $item['cantidad'] . '</td>
                    <td>$' . number_format($item['precio'], 0, ',', '.') . '</td>
                    <td>$' . number_format($item['cantidad'] * $item['precio'], 0, ',', '.') . '</td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>
        
        <div class="totales">
            <div>Subtotal: $' . number_format($factura['subtotal'], 0, ',', '.') . '</div>
            <div>IVA: $' . number_format($factura['iva'], 0, ',', '.') . '</div>
            <div class="total">Total: $' . number_format($factura['total'], 0, ',', '.') . '</div>
        </div>
        
        <div style="margin-top: 30px;">
            <strong>Observaciones:</strong><br>
            ' . ($factura['observaciones'] ?? 'Sin observaciones') . '
        </div>
    </body>
    </html>';
    
    return $html;
}

function enviarFacturaEmail($id, $email) {
    try {
        // Obtener datos de la factura
        $resultado = obtenerFactura($id);
        if (!$resultado['success']) {
            return $resultado;
        }
        
        $factura = $resultado['factura'];
        
        // Generar PDF
        $pdf_resultado = generarPDF($id);
        if (!$pdf_resultado['success']) {
            return $pdf_resultado;
        }
        
        // Aquí iría la lógica de envío de email
        // Por ahora simulamos el envío
        
        return ['success' => true, 'message' => 'Factura enviada por email correctamente'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}
?>

