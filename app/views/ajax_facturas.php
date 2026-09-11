<?php
session_start();

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Conexión a la base de datos
try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión: ' . $e->getMessage()]);
    exit();
}

// Obtener datos JSON
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    case 'obtener_facturas_pendientes':
        obtenerFacturasPendientes();
        break;
    
    case 'enviar_email':
        enviarEmailFactura($input['factura_id'], $input['email']);
        break;
    
    case 'generar_pdf':
        generarPDFFactura($input['factura_id']);
        break;
    
    case 'enviar_dian':
        enviarFacturaDIAN($input['factura_id']);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}

/**
 * Obtener facturas pendientes para envío masivo
 */
function obtenerFacturasPendientes() {
    global $pdo;
    
    try {
        $sql = "SELECT v.id, v.numero_factura, v.total, v.fecha_venta, c.nombre as cliente_nombre
                FROM ventas v 
                LEFT JOIN clientes c ON v.cliente_id = c.id 
                WHERE v.estado_fe = 'pendiente' 
                ORDER BY v.fecha_venta ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'facturas' => $facturas,
            'total' => count($facturas)
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Enviar factura por email
 */
function enviarEmailFactura($factura_id, $email) {
    global $pdo;
    
    try {
        // Obtener datos de la factura
        $sql = "SELECT v.*, c.nombre as cliente_nombre, c.email as cliente_email
                FROM ventas v 
                LEFT JOIN clientes c ON v.cliente_id = c.id 
                WHERE v.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$factura_id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$factura) {
            echo json_encode(['success' => false, 'message' => 'Factura no encontrada']);
            return;
        }
        
        // Generar PDF
        $pdf_path = generarPDFInterno($factura_id);
        if (!$pdf_path) {
            echo json_encode(['success' => false, 'message' => 'Error generando PDF']);
            return;
        }
        
        // Configurar email
        $asunto = "Factura #" . $factura['numero_factura'] . " - TECNOXPERT";
        $mensaje = "Estimado cliente,\n\n";
        $mensaje .= "Adjunto encontrará la factura #" . $factura['numero_factura'] . " por un valor de $" . number_format($factura['total'], 0, ',', '.') . ".\n\n";
        $mensaje .= "Gracias por su compra.\n\n";
        $mensaje .= "TECNOXPERT\n";
        $mensaje .= "Contacto: contacto@tecnoxpert.com";
        
        // Enviar email (simulado)
        $resultado = enviarEmailSimulado($email, $asunto, $mensaje, $pdf_path);
        
        if ($resultado) {
            // Registrar en log
            $stmt = $pdo->prepare("INSERT INTO fe_log (venta_id, tipo_documento, numero_documento, estado, mensaje, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$factura_id, 'factura', $factura['numero_factura'], 'enviado', 'Factura enviada por email a ' . $email, $_SESSION['user_id']]);
            
            echo json_encode(['success' => true, 'message' => 'Factura enviada por email exitosamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error enviando email']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Generar PDF de factura
 */
function generarPDFFactura($factura_id) {
    try {
        $pdf_path = generarPDFInterno($factura_id);
        
        if ($pdf_path) {
            // Forzar descarga
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="factura_' . $factura_id . '.pdf"');
            readfile($pdf_path);
            unlink($pdf_path); // Eliminar archivo temporal
        } else {
            echo json_encode(['success' => false, 'message' => 'Error generando PDF']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Generar PDF interno
 */
function generarPDFInterno($factura_id) {
    global $pdo;
    
    try {
        // Obtener datos de la factura
        $sql = "SELECT v.*, c.*, u.nombre as vendedor_nombre
                FROM ventas v 
                LEFT JOIN clientes c ON v.cliente_id = c.id 
                LEFT JOIN usuarios u ON v.usuario_id = u.id 
                WHERE v.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$factura_id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$factura) {
            return false;
        }
        
        // Obtener items
        $sql = "SELECT vi.*, p.nombre as producto_nombre, p.referencia as producto_codigo
                FROM venta_items vi 
                LEFT JOIN productos p ON vi.producto_id = p.id 
                WHERE vi.venta_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$factura_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generar HTML para PDF
        $html = generarHTMLFactura($factura, $items);
        
        // Crear archivo temporal
        $temp_file = tempnam(sys_get_temp_dir(), 'factura_') . '.html';
        file_put_contents($temp_file, $html);
        
        return $temp_file;
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Generar HTML para factura
 */
function generarHTMLFactura($factura, $items) {
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Factura #' . $factura['numero_factura'] . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .company-info { margin-bottom: 20px; }
        .invoice-details { margin-bottom: 20px; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .items-table th { background-color: #f2f2f2; }
        .totals { text-align: right; }
        .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>TECNOXPERT</h1>
        <p>Factura Electrónica</p>
    </div>
    
    <div class="company-info">
        <h3>Datos del Emisor</h3>
        <p><strong>NIT:</strong> 900000000</p>
        <p><strong>Dirección:</strong> Calle 53 Carrera 49-135, Medellín</p>
        <p><strong>Teléfono:</strong> </p>
    </div>
    
    <div class="invoice-details">
        <h3>Datos de la Factura</h3>
        <p><strong>Número:</strong> ' . $factura['numero_factura'] . '</p>
        <p><strong>Fecha:</strong> ' . date('d/m/Y', strtotime($factura['fecha_venta'])) . '</p>
        <p><strong>Cliente:</strong> ' . htmlspecialchars($factura['cliente_nombre'] ?? 'CLIENTE GENERAL') . '</p>
        <p><strong>Vendedor:</strong> ' . htmlspecialchars($factura['vendedor_nombre'] ?? 'Sistema') . '</p>
    </div>
    
    <table class="items-table">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio Unit.</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($items as $item) {
        $html .= '<tr>
            <td>' . htmlspecialchars($item['producto_nombre']) . '</td>
            <td>' . $item['cantidad'] . '</td>
            <td>$' . number_format($item['precio_unitario'], 0, ',', '.') . '</td>
            <td>$' . number_format($item['subtotal'], 0, ',', '.') . '</td>
        </tr>';
    }
    
    $html .= '</tbody>
    </table>
    
    <div class="totals">
        <p><strong>Subtotal:</strong> $' . number_format($factura['subtotal'], 0, ',', '.') . '</p>
        <p><strong>IVA:</strong> $' . number_format($factura['iva'], 0, ',', '.') . '</p>
        <p><strong>Total:</strong> $' . number_format($factura['total'], 0, ',', '.') . '</p>
    </div>
    
    <div class="footer">
        <p>TECNOXPERT by Ing Hugo Ardila - Contacto: contacto@tecnoxpert.com</p>
        <p>Esta es una factura electrónica válida</p>
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * Enviar email simulado
 */
function enviarEmailSimulado($email, $asunto, $mensaje, $archivo_adjunto) {
    // Simular envío de email
    // En producción, usar PHPMailer o similar
    
    // Log del email
    error_log("EMAIL SIMULADO:");
    error_log("Para: " . $email);
    error_log("Asunto: " . $asunto);
    error_log("Mensaje: " . $mensaje);
    error_log("Archivo: " . $archivo_adjunto);
    
    return true; // Simular éxito
}

/**
 * Enviar factura a DIAN
 */
function enviarFacturaDIAN($factura_id) {
    global $pdo;
    
    try {
        // Obtener datos de la factura
        $sql = "SELECT v.*, c.nombre as cliente_nombre
                FROM ventas v 
                LEFT JOIN clientes c ON v.cliente_id = c.id 
                WHERE v.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$factura_id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$factura) {
            echo json_encode(['success' => false, 'message' => 'Factura no encontrada']);
            return;
        }
        
        // Generar XML
        include_once 'ajax_facturacion_electronica.php';
        $xml_result = generarXMLFactura($factura_id);
        
        if (!$xml_result['success']) {
            echo json_encode($xml_result);
            return;
        }
        
        // Simular envío a DIAN
        $resultado = simularEnvioDIAN($factura);
        $resultado['xml'] = $xml_result['xml'];
        $resultado['cufe'] = $xml_result['cufe'];
        
        if ($resultado['success']) {
            // Actualizar estado
            $stmt = $pdo->prepare("UPDATE ventas SET estado_fe = 'enviada', cufe = ? WHERE id = ?");
            $stmt->execute([$resultado['cufe'], $factura_id]);
            
            // Guardar XML en respaldo
            guardarRespaldoElectronico($factura_id, 'xml', $xml_result['xml']);
            
            // Registrar en log
            $stmt = $pdo->prepare("INSERT INTO fe_log (tipo, mensaje, factura_id, estado) VALUES (?, ?, ?, ?)");
            $stmt->execute(['envio', 'Factura enviada exitosamente a DIAN', $factura_id, 'exitoso']);
        }
        
        echo json_encode($resultado);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Guardar respaldo electrónico para auditoría
 */
function guardarRespaldoElectronico($factura_id, $tipo, $contenido) {
    global $pdo;
    
    try {
        // Crear directorio de respaldos si no existe
        $respaldo_dir = dirname(__DIR__) . '/respaldos_fe/';
        if (!is_dir($respaldo_dir)) {
            mkdir($respaldo_dir, 0755, true);
        }
        
        // Generar nombre único para el archivo
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "factura_{$factura_id}_{$tipo}_{$timestamp}";
        
        if ($tipo === 'xml') {
            $filename .= '.xml';
        } elseif ($tipo === 'pdf') {
            $filename .= '.pdf';
        } else {
            $filename .= '.txt';
        }
        
        $file_path = $respaldo_dir . $filename;
        
        // Guardar archivo
        file_put_contents($file_path, $contenido);
        
        // Calcular hash del archivo
        $hash_archivo = hash_file('sha256', $file_path);
        
        // Guardar registro en base de datos
        $stmt = $pdo->prepare("INSERT INTO fe_respaldo (factura_id, tipo_documento, archivo_path, hash_archivo) VALUES (?, ?, ?, ?)");
        $stmt->execute([$factura_id, $tipo, $file_path, $hash_archivo]);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error guardando respaldo: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtener respaldos de una factura
 */
function obtenerRespaldosFactura($factura_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM fe_respaldo WHERE factura_id = ? AND estado = 'activo' ORDER BY fecha_creacion DESC");
        $stmt->execute([$factura_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Verificar integridad de respaldo
 */
function verificarIntegridadRespaldo($respaldo_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM fe_respaldo WHERE id = ?");
        $stmt->execute([$respaldo_id]);
        $respaldo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$respaldo) {
            return ['success' => false, 'message' => 'Respaldo no encontrado'];
        }
        
        // Verificar si el archivo existe
        if (!file_exists($respaldo['archivo_path'])) {
            return ['success' => false, 'message' => 'Archivo de respaldo no encontrado'];
        }
        
        // Verificar hash
        $hash_actual = hash_file('sha256', $respaldo['archivo_path']);
        if ($hash_actual !== $respaldo['hash_archivo']) {
            return ['success' => false, 'message' => 'Archivo de respaldo corrupto'];
        }
        
        return ['success' => true, 'message' => 'Respaldo íntegro'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error verificando respaldo: ' . $e->getMessage()];
    }
}
?>

function verificarIntegridadRespaldo($respaldo_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM fe_respaldo WHERE id = ?");
        $stmt->execute([$respaldo_id]);
        $respaldo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$respaldo) {
            return ['success' => false, 'message' => 'Respaldo no encontrado'];
        }
        
        // Verificar si el archivo existe
        if (!file_exists($respaldo['archivo_path'])) {
            return ['success' => false, 'message' => 'Archivo de respaldo no encontrado'];
        }
        
        // Verificar hash
        $hash_actual = hash_file('sha256', $respaldo['archivo_path']);
        if ($hash_actual !== $respaldo['hash_archivo']) {
            return ['success' => false, 'message' => 'Archivo de respaldo corrupto'];
        }
        
        return ['success' => true, 'message' => 'Respaldo íntegro'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error verificando respaldo: ' . $e->getMessage()];
    }
}
?>
