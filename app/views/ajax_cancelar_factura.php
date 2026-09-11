<?php
session_start();
header('Content-Type: application/json');

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $factura_id = $_POST['id'] ?? 0;
    
    if (!$factura_id) {
        echo json_encode(['success' => false, 'message' => 'ID de factura requerido']);
        exit();
    }
    
    // Verificar que la factura existe y no está enviada a DIAN
    $stmt = $pdo->prepare("SELECT * FROM ventas WHERE id = ?");
    $stmt->execute([$factura_id]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$factura) {
        echo json_encode(['success' => false, 'message' => 'Factura no encontrada']);
        exit();
    }
    
    if ($factura['estado_fe'] === 'enviada') {
        echo json_encode(['success' => false, 'message' => 'No se puede cancelar una factura ya enviada a DIAN']);
        exit();
    }
    
    if ($factura['estado'] === 'anulada') {
        echo json_encode(['success' => false, 'message' => 'La factura ya está cancelada']);
        exit();
    }
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    try {
        // Actualizar estado de la factura
        $stmt = $pdo->prepare("UPDATE ventas SET estado = 'anulada', estado_fe = 'anulada' WHERE id = ?");
        $stmt->execute([$factura_id]);
        
        // Restaurar stock de productos
        $stmt = $pdo->prepare("
            SELECT vi.producto_id, vi.cantidad 
            FROM venta_items vi 
            WHERE vi.venta_id = ?
        ");
        $stmt->execute([$factura_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as $item) {
            $stmt = $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
            $stmt->execute([$item['cantidad'], $item['producto_id']]);
        }
        
        // Registrar log de cancelación
        $stmt = $pdo->prepare("INSERT INTO fe_log (venta_id, tipo_documento, numero_documento, estado, mensaje, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $factura_id, 
            'factura', 
            $factura['numero_factura'], 
            'rechazado', 
            'Factura cancelada por el usuario', 
            $_SESSION['user_id']
        ]);
        
        // Confirmar transacción
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Factura cancelada exitosamente. El stock de productos ha sido restaurado.'
        ]);
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

header('Content-Type: application/json');

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $factura_id = $_POST['id'] ?? 0;
    
    if (!$factura_id) {
        echo json_encode(['success' => false, 'message' => 'ID de factura requerido']);
        exit();
    }
    
    // Verificar que la factura existe y no está enviada a DIAN
    $stmt = $pdo->prepare("SELECT * FROM ventas WHERE id = ?");
    $stmt->execute([$factura_id]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$factura) {
        echo json_encode(['success' => false, 'message' => 'Factura no encontrada']);
        exit();
    }
    
    if ($factura['estado_fe'] === 'enviada') {
        echo json_encode(['success' => false, 'message' => 'No se puede cancelar una factura ya enviada a DIAN']);
        exit();
    }
    
    if ($factura['estado'] === 'anulada') {
        echo json_encode(['success' => false, 'message' => 'La factura ya está cancelada']);
        exit();
    }
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    try {
        // Actualizar estado de la factura
        $stmt = $pdo->prepare("UPDATE ventas SET estado = 'anulada', estado_fe = 'anulada' WHERE id = ?");
        $stmt->execute([$factura_id]);
        
        // Restaurar stock de productos
        $stmt = $pdo->prepare("
            SELECT vi.producto_id, vi.cantidad 
            FROM venta_items vi 
            WHERE vi.venta_id = ?
        ");
        $stmt->execute([$factura_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as $item) {
            $stmt = $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
            $stmt->execute([$item['cantidad'], $item['producto_id']]);
        }
        
        // Registrar log de cancelación
        $stmt = $pdo->prepare("INSERT INTO fe_log (venta_id, tipo_documento, numero_documento, estado, mensaje, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $factura_id, 
            'factura', 
            $factura['numero_factura'], 
            'rechazado', 
            'Factura cancelada por el usuario', 
            $_SESSION['user_id']
        ]);
        
        // Confirmar transacción
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Factura cancelada exitosamente. El stock de productos ha sido restaurado.'
        ]);
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
