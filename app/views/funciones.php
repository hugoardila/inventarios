<?php
// Funciones básicas para el sistema

function guardarCliente($datos) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "INSERT INTO clientes (tipo_persona, tipo_documento, numero_documento, dv, nombre, telefono, email, direccion, ciudad, activo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $datos['tipo_persona'],
            $datos['tipo_documento'],
            $datos['numero_documento'],
            $datos['dv'] ?? '',
            $datos['nombre'],
            $datos['telefono'],
            $datos['email'],
            $datos['direccion'] ?? '',
            $datos['ciudad']
        ]);
        
        return ['success' => true, 'message' => 'Cliente guardado correctamente'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function guardarProveedor($datos) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "INSERT INTO proveedores (nit, nombre, contacto, telefono, email, direccion, ciudad, pais, activo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $datos['nit'],
            $datos['nombre'],
            $datos['contacto'],
            $datos['telefono'],
            $datos['email'],
            $datos['direccion'] ?? '',
            $datos['ciudad'],
            $datos['pais'] ?? 'Colombia'
        ]);
        
        return ['success' => true, 'message' => 'Proveedor guardado correctamente'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function guardarProducto($datos) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $producto_id = $datos['producto_id'] ?? 0;
        
        if ($producto_id > 0) {
            // Actualizar producto existente
            $sql = "UPDATE productos SET 
                    nombre = ?, referencia = ?, descripcion = ?, categoria_id = ?, marca_id = ?, 
                    proveedor_id = ?, costo = ?, precio = ?, stock = ?, stock_minimo = ?, 
                    iva_porcentaje = ?, unidad_medida = ?, ubicacion = ?, estado = ?
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $datos['nombre'],
                $datos['referencia'],
                $datos['descripcion'] ?? '',
                $datos['categoria_id'],
                $datos['marca_id'],
                $datos['proveedor_id'],
                $datos['costo'],
                $datos['precio'],
                $datos['stock'],
                $datos['stock_minimo'],
                $datos['iva_porcentaje'],
                $datos['unidad_medida'],
                $datos['ubicacion'],
                $datos['estado'],
                $producto_id
            ]);
            
            return ['success' => true, 'message' => 'Producto actualizado correctamente'];
        } else {
            // Crear nuevo producto
            $sql = "INSERT INTO productos (nombre, referencia, descripcion, categoria_id, marca_id, 
                    proveedor_id, costo, precio, stock, stock_minimo, iva_porcentaje, 
                    unidad_medida, ubicacion, estado) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $datos['nombre'],
                $datos['referencia'],
                $datos['descripcion'] ?? '',
                $datos['categoria_id'],
                $datos['marca_id'],
                $datos['proveedor_id'],
                $datos['costo'],
                $datos['precio'],
                $datos['stock'],
                $datos['stock_minimo'],
                $datos['iva_porcentaje'],
                $datos['unidad_medida'],
                $datos['ubicacion'],
                $datos['estado']
            ]);
            
            return ['success' => true, 'message' => 'Producto creado correctamente'];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function eliminarRegistro($tabla, $id, $columna = 'id') {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Determinar la columna de estado según la tabla
        $estadoColumna = ($tabla === 'productos') ? 'estado' : 'activo';
        $estadoValor = ($tabla === 'productos') ? 'inactivo' : 0;
        
        $sql = "UPDATE $tabla SET $estadoColumna = ? WHERE $columna = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$estadoValor, $id]);
        
        return ['success' => true, 'message' => 'Registro eliminado correctamente'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function generarFactura($datos) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Obtener configuración de FE para generar número de factura
        $stmt = $pdo->prepare("SELECT prefijo, rango_desde, rango_hasta FROM fe_ambientes WHERE ambiente = 'test'");
        $stmt->execute();
        $config_fe = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Generar número de factura con prefijo y rango configurados
        if ($config_fe && !empty($config_fe['prefijo']) && !empty($config_fe['rango_desde']) && !empty($config_fe['rango_hasta'])) {
            // Usar prefijo y rango configurados
            $prefijo = $config_fe['prefijo'];
            $rango_desde = (int)$config_fe['rango_desde'];
            $rango_hasta = (int)$config_fe['rango_hasta'];
            
            // Generar número secuencial dentro del rango
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM ventas WHERE numero_factura LIKE ?");
            $stmt->execute([$prefijo . '%']);
            $contador = $stmt->fetchColumn();
            
            $numero_secuencial = $rango_desde + $contador;
            
            // Verificar que no exceda el rango
            if ($numero_secuencial > $rango_hasta) {
                throw new Exception("Rango de numeración agotado. Contacte a DIAN para nuevo rango.");
            }
            
            $numeroFactura = $prefijo . str_pad($numero_secuencial, strlen($config_fe['rango_desde']), '0', STR_PAD_LEFT);
        } else {
            // Fallback al método anterior si no hay configuración
            $numeroFactura = 'FAC' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        }
        
        $sql = "INSERT INTO ventas (numero_factura, cliente_id, usuario_id, fecha_venta, subtotal, iva, total, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pagada')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $numeroFactura,
            $datos['cliente_id'] ?: null, // NULL si está vacío
            $_SESSION['user_id'], // usuario_id de la sesión actual
            $datos['fecha'],
            $datos['subtotal'],
            $datos['iva'],
            $datos['total']
        ]);
        
        $ventaId = $pdo->lastInsertId();
        
        // Guardar items de la venta
        foreach ($datos['items'] as $item) {
            $sql = "INSERT INTO venta_items (venta_id, producto_id, cantidad, precio_unitario, subtotal, total) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $ventaId,
                $item['producto_id'],
                $item['cantidad'],
                $item['precio'],
                $item['subtotal'],
                $item['subtotal'] // total = subtotal para items individuales
            ]);
            
            // Actualizar stock
            $sql = "UPDATE productos SET stock = stock - ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$item['cantidad'], $item['producto_id']]);
        }
        
        return ['success' => true, 'message' => 'Factura generada correctamente', 'numero' => $numeroFactura, 'factura_id' => $ventaId];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function guardarConfiguracion($clave, $valor, $tipo = 'string') {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "INSERT INTO config (clave, valor, tipo) VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE valor = VALUES(valor), tipo = VALUES(tipo)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$clave, $valor, $tipo]);
        
        return ['success' => true, 'message' => 'Configuración guardada correctamente'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function obtenerConfiguracion($clave) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "SELECT valor FROM config WHERE clave = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$clave]);
        
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado ? $resultado['valor'] : null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Calcular precio base sin IVA
 */
function calcularPrecioBase($precioFinal, $ivaPorcentaje) {
    if ($ivaPorcentaje == 0) {
        return $precioFinal;
    }
    
    return $precioFinal / (1 + ($ivaPorcentaje / 100));
}

/**
 * Calcular precio con IVA
 */
function calcularPrecioConIva($precioBase, $ivaPorcentaje) {
    return $precioBase * (1 + ($ivaPorcentaje / 100));
}

/**
 * Calcular IVA de un precio
 */
function calcularIva($precioBase, $ivaPorcentaje) {
    return $precioBase * ($ivaPorcentaje / 100);
}

/**
 * Obtener ambiente de FE seleccionado por el usuario
 */
function obtenerAmbienteFE($usuario_id) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("SELECT ambiente FROM fe_ambiente_seleccionado WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $resultado['ambiente'] ?? 'test'; // Por defecto 'test'
        
    } catch (PDOException $e) {
        return 'test'; // En caso de error, usar 'test'
    }
}

/**
 * Obtener configuración de FE para el ambiente seleccionado
 */
function obtenerConfiguracionFE($usuario_id) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $ambiente = obtenerAmbienteFE($usuario_id);
        
        $stmt = $pdo->prepare("SELECT * FROM fe_ambientes WHERE ambiente = ?");
        $stmt->execute([$ambiente]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $config ?: [];
        
    } catch (PDOException $e) {
        return [];
    }
}

?>
