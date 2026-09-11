<?php
session_start();

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Incluir clase de permisos
require_once '../app/Lib/Permissions.php';

// Verificar permisos para el módulo de productos
Permissions::requirePermission('read', 'productos');

// Obtener ambiente seleccionado para FE
require_once 'funciones.php';
$ambiente_seleccionado = obtenerAmbienteFE($_SESSION['user_id']);

// Conexión a la base de datos
try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Cargar productos con información completa
    $sql = "SELECT p.*, c.nombre as categoria, m.nombre as marca, pr.nombre as proveedor,
                   (p.stock * p.costo) as valor_costo, 
                   (p.stock * (p.precio * (1 + p.iva_porcentaje/100))) as valor_venta,
                   (p.precio * (1 + p.iva_porcentaje/100)) as precio_con_iva
            FROM productos p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            LEFT JOIN marcas m ON p.marca_id = m.id 
            LEFT JOIN proveedores pr ON p.proveedor_id = pr.id 
            WHERE p.estado = 'activo' 
            ORDER BY p.nombre";
    $productos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
    // Cargar categorías para filtros
    $categorias = $pdo->query("SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    
    // Cargar marcas para filtros
    $marcas = $pdo->query("SELECT id, nombre FROM marcas WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    
    // Cargar proveedores para filtros
    $proveedores = $pdo->query("SELECT id, nombre FROM proveedores WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas
    $totalProductos = count($productos);
    $stockBajo = $pdo->query("SELECT COUNT(*) FROM productos WHERE stock <= stock_minimo AND estado = 'activo'")->fetchColumn();
    $valorTotal = $pdo->query("SELECT SUM(stock * costo) as total FROM productos WHERE estado = 'activo'")->fetchColumn();
    $valorTotalVenta = $pdo->query("SELECT SUM(stock * (precio * (1 + iva_porcentaje/100))) as total FROM productos WHERE estado = 'activo'")->fetchColumn();
    
} catch (PDOException $e) {
    $error = "Error de conexión: " . $e->getMessage();
    $productos = [];
    $categorias = [];
    $marcas = [];
    $proveedores = [];
    $totalProductos = 0;
    $stockBajo = 0;
    $valorTotal = 0;
    $valorTotalVenta = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario - TECNOXPERT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .inventario-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .stats-card {
            transition: transform 0.2s;
            border: none;
            border-radius: 15px;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .producto-card {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .producto-card:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        .stock-bajo {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        .stock-ok {
            border-left-color: #28a745;
        }
        .btn-action {
            transition: all 0.3s ease;
        }
        .btn-action:hover {
            transform: translateY(-2px);
        }
        .filtros-section {
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #dee2e6;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .container-fluid {
                padding: 10px;
            }
            
            .stats-card {
                margin-bottom: 15px;
            }
            
            .table-responsive {
                font-size: 0.875rem;
            }
            
            .btn-group .btn {
                padding: 0.375rem 0.5rem;
                font-size: 0.8rem;
            }
            
            .filtros-section {
                margin-bottom: 15px;
            }
            
            .filtros-section .row > div {
                margin-bottom: 10px;
            }
        }
        
        @media (max-width: 576px) {
            .stats-card .card-body {
                padding: 1rem;
            }
            
            .stats-card h3 {
                font-size: 1.5rem;
            }
            
            .table th, .table td {
                padding: 0.5rem 0.25rem;
                font-size: 0.75rem;
            }
            
            .btn-group .btn {
                padding: 0.25rem 0.375rem;
                font-size: 0.7rem;
            }
            
            .modal-dialog {
                margin: 0.5rem;
            }
            
            .form-control, .form-select {
                font-size: 0.875rem;
            }
        }
        
        @media (max-width: 480px) {
            .table th, .table td {
                padding: 0.25rem;
                font-size: 0.7rem;
            }
            
            .btn-group .btn {
                padding: 0.2rem 0.3rem;
                font-size: 0.65rem;
            }
            
            .stats-card h3 {
                font-size: 1.25rem;
            }
            
            .stats-card small {
                font-size: 0.7rem;
            }
        }
        
        /* Estilos para ingreso masivo */
        #tablaIngresoMasivo {
            font-size: 0.875rem;
        }
        
        #tablaIngresoMasivo th {
            white-space: nowrap;
            font-size: 0.8rem;
            padding: 0.5rem 0.25rem;
        }
        
        #tablaIngresoMasivo td {
            padding: 0.5rem 0.25rem;
        }
        
        #tablaIngresoMasivo .form-control,
        #tablaIngresoMasivo .form-select {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        
        #tablaIngresoMasivo thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #212529 !important;
        }
        
        .sticky-top {
            position: sticky;
            top: 0;
            z-index: 10;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">TECNOXPERT - Inventarios</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Bienvenido, <?php echo $_SESSION['user_name']; ?>
                    <span class="badge bg-<?php echo $ambiente_seleccionado === 'test' ? 'info' : 'warning'; ?> ms-2">
                        <?php echo $ambiente_seleccionado === 'test' ? 'PRUEBAS' : 'PRODUCCIÓN'; ?>
                    </span>
                </span>
                <a class="nav-link" href="logout.php">Cerrar sesión</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>📦 Gestión de Inventario</h2>
                <p class="text-muted">Administrar productos y control de stock</p>
            </div>
            <div>
                <a href="../index.php" class="btn btn-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
                <a href="imprimir_inventario.php" target="_blank" class="btn btn-warning me-2">
                    <i class="bi bi-printer"></i> Imprimir Inventario
                </a>
                <?php if (Permissions::canCreate('productos')): ?>
                <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#modalProducto">
                    <i class="bi bi-plus-circle"></i> Nuevo Producto
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalIngresoMasivo">
                    <i class="bi bi-stack"></i> Ingreso Masivo
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Alertas para AJAX -->
        <div id="alertContainer"></div>
        
        <!-- KPIs del Inventario -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-box h3"></i>
                        <h4 class="mb-1"><?php echo number_format($totalProductos); ?></h4>
                        <small>Total Productos</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-danger text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-exclamation-triangle h3"></i>
                        <h4 class="mb-1"><?php echo number_format($stockBajo); ?></h4>
                        <small>Stock Bajo</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-currency-dollar h3"></i>
                        <h4 class="mb-1">$<?php echo number_format($valorTotal, 0, ',', '.'); ?></h4>
                        <small>Valor al Costo</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-warning text-dark">
                    <div class="card-body text-center">
                        <i class="bi bi-cash-stack h3"></i>
                        <h4 class="mb-1">$<?php echo number_format($valorTotalVenta, 0, ',', '.'); ?></h4>
                        <small>Valor Total a Venta</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-info text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-graph-up h3"></i>
                        <h4 class="mb-1"><?php echo number_format(count($categorias)); ?></h4>
                        <small>Categorías</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtros Avanzados -->
        <div class="filtros-section p-3 mb-4">
            <h5 class="mb-3">
                <i class="bi bi-funnel"></i> Filtros de Búsqueda
            </h5>
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">Categoría</label>
                    <select class="form-select" id="filtroCategoria">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $categoria): ?>
                        <option value="<?php echo $categoria['id']; ?>"><?php echo htmlspecialchars($categoria['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Marca</label>
                    <select class="form-select" id="filtroMarca">
                        <option value="">Todas las marcas</option>
                        <?php foreach ($marcas as $marca): ?>
                        <option value="<?php echo $marca['id']; ?>"><?php echo htmlspecialchars($marca['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Proveedor</label>
                    <select class="form-select" id="filtroProveedor">
                        <option value="">Todos los proveedores</option>
                        <?php foreach ($proveedores as $proveedor): ?>
                        <option value="<?php echo $proveedor['id']; ?>"><?php echo htmlspecialchars($proveedor['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado Stock</label>
                    <select class="form-select" id="filtroStock">
                        <option value="">Todos</option>
                        <option value="bajo">Stock Bajo</option>
                        <option value="ok">Stock OK</option>
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary" onclick="aplicarFiltros()">
                    <i class="bi bi-search"></i> Aplicar Filtros
                </button>
                <button class="btn btn-outline-secondary" onclick="limpiarFiltros()">
                    <i class="bi bi-arrow-clockwise"></i> Limpiar
                </button>
                <button class="btn btn-outline-success" onclick="exportarInventario()">
                    <i class="bi bi-download"></i> Exportar
                </button>
            </div>
        </div>
        
        <!-- Lista de Productos -->
        <div class="inventario-container p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4>
                    <i class="bi bi-list-ul"></i> Productos del Inventario
                </h4>
                <div class="input-group" style="width: 300px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="buscarProducto" placeholder="Buscar productos...">
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Producto</th>
                            <th>Referencia</th>
                            <th>Categoría</th>
                            <th>Marca</th>
                            <th>Stock</th>
                            <th>Precio</th>
                            <th>Valor Venta con IVA</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaProductos">
                        <?php foreach ($productos as $producto): ?>
                        <tr class="producto-card <?php echo $producto['stock'] <= $producto['stock_minimo'] ? 'stock-bajo' : 'stock-ok'; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($producto['proveedor'] ?? 'Sin proveedor'); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($producto['referencia']); ?></td>
                            <td><?php echo htmlspecialchars($producto['categoria'] ?? 'Sin categoría'); ?></td>
                            <td><?php echo htmlspecialchars($producto['marca'] ?? 'Sin marca'); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $producto['stock'] <= $producto['stock_minimo'] ? 'danger' : 'success'; ?>">
                                    <?php echo number_format($producto['stock']); ?>
                                </span>
                                <br><small class="text-muted">Mín: <?php echo $producto['stock_minimo']; ?></small>
                            </td>
                            <td>$<?php echo number_format($producto['precio_con_iva'], 0, ',', '.'); ?></td>
                            <td>$<?php echo number_format($producto['valor_venta'], 0, ',', '.'); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $producto['stock'] <= $producto['stock_minimo'] ? 'warning' : 'success'; ?>">
                                    <?php echo $producto['stock'] <= $producto['stock_minimo'] ? 'Stock Bajo' : 'Disponible'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <?php if (Permissions::canEdit('productos')): ?>
                                    <button class="btn btn-outline-primary btn-action" onclick="editarProducto(<?php echo $producto['id']; ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-outline-info btn-action" onclick="verDetalles(<?php echo $producto['id']; ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    
                                    <?php if (Permissions::canEdit('productos')): ?>
                                    <button class="btn btn-outline-warning btn-action" onclick="ajustarStock(<?php echo $producto['id']; ?>)">
                                        <i class="bi bi-box-arrow-in-down"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (Permissions::canDelete('productos')): ?>
                                    <button class="btn btn-outline-danger btn-action" onclick="eliminarProducto(<?php echo $producto['id']; ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Nuevo/Editar Producto -->
    <div class="modal fade" id="modalProducto" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formProducto">
                        <input type="hidden" id="producto_id" name="producto_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nombre del Producto</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Referencia/SKU</label>
                                    <input type="text" class="form-control" id="referencia" name="referencia" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Categoría</label>
                                    <select class="form-select" id="categoria_id" name="categoria_id" required>
                                        <option value="">Seleccionar categoría</option>
                                        <?php foreach ($categorias as $categoria): ?>
                                        <option value="<?php echo $categoria['id']; ?>"><?php echo htmlspecialchars($categoria['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Marca</label>
                                    <select class="form-select" id="marca_id" name="marca_id" required>
                                        <option value="">Seleccionar marca</option>
                                        <?php foreach ($marcas as $marca): ?>
                                        <option value="<?php echo $marca['id']; ?>"><?php echo htmlspecialchars($marca['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Proveedor</label>
                                    <select class="form-select" id="proveedor_id" name="proveedor_id">
                                        <option value="">Seleccionar proveedor</option>
                                        <?php foreach ($proveedores as $proveedor): ?>
                                        <option value="<?php echo $proveedor['id']; ?>"><?php echo htmlspecialchars($proveedor['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Costo</label>
                                    <input type="number" class="form-control" id="costo" name="costo" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Precio de Venta</label>
                                    <input type="number" class="form-control" id="precio" name="precio" step="0.01" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Stock Actual</label>
                                    <input type="number" class="form-control" id="stock" name="stock" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Stock Mínimo</label>
                                    <input type="number" class="form-control" id="stock_minimo" name="stock_minimo" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">IVA (%)</label>
                                    <input type="number" class="form-control" id="iva_porcentaje" name="iva_porcentaje" value="19" min="0" max="100">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarProducto()">Guardar Producto</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ingreso Masivo -->
    <div class="modal fade" id="modalIngresoMasivo" tabindex="-1">
        <div class="modal-dialog modal-fullscreen-lg-down modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-stack"></i> Ingreso Masivo de Productos
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Instrucciones:</strong> Cada fila representa un producto. Puedes agregar o eliminar filas según necesites. 
                        Completa todos los campos requeridos antes de guardar.
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <button type="button" class="btn btn-primary btn-sm" onclick="agregarFilaProducto()">
                                <i class="bi bi-plus-circle"></i> Agregar Fila
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" onclick="eliminarFilaSeleccionada()">
                                <i class="bi bi-trash"></i> Eliminar Fila Seleccionada
                            </button>
                        </div>
                        <div>
                            <span class="badge bg-secondary" id="contadorFilas">0 productos</span>
                        </div>
                    </div>
                    
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-bordered table-hover" id="tablaIngresoMasivo">
                            <thead class="table-dark sticky-top">
                                <tr>
                                    <th style="width: 30px;">
                                        <input type="checkbox" id="seleccionarTodos" onchange="seleccionarTodosFilas()">
                                    </th>
                                    <th>Nombre</th>
                                    <th>Referencia</th>
                                    <th>Categoría</th>
                                    <th>Marca</th>
                                    <th>Proveedor</th>
                                    <th>Costo</th>
                                    <th>Precio</th>
                                    <th>Stock</th>
                                    <th>Stock Mín.</th>
                                    <th>IVA (%)</th>
                                    <th>Descripción</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyIngresoMasivo">
                                <!-- Las filas se agregarán dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="guardarIngresoMasivo()">
                        <i class="bi bi-save"></i> Guardar Todos los Productos
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function mostrarAlerta(mensaje, tipo = 'success') {
            const alertContainer = document.getElementById('alertContainer');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${tipo} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${mensaje}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            alertContainer.appendChild(alertDiv);
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
        
        function guardarProducto() {
            const form = document.getElementById('formProducto');
            const formData = new FormData(form);
            formData.append('action', 'guardar');
            
            fetch('ajax_productos.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta(data.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al guardar el producto', 'danger');
            });
        }
        
        function editarProducto(id) {
            fetch('ajax_editar_producto.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=obtener&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const producto = data.data;
                    document.getElementById('modalTitle').textContent = 'Editar Producto';
                    document.getElementById('producto_id').value = producto.id;
                    document.getElementById('nombre').value = producto.nombre;
                    document.getElementById('referencia').value = producto.referencia;
                    document.getElementById('categoria_id').value = producto.categoria_id;
                    document.getElementById('marca_id').value = producto.marca_id;
                    document.getElementById('proveedor_id').value = producto.proveedor_id;
                    document.getElementById('costo').value = producto.costo;
                    document.getElementById('precio').value = producto.precio;
                    document.getElementById('stock').value = producto.stock;
                    document.getElementById('stock_minimo').value = producto.stock_minimo;
                    document.getElementById('iva_porcentaje').value = producto.iva_porcentaje;
                    document.getElementById('descripcion').value = producto.descripcion;
                    
                    new bootstrap.Modal(document.getElementById('modalProducto')).show();
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            });
        }
        
        function eliminarProducto(id) {
            if (confirm('¿Está seguro de eliminar este producto?')) {
                fetch('ajax_productos.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=eliminar&id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarAlerta(data.message, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        mostrarAlerta(data.message, 'danger');
                    }
                });
            }
        }
        
        function verDetalles(id) {
            fetch('ajax_productos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=obtener&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const producto = data.producto;
                    const detalles = `
                        <strong>${producto.nombre}</strong><br>
                        <strong>Referencia:</strong> ${producto.referencia}<br>
                        <strong>Stock:</strong> ${producto.stock}<br>
                        <strong>Stock Mínimo:</strong> ${producto.stock_minimo}<br>
                        <strong>Costo:</strong> $${Number(producto.costo).toLocaleString()}<br>
                        <strong>Precio:</strong> $${Number(producto.precio_con_iva).toLocaleString()}<br>
                        <strong>IVA:</strong> ${producto.iva_porcentaje}%<br>
                        <strong>Descripción:</strong> ${producto.descripcion || 'Sin descripción'}
                    `;
                    mostrarAlerta(detalles, 'info');
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            });
        }
        
        function ajustarStock(id) {
            const cantidad = prompt('Ingrese la cantidad para ajustar:');
            if (cantidad === null) return;
            
            const tipo = prompt('Tipo de ajuste (entrada/salida/ajuste):', 'entrada');
            if (tipo === null) return;
            
            const motivo = prompt('Motivo del ajuste:', 'Ajuste manual');
            if (motivo === null) return;
            
            fetch('ajax_productos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=ajustar_stock&id=${id}&cantidad=${cantidad}&tipo=${tipo}&motivo=${motivo}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta(data.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            });
        }
        
        function aplicarFiltros() {
            const categoria = document.getElementById('filtroCategoria').value;
            const marca = document.getElementById('filtroMarca').value;
            const proveedor = document.getElementById('filtroProveedor').value;
            const stock = document.getElementById('filtroStock').value;
            
            fetch('ajax_productos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=exportar&categoria=${categoria}&marca=${marca}&proveedor=${proveedor}&stock=${stock}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta('Filtros aplicados correctamente', 'success');
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            });
        }
        
        function limpiarFiltros() {
            document.getElementById('filtroCategoria').value = '';
            document.getElementById('filtroMarca').value = '';
            document.getElementById('filtroProveedor').value = '';
            document.getElementById('filtroStock').value = '';
            mostrarAlerta('Filtros limpiados', 'info');
        }
        
        function exportarInventario() {
            const categoria = document.getElementById('filtroCategoria').value;
            const marca = document.getElementById('filtroMarca').value;
            const proveedor = document.getElementById('filtroProveedor').value;
            const stock = document.getElementById('filtroStock').value;
            
            mostrarAlerta('Exportando inventario...', 'info');
            
            fetch('ajax_productos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=exportar&categoria=${categoria}&marca=${marca}&proveedor=${proveedor}&stock=${stock}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta(data.message, 'success');
                    if (data.archivo) {
                        setTimeout(() => {
                            window.open(`../temp/${data.archivo}`, '_blank');
                        }, 1000);
                    }
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            });
        }
        
        // Búsqueda en tiempo real
        document.getElementById('buscarProducto').addEventListener('input', function() {
            const busqueda = this.value.toLowerCase();
            const filas = document.querySelectorAll('#tablaProductos tr');
            
            filas.forEach(fila => {
                const texto = fila.textContent.toLowerCase();
                fila.style.display = texto.includes(busqueda) ? '' : 'none';
            });
        });
        
        // Limpiar modal al cerrar
        document.getElementById('modalProducto').addEventListener('hidden.bs.modal', function() {
            document.getElementById('formProducto').reset();
            document.getElementById('modalTitle').textContent = 'Nuevo Producto';
            document.getElementById('producto_id').value = '';
        });
        
        // ========== FUNCIONES PARA INGRESO MASIVO ==========
        
        // Categorías, marcas y proveedores para los selects
        const categorias = <?php echo json_encode($categorias); ?>;
        const marcas = <?php echo json_encode($marcas); ?>;
        const proveedores = <?php echo json_encode($proveedores); ?>;
        
        let contadorFilas = 0;
        
        function agregarFilaProducto() {
            contadorFilas++;
            const tbody = document.getElementById('tbodyIngresoMasivo');
            const fila = document.createElement('tr');
            fila.id = `fila-${contadorFilas}`;
            fila.innerHTML = `
                <td>
                    <input type="checkbox" class="fila-checkbox" onchange="actualizarContador()">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm" name="nombre[]" required>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm" name="referencia[]" required>
                </td>
                <td>
                    <select class="form-select form-select-sm" name="categoria_id[]" required>
                        <option value="">Seleccionar</option>
                        ${categorias.map(cat => `<option value="${cat.id}">${cat.nombre}</option>`).join('')}
                    </select>
                </td>
                <td>
                    <select class="form-select form-select-sm" name="marca_id[]" required>
                        <option value="">Seleccionar</option>
                        ${marcas.map(marca => `<option value="${marca.id}">${marca.nombre}</option>`).join('')}
                    </select>
                </td>
                <td>
                    <select class="form-select form-select-sm" name="proveedor_id[]">
                        <option value="">Seleccionar</option>
                        ${proveedores.map(prov => `<option value="${prov.id}">${prov.nombre}</option>`).join('')}
                    </select>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm" name="costo[]" step="0.01" required>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm" name="precio[]" step="0.01" required>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm" name="stock[]" min="0" required>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm" name="stock_minimo[]" min="0" required>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm" name="iva_porcentaje[]" value="19" min="0" max="100">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm" name="descripcion[]">
                </td>
            `;
            tbody.appendChild(fila);
            actualizarContador();
        }
        
        function eliminarFilaSeleccionada() {
            const checkboxes = document.querySelectorAll('.fila-checkbox:checked');
            if (checkboxes.length === 0) {
                mostrarAlerta('Por favor, selecciona al menos una fila para eliminar', 'warning');
                return;
            }
            
            if (confirm(`¿Estás seguro de eliminar ${checkboxes.length} fila(s)?`)) {
                checkboxes.forEach(checkbox => {
                    checkbox.closest('tr').remove();
                });
                actualizarContador();
            }
        }
        
        function seleccionarTodosFilas() {
            const checkboxTodos = document.getElementById('seleccionarTodos');
            const checkboxes = document.querySelectorAll('.fila-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = checkboxTodos.checked;
            });
            actualizarContador();
        }
        
        function actualizarContador() {
            const totalFilas = document.querySelectorAll('#tbodyIngresoMasivo tr').length;
            document.getElementById('contadorFilas').textContent = `${totalFilas} producto(s)`;
        }
        
        function guardarIngresoMasivo() {
            const filas = document.querySelectorAll('#tbodyIngresoMasivo tr');
            
            if (filas.length === 0) {
                mostrarAlerta('No hay productos para guardar. Agrega al menos una fila.', 'warning');
                return;
            }
            
            // Validar que todas las filas tengan los campos requeridos
            let productos = [];
            let hayErrores = false;
            let mensajeError = '';
            
            filas.forEach((fila, index) => {
                const nombre = fila.querySelector('input[name="nombre[]"]').value.trim();
                const referencia = fila.querySelector('input[name="referencia[]"]').value.trim();
                const categoria_id = fila.querySelector('select[name="categoria_id[]"]').value;
                const marca_id = fila.querySelector('select[name="marca_id[]"]').value;
                const costo = fila.querySelector('input[name="costo[]"]').value;
                const precio = fila.querySelector('input[name="precio[]"]').value;
                const stock = fila.querySelector('input[name="stock[]"]').value;
                const stock_minimo = fila.querySelector('input[name="stock_minimo[]"]').value;
                
                // Validar campos requeridos
                if (!nombre || !referencia || !categoria_id || !marca_id || !costo || !precio || !stock || !stock_minimo) {
                    hayErrores = true;
                    mensajeError += `Fila ${index + 1}: Faltan campos requeridos. `;
                    fila.style.backgroundColor = '#ffebee';
                } else {
                    fila.style.backgroundColor = '';
                    productos.push({
                        nombre: nombre,
                        referencia: referencia,
                        categoria_id: categoria_id,
                        marca_id: marca_id,
                        proveedor_id: fila.querySelector('select[name="proveedor_id[]"]').value || null,
                        costo: parseFloat(costo),
                        precio: parseFloat(precio),
                        stock: parseInt(stock),
                        stock_minimo: parseInt(stock_minimo),
                        iva_porcentaje: parseFloat(fila.querySelector('input[name="iva_porcentaje[]"]').value) || 19,
                        descripcion: fila.querySelector('input[name="descripcion[]"]').value.trim() || ''
                    });
                }
            });
            
            if (hayErrores) {
                mostrarAlerta(mensajeError, 'danger');
                return;
            }
            
            if (productos.length === 0) {
                mostrarAlerta('No hay productos válidos para guardar', 'warning');
                return;
            }
            
            // Confirmar antes de guardar
            if (!confirm(`¿Estás seguro de guardar ${productos.length} producto(s)?`)) {
                return;
            }
            
            mostrarAlerta('Guardando productos...', 'info');
            
            fetch('ajax_productos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'ingreso_masivo',
                    productos: productos
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let mensaje = `¡Éxito! Se guardaron ${data.guardados} producto(s) correctamente.`;
                    if (data.errores > 0) {
                        mensaje += `\nErrores: ${data.errores}`;
                        if (data.detalles_errores && data.detalles_errores.length > 0) {
                            mensaje += '\n\nDetalles:\n' + data.detalles_errores.join('\n');
                        }
                    }
                    mostrarAlerta(mensaje, data.errores > 0 ? 'warning' : 'success');
                    setTimeout(() => {
                        location.reload();
                    }, data.errores > 0 ? 5000 : 2000);
                } else {
                    let mensajeError = data.message || 'Error al guardar los productos';
                    if (data.detalles_errores && data.detalles_errores.length > 0) {
                        mensajeError += '\n\nDetalles:\n' + data.detalles_errores.join('\n');
                    }
                    mostrarAlerta(mensajeError, 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al guardar los productos: ' + error.message, 'danger');
            });
        }
        
        // Limpiar tabla al cerrar el modal de ingreso masivo
        document.getElementById('modalIngresoMasivo').addEventListener('hidden.bs.modal', function() {
            document.getElementById('tbodyIngresoMasivo').innerHTML = '';
            contadorFilas = 0;
            actualizarContador();
            document.getElementById('seleccionarTodos').checked = false;
        });
        
        // Agregar una fila inicial al abrir el modal
        document.getElementById('modalIngresoMasivo').addEventListener('shown.bs.modal', function() {
            if (document.querySelectorAll('#tbodyIngresoMasivo tr').length === 0) {
                agregarFilaProducto();
            }
        });
    </script>
</body>
</html>
