<?php
/**
 * Validador previo al envío a DIAN (Sin echo statements)
 * Verifica que los documentos cumplan con todos los estándares DIAN
 */

class ValidadorDIAN {
    
    private $errores = [];
    private $advertencias = [];
    private $validaciones_detalladas = [];
    
    /**
     * Agregar validación detallada
     */
    private function agregarValidacion($categoria, $item, $descripcion, $estado, $mensaje = '') {
        $this->validaciones_detalladas[] = [
            'categoria' => $categoria,
            'item' => $item,
            'descripcion' => $descripcion,
            'estado' => $estado, // 'ok', 'error', 'warning'
            'mensaje' => $mensaje
        ];
    }
    
    /**
     * Obtener validaciones detalladas
     */
    public function obtenerValidacionesDetalladas() {
        return $this->validaciones_detalladas;
    }
    
    /**
     * Validar factura electrónica antes del envío
     */
    public function validarFacturaElectronica($factura_id, $config) {
        $this->errores = [];
        $this->advertencias = [];
        $this->validaciones_detalladas = [];
        
        // 1. Validar configuración básica
        $this->validarConfiguracionBasica($config);
        
        // 2. Validar datos de la factura
        $this->validarDatosFactura($factura_id);
        
        // 3. Validar estructura XML
        $this->validarEstructuraXMLFactura($factura_id, $config);
        
        // 4. Validar campos obligatorios DIAN
        $this->validarCamposObligatoriosDIAN($factura_id, $config);
        
        // 5. Validar numeración y rangos
        $this->validarNumeracionRangos($factura_id, $config);
        
        // 6. Validar certificado digital
        $this->validarCertificadoDigital($config);
        
        return [
            'success' => empty($this->errores),
            'errores' => $this->errores,
            'advertencias' => $this->advertencias,
            'validaciones_detalladas' => $this->validaciones_detalladas,
            'total_errores' => count($this->errores),
            'total_advertencias' => count($this->advertencias)
        ];
    }
    
    /**
     * Validar nota de crédito antes del envío
     */
    public function validarNotaCredito($nota_id, $config) {
        $this->errores = [];
        $this->advertencias = [];
        
        // 1. Validar configuración básica
        $this->validarConfiguracionBasica($config);
        
        // 2. Validar datos de la nota de crédito
        $this->validarDatosNotaCredito($nota_id);
        
        // 3. Validar referencia a factura original
        $this->validarReferenciaFacturaOriginal($nota_id);
        
        // 4. Validar estructura XML
        $this->validarEstructuraXMLNotaCredito($nota_id, $config);
        
        return [
            'success' => empty($this->errores),
            'errores' => $this->errores,
            'advertencias' => $this->advertencias,
            'total_errores' => count($this->errores),
            'total_advertencias' => count($this->advertencias)
        ];
    }
    
    /**
     * Validar nota de débito antes del envío
     */
    public function validarNotaDebito($nota_id, $config) {
        $this->errores = [];
        $this->advertencias = [];
        
        // 1. Validar configuración básica
        $this->validarConfiguracionBasica($config);
        
        // 2. Validar datos de la nota de débito
        $this->validarDatosNotaDebito($nota_id);
        
        // 3. Validar referencia a factura original
        $this->validarReferenciaFacturaOriginal($nota_id);
        
        // 4. Validar estructura XML
        $this->validarEstructuraXMLNotaDebito($nota_id, $config);
        
        return [
            'success' => empty($this->errores),
            'errores' => $this->errores,
            'advertencias' => $this->advertencias,
            'total_errores' => count($this->errores),
            'total_advertencias' => count($this->advertencias)
        ];
    }
    
    /**
     * Validar configuración básica
     */
    private function validarConfiguracionBasica($config) {
        // Validar campos obligatorios
        $campos_obligatorios = [
            'software_id' => 'Software ID',
            'pin' => 'PIN',
            'nit_empresa' => 'NIT Empresa',
            'nombre_empresa' => 'Nombre Empresa',
            'resolucion_dian' => 'Resolución DIAN',
            'prefijo' => 'Prefijo',
            'testset_id' => 'TestSetId',
            'url_dian' => 'URL DIAN',
            'clave_tecnica' => 'Clave Técnica'
        ];
        
        foreach ($campos_obligatorios as $campo => $descripcion) {
            if (empty($config[$campo])) {
                $this->errores[] = "Campo obligatorio faltante: $descripcion";
                $this->agregarValidacion('Configuración', $descripcion, 'Campo obligatorio', 'error', 'No configurado');
            } else {
                $this->agregarValidacion('Configuración', $descripcion, 'Campo obligatorio', 'ok', 'Configurado correctamente');
            }
        }
        
        // Validar formato de NIT
        if (!empty($config['nit_empresa'])) {
            if (preg_match('/^\d{9,15}-?\d?$/', $config['nit_empresa'])) {
                $this->agregarValidacion('Configuración', 'Formato NIT', 'Formato válido', 'ok', 'NIT con formato correcto');
            } else {
                $this->errores[] = "Formato de NIT inválido";
                $this->agregarValidacion('Configuración', 'Formato NIT', 'Formato válido', 'error', 'Formato inválido');
            }
        }
        
        // Validar formato de TestSetId (UUID)
        if (!empty($config['testset_id'])) {
            if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $config['testset_id'])) {
                $this->agregarValidacion('Configuración', 'TestSetId', 'Formato UUID válido', 'ok', 'UUID válido');
            } else {
                $this->errores[] = "Formato de TestSetId inválido (debe ser UUID)";
                $this->agregarValidacion('Configuración', 'TestSetId', 'Formato UUID válido', 'error', 'Formato inválido');
            }
        }
    }
    
    /**
     * Validar datos de la factura
     */
    private function validarDatosFactura($factura_id) {
        try {
            $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $sql = "SELECT v.*, c.nombre as cliente_nombre, c.numero_documento 
                    FROM ventas v 
                    LEFT JOIN clientes c ON v.cliente_id = c.id 
                    WHERE v.id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$factura_id]);
            $factura = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$factura) {
                $this->errores[] = "Factura no encontrada";
                $this->agregarValidacion('Datos Factura', 'Existencia', 'Factura existe', 'error', 'No encontrada');
                return;
            }
            
            $this->agregarValidacion('Datos Factura', 'Existencia', 'Factura existe', 'ok', 'Factura encontrada');
            
            // Validar campos obligatorios
            $campos_factura = [
                'numero_factura' => 'Número de factura',
                'fecha_venta' => 'Fecha de venta',
                'cliente_nombre' => 'Nombre del cliente',
                'numero_documento' => 'Documento del cliente',
                'subtotal' => 'Subtotal',
                'total' => 'Total'
            ];
            
            foreach ($campos_factura as $campo => $nombre) {
                if (empty($factura[$campo])) {
                    $this->errores[] = "Campo obligatorio de factura faltante: $nombre";
                    $this->agregarValidacion('Datos Factura', $nombre, 'Campo obligatorio', 'error', 'Vacío');
                } else {
                    $this->agregarValidacion('Datos Factura', $nombre, 'Campo obligatorio', 'ok', 'Completado');
                }
            }
            
            // Validar que tenga items
            $sql_items = "SELECT COUNT(*) FROM venta_items WHERE venta_id = ?";
            $stmt_items = $pdo->prepare($sql_items);
            $stmt_items->execute([$factura_id]);
            $count_items = $stmt_items->fetchColumn();
            
            if ($count_items == 0) {
                $this->errores[] = "La factura debe tener al menos un item";
                $this->agregarValidacion('Datos Factura', 'Items', 'Al menos un item', 'error', 'Sin items');
            } else {
                $this->agregarValidacion('Datos Factura', 'Items', 'Al menos un item', 'ok', "$count_items items encontrados");
            }
            
            // Validar totales
            if ($factura['total'] <= 0) {
                $this->errores[] = "El total de la factura debe ser mayor a 0";
                $this->agregarValidacion('Datos Factura', 'Total', 'Total mayor a 0', 'error', 'Total inválido');
            } else {
                $this->agregarValidacion('Datos Factura', 'Total', 'Total mayor a 0', 'ok', 'Total válido');
            }
            
        } catch (PDOException $e) {
            $this->errores[] = "Error validando datos de factura: " . $e->getMessage();
            $this->agregarValidacion('Datos Factura', 'Conexión BD', 'Acceso a base de datos', 'error', $e->getMessage());
        }
    }
    
    /**
     * Validar datos de la nota de crédito
     */
    private function validarDatosNotaCredito($nota_id) {
        try {
            $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $sql = "SELECT nc.*, c.nombre as cliente_nombre, c.numero_documento 
                    FROM notas_credito nc 
                    LEFT JOIN clientes c ON nc.cliente_id = c.id 
                    WHERE nc.id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nota_id]);
            $nota = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$nota) {
                $this->errores[] = "Nota de crédito no encontrada";
                return;
            }
            
            // Validar que tenga items
            $sql_items = "SELECT COUNT(*) FROM nota_credito_items WHERE nota_credito_id = ?";
            $stmt_items = $pdo->prepare($sql_items);
            $stmt_items->execute([$nota_id]);
            $count_items = $stmt_items->fetchColumn();
            
            if ($count_items == 0) {
                $this->errores[] = "La nota de crédito debe tener al menos un item";
            }
            
        } catch (PDOException $e) {
            $this->errores[] = "Error validando datos de nota de crédito: " . $e->getMessage();
        }
    }
    
    /**
     * Validar datos de la nota de débito
     */
    private function validarDatosNotaDebito($nota_id) {
        try {
            $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $sql = "SELECT nd.*, c.nombre as cliente_nombre, c.numero_documento 
                    FROM notas_debito nd 
                    LEFT JOIN clientes c ON nd.cliente_id = c.id 
                    WHERE nd.id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nota_id]);
            $nota = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$nota) {
                $this->errores[] = "Nota de débito no encontrada";
                return;
            }
            
            // Validar que tenga items
            $sql_items = "SELECT COUNT(*) FROM nota_debito_items WHERE nota_debito_id = ?";
            $stmt_items = $pdo->prepare($sql_items);
            $stmt_items->execute([$nota_id]);
            $count_items = $stmt_items->fetchColumn();
            
            if ($count_items == 0) {
                $this->errores[] = "La nota de débito debe tener al menos un item";
            }
            
        } catch (PDOException $e) {
            $this->errores[] = "Error validando datos de nota de débito: " . $e->getMessage();
        }
    }
    
    /**
     * Validar referencia a factura original
     */
    private function validarReferenciaFacturaOriginal($nota_id) {
        try {
            $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Determinar si es nota de crédito o débito
            $sql_nc = "SELECT factura_id FROM notas_credito WHERE id = ?";
            $stmt_nc = $pdo->prepare($sql_nc);
            $stmt_nc->execute([$nota_id]);
            $factura_id_nc = $stmt_nc->fetchColumn();
            
            $sql_nd = "SELECT factura_id FROM notas_debito WHERE id = ?";
            $stmt_nd = $pdo->prepare($sql_nd);
            $stmt_nd->execute([$nota_id]);
            $factura_id_nd = $stmt_nd->fetchColumn();
            
            $factura_id = $factura_id_nc ?: $factura_id_nd;
            
            if (!$factura_id) {
                $this->errores[] = "No se encontró referencia a factura original";
                return;
            }
            
            // Verificar que la factura original exista
            $sql_factura = "SELECT id FROM ventas WHERE id = ?";
            $stmt_factura = $pdo->prepare($sql_factura);
            $stmt_factura->execute([$factura_id]);
            
            if (!$stmt_factura->fetchColumn()) {
                $this->errores[] = "La factura original referenciada no existe";
            }
            
        } catch (PDOException $e) {
            $this->errores[] = "Error validando referencia a factura original: " . $e->getMessage();
        }
    }
    
    /**
     * Validar estructura XML de factura
     */
    private function validarEstructuraXMLFactura($factura_id, $config) {
        // Secciones obligatorias según DIAN
        $secciones_obligatorias = [
            'Invoice' => 'Encabezado con versión UBL, ID y fecha',
            'AccountingSupplierParty' => 'Datos del emisor (NIT, nombre, dirección, régimen)',
            'AccountingCustomerParty' => 'Datos del comprador',
            'PaymentMeans' => 'Forma de pago (contado/crédito)',
            'PaymentTerms' => 'Condiciones del pago',
            'TaxTotal' => 'Impuestos (IVA, INC, retenciones)',
            'LegalMonetaryTotal' => 'Totales (subtotal, impuestos, total)',
            'InvoiceLine' => 'Detalle de productos o servicios',
            'UBLExtensions' => 'Firma digital y CUFE'
        ];
        
        // Validar que el XML se pueda generar
        try {
            // Incluir funciones XML puras
            require_once '../funciones_xml_puras.php';
            $xml_result = generarXMLFacturaPuro($factura_id);
            
            if ($xml_result['success']) {
                $this->agregarValidacion('XML', 'Generación', 'XML se genera correctamente', 'ok', 'XML generado exitosamente');
                
                // Validar estructura básica del XML
                $xml = $xml_result['xml'];
                foreach ($secciones_obligatorias as $seccion => $descripcion) {
                    // Buscar la sección con diferentes variaciones
                    $encontrada = false;
                    if (strpos($xml, "<$seccion>") !== false) $encontrada = true;
                    if (strpos($xml, "<cac:$seccion>") !== false) $encontrada = true;
                    if (strpos($xml, "<cbc:$seccion>") !== false) $encontrada = true;
                    if (strpos($xml, "<$seccion ") !== false) $encontrada = true;
                    if (strpos($xml, "<$seccion xmlns") !== false) $encontrada = true;
                    
                    if ($encontrada) {
                        $this->agregarValidacion('XML', $seccion, $descripcion, 'ok', 'Sección presente');
                    } else {
                        $this->errores[] = "Sección obligatoria faltante en XML: $seccion";
                        $this->agregarValidacion('XML', $seccion, $descripcion, 'error', 'Sección faltante');
                    }
                }
                
                // Validar CUFE
                if (!empty($xml_result['cufe'])) {
                    $this->agregarValidacion('XML', 'CUFE', 'Código único generado', 'ok', 'CUFE generado correctamente');
                } else {
                    $this->errores[] = "CUFE no generado";
                    $this->agregarValidacion('XML', 'CUFE', 'Código único generado', 'error', 'CUFE no generado');
                }
                
                // Validar UUID
                if (!empty($xml_result['uuid'])) {
                    $this->agregarValidacion('XML', 'UUID', 'Identificador único', 'ok', 'UUID generado correctamente');
                } else {
                    $this->agregarValidacion('XML', 'UUID', 'Identificador único', 'warning', 'UUID no generado');
                }
                
            } else {
                $this->errores[] = "Error generando XML: " . $xml_result['message'];
                $this->agregarValidacion('XML', 'Generación', 'XML se genera correctamente', 'error', $xml_result['message']);
            }
            
        } catch (Exception $e) {
            $this->errores[] = "Error validando estructura XML: " . $e->getMessage();
            $this->agregarValidacion('XML', 'Generación', 'XML se genera correctamente', 'error', $e->getMessage());
        }
    }
    
    /**
     * Validar estructura XML de nota de crédito
     */
    private function validarEstructuraXMLNotaCredito($nota_id, $config) {
        // Secciones obligatorias según DIAN
        $secciones_obligatorias = [
            'CreditNote' => 'Encabezado',
            'DiscrepancyResponse' => 'Motivo de la nota y factura referenciada',
            'BillingReference' => 'ID de la factura a la que aplica',
            'CreditNoteLine' => 'Detalle de los valores corregidos',
            'LegalMonetaryTotal' => 'Totales después del ajuste',
            'UBLExtensions' => 'Firma digital y CUDE'
        ];
        
        // Validar que el XML se pueda generar
        try {
            // Incluir funciones XML limpias
            require_once 'funciones_xml_validador.php';
            
            $xml_result = generarXMLNotaCreditoParaValidador($nota_id);
            
            if ($xml_result['success']) {
                // Validar estructura básica del XML
                $xml = $xml_result['xml'];
                foreach ($secciones_obligatorias as $seccion => $descripcion) {
                    if (strpos($xml, "<$seccion>") !== false || strpos($xml, "<cac:$seccion>") !== false || strpos($xml, "<cbc:$seccion>") !== false) {
                        // Sección presente
                    } else {
                        $this->errores[] = "Sección obligatoria faltante en XML: $seccion";
                    }
                }
                
                // Validar CUDE
                if (!empty($xml_result['cude'])) {
                    // CUDE generado correctamente
                } else {
                    $this->errores[] = "CUDE no generado";
                }
                
            } else {
                $this->errores[] = "Error generando XML: " . $xml_result['message'];
            }
            
        } catch (Exception $e) {
            $this->errores[] = "Error validando estructura XML: " . $e->getMessage();
        }
    }
    
    /**
     * Validar estructura XML de nota de débito
     */
    private function validarEstructuraXMLNotaDebito($nota_id, $config) {
        // Secciones obligatorias según DIAN
        $secciones_obligatorias = [
            'DebitNote' => 'Encabezado',
            'DiscrepancyResponse' => 'Motivo del incremento',
            'BillingReference' => 'Referencia a la factura original',
            'DebitNoteLine' => 'Detalles del ajuste',
            'LegalMonetaryTotal' => 'Totales actualizados',
            'UBLExtensions' => 'Firma digital y CUDE'
        ];
        
        // Validar que el XML se pueda generar
        try {
            // Incluir funciones XML limpias
            require_once 'funciones_xml_validador.php';
            
            $xml_result = generarXMLNotaDebitoParaValidador($nota_id);
            
            if ($xml_result['success']) {
                // Validar estructura básica del XML
                $xml = $xml_result['xml'];
                foreach ($secciones_obligatorias as $seccion => $descripcion) {
                    if (strpos($xml, "<$seccion>") !== false || strpos($xml, "<cac:$seccion>") !== false || strpos($xml, "<cbc:$seccion>") !== false) {
                        // Sección presente
                    } else {
                        $this->errores[] = "Sección obligatoria faltante en XML: $seccion";
                    }
                }
                
                // Validar CUDE
                if (!empty($xml_result['cude'])) {
                    // CUDE generado correctamente
                } else {
                    $this->errores[] = "CUDE no generado";
                }
                
            } else {
                $this->errores[] = "Error generando XML: " . $xml_result['message'];
            }
            
        } catch (Exception $e) {
            $this->errores[] = "Error validando estructura XML: " . $e->getMessage();
        }
    }
    
    /**
     * Validar campos obligatorios DIAN
     */
    private function validarCamposObligatoriosDIAN($documento_id, $config) {
        // Validar resolución DIAN
        if (!empty($config['resolucion_dian']) && !preg_match('/^\d{5,15}$/', $config['resolucion_dian'])) {
            $this->errores[] = "Formato de resolución DIAN inválido";
        }
        
        // Validar prefijo
        if (!empty($config['prefijo']) && !preg_match('/^[A-Z]{2,4}$/', $config['prefijo'])) {
            $this->errores[] = "Formato de prefijo inválido";
        }
    }
    
    /**
     * Validar numeración y rangos
     */
    private function validarNumeracionRangos($documento_id, $config) {
        try {
            $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Determinar tipo de documento
            $sql_venta = "SELECT numero_factura FROM ventas WHERE id = ?";
            $stmt_venta = $pdo->prepare($sql_venta);
            $stmt_venta->execute([$documento_id]);
            $numero_venta = $stmt_venta->fetchColumn();
            
            $sql_nc = "SELECT numero_nota FROM notas_credito WHERE id = ?";
            $stmt_nc = $pdo->prepare($sql_nc);
            $stmt_nc->execute([$documento_id]);
            $numero_nc = $stmt_nc->fetchColumn();
            
            $sql_nd = "SELECT numero_nota FROM notas_debito WHERE id = ?";
            $stmt_nd = $pdo->prepare($sql_nd);
            $stmt_nd->execute([$documento_id]);
            $numero_nd = $stmt_nd->fetchColumn();
            
            $numero_documento = $numero_venta ?: $numero_nc ?: $numero_nd;
            
            if ($numero_documento) {
                // Validar que el número esté dentro del rango configurado
                $rango_desde = $config['rango_desde'] ?? 1;
                $rango_hasta = $config['rango_hasta'] ?? 999999;
                
                // Extraer número del documento (asumiendo formato FAC001, NC001, etc.)
                $numero_solo = preg_replace('/[^0-9]/', '', $numero_documento);
                
                if ($numero_solo < $rango_desde || $numero_solo > $rango_hasta) {
                    $this->errores[] = "Número de documento fuera del rango autorizado ($rango_desde - $rango_hasta)";
                }
            }
            
        } catch (PDOException $e) {
            $this->errores[] = "Error validando numeración: " . $e->getMessage();
        }
    }
    
    /**
     * Validar certificado digital
     */
    private function validarCertificadoDigital($config) {
        // Verificar que exista el archivo del certificado digital
        $certificado_path = 'certificados/certificado.p12';
        
        if (!file_exists($certificado_path)) {
            $this->advertencias[] = "Certificado digital no encontrado en: $certificado_path";
        }
    }
}
?>
