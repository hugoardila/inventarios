<?php
session_start();

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$factura_id = $_POST['id'] ?? 0;

if (!$factura_id) {
    echo json_encode(['success' => false, 'message' => 'ID de factura no válido']);
    exit();
}

try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener datos de la factura
    $sql = "SELECT v.*, c.nombre as cliente_nombre, c.tipo_documento, c.numero_documento, c.direccion as cliente_direccion,
                   u.nombre as vendedor_nombre
            FROM ventas v 
            LEFT JOIN clientes c ON v.cliente_id = c.id 
            LEFT JOIN usuarios u ON v.usuario_id = u.id 
            WHERE v.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$factura_id]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$factura) {
        echo json_encode(['success' => false, 'message' => 'Factura no encontrada']);
        exit();
    }
    
    // Obtener items de la factura
    $sql = "SELECT vi.*, p.nombre as producto_nombre, p.referencia 
            FROM venta_items vi 
            LEFT JOIN productos p ON vi.producto_id = p.id 
            WHERE vi.venta_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$factura_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generar HTML
    $html = '
    <div class="row">
        <div class="col-md-6">
            <h6><i class="bi bi-building"></i> Información de la Empresa</h6>
            <div class="border rounded p-3 mb-3">
                <strong>TECNOXPERT</strong><br>
                <small class="text-muted">NIT: 900.000.000-1</small><br>
                <small class="text-muted">Calle Principal #123, Bogotá</small>
            </div>
        </div>
        <div class="col-md-6">
            <h6><i class="bi bi-person"></i> Información del Cliente</h6>
            <div class="border rounded p-3 mb-3">
                <strong>' . htmlspecialchars($factura['cliente_nombre'] ?? 'CLIENTE GENERAL') . '</strong><br>';
                
    if ($factura['tipo_documento'] && $factura['numero_documento']) {
        $html .= '<small class="text-muted">' . htmlspecialchars($factura['tipo_documento']) . ': ' . htmlspecialchars($factura['numero_documento']) . '</small><br>';
    }
    
    if ($factura['cliente_direccion']) {
        $html .= '<small class="text-muted">' . htmlspecialchars($factura['cliente_direccion']) . '</small>';
    }
    
    $html .= '
            </div>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-4">
            <strong>Número de Factura:</strong><br>
            <span class="text-primary">' . htmlspecialchars($factura['numero_factura']) . '</span>
        </div>
        <div class="col-md-4">
            <strong>Fecha:</strong><br>
            ' . date('d/m/Y H:i', strtotime($factura['fecha_venta'])) . '
        </div>
        <div class="col-md-4">
            <strong>Vendedor:</strong><br>
            ' . htmlspecialchars($factura['vendedor_nombre'] ?? 'Sistema') . '
        </div>
    </div>
    
    <h6><i class="bi bi-list"></i> Productos</h6>
    <div class="table-responsive">
        <table class="table table-sm table-bordered">
            <thead class="table-primary">
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th class="text-end">Cantidad</th>
                    <th class="text-end">Precio Unit.</th>
                    <th class="text-end">Subtotal</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($items as $item) {
        $html .= '
            <tr>
                <td>' . htmlspecialchars($item['referencia'] ?? '') . '</td>
                <td>' . htmlspecialchars($item['producto_nombre'] ?? '') . '</td>
                <td class="text-end">' . number_format($item['cantidad'], 0) . '</td>
                <td class="text-end">$' . number_format($item['precio_unitario'], 0, ',', '.') . '</td>
                <td class="text-end">$' . number_format($item['subtotal'], 0, ',', '.') . '</td>
            </tr>';
    }
    
    $html .= '
            </tbody>
        </table>
    </div>
    
    <div class="row">
        <div class="col-md-6"></div>
        <div class="col-md-6">
            <div class="border rounded p-3">
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal:</span>
                    <span>$' . number_format($factura['subtotal'], 0, ',', '.') . '</span>
                </div>';
    
    if ($factura['iva'] > 0) {
        $html .= '
                <div class="d-flex justify-content-between mb-2">
                    <span>IVA (19%):</span>
                    <span>$' . number_format($factura['iva'], 0, ',', '.') . '</span>
                </div>';
    }
    
    if ($factura['descuento'] > 0) {
        $html .= '
                <div class="d-flex justify-content-between mb-2">
                    <span>Descuento:</span>
                    <span>-$' . number_format($factura['descuento'], 0, ',', '.') . '</span>
                </div>';
    }
    
    $html .= '
                <hr>
                <div class="d-flex justify-content-between">
                    <strong>TOTAL:</strong>
                    <strong class="text-success">$' . number_format($factura['total'], 0, ',', '.') . '</strong>
                </div>
            </div>
        </div>
    </div>';
    
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

