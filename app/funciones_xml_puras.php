<?php
/**
 * Funciones XML puras para el Validador DIAN
 * Solo contiene las funciones de generación XML
 */

// Incluir funciones básicas
require_once 'views/funciones.php';

// Función para generar XML de factura (extraída de ajax_facturacion_electronica.php)
function generarXMLFacturaPuro($factura_id) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Obtener configuración FE del ambiente seleccionado
        $config = obtenerConfiguracionFE($_SESSION['user_id']);
        if (!$config) {
            return ['success' => false, 'message' => 'Configuración FE no encontrada'];
        }
        
        // Obtener datos de la factura
        $sql = "SELECT v.*, c.nombre as cliente_nombre, c.numero_documento, c.tipo_documento, c.direccion as cliente_direccion
                FROM ventas v 
                LEFT JOIN clientes c ON v.cliente_id = c.id 
                WHERE v.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$factura_id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$factura) {
            return ['success' => false, 'message' => 'Factura no encontrada'];
        }
        
        // Obtener items de la factura
        $sql_items = "SELECT vi.*, p.nombre as producto_nombre, p.referencia as producto_codigo
                      FROM venta_items vi
                      LEFT JOIN productos p ON vi.producto_id = p.id
                      WHERE vi.venta_id = ?";
        $stmt_items = $pdo->prepare($sql_items);
        $stmt_items->execute([$factura_id]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($items)) {
            return ['success' => false, 'message' => 'La factura no tiene items'];
        }
        
        // Generar CUFE
        $cufe = generarCUFE($factura, $config);
        
        // Generar UUID
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        // Generar XML UBL 2.1
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" 
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" 
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" 
         xmlns:ccts="urn:un:unece:uncefact:documentation:2" 
         xmlns:qdt="urn:oasis:names:specification:ubl:schema:xsd:QualifiedDatatypes-2" 
         xmlns:udt="urn:un:unece:uncefact:data:specification:UnqualifiedDataTypesSchemaModule:2">
    
    <!-- ID del documento -->
    <cbc:ID>' . htmlspecialchars($factura['numero_factura']) . '</cbc:ID>
    
    <!-- Fecha de emisión -->
    <cbc:IssueDate>' . date('Y-m-d', strtotime($factura['fecha_venta'])) . '</cbc:IssueDate>
    
    <!-- Hora de emisión -->
    <cbc:IssueTime>' . date('H:i:s', strtotime($factura['fecha_venta'])) . '</cbc:IssueTime>
    
    <!-- Tipo de documento -->
    <cbc:InvoiceTypeCode listID="1" listAgencyID="6" listName="Tipo de Documento" listURI="urn:oasis:names:specification:ubl:codelist:gc:InvoiceTypeCode-1.0">01</cbc:InvoiceTypeCode>
    
    <!-- Código de moneda -->
    <cbc:DocumentCurrencyCode listID="ISO 4217 Alpha" listName="Currency" listAgencyID="6">COP</cbc:DocumentCurrencyCode>
    
    <!-- Referencia de orden de compra -->
    <cbc:OrderReference>
        <cbc:ID>' . htmlspecialchars($factura['numero_factura']) . '</cbc:ID>
    </cbc:OrderReference>
    
    <!-- Datos del emisor -->
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PartyIdentification>
                <cbc:ID schemeID="4" schemeName="NIT">' . htmlspecialchars($config['nit_empresa']) . '</cbc:ID>
            </cac:PartyIdentification>
            <cac:PartyName>
                <cbc:Name>' . htmlspecialchars($config['nombre_empresa']) . '</cbc:Name>
            </cac:PartyName>
            <cac:PostalAddress>
                <cbc:StreetName>' . htmlspecialchars($config['direccion_empresa'] ?? 'Dirección no configurada') . '</cbc:StreetName>
                <cbc:CityName>' . htmlspecialchars($config['ciudad_empresa'] ?? 'Ciudad no configurada') . '</cbc:CityName>
                <cac:Country>
                    <cbc:IdentificationCode listID="ISO 3166-1" listName="Country" listAgencyID="6">CO</cbc:IdentificationCode>
                </cac:Country>
            </cac:PostalAddress>
            <cac:PartyTaxScheme>
                <cac:TaxScheme>
                    <cbc:ID>01</cbc:ID>
                    <cbc:Name>IVA</cbc:Name>
                </cac:TaxScheme>
            </cac:PartyTaxScheme>
        </cac:Party>
    </cac:AccountingSupplierParty>
    
    <!-- Datos del comprador -->
    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PartyIdentification>
                <cbc:ID schemeID="4" schemeName="NIT">' . htmlspecialchars($factura['numero_documento'] ?? '12345678') . '</cbc:ID>
            </cac:PartyIdentification>
            <cac:PartyName>
                <cbc:Name>' . htmlspecialchars($factura['cliente_nombre']) . '</cbc:Name>
            </cac:PartyName>
            <cac:PostalAddress>
                <cbc:StreetName>' . htmlspecialchars($factura['cliente_direccion'] ?? 'Dirección no especificada') . '</cbc:StreetName>
                <cbc:CityName>Bogotá</cbc:CityName>
                <cac:Country>
                    <cbc:IdentificationCode listID="ISO 3166-1" listName="Country" listAgencyID="6">CO</cbc:IdentificationCode>
                </cac:Country>
            </cac:PostalAddress>
        </cac:Party>
    </cac:AccountingCustomerParty>
    
    <!-- Forma de pago -->
    <cac:PaymentMeans>
        <cbc:PaymentMeansCode listID="1" listAgencyID="6" listName="Medio de Pago" listURI="urn:oasis:names:specification:ubl:codelist:gc:PaymentMeansCode-1.0">10</cbc:PaymentMeansCode>
        <cbc:PaymentDueDate>' . date('Y-m-d', strtotime($factura['fecha_venta'])) . '</cbc:PaymentDueDate>
    </cac:PaymentMeans>
    
    <!-- Condiciones de pago -->
    <cac:PaymentTerms>
        <cbc:Note>Pago inmediato</cbc:Note>
    </cac:PaymentTerms>
    
    <!-- Totales de impuestos -->
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="COP">' . number_format($factura['iva'] ?? 0, 2, '.', '') . '</cbc:TaxAmount>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="COP">' . number_format($factura['subtotal'], 2, '.', '') . '</cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="COP">' . number_format($factura['iva'] ?? 0, 2, '.', '') . '</cbc:TaxAmount>
            <cac:TaxCategory>
                <cbc:ID schemeID="1" schemeName="Tipo de Impuesto" schemeAgencyID="6">S</cbc:ID>
                <cbc:Percent>19.00</cbc:Percent>
                <cac:TaxScheme>
                    <cbc:ID>01</cbc:ID>
                    <cbc:Name>IVA</cbc:Name>
                </cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
    </cac:TaxTotal>
    
    <!-- Totales legales -->
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="COP">' . number_format($factura['subtotal'], 2, '.', '') . '</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="COP">' . number_format($factura['subtotal'], 2, '.', '') . '</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="COP">' . number_format($factura['total'], 2, '.', '') . '</cbc:TaxInclusiveAmount>
        <cbc:AllowanceTotalAmount currencyID="COP">0.00</cbc:AllowanceTotalAmount>
        <cbc:PayableAmount currencyID="COP">' . number_format($factura['total'], 2, '.', '') . '</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
    
    <!-- Líneas de factura -->';
    
        foreach ($items as $item) {
            $xml .= '
    <cac:InvoiceLine>
        <cbc:ID>' . $item['id'] . '</cbc:ID>
        <cbc:InvoicedQuantity unitCode="NIU">' . $item['cantidad'] . '</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="COP">' . number_format($item['subtotal'], 2, '.', '') . '</cbc:LineExtensionAmount>
        <cac:Item>
            <cbc:Description>' . htmlspecialchars($item['producto_nombre']) . '</cbc:Description>
            <cac:SellersItemIdentification>
                <cbc:ID>' . htmlspecialchars($item['producto_codigo']) . '</cbc:ID>
            </cac:SellersItemIdentification>
        </cac:Item>
        <cac:Price>
            <cbc:PriceAmount currencyID="COP">' . number_format($item['precio_unitario'], 2, '.', '') . '</cbc:PriceAmount>
        </cac:Price>
    </cac:InvoiceLine>';
        }
        
        $xml .= '
    
    <!-- Extensiones UBL -->
    <cac:UBLExtensions>
        <cac:UBLExtension>
            <cac:ExtensionContent>
                <ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
                    <ds:SignedInfo>
                        <ds:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
                        <ds:SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"/>
                        <ds:Reference URI="">
                            <ds:Transforms>
                                <ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>
                            </ds:Transforms>
                            <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
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
            </cac:ExtensionContent>
        </cac:UBLExtension>
    </cac:UBLExtensions>
    
</Invoice>';
        
        return [
            'success' => true,
            'xml' => $xml,
            'cufe' => $cufe,
            'uuid' => $uuid
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error generando XML: ' . $e->getMessage()];
    }
}

// Función para generar CUFE
function generarCUFE($factura, $config) {
    $datos_cufe = [
        $config['nit_empresa'],
        $factura['numero_factura'],
        date('Y-m-d', strtotime($factura['fecha_venta'])),
        number_format($factura['total'], 2, '.', ''),
        $config['clave_tecnica'] ?? 'fc8eac422eba16e22ffd8c6f94b3f40a6e38162c'
    ];
    
    $cadena_cufe = implode('', $datos_cufe);
    return strtoupper(hash('sha384', $cadena_cufe));
}
?>
