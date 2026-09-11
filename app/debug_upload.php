<?php
// Debug para subida de archivos
session_start();

echo "<h2>Debug de Subida de Archivos</h2>";

// Verificar permisos del directorio
$documentosDir = '/opt/lampp/htdocs/local/documentos/';
echo "<h3>1. Verificación de Directorio</h3>";
echo "Directorio: " . $documentosDir . "<br>";
echo "Existe: " . (is_dir($documentosDir) ? 'SÍ' : 'NO') . "<br>";
echo "Escribible: " . (is_writable($documentosDir) ? 'SÍ' : 'NO') . "<br>";
echo "Permisos: " . substr(sprintf('%o', fileperms($documentosDir)), -4) . "<br>";

// Verificar configuración PHP
echo "<h3>2. Configuración PHP</h3>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";

// Probar subida si hay archivos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    echo "<h3>3. Prueba de Subida</h3>";
    
    $file = $_FILES['test_file'];
    echo "Nombre: " . $file['name'] . "<br>";
    echo "Tipo: " . $file['type'] . "<br>";
    echo "Tamaño: " . $file['size'] . " bytes<br>";
    echo "Error: " . $file['error'] . "<br>";
    echo "Temporal: " . $file['tmp_name'] . "<br>";
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileName = 'test_' . time() . '_' . basename($file['name']);
        $filePath = $documentosDir . $fileName;
        
        echo "Intentando mover a: " . $filePath . "<br>";
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            echo "<strong style='color: green;'>✅ Archivo subido correctamente</strong><br>";
            echo "Archivo guardado en: " . $filePath . "<br>";
        } else {
            echo "<strong style='color: red;'>❌ Error al mover archivo</strong><br>";
        }
    } else {
        echo "<strong style='color: red;'>❌ Error en subida: " . $file['error'] . "</strong><br>";
    }
}

// Mostrar archivos existentes
echo "<h3>4. Archivos Existentes</h3>";
$files = scandir($documentosDir);
foreach ($files as $file) {
    if ($file !== '.' && $file !== '..') {
        echo "- " . $file . " (" . filesize($documentosDir . $file) . " bytes)<br>";
    }
}
?>

<form method="POST" enctype="multipart/form-data">
    <h3>5. Prueba Manual</h3>
    <input type="file" name="test_file" required>
    <button type="submit">Probar Subida</button>
</form>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
</style>

