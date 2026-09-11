<?php
session_start();
header('Content-Type: application/json');

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$action = $_POST['action'] ?? '';

try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    switch ($action) {
        case 'obtener':
            $id = $_POST['id'] ?? 0;
            $sql = "SELECT * FROM productos WHERE id = ? AND estado = 'activo'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($producto) {
                echo json_encode(['success' => true, 'data' => $producto]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
            }
            break;
            
        case 'actualizar':
            $id = $_POST['id'] ?? 0;
            $sql = "UPDATE productos SET 
                    nombre = ?, 
                    referencia = ?, 
                    descripcion = ?, 
                    precio = ?, 
                    stock = ? 
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['nombre'] ?? '',
                $_POST['referencia'] ?? '',
                $_POST['descripcion'] ?? '',
                $_POST['precio'] ?? 0,
                $_POST['stock'] ?? 0,
                $id
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Producto actualizado correctamente']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

