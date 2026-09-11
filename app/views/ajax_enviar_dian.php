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
    $sql = "SELECT v.*, c.nombre as cliente_nombre, c.tipo_documento, c.numero_documento, c.direccion as cliente_direccion
            FROM ventas v 
            LEFT JOIN clientes c ON v.cliente_id = c.id 
            WHERE v.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$factura_id]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$factura) {
        echo json_encode(['success' => false, 'message' => 'Factura no encontrada']);
        exit();
    }
    
    // Verificar si ya fue enviada
    $sql = "SELECT COUNT(*) as total FROM fe_log WHERE venta_id = ? AND estado = 'enviada'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$factura_id]);
    $ya_enviada = $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
    
    if ($ya_enviada) {
        echo json_encode(['success' => false, 'message' => 'Esta factura ya fue enviada a la DIAN']);
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
    
    // Obtener datos de la empresa
    $sql = "SELECT clave, valor FROM config WHERE clave IN ('nombre_empresa', 'nit', 'direccion', 'ciudad', 'telefono', 'email')";
    $stmt = $pdo->query($sql);
    $config = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $config[$row['clave']] = $row['valor'];
    }
    
    // Preparar datos para envío a DIAN
    $datos_dian = [
        'empresa' => [
            'nombre' => $config['nombre_empresa'] ?? 'TECNOXPERT',
            'nit' => $config['nit'] ?? '900.000.000-1',
            'direccion' => $config['direccion'] ?? 'Calle Principal #123',
            'ciudad' => $config['ciudad'] ?? 'Bogotá',
            'telefono' => $config['telefono'] ?? '',
            'email' => $config['email'] ?? 'info@tecnoexpert.com'
        ],
        'factura' => [
            'numero' => $factura['numero_factura'],
            'fecha' => $factura['fecha_venta'],
            'subtotal' => $factura['subtotal'],
            'iva' => $factura['iva'],
            'descuento' => $factura['descuento'],
            'total' => $factura['total']
        ],
        'cliente' => [
            'nombre' => $factura['cliente_nombre'] ?? 'CLIENTE GENERAL',
            'tipo_documento' => $factura['tipo_documento'] ?? '',
            'numero_documento' => $factura['numero_documento'] ?? '',
            'direccion' => $factura['cliente_direccion'] ?? ''
        ],
        'items' => $items
    ];
    
    // Simular envío a DIAN (aquí iría la integración real)
    $resultado_envio = simularEnvioDIAN($datos_dian);
    
    if ($resultado_envio['success']) {
        // Registrar envío en fe_log
        $sql = "INSERT INTO fe_log (venta_id, estado, mensaje, creado_en) VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $factura_id,
            'enviada',
            $resultado_envio['message']
        ]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Factura enviada exitosamente a la DIAN. CUFE: ' . $resultado_envio['cufe']
        ]);
    } else {
        // Registrar error en fe_log
        $sql = "INSERT INTO fe_log (venta_id, estado, mensaje, creado_en) VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $factura_id,
            'error',
            $resultado_envio['message']
        ]);
        
        echo json_encode([
            'success' => false, 
            'message' => 'Error al enviar a la DIAN: ' . $resultado_envio['message']
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function simularEnvioDIAN($datos) {
    // Esta función simula el envío a la DIAN
    // En un entorno real, aquí se haría la integración con el proveedor de facturación electrónica
    
    // Simular procesamiento
    sleep(1);
    
    // Simular éxito (90% de probabilidad)
    if (rand(1, 10) <= 9) {
        $cufe = 'CUFE-' . date('YmdHis') . '-' . rand(1000, 9999);
        return [
            'success' => true,
            'message' => 'Factura procesada correctamente',
            'cufe' => $cufe
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Error de conexión con la DIAN'
        ];
    }
}
?>
