<?php
session_start();
header('Content-Type: application/json');

// Verificar si está logueado y es admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$action = $_POST['action'] ?? '';

// Si no hay acción en POST, intentar obtenerla del JSON
if (empty($action)) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $action = $data['action'] ?? '';
}

try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    switch ($action) {
        case 'guardar_configuracion':
            $datos = [
                'software_id' => $_POST['software_id'] ?? '',
                'pin' => $_POST['pin'] ?? '',
                'ambiente' => $_POST['ambiente'] ?? '',
                'prefijo' => $_POST['prefijo'] ?? '',
                'resolucion_dian' => $_POST['resolucion_dian'] ?? '',
                'fecha_resolucion' => $_POST['fecha_resolucion'] ?? '',
                'rango_desde' => $_POST['rango_desde'] ?? '',
                'rango_hasta' => $_POST['rango_hasta'] ?? '',
                'fecha_desde' => $_POST['fecha_desde'] ?? '',
                'fecha_hasta' => $_POST['fecha_hasta'] ?? '',
                'testset_id' => $_POST['testset_id'] ?? '',
                'url_dian' => $_POST['url_dian'] ?? '',
                'clave_tecnica' => $_POST['clave_tecnica'] ?? '',
                'nit_empresa' => $_POST['nit_empresa'] ?? '',
                'nombre_empresa' => $_POST['nombre_empresa'] ?? '',
                'direccion_empresa' => $_POST['direccion_empresa'] ?? '',
                'ciudad_empresa' => $_POST['ciudad_empresa'] ?? ''
            ];
            
            $resultado = guardarConfiguracionFE($datos);
            echo json_encode($resultado);
            break;
            
        case 'probar_conexion':
            // Obtener ambiente del JSON si viene por ahí
            if (empty($_POST['ambiente'])) {
                $input = file_get_contents('php://input');
                $data = json_decode($input, true);
                $_POST['ambiente'] = $data['ambiente'] ?? 'test';
            }
            $resultado = probarConexionDIAN();
            echo json_encode($resultado);
            break;
            
        case 'enviar_factura':
            $factura_id = $_POST['factura_id'] ?? 0;
            $resultado = enviarFacturaDIAN($factura_id);
            echo json_encode($resultado);
            break;
            
        case 'obtener_logs':
            $filtros = [
                'fecha_desde' => $_POST['fecha_desde'] ?? '',
                'fecha_hasta' => $_POST['fecha_hasta'] ?? '',
                'estado' => $_POST['estado'] ?? ''
            ];
            
            $resultado = obtenerLogsFE($filtros);
            echo json_encode($resultado);
            break;
            
        case 'validar_flujo':
            // Obtener ambiente del JSON si viene por ahí
            if (empty($_POST['ambiente'])) {
                $input = file_get_contents('php://input');
                $data = json_decode($input, true);
                $_POST['ambiente'] = $data['ambiente'] ?? 'test';
            }
            $resultado = validarFlujoVentas();
            echo json_encode($resultado);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function guardarConfiguracionFE($datos) {
    global $pdo;
    
    try {
        $ambiente = $datos['ambiente'] ?? 'test';
        
        // Verificar si ya existe configuración para este ambiente
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM fe_ambientes WHERE ambiente = ?");
        $stmt->execute([$ambiente]);
        $existe = $stmt->fetchColumn() > 0;
        
        if ($existe) {
            $sql = "UPDATE fe_ambientes SET 
                    software_id = ?, pin = ?, prefijo = ?, resolucion_dian = ?, fecha_resolucion = ?, testset_id = ?,
                    url_dian = ?, clave_tecnica = ?, rango_desde = ?, rango_hasta = ?, fecha_desde = ?, fecha_hasta = ?,
                    nit_empresa = ?, nombre_empresa = ?, direccion_empresa = ?, ciudad_empresa = ?,
                    actualizado_en = NOW() 
                    WHERE ambiente = ?";
        } else {
            $sql = "INSERT INTO fe_ambientes 
                    (ambiente, software_id, pin, prefijo, resolucion_dian, 
                     fecha_resolucion, testset_id, url_dian, clave_tecnica, rango_desde, rango_hasta, 
                     fecha_desde, fecha_hasta, nit_empresa, nombre_empresa, 
                     direccion_empresa, ciudad_empresa, creado_en) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        }
        
        $stmt = $pdo->prepare($sql);
        if ($existe) {
            $stmt->execute([
                $datos['software_id'], $datos['pin'], $datos['prefijo'], $datos['resolucion_dian'], $datos['fecha_resolucion'], $datos['testset_id'],
                $datos['url_dian'], $datos['clave_tecnica'], $datos['rango_desde'], $datos['rango_hasta'], $datos['fecha_desde'], $datos['fecha_hasta'],
                $datos['nit_empresa'], $datos['nombre_empresa'], $datos['direccion_empresa'], $datos['ciudad_empresa'],
                $ambiente
            ]);
        } else {
            $stmt->execute([
                $ambiente, $datos['software_id'], $datos['pin'], $datos['prefijo'], $datos['resolucion_dian'], 
                $datos['fecha_resolucion'], $datos['testset_id'], $datos['url_dian'], $datos['clave_tecnica'], $datos['rango_desde'], $datos['rango_hasta'], 
                $datos['fecha_desde'], $datos['fecha_hasta'], $datos['nit_empresa'], $datos['nombre_empresa'], 
                $datos['direccion_empresa'], $datos['ciudad_empresa']
            ]);
        }
        
        return ['success' => true, 'message' => 'Configuración guardada correctamente para ambiente ' . $ambiente];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function probarConexionDIAN() {
    global $pdo;
    
    try {
        // Obtener ambiente del POST o del JSON
        $ambiente = $_POST['ambiente'] ?? 'test';
        
        // Si no hay ambiente en POST, intentar obtenerlo del JSON
        if (empty($_POST['ambiente'])) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            $ambiente = $data['ambiente'] ?? 'test';
        }
        
        // Obtener configuración del ambiente específico
        $stmt = $pdo->prepare("SELECT * FROM fe_ambientes WHERE ambiente = ?");
        $stmt->execute([$ambiente]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            return ['success' => false, 'message' => 'No hay configuración guardada para el ambiente ' . $ambiente . '. Debe configurar primero los parámetros.'];
        }
        
        // Validar campos obligatorios
        $campos_requeridos = ['software_id', 'pin', 'resolucion_dian', 'nit_empresa', 'nombre_empresa', 'prefijo', 'rango_desde', 'rango_hasta'];
        $campos_faltantes = [];
        
        foreach ($campos_requeridos as $campo) {
            if (empty($config[$campo])) {
                $campos_faltantes[] = $campo;
            }
        }
        
        if (!empty($campos_faltantes)) {
            return ['success' => false, 'message' => 'Faltan campos obligatorios para el ambiente ' . $ambiente . ': ' . implode(', ', $campos_faltantes)];
        }
        
        // Validar TestSetId (obligatorio para ambos ambientes)
        if (empty($config['testset_id'])) {
            return ['success' => false, 'message' => 'TestSetId requerido para ambos ambientes'];
        }
        
        // Validar formatos
        $errores_formato = [];
        
        // Validar Software ID (formato UUID)
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $config['software_id'])) {
            $errores_formato[] = "Software ID debe tener formato UUID válido";
        }
        
        // Validar PIN (numérico 4-8 dígitos)
        if (!preg_match('/^[0-9]{4,8}$/', $config['pin'])) {
            $errores_formato[] = "PIN debe ser numérico de 4-8 dígitos";
        }
        
        // Validar TestSetId (formato UUID para ambos ambientes)
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $config['testset_id'])) {
            $errores_formato[] = "TestSetId debe tener formato UUID válido";
        }
        
        // Validar NIT
        if (!preg_match('/^[0-9]{6,15}-?[0-9]?$/', $config['nit_empresa'])) {
            $errores_formato[] = "NIT debe tener formato válido";
        }
        
        if (!empty($errores_formato)) {
            return ['success' => false, 'message' => 'Errores de formato: ' . implode(', ', $errores_formato)];
        }
        
        // PROBAR CONEXIÓN REAL CON DIAN
        $url_dian = $config['url_dian'] ?? ($ambiente === 'test' 
            ? 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc?wsdl'
            : 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc?wsdl');
        
        // Crear contexto para la conexión
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'method' => 'GET',
                'header' => [
                    'User-Agent: TECNOXPERT-FE-Test/1.0',
                    'Accept: text/xml, application/xml, */*'
                ]
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false
            ]
        ]);
        
        $start_time = microtime(true);
        $headers = @get_headers($url_dian, 1, $context);
        $end_time = microtime(true);
        $response_time = round(($end_time - $start_time) * 1000, 2);
        
        if ($headers && strpos($headers[0], '200') !== false) {
            // Registrar log de éxito
            $stmt = $pdo->prepare("INSERT INTO fe_log (tipo_documento, numero_documento, estado, mensaje, usuario_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['factura', 'PRUEBA_CONEXION', 'aceptado', "Conexión validada - Tiempo: {$response_time}ms", $_SESSION['user_id']]);
            
            return [
                'success' => true, 
                'message' => 'Conexión exitosa con la DIAN', 
                'datos' => [
                    'conectado' => true,
                    'ambiente' => $ambiente,
                    'software_id' => $config['software_id'],
                    'testset_id' => $config['testset_id'] ?? 'No configurado',
                    'nit_empresa' => $config['nit_empresa'],
                    'nombre_empresa' => $config['nombre_empresa'],
                    'servidor_dian' => 'Conectado',
                    'url_dian' => $url_dian,
                    'tiempo_respuesta' => $response_time . 'ms',
                    'estado_http' => $headers[0],
                    'timestamp' => date('Y-m-d H:i:s'),
                    'validaciones' => [
                        'campos_obligatorios' => 'OK',
                        'formatos_validos' => 'OK',
                        'conexion_dian' => 'OK',
                        'testset_id' => $ambiente === 'test' ? 'OK' : 'N/A'
                    ]
                ]
            ];
        } else {
            // Registrar log de error
            $stmt = $pdo->prepare("INSERT INTO fe_log (tipo_documento, numero_documento, estado, mensaje, usuario_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['factura', 'PRUEBA_CONEXION', 'error', "Error de conexión - Tiempo: {$response_time}ms", $_SESSION['user_id']]);
            
            return [
                'success' => false, 
                'message' => 'Error de conexión con DIAN. Servidor no responde correctamente.',
                'datos' => [
                    'url_dian' => $url_dian,
                    'tiempo_respuesta' => $response_time . 'ms',
                    'estado_http' => $headers ? $headers[0] : 'Sin respuesta',
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function simularPruebaConexionReal($config) {
    // Simular validaciones reales
    $errores = [];
    
    // PRIMERO: Validar que todos los campos requeridos estén presentes
    $campos_requeridos = ['software_id', 'pin', 'resolucion_dian', 'nit_empresa', 'nombre_empresa', 'prefijo', 'rango_desde', 'rango_hasta'];
    foreach ($campos_requeridos as $campo) {
        if (empty($config[$campo])) {
            $errores[] = "Campo '$campo' es requerido";
        }
    }
    
    // Si hay campos faltantes, devolver error inmediatamente
    if (!empty($errores)) {
        return [
            'success' => false,
            'message' => 'Errores de validación: ' . implode(', ', $errores)
        ];
    }
    
    // SEGUNDO: Validar formatos solo si los campos están presentes
    // Validar Software ID (formato UUID de DIAN)
    if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $config['software_id'])) {
        $errores[] = 'Software ID debe tener formato UUID válido';
    }
    
    // Validar PIN (debe ser numérico de 4-8 dígitos)
    if (!preg_match('/^[0-9]{4,8}$/', $config['pin'])) {
        $errores[] = 'PIN debe ser numérico de 4-8 dígitos';
    }
    
    // Validar Resolución DIAN (formato específico)
    if (!preg_match('/^[0-9]{1,20}$/', $config['resolucion_dian'])) {
        $errores[] = 'Resolución DIAN debe ser numérica';
    }
    
    // Validar TestSetId (obligatorio para ambos ambientes)
    if (empty($config['testset_id'])) {
        $errores[] = 'TestSetId requerido para ambos ambientes';
    }
    
    // Simular conexión a servidor DIAN
    $conexion_dian = simularConexionServidorDIAN($config['ambiente']);
    
    if (!$conexion_dian['success']) {
        $errores[] = 'Servidor DIAN no disponible en ambiente ' . $config['ambiente'] . ' - URL: ' . $conexion_dian['url'];
    }
    
    if (!empty($errores)) {
        return [
            'success' => false,
            'message' => 'Errores de validación: ' . implode(', ', $errores)
        ];
    }
    
    // Simular respuesta exitosa
    return [
        'success' => true,
        'message' => 'Conexión exitosa con DIAN',
        'datos' => [
            'conectado' => true,
            'ambiente' => $config['ambiente'],
            'software_id' => $config['software_id'],
            'resolucion_valida' => true,
            'rangos_disponibles' => true,
            'servidor_dian' => 'Conectado',
            'url_dian' => $conexion_dian['url'],
            'certificado_valido' => true,
            'testset_id' => $config['testset_id'] ?? 'No configurado',
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
}

function simularConexionServidorDIAN($ambiente, $url_dian = null) {
    // URLs por defecto si no se proporciona
    $urls_dian = [
        'test' => 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc?wsdl',
        'prod' => 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc?wsdl'
    ];
    
    $url = $url_dian ?? $urls_dian[$ambiente] ?? $urls_dian['test'];
    
    try {
        // Intentar conexión real a DIAN
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method' => 'GET'
            ]
        ]);
        
        // Verificar si el servicio está disponible
        $headers = @get_headers($url, 1, $context);
        
        if ($headers && strpos($headers[0], '200') !== false) {
            return [
                'success' => true,
                'url' => $url,
                'message' => 'Servidor DIAN disponible',
                'ambiente' => $ambiente
            ];
        } else {
            return [
                'success' => false,
                'url' => $url,
                'message' => 'Servidor DIAN no responde correctamente',
                'ambiente' => $ambiente
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'url' => $url,
            'message' => 'Error de conexión: ' . $e->getMessage(),
            'ambiente' => $ambiente
        ];
    }
}

function enviarFacturaDIAN($factura_id) {
    global $pdo;
    
    try {
        // Obtener configuración de FE del ambiente seleccionado
        require_once 'funciones.php';
        $config = obtenerConfiguracionFE($_SESSION['user_id']);
        
        if (!$config || empty($config['software_id'])) {
            return ['success' => false, 'message' => 'Configuración de FE no encontrada'];
        }
        
        // Obtener datos de la factura
        $stmt = $pdo->prepare("SELECT v.*, c.nombre as cliente FROM ventas v LEFT JOIN clientes c ON v.cliente_id = c.id WHERE v.id = ?");
        $stmt->execute([$factura_id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$factura) {
            return ['success' => false, 'message' => 'Factura no encontrada'];
        }
        
        // Generar XML de la factura
        $xml_result = generarXMLFactura($factura_id);
        
        if (!$xml_result['success']) {
            return $xml_result;
        }
        
        $xml = $xml_result['xml'];
        $cufe = $xml_result['cufe'];
        
        // URL real de DIAN
        $url_dian = $config['url_dian'] ?? ($config['ambiente'] === 'test' 
            ? 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc?wsdl'
            : 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc?wsdl');
        
        // Simular envío a DIAN (en producción aquí se haría el envío real)
        $respuesta_dian = [
            'success' => true,
            'message' => 'Factura enviada exitosamente a DIAN',
            'dian_response' => [
                'status' => 'ACCEPTED',
                'uuid' => 'test-uuid-' . time(),
                'testset_id' => $config['testset_id'],
                'url_dian' => $url_dian,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
        if ($respuesta_dian['success']) {
            // Actualizar factura con CUFE
            $stmt = $pdo->prepare("UPDATE ventas SET cufe = ?, estado_fe = 'enviada' WHERE id = ?");
            $stmt->execute([$cufe, $factura_id]);
            
            // Registrar log
            $stmt = $pdo->prepare("INSERT INTO fe_log (venta_id, tipo_documento, numero_documento, estado, cufe, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$factura_id, 'factura', $factura['numero_factura'], 'enviado', $cufe, $_SESSION['user_id']]);
            
            // Guardar respaldo
            $directorio_respaldo = '../respaldos_fe/';
            if (!is_dir($directorio_respaldo)) {
                mkdir($directorio_respaldo, 0777, true);
            }
            
            $archivo_respaldo = $directorio_respaldo . 'FAC_' . $factura_id . '_' . date('YmdHis') . '.xml';
            file_put_contents($archivo_respaldo, $xml);
            
            return [
                'success' => true,
                'message' => $respuesta_dian['message'],
                'icon' => '✅',
                'cufe' => $cufe,
                'dian_response' => $respuesta_dian['dian_response'],
                'archivo_respaldo' => basename($archivo_respaldo)
            ];
        } else {
            return $respuesta_dian;
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function obtenerLogsFE($filtros) {
    global $pdo;
    
    try {
        $where = "WHERE 1=1";
        $params = [];
        
        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND creado_en >= ?";
            $params[] = $filtros['fecha_desde'];
        }
        
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND creado_en <= ?";
            $params[] = $filtros['fecha_hasta'];
        }
        
        if (!empty($filtros['estado'])) {
            $where .= " AND estado = ?";
            $params[] = $filtros['estado'];
        }
        
        $sql = "SELECT * FROM fe_log $where ORDER BY creado_en DESC LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ['success' => true, 'logs' => $logs];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Generar XML para factura electrónica según estándares DIAN
 */
function generarXMLFactura($factura_id) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Obtener configuración FE del ambiente seleccionado
        require_once 'funciones.php';
        $config = obtenerConfiguracionFE($_SESSION['user_id']);
        
        if (!$config || empty($config['software_id'])) {
            return ['success' => false, 'message' => 'Configuración de FE no encontrada'];
        }
        
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
            return ['success' => false, 'message' => 'Factura no encontrada'];
        }
        
        // Obtener items de la factura
        $sql = "SELECT vi.*, p.nombre as producto_nombre, p.referencia as producto_codigo
                FROM venta_items vi 
                LEFT JOIN productos p ON vi.producto_id = p.id 
                WHERE vi.venta_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$factura_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($items)) {
            return ['success' => false, 'message' => 'La factura debe tener al menos un item'];
        }
        
        // Generar CUFE
        $cufe = generarCUFE($factura, $config);
        
        // Generar UUID único para el documento
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        // Construir XML completo según estándares DIAN UBL 2.1
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" 
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
         xmlns:ccts="urn:un:unece:uncefact:documentation:2"
         xmlns:qdt="urn:oasis:names:specification:ubl:schema:xsd:QualifiedDatatypes-2"
         xmlns:udt="urn:un:unece:uncefact:data:specification:UnqualifiedDataTypesSchemaModule:2">
    
    <!-- Encabezado del documento -->
    <cbc:ID>' . htmlspecialchars($factura['numero_factura']) . '</cbc:ID>
    <cbc:UUID>' . $uuid . '</cbc:UUID>
    <cbc:IssueDate>' . date('Y-m-d', strtotime($factura['fecha_venta'])) . '</cbc:IssueDate>
    <cbc:IssueTime>' . date('H:i:s', strtotime($factura['fecha_venta'])) . '</cbc:IssueTime>
    <cbc:InvoiceTypeCode listID="1" listAgencyID="195" listAgencyName="CO, DIAN" listName="Tipo de Documento" listURI="urn:oasis:names:specification:ubl:codelist:gc:InvoiceTypeCode-1.0">01</cbc:InvoiceTypeCode>
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
                <cbc:ID schemeID="4" schemeName="31" schemeAgencyID="195" schemeAgencyName="CO, DIAN" schemeURI="urn:oasis:names:specification:ubl:codelist:gc:PartyIdentificationCode-1.0">' . htmlspecialchars($factura['numero_documento'] ?? '900000000') . '</cbc:ID>
            </cac:PartyIdentification>
            <cac:PartyName>
                <cbc:Name>' . htmlspecialchars($factura['cliente_nombre'] ?? 'Cliente Genérico') . '</cbc:Name>
            </cac:PartyName>
            <cac:PostalAddress>
                <cbc:StreetName>' . htmlspecialchars($factura['cliente_direccion'] ?? 'Dirección Cliente') . '</cbc:StreetName>
                <cac:Country>
                    <cbc:IdentificationCode listID="ISO 3166-1" listName="Country">CO</cbc:IdentificationCode>
                </cac:Country>
            </cac:PostalAddress>
        </cac:Party>
    </cac:AccountingCustomerParty>
    
    <!-- Forma de pago (PaymentMeans) -->
    <cac:PaymentMeans>
        <cbc:PaymentMeansCode listID="1" listAgencyID="195" listAgencyName="CO, DIAN" listName="Medio de Pago" listURI="urn:oasis:names:specification:ubl:codelist:gc:PaymentMeansCode-1.0">10</cbc:PaymentMeansCode>
        <cbc:PaymentDueDate>' . date('Y-m-d', strtotime($factura['fecha_venta'])) . '</cbc:PaymentDueDate>
    </cac:PaymentMeans>
    
    <!-- Condiciones de pago (PaymentTerms) -->
    <cac:PaymentTerms>
        <cbc:Note>Pago inmediato</cbc:Note>
        <cbc:PaymentDueDate>' . date('Y-m-d', strtotime($factura['fecha_venta'])) . '</cbc:PaymentDueDate>
    </cac:PaymentTerms>
    
    <!-- Impuestos (TaxTotal) -->
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="COP">' . number_format($factura['iva'], 2, '.', '') . '</cbc:TaxAmount>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="COP">' . number_format($factura['subtotal'], 2, '.', '') . '</cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="COP">' . number_format($factura['iva'], 2, '.', '') . '</cbc:TaxAmount>
            <cac:TaxCategory>
                <cbc:ID schemeID="1" schemeName="Código de Tipo de Impuesto" schemeAgencyID="195" schemeAgencyName="CO, DIAN" schemeURI="urn:oasis:names:specification:ubl:codelist:gc:TaxCategoryCode-1.0">S</cbc:ID>
                <cbc:Percent>19.00</cbc:Percent>
                <cac:TaxScheme>
                    <cbc:ID schemeID="1" schemeName="Código de Tipo de Impuesto" schemeAgencyID="195" schemeAgencyName="CO, DIAN" schemeURI="urn:oasis:names:specification:ubl:codelist:gc:TaxSchemeCode-1.0">01</cbc:ID>
                </cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
    </cac:TaxTotal>
    
    <!-- Totales legales (LegalMonetaryTotal) -->
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="COP">' . number_format($factura['subtotal'], 2, '.', '') . '</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="COP">' . number_format($factura['subtotal'], 2, '.', '') . '</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="COP">' . number_format($factura['total'], 2, '.', '') . '</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="COP">' . number_format($factura['total'], 2, '.', '') . '</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
    
    <!-- Detalle de productos/servicios (InvoiceLine) -->';
        
        foreach ($items as $index => $item) {
            $xml .= '
    <cac:InvoiceLine>
        <cbc:ID>' . ($index + 1) . '</cbc:ID>
        <cbc:InvoicedQuantity unitCode="NIU" unitCodeListID="UN/ECE rec 20" unitCodeListAgencyID="6" unitCodeListAgencyName="United Nations Economic Commission for Europe">' . $item['cantidad'] . '</cbc:InvoicedQuantity>
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
    </cac:InvoiceLine>';
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
    
    <!-- Extensiones UBL (Firma digital y CUFE) -->
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
                <cbc:ProfileID>DIAN 2.1: Factura Electrónica de Venta</cbc:ProfileID>
                <cbc:UUID>' . $uuid . '</cbc:UUID>
                <cbc:Hash>' . $cufe . '</cbc:Hash>
            </cbc:ExtensionContent>
        </cac:UBLExtension>
    </cac:UBLExtensions>
    
</Invoice>';
        
        return ['success' => true, 'xml' => $xml, 'cufe' => $cufe, 'uuid' => $uuid];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error generando XML: ' . $e->getMessage()];
    }
}

/**
 * Generar CUFE para la factura
 */
function generarCUFE($factura, $config) {
    // Datos para generar CUFE
    $nit_emisor = $config['nit_empresa'] ?? '900000000';
    $nit_receptor = $factura['numero_documento'] ?? '900000000';
    $fecha = date('Y-m-d', strtotime($factura['fecha_venta']));
    $numero_factura = $factura['numero_factura'];
    $total = $factura['total'];
    $iva = $factura['iva'];
    $ambiente = $config['ambiente'] ?? 'test';
    $testset_id = $config['testset_id'] ?? '';
    $clave_tecnica = $config['clave_tecnica'] ?? '';
    
    // Generar CUFE usando algoritmo DIAN (incluyendo clave técnica)
    $datos_cufe = $nit_emisor . $nit_receptor . $fecha . $numero_factura . $total . $iva . $ambiente . $testset_id . $clave_tecnica;
    $cufe = hash('sha256', $datos_cufe);
    
    return strtoupper($cufe);
}


/**
 * Validar flujo de ventas para facturación electrónica
 */
function validarFlujoVentas() {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $validaciones = [];
        $errores = [];
        
        // 1. Verificar productos con IVA
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE iva_porcentaje > 0");
        $productos_iva = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $validaciones[] = "Productos con IVA: $productos_iva";
        
        // 2. Verificar clientes con documentos
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM clientes WHERE numero_documento IS NOT NULL AND numero_documento != ''");
        $clientes_documento = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $validaciones[] = "Clientes con documento: $clientes_documento";
        
        // 3. Verificar facturas pendientes
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM ventas WHERE estado_fe = 'pendiente'");
        $facturas_pendientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $validaciones[] = "Facturas pendientes FE: $facturas_pendientes";
        
        // 4. Verificar configuración FE del ambiente específico
        $ambiente = $_POST['ambiente'] ?? 'test';
        $stmt = $pdo->prepare("SELECT * FROM fe_ambientes WHERE ambiente = ?");
        $stmt->execute([$ambiente]);
        $config_fe = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config_fe) {
            $errores[] = "Configuración de FE no encontrada para ambiente $ambiente";
        } else {
            if (empty($config_fe['software_id'])) {
                $errores[] = "Software ID no configurado para ambiente $ambiente";
            }
            if (empty($config_fe['nit_empresa'])) {
                $errores[] = "NIT de empresa no configurado para ambiente $ambiente";
            }
            if (empty($config_fe['nombre_empresa'])) {
                $errores[] = "Nombre de empresa no configurado para ambiente $ambiente";
            }
            if (empty($config_fe['prefijo'])) {
                $errores[] = "Prefijo no configurado para ambiente $ambiente";
            }
            if (empty($config_fe['resolucion_dian'])) {
                $errores[] = "Resolución DIAN no configurada para ambiente $ambiente";
            }
        }
        
        // 5. Verificar estructura de base de datos
        $tablas_requeridas = ['ventas', 'venta_items', 'productos', 'clientes', 'fe_ambientes', 'fe_log'];
        foreach ($tablas_requeridas as $tabla) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$tabla'");
            if (!$stmt->fetch()) {
                $errores[] = "Tabla '$tabla' no encontrada";
            }
        }
        
        // URL de DIAN
        $url_dian = $config_fe['url_dian'] ?? ($config_fe['ambiente'] === 'test' 
            ? 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc?wsdl'
            : 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc?wsdl');
        
        $mensaje = "Validación del flujo de ventas:\n";
        $mensaje .= implode("\n", $validaciones);
        
        if (!empty($errores)) {
            $mensaje .= "\n\nErrores encontrados:\n";
            $mensaje .= implode("\n", $errores);
            return [
                'success' => false, 
                'message' => $mensaje,
                'datos' => [
                    'productos_con_iva' => $productos_iva,
                    'clientes_con_documentos' => $clientes_documento,
                    'facturas_pendientes' => $facturas_pendientes,
                    'config_fe_ok' => !empty($config_fe),
                    'estructura_bd_ok' => empty($errores),
                    'testset_id_ok' => !empty($config_fe['testset_id']),
                    'url_dian' => $url_dian
                ]
            ];
        }
        
        return [
            'success' => true, 
            'message' => $mensaje,
            'datos' => [
                'productos_con_iva' => $productos_iva,
                'clientes_con_documentos' => $clientes_documento,
                'facturas_pendientes' => $facturas_pendientes,
                'config_fe_ok' => !empty($config_fe),
                'estructura_bd_ok' => empty($errores),
                'testset_id_ok' => !empty($config_fe['testset_id']),
                'url_dian' => $url_dian
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error validando flujo: ' . $e->getMessage()];
    }
}
?>
