<?php
/**
 * AJAX para manejo de Notas de Crédito
 */

session_start();
header('Content-Type: application/json');

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
    case 'obtener_motivos':
        obtenerMotivosNotaCredito();
        break;
    case 'obtener_productos_factura':
        obtenerProductosFactura($input['factura_id']);
        break;
    case 'crear_nota_credito':
        crearNotaCredito($input['factura_id'], $input['motivo_codigo'], $input['motivo'], $input['tipo_monto'] ?? 'total', $input);
        break;
    case 'crear_nota_credito_avanzada':
        crearNotaCreditoAvanzada($input);
        break;
    case 'enviar_nota_credito':
        enviarNotaCreditoDIAN($input['nota_id']);
        break;
    case 'obtener_notas_credito':
        obtenerNotasCredito();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}

/**
 * Obtener motivos disponibles para notas de crédito
 */
function obtenerMotivosNotaCredito() {
    global $pdo;
    
    try {
        $sql = "SELECT codigo, descripcion FROM motivos_nota_credito WHERE activo = 1 ORDER BY codigo";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
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
 * Crear una nueva nota de crédito
 */
function crearNotaCredito($factura_id, $motivo_codigo, $motivo, $tipo_monto, $input) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Obtener datos de la factura original
        $sql = "SELECT v.*, c.id as cliente_id, c.nombre as cliente_nombre 
                FROM ventas v 
                LEFT JOIN clientes c ON v.cliente_id = c.id 
                WHERE v.id = ? AND v.estado_fe = 'enviada'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$factura_id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$factura) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Factura no encontrada o no enviada a DIAN']);
            return;
        }
        
        // Generar número de nota de crédito
        $numero_nota = 'NC' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        // Verificar que el número no exista
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notas_credito WHERE numero_nota = ?");
        $stmt->execute([$numero_nota]);
        while ($stmt->fetchColumn() > 0) {
            $numero_nota = 'NC' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
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
        
        // Crear la nota de crédito
        $stmt = $pdo->prepare("INSERT INTO notas_credito 
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
        
        // Registrar en log
        $mensaje_log = "Nota de crédito {$tipo_monto} creada: {$motivo_codigo} - {$motivo}";
        if ($tipo_monto === 'parcial') {
            $mensaje_log .= " (Monto: $" . number_format($total, 2) . ")";
        }
        $stmt = $pdo->prepare("INSERT INTO fe_log (venta_id, tipo_documento, numero_documento, estado, mensaje, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$factura_id, 'nota_credito', $numero_nota, 'pendiente', $mensaje_log, $_SESSION['user_id']]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "Nota de crédito {$numero_nota} creada exitosamente",
            'nota_id' => $nota_id,
            'numero_nota' => $numero_nota
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Enviar nota de crédito a DIAN
 */
function enviarNotaCreditoDIAN($nota_id) {
    global $pdo;
    
    try {
        // Obtener datos de la nota de crédito
        $sql = "SELECT nc.*, v.numero_factura, c.nombre as cliente_nombre, c.numero_documento as cliente_documento
                FROM notas_credito nc
                LEFT JOIN ventas v ON nc.factura_id = v.id
                LEFT JOIN clientes c ON nc.cliente_id = c.id
                WHERE nc.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nota_id]);
        $nota = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$nota) {
            echo json_encode(['success' => false, 'message' => 'Nota de crédito no encontrada']);
            return;
        }
        
        if ($nota['estado_fe'] === 'enviada') {
            echo json_encode(['success' => false, 'message' => 'La nota de crédito ya fue enviada a DIAN']);
            return;
        }
        
        // Obtener configuración de DIAN del ambiente seleccionado
        require_once 'funciones.php';
        $config_dian = obtenerConfiguracionFE($_SESSION['user_id']);
        
        if (!$config_dian || empty($config_dian['software_id'])) {
            echo json_encode(['success' => false, 'message' => 'Configuración de DIAN no encontrada']);
            return;
        }
        
        // Generar XML de la nota de crédito
        $xml_nota = generarXMLNotaCredito($nota, $config_dian);
        
        // Generar CUFE real
        $cufe = generarCUFE($nota, $config_dian);
        
        // Enviar a DIAN (usar la misma lógica que las facturas)
        $resultado_dian = enviarDocumentoDIAN($xml_nota, $cufe, $config_dian);
        
        if ($resultado_dian['success']) {
            $estado_dian = 'aceptada';
            $mensaje_dian = 'Nota de crédito aceptada por DIAN';
        } else {
            $estado_dian = 'rechazada';
            $mensaje_dian = 'Nota de crédito rechazada por DIAN: ' . $resultado_dian['message'];
        }
        
        // Actualizar estado
        $stmt = $pdo->prepare("UPDATE notas_credito SET estado_fe = ? WHERE id = ?");
        $stmt->execute([$estado_dian, $nota_id]);
        
        // Registrar en log con CUFE
        $stmt = $pdo->prepare("INSERT INTO fe_log (venta_id, tipo_documento, numero_documento, estado, mensaje, cufe, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nota['factura_id'], 'nota_credito', $nota['numero_nota'], 'aceptado', $mensaje_dian, $cufe, $_SESSION['user_id']]);
        
        echo json_encode([
            'success' => true, 
            'message' => "Nota de crédito {$nota['numero_nota']} enviada exitosamente a DIAN",
            'cufe' => $cufe
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Obtener todas las notas de crédito
 */
function obtenerNotasCredito() {
    global $pdo;
    
    try {
        $sql = "SELECT nc.*, v.numero_factura, c.nombre as cliente_nombre
                FROM notas_credito nc
                LEFT JOIN ventas v ON nc.factura_id = v.id
                LEFT JOIN clientes c ON nc.cliente_id = c.id
                ORDER BY nc.fecha_creacion DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'notas' => $notas,
            'total' => count($notas)
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
 * Crear nota de crédito avanzada (por toda la factura o por productos individuales)
 */
function crearNotaCreditoAvanzada($input) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $factura_id = $input['factura_id'];
        $tipo = $input['tipo']; // 'total' o 'productos'
        
        // Obtener datos de la factura original
        $sql = "SELECT v.*, c.id as cliente_id, c.nombre as cliente_nombre 
                FROM ventas v 
                LEFT JOIN clientes c ON v.cliente_id = c.id 
                WHERE v.id = ? AND v.estado_fe = 'enviada'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$factura_id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$factura) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Factura no encontrada o no enviada a DIAN']);
            return;
        }
        
        // Generar número de nota de crédito
        $numero_nota = 'NC' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        // Verificar que el número no exista
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notas_credito WHERE numero_nota = ?");
        $stmt->execute([$numero_nota]);
        while ($stmt->fetchColumn() > 0) {
            $numero_nota = 'NC' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $stmt->execute([$numero_nota]);
        }
        
        if ($tipo === 'total') {
            // Nota de crédito por toda la factura
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
            
            // Crear la nota de crédito
            $stmt = $pdo->prepare("INSERT INTO notas_credito 
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
            
            // Crear items de la nota de crédito (todos los productos de la factura)
            $sql_items = "SELECT * FROM venta_items WHERE venta_id = ?";
            $stmt_items = $pdo->prepare($sql_items);
            $stmt_items->execute([$factura_id]);
            $items_factura = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($items_factura as $item) {
                $stmt_item = $pdo->prepare("INSERT INTO nota_credito_items 
                    (nota_credito_id, producto_id, cantidad, precio_unitario, subtotal, iva, total, motivo_codigo, motivo, tipo_monto) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_item->execute([
                    $nota_id,
                    $item['producto_id'],
                    $item['cantidad'],
                    $item['precio_unitario'],
                    $item['subtotal'],
                    $item['iva'],
                    $item['total'],
                    $motivo_codigo,
                    $motivo,
                    $tipo_monto
                ]);
            }
            
        } else {
            // Nota de crédito por productos individuales
            $productos = $input['productos'];
            
            // Calcular totales
            $subtotal_total = 0;
            $iva_total = 0;
            $total_total = 0;
            
            foreach ($productos as $producto) {
                // Obtener datos del producto de la factura
                $sql_item = "SELECT * FROM venta_items WHERE venta_id = ? AND producto_id = ?";
                $stmt_item = $pdo->prepare($sql_item);
                $stmt_item->execute([$factura_id, $producto['producto_id']]);
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
            
            // Crear la nota de crédito
            $stmt = $pdo->prepare("INSERT INTO notas_credito 
                (numero_nota, factura_id, cliente_id, motivo, motivo_codigo, tipo_monto, 
                 subtotal, iva, total, usuario_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $numero_nota,
                $factura_id,
                $factura['cliente_id'],
                'Nota de crédito por productos individuales',
                'MULTI',
                'total',
                $subtotal_total ?: 0,
                $iva_total ?: 0,
                $total_total ?: 0,
                $_SESSION['user_id']
            ]);
            
            $nota_id = $pdo->lastInsertId();
            
            // Crear items de la nota de crédito (solo los productos seleccionados)
            foreach ($productos as $producto) {
                // Obtener datos del producto de la factura
                $sql_item = "SELECT * FROM venta_items WHERE venta_id = ? AND producto_id = ?";
                $stmt_item = $pdo->prepare($sql_item);
                $stmt_item->execute([$factura_id, $producto['producto_id']]);
                $item_factura = $stmt_item->fetch(PDO::FETCH_ASSOC);
                
                if ($item_factura) {
                    // Asegurar que los valores no sean NULL
                    $subtotal = $item_factura['subtotal'] ?? 0;
                    $iva = $item_factura['iva'] ?? 0;
                    $total = $item_factura['total'] ?? 0;
                    
                    $stmt_item_nc = $pdo->prepare("INSERT INTO nota_credito_items 
                        (nota_credito_id, producto_id, cantidad, precio_unitario, subtotal, iva, total, motivo_codigo, motivo, tipo_monto) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt_item_nc->execute([
                        $nota_id,
                        $producto['producto_id'],
                        $item_factura['cantidad'],
                        $item_factura['precio_unitario'],
                        $subtotal,
                        $iva,
                        $total,
                        $producto['motivo_codigo'],
                        $producto['motivo'],
                        $producto['tipo_monto']
                    ]);
                }
            }
        }
        
        // Registrar en log
        $mensaje_log = "Nota de crédito {$tipo} creada: {$numero_nota}";
        if ($tipo === 'total') {
            $mensaje_log .= " - {$input['motivo_codigo']} - {$input['motivo']}";
        } else {
            $mensaje_log .= " - " . count($productos) . " producto(s)";
        }
        
        $stmt = $pdo->prepare("INSERT INTO fe_log (venta_id, tipo_documento, numero_documento, estado, mensaje, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$factura_id, 'nota_credito', $numero_nota, 'pendiente', $mensaje_log, $_SESSION['user_id']]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "Nota de crédito {$numero_nota} creada exitosamente",
            'nota_id' => $nota_id,
            'numero_nota' => $numero_nota
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
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
            'document' => $xml,
            'testSetId' => $config_dian['testset_id'] ?? ''
        ];
        
        // Enviar documento
        $resultado = $client->SendBillSync($datos_envio);
        
        return [
            'success' => true,
            'message' => 'Documento enviado exitosamente a DIAN',
            'dian_response' => $resultado,
            'cufe' => $cufe,
            'url_dian' => $url_dian
        ];
        
    } catch (SoapFault $e) {
        return [
            'success' => false,
            'message' => 'Error SOAP: ' . $e->getMessage(),
            'url_dian' => $url_dian
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error de conexión: ' . $e->getMessage(),
            'url_dian' => $url_dian
        ];
    }
}

/**
 * Generar CUFE para nota de crédito
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
 * Generar XML para nota de crédito según estándares DIAN UBL 2.1
 */
function generarXMLNotaCredito($nota, $config) {
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
        
        // Obtener items de la nota de crédito
        $sql_items = "SELECT nci.*, p.nombre as producto_nombre, p.referencia as producto_codigo
                      FROM nota_credito_items nci 
                      LEFT JOIN productos p ON nci.producto_id = p.id 
                      WHERE nci.nota_credito_id = ?";
        $stmt_items = $pdo->prepare($sql_items);
        $stmt_items->execute([$nota['id']]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($items)) {
            return ['success' => false, 'message' => 'La nota de crédito debe tener al menos un item'];
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
        
        // Construir XML completo según estándares DIAN UBL 2.1 para Nota de Crédito
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<CreditNote xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2" 
            xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
            xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
            xmlns:ccts="urn:un:unece:uncefact:documentation:2"
            xmlns:qdt="urn:oasis:names:specification:ubl:schema:xsd:QualifiedDatatypes-2"
            xmlns:udt="urn:un:unece:uncefact:data:specification:UnqualifiedDataTypesSchemaModule:2">
    
    <!-- Encabezado del documento (CreditNote) -->
    <cbc:ID>' . htmlspecialchars($nota['numero_nota']) . '</cbc:ID>
    <cbc:UUID>' . $uuid . '</cbc:UUID>
    <cbc:IssueDate>' . date('Y-m-d', strtotime($nota['fecha_creacion'])) . '</cbc:IssueDate>
    <cbc:IssueTime>' . date('H:i:s', strtotime($nota['fecha_creacion'])) . '</cbc:IssueTime>
    <cbc:CreditNoteTypeCode listID="1" listAgencyID="195" listAgencyName="CO, DIAN" listName="Tipo de Documento" listURI="urn:oasis:names:specification:ubl:codelist:gc:CreditNoteTypeCode-1.0">91</cbc:CreditNoteTypeCode>
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
    
    <!-- Motivo de la nota y factura referenciada (DiscrepancyResponse) -->
    <cac:DiscrepancyResponse>
        <cbc:ReferenceID>' . htmlspecialchars($factura_original['numero_factura']) . '</cbc:ReferenceID>
        <cbc:ResponseCode listID="1" listAgencyID="195" listAgencyName="CO, DIAN" listName="Código de Respuesta" listURI="urn:oasis:names:specification:ubl:codelist:gc:ResponseCode-1.0">1</cbc:ResponseCode>
        <cbc:Description>' . htmlspecialchars($nota['motivo'] ?? 'Corrección de factura') . '</cbc:Description>
    </cac:DiscrepancyResponse>
    
    <!-- ID de la factura a la que aplica (BillingReference) -->
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
    
    <!-- Totales después del ajuste (LegalMonetaryTotal) -->
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="COP">' . number_format($nota['subtotal'], 2, '.', '') . '</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="COP">' . number_format($nota['subtotal'], 2, '.', '') . '</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="COP">' . number_format($nota['total'], 2, '.', '') . '</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="COP">' . number_format($nota['total'], 2, '.', '') . '</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
    
    <!-- Detalle de los valores corregidos (CreditNoteLine) -->';
        
        foreach ($items as $index => $item) {
            $xml .= '
    <cac:CreditNoteLine>
        <cbc:ID>' . ($index + 1) . '</cbc:ID>
        <cbc:CreditedQuantity unitCode="NIU" unitCodeListID="UN/ECE rec 20" unitCodeListAgencyID="6" unitCodeListAgencyName="United Nations Economic Commission for Europe">' . $item['cantidad'] . '</cbc:CreditedQuantity>
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
    </cac:CreditNoteLine>';
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
                <cbc:ProfileID>DIAN 2.1: Nota de Crédito Electrónica</cbc:ProfileID>
                <cbc:UUID>' . $uuid . '</cbc:UUID>
                <cbc:Hash>' . $cude . '</cbc:Hash>
            </cbc:ExtensionContent>
        </cac:UBLExtension>
    </cac:UBLExtensions>
    
</CreditNote>';
        
        return ['success' => true, 'xml' => $xml, 'cude' => $cude, 'uuid' => $uuid];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error generando XML: ' . $e->getMessage()];
    }
}
?>

