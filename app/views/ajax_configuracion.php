<?php
session_start();
header('Content-Type: application/json');

// Verificar si está logueado y es admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once 'funciones.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$projectRoot = dirname(__DIR__);
$backupDir = $projectRoot . '/backups/';
$documentosDir = $projectRoot . '/documentos/';
$mysqldump = getenv('LOCAL_MYSQLDUMP_BIN') ?: 'mysqldump';
$dbHost = getenv('LOCAL_DB_HOST') ?: '';
$dbUser = getenv('LOCAL_DB_USER') ?: '';
$dbPass = getenv('LOCAL_DB_PASS') ?: '';
$dbName = getenv('LOCAL_DB_NAME') ?: '';


switch ($action) {
    case 'guardar_empresa':
        $configuraciones = [
            'nombre_empresa' => $_POST['nombre_empresa'] ?? '',
            'nit' => $_POST['nit'] ?? '',
            'direccion' => $_POST['direccion'] ?? '',
            'ciudad' => $_POST['ciudad'] ?? '',
            'telefono' => $_POST['telefono'] ?? '',
            'email' => $_POST['email'] ?? ''
        ];
        
        $errores = [];
        foreach ($configuraciones as $clave => $valor) {
            $resultado = guardarConfiguracion($clave, $valor);
            if (!$resultado['success']) {
                $errores[] = $resultado['message'];
            }
        }
        
        if (empty($errores)) {
            echo json_encode(['success' => true, 'message' => 'Datos de empresa guardados correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Errores: ' . implode(', ', $errores)]);
        }
        break;
        
    case 'guardar_impuestos':
        $configuraciones = [
            'iva' => $_POST['iva'] ?? 19,
            'retefuente' => $_POST['retefuente'] ?? 2.5,
            'reteiva' => $_POST['reteiva'] ?? 15,
            'reteica' => $_POST['reteica'] ?? 9
        ];
        
        $errores = [];
        foreach ($configuraciones as $clave => $valor) {
            $resultado = guardarConfiguracion($clave, $valor, 'number');
            if (!$resultado['success']) {
                $errores[] = $resultado['message'];
            }
        }
        
        if (empty($errores)) {
            echo json_encode(['success' => true, 'message' => 'Impuestos guardados correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Errores: ' . implode(', ', $errores)]);
        }
        break;
        
    case 'exportar_bd':
        // Crear backup de la base de datos
        $backupFile = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backupPath = $backupDir . $backupFile;
        
        // Crear directorio de backups si no existe
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $commandParts = [
    escapeshellarg($mysqldump),
    '--host=' . escapeshellarg($dbHost),
    '--user=' . escapeshellarg($dbUser)
];
if ($dbPass !== '') {
    $commandParts[] = '--password=' . escapeshellarg($dbPass);
}
$commandParts[] = '--single-transaction';
$commandParts[] = escapeshellarg($dbName);
$command = implode(' ', $commandParts) . ' > ' . escapeshellarg($backupPath) . ' 2>&1';

        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            echo json_encode(['success' => true, 'message' => 'Backup creado correctamente', 'file' => $backupFile]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al crear backup: ' . implode(' ', $output)]);
        }
        break;
        
    case 'guardar_documentos':
        
        // Crear directorio si no existe
        if (!is_dir($documentosDir)) {
            if (!mkdir($documentosDir, 0755, true)) {
                echo json_encode(['success' => false, 'message' => 'Error: No se pudo crear el directorio de documentos']);
                exit();
            }
        }
        
        // Verificar permisos de escritura
        if (!is_writable($documentosDir)) {
            echo json_encode(['success' => false, 'message' => 'Error: El directorio de documentos no tiene permisos de escritura']);
            exit();
        }
        
        $resultados = [];
        
        // Debug: Verificar archivos recibidos
        $debugInfo = [];
        $debugInfo[] = "Archivos recibidos: " . count($_FILES);
        foreach ($_FILES as $key => $file) {
            $debugInfo[] = "$key: " . ($file['error'] === UPLOAD_ERR_OK ? 'OK' : 'Error ' . $file['error']);
        }
        
        // Procesar RUT
        if (isset($_FILES['rut']) && $_FILES['rut']['error'] === UPLOAD_ERR_OK) {
            $rutFile = $_FILES['rut'];
            $allowedTypes = [
                'application/pdf', 
                'image/jpeg', 
                'image/jpg', 
                'image/png',
                'image/gif',
                'application/octet-stream' // Para archivos con tipo no detectado
            ];
            
            // Validar tamaño (5MB máximo)
            if ($rutFile['size'] <= 5 * 1024 * 1024) {
                // Validar extensión del archivo como respaldo
                $extension = strtolower(pathinfo($rutFile['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($rutFile['type'], $allowedTypes) || in_array($extension, $allowedExtensions)) {
                    $rutFileName = 'rut_' . time() . '_' . basename($rutFile['name']);
                    $rutPath = $documentosDir . $rutFileName;
                    
                    if (move_uploaded_file($rutFile['tmp_name'], $rutPath)) {
                        // Guardar información en la base de datos
                        guardarConfiguracion('rut_archivo', $rutFileName);
                        guardarConfiguracion('rut_tamaño', $rutFile['size']);
                        guardarConfiguracion('rut_fecha', date('Y-m-d H:i:s'));
                        $resultados[] = 'RUT subido correctamente';
                    } else {
                        $resultados[] = 'Error al subir RUT';
                    }
                } else {
                    $resultados[] = 'RUT: Formato no permitido. Use PDF, JPG, PNG o GIF';
                }
            } else {
                $resultados[] = 'RUT: Archivo demasiado grande (máximo 5MB)';
            }
        }
        
        // Procesar Certificado
        if (isset($_FILES['certificado']) && $_FILES['certificado']['error'] === UPLOAD_ERR_OK) {
            $certFile = $_FILES['certificado'];
            $allowedTypes = [
                'application/x-pkcs12', 
                'application/x-pkcs12-certificates', 
                'application/x-x509-ca-cert',
                'application/octet-stream' // Para archivos con tipo no detectado
            ];
            
            // Validar tamaño (10MB máximo)
            if ($certFile['size'] <= 10 * 1024 * 1024) {
                // Validar extensión del archivo como respaldo
                $extension = strtolower(pathinfo($certFile['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['p12', 'pfx', 'crt', 'cer', 'pem', 'key'];
                
                if (in_array($certFile['type'], $allowedTypes) || in_array($extension, $allowedExtensions)) {
                    $certFileName = 'certificado_' . time() . '_' . basename($certFile['name']);
                    $certPath = $documentosDir . $certFileName;
                    
                    if (move_uploaded_file($certFile['tmp_name'], $certPath)) {
                        // Guardar información en la base de datos
                        guardarConfiguracion('certificado_archivo', $certFileName);
                        guardarConfiguracion('certificado_tamaño', $certFile['size']);
                        guardarConfiguracion('certificado_fecha', date('Y-m-d H:i:s'));
                        
                        // Guardar contraseña si se proporciona
                        if (!empty($_POST['password_certificado'])) {
                            guardarConfiguracion('certificado_password', $_POST['password_certificado']);
                        }
                        
                        $resultados[] = 'Certificado subido correctamente';
                    } else {
                        $resultados[] = 'Error al subir certificado';
                    }
                } else {
                    $resultados[] = 'Certificado: Formato no permitido. Use P12, PFX, CRT, CER, PEM o KEY';
                }
            } else {
                $resultados[] = 'Certificado: Archivo demasiado grande (máximo 10MB)';
            }
        }
        
        if (!empty($resultados)) {
            echo json_encode(['success' => true, 'message' => implode(', ', $resultados)]);
        } else {
            $debugMessage = 'No se subieron archivos. Debug: ' . implode(', ', $debugInfo);
            echo json_encode(['success' => false, 'message' => $debugMessage]);
        }
        break;
        
    case 'obtener_documentos':
        $documentos = [];
        
        // Obtener RUT
        $rutArchivo = obtenerConfiguracion('rut_archivo');
        if ($rutArchivo) {
            $rutPath = $documentosDir . $rutArchivo;
            if (file_exists($rutPath)) {
                $documentos['rut'] = [
                    'nombre' => $rutArchivo,
                    'tamaño' => obtenerConfiguracion('rut_tamaño') ?: filesize($rutPath),
                    'fecha' => obtenerConfiguracion('rut_fecha') ?: date('Y-m-d H:i:s', filemtime($rutPath))
                ];
            }
        }
        
        // Obtener Certificado
        $certArchivo = obtenerConfiguracion('certificado_archivo');
        if ($certArchivo) {
            $certPath = $documentosDir . $certArchivo;
            if (file_exists($certPath)) {
                $documentos['certificado'] = [
                    'nombre' => $certArchivo,
                    'tamaño' => obtenerConfiguracion('certificado_tamaño') ?: filesize($certPath),
                    'fecha' => obtenerConfiguracion('certificado_fecha') ?: date('Y-m-d H:i:s', filemtime($certPath))
                ];
            }
        }
        
        echo json_encode(['success' => true, 'documentos' => $documentos]);
        break;
        
    case 'descargar_documento':
        $tipo = $_GET['tipo'] ?? '';
        $archivo = null;
        
        if ($tipo === 'rut') {
            $archivo = obtenerConfiguracion('rut_archivo');
        } elseif ($tipo === 'certificado') {
            $archivo = obtenerConfiguracion('certificado_archivo');
        }
        
        if ($archivo) {
            $rutaCompleta = $documentosDir . $archivo;
            if (file_exists($rutaCompleta)) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $archivo . '"');
                header('Content-Length: ' . filesize($rutaCompleta));
                readfile($rutaCompleta);
                exit();
            }
        }
        
        echo json_encode(['success' => false, 'message' => 'Archivo no encontrado']);
        break;
        
    case 'eliminar_documento':
        $tipo = $_POST['tipo'] ?? '';
        $archivo = null;
        
        if ($tipo === 'rut') {
            $archivo = obtenerConfiguracion('rut_archivo');
            if ($archivo) {
                $rutaCompleta = $documentosDir . $archivo;
                if (file_exists($rutaCompleta)) {
                    unlink($rutaCompleta);
                }
                guardarConfiguracion('rut_archivo', '');
                guardarConfiguracion('rut_tamaño', '');
                guardarConfiguracion('rut_fecha', '');
            }
        } elseif ($tipo === 'certificado') {
            $archivo = obtenerConfiguracion('certificado_archivo');
            if ($archivo) {
                $rutaCompleta = $documentosDir . $archivo;
                if (file_exists($rutaCompleta)) {
                    unlink($rutaCompleta);
                }
                guardarConfiguracion('certificado_archivo', '');
                guardarConfiguracion('certificado_tamaño', '');
                guardarConfiguracion('certificado_fecha', '');
                guardarConfiguracion('certificado_password', '');
            }
        }
        
        echo json_encode(['success' => true, 'message' => ucfirst($tipo) . ' eliminado correctamente']);
        break;
        
    case 'visualizar_documento':
        $tipo = $_GET['tipo'] ?? '';
        $archivo = null;
        
        if ($tipo === 'rut') {
            $archivo = obtenerConfiguracion('rut_archivo');
        } elseif ($tipo === 'certificado') {
            $archivo = obtenerConfiguracion('certificado_archivo');
        }
        
        if ($archivo) {
            $rutaCompleta = $documentosDir . $archivo;
            if (file_exists($rutaCompleta)) {
                // Determinar el tipo de contenido
                $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
                
                if (in_array($extension, ['pdf'])) {
                    // Para PDF, mostrar en iframe
                    header('Content-Type: text/html; charset=utf-8');
                    echo '<!DOCTYPE html>
                    <html>
                    <head>
                        <title>Visualizador de Documento</title>
                        <style>
                            body { margin: 0; padding: 0; font-family: Arial, sans-serif; }
                            .header { background: #007bff; color: white; padding: 10px; text-align: center; }
                            .viewer { width: 100%; height: calc(100vh - 60px); border: none; }
                            .controls { background: #f8f9fa; padding: 10px; text-align: center; border-bottom: 1px solid #dee2e6; }
                            .btn { margin: 0 5px; padding: 5px 15px; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h3>📄 Visualizador de ' . ucfirst($tipo) . '</h3>
                        </div>
                        <div class="controls">
                            <button class="btn" onclick="window.print()">🖨️ Imprimir</button>
                            <button class="btn" onclick="window.close()">❌ Cerrar</button>
                        </div>
                        <iframe src="data:application/pdf;base64,' . base64_encode(file_get_contents($rutaCompleta)) . '" class="viewer"></iframe>
                    </body>
                    </html>';
                } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                    // Para imágenes, mostrar directamente
                    header('Content-Type: text/html; charset=utf-8');
                    echo '<!DOCTYPE html>
                    <html>
                    <head>
                        <title>Visualizador de Imagen</title>
                        <style>
                            body { margin: 0; padding: 0; font-family: Arial, sans-serif; background: #f0f0f0; }
                            .header { background: #007bff; color: white; padding: 10px; text-align: center; }
                            .viewer { text-align: center; padding: 20px; }
                            .controls { background: #f8f9fa; padding: 10px; text-align: center; border-bottom: 1px solid #dee2e6; }
                            .btn { margin: 0 5px; padding: 5px 15px; }
                            img { max-width: 100%; max-height: 80vh; border: 2px solid #ddd; border-radius: 5px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h3>🖼️ Visualizador de Imagen - ' . ucfirst($tipo) . '</h3>
                        </div>
                        <div class="controls">
                            <button class="btn" onclick="window.print()">🖨️ Imprimir</button>
                            <button class="btn" onclick="window.close()">❌ Cerrar</button>
                        </div>
                        <div class="viewer">
                            <img src="data:image/' . $extension . ';base64,' . base64_encode(file_get_contents($rutaCompleta)) . '" alt="' . $archivo . '">
                        </div>
                    </body>
                    </html>';
                } else {
                    // Para otros tipos de archivo, mostrar información
                    header('Content-Type: text/html; charset=utf-8');
                    echo '<!DOCTYPE html>
                    <html>
                    <head>
                        <title>Información del Documento</title>
                        <style>
                            body { margin: 0; padding: 20px; font-family: Arial, sans-serif; }
                            .header { background: #007bff; color: white; padding: 10px; text-align: center; margin: -20px -20px 20px -20px; }
                            .info { background: #f8f9fa; padding: 20px; border-radius: 5px; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h3>📄 Información del Documento</h3>
                        </div>
                        <div class="info">
                            <h4>Archivo: ' . $archivo . '</h4>
                            <p><strong>Tipo:</strong> ' . $extension . '</p>
                            <p><strong>Tamaño:</strong> ' . formatFileSize(filesize($rutaCompleta)) . '</p>
                            <p><strong>Fecha de modificación:</strong> ' . date('Y-m-d H:i:s', filemtime($rutaCompleta)) . '</p>
                            <p><em>Este tipo de archivo no se puede visualizar directamente en el navegador.</em></p>
                            <button onclick="window.close()">Cerrar</button>
                        </div>
                    </body>
                    </html>';
                }
                exit();
            }
        }
        
        // Si no se encuentra el archivo
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Error</title>
            <style>
                body { margin: 0; padding: 20px; font-family: Arial, sans-serif; text-align: center; }
                .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class="error">
                <h3>❌ Error</h3>
                <p>No se pudo encontrar el archivo solicitado.</p>
                <button onclick="window.close()">Cerrar</button>
            </div>
        </body>
        </html>';
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>
