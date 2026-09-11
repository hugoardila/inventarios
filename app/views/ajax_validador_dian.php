<?php
/**
 * AJAX para el Validador DIAN
 * Maneja las validaciones de facturas, notas de crédito y notas de débito
 */

session_start();
require_once '../config/config.php';
require_once 'funciones.php';
require_once '../validador_dian.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Obtener acción
$action = $_POST['action'] ?? '';

try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener configuración FE
    $config = obtenerConfiguracionFE($_SESSION['user_id']);
    
    if (!$config) {
        echo json_encode(['success' => false, 'message' => 'Configuración de FE no encontrada']);
        exit;
    }
    
    $validador = new ValidadorDIAN();
    
    switch ($action) {
        case 'validar_facturas':
            validarFacturas($pdo, $validador, $config);
            break;
            
        case 'validar_notas_credito':
            validarNotasCredito($pdo, $validador, $config);
            break;
            
        case 'validar_notas_debito':
            validarNotasDebito($pdo, $validador, $config);
            break;
            
        case 'ver_xml_factura':
            verXMLFactura($pdo, $validador, $config);
            break;
            
        case 'ver_xml_nota_credito':
            verXMLNotaCredito($pdo, $validador, $config);
            break;
            
        case 'ver_xml_nota_debito':
            verXMLNotaDebito($pdo, $validador, $config);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

/**
 * Validar todas las facturas
 */
function validarFacturas($pdo, $validador, $config) {
    try {
        // Obtener facturas pendientes
        $sql = "SELECT v.*, c.nombre as cliente_nombre, c.numero_documento
                FROM ventas v 
                LEFT JOIN clientes c ON v.cliente_id = c.id 
                WHERE v.estado_fe IN ('pendiente', 'rechazada')
                ORDER BY v.creado_en DESC
                LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $documentos_validos = [];
        $errores_totales = [];
        $advertencias_totales = [];
        $validaciones_detalladas_totales = [];
        $total_errores = 0;
        $total_advertencias = 0;
        
        foreach ($facturas as $factura) {
            $resultado = $validador->validarFacturaElectronica($factura['id'], $config);
            
            // Obtener validaciones detalladas
            $validaciones_detalladas = $validador->obtenerValidacionesDetalladas();
            
            // Obtener items de la factura para mostrar detalles
            $sql_items = "SELECT vi.*, p.nombre as producto_nombre 
                         FROM venta_items vi 
                         LEFT JOIN productos p ON vi.producto_id = p.id 
                         WHERE vi.venta_id = ?";
            $stmt_items = $pdo->prepare($sql_items);
            $stmt_items->execute([$factura['id']]);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            
            if ($resultado['success']) {
                $documentos_validos[] = [
                    'numero_factura' => $factura['numero_factura'],
                    'cliente_nombre' => $factura['cliente_nombre'],
                    'total' => $factura['total'],
                    'fecha_venta' => $factura['fecha_venta'],
                    'items_count' => count($items),
                    'items' => $items,
                    'estado' => '✅ Válida',
                    'validaciones_detalladas' => $validaciones_detalladas
                ];
            } else {
                $documentos_validos[] = [
                    'numero_factura' => $factura['numero_factura'],
                    'cliente_nombre' => $factura['cliente_nombre'],
                    'total' => $factura['total'],
                    'fecha_venta' => $factura['fecha_venta'],
                    'items_count' => count($items),
                    'items' => $items,
                    'estado' => '❌ Con errores',
                    'errores_documento' => $resultado['errores'],
                    'validaciones_detalladas' => $validaciones_detalladas
                ];
                $errores_totales = array_merge($errores_totales, $resultado['errores']);
                $total_errores += $resultado['total_errores'];
            }
            
            $advertencias_totales = array_merge($advertencias_totales, $resultado['advertencias']);
            $total_advertencias += $resultado['total_advertencias'];
        }
        
        $success = empty($errores_totales);
        
        echo json_encode([
            'success' => $success,
            'total_documentos' => count($facturas),
            'documentos_validos' => $documentos_validos,
            'total_validos' => count($documentos_validos),
            'errores' => array_unique($errores_totales),
            'advertencias' => array_unique($advertencias_totales),
            'total_errores' => $total_errores,
            'total_advertencias' => $total_advertencias,
            'message' => $success ? 
                'Todas las facturas cumplen con los estándares DIAN' : 
                'Se encontraron errores en la validación de facturas'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error validando facturas: ' . $e->getMessage()]);
    }
}

/**
 * Validar todas las notas de crédito
 */
function validarNotasCredito($pdo, $validador, $config) {
    try {
        // Obtener notas de crédito pendientes
        $sql = "SELECT nc.*, c.nombre as cliente_nombre, c.numero_documento
                FROM notas_credito nc 
                LEFT JOIN clientes c ON nc.cliente_id = c.id 
                WHERE nc.estado_fe IN ('pendiente', 'rechazada')
                ORDER BY nc.fecha_creacion DESC
                LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $documentos_validos = [];
        $errores_totales = [];
        $advertencias_totales = [];
        $total_errores = 0;
        $total_advertencias = 0;
        
        foreach ($notas as $nota) {
            $resultado = $validador->validarNotaCredito($nota['id'], $config);
            
            // Obtener items de la nota de crédito para mostrar detalles
            $sql_items = "SELECT nci.*, p.nombre as producto_nombre 
                         FROM nota_credito_items nci 
                         LEFT JOIN productos p ON nci.producto_id = p.id 
                         WHERE nci.nota_credito_id = ?";
            $stmt_items = $pdo->prepare($sql_items);
            $stmt_items->execute([$nota['id']]);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            
            if ($resultado['success']) {
                $documentos_validos[] = [
                    'numero_nota' => $nota['numero_nota'],
                    'cliente_nombre' => $nota['cliente_nombre'],
                    'total' => $nota['total'],
                    'fecha_creacion' => $nota['fecha_creacion'],
                    'items_count' => count($items),
                    'items' => $items,
                    'estado' => '✅ Válida'
                ];
            } else {
                $documentos_validos[] = [
                    'numero_nota' => $nota['numero_nota'],
                    'cliente_nombre' => $nota['cliente_nombre'],
                    'total' => $nota['total'],
                    'fecha_creacion' => $nota['fecha_creacion'],
                    'items_count' => count($items),
                    'items' => $items,
                    'estado' => '❌ Con errores',
                    'errores_documento' => $resultado['errores']
                ];
                $errores_totales = array_merge($errores_totales, $resultado['errores']);
                $total_errores += $resultado['total_errores'];
            }
            
            $advertencias_totales = array_merge($advertencias_totales, $resultado['advertencias']);
            $total_advertencias += $resultado['total_advertencias'];
        }
        
        $success = empty($errores_totales);
        
        echo json_encode([
            'success' => $success,
            'total_documentos' => count($notas),
            'documentos_validos' => $documentos_validos,
            'total_validos' => count($documentos_validos),
            'errores' => array_unique($errores_totales),
            'advertencias' => array_unique($advertencias_totales),
            'total_errores' => $total_errores,
            'total_advertencias' => $total_advertencias,
            'message' => $success ? 
                'Todas las notas de crédito cumplen con los estándares DIAN' : 
                'Se encontraron errores en la validación de notas de crédito'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error validando notas de crédito: ' . $e->getMessage()]);
    }
}

/**
 * Validar todas las notas de débito
 */
function validarNotasDebito($pdo, $validador, $config) {
    try {
        // Obtener notas de débito pendientes
        $sql = "SELECT nd.*, c.nombre as cliente_nombre, c.numero_documento
                FROM notas_debito nd 
                LEFT JOIN clientes c ON nd.cliente_id = c.id 
                WHERE nd.estado IN ('pendiente', 'rechazada')
                ORDER BY nd.creado_en DESC
                LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $documentos_validos = [];
        $errores_totales = [];
        $advertencias_totales = [];
        $total_errores = 0;
        $total_advertencias = 0;
        
        foreach ($notas as $nota) {
            $resultado = $validador->validarNotaDebito($nota['id'], $config);
            
            // Obtener items de la nota de débito para mostrar detalles
            $sql_items = "SELECT ndi.*, p.nombre as producto_nombre 
                         FROM nota_debito_items ndi 
                         LEFT JOIN productos p ON ndi.producto_id = p.id 
                         WHERE ndi.nota_debito_id = ?";
            $stmt_items = $pdo->prepare($sql_items);
            $stmt_items->execute([$nota['id']]);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            
            if ($resultado['success']) {
                $documentos_validos[] = [
                    'numero_nota' => $nota['numero_nota'],
                    'cliente_nombre' => $nota['cliente_nombre'],
                    'total' => $nota['total'],
                    'creado_en' => $nota['creado_en'],
                    'items_count' => count($items),
                    'items' => $items,
                    'estado' => '✅ Válida'
                ];
            } else {
                $documentos_validos[] = [
                    'numero_nota' => $nota['numero_nota'],
                    'cliente_nombre' => $nota['cliente_nombre'],
                    'total' => $nota['total'],
                    'creado_en' => $nota['creado_en'],
                    'items_count' => count($items),
                    'items' => $items,
                    'estado' => '❌ Con errores',
                    'errores_documento' => $resultado['errores']
                ];
                $errores_totales = array_merge($errores_totales, $resultado['errores']);
                $total_errores += $resultado['total_errores'];
            }
            
            $advertencias_totales = array_merge($advertencias_totales, $resultado['advertencias']);
            $total_advertencias += $resultado['total_advertencias'];
        }
        
        $success = empty($errores_totales);
        
        echo json_encode([
            'success' => $success,
            'total_documentos' => count($notas),
            'documentos_validos' => $documentos_validos,
            'total_validos' => count($documentos_validos),
            'errores' => array_unique($errores_totales),
            'advertencias' => array_unique($advertencias_totales),
            'total_errores' => $total_errores,
            'total_advertencias' => $total_advertencias,
            'message' => $success ? 
                'Todas las notas de débito cumplen con los estándares DIAN' : 
                'Se encontraron errores en la validación de notas de débito'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error validando notas de débito: ' . $e->getMessage()]);
    }
}

/**
 * Ver XML de factura
 */
function verXMLFactura($pdo, $validador, $config) {
    $factura_id = $_POST['factura_id'] ?? null;
    
    if (!$factura_id) {
        echo json_encode(['success' => false, 'message' => 'ID de factura requerido']);
        return;
    }
    
    try {
        require_once '../funciones_xml_puras.php';
        $xml_result = generarXMLFacturaPuro($factura_id);
        
        if ($xml_result['success']) {
            // Obtener número de factura
            $sql = "SELECT numero_factura FROM ventas WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$factura_id]);
            $numero_factura = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'xml' => $xml_result['xml'],
                'cufe' => $xml_result['cufe'],
                'uuid' => $xml_result['uuid'],
                'numero_factura' => $numero_factura ?: 'N/A'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => $xml_result['message']]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error generando XML: ' . $e->getMessage()]);
    }
}

/**
 * Ver XML de nota de crédito
 */
function verXMLNotaCredito($pdo, $validador, $config) {
    $nota_id = $_POST['nota_id'] ?? null;
    
    if (!$nota_id) {
        echo json_encode(['success' => false, 'message' => 'ID de nota de crédito requerido']);
        return;
    }
    
    try {
        // Obtener datos de la nota de crédito
        $sql = "SELECT nc.*, c.nombre as cliente_nombre, c.numero_documento 
                FROM notas_credito nc 
                LEFT JOIN clientes c ON nc.cliente_id = c.id 
                WHERE nc.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nota_id]);
        $nota = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$nota) {
            echo json_encode(['success' => false, 'message' => 'Nota de crédito no encontrada']);
            return;
        }
        
        require_once '../views/ajax_notas_credito.php';
        $xml_result = generarXMLNotaCredito($nota, $config);
        
        if ($xml_result['success']) {
            echo json_encode([
                'success' => true,
                'xml' => $xml_result['xml'],
                'cude' => $xml_result['cude'],
                'uuid' => $xml_result['uuid'],
                'numero_nota' => $nota['numero_nota']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => $xml_result['message']]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error generando XML: ' . $e->getMessage()]);
    }
}

/**
 * Ver XML de nota de débito
 */
function verXMLNotaDebito($pdo, $validador, $config) {
    $nota_id = $_POST['nota_id'] ?? null;
    
    if (!$nota_id) {
        echo json_encode(['success' => false, 'message' => 'ID de nota de débito requerido']);
        return;
    }
    
    try {
        // Obtener datos de la nota de débito
        $sql = "SELECT nd.*, c.nombre as cliente_nombre, c.numero_documento 
                FROM notas_debito nd 
                LEFT JOIN clientes c ON nd.cliente_id = c.id 
                WHERE nd.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nota_id]);
        $nota = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$nota) {
            echo json_encode(['success' => false, 'message' => 'Nota de débito no encontrada']);
            return;
        }
        
        require_once '../views/ajax_notas_debito.php';
        $xml_result = generarXMLNotaDebito($nota, $config);
        
        if ($xml_result['success']) {
            echo json_encode([
                'success' => true,
                'xml' => $xml_result['xml'],
                'cude' => $xml_result['cude'],
                'uuid' => $xml_result['uuid'],
                'numero_nota' => $nota['numero_nota']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => $xml_result['message']]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error generando XML: ' . $e->getMessage()]);
    }
}
?>
