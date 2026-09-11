<?php
session_start();
header('Content-Type: application/json');

// Verificar si está logueado y es admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$action = $_POST['action'] ?? '';

if ($action === 'cambiar_password_admin') {
    $usuario_id = $_POST['usuario_id'] ?? 0;
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nuevo = $_POST['password_nuevo'] ?? '';
    $password_confirmar = $_POST['password_confirmar'] ?? '';
    
    // Validaciones
    if (empty($usuario_id)) {
        echo json_encode(['success' => false, 'message' => 'ID de usuario no válido']);
        exit();
    }
    
    if (empty($password_actual)) {
        echo json_encode(['success' => false, 'message' => 'Debe ingresar la contraseña actual']);
        exit();
    }
    
    if (empty($password_nuevo)) {
        echo json_encode(['success' => false, 'message' => 'Debe ingresar la nueva contraseña']);
        exit();
    }
    
    if (strlen($password_nuevo) < 8) {
        echo json_encode(['success' => false, 'message' => 'La nueva contraseña debe tener al menos 8 caracteres']);
        exit();
    }
    
    if ($password_nuevo !== $password_confirmar) {
        echo json_encode(['success' => false, 'message' => 'Las contraseñas nuevas no coinciden']);
        exit();
    }
    
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Obtener usuario
        $stmt = $pdo->prepare("SELECT id, username, password, rol_id FROM usuarios WHERE id = ? AND activo = 1");
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario) {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            exit();
        }
        
        // Verificar que sea admin
        $stmt = $pdo->prepare("SELECT nombre FROM roles WHERE id = ?");
        $stmt->execute([$usuario['rol_id']]);
        $rol = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$rol || $rol['nombre'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Solo se puede cambiar la contraseña de usuarios administradores']);
            exit();
        }
        
        // Verificar contraseña actual
        if (!password_verify($password_actual, $usuario['password'])) {
            echo json_encode(['success' => false, 'message' => 'La contraseña actual es incorrecta']);
            exit();
        }
        
        // Verificar que la nueva contraseña sea diferente
        if (password_verify($password_nuevo, $usuario['password'])) {
            echo json_encode(['success' => false, 'message' => 'La nueva contraseña debe ser diferente a la actual']);
            exit();
        }
        
        // Hashear nueva contraseña
        $password_hash = password_hash($password_nuevo, PASSWORD_BCRYPT, ['cost' => 12]);
        
        // Actualizar contraseña
        $stmt = $pdo->prepare("UPDATE usuarios SET password = ?, actualizado_en = NOW() WHERE id = ?");
        $stmt->execute([$password_hash, $usuario_id]);
        
        // Registrar en log
        require_once __DIR__ . '/../../config/config.php';
        if (function_exists('logActivity')) {
            logActivity("Contraseña cambiada para usuario administrador: {$usuario['username']}", 'INFO', $_SESSION['user_id']);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Contraseña del administrador actualizada correctamente'
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error de conexión: ' . $e->getMessage()
        ]);
    }
} elseif ($action === 'obtener_usuarios_admin') {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Obtener usuarios administradores
        $stmt = $pdo->query("
            SELECT u.id, u.username, u.nombre, u.apellido, u.email, u.ultimo_login
            FROM usuarios u
            INNER JOIN roles r ON u.rol_id = r.id
            WHERE r.nombre = 'admin' AND u.activo = 1
            ORDER BY u.nombre, u.apellido
        ");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'usuarios' => $usuarios
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

