<?php
session_start();

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: views/login.php");
    exit();
}

// Conexión a la base de datos
try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // KPIs del sistema
    $stats = [];
    
    // Total de productos activos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE estado = 'activo'");
    $stats['productos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de clientes activos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM clientes WHERE activo = 1");
    $stats['clientes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de proveedores activos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM proveedores WHERE activo = 1");
    $stats['proveedores'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Ventas del mes actual
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(total) as monto FROM ventas WHERE MONTH(fecha_venta) = MONTH(CURRENT_DATE()) AND YEAR(fecha_venta) = YEAR(CURRENT_DATE())");
    $ventas_mes = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['ventas_mes'] = $ventas_mes['total'] ?? 0;
    $stats['monto_mes'] = $ventas_mes['monto'] ?? 0;
    
    // Productos con stock bajo
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE stock <= stock_minimo AND estado = 'activo'");
    $stats['stock_bajo'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Últimas ventas
    $stmt = $pdo->query("SELECT v.*, c.nombre as cliente FROM ventas v LEFT JOIN clientes c ON v.cliente_id = c.id ORDER BY v.creado_en DESC LIMIT 5");
    $ultimas_ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Productos más vendidos
    $stmt = $pdo->query("SELECT p.nombre, SUM(vi.cantidad) as total_vendido FROM venta_items vi LEFT JOIN productos p ON vi.producto_id = p.id GROUP BY p.id ORDER BY total_vendido DESC LIMIT 5");
    $productos_vendidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error de conexión: " . $e->getMessage();
    $stats = [];
    $ultimas_ventas = [];
    $productos_vendidos = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TECNOXPERT Inventarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .kpi-card {
            transition: transform 0.2s;
        }
        .kpi-card:hover {
            transform: translateY(-5px);
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .main-content {
            background-color: #f8f9fa;
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
                position: fixed;
                top: 0;
                left: -100%;
                width: 80%;
                z-index: 1050;
                transition: left 0.3s ease;
            }
            .sidebar.show {
                left: 0;
            }
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            .kpi-card {
                margin-bottom: 15px;
            }
            .table-responsive {
                font-size: 0.875rem;
            }
            .btn-group-vertical .btn {
                margin-bottom: 5px;
            }
        }
        
        @media (max-width: 576px) {
            .kpi-card .card-body {
                padding: 1rem;
            }
            .kpi-card h3 {
                font-size: 1.5rem;
            }
            .table th, .table td {
                padding: 0.5rem;
                font-size: 0.8rem;
            }
        }
        
        /* Mobile menu toggle */
        .mobile-menu-btn {
            display: none;
        }
        
        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
                position: fixed;
                top: 15px;
                left: 15px;
                z-index: 1060;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Button -->
    <button class="btn btn-primary mobile-menu-btn" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-0" id="sidebar">
                <div class="p-3 text-white">
                    <h4 class="mb-4">
                        <i class="bi bi-building"></i> TECNOXPERT
                    </h4>
                    <div class="mb-3">
                        <small class="text-light">Bienvenido,</small><br>
                        <strong><?php echo $_SESSION['user_name']; ?></strong><br>
                        <span class="badge bg-light text-dark"><?php echo ucfirst($_SESSION['user_role']); ?></span>
                    </div>
                    
                    <hr class="text-light">
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="index.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="views/productos.php">
                                <i class="bi bi-box"></i> Inventario
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="views/facturacion.php">
                                <i class="bi bi-receipt"></i> Facturación
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="views/notas_credito.php">
                                <i class="bi bi-arrow-counterclockwise"></i> Notas Crédito
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="views/notas_debito.php">
                                <i class="bi bi-arrow-clockwise"></i> Notas Débito
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="views/clientes.php">
                                <i class="bi bi-people"></i> Clientes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="views/proveedores.php">
                                <i class="bi bi-truck"></i> Proveedores
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="views/categorias.php">
                                <i class="bi bi-folder"></i> Categorías
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="views/reportes.php">
                                <i class="bi bi-graph-up"></i> Reportes
                            </a>
                        </li>
                        <?php if ($_SESSION['user_role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="views/configuracion.php">
                                <i class="bi bi-gear"></i> Configuración
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="views/usuarios.php">
                                <i class="bi bi-people-fill"></i> Usuarios
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                    
                    <hr class="text-light">
                    
                    <a href="views/logout.php" class="btn btn-outline-light btn-sm w-100">
                        <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                    </a>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="p-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-0">Dashboard</h1>
                            <p class="text-muted">Panel de control del sistema</p>
                        </div>
                        <div class="text-end">
                            <small class="text-muted"><?php echo date('d/m/Y H:i'); ?></small>
                        </div>
                    </div>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <!-- KPIs -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card kpi-card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="text-primary mb-2">
                                        <i class="bi bi-box h1"></i>
                                    </div>
                                    <h3 class="mb-1"><?php echo number_format($stats['productos']); ?></h3>
                                    <p class="text-muted mb-0">Productos Activos</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card kpi-card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="text-success mb-2">
                                        <i class="bi bi-people h1"></i>
                                    </div>
                                    <h3 class="mb-1"><?php echo number_format($stats['clientes']); ?></h3>
                                    <p class="text-muted mb-0">Clientes</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card kpi-card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="text-warning mb-2">
                                        <i class="bi bi-receipt h1"></i>
                                    </div>
                                    <h3 class="mb-1"><?php echo number_format($stats['ventas_mes']); ?></h3>
                                    <p class="text-muted mb-0">Ventas del Mes</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card kpi-card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="text-info mb-2">
                                        <i class="bi bi-currency-dollar h1"></i>
                                    </div>
                                    <h3 class="mb-1">$<?php echo number_format($stats['monto_mes'], 0, ',', '.'); ?></h3>
                                    <p class="text-muted mb-0">Ingresos del Mes</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Alertas -->
                    <?php if ($stats['stock_bajo'] > 0): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>¡Atención!</strong> Tienes <?php echo $stats['stock_bajo']; ?> productos con stock bajo.
                        <a href="views/productos.php" class="alert-link">Ver inventario</a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Contenido Principal -->
                    <div class="row">
                        <!-- Últimas Ventas -->
                        <div class="col-lg-6 mb-4">
                            <div class="chart-container p-3">
                                <h5 class="mb-3">
                                    <i class="bi bi-clock-history"></i> Últimas Ventas
                                </h5>
                                <?php if (empty($ultimas_ventas)): ?>
                                    <p class="text-muted text-center">No hay ventas recientes</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Factura</th>
                                                    <th>Cliente</th>
                                                    <th>Total</th>
                                                    <th>Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($ultimas_ventas as $venta): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($venta['numero_factura']); ?></td>
                                                    <td><?php echo htmlspecialchars($venta['cliente']); ?></td>
                                                    <td>$<?php echo number_format($venta['total'], 0, ',', '.'); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $venta['estado'] === 'pagada' ? 'success' : 'warning'; ?>">
                                                            <?php echo ucfirst($venta['estado']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Productos Más Vendidos -->
                        <div class="col-lg-6 mb-4">
                            <div class="chart-container p-3">
                                <h5 class="mb-3">
                                    <i class="bi bi-trophy"></i> Productos Más Vendidos
                                </h5>
                                <?php if (empty($productos_vendidos)): ?>
                                    <p class="text-muted text-center">No hay datos de ventas</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Producto</th>
                                                    <th>Cantidad</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($productos_vendidos as $producto): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                                    <td><?php echo number_format($producto['total_vendido']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Acciones Rápidas -->
                    <div class="row">
                        <div class="col-12">
                            <div class="chart-container p-3">
                                <h5 class="mb-3">
                                    <i class="bi bi-lightning"></i> Acciones Rápidas
                                </h5>
                                <div class="row">
                                    <div class="col-md-4 mb-2">
                                        <a href="views/facturacion.php" class="btn btn-primary w-100">
                                            <i class="bi bi-plus-circle"></i> Nueva Factura
                                        </a>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <a href="views/productos.php" class="btn btn-success w-100">
                                            <i class="bi bi-box"></i> Agregar Producto
                                        </a>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <a href="views/clientes.php" class="btn btn-info w-100">
                                            <i class="bi bi-person-plus"></i> Nuevo Cliente
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }
        
        // Cerrar sidebar al hacer clic fuera en móvil
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !menuBtn.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
        
        // Cerrar sidebar al redimensionar ventana
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
            }
        });
    </script>
</body>
</html>

        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
            }
        });
    </script>
</body>
</html>
