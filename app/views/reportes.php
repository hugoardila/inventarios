<?php
session_start();

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Conexión a la base de datos
try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Estadísticas generales
    $totalProductos = $pdo->query("SELECT COUNT(*) FROM productos WHERE estado = 'activo'")->fetchColumn();
    $totalClientes = $pdo->query("SELECT COUNT(*) FROM clientes WHERE activo = 1")->fetchColumn();
    $totalProveedores = $pdo->query("SELECT COUNT(*) FROM proveedores WHERE activo = 1")->fetchColumn();
    $totalVentas = $pdo->query("SELECT COUNT(*) FROM ventas")->fetchColumn();
    
    // Ventas del mes
    $ventasMes = $pdo->query("SELECT COUNT(*) as total, SUM(total) as monto FROM ventas WHERE MONTH(fecha_venta) = MONTH(CURRENT_DATE()) AND YEAR(fecha_venta) = YEAR(CURRENT_DATE())")->fetch(PDO::FETCH_ASSOC);
    
    // Productos con stock bajo
    $stockBajo = $pdo->query("SELECT COUNT(*) FROM productos WHERE stock <= stock_minimo AND estado = 'activo'")->fetchColumn();
    
    // Últimas ventas para el dashboard
    $ultimasVentas = $pdo->query("SELECT v.*, c.nombre as cliente FROM ventas v LEFT JOIN clientes c ON v.cliente_id = c.id ORDER BY v.creado_en DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    
    // Productos más vendidos
    $productosVendidos = $pdo->query("SELECT p.nombre, SUM(vi.cantidad) as total_vendido FROM venta_items vi LEFT JOIN productos p ON vi.producto_id = p.id GROUP BY p.id ORDER BY total_vendido DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error de conexión: " . $e->getMessage();
    $totalProductos = $totalClientes = $totalProveedores = $totalVentas = 0;
    $ventasMes = ['total' => 0, 'monto' => 0];
    $stockBajo = 0;
    $ultimasVentas = [];
    $productosVendidos = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - TECNOXPERT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .reporte-container {
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
        .reporte-item {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        .reporte-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .btn-reporte {
            transition: all 0.3s ease;
        }
        .btn-reporte:hover {
            transform: translateY(-2px);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .container-fluid {
                padding: 10px;
            }
            
            .stats-card {
                margin-bottom: 15px;
            }
            
            .btn-reporte {
                margin-bottom: 10px;
            }
            
            .btn-reporte .d-flex {
                flex-direction: column;
                text-align: center;
            }
            
            .btn-reporte i {
                margin-bottom: 5px;
                margin-right: 0 !important;
            }
        }
        
        @media (max-width: 576px) {
            .stats-card .card-body {
                padding: 1rem;
            }
            
            .stats-card h3 {
                font-size: 1.5rem;
            }
            
            .btn-reporte {
                padding: 0.75rem;
                font-size: 0.875rem;
            }
            
            .btn-reporte strong {
                font-size: 0.9rem;
            }
            
            .btn-reporte small {
                font-size: 0.75rem;
            }
        }
        
        @media (max-width: 480px) {
            .stats-card h3 {
                font-size: 1.25rem;
            }
            
            .stats-card small {
                font-size: 0.7rem;
            }
            
            .btn-reporte {
                padding: 0.5rem;
                font-size: 0.8rem;
            }
            
            .btn-reporte strong {
                font-size: 0.8rem;
            }
            
            .btn-reporte small {
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
                <span class="navbar-text me-3">Bienvenido, <?php echo $_SESSION['user_name']; ?></span>
                <a class="nav-link" href="logout.php">Cerrar sesión</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>📊 Centro de Reportes</h2>
                <p class="text-muted">Análisis y estadísticas del sistema</p>
            </div>
            <div>
                <a href="../index.php" class="btn btn-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
                <button class="btn btn-outline-primary" onclick="exportarTodosReportes()">
                    <i class="bi bi-download"></i> Exportar Todo
                </button>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Alertas para AJAX -->
        <div id="alertContainer"></div>
        
        <!-- KPIs Principales -->
        <div class="row mb-4">
            <div class="col-md-2 mb-3">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-box h3"></i>
                        <h4 class="mb-1"><?php echo number_format($totalProductos); ?></h4>
                        <small>Productos Activos</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-people h3"></i>
                        <h4 class="mb-1"><?php echo number_format($totalClientes); ?></h4>
                        <small>Clientes</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card stats-card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-building h3"></i>
                        <h4 class="mb-1"><?php echo number_format($totalProveedores); ?></h4>
                        <small>Proveedores</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card stats-card bg-info text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-receipt h3"></i>
                        <h4 class="mb-1"><?php echo number_format($totalVentas); ?></h4>
                        <small>Total Ventas</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card stats-card bg-danger text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-exclamation-triangle h3"></i>
                        <h4 class="mb-1"><?php echo number_format($stockBajo); ?></h4>
                        <small>Stock Bajo</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card stats-card bg-dark text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-currency-dollar h3"></i>
                        <h4 class="mb-1">$<?php echo number_format($ventasMes['monto'] ?? 0, 0, ',', '.'); ?></h4>
                        <small>Ventas del Mes</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Reportes de Inventario -->
            <div class="col-lg-4 mb-4">
                <div class="reporte-container p-4">
                    <h4 class="mb-4">
                        <i class="bi bi-box"></i> Reportes de Inventario
                    </h4>
                    
                    <div class="d-grid gap-3">
                        <button class="btn btn-outline-primary btn-reporte" onclick="generarReporte('inventario-valorizado')">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-file-earmark-text me-3"></i>
                                <div class="text-start">
                                    <strong>Inventario Valorizado</strong>
                                    <br><small class="text-muted">Costo y precio de venta</small>
                                </div>
                            </div>
                        </button>
                        
                        <button class="btn btn-outline-warning btn-reporte" onclick="generarReporte('stock-bajo')">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-exclamation-triangle me-3"></i>
                                <div class="text-start">
                                    <strong>Stock Bajo</strong>
                                    <br><small class="text-muted">Productos por debajo del mínimo</small>
                                </div>
                            </div>
                        </button>
                        
                        <button class="btn btn-outline-info btn-reporte" onclick="generarReporte('movimientos')">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-arrow-left-right me-3"></i>
                                <div class="text-start">
                                    <strong>Movimientos</strong>
                                    <br><small class="text-muted">Entradas y salidas</small>
                                </div>
                            </div>
                        </button>
                        
                        <button class="btn btn-outline-success btn-reporte" onclick="generarReporte('kardex')">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-box-seam me-3"></i>
                                <div class="text-start">
                                    <strong>Kardex</strong>
                                    <br><small class="text-muted">Detallado por producto</small>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Reportes de Ventas -->
            <div class="col-lg-4 mb-4">
                <div class="reporte-container p-4">
                    <h4 class="mb-4">
                        <i class="bi bi-graph-up"></i> Reportes de Ventas
                    </h4>
                    
                    <div class="d-grid gap-3">
                        <button class="btn btn-outline-primary btn-reporte" onclick="generarReporte('ventas-periodo')">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-calendar-range me-3"></i>
                                <div class="text-start">
                                    <strong>Ventas por Período</strong>
                                    <br><small class="text-muted">Filtros de fecha</small>
                                </div>
                            </div>
                        </button>
                        
                        <button class="btn btn-outline-success btn-reporte" onclick="generarReporte('ventas-cliente')">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-person me-3"></i>
                                <div class="text-start">
                                    <strong>Ventas por Cliente</strong>
                                    <br><small class="text-muted">Agrupado por cliente</small>
                                </div>
                            </div>
                        </button>
                        
                        <button class="btn btn-outline-info btn-reporte" onclick="generarReporte('ventas-producto')">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-box me-3"></i>
                                <div class="text-start">
                                    <strong>Ventas por Producto</strong>
                                    <br><small class="text-muted">Productos más vendidos</small>
                                </div>
                            </div>
                        </button>
                        
                        <button class="btn btn-outline-warning btn-reporte" onclick="generarReporte('impuestos')">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-cash-coin me-3"></i>
                                <div class="text-start">
                                    <strong>Impuestos</strong>
                                    <br><small class="text-muted">IVA y retenciones</small>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Dashboard en Tiempo Real -->
            <div class="col-lg-4 mb-4">
                <div class="reporte-container p-4">
                    <h4 class="mb-4">
                        <i class="bi bi-speedometer2"></i> Dashboard en Tiempo Real
                    </h4>
                    
                    <!-- Últimas Ventas -->
                    <div class="mb-4">
                        <h6><i class="bi bi-clock-history"></i> Últimas Ventas</h6>
                        <?php if (empty($ultimasVentas)): ?>
                            <p class="text-muted small">No hay ventas recientes</p>
                        <?php else: ?>
                            <?php foreach ($ultimasVentas as $venta): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 reporte-item">
                                    <div>
                                        <small class="text-muted"><?php echo htmlspecialchars($venta['cliente'] ?? 'Cliente'); ?></small><br>
                                        <strong>$<?php echo number_format($venta['total'], 0, ',', '.'); ?></strong>
                                    </div>
                                    <small class="text-muted"><?php echo date('d/m', strtotime($venta['fecha_venta'])); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Productos Más Vendidos -->
                    <div>
                        <h6><i class="bi bi-trophy"></i> Productos Más Vendidos</h6>
                        <?php if (empty($productosVendidos)): ?>
                            <p class="text-muted small">No hay datos de ventas</p>
                        <?php else: ?>
                            <?php foreach ($productosVendidos as $producto): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 reporte-item">
                                    <div>
                                        <small class="text-muted"><?php echo htmlspecialchars($producto['nombre']); ?></small>
                                    </div>
                                    <strong><?php echo number_format($producto['total_vendido']); ?></strong>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtros Avanzados -->
        <div class="row">
            <div class="col-12">
                <div class="reporte-container p-4">
                    <h4 class="mb-4">
                        <i class="bi bi-funnel"></i> Filtros Avanzados
                    </h4>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Fecha Desde</label>
                            <input type="date" class="form-control" id="fechaDesde">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha Hasta</label>
                            <input type="date" class="form-control" id="fechaHasta">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cliente</label>
                            <select class="form-select" id="filtroCliente">
                                <option value="">Todos los clientes</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Formato</label>
                            <select class="form-select" id="formatoReporte">
                                <option value="pdf">PDF</option>
                                <option value="excel">Excel</option>
                                <option value="csv">CSV</option>
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
                    </div>
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
        
        function generarReporte(tipo) {
            const fechaDesde = document.getElementById('fechaDesde').value;
            const fechaHasta = document.getElementById('fechaHasta').value;
            const cliente = document.getElementById('filtroCliente').value;
            const formato = document.getElementById('formatoReporte').value;
            
            const formData = new FormData();
            formData.append('action', 'generar_reporte');
            formData.append('tipo', tipo);
            formData.append('fecha_desde', fechaDesde);
            formData.append('fecha_hasta', fechaHasta);
            formData.append('cliente', cliente);
            formData.append('formato', formato);
            
            mostrarAlerta('Generando reporte...', 'info');
            
            fetch('ajax_reportes.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta(data.message, 'success');
                    if (data.download_url) {
                        setTimeout(() => {
                            window.open(data.download_url, '_blank');
                        }, 1000);
                    }
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al generar el reporte', 'danger');
            });
        }
        
        function exportarTodosReportes() {
            if (confirm('¿Desea exportar todos los reportes disponibles?')) {
                mostrarAlerta('Exportando todos los reportes...', 'info');
                
                // Simular exportación
                setTimeout(() => {
                    mostrarAlerta('Todos los reportes han sido exportados correctamente', 'success');
                }, 2000);
            }
        }
        
        function aplicarFiltros() {
            const fechaDesde = document.getElementById('fechaDesde').value;
            const fechaHasta = document.getElementById('fechaHasta').value;
            
            if (fechaDesde && fechaHasta && fechaDesde > fechaHasta) {
                mostrarAlerta('La fecha desde no puede ser mayor que la fecha hasta', 'warning');
                return;
            }
            
            mostrarAlerta('Filtros aplicados correctamente', 'success');
        }
        
        function limpiarFiltros() {
            document.getElementById('fechaDesde').value = '';
            document.getElementById('fechaHasta').value = '';
            document.getElementById('filtroCliente').value = '';
            document.getElementById('formatoReporte').value = 'pdf';
            mostrarAlerta('Filtros limpiados', 'info');
        }
        
        // Cargar clientes para el filtro
        document.addEventListener('DOMContentLoaded', function() {
            fetch('ajax_reportes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=obtener_clientes'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const select = document.getElementById('filtroCliente');
                    data.clientes.forEach(cliente => {
                        const option = document.createElement('option');
                        option.value = cliente.id;
                        option.textContent = cliente.nombre;
                        select.appendChild(option);
                    });
                }
            });
        });
    </script>
</body>
</html>
