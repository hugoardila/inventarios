<?php
$title = 'Dashboard - Sistema de Inventarios y Facturación';
?>

<div class="container-fluid">
    <!-- Header del Dashboard -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
            </h1>
            <p class="text-muted mb-0">Panel de control y estadísticas del sistema</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="window.print()">
                <i class="fas fa-print me-1"></i>Imprimir
            </button>
            <button class="btn btn-primary" onclick="location.reload()">
                <i class="fas fa-sync-alt me-1"></i>Actualizar
            </button>
        </div>
    </div>

    <!-- Tarjetas de estadísticas -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="dashboard-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="card-value"><?= number_format($stats['total_productos']) ?></div>
                        <div class="card-label">Total Productos</div>
                    </div>
                    <div class="card-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="dashboard-card warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="card-value"><?= number_format($stats['productos_bajo_stock']) ?></div>
                        <div class="card-label">Bajo Stock</div>
                    </div>
                    <div class="card-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="dashboard-card success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="card-value"><?= number_format($stats['total_clientes']) ?></div>
                        <div class="card-label">Total Clientes</div>
                    </div>
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="dashboard-card info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="card-value"><?= number_format($stats['total_proveedores']) ?></div>
                        <div class="card-label">Total Proveedores</div>
                    </div>
                    <div class="card-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas de ventas -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="dashboard-card success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="card-value">$<?= number_format($stats['ventas_mes']) ?></div>
                        <div class="card-label">Ventas del Mes</div>
                    </div>
                    <div class="card-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="dashboard-card info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="card-value"><?= number_format($stats['ventas_hoy']) ?></div>
                        <div class="card-label">Ventas Hoy</div>
                    </div>
                    <div class="card-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="dashboard-card primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="card-value">$<?= number_format($stats['valor_inventario']) ?></div>
                        <div class="card-label">Valor Inventario</div>
                    </div>
                    <div class="card-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="dashboard-card warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="card-value"><?= number_format($stats['productos_agotados']) ?></div>
                        <div class="card-label">Productos Agotados</div>
                    </div>
                    <div class="card-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenido principal -->
    <div class="row">
        <!-- Productos con bajo stock -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                        Productos con Bajo Stock
                    </h5>
                    <a href="<?= BASE_URL ?>productos" class="btn btn-sm btn-outline-primary">
                        Ver Todos
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($productos_bajo_stock)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <p>No hay productos con bajo stock</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Stock</th>
                                        <th>Mínimo</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productos_bajo_stock as $producto): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($producto['nombre']) ?></strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($producto['codigo_barras']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger"><?= $producto['stock'] ?></span>
                                            </td>
                                            <td><?= $producto['stock_minimo'] ?></td>
                                            <td>
                                                <?php if ($producto['stock'] == 0): ?>
                                                    <span class="badge bg-danger">Agotado</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Bajo Stock</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Ventas recientes -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-shopping-cart text-success me-2"></i>
                        Ventas Recientes
                    </h5>
                    <a href="<?= BASE_URL ?>ventas" class="btn btn-sm btn-outline-primary">
                        Ver Todas
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($ventas_recientes)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                            <p>No hay ventas recientes</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Factura</th>
                                        <th>Cliente</th>
                                        <th>Total</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ventas_recientes as $venta): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= BASE_URL ?>ventas/ver/<?= $venta['id'] ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($venta['numero_factura']) ?>
                                                </a>
                                            </td>
                                            <td><?= htmlspecialchars($venta['cliente_nombre']) ?></td>
                                            <td>
                                                <strong>$<?= number_format($venta['total']) ?></strong>
                                            </td>
                                            <td>
                                                <small><?= date('d/m/Y H:i', strtotime($venta['fecha'])) ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos y estadísticas -->
    <div class="row">
        <!-- Productos más vendidos -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar text-primary me-2"></i>
                        Productos Más Vendidos
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($productos_mas_vendidos)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                            <p>No hay datos de productos vendidos</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Categoría</th>
                                        <th>Unidades Vendidas</th>
                                        <th>Valor Total</th>
                                        <th>Progreso</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $maxVentas = max(array_column($productos_mas_vendidos, 'unidades_vendidas'));
                                    foreach ($productos_mas_vendidos as $producto): 
                                        $porcentaje = $maxVentas > 0 ? ($producto['unidades_vendidas'] / $maxVentas) * 100 : 0;
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($producto['nombre']) ?></strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($producto['codigo_barras']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($producto['categoria_nombre']) ?></td>
                                            <td>
                                                <span class="badge bg-primary"><?= number_format($producto['unidades_vendidas']) ?></span>
                                            </td>
                                            <td>
                                                <strong>$<?= number_format($producto['valor_total']) ?></strong>
                                            </td>
                                            <td style="width: 200px;">
                                                <div class="progress">
                                                    <div class="progress-bar bg-primary" 
                                                         style="width: <?= $porcentaje ?>%"
                                                         title="<?= number_format($porcentaje, 1) ?>%">
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Actividad reciente -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-clock text-info me-2"></i>
                        Actividad Reciente
                    </h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php if (empty($actividad_reciente)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                                <p>No hay actividad reciente</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($actividad_reciente as $actividad): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker"></div>
                                    <div class="timeline-content">
                                        <h6 class="mb-1"><?= htmlspecialchars($actividad['accion']) ?></h6>
                                        <p class="text-muted mb-1"><?= htmlspecialchars($actividad['descripcion']) ?></p>
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i>
                                            <?= htmlspecialchars($actividad['usuario_nombre']) ?>
                                            <i class="fas fa-clock ms-2 me-1"></i>
                                            <?= date('d/m/Y H:i', strtotime($actividad['fecha'])) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Timeline styles */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -35px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background-color: var(--primary-color);
    border: 2px solid white;
    box-shadow: 0 0 0 2px var(--primary-color);
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -29px;
    top: 17px;
    width: 2px;
    height: calc(100% + 10px);
    background-color: #e9ecef;
}

.timeline-item:last-child::before {
    display: none;
}

.timeline-content h6 {
    font-size: 0.875rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.timeline-content p {
    font-size: 0.8rem;
    margin-bottom: 0.25rem;
}

.timeline-content small {
    font-size: 0.75rem;
}
</style>

<script>
// Actualizar datos del dashboard cada 5 minutos
setInterval(function() {
    // Aquí se pueden hacer llamadas AJAX para actualizar las estadísticas
    console.log('Actualizando dashboard...');
}, 300000);

// Inicializar tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

