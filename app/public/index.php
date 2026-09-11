<?php
/**
 * Front Controller - Sistema de Inventarios y Facturación
 * TECNOXPERT - XAMPP Debian 12
 */

// Incluir configuración
require_once __DIR__ . '/../config/config.php';

// Incluir librerías
require_once __DIR__ . '/../app/Lib/Database.php';
require_once __DIR__ . '/../app/Lib/Auth.php';

// Incluir controladores
require_once __DIR__ . '/../app/Controllers/AuthController.php';
require_once __DIR__ . '/../app/Controllers/DashboardController.php';
require_once __DIR__ . '/../app/Controllers/ProductoController.php';
require_once __DIR__ . '/../app/Controllers/ClienteController.php';
require_once __DIR__ . '/../app/Controllers/ProveedorController.php';
require_once __DIR__ . '/../app/Controllers/VentaController.php';
require_once __DIR__ . '/../app/Controllers/CompraController.php';
require_once __DIR__ . '/../app/Controllers/ReporteController.php';
require_once __DIR__ . '/../app/Controllers/ConfigController.php';

// Incluir modelos
require_once __DIR__ . '/../app/Models/Producto.php';
require_once __DIR__ . '/../app/Models/Cliente.php';
require_once __DIR__ . '/../app/Models/Proveedor.php';
require_once __DIR__ . '/../app/Models/Venta.php';
require_once __DIR__ . '/../app/Models/Compra.php';
require_once __DIR__ . '/../app/Models/Categoria.php';
require_once __DIR__ . '/../app/Models/Marca.php';
require_once __DIR__ . '/../app/Models/InventarioMovimiento.php';
require_once __DIR__ . '/../app/Models/Config.php';

// Función para manejar errores
function handleError($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $errorMessage = "Error [$errno]: $errstr en $errfile:$errline";
    logActivity($errorMessage, 'ERROR');
    
    if (DEBUG_MODE) {
        echo "<h1>Error del Sistema</h1>";
        echo "<p>$errorMessage</p>";
    } else {
        echo "<h1>Error del Sistema</h1>";
        echo "<p>Ha ocurrido un error interno. Por favor, intente más tarde.</p>";
    }
    
    exit(1);
}

// Función para manejar excepciones
function handleException($exception) {
    $errorMessage = "Excepción: " . $exception->getMessage() . " en " . $exception->getFile() . ":" . $exception->getLine();
    logActivity($errorMessage, 'ERROR');
    
    if (DEBUG_MODE) {
        echo "<h1>Error del Sistema</h1>";
        echo "<p>$errorMessage</p>";
        echo "<pre>" . $exception->getTraceAsString() . "</pre>";
    } else {
        echo "<h1>Error del Sistema</h1>";
        echo "<p>Ha ocurrido un error interno. Por favor, intente más tarde.</p>";
    }
    
    exit(1);
}

// Configurar manejadores de errores
set_error_handler('handleError');
set_exception_handler('handleException');

// Función para obtener la URL base (wrapper para la constante)
if (!function_exists('getBaseUrl')) {
    function getBaseUrl() {
        return BASE_URL;
    }
}

// Función para redirigir (wrapper para evitar duplicación)
if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit();
    }
}

// Función para renderizar vista
if (!function_exists('render')) {
    function render($view, $data = []) {
    extract($data);
    $viewFile = __DIR__ . "/../app/Views/{$view}.php";
    
    if (!file_exists($viewFile)) {
        throw new Exception("Vista no encontrada: {$view}");
    }
    
    ob_start();
    include $viewFile;
    $content = ob_get_clean();
    
    return $content;
    }
}

// Función para renderizar vista con layout
if (!function_exists('renderWithLayout')) {
    function renderWithLayout($view, $data = [], $layout = 'default') {
    $content = render($view, $data);
    
    $layoutFile = __DIR__ . "/../app/Views/layouts/{$layout}.php";
    
    if (!file_exists($layoutFile)) {
        throw new Exception("Layout no encontrado: {$layout}");
    }
    
    extract($data);
    include $layoutFile;
    }
}

// Función para obtener parámetros de la URL
function getUrlParams() {
    $requestUri = $_SERVER['REQUEST_URI'];
    $scriptName = $_SERVER['SCRIPT_NAME'];
    
    // Remover el directorio del script de la URI
    $path = str_replace(dirname($scriptName), '', $requestUri);
    
    // Remover query string
    $path = parse_url($path, PHP_URL_PATH);
    
    // Dividir en segmentos
    $segments = array_filter(explode('/', $path));
    
    return array_values($segments);
}

// Función para verificar si es una petición AJAX
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Función para responder con JSON
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// Función para verificar permisos
function checkPermission($permission, $action = 'read') {
    $auth = new Auth();
    return $auth->hasPermission($permission, $action);
}

// Función para verificar acceso al módulo
function checkModuleAccess($module) {
    $auth = new Auth();
    return $auth->hasModuleAccess($module);
}

// Función para obtener menú según permisos
function getMenuItems() {
    $auth = new Auth();
    $permissions = $auth->getCurrentPermissions();
    
    $menu = [
        'dashboard' => [
            'title' => 'Dashboard',
            'icon' => 'fas fa-tachometer-alt',
            'url' => 'dashboard',
            'visible' => true
        ],
        'productos' => [
            'title' => 'Productos',
            'icon' => 'fas fa-box',
            'url' => 'productos',
            'visible' => isset($permissions['productos'])
        ],
        'clientes' => [
            'title' => 'Clientes',
            'icon' => 'fas fa-users',
            'url' => 'clientes',
            'visible' => isset($permissions['clientes'])
        ],
        'proveedores' => [
            'title' => 'Proveedores',
            'icon' => 'fas fa-truck',
            'url' => 'proveedores',
            'visible' => isset($permissions['proveedores'])
        ],
        'ventas' => [
            'title' => 'Ventas',
            'icon' => 'fas fa-shopping-cart',
            'url' => 'ventas',
            'visible' => isset($permissions['ventas'])
        ],
        'compras' => [
            'title' => 'Compras',
            'icon' => 'fas fa-shopping-bag',
            'url' => 'compras',
            'visible' => isset($permissions['compras'])
        ],
        'reportes' => [
            'title' => 'Reportes',
            'icon' => 'fas fa-chart-bar',
            'url' => 'reportes',
            'visible' => isset($permissions['reportes'])
        ],
        'configuracion' => [
            'title' => 'Configuración',
            'icon' => 'fas fa-cog',
            'url' => 'configuracion',
            'visible' => isset($permissions['configuracion'])
        ]
    ];
    
    return array_filter($menu, function($item) {
        return $item['visible'];
    });
}

// Obtener parámetros de la URL
$urlParams = getUrlParams();

// Determinar controlador y acción
$controller = $urlParams[0] ?? 'dashboard';
$action = $urlParams[1] ?? 'index';
$id = $urlParams[2] ?? null;

// Mapeo de controladores
$controllers = [
    'auth' => 'AuthController',
    'dashboard' => 'DashboardController',
    'productos' => 'ProductoController',
    'clientes' => 'ClienteController',
    'proveedores' => 'ProveedorController',
    'ventas' => 'VentaController',
    'compras' => 'CompraController',
    'reportes' => 'ReporteController',
    'configuracion' => 'ConfigController'
];

// Verificar si el controlador existe
if (!isset($controllers[$controller])) {
    $controller = 'auth';
    $action = 'login';
}

$controllerClass = $controllers[$controller];

// Verificar autenticación (excepto para auth)
if ($controller !== 'auth' && !isAuthenticated()) {
    redirect('auth/login');
}

// Verificar expiración de sesión
if (isAuthenticated()) {
    $auth = new Auth();
    if ($auth->isSessionExpired()) {
        $auth->logout();
        redirect('auth/login?expired=1');
    } else {
        $auth->renewSession();
    }
}

// Verificar permisos para módulos protegidos
if ($controller !== 'auth' && $controller !== 'dashboard') {
    if (!checkModuleAccess($controller)) {
        if (isAjaxRequest()) {
            jsonResponse(['error' => 'Acceso denegado'], 403);
        } else {
            redirect('dashboard?error=access_denied');
        }
    }
}

try {
    // Instanciar controlador
    $controllerInstance = new $controllerClass();
    
    // Verificar si el método existe
    if (!method_exists($controllerInstance, $action)) {
        throw new Exception("Acción no encontrada: {$action}");
    }
    
    // Ejecutar acción
    $result = $controllerInstance->$action($id);
    
    // Si es una petición AJAX, responder con JSON
    if (isAjaxRequest()) {
        jsonResponse($result);
    }
    
    // Si el resultado es un array, renderizar vista
    if (is_array($result)) {
        $view = $result['view'] ?? $controller . '/' . $action;
        $data = $result['data'] ?? [];
        $layout = $result['layout'] ?? 'default';
        
        renderWithLayout($view, $data, $layout);
    } else {
        // Si es un string, mostrarlo directamente
        echo $result;
    }
    
} catch (Exception $e) {
    logActivity("Error en controlador: " . $e->getMessage(), 'ERROR');
    
    if (isAjaxRequest()) {
        jsonResponse(['error' => $e->getMessage()], 500);
    } else {
        // Mostrar página de error
        $errorData = [
            'error' => $e->getMessage(),
            'title' => 'Error del Sistema',
            'menuItems' => getMenuItems()
        ];
        
        renderWithLayout('error/500', $errorData, 'default');
    }
}

// Función para limpiar output buffer al final
register_shutdown_function(function() {
    if (ob_get_level()) {
        ob_end_flush();
    }
});
?>
