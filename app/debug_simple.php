<?php
// Debug simple - NO usar redirect
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Debug TECNOXPERT</h1>";
echo "<p>Fecha: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Archivo ejecutándose: " . __FILE__ . "</p>";

echo "<h2>Variables de Servidor:</h2>";
echo "<p>HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'No definido') . "</p>";
echo "<p>REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'No definido') . "</p>";
echo "<p>SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'No definido') . "</p>";

echo "<h2>Prueba de Configuración:</h2>";
try {
    require_once __DIR__ . '/config/config.php';
    echo "<p>✅ Config cargado</p>";
    echo "<p>BASE_URL: " . (defined('BASE_URL') ? BASE_URL : 'No definido') . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Error config: " . $e->getMessage() . "</p>";
}

echo "<h2>Prueba de Base de Datos:</h2>";
try {
    require_once __DIR__ . '/app/Lib/Database.php';
    $db = Database::getInstance();
    echo "<p>✅ BD conectada</p>";
} catch (Exception $e) {
    echo "<p>❌ Error BD: " . $e->getMessage() . "</p>";
}

echo "<p><a href='public/'>🔗 Ir a Public/</a></p>";
echo "<p><a href='../app/'>🔗 Ir a App/ (sistema que funciona)</a></p>";
?>

