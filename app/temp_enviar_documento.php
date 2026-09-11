/**
 * Enviar documento a DIAN
 */
function enviarDocumentoDIAN($xml, $cufe, $config_dian) {
    // URLs reales de DIAN
    $urls_dian = [
        'test' => 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc?wsdl',
        'prod' => 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc?wsdl'
    ];
    
    $ambiente = $config_dian['ambiente'] ?? 'test';
    $url_dian = $urls_dian[$ambiente];
    
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


