<?php
session_start();

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Incluir clase de permisos
require_once '../app/Lib/Permissions.php';

// Verificar permisos para el módulo de facturación
Permissions::requirePermission('read', 'ventas');

// Obtener ambiente seleccionado para FE
require_once 'funciones.php';
$ambiente_seleccionado = obtenerAmbienteFE($_SESSION['user_id']);

// Conexión a la base de datos
try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Cargar clientes activos
    $stmt = $pdo->query("SELECT id, nombre, tipo_documento, numero_documento FROM clientes WHERE activo = 1 ORDER BY nombre");
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Cargar productos activos
    $stmt = $pdo->query("SELECT id, nombre, referencia, precio, stock FROM productos WHERE estado = 'activo' ORDER BY nombre");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Cargar configuración de impuestos
    $stmt = $pdo->query("SELECT * FROM config WHERE clave IN ('iva', 'retefuente', 'reteiva', 'reteica')");
    $config_impuestos = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $config_impuestos[$row['clave']] = $row['valor'];
    }
    
    // Cargar datos de empresa
    $stmt = $pdo->query("SELECT * FROM config WHERE clave IN ('nombre_empresa', 'nit', 'direccion', 'ciudad', 'telefono')");
    $datos_empresa = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $datos_empresa[$row['clave']] = $row['valor'];
    }
    
} catch (PDOException $e) {
    $error = "Error de conexión: " . $e->getMessage();
    $clientes = [];
    $productos = [];
    $config_impuestos = [];
    $datos_empresa = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facturación - TECNOXPERT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .factura-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .producto-item {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
        }
        .totales-section {
            background: #e9ecef;
            border-radius: 8px;
        }
        .btn-action {
            transition: all 0.3s ease;
        }
        .btn-action:hover {
            transform: translateY(-2px);
        }
        
        /* Estilos para previsualización */
        .preview-factura {
            font-family: Arial, sans-serif;
            max-width: 100%;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
        }
        
        .preview-header {
            text-align: center;
            border-bottom: 2px solid #007bff;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .preview-header h3 {
            color: #007bff;
            margin-bottom: 10px;
        }
        
        .preview-empresa {
            font-size: 18px;
            color: #333;
        }
        
        .preview-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        .preview-cliente, .preview-fecha {
            font-size: 14px;
        }
        
        .preview-items h4 {
            color: #333;
            margin-bottom: 15px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        .preview-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .preview-table th, .preview-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .preview-table th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        
        .preview-table .cantidad, .preview-table .precio, .preview-table .subtotal {
            text-align: right;
        }
        
        .preview-totales {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .preview-total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            padding: 3px 0;
        }
        
        .preview-total-final {
            display: flex;
            justify-content: space-between;
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
            border-top: 2px solid #007bff;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .preview-actions {
            text-align: center;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }
        
        .preview-actions .btn {
            margin: 0 5px;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .container-fluid {
                padding: 10px;
            }
            
            .factura-container {
                margin-bottom: 15px;
            }
            
            .table-responsive {
                font-size: 0.875rem;
            }
            
            .btn-group .btn {
                padding: 0.375rem 0.5rem;
                font-size: 0.8rem;
            }
            
            .form-control, .form-select {
                font-size: 0.875rem;
            }
        }
        
        @media (max-width: 576px) {
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
            
            .preview-table th, .preview-table td {
                padding: 0.5rem 0.25rem;
                font-size: 0.8rem;
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
            
            .preview-table th, .preview-table td {
                padding: 0.25rem;
                font-size: 0.7rem;
            }
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
                <h2>🧾 Sistema de Facturación</h2>
                <p class="text-muted">Crear y gestionar facturas de venta</p>
            </div>
            <div>
                <a href="../index.php" class="btn btn-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
                <a href="facturas.php" class="btn btn-info me-2">
                    <i class="bi bi-list-ul"></i> Ver Facturas
                </a>
                <a href="facturacion_electronica.php" class="btn btn-outline-primary">
                    <i class="bi bi-lightning"></i> Facturación Electrónica
                </a>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Alertas para AJAX -->
        <div id="alertContainer"></div>
        
        <div class="row">
            <!-- Formulario de Facturación -->
            <div class="col-lg-8">
                <div class="factura-container p-4">
                    <h4 class="mb-4">
                        <i class="bi bi-receipt"></i> Nueva Factura
                    </h4>
                    
                    <form id="formFactura">
                        <!-- Datos del Cliente -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Cliente</label>
                                <select class="form-select" id="cliente_id" name="cliente_id" required>
                                    <option value="">Seleccionar cliente...</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?php echo $cliente['id']; ?>">
                                        <?php echo htmlspecialchars($cliente['nombre']); ?> 
                                        (<?php echo $cliente['tipo_documento']; ?>: <?php echo $cliente['numero_documento']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fecha de Factura</label>
                                <input type="date" class="form-control" id="fecha" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <!-- Productos -->
                        <div class="mb-4">
                            <h5>Productos</h5>
                            <div id="productos-container">
                                <div class="producto-item p-3 mb-3">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label">Producto</label>
                                            <select class="form-select producto-select" name="productos[]" required>
                                                <option value="">Seleccionar producto...</option>
                                                <?php foreach ($productos as $producto): ?>
                                                <option value="<?php echo $producto['id']; ?>" 
                                                        data-precio="<?php echo $producto['precio']; ?>"
                                                        data-stock="<?php echo $producto['stock']; ?>">
                                                    <?php echo htmlspecialchars($producto['nombre']); ?> 
                                                    (Ref: <?php echo $producto['referencia']; ?>)
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Cantidad</label>
                                            <input type="number" class="form-control cantidad-input" name="cantidades[]" min="1" value="1" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Precio Unit.</label>
                                            <input type="number" class="form-control precio-input" name="precios[]" step="0.01" readonly>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Subtotal</label>
                                            <input type="number" class="form-control subtotal-input" name="subtotales[]" step="0.01" readonly>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" class="btn btn-danger btn-sm w-100" onclick="eliminarProducto(this)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="button" class="btn btn-outline-primary" onclick="agregarProducto()">
                                <i class="bi bi-plus-circle"></i> Agregar Producto
                            </button>
                        </div>
                        
                        <!-- Totales -->
                        <div class="totales-section p-3 mb-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Subtotal</label>
                                        <input type="number" class="form-control" id="subtotal" name="subtotal" step="0.01" readonly>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">IVA (<?php echo $config_impuestos['iva'] ?? 19; ?>%)</label>
                                        <input type="number" class="form-control" id="iva" name="iva" step="0.01" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Descuento</label>
                                        <input type="number" class="form-control" id="descuento" name="descuento" step="0.01" value="0" min="0">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Total</label>
                                        <input type="number" class="form-control" id="total" name="total" step="0.01" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Observaciones -->
                        <div class="mb-4">
                            <label class="form-label">Observaciones</label>
                            <textarea class="form-control" name="observaciones" rows="3" placeholder="Observaciones adicionales..."></textarea>
                        </div>
                        
                        <!-- Botones de Acción -->
                        <div class="d-flex justify-content-between">
                            <div>
                                <button type="button" class="btn btn-outline-secondary" onclick="limpiarFormulario()">
                                    <i class="bi bi-arrow-clockwise"></i> Limpiar
                                </button>
                                <button type="button" class="btn btn-outline-info" onclick="previsualizarFactura()">
                                    <i class="bi bi-eye"></i> Previsualizar
                                </button>
                            </div>
                            <?php if (Permissions::canCreate('ventas')): ?>
                            <div>
                                <button type="button" class="btn btn-success btn-lg" onclick="generarFactura()">
                                    <i class="bi bi-check-circle"></i> Generar Factura
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Panel Lateral -->
            <div class="col-lg-4">
                <!-- Información de la Empresa -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6><i class="bi bi-building"></i> Datos de la Empresa</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong><?php echo htmlspecialchars($datos_empresa['nombre_empresa'] ?? 'TECNOXPERT'); ?></strong></p>
                        <p class="mb-1 small">NIT: <?php echo htmlspecialchars($datos_empresa['nit'] ?? '900.000.000-1'); ?></p>
                        <p class="mb-1 small"><?php echo htmlspecialchars($datos_empresa['direccion'] ?? 'Dirección de la empresa'); ?></p>
                        <p class="mb-0 small"><?php echo htmlspecialchars($datos_empresa['ciudad'] ?? 'Ciudad'); ?></p>
                    </div>
                </div>
                
                <!-- Configuración de Impuestos -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6><i class="bi bi-percent"></i> Configuración de Impuestos</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">IVA</small><br>
                                <strong><?php echo $config_impuestos['iva'] ?? 19; ?>%</strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Retefuente</small><br>
                                <strong><?php echo $config_impuestos['retefuente'] ?? 2.5; ?>%</strong>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Acciones Rápidas -->
                <div class="card">
                    <div class="card-header">
                        <h6><i class="bi bi-lightning"></i> Acciones Rápidas</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="facturas.php" class="btn btn-outline-warning btn-sm">
                                <i class="bi bi-list-ul"></i> Ver Facturas
                            </a>
                            <a href="clientes.php" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-person-plus"></i> Nuevo Cliente
                            </a>
                            <a href="productos.php" class="btn btn-outline-success btn-sm">
                                <i class="bi bi-box"></i> Nuevo Producto
                            </a>
                            <a href="reportes.php" class="btn btn-outline-info btn-sm">
                                <i class="bi bi-graph-up"></i> Ver Reportes
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Variables globales
        let productos = <?php echo json_encode($productos); ?>;
        let configImpuestos = <?php echo json_encode($config_impuestos); ?>;
        
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
        
        function agregarProducto() {
            const container = document.getElementById('productos-container');
            const nuevoProducto = container.children[0].cloneNode(true);
            
            // Limpiar valores
            nuevoProducto.querySelector('.producto-select').value = '';
            nuevoProducto.querySelector('.cantidad-input').value = '1';
            nuevoProducto.querySelector('.precio-input').value = '';
            nuevoProducto.querySelector('.subtotal-input').value = '';
            
            // Agregar eventos
            agregarEventosProducto(nuevoProducto);
            
            container.appendChild(nuevoProducto);
        }
        
        function eliminarProducto(button) {
            const container = document.getElementById('productos-container');
            if (container.children.length > 1) {
                button.closest('.producto-item').remove();
                calcularTotales();
            } else {
                mostrarAlerta('Debe tener al menos un producto', 'warning');
            }
        }
        
        function agregarEventosProducto(productoItem) {
            const select = productoItem.querySelector('.producto-select');
            const cantidad = productoItem.querySelector('.cantidad-input');
            const precio = productoItem.querySelector('.precio-input');
            const subtotal = productoItem.querySelector('.subtotal-input');
            
            select.addEventListener('change', function() {
                const productoId = this.value;
                const producto = productos.find(p => p.id == productoId);
                if (producto) {
                    precio.value = producto.precio;
                    calcularSubtotalProducto(productoItem);
                }
            });
            
            cantidad.addEventListener('input', function() {
                calcularSubtotalProducto(productoItem);
            });
        }
        
        function calcularSubtotalProducto(productoItem) {
            const cantidad = parseFloat(productoItem.querySelector('.cantidad-input').value) || 0;
            const precio = parseFloat(productoItem.querySelector('.precio-input').value) || 0;
            const subtotal = cantidad * precio;
            
            productoItem.querySelector('.subtotal-input').value = subtotal.toFixed(2);
            calcularTotales();
        }
        
        function calcularTotales() {
            let subtotal = 0;
            const subtotales = document.querySelectorAll('.subtotal-input');
            
            subtotales.forEach(input => {
                subtotal += parseFloat(input.value) || 0;
            });
            
            const iva = subtotal * (parseFloat(configImpuestos.iva || 19) / 100);
            const descuento = parseFloat(document.getElementById('descuento').value) || 0;
            const total = subtotal + iva - descuento;
            
            document.getElementById('subtotal').value = subtotal.toFixed(2);
            document.getElementById('iva').value = iva.toFixed(2);
            document.getElementById('total').value = total.toFixed(2);
        }
        
        function generarFactura() {
            const form = document.getElementById('formFactura');
            const formData = new FormData(form);
            
            // Validar que haya al menos un producto
            const productos = document.querySelectorAll('.producto-select');
            let productosValidos = 0;
            productos.forEach(select => {
                if (select.value) productosValidos++;
            });
            
            if (productosValidos === 0) {
                mostrarAlerta('Debe agregar al menos un producto', 'warning');
                return;
            }
            
            // Preparar datos de productos
            const items = [];
            const productosItems = document.querySelectorAll('.producto-item');
            productosItems.forEach(item => {
                const productoId = item.querySelector('.producto-select').value;
                const cantidad = item.querySelector('.cantidad-input').value;
                const precio = item.querySelector('.precio-input').value;
                const subtotal = item.querySelector('.subtotal-input').value;
                
                if (productoId && cantidad && precio) {
                    items.push({
                        producto_id: productoId,
                        cantidad: parseFloat(cantidad),
                        precio: parseFloat(precio),
                        subtotal: parseFloat(subtotal)
                    });
                }
            });
            
            formData.append('action', 'generar_factura');
            formData.append('items', JSON.stringify(items));
            
            fetch('ajax_facturacion.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta(data.message, 'success');
                    setTimeout(() => {
                        if (confirm('¿Desea generar el PDF de la factura?')) {
                            // Generar PDF de la factura
                            window.open('generar_pdf_factura.php?id=' + data.factura_id, '_blank');
                        }
                        limpiarFormulario();
                    }, 1000);
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al generar la factura', 'danger');
            });
        }
        
        function limpiarFormulario() {
            document.getElementById('formFactura').reset();
            const container = document.getElementById('productos-container');
            while (container.children.length > 1) {
                container.removeChild(container.lastChild);
            }
            
            // Limpiar campos de totales
            document.getElementById('subtotal').value = '';
            document.getElementById('iva').value = '';
            document.getElementById('total').value = '';
        }
        
        function previsualizarFactura() {
            // Validar que haya al menos un producto
            const productos = document.querySelectorAll('.producto-select');
            let productosValidos = 0;
            productos.forEach(select => {
                if (select.value) productosValidos++;
            });
            
            if (productosValidos === 0) {
                mostrarAlerta('Debe agregar al menos un producto', 'warning');
                return;
            }
            
            // Recopilar datos de la factura
            const clienteId = document.getElementById('cliente_id').value;
            const clienteNombre = document.getElementById('cliente_id').selectedOptions[0]?.text || 'CLIENTE GENERAL';
            const fecha = document.getElementById('fecha').value;
            const subtotal = parseFloat(document.getElementById('subtotal').value) || 0;
            const iva = parseFloat(document.getElementById('iva').value) || 0;
            const total = parseFloat(document.getElementById('total').value) || 0;
            
            // Recopilar productos
            const items = [];
            const productosItems = document.querySelectorAll('.producto-item');
            productosItems.forEach(item => {
                const productoId = item.querySelector('.producto-select').value;
                const productoNombre = item.querySelector('.producto-select').selectedOptions[0]?.text;
                const cantidad = parseFloat(item.querySelector('.cantidad-input').value) || 0;
                const precio = parseFloat(item.querySelector('.precio-input').value) || 0;
                const subtotalItem = parseFloat(item.querySelector('.subtotal-input').value) || 0;
                
                if (productoId && cantidad > 0 && precio > 0) {
                    items.push({
                        producto_id: productoId,
                        producto_nombre: productoNombre,
                        cantidad: cantidad,
                        precio: precio,
                        subtotal: subtotalItem
                    });
                }
            });
            
            // Crear contenido de previsualización
            let previewContent = `
                <div class="preview-factura">
                    <div class="preview-header">
                        <h3>📄 PREVISUALIZACIÓN DE FACTURA</h3>
                        <div class="preview-empresa">
                            <strong>TECNOXPERT</strong><br>
                            <small>NIT: 900.000.000-1</small>
                        </div>
                    </div>
                    
                    <div class="preview-info">
                        <div class="preview-cliente">
                            <strong>CLIENTE:</strong> ${clienteNombre}
                        </div>
                        <div class="preview-fecha">
                            <strong>FECHA:</strong> ${fecha}
                        </div>
                    </div>
                    
                    <div class="preview-items">
                        <h4>PRODUCTOS:</h4>
                        <table class="preview-table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Precio</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            items.forEach(item => {
                previewContent += `
                    <tr>
                        <td>${item.producto_nombre}</td>
                        <td>${item.cantidad}</td>
                        <td>$${item.precio.toLocaleString()}</td>
                        <td>$${item.subtotal.toLocaleString()}</td>
                    </tr>
                `;
            });
            
            previewContent += `
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="preview-totales">
                        <div class="preview-total-row">
                            <span>Subtotal:</span>
                            <span>$${subtotal.toLocaleString()}</span>
                        </div>
                        ${iva > 0 ? `
                        <div class="preview-total-row">
                            <span>IVA (19%):</span>
                            <span>$${iva.toLocaleString()}</span>
                        </div>
                        ` : ''}
                        <div class="preview-total-final">
                            <span><strong>TOTAL:</strong></span>
                            <span><strong>$${total.toLocaleString()}</strong></span>
                        </div>
                    </div>
                    
                    <div class="preview-actions">
                        <?php if (Permissions::canCreate('ventas')): ?>
                        <button class="btn btn-success" onclick="generarFactura()">
                            <i class="bi bi-check-circle"></i> Confirmar y Generar
                        </button>
                        <?php endif; ?>
                        <button class="btn btn-secondary" onclick="cerrarPreview()">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                    </div>
                </div>
            `;
            
            // Mostrar previsualización
            mostrarPreview(previewContent);
        }
        
        function mostrarPreview(content) {
            // Crear modal de previsualización
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.id = 'modalPreview';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Previsualización de Factura</h5>
                            <button type="button" class="btn-close" onclick="cerrarPreview()"></button>
                        </div>
                        <div class="modal-body">
                            ${content}
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            new bootstrap.Modal(modal).show();
        }
        
        function cerrarPreview() {
            const modal = document.getElementById('modalPreview');
            if (modal) {
                bootstrap.Modal.getInstance(modal).hide();
                modal.remove();
            }
        }
        
        // Agregar eventos al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            const productosItems = document.querySelectorAll('.producto-item');
            productosItems.forEach(item => {
                agregarEventosProducto(item);
            });
            
            // Evento para descuento
            document.getElementById('descuento').addEventListener('input', calcularTotales);
        });
    </script>
</body>
</html>
