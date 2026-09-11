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
            $sql = "SELECT * FROM proveedores WHERE id = ? AND activo = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($proveedor) {
                echo json_encode(['success' => true, 'data' => $proveedor]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Proveedor no encontrado']);
            }
            break;
            
        case 'actualizar':
            $id = $_POST['id'] ?? 0;
            $sql = "UPDATE proveedores SET 
                    nit = ?, 
                    nombre = ?, 
                    contacto = ?, 
                    telefono = ?, 
                    email = ?, 
                    direccion = ?, 
                    ciudad = ?, 
                    pais = ? 
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['nit'] ?? '',
                $_POST['nombre'] ?? '',
                $_POST['contacto'] ?? '',
                $_POST['telefono'] ?? '',
                $_POST['email'] ?? '',
                $_POST['direccion'] ?? '',
                $_POST['ciudad'] ?? '',
                $_POST['pais'] ?? 'Colombia',
                $id
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Proveedor actualizado correctamente']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
