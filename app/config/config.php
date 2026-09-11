<?php
/**
 * Configuración del Sistema de Inventarios y Facturación
 * XAMPP - Debian 12
 */

if (!function_exists('env_or_default')) {
    function env_or_default($key, $default) {
        $value = getenv($key);
        return ($value !== false && $value !== '') ? $value : $default;
    }
}

if (!function_exists('local_request_scheme')) {
    function local_request_scheme() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]);
        }
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return 'https';
        }
        return 'http';
    }
}

if (!function_exists('local_app_url_default')) {
    function local_app_url_default() {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return local_request_scheme() . '://' . $host . '/local';
    }
}

if (!function_exists('local_public_url_default')) {
    function local_public_url_default() {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return local_request_scheme() . '://' . $host . '/local/public/';
    }
}

// Configuración de la base de datos
define('DB_HOST', env_or_default('LOCAL_DB_HOST', 'localhost'));
define('DB_NAME', env_or_default('LOCAL_DB_NAME', 'inventario_facturacion'));
define('DB_USER', env_or_default('LOCAL_DB_USER', 'root'));
define('DB_PASS', env_or_default('LOCAL_DB_PASS', 'Fiddle72*'));
define('DB_CHARSET', 'utf8mb4');

// Configuración de la aplicación
define('APP_NAME', 'TECNOXPERT - Sistema de Inventarios');
define('APP_VERSION', '1.0.0');
define('APP_URL', env_or_default('LOCAL_APP_URL', local_app_url_default()));
define('APP_PATH', __DIR__ . '/../');

// Configuración de sesiones
define('SESSION_NAME', 'TECNOXPERT_SESSION');
define('SESSION_LIFETIME', 3600); // 1 hora
define('CSRF_TOKEN_NAME', 'csrf_token');

// Configuración de seguridad
define('PASSWORD_COST', 12);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos

// Configuración de archivos
define('UPLOAD_PATH', APP_PATH . 'public/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf']);

// Configuración de facturación
define('DEFAULT_IVA', 19);
define('DEFAULT_RETEFUENTE', 2.5);
define('DEFAULT_RETEIVA', 15);
define('DEFAULT_RETEICA', 9);

// Configuración de paginación
define('ITEMS_PER_PAGE', 20);

// Configuración de reportes
define('REPORTS_PATH', APP_PATH . 'public/reports/');

// Configuración de logs
define('LOG_PATH', APP_PATH . 'logs/');
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR

// Configuración de debug
define('DEBUG_MODE', true); // Cambiar a false en producción

// Configuración de facturación electrónica (pendiente)
define('FE_ENABLED', false);
define('FE_SOFTWARE_ID', '');
define('FE_PIN', '');
define('FE_PROVIDER', '');
define('FE_ENVIRONMENT', 'test'); // test, production

// Configuración de empresa (por defecto)
define('EMPRESA_NOMBRE', 'TECNOXPERT');
define('EMPRESA_NIT', '900.000.000-1');
define('EMPRESA_DIRECCION', 'Calle Principal #123');
define('EMPRESA_CIUDAD', 'Bogotá');
define('EMPRESA_TELEFONO', '');
define('EMPRESA_EMAIL', 'contacto@tecnoxpert.com');

// Configuración de consecutivos
define('FACTURA_PREFIX', 'FAC');
define('NOTA_CREDITO_PREFIX', 'NC');
define('NOTA_DEBITO_PREFIX', 'ND');
define('COMPRA_PREFIX', 'COM');

// Configuración de zona horaria
date_default_timezone_set('America/Bogota');

// Configuración de errores (solo para desarrollo)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', LOG_PATH . 'php_errors.log');

// Crear directorios necesarios si no existen
$directories = [
    UPLOAD_PATH,
    REPORTS_PATH,
    LOG_PATH
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Definir BASE_URL como constante
if (!defined('BASE_URL')) {
    define('BASE_URL', env_or_default('LOCAL_BASE_URL', local_public_url_default()));
}

// Función para obtener la ruta base
if (!function_exists('getBasePath')) {
    function getBasePath() {
    return APP_PATH;
}
    }


// Función para generar token CSRF
if (!function_exists('generateCSRFToken')) {
    if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    }

}

// Función para verificar token CSRF
if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}
    }


// Función para sanitizar entrada
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
    }


// Función para validar email
if (!function_exists('validateEmail')) {
    function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}
    }


// Función para formatear moneda
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
    return number_format($amount, 2, ',', '.');
}
    }


// Función para formatear fecha
if (!function_exists('formatDate')) {
    function formatDate($date, $format = 'd/m/Y') {
    return date($format, strtotime($date));
}
    }


// Función para formatear fecha y hora
if (!function_exists('formatDateTime')) {
    function formatDateTime($datetime, $format = 'd/m/Y H:i:s') {
    return date($format, strtotime($datetime));
}
    }


// Función para generar número consecutivo
if (!function_exists('generateConsecutive')) {
    function generateConsecutive($prefix, $lastNumber = 0) {
    $nextNumber = $lastNumber + 1;
    return $prefix . str_pad($nextNumber, 8, '0', STR_PAD_LEFT);
}
    }


// Función para calcular IVA
if (!function_exists('calculateIVA')) {
    function calculateIVA($amount, $percentage = DEFAULT_IVA) {
    return $amount * ($percentage / 100);
}
    }


// Función para calcular retenciones
if (!function_exists('calculateRetention')) {
    function calculateRetention($amount, $percentage) {
    return $amount * ($percentage / 100);
}
    }


// Función para registrar logs
if (!function_exists('logActivity')) {
    function logActivity($message, $level = 'INFO', $userId = null) {
    $logFile = LOG_PATH . 'activity.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userId = $userId ?? ($_SESSION['user_id'] ?? 'system');
    
    $logEntry = "[$timestamp] [$level] [User:$userId] [IP:$ip] $message" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
    }


// Función para verificar si el usuario está autenticado
if (!function_exists('isAuthenticated')) {
    function isAuthenticated() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}
    }


// Función para verificar permisos de rol
if (!function_exists('hasPermission')) {
    function hasPermission($requiredRole) {
    if (!isAuthenticated()) {
        return false;
    }
    
    $roleHierarchy = [
        'consulta' => 1,
        'empleado' => 2,
        'admin' => 3
    ];
    
    $userRole = $_SESSION['user_role'];
    $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
    $userLevel = $roleHierarchy[$userRole] ?? 0;
    
    return $userLevel >= $requiredLevel;
}
    }


// Función para redirigir (wrapper para evitar duplicación)
if (!function_exists('redirect')) {
    if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit();
    }
    }

}

// Función para obtener el rol del usuario actual
if (!function_exists('getCurrentUserRole')) {
    function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}
    }


// Función para obtener el ID del usuario actual
if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}
    }


// Función para obtener el nombre del usuario actual
if (!function_exists('getCurrentUserName')) {
    function getCurrentUserName() {
    return $_SESSION['user_name'] ?? null;
}
    }


// Función para verificar si es admin
if (!function_exists('isAdmin')) {
    function isAdmin() {
    return getCurrentUserRole() === 'admin';
}
    }


// Función para verificar si es empleado o admin
if (!function_exists('isEmployee')) {
    function isEmployee() {
    return in_array(getCurrentUserRole(), ['empleado', 'admin']);
}
    }


// Función para verificar si es consulta
if (!function_exists('isConsulta')) {
    function isConsulta() {
    return getCurrentUserRole() === 'consulta';
}
    }


// Función para generar contraseña segura
if (!function_exists('generateSecurePassword')) {
    function generateSecurePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    return $password;
}
    }


// Función para hashear contraseña
if (!function_exists('hashPassword')) {
    function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
}
    }


// Función para verificar contraseña
if (!function_exists('verifyPassword')) {
    function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
    }


// Función para generar token de recuperación
if (!function_exists('generateRecoveryToken')) {
    function generateRecoveryToken() {
    return bin2hex(random_bytes(32));
}
    }


// Función para validar NIT/RUT
if (!function_exists('validateNIT')) {
    function validateNIT($nit) {
    // Validación básica para NIT colombiano
    $nit = preg_replace('/[^0-9]/', '', $nit);
    return strlen($nit) >= 8 && strlen($nit) <= 15;
}
    }


// Función para validar teléfono
if (!function_exists('validatePhone')) {
    function validatePhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 7 && strlen($phone) <= 15;
}
    }


// Función para obtener la IP del cliente
if (!function_exists('getClientIP')) {
    function getClientIP() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}
    }


// Función para limpiar archivos temporales
if (!function_exists('cleanupTempFiles')) {
    function cleanupTempFiles($directory, $maxAge = 3600) {
    if (!is_dir($directory)) {
        return;
    }
    
    $files = glob($directory . '/*');
    $now = time();
    
    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) >= $maxAge) {
                unlink($file);
            }
        }
    }
}
    }


// Configuración de sesión (solo si no se han enviado cabeceras y la sesión no está activa)
if (!headers_sent() && session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Cambiar a 1 en producción con HTTPS
    
    // Iniciar sesión
    session_name(SESSION_NAME);
    session_start();
}

// Limpiar archivos temporales cada 100 requests
if (rand(1, 100) === 1) {
    cleanupTempFiles(UPLOAD_PATH . 'temp/');
    cleanupTempFiles(REPORTS_PATH . 'temp/');
}
?>
