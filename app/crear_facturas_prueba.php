<?php
// Script para crear 10 facturas de prueba
try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🚀 Creando 10 facturas de prueba...\n\n";
    
    // Obtener el producto PRUEBA
    $stmt = $pdo->prepare("SELECT id, nombre, precio FROM productos WHERE id = 23");
    $stmt->execute();
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$producto) {
        echo "❌ Error: Producto PRUEBA no encontrado\n";
        exit;
    }
    
    echo "📦 Producto encontrado: {$producto['nombre']} - Precio: $" . number_format($producto['precio'], 0, ',', '.') . "\n\n";
    
    // Obtener cliente genérico
    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE nombre = 'CLIENTE GENERAL' LIMIT 1");
    $stmt->execute();
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        // Crear cliente genérico si no existe
        $stmt = $pdo->prepare("INSERT INTO clientes (nombre, tipo_documento, numero_documento, email, telefono, direccion) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['CLIENTE GENERAL', 'CC', '900000000', 'cliente@general.com', '0000000000', 'Dirección General']);
        $cliente_id = $pdo->lastInsertId();
        echo "👤 Cliente genérico creado con ID: $cliente_id\n";
    } else {
        $cliente_id = $cliente['id'];
        echo "👤 Usando cliente genérico existente ID: $cliente_id\n";
    }
    
    // Obtener usuario admin
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE rol_id = 1 LIMIT 1");
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    $usuario_id = $usuario['id'];
    
    echo "👨‍💼 Usuario admin ID: $usuario_id\n\n";
    
    // Crear 10 facturas
    for ($i = 1; $i <= 10; $i++) {
        $pdo->beginTransaction();
        
        try {
            // Generar número de factura
            $numero_factura = 'FAC-' . str_pad($i, 6, '0', STR_PAD_LEFT);
            
            // Calcular totales
            $cantidad = rand(1, 5); // Cantidad aleatoria entre 1 y 5
            $precio_unitario = $producto['precio'];
            $subtotal = $cantidad * $precio_unitario;
            $iva = $subtotal * 0.19; // 19% IVA
            $total = $subtotal + $iva;
            
            // Insertar venta
            $stmt = $pdo->prepare("INSERT INTO ventas (numero_factura, cliente_id, usuario_id, fecha_venta, subtotal, iva, descuento, retefuente, reteiva, reteica, total, estado, estado_fe) VALUES (?, ?, ?, NOW(), ?, ?, 0.00, 0.00, 0.00, 0.00, ?, 'pendiente', 'pendiente')");
            $stmt->execute([$numero_factura, $cliente_id, $usuario_id, $subtotal, $iva, $total]);
            $venta_id = $pdo->lastInsertId();
            
            // Insertar item de venta
            $stmt = $pdo->prepare("INSERT INTO venta_items (venta_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$venta_id, $producto['id'], $cantidad, $precio_unitario, $subtotal]);
            
            // Actualizar stock del producto
            $stmt = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
            $stmt->execute([$cantidad, $producto['id']]);
            
            $pdo->commit();
            
            echo "✅ Factura #$i creada: $numero_factura - Cantidad: $cantidad - Total: $" . number_format($total, 0, ',', '.') . "\n";
            
        } catch (Exception $e) {
            $pdo->rollback();
            echo "❌ Error creando factura #$i: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🎉 ¡10 facturas de prueba creadas exitosamente!\n";
    echo "📊 Puedes verlas en el módulo de Facturas\n";
    
} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
}
?>
