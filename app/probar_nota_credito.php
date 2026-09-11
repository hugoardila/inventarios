<?php
// Script de prueba para verificar el funcionamiento de las notas de crédito

echo "<h2>🔍 Prueba de Notas de Crédito</h2>";

// Probar obtener motivos
echo "<h3>1. Probando obtener motivos...</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/local/views/ajax_notas_credito.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['action' => 'obtener_motivos']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<strong>HTTP Code:</strong> $httpCode<br>";
echo "<strong>Response:</strong> <pre>" . htmlspecialchars($response) . "</pre>";

// Probar obtener productos de una factura (usando ID 1 como ejemplo)
echo "<h3>2. Probando obtener productos de factura...</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/local/views/ajax_notas_credito.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['action' => 'obtener_productos_factura', 'factura_id' => 1]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response2 = curl_exec($ch);
$httpCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<strong>HTTP Code:</strong> $httpCode2<br>";
echo "<strong>Response:</strong> <pre>" . htmlspecialchars($response2) . "</pre>";

// Verificar estructura de la base de datos
echo "<h3>3. Verificando estructura de la base de datos...</h3>";
try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar tabla motivos_nota_credito
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM motivos_nota_credito WHERE activo = 1");
    $motivos_count = $stmt->fetchColumn();
    echo "<strong>Motivos activos:</strong> $motivos_count<br>";
    
    // Verificar tabla nota_credito_items
    $stmt = $pdo->query("SHOW TABLES LIKE 'nota_credito_items'");
    $table_exists = $stmt->fetchColumn();
    echo "<strong>Tabla nota_credito_items existe:</strong> " . ($table_exists ? 'Sí' : 'No') . "<br>";
    
    // Verificar facturas disponibles
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM ventas WHERE estado_fe = 'enviada'");
    $facturas_count = $stmt->fetchColumn();
    echo "<strong>Facturas enviadas a DIAN:</strong> $facturas_count<br>";
    
    if ($facturas_count > 0) {
        $stmt = $pdo->query("SELECT id, numero_factura FROM ventas WHERE estado_fe = 'enviada' LIMIT 3");
        $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<strong>Primeras facturas:</strong><br>";
        foreach ($facturas as $factura) {
            echo "- ID: {$factura['id']}, Número: {$factura['numero_factura']}<br>";
        }
    }
    
} catch (PDOException $e) {
    echo "<strong>Error de base de datos:</strong> " . $e->getMessage() . "<br>";
}

echo "<h3>4. Instrucciones para probar:</h3>";
echo "<ol>";
echo "<li>Abre la consola del navegador (F12)</li>";
echo "<li>Ve a la página de facturas</li>";
echo "<li>Haz clic en 'Nota Crédito' en una factura enviada a DIAN</li>";
echo "<li>Revisa los mensajes en la consola</li>";
echo "</ol>";
?>

// Script de prueba para verificar el funcionamiento de las notas de crédito

echo "<h2>🔍 Prueba de Notas de Crédito</h2>";

// Probar obtener motivos
echo "<h3>1. Probando obtener motivos...</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/local/views/ajax_notas_credito.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['action' => 'obtener_motivos']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<strong>HTTP Code:</strong> $httpCode<br>";
echo "<strong>Response:</strong> <pre>" . htmlspecialchars($response) . "</pre>";

// Probar obtener productos de una factura (usando ID 1 como ejemplo)
echo "<h3>2. Probando obtener productos de factura...</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/local/views/ajax_notas_credito.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['action' => 'obtener_productos_factura', 'factura_id' => 1]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response2 = curl_exec($ch);
$httpCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<strong>HTTP Code:</strong> $httpCode2<br>";
echo "<strong>Response:</strong> <pre>" . htmlspecialchars($response2) . "</pre>";

// Verificar estructura de la base de datos
echo "<h3>3. Verificando estructura de la base de datos...</h3>";
try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar tabla motivos_nota_credito
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM motivos_nota_credito WHERE activo = 1");
    $motivos_count = $stmt->fetchColumn();
    echo "<strong>Motivos activos:</strong> $motivos_count<br>";
    
    // Verificar tabla nota_credito_items
    $stmt = $pdo->query("SHOW TABLES LIKE 'nota_credito_items'");
    $table_exists = $stmt->fetchColumn();
    echo "<strong>Tabla nota_credito_items existe:</strong> " . ($table_exists ? 'Sí' : 'No') . "<br>";
    
    // Verificar facturas disponibles
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM ventas WHERE estado_fe = 'enviada'");
    $facturas_count = $stmt->fetchColumn();
    echo "<strong>Facturas enviadas a DIAN:</strong> $facturas_count<br>";
    
    if ($facturas_count > 0) {
        $stmt = $pdo->query("SELECT id, numero_factura FROM ventas WHERE estado_fe = 'enviada' LIMIT 3");
        $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<strong>Primeras facturas:</strong><br>";
        foreach ($facturas as $factura) {
            echo "- ID: {$factura['id']}, Número: {$factura['numero_factura']}<br>";
        }
    }
    
} catch (PDOException $e) {
    echo "<strong>Error de base de datos:</strong> " . $e->getMessage() . "<br>";
}

echo "<h3>4. Instrucciones para probar:</h3>";
echo "<ol>";
echo "<li>Abre la consola del navegador (F12)</li>";
echo "<li>Ve a la página de facturas</li>";
echo "<li>Haz clic en 'Nota Crédito' en una factura enviada a DIAN</li>";
echo "<li>Revisa los mensajes en la consola</li>";
echo "</ol>";
?>




