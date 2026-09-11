<?php
echo "<h1>✅ SISTEMA FUNCIONANDO</h1>";
echo "<p>Si ves esto, la subcarpeta funciona correctamente.</p>";
echo "<p>Fecha: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Archivo: " . __FILE__ . "</p>";
echo "<p>HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'No definido') . "</p>";
echo "<p>REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'No definido') . "</p>";
?>

