<?php
session_start();
header('Content-Type: application/json');

// Verificar si está logueado y es admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$action = $_POST['action'] ?? '';

if ($action === 'obtener_configuracion') {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Obtener configuración de sesiones desde la tabla config
        $stmt = $pdo->query("SELECT clave, valor FROM config WHERE clave IN ('session_lifetime', 'max_login_attempts', 'lockout_time', 'session_cookie_httponly', 'session_cookie_secure')");
        $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Valores por defecto si no existen
        $configuracion = [
            'session_lifetime' => $configs['session_lifetime'] ?? '3600',
            'max_login_attempts' => $configs['max_login_attempts'] ?? '5',
            'lockout_time' => $configs['lockout_time'] ?? '900',
            'session_cookie_httponly' => ($configs['session_cookie_httponly'] ?? '1') == '1',
            'session_cookie_secure' => ($configs['session_cookie_secure'] ?? '0') == '1'
        ];
        
        echo json_encode([
            'success' => true,
            'configuracion' => $configuracion
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} elseif ($action === 'guardar_configuracion') {
    $session_lifetime = intval($_POST['session_lifetime'] ?? 3600);
    $max_login_attempts = intval($_POST['max_login_attempts'] ?? 5);
    $lockout_time = intval($_POST['lockout_time'] ?? 900);
    $session_cookie_httponly = isset($_POST['session_cookie_httponly']) ? 1 : 0;
    $session_cookie_secure = isset($_POST['session_cookie_secure']) ? 1 : 0;
    
    // Validaciones
    if ($session_lifetime < 300 || $session_lifetime > 86400) {
        echo json_encode(['success' => false, 'message' => 'El tiempo de vida de sesión debe estar entre 300 segundos (5 min) y 86400 segundos (24 horas)']);
        exit();
    }
    
    if ($max_login_attempts < 3 || $max_login_attempts > 10) {
        echo json_encode(['success' => false, 'message' => 'El máximo de intentos de login debe estar entre 3 y 10']);
        exit();
    }
    
    if ($lockout_time < 60 || $lockout_time > 3600) {
        echo json_encode(['success' => false, 'message' => 'El tiempo de bloqueo debe estar entre 60 segundos (1 min) y 3600 segundos (1 hora)']);
        exit();
    }
    
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Guardar cada configuración
        $configuraciones = [
            'session_lifetime' => [
                'valor' => $session_lifetime,
                'descripcion' => 'Tiempo de vida de la sesión en segundos',
                'tipo' => 'number'
            ],
            'max_login_attempts' => [
                'valor' => $max_login_attempts,
                'descripcion' => 'Máximo número de intentos de login permitidos',
                'tipo' => 'number'
            ],
            'lockout_time' => [
                'valor' => $lockout_time,
                'descripcion' => 'Tiempo de bloqueo después de intentos fallidos (segundos)',
                'tipo' => 'number'
            ],
            'session_cookie_httponly' => [
                'valor' => $session_cookie_httponly,
                'descripcion' => 'Cookie HttpOnly para sesiones (seguridad)',
                'tipo' => 'boolean'
            ],
            'session_cookie_secure' => [
                'valor' => $session_cookie_secure,
                'descripcion' => 'Cookie Secure para sesiones (requiere HTTPS)',
                'tipo' => 'boolean'
            ]
        ];
        
        foreach ($configuraciones as $clave => $data) {
            $stmt = $pdo->prepare("INSERT INTO config (clave, valor, descripcion, tipo) 
                                  VALUES (?, ?, ?, ?)
                                  ON DUPLICATE KEY UPDATE valor = ?, descripcion = ?, tipo = ?, actualizado_en = NOW()");
            $stmt->execute([
                $clave,
                $data['valor'],
                $data['descripcion'],
                $data['tipo'],
                $data['valor'],
                $data['descripcion'],
                $data['tipo']
            ]);
        }
        
        // Registrar en log
        require_once __DIR__ . '/../../config/config.php';
        if (function_exists('logActivity')) {
            logActivity("Configuración de sesiones actualizada", 'INFO', $_SESSION['user_id']);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Configuración de sesiones guardada correctamente. Los cambios se aplicarán en el próximo inicio de sesión.'
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}
?>

