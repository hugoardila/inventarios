<?php
session_start();
header('Content-Type: application/json');

// Verificar si está logueado y es admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$action = $_POST['action'] ?? '';

try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    switch ($action) {
        case 'guardar':
            $nombre = $_POST['nombre'] ?? '';
            $apellido = $_POST['apellido'] ?? '';
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $rol_id = $_POST['rol_id'] ?? 0;
            
            if (empty($nombre) || empty($apellido) || empty($username) || empty($email) || empty($password) || empty($rol_id)) {
                echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
                exit();
            }
            
            // Verificar si el username ya existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? AND activo = 1");
            $stmt->execute([$username]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'El nombre de usuario ya está registrado']);
                exit();
            }
            
            // Verificar si el email ya existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND activo = 1");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'El email ya está registrado']);
                exit();
            }
            
            // Hash de la contraseña
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO usuarios (nombre, apellido, username, email, password, rol_id, activo) VALUES (?, ?, ?, ?, ?, ?, 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $apellido, $username, $email, $password_hash, $rol_id]);
            
            echo json_encode(['success' => true, 'message' => 'Usuario creado correctamente']);
            break;
            
        case 'obtener':
            $id = $_POST['id'] ?? 0;
            $sql = "SELECT u.*, r.nombre as rol FROM usuarios u LEFT JOIN roles r ON u.rol_id = r.id WHERE u.id = ? AND u.activo = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario) {
                // No enviar la contraseña
                unset($usuario['password']);
                echo json_encode(['success' => true, 'data' => $usuario]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            }
            break;
            
        case 'actualizar':
            $id = $_POST['id'] ?? 0;
            $nombre = $_POST['nombre'] ?? '';
            $apellido = $_POST['apellido'] ?? '';
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $rol_id = $_POST['rol_id'] ?? 0;
            $password = $_POST['password'] ?? '';
            
            if (empty($nombre) || empty($apellido) || empty($username) || empty($email) || empty($rol_id)) {
                echo json_encode(['success' => false, 'message' => 'Los campos nombre, apellido, username, email y rol son obligatorios']);
                exit();
            }
            
            // Verificar si el username ya existe en otro usuario
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? AND id != ? AND activo = 1");
            $stmt->execute([$username, $id]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'El nombre de usuario ya está registrado por otro usuario']);
                exit();
            }
            
            // Verificar si el email ya existe en otro usuario
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ? AND activo = 1");
            $stmt->execute([$email, $id]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'El email ya está registrado por otro usuario']);
                exit();
            }
            
            if (!empty($password)) {
                // Actualizar con nueva contraseña
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE usuarios SET nombre = ?, apellido = ?, username = ?, email = ?, password = ?, rol_id = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $apellido, $username, $email, $password_hash, $rol_id, $id]);
            } else {
                // Actualizar sin cambiar contraseña
                $sql = "UPDATE usuarios SET nombre = ?, apellido = ?, username = ?, email = ?, rol_id = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $apellido, $username, $email, $rol_id, $id]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Usuario actualizado correctamente']);
            break;
            
        case 'eliminar':
            $id = $_POST['id'] ?? 0;
            
            // No permitir eliminar el usuario admin principal
            if ($id == 1) {
                echo json_encode(['success' => false, 'message' => 'No se puede eliminar el usuario administrador principal']);
                exit();
            }
            
            $sql = "UPDATE usuarios SET activo = 0 WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

