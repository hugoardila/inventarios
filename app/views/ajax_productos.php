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

// Leer action desde POST o desde JSON body
$input = file_get_contents('php://input');
$dataJson = json_decode($input, true);
$action = $_POST['action'] ?? $dataJson['action'] ?? '';

switch ($action) {
    case 'guardar':
        // Verificar permisos para crear/editar productos
        if (!Permissions::canCreate('productos') && !Permissions::canEdit('productos')) {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para realizar esta acción']);
            exit();
        }
        
        $producto_id = $_POST['producto_id'] ?? 0;
        $referencia = $_POST['referencia'] ?? '';
        
        // Verificar si la referencia ya existe en otro producto
        try {
            $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            if (!empty($referencia)) {
                $sql = "SELECT id FROM productos WHERE referencia = ?";
                $params = [$referencia];
                
                // Si estamos editando, excluir el producto actual
                if ($producto_id > 0) {
                    $sql .= " AND id != ?";
                    $params[] = $producto_id;
                }
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => false, 'message' => 'La referencia "' . $referencia . '" ya existe en otro producto']);
                    exit();
                }
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error verificando referencia: ' . $e->getMessage()]);
            exit();
        }
        
        $datos = [
            'producto_id' => $producto_id,
            'nombre' => $_POST['nombre'] ?? '',
            'referencia' => $referencia,
            'descripcion' => $_POST['descripcion'] ?? '',
            'categoria_id' => $_POST['categoria_id'] ?? null,
            'marca_id' => $_POST['marca_id'] ?? null,
            'proveedor_id' => $_POST['proveedor_id'] ?? null,
            'costo' => $_POST['costo'] ?? 0,
            'precio' => $_POST['precio'] ?? 0,
            'stock' => $_POST['stock'] ?? 0,
            'stock_minimo' => $_POST['stock_minimo'] ?? 0,
            'iva_porcentaje' => $_POST['iva_porcentaje'] ?? 19,
            'unidad_medida' => $_POST['unidad_medida'] ?? 'UN',
            'ubicacion' => $_POST['ubicacion'] ?? '',
            'estado' => 'activo'
        ];
        
        $resultado = guardarProducto($datos);
        echo json_encode($resultado);
        break;
        
    case 'eliminar':
        // Verificar permisos para eliminar productos
        if (!Permissions::canDelete('productos')) {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para eliminar productos']);
            exit();
        }
        
        $id = $_POST['id'] ?? 0;
        $resultado = eliminarRegistro('productos', $id);
        echo json_encode($resultado);
        break;
        
    case 'obtener':
        $id = $_POST['id'] ?? 0;
        try {
            $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
            $stmt->execute([$id]);
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($producto) {
                echo json_encode(['success' => true, 'producto' => $producto]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    case 'actualizar':
        $id = $_POST['producto_id'] ?? 0;
        $datos = [
            'nombre' => $_POST['nombre'] ?? '',
            'referencia' => $_POST['referencia'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? '',
            'categoria_id' => $_POST['categoria_id'] ?? null,
            'marca_id' => $_POST['marca_id'] ?? null,
            'proveedor_id' => $_POST['proveedor_id'] ?? null,
            'costo' => $_POST['costo'] ?? 0,
            'precio' => $_POST['precio'] ?? 0,
            'stock' => $_POST['stock'] ?? 0,
            'stock_minimo' => $_POST['stock_minimo'] ?? 0,
            'iva_porcentaje' => $_POST['iva_porcentaje'] ?? 19,
            'unidad_medida' => $_POST['unidad_medida'] ?? 'UN',
            'ubicacion' => $_POST['ubicacion'] ?? ''
        ];
        
        $resultado = actualizarProducto($id, $datos);
        echo json_encode($resultado);
        break;
        
    case 'ajustar_stock':
        $id = $_POST['id'] ?? 0;
        $cantidad = $_POST['cantidad'] ?? 0;
        $tipo = $_POST['tipo'] ?? 'entrada'; // entrada, salida, ajuste
        $motivo = $_POST['motivo'] ?? '';
        
        $resultado = ajustarStock($id, $cantidad, $tipo, $motivo);
        echo json_encode($resultado);
        break;
        
    case 'ingreso_masivo':
        // Verificar permisos para crear productos
        if (!Permissions::canCreate('productos')) {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para realizar esta acción']);
            exit();
        }
        
        // Leer datos JSON del body (ya leído arriba)
        if (!isset($dataJson['productos']) || !is_array($dataJson['productos'])) {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos. No se recibieron productos.']);
            exit();
        }
        
        $resultado = guardarProductosMasivo($dataJson['productos']);
        echo json_encode($resultado);
        break;
        
    case 'exportar':
        $filtros = [
            'categoria' => $_POST['categoria'] ?? '',
            'marca' => $_POST['marca'] ?? '',
            'proveedor' => $_POST['proveedor'] ?? '',
            'stock' => $_POST['stock'] ?? ''
        ];
        
        $resultado = exportarInventario($filtros);
        echo json_encode($resultado);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

function actualizarProducto($id, $datos) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "UPDATE productos SET 
                nombre = ?, referencia = ?, descripcion = ?, categoria_id = ?, 
                marca_id = ?, proveedor_id = ?, costo = ?, precio = ?, 
                stock = ?, stock_minimo = ?, iva_porcentaje = ?, 
                unidad_medida = ?, ubicacion = ?, actualizado_en = NOW() 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $datos['nombre'], $datos['referencia'], $datos['descripcion'], 
            $datos['categoria_id'], $datos['marca_id'], $datos['proveedor_id'],
            $datos['costo'], $datos['precio'], $datos['stock'], 
            $datos['stock_minimo'], $datos['iva_porcentaje'], 
            $datos['unidad_medida'], $datos['ubicacion'], $id
        ]);
        
        return ['success' => true, 'message' => 'Producto actualizado correctamente'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function ajustarStock($id, $cantidad, $tipo, $motivo) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $pdo->beginTransaction();
        
        // Obtener stock actual
        $stmt = $pdo->prepare("SELECT stock FROM productos WHERE id = ?");
        $stmt->execute([$id]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$producto) {
            throw new Exception('Producto no encontrado');
        }
        
        $stock_actual = $producto['stock'];
        $nuevo_stock = $stock_actual;
        
        switch ($tipo) {
            case 'entrada':
                $nuevo_stock += $cantidad;
                break;
            case 'salida':
                $nuevo_stock -= $cantidad;
                if ($nuevo_stock < 0) {
                    throw new Exception('Stock insuficiente');
                }
                break;
            case 'ajuste':
                $nuevo_stock = $cantidad;
                break;
        }
        
        // Actualizar stock
        $stmt = $pdo->prepare("UPDATE productos SET stock = ?, actualizado_en = NOW() WHERE id = ?");
        $stmt->execute([$nuevo_stock, $id]);
        
        // Registrar movimiento
        $stmt = $pdo->prepare("INSERT INTO inventario_movimientos 
                              (producto_id, tipo, cantidad, stock_anterior, stock_nuevo, motivo, usuario_id, fecha_movimiento) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$id, $tipo, $cantidad, $stock_actual, $nuevo_stock, $motivo, $_SESSION['user_id']]);
        
        $pdo->commit();
        
        return ['success' => true, 'message' => 'Stock ajustado correctamente', 'nuevo_stock' => $nuevo_stock];
    } catch (Exception $e) {
        if (isset($pdo)) $pdo->rollBack();
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function exportarInventario($filtros) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $where = "WHERE p.estado = 'activo'";
        $params = [];
        
        if (!empty($filtros['categoria'])) {
            $where .= " AND p.categoria_id = ?";
            $params[] = $filtros['categoria'];
        }
        
        if (!empty($filtros['marca'])) {
            $where .= " AND p.marca_id = ?";
            $params[] = $filtros['marca'];
        }
        
        if (!empty($filtros['proveedor'])) {
            $where .= " AND p.proveedor_id = ?";
            $params[] = $filtros['proveedor'];
        }
        
        if (!empty($filtros['stock'])) {
            if ($filtros['stock'] === 'bajo') {
                $where .= " AND p.stock <= p.stock_minimo";
            }
        }
        
        $sql = "SELECT p.*, c.nombre as categoria, m.nombre as marca, pr.nombre as proveedor
                FROM productos p 
                LEFT JOIN categorias c ON p.categoria_id = c.id 
                LEFT JOIN marcas m ON p.marca_id = m.id 
                LEFT JOIN proveedores pr ON p.proveedor_id = pr.id 
                $where 
                ORDER BY p.nombre";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generar archivo CSV
        $timestamp = date('Y-m-d_H-i-s');
        $archivo = "../temp/inventario_$timestamp.csv";
        
        if (!is_dir('../temp')) {
            mkdir('../temp', 0755, true);
        }
        
        $handle = fopen($archivo, 'w');
        fputcsv($handle, ['ID', 'Nombre', 'Referencia', 'Categoría', 'Marca', 'Proveedor', 'Stock', 'Stock Mínimo', 'Costo', 'Precio', 'IVA %', 'Ubicación']);
        
        foreach ($productos as $producto) {
            fputcsv($handle, [
                $producto['id'],
                $producto['nombre'],
                $producto['referencia'],
                $producto['categoria'] ?? 'Sin categoría',
                $producto['marca'] ?? 'Sin marca',
                $producto['proveedor'] ?? 'Sin proveedor',
                $producto['stock'],
                $producto['stock_minimo'],
                $producto['costo'],
                $producto['precio'],
                $producto['iva_porcentaje'],
                $producto['ubicacion']
            ]);
        }
        fclose($handle);
        
        return ['success' => true, 'message' => 'Inventario exportado correctamente', 'archivo' => basename($archivo)];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function guardarProductosMasivo($productos) {
    try {
        $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $pdo->beginTransaction();
        
        $guardados = 0;
        $errores = 0;
        $mensajesError = [];
        
        $sql = "INSERT INTO productos (nombre, referencia, descripcion, categoria_id, marca_id, 
                proveedor_id, costo, precio, stock, stock_minimo, iva_porcentaje, 
                unidad_medida, ubicacion, estado, creado_en) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        
        foreach ($productos as $index => $producto) {
            try {
                // Validar que la referencia no exista
                $stmtCheck = $pdo->prepare("SELECT id FROM productos WHERE referencia = ?");
                $stmtCheck->execute([$producto['referencia']]);
                
                if ($stmtCheck->rowCount() > 0) {
                    $errores++;
                    $mensajesError[] = "Fila " . ($index + 1) . ": La referencia '{$producto['referencia']}' ya existe";
                    continue;
                }
                
                // Validar campos requeridos
                if (empty($producto['nombre']) || empty($producto['referencia']) || 
                    empty($producto['categoria_id']) || empty($producto['marca_id'])) {
                    $errores++;
                    $mensajesError[] = "Fila " . ($index + 1) . ": Faltan campos requeridos";
                    continue;
                }
                
                // Insertar producto
                $stmt->execute([
                    $producto['nombre'],
                    $producto['referencia'],
                    $producto['descripcion'] ?? '',
                    $producto['categoria_id'],
                    $producto['marca_id'],
                    $producto['proveedor_id'] ?? null,
                    $producto['costo'] ?? 0,
                    $producto['precio'] ?? 0,
                    $producto['stock'] ?? 0,
                    $producto['stock_minimo'] ?? 0,
                    $producto['iva_porcentaje'] ?? 19,
                    'UN', // unidad_medida por defecto
                    '', // ubicacion
                    'activo'
                ]);
                
                $guardados++;
            } catch (PDOException $e) {
                $errores++;
                $mensajesError[] = "Fila " . ($index + 1) . ": " . $e->getMessage();
            }
        }
        
        if ($guardados > 0) {
            $pdo->commit();
            $mensaje = "Se guardaron {$guardados} producto(s) correctamente";
            if ($errores > 0) {
                $mensaje .= ". Errores: {$errores}";
            }
            return [
                'success' => true,
                'message' => $mensaje,
                'guardados' => $guardados,
                'errores' => $errores,
                'detalles_errores' => $mensajesError
            ];
        } else {
            $pdo->rollBack();
            return [
                'success' => false,
                'message' => 'No se pudo guardar ningún producto. Errores: ' . implode(', ', $mensajesError),
                'guardados' => 0,
                'errores' => $errores,
                'detalles_errores' => $mensajesError
            ];
        }
    } catch (PDOException $e) {
        if (isset($pdo)) $pdo->rollBack();
        return [
            'success' => false,
            'message' => 'Error general: ' . $e->getMessage(),
            'guardados' => 0,
            'errores' => count($productos)
        ];
    }
}
?>

