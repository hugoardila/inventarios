<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
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

// Obtener acción
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Decodificar JSON si viene en el body
$input = [];
if ($_SERVER['CONTENT_TYPE'] === 'application/json') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $action;
} else {
    $input = $_POST;
}

// Procesar acciones
switch ($action) {
    case 'obtener_motivos':
        obtenerMotivos();
        break;
    case 'obtener_productos_factura':
        obtenerProductosFactura($input['factura_id'] ?? 0);
        break;
    case 'crear_nota_debito':
        crearNotaDebito($input['factura_id'] ?? 0, $input['motivo_codigo'] ?? '', $input['motivo'] ?? '', $input['tipo_monto'] ?? 'total', $input);
        break;
    case 'crear_nota_debito_avanzada':
        crearNotaDebitoAvanzada($input);
        break;
    case 'enviar_nota_debito_dian':
        enviarNotaDebitoDIAN($input['nota_id'] ?? 0);
        break;
    case 'obtener_detalle_nota':
        obtenerDetalleNota($input['nota_id'] ?? 0);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}

/**
 * Obtener motivos de nota de débito
 */
function obtenerMotivos() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM motivos_nota_debito WHERE activo = 1 ORDER BY codigo");
        $motivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'motivos' => $motivos
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Obtener productos de una factura específica
 */
function obtenerProductosFactura($factura_id) {
    global $pdo;
    
    try {
        $sql = "SELECT vi.*, p.nombre as producto_nombre, p.referencia as producto_referencia
                FROM venta_items vi
                LEFT JOIN productos p ON vi.producto_id = p.id
                WHERE vi.venta_id = ?
                ORDER BY vi.producto_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$factura_id]);
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'productos' => $productos
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Crear nota de débito (método simple)
 */
function crearNotaDebito($factura_id, $motivo_codigo, $motivo, $tipo_monto, $input) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Obtener datos de la factura
        $sql = "SELECT v.*, c.id as cliente_id 
                FROM ventas v 
                LEFT JOIN clientes c ON v.cliente_id = c.id 
                WHERE v.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$factura_id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$factura) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Factura no encontrada']);
            return;
        }
        
        // Generar número de nota de débito
        $numero_nota = 'ND' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        // Verificar que el número no exista
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notas_debito WHERE numero_nota = ?");
        $stmt->execute([$numero_nota]);
        while ($stmt->fetchColumn() > 0) {
            $numero_nota = 'ND' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $stmt->execute([$numero_nota]);
        }
        
        // Determinar los montos según el tipo
        if ($tipo_monto === 'parcial') {
            $subtotal = floatval($input['subtotal_parcial'] ?? 0);
            $iva = floatval($input['iva_parcial'] ?? 0);
            $total = floatval($input['total_parcial'] ?? 0);
        } else {
            $subtotal = floatval($factura['subtotal'] ?? 0);
            $iva = floatval($factura['iva'] ?? 0);
            $total = floatval($factura['total'] ?? 0);
        }
        
        // Crear la nota de débito
        $stmt = $pdo->prepare("INSERT INTO notas_debito 
            (numero_nota, factura_id, cliente_id, motivo, motivo_codigo, tipo_monto, 
             subtotal, iva, total, subtotal_parcial, iva_parcial, total_parcial, usuario_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $numero_nota,
            $factura_id,
            $factura['cliente_id'],
            $motivo,
            $motivo_codigo,
            $tipo_monto,
            $subtotal,
            $iva,
            $total,
            $tipo_monto === 'parcial' ? $subtotal : null,
            $tipo_monto === 'parcial' ? $iva : null,
            $tipo_monto === 'parcial' ? $total : null,
            $_SESSION['user_id']
        ]);
        
        $nota_id = $pdo->lastInsertId();
        
        // Crear items (todos los productos de la factura)
        $sql_items = "SELECT * FROM venta_items WHERE venta_id = ?";
        $stmt_items = $pdo->prepare($sql_items);
        $stmt_items->execute([$factura_id]);
        $items_factura = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items_factura as $item) {
            $subtotal_item = floatval($item['subtotal'] ?? 0);
            $iva_item = floatval($item['iva'] ?? 0);
            $total_item = floatval($item['total'] ?? 0);
            
            $stmt_item = $pdo->prepare("INSERT INTO nota_debito_items 
                (nota_debito_id, producto_id, cantidad, precio_unitario, subtotal, iva, total, motivo_codigo, motivo, tipo_monto) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_item->execute([
                $nota_id,
                $item['producto_id'],
                $item['cantidad'],
                $item['precio_unitario'],
                $subtotal_item,
                $iva_item,
                $total_item,
                $motivo_codigo,
                $motivo,
                $tipo_monto
            ]);
        }
        
        // Registrar en log
        $mensaje_log = "Nota de débito {$tipo_monto} creada: {$motivo_codigo} - {$motivo}";
        if ($tipo_monto === 'parcial') {
            $mensaje_log .= " (Monto: $" . number_format($total, 2) . ")";
        }
        $stmt = $pdo->prepare("INSERT INTO fe_log (venta_id, tipo_documento, numero_documento, estado, mensaje, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$factura_id, 'nota_debito', $numero_nota, 'pendiente', $mensaje_log, $_SESSION['user_id']]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "Nota de débito {$numero_nota} creada exitosamente",
            'nota_id' => $nota_id,
            'numero_nota' => $numero_nota
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Crear nota de débito avanzada (por toda la factura o por productos individuales)
 */
function crearNotaDebitoAvanzada($input) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $factura_id = $input['factura_id'];
        $tipo = $input['tipo']; // 'total' o 'productos'
        
        // Obtener datos de la factura original
        $sql = "SELECT v.*, c.id as cliente_id, c.nombre as cliente_nombre 
                FROM ventas v 
                LEFT JOIN clientes c ON v.cliente_id = c.id 
                WHERE v.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$factura_id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$factura) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Factura no encontrada']);
            return;
        }
        
        // Generar número de nota de débito
        $numero_nota = 'ND' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        // Verificar que el número no exista
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notas_debito WHERE numero_nota = ?");
        $stmt->execute([$numero_nota]);
        while ($stmt->fetchColumn() > 0) {
            $numero_nota = 'ND' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $stmt->execute([$numero_nota]);
        }
        
        if ($tipo === 'total') {
            // Nota de débito por toda la factura
            $motivo_codigo = $input['motivo_codigo'];
            $motivo = $input['motivo'];
            $tipo_monto = $input['tipo_monto'];
            
            // Determinar los montos según el tipo
            if ($tipo_monto === 'parcial') {
                $subtotal = floatval($input['subtotal_parcial'] ?? 0);
                $iva = floatval($input['iva_parcial'] ?? 0);
                $total = floatval($input['total_parcial'] ?? 0);
            } else {
                $subtotal = floatval($factura['subtotal'] ?? 0);
                $iva = floatval($factura['iva'] ?? 0);
                $total = floatval($factura['total'] ?? 0);
            }
            
            // Crear la nota de débito
            $stmt = $pdo->prepare("INSERT INTO notas_debito 
                (numero_nota, factura_id, cliente_id, motivo, motivo_codigo, tipo_monto, 
                 subtotal, iva, total, subtotal_parcial, iva_parcial, total_parcial, usuario_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $numero_nota,
                $factura_id,
                $factura['cliente_id'],
                $motivo,
                $motivo_codigo,
                $tipo_monto,
                $subtotal,
                $iva,
                $total,
                $tipo_monto === 'parcial' ? $subtotal : null,
                $tipo_monto === 'parcial' ? $iva : null,
                $tipo_monto === 'parcial' ? $total : null,
                $_SESSION['user_id']
            ]);
            
            $nota_id = $pdo->lastInsertId();
            
            // Crear items de la nota de débito (todos los productos de la factura)
            $sql_items = "SELECT * FROM venta_items WHERE venta_id = ?";
            $stmt_items = $pdo->prepare($sql_items);
            $stmt_items->execute([$factura_id]);
            $items_factura = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($items_factura as $item) {
                $subtotal_item = floatval($item['subtotal'] ?? 0);
                $iva_item = floatval($item['iva'] ?? 0);
                $total_item = floatval($item['total'] ?? 0);
                
                $stmt_item = $pdo->prepare("INSERT INTO nota_debito_items 
                    (nota_debito_id, producto_id, cantidad, precio_unitario, subtotal, iva, total, motivo_codigo, motivo, tipo_monto) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_item->execute([
                    $nota_id,
                    $item['producto_id'],
                    $item['cantidad'],
                    $item['precio_unitario'],
                    $subtotal_item,
                    $iva_item,
                    $total_item,
                    $motivo_codigo,
                    $motivo,
                    $tipo_monto
                ]);
            }
            
        } else {
            // Nota de débito por productos individuales
            $productos = $input['productos'];
            
            // Calcular totales
            $subtotal_total = 0;
            $iva_total = 0;
            $total_total = 0;
            
            foreach ($productos as $producto) {
                // Obtener datos del producto de la factura
                $sql_item = "SELECT * FROM venta_items WHERE venta_id = ? AND id = ?";
                $stmt_item = $pdo->prepare($sql_item);
                $stmt_item->execute([$factura_id, $producto['item_id']]);
                $item_factura = $stmt_item->fetch(PDO::FETCH_ASSOC);
                
                if ($item_factura) {
                    // Asegurar que los valores no sean NULL
                    $subtotal_item = $item_factura['subtotal'] ?? 0;
                    $iva_item = $item_factura['iva'] ?? 0;
                    $total_item = $item_factura['total'] ?? 0;
                    
                    $subtotal_total += $subtotal_item;
                    $iva_total += $iva_item;
                    $total_total += $total_item;
                }
            }
            
            // Crear la nota de débito
            $stmt = $pdo->prepare("INSERT INTO notas_debito 
                (numero_nota, factura_id, cliente_id, motivo, motivo_codigo, tipo_monto, 
                 subtotal, iva, total, usuario_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $numero_nota,
                $factura_id,
                $factura['cliente_id'],
                'Nota de débito por productos individuales',
                'MULTI',
                'parcial',
                $subtotal_total ?: 0,
                $iva_total ?: 0,
                $total_total ?: 0,
                $_SESSION['user_id']
            ]);
            
            $nota_id = $pdo->lastInsertId();
            
            // Crear items de la nota de débito (solo los productos seleccionados)
            foreach ($productos as $producto) {
                // Obtener datos del producto de la factura
                $sql_item = "SELECT * FROM venta_items WHERE venta_id = ? AND id = ?";
                $stmt_item = $pdo->prepare($sql_item);
                $stmt_item->execute([$factura_id, $producto['item_id']]);
                $item_factura = $stmt_item->fetch(PDO::FETCH_ASSOC);
                
                if ($item_factura) {
                    // Asegurar que los valores no sean NULL
                    $subtotal = $item_factura['subtotal'] ?? 0;
                    $iva = $item_factura['iva'] ?? 0;
                    $total = $item_factura['total'] ?? 0;
                    
                    $stmt_item_nd = $pdo->prepare("INSERT INTO nota_debito_items 
                        (nota_debito_id, producto_id, cantidad, precio_unitario, subtotal, iva, total, motivo_codigo, motivo, tipo_monto, cantidad_parcial, subtotal_parcial, iva_parcial, total_parcial) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt_item_nd->execute([
                        $nota_id,
                        $producto['producto_id'],
                        $item_factura['cantidad'],
                        $item_factura['precio_unitario'],
                        $subtotal,
                        $iva,
                        $total,
                        $producto['motivo_codigo'],
                        $producto['motivo'],
                        $producto['tipo_monto'],
                        $producto['tipo_monto'] === 'parcial' ? $producto['cantidad_parcial'] : null,
                        $producto['tipo_monto'] === 'parcial' ? $producto['subtotal_parcial'] : null,
                        $producto['tipo_monto'] === 'parcial' ? $producto['iva_parcial'] : null,
                        $producto['tipo_monto'] === 'parcial' ? $producto['total_parcial'] : null
                    ]);
                }
            }
        }
        
        // Registrar en log
        $mensaje_log = "Nota de débito {$tipo} creada: {$numero_nota}";
        if ($tipo === 'total') {
            $mensaje_log .= " - {$input['motivo_codigo']} - {$input['motivo']}";
        } else {
            $mensaje_log .= " - " . count($productos) . " producto(s)";
        }
        
        $stmt = $pdo->prepare("INSERT INTO fe_log (venta_id, tipo_documento, numero_documento, estado, mensaje, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$factura_id, 'nota_debito', $numero_nota, 'pendiente', $mensaje_log, $_SESSION['user_id']]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "Nota de débito {$numero_nota} creada exitosamente",
            'nota_id' => $nota_id,
            'numero_nota' => $numero_nota
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Enviar nota de débito a DIAN
 */
function enviarNotaDebitoDIAN($nota_id) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Obtener datos de la nota de débito
        $sql = "SELECT nd.*, v.numero_factura, c.nombre as cliente_nombre, c.numero_documento, c.tipo_documento
                FROM notas_debito nd
                LEFT JOIN ventas v ON nd.factura_id = v.id
                LEFT JOIN clientes c ON nd.cliente_id = c.id
                WHERE nd.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nota_id]);
        $nota = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$nota) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Nota de débito no encontrada']);
            return;
        }
        
        if ($nota['estado'] !== 'pendiente') {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'La nota de débito ya fue procesada']);
            return;
        }
        
        // Obtener configuración de DIAN del ambiente seleccionado
        require_once 'funciones.php';
        $config_dian = obtenerConfiguracionFE($_SESSION['user_id']);
        
        if (!$config_dian || empty($config_dian['software_id'])) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Configuración de DIAN no encontrada']);
            return;
        }
        
        // Generar XML de la nota de débito
        $xml_nota = generarXMLNotaDebito($nota, $config_dian);
        
        // Generar CUFE real
        $cufe = generarCUFE($nota, $config_dian);
        
        // Enviar a DIAN (usar la misma lógica que las facturas)
        $resultado_dian = enviarDocumentoDIAN($xml_nota, $cufe, $config_dian);
        
        if ($resultado_dian['success']) {
            $estado_dian = 'aceptada';
            $mensaje_dian = 'Nota de débito aceptada por DIAN';
        } else {
            $estado_dian = 'rechazada';
            $mensaje_dian = 'Nota de débito rechazada por DIAN: ' . $resultado_dian['message'];
        }
        
        // Actualizar estado de la nota de débito
        $stmt = $pdo->prepare("UPDATE notas_debito SET estado = ?, enviado_en = NOW() WHERE id = ?");
        $stmt->execute([$estado_dian, $nota_id]);
        
        // Registrar en log con CUFE
        $stmt = $pdo->prepare("INSERT INTO fe_log (venta_id, tipo_documento, numero_documento, estado, mensaje, cufe, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $nota['factura_id'], 
            'nota_debito', 
            $nota['numero_nota'], 
            'aceptado', 
            $mensaje_dian, 
            $cufe,
            $_SESSION['user_id']
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "Nota de débito {$nota['numero_nota']} enviada exitosamente a DIAN",
            'estado' => $estado_dian,
            'cufe' => $cufe
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Obtener detalle de una nota de débito
 */
function obtenerDetalleNota($nota_id) {
    global $pdo;
    
    try {
        // Obtener datos de la nota de débito
        $sql = "SELECT nd.*, v.numero_factura, c.nombre as cliente_nombre, u.nombre as usuario_nombre,
                       fl.cufe, fl.creado_en as fecha_envio
                FROM notas_debito nd
                LEFT JOIN ventas v ON nd.factura_id = v.id
                LEFT JOIN clientes c ON nd.cliente_id = c.id
                LEFT JOIN usuarios u ON nd.usuario_id = u.id
                LEFT JOIN fe_log fl ON fl.venta_id = nd.factura_id AND fl.tipo_documento = 'nota_debito' AND fl.numero_documento = nd.numero_nota
                WHERE nd.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nota_id]);
        $nota = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$nota) {
            echo json_encode(['success' => false, 'message' => 'Nota de débito no encontrada']);
            return;
        }
        
        // Obtener items de la nota de débito
        $sql_items = "SELECT ndi.*, p.nombre as producto_nombre, p.referencia as producto_referencia
                      FROM nota_debito_items ndi
                      LEFT JOIN productos p ON ndi.producto_id = p.id
                      WHERE ndi.nota_debito_id = ?
                      ORDER BY ndi.id";
        $stmt_items = $pdo->prepare($sql_items);
        $stmt_items->execute([$nota_id]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'nota' => $nota,
            'items' => $items
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Generar XML para nota de débito
 */
function generarXMLNotaDebito($nota, $config) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Obtener datos de la factura original
        $sql_factura = "SELECT v.*, c.nombre as cliente_nombre, c.numero_documento, c.direccion as cliente_direccion
                        FROM ventas v 
                        LEFT JOIN clientes c ON v.cliente_id = c.id 
                        WHERE v.id = ?";
        $stmt_factura = $pdo->prepare($sql_factura);
        $stmt_factura->execute([$nota['factura_id']]);
        $factura_original = $stmt_factura->fetch(PDO::FETCH_ASSOC);
        
        if (!$factura_original) {
            return ['success' => false, 'message' => 'Factura original no encontrada'];
        }
        
        // Obtener items de la nota de débito
        $sql_items = "SELECT ndi.*, p.nombre as producto_nombre, p.referencia as producto_codigo
                      FROM nota_debito_items ndi 
                      LEFT JOIN productos p ON ndi.producto_id = p.id 
                      WHERE ndi.nota_debito_id = ?";
        $stmt_items = $pdo->prepare($sql_items);
        $stmt_items->execute([$nota['id']]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($items)) {
            return ['success' => false, 'message' => 'La nota de débito debe tener al menos un item'];
        }
        
        // Generar CUDE (Código Único de Documento Electrónico)
        $cude = generarCUFE($nota, $config);
        
        // Generar UUID único para el documento
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        // Construir XML completo según estándares DIAN UBL 2.1 para Nota de Débito
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<DebitNote xmlns="urn:oasis:names:specification:ubl:schema:xsd:DebitNote-2" 
           xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
           xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
           xmlns:ccts="urn:un:unece:uncefact:documentation:2"
           xmlns:qdt="urn:oasis:names:specification:ubl:schema:xsd:QualifiedDatatypes-2"
           xmlns:udt="urn:un:unece:uncefact:data:specification:UnqualifiedDataTypesSchemaModule:2">
    
    <!-- Encabezado del documento (DebitNote) -->
    <cbc:ID>' . htmlspecialchars($nota['numero_nota']) . '</cbc:ID>
    <cbc:UUID>' . $uuid . '</cbc:UUID>
    <cbc:IssueDate>' . date('Y-m-d', strtotime($nota['fecha_creacion'])) . '</cbc:IssueDate>
    <cbc:IssueTime>' . date('H:i:s', strtotime($nota['fecha_creacion'])) . '</cbc:IssueTime>
    <cbc:DebitNoteTypeCode listID="1" listAgencyID="195" listAgencyName="CO, DIAN" listName="Tipo de Documento" listURI="urn:oasis:names:specification:ubl:codelist:gc:DebitNoteTypeCode-1.0">92</cbc:DebitNoteTypeCode>
    <cbc:DocumentCurrencyCode listID="ISO 4217 Alpha" listName="Currency">COP</cbc:DocumentCurrencyCode>
    
    <!-- Emisor (AccountingSupplierParty) -->
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PartyIdentification>
                <cbc:ID schemeID="4" schemeName="31" schemeAgencyID="195" schemeAgencyName="CO, DIAN" schemeURI="urn:oasis:names:specification:ubl:codelist:gc:PartyIdentificationCode-1.0">' . htmlspecialchars($config['nit_empresa']) . '</cbc:ID>
            </cac:PartyIdentification>
            <cac:PartyName>
                <cbc:Name>' . htmlspecialchars($config['nombre_empresa']) . '</cbc:Name>
            </cac:PartyName>
            <cac:PostalAddress>
                <cbc:StreetName>' . htmlspecialchars($config['direccion_empresa'] ?? 'Dirección Principal') . '</cbc:StreetName>
                <cbc:CityName>' . htmlspecialchars($config['ciudad_empresa'] ?? 'Bogotá') . '</cbc:CityName>
                <cac:Country>
                    <cbc:IdentificationCode listID="ISO 3166-1" listName="Country">CO</cbc:IdentificationCode>
                </cac:Country>
            </cac:PostalAddress>
            <cac:PartyTaxScheme>
                <cac:TaxScheme>
                    <cbc:ID>01</cbc:ID>
                </cac:TaxScheme>
            </cac:PartyTaxScheme>
        </cac:Party>
    </cac:AccountingSupplierParty>
    
    <!-- Comprador (AccountingCustomerParty) -->
    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PartyIdentification>
                <cbc:ID schemeID="4" schemeName="31" schemeAgencyID="195" schemeAgencyName="CO, DIAN" schemeURI="urn:oasis:names:specification:ubl:codelist:gc:PartyIdentificationCode-1.0">' . htmlspecialchars($factura_original['numero_documento'] ?? '900000000') . '</cbc:ID>
            </cac:PartyIdentification>
            <cac:PartyName>
                <cbc:Name>' . htmlspecialchars($factura_original['cliente_nombre'] ?? 'Cliente Genérico') . '</cbc:Name>
            </cac:PartyName>
            <cac:PostalAddress>
                <cbc:StreetName>' . htmlspecialchars($factura_original['cliente_direccion'] ?? 'Dirección Cliente') . '</cbc:StreetName>
                <cac:Country>
                    <cbc:IdentificationCode listID="ISO 3166-1" listName="Country">CO</cbc:IdentificationCode>
                </cac:Country>
            </cac:PostalAddress>
        </cac:Party>
    </cac:AccountingCustomerParty>
    
    <!-- Motivo del incremento (DiscrepancyResponse) -->
    <cac:DiscrepancyResponse>
        <cbc:ReferenceID>' . htmlspecialchars($factura_original['numero_factura']) . '</cbc:ReferenceID>
        <cbc:ResponseCode listID="1" listAgencyID="195" listAgencyName="CO, DIAN" listName="Código de Respuesta" listURI="urn:oasis:names:specification:ubl:codelist:gc:ResponseCode-1.0">1</cbc:ResponseCode>
        <cbc:Description>' . htmlspecialchars($nota['motivo'] ?? 'Incremento de factura') . '</cbc:Description>
    </cac:DiscrepancyResponse>
    
    <!-- Referencia a la factura original (BillingReference) -->
    <cac:BillingReference>
        <cac:InvoiceDocumentReference>
            <cbc:ID>' . htmlspecialchars($factura_original['numero_factura']) . '</cbc:ID>
            <cbc:IssueDate>' . date('Y-m-d', strtotime($factura_original['fecha_venta'])) . '</cbc:IssueDate>
        </cac:InvoiceDocumentReference>
    </cac:BillingReference>
    
    <!-- Impuestos (TaxTotal) -->
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="COP">' . number_format($nota['iva'], 2, '.', '') . '</cbc:TaxAmount>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="COP">' . number_format($nota['subtotal'], 2, '.', '') . '</cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="COP">' . number_format($nota['iva'], 2, '.', '') . '</cbc:TaxAmount>
            <cac:TaxCategory>
                <cbc:ID schemeID="1" schemeName="Código de Tipo de Impuesto" schemeAgencyID="195" schemeAgencyName="CO, DIAN" schemeURI="urn:oasis:names:specification:ubl:codelist:gc:TaxCategoryCode-1.0">S</cbc:ID>
                <cbc:Percent>19.00</cbc:Percent>
                <cac:TaxScheme>
                    <cbc:ID schemeID="1" schemeName="Código de Tipo de Impuesto" schemeAgencyID="195" schemeAgencyName="CO, DIAN" schemeURI="urn:oasis:names:specification:ubl:codelist:gc:TaxSchemeCode-1.0">01</cbc:ID>
                </cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
    </cac:TaxTotal>
    
    <!-- Totales actualizados (LegalMonetaryTotal) -->
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="COP">' . number_format($nota['subtotal'], 2, '.', '') . '</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="COP">' . number_format($nota['subtotal'], 2, '.', '') . '</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="COP">' . number_format($nota['total'], 2, '.', '') . '</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="COP">' . number_format($nota['total'], 2, '.', '') . '</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
    
    <!-- Detalles del ajuste (DebitNoteLine) -->';
        
        foreach ($items as $index => $item) {
            $xml .= '
    <cac:DebitNoteLine>
        <cbc:ID>' . ($index + 1) . '</cbc:ID>
        <cbc:DebitedQuantity unitCode="NIU" unitCodeListID="UN/ECE rec 20" unitCodeListAgencyID="6" unitCodeListAgencyName="United Nations Economic Commission for Europe">' . $item['cantidad'] . '</cbc:DebitedQuantity>
        <cbc:LineExtensionAmount currencyID="COP">' . number_format($item['subtotal'], 2, '.', '') . '</cbc:LineExtensionAmount>
        <cac:Item>
            <cbc:Description>' . htmlspecialchars($item['producto_nombre'] ?? 'Producto') . '</cbc:Description>
            <cac:SellersItemIdentification>
                <cbc:ID>' . htmlspecialchars($item['producto_codigo'] ?? 'PROD' . $item['producto_id']) . '</cbc:ID>
            </cac:SellersItemIdentification>
        </cac:Item>
        <cac:Price>
            <cbc:PriceAmount currencyID="COP">' . number_format($item['precio_unitario'], 2, '.', '') . '</cbc:PriceAmount>
        </cac:Price>
    </cac:DebitNoteLine>';
        }
        
        $xml .= '
    
    <!-- Referencia a resolución DIAN -->
    <cac:DocumentReference>
        <cbc:ID>' . htmlspecialchars($config['prefijo'] ?? 'SETP') . htmlspecialchars($config['resolucion_dian'] ?? '00001') . '</cbc:ID>
        <cbc:DocumentTypeCode>Resolución DIAN</cbc:DocumentTypeCode>
        <cbc:IssueDate>' . date('Y-m-d', strtotime($config['fecha_resolucion'] ?? 'now')) . '</cbc:IssueDate>
    </cac:DocumentReference>
    
    <!-- TestSetId para ambiente de pruebas -->
    ' . ($config['ambiente'] === 'test' && !empty($config['testset_id']) ? '<cbc:TestSetId>' . htmlspecialchars($config['testset_id']) . '</cbc:TestSetId>' : '') . '
    
    <!-- Extensiones UBL (Firma digital y CUDE) -->
    <cac:UBLExtensions>
        <cac:UBLExtension>
            <cbc:ExtensionContent>
                <ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
                    <ds:SignedInfo>
                        <ds:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
                        <ds:SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"/>
                        <ds:Reference URI="">
                            <ds:Transforms>
                                <ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>
                            </ds:Transforms>
                            <ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>
                            <ds:DigestValue></ds:DigestValue>
                        </ds:Reference>
                    </ds:SignedInfo>
                    <ds:SignatureValue></ds:SignatureValue>
                    <ds:KeyInfo>
                        <ds:X509Data>
                            <ds:X509Certificate></ds:X509Certificate>
                        </ds:X509Data>
                    </ds:KeyInfo>
                </ds:Signature>
            </cbc:ExtensionContent>
        </cac:UBLExtension>
        <cac:UBLExtension>
            <cbc:ExtensionContent>
                <cbc:CustomizationID>10</cbc:CustomizationID>
                <cbc:ProfileID>DIAN 2.1: Nota de Débito Electrónica</cbc:ProfileID>
                <cbc:UUID>' . $uuid . '</cbc:UUID>
                <cbc:Hash>' . $cude . '</cbc:Hash>
            </cbc:ExtensionContent>
        </cac:UBLExtension>
    </cac:UBLExtensions>
    
</DebitNote>';
        
        return ['success' => true, 'xml' => $xml, 'cude' => $cude, 'uuid' => $uuid];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error generando XML: ' . $e->getMessage()];
    }
}

/**
 * Generar CUFE para nota de débito
 */
function generarCUFE($nota, $config) {
    // Datos para generar CUFE
    $nit_emisor = $config['nit_empresa'] ?? '900000000';
    $nit_receptor = $nota['cliente_documento'] ?? '900000000';
    $fecha = date('Y-m-d', strtotime($nota['fecha_creacion']));
    $numero_nota = $nota['numero_nota'];
    $total = $nota['total'];
    $iva = $nota['iva'];
    $ambiente = $config['ambiente'] ?? 'test';
    $testset_id = $config['testset_id'] ?? '';
    $clave_tecnica = $config['clave_tecnica'] ?? '';
    
    // Generar CUFE usando algoritmo DIAN (incluyendo clave técnica)
    $datos_cufe = $nit_emisor . $nit_receptor . $fecha . $numero_nota . $total . $iva . $ambiente . $testset_id . $clave_tecnica;
    $cufe = hash('sha256', $datos_cufe);
    
    return strtoupper($cufe);
}

/**
 * Enviar documento a DIAN
 */
function enviarDocumentoDIAN($xml, $cufe, $config_dian) {
    // URLs por defecto si no se proporciona
    $urls_dian = [
        'test' => 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc?wsdl',
        'prod' => 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc?wsdl'
    ];
    
    $ambiente = $config_dian['ambiente'] ?? 'test';
    $url_dian = $config_dian['url_dian'] ?? $urls_dian[$ambiente];
    
    try {
        // Crear cliente SOAP
        $client = new SoapClient($url_dian, [
            'trace' => true,
            'exceptions' => true,
            'connection_timeout' => 30,
            'cache_wsdl' => WSDL_CACHE_NONE
        ]);
        
        // Preparar datos para envío
        $datos_envio = [
            'softwareId' => $config_dian['software_id'],
            'pin' => $config_dian['pin'],
            'document' => base64_encode($xml),
            'cufe' => $cufe
        ];
        
        // Solo agregar testSetId si es ambiente de pruebas
        if ($ambiente === 'test' && !empty($config_dian['testset_id'])) {
            $datos_envio['testSetId'] = $config_dian['testset_id'];
        }
        
        // Enviar a DIAN
        $resultado = $client->SendTestSetAsync($datos_envio);
        
        if (isset($resultado->SendTestSetAsyncResult) && $resultado->SendTestSetAsyncResult->IsValid) {
            return [
                'success' => true,
                'message' => 'Documento enviado exitosamente a DIAN',
                'cufe' => $cufe
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Error al enviar a DIAN: ' . ($resultado->SendTestSetAsyncResult->ErrorMessage ?? 'Error desconocido')
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error de conexión con DIAN: ' . $e->getMessage()
        ];
    }
}
?>

