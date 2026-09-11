<?php
session_start();

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$factura_id = $_POST['id'] ?? 0;

if (!$factura_id) {
    echo json_encode(['success' => false, 'message' => 'ID de factura no válido']);
    exit();
}

try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar que la factura existe y está pendiente
    $sql = "SELECT id, estado, numero_factura FROM ventas WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$factura_id]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$factura) {
        echo json_encode(['success' => false, 'message' => 'Factura no encontrada']);
        exit();
    }
    
    if ($factura['estado'] !== 'pendiente') {
        echo json_encode(['success' => false, 'message' => 'Solo se pueden marcar como pagadas las facturas pendientes']);
        exit();
    }
    
    // Actualizar estado a pagada
    $sql = "UPDATE ventas SET estado = 'pagada', actualizado_en = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$factura_id]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Factura ' . $factura['numero_factura'] . ' marcada como pagada exitosamente'
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

