<?php
session_start();
header('Content-Type: application/json');

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Incluir clase de permisos
require_once '../app/Lib/Permissions.php';

require_once 'funciones.php';

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'guardar':
        // Verificar permisos para crear categorías
        if (!Permissions::canCreate('productos')) {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para realizar esta acción']);
            exit();
        }
        
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        
        if (empty($nombre)) {
            echo json_encode(['success' => false, 'message' => 'El nombre de la categoría es requerido']);
            exit();
        }
        
        try {
            $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Verificar si ya existe una categoría con el mismo nombre
            $stmt = $pdo->prepare("SELECT id FROM categorias WHERE nombre = ? AND activo = 1");
            $stmt->execute([$nombre]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'Ya existe una categoría con el nombre "' . $nombre . '"']);
                exit();
            }
            
            // Insertar nueva categoría
            $sql = "INSERT INTO categorias (nombre, descripcion, activo, creado_en) VALUES (?, ?, 1, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $descripcion]);
            
            echo json_encode(['success' => true, 'message' => 'Categoría creada correctamente']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    case 'actualizar':
        // Verificar permisos para editar categorías
        if (!Permissions::canEdit('productos')) {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para realizar esta acción']);
            exit();
        }
        
        $id = $_POST['id'] ?? 0;
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        
        if (empty($nombre)) {
            echo json_encode(['success' => false, 'message' => 'El nombre de la categoría es requerido']);
            exit();
        }
        
        try {
            $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Verificar si ya existe otra categoría con el mismo nombre
            $stmt = $pdo->prepare("SELECT id FROM categorias WHERE nombre = ? AND id != ? AND activo = 1");
            $stmt->execute([$nombre, $id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'Ya existe otra categoría con el nombre "' . $nombre . '"']);
                exit();
            }
            
            // Actualizar categoría
            $sql = "UPDATE categorias SET nombre = ?, descripcion = ?, actualizado_en = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $descripcion, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Categoría actualizada correctamente']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    case 'eliminar':
        // Verificar permisos para eliminar categorías
        if (!Permissions::canDelete('productos')) {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para realizar esta acción']);
            exit();
        }
        
        $id = $_POST['id'] ?? 0;
        
        try {
            $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Verificar si hay productos asociados
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM productos WHERE categoria_id = ? AND estado = 'activo'");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['total'] > 0) {
                // Si hay productos, solo desactivar la categoría
                $sql = "UPDATE categorias SET activo = 0, actualizado_en = NOW() WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Categoría desactivada correctamente (tiene productos asociados)']);
            } else {
                // Si no hay productos, eliminar físicamente
                $sql = "DELETE FROM categorias WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Categoría eliminada correctamente']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    case 'obtener':
        $id = $_POST['id'] ?? 0;
        
        try {
            $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("SELECT * FROM categorias WHERE id = ?");
            $stmt->execute([$id]);
            $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($categoria) {
                echo json_encode(['success' => true, 'data' => $categoria]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Categoría no encontrada']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}
?>

