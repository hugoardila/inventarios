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
    
    // Filtros de búsqueda
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
    $cliente_id = $_GET['cliente_id'] ?? '';
    $estado = $_GET['estado'] ?? '';
    $search = $_GET['search'] ?? '';
    
    // Construir consulta
    $sql = "SELECT v.*, c.nombre as cliente_nombre, c.tipo_documento, c.numero_documento,
                   u.nombre as vendedor_nombre
            FROM ventas v 
            LEFT JOIN clientes c ON v.cliente_id = c.id 
            LEFT JOIN usuarios u ON v.usuario_id = u.id 
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($fecha_inicio)) {
        $sql .= " AND v.fecha_venta >= ?";
        $params[] = $fecha_inicio;
    }
    
    if (!empty($fecha_fin)) {
        $sql .= " AND v.fecha_venta <= ?";
        $params[] = $fecha_fin;
    }
    
    if (!empty($cliente_id)) {
        $sql .= " AND v.cliente_id = ?";
        $params[] = $cliente_id;
    }
    
    if (!empty($estado)) {
        $sql .= " AND v.estado = ?";
        $params[] = $estado;
    }
    
    if (!empty($search)) {
        $sql .= " AND (v.numero_factura LIKE ? OR c.nombre LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $sql .= " ORDER BY v.fecha_venta DESC, v.id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Cargar clientes para filtro
    $stmt = $pdo->query("SELECT id, nombre FROM clientes WHERE activo = 1 ORDER BY nombre");
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error de conexión: " . $e->getMessage();
    $facturas = [];
    $clientes = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facturas - TECNOXPERT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .factura-card {
            transition: all 0.3s ease;
            border-left: 2px solid #007bff;
            font-size: 0.7rem;
        }
        .factura-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .factura-card .card-header {
            padding: 0.2rem 0.4rem;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        .factura-card .card-body {
            padding: 0.2rem 0.4rem;
        }
        .factura-card .card-footer {
            padding: 0.2rem 0.4rem;
            border-top: 1px solid rgba(0,0,0,0.1);
        }
        .estado-badge {
            font-size: 0.55em;
        }
        .filters-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .action-buttons {
            display: flex;
            gap: 1px;
            flex-wrap: wrap;
        }
        .btn-sm {
            font-size: 0.6rem;
            padding: 0.1rem 0.25rem;
            line-height: 1.0;
        }
        .factura-card .row {
            margin-bottom: 0.05rem;
        }
        .factura-card .mb-0 {
            margin-bottom: 0 !important;
        }
        .factura-card .mb-1 {
            margin-bottom: 0.05rem !important;
        }
        .factura-card .mb-2 {
            margin-bottom: 0.1rem !important;
        }
        .campos-parcial-container {
            background-color: #f8f9fa !important;
        }
        .campos-parcial-container .card {
            border: 1px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .campos-parcial-container .card-title {
            color: #495057;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .campos-parcial-container .form-label-sm {
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
        }
        .campos-parcial-container .form-control-sm {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">TECNOXPERT - Inventarios</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="facturacion.php">
                    <i class="bi bi-arrow-left"></i> Volver a Facturación
                </a>
                <a class="nav-link" href="../index.php">
                    <i class="bi bi-house"></i> Inicio
                </a>
                <a class="nav-link" href="facturacion.php">
                    <i class="bi bi-plus-circle"></i> Nueva Factura
                </a>
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Salir
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="bi bi-receipt"></i> Gestión de Facturas</h2>
                        <p class="text-muted mb-0">Ver y gestionar todas las facturas del sistema</p>
                    </div>
                    <div>
                        <a href="facturacion.php" class="btn btn-secondary me-2">
                            <i class="bi bi-arrow-left"></i> Volver a Facturación
                        </a>
                        <button class="btn btn-success me-2" onclick="enviarMasivoDIAN()">
                            <i class="bi bi-send"></i> Envío Masivo DIAN
                        </button>
                        <button class="btn btn-info me-2" onclick="abrirValidadorDIAN()">
                            <i class="bi bi-check-circle"></i> Validador DIAN
                        </button>
                        <a href="facturacion.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Nueva Factura
                        </a>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="filters-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Fecha Inicio</label>
                            <input type="date" class="form-control" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha Fin</label>
                            <input type="date" class="form-control" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cliente</label>
                            <select class="form-select" name="cliente_id">
                                <option value="">Todos los clientes</option>
                                <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>" <?php echo $cliente_id == $cliente['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cliente['nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado">
                                <option value="">Todos los estados</option>
                                <option value="pendiente" <?php echo $estado == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="pagada" <?php echo $estado == 'pagada' ? 'selected' : ''; ?>>Pagada</option>
                                <option value="anulada" <?php echo $estado == 'anulada' ? 'selected' : ''; ?>>Anulada</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Buscar</label>
                            <input type="text" class="form-control" name="search" placeholder="Número de factura o cliente..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-outline-primary me-2">
                                <i class="bi bi-search"></i> Filtrar
                            </button>
                            <a href="facturas.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i> Limpiar
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Lista de Facturas -->
                <?php if (empty($facturas)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-receipt display-1 text-muted"></i>
                    <h4 class="text-muted mt-3">No se encontraron facturas</h4>
                    <p class="text-muted">Intenta ajustar los filtros de búsqueda</p>
                </div>
                <?php else: ?>
                <div class="row">
                    <?php foreach ($facturas as $factura): ?>
                    <div class="col-6 col-md-3 col-lg-2 mb-2">
                        <div class="card factura-card h-100" style="font-size: 0.7rem;">
                            <div class="card-header py-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0" style="font-size: 0.75rem;">
                                        <i class="bi bi-receipt"></i> <?php echo htmlspecialchars($factura['numero_factura']); ?>
                                    </h6>
                                    <div class="d-flex align-items-center gap-1">
                                        <!-- Indicador de estado FE -->
                                        <?php if ($factura['estado_fe'] === 'enviada'): ?>
                                        <span class="badge bg-success" style="font-size: 0.55rem;" title="Enviada a DIAN">
                                            <i class="bi bi-check-circle"></i> DIAN
                                        </span>
                                        <?php elseif ($factura['estado_fe'] === 'rechazada'): ?>
                                        <span class="badge bg-danger" style="font-size: 0.55rem;" title="Rechazada por DIAN">
                                            <i class="bi bi-x-circle"></i> DIAN
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary" style="font-size: 0.55rem;" title="Pendiente de envío">
                                            <i class="bi bi-clock"></i> DIAN
                                        </span>
                                        <?php endif; ?>
                                        
                                        <!-- Indicador de estado general -->
                                        <span class="badge bg-<?php echo $factura['estado'] === 'pagada' ? 'success' : ($factura['estado'] === 'anulada' ? 'danger' : 'warning'); ?> estado-badge" style="font-size: 0.55rem;">
                                            <?php echo ucfirst($factura['estado']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body py-1">
                                <div class="row mb-0">
                                    <div class="col-6">
                                        <small class="text-muted" style="font-size: 0.6rem;">Cliente:</small><br>
                                        <strong style="font-size: 0.65rem;"><?php echo htmlspecialchars($factura['cliente_nombre'] ?? 'CLIENTE GENERAL'); ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted" style="font-size: 0.6rem;">Fecha:</small><br>
                                        <strong style="font-size: 0.65rem;"><?php echo date('d/m/Y', strtotime($factura['fecha_venta'])); ?></strong>
                                    </div>
                                </div>
                                <div class="row mb-0">
                                    <div class="col-6">
                                        <small class="text-muted" style="font-size: 0.6rem;">Vendedor:</small><br>
                                        <strong style="font-size: 0.65rem;"><?php echo htmlspecialchars($factura['vendedor_nombre'] ?? 'Sistema'); ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted" style="font-size: 0.6rem;">Total:</small><br>
                                        <strong class="text-success" style="font-size: 0.65rem;">$<?php echo number_format($factura['total'], 0, ',', '.'); ?></strong>
                                    </div>
                                </div>
                                <?php if ($factura['cliente_id'] && $factura['tipo_documento'] && $factura['numero_documento']): ?>
                                <div class="mb-0">
                                    <small class="text-muted" style="font-size: 0.6rem;">Documento:</small><br>
                                    <strong style="font-size: 0.65rem;"><?php echo htmlspecialchars($factura['tipo_documento']); ?>: <?php echo htmlspecialchars($factura['numero_documento']); ?></strong>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer py-1">
                                <div class="action-buttons d-flex flex-wrap gap-1">
                                    <button class="btn btn-outline-primary btn-sm" style="font-size: 0.6rem; padding: 0.1rem 0.25rem;" onclick="verFactura(<?php echo $factura['id']; ?>)">
                                        <i class="bi bi-eye"></i> Ver
                                    </button>
                                    <button class="btn btn-outline-success btn-sm" style="font-size: 0.6rem; padding: 0.1rem 0.25rem;" onclick="generarPDF(<?php echo $factura['id']; ?>)">
                                        <i class="bi bi-file-pdf"></i> PDF
                                    </button>
                                    <?php if ($factura['estado'] !== 'anulada' && $factura['estado_fe'] !== 'enviada'): ?>
                                    <button class="btn btn-outline-info btn-sm" style="font-size: 0.6rem; padding: 0.1rem 0.25rem;" onclick="enviarDIAN(<?php echo $factura['id']; ?>)">
                                        <i class="bi bi-send"></i> DIAN
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($factura['estado_fe'] === 'enviada'): ?>
                                    <button class="btn btn-outline-secondary btn-sm" style="font-size: 0.6rem; padding: 0.1rem 0.25rem;" onclick="enviarEmail(<?php echo $factura['id']; ?>)">
                                        <i class="bi bi-envelope"></i> Email
                                    </button>
                                    <button class="btn btn-outline-warning btn-sm" style="font-size: 0.6rem; padding: 0.1rem 0.25rem;" onclick="crearNotaCredito(<?php echo $factura['id']; ?>)">
                                        <i class="bi bi-arrow-counterclockwise"></i> NC
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm" style="font-size: 0.6rem; padding: 0.1rem 0.25rem;" onclick="crearNotaDebito(<?php echo $factura['id']; ?>)">
                                        <i class="bi bi-arrow-clockwise"></i> ND
                                    </button>
                                    <button class="btn btn-outline-info btn-sm" style="font-size: 0.6rem; padding: 0.1rem 0.25rem;" onclick="verXMLFactura(<?php echo $factura['id']; ?>)">
                                        <i class="bi bi-file-code"></i> XML
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($factura['estado'] === 'pendiente'): ?>
                                    <button class="btn btn-outline-warning btn-sm" style="font-size: 0.6rem; padding: 0.1rem 0.25rem;" onclick="marcarPagada(<?php echo $factura['id']; ?>)">
                                        <i class="bi bi-check-circle"></i> Pagar
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($factura['estado'] !== 'anulada' && $factura['estado_fe'] !== 'enviada'): ?>
                                    <button class="btn btn-outline-danger btn-sm" style="font-size: 0.6rem; padding: 0.1rem 0.25rem;" onclick="cancelarFactura(<?php echo $factura['id']; ?>)">
                                        <i class="bi bi-x-circle"></i> Cancelar
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal para ver factura -->
    <div class="modal fade" id="modalFactura" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalle de Factura</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalFacturaBody">
                    <!-- Contenido cargado dinámicamente -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Validador DIAN -->
    <div class="modal fade" id="modalValidadorDIAN" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-check-circle text-info"></i> Validador DIAN
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <strong>Validador DIAN:</strong> Verifica que todos los documentos cumplan con los estándares DIAN antes del envío.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pestañas para diferentes tipos de documentos -->
                    <ul class="nav nav-tabs" id="validadorTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="facturas-tab" data-bs-toggle="tab" data-bs-target="#facturas" type="button" role="tab">
                                <i class="bi bi-receipt"></i> Facturas
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="notas-credito-tab" data-bs-toggle="tab" data-bs-target="#notas-credito" type="button" role="tab">
                                <i class="bi bi-arrow-up-circle"></i> Notas Crédito
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="notas-debito-tab" data-bs-toggle="tab" data-bs-target="#notas-debito" type="button" role="tab">
                                <i class="bi bi-arrow-down-circle"></i> Notas Débito
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="validadorTabsContent">
                        <!-- Pestaña Facturas -->
                        <div class="tab-pane fade show active" id="facturas" role="tabpanel">
                            <div class="mt-3">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6><i class="bi bi-receipt"></i> Validación de Facturas</h6>
                                    <button class="btn btn-sm btn-primary" onclick="validarFacturas()">
                                        <i class="bi bi-play-circle"></i> Ejecutar Validación
                                    </button>
                                </div>
                                <div id="resultadoFacturas" class="mt-3">
                                    <div class="text-center text-muted">
                                        <i class="bi bi-hourglass-split"></i> Presiona "Ejecutar Validación" para comenzar
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Pestaña Notas Crédito -->
                        <div class="tab-pane fade" id="notas-credito" role="tabpanel">
                            <div class="mt-3">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6><i class="bi bi-arrow-up-circle"></i> Validación de Notas de Crédito</h6>
                                    <button class="btn btn-sm btn-primary" onclick="validarNotasCredito()">
                                        <i class="bi bi-play-circle"></i> Ejecutar Validación
                                    </button>
                                </div>
                                <div id="resultadoNotasCredito" class="mt-3">
                                    <div class="text-center text-muted">
                                        <i class="bi bi-hourglass-split"></i> Presiona "Ejecutar Validación" para comenzar
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Pestaña Notas Débito -->
                        <div class="tab-pane fade" id="notas-debito" role="tabpanel">
                            <div class="mt-3">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6><i class="bi bi-arrow-down-circle"></i> Validación de Notas de Débito</h6>
                                    <button class="btn btn-sm btn-primary" onclick="validarNotasDebito()">
                                        <i class="bi bi-play-circle"></i> Ejecutar Validación
                                    </button>
                                </div>
                                <div id="resultadoNotasDebito" class="mt-3">
                                    <div class="text-center text-muted">
                                        <i class="bi bi-hourglass-split"></i> Presiona "Ejecutar Validación" para comenzar
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function verFactura(id) {
            fetch('ajax_ver_factura.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('modalFacturaBody').innerHTML = data.html;
                    new bootstrap.Modal(document.getElementById('modalFactura')).show();
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            });
        }
        
        function generarPDF(id) {
            window.open('generar_pdf_factura.php?id=' + id, '_blank');
        }
        
        function enviarDIAN(id) {
            if (confirm('¿Desea enviar esta factura a la DIAN?')) {
                fetch('ajax_facturacion_electronica.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=enviar_factura&factura_id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarAlerta(data.icon + ' ' + data.message, 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        mostrarAlerta(data.message, 'danger');
                    }
                });
            }
        }
        
        function crearNotaCredito(facturaId) {
            // Mostrar modal avanzado para seleccionar productos
            const modalHtml = `
                <div class="modal fade" id="modalNotaCreditoAvanzada" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Crear Nota de Crédito Avanzada</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <!-- Información de la factura -->
                                <div class="alert alert-info mb-4" id="infoFactura">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <strong>Subtotal:</strong><br>
                                            <span id="facturaSubtotal">$0.00</span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>IVA:</strong><br>
                                            <span id="facturaIva">$0.00</span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Total:</strong><br>
                                            <span id="facturaTotal">$0.00</span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Productos:</strong><br>
                                            <span id="facturaProductos">0</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Opción para toda la factura -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipo_nota" id="todaFactura" value="total" checked>
                                            <label class="form-check-label" for="todaFactura">
                                                <strong>Nota de Crédito por Toda la Factura</strong>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="card-body" id="opcionTodaFactura">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label class="form-label">Motivo:</label>
                                                <select class="form-select" id="motivoTodaFactura">
                                                    <option value="">Seleccionar motivo...</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Tipo de Monto:</label>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="montoTodaFactura" id="totalTodaFactura" value="total" checked>
                                                    <label class="form-check-label" for="totalTodaFactura">Total</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="montoTodaFactura" id="parcialTodaFactura" value="parcial">
                                                    <label class="form-check-label" for="parcialTodaFactura">Parcial</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="camposMontoTodaFactura" style="display: none;" class="mt-3">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <label class="form-label">Subtotal:</label>
                                                    <input type="number" class="form-control" id="subtotalTodaFactura" step="0.01" min="0">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">IVA:</label>
                                                    <input type="number" class="form-control" id="ivaTodaFactura" step="0.01" min="0">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Total:</label>
                                                    <input type="number" class="form-control" id="totalTodaFacturaInput" step="0.01" min="0" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Opción por productos individuales -->
                                <div class="card">
                                    <div class="card-header">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipo_nota" id="productosIndividuales" value="productos">
                                            <label class="form-check-label" for="productosIndividuales">
                                                <strong>Nota de Crédito por Productos Individuales</strong>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="card-body" id="opcionProductosIndividuales" style="display: none;">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Producto</th>
                                                        <th>Cantidad</th>
                                                        <th>Precio Unit.</th>
                                                        <th>Subtotal</th>
                                                        <th>IVA</th>
                                                        <th>Total</th>
                                                        <th>Motivo</th>
                                                        <th>Tipo</th>
                                                        <th>Acción</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tablaProductosFactura">
                                                    <tr>
                                                        <td colspan="9" class="text-center">
                                                            <div class="spinner-border" role="status">
                                                                <span class="visually-hidden">Cargando productos...</span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-primary" id="btnCrearNotaCreditoAvanzada" disabled onclick="confirmarCrearNotaCreditoAvanzada(${facturaId})">
                                    Crear Nota de Crédito
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Agregar modal al DOM si no existe
            if (!document.getElementById('modalNotaCreditoAvanzada')) {
                document.body.insertAdjacentHTML('beforeend', modalHtml);
            }
            
            // Cargar motivos y productos
            Promise.all([
                cargarMotivos(),
                cargarProductosFactura(facturaId)
            ]).then(() => {
                configurarEventListeners();
            }).catch(error => {
                console.error('Error cargando datos:', error);
                mostrarAlerta('Error al cargar los datos del modal', 'danger');
            });
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('modalNotaCreditoAvanzada'));
            modal.show();
            
            // Limpiar event listeners cuando se cierre el modal
            modal._element.addEventListener('hidden.bs.modal', function() {
                window.eventListenersConfigurados = false;
            });
        }
        
        function crearNotaDebito(facturaId) {
            // Mostrar modal avanzado para seleccionar productos
            const modalHtml = `
                <div class="modal fade" id="modalNotaDebitoAvanzada" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Crear Nota de Débito Avanzada</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <!-- Información de la factura -->
                                <div class="alert alert-danger mb-4" id="infoFacturaDebito">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <strong>Subtotal:</strong><br>
                                            <span id="facturaSubtotalDebito">$0.00</span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>IVA:</strong><br>
                                            <span id="facturaIvaDebito">$0.00</span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Total:</strong><br>
                                            <span id="facturaTotalDebito">$0.00</span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Productos:</strong><br>
                                            <span id="facturaProductosDebito">0</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Opciones de tipo de nota -->
                                <div class="mb-4">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="tipo_nota_debito" id="todaFacturaDebito" value="total" checked>
                                        <label class="form-check-label" for="todaFacturaDebito">
                                            <strong>Nota de Débito por Toda la Factura</strong>
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="tipo_nota_debito" id="productosIndividualesDebito" value="productos">
                                        <label class="form-check-label" for="productosIndividualesDebito">
                                            <strong>Nota de Débito por Productos Individuales</strong>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Configuración para toda la factura -->
                                <div id="configTodaFacturaDebito">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Motivo:</label>
                                            <select class="form-select" id="motivoTodaFacturaDebito" required>
                                                <option value="">Seleccionar motivo...</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Tipo de Monto:</label>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="tipo_monto_debito" id="totalDebito" value="total" checked>
                                                <label class="form-check-label" for="totalDebito">Total</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="tipo_monto_debito" id="parcialDebito" value="parcial">
                                                <label class="form-check-label" for="parcialDebito">Parcial</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Campos para monto parcial -->
                                    <div id="camposParcialDebito" style="display: none;">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <label class="form-label">Subtotal Parcial:</label>
                                                <input type="number" class="form-control" id="subtotalParcialDebito" step="0.01" min="0">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">IVA Parcial:</label>
                                                <input type="number" class="form-control" id="ivaParcialDebito" step="0.01" min="0">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Total Parcial:</label>
                                                <input type="number" class="form-control" id="totalParcialDebito" step="0.01" min="0" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Tabla de productos individuales -->
                                <div id="tablaProductosDebito" style="display: none;">
                                    <h6>Seleccionar Productos:</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Producto</th>
                                                    <th>Cantidad</th>
                                                    <th>Precio Unit.</th>
                                                    <th>Subtotal</th>
                                                    <th>IVA</th>
                                                    <th>Total</th>
                                                    <th>Motivo</th>
                                                    <th>Tipo</th>
                                                    <th>Acción</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tablaProductosFacturaDebito">
                                                <!-- Productos cargados dinámicamente -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-danger" id="btnCrearNotaDebitoAvanzada" onclick="confirmarCrearNotaDebitoAvanzada(<?php echo $factura['id']; ?>)" disabled>
                                    <i class="bi bi-file-earmark-minus"></i> Crear Nota de Débito
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Agregar modal al DOM si no existe
            if (!document.getElementById('modalNotaDebitoAvanzada')) {
                document.body.insertAdjacentHTML('beforeend', modalHtml);
            }
            
            // Cargar motivos y productos
            Promise.all([
                cargarMotivosDebito(),
                cargarProductosFacturaDebito(facturaId)
            ]).then(() => {
                configurarEventListenersDebito();
            }).catch(error => {
                console.error('Error cargando datos:', error);
                mostrarAlerta('Error al cargar los datos del modal', 'danger');
            });
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('modalNotaDebitoAvanzada'));
            modal.show();
            
            // Limpiar event listeners cuando se cierre el modal
            modal._element.addEventListener('hidden.bs.modal', function() {
                window.eventListenersConfiguradosDebito = false;
            });
        }
        
        function calcularTotalParcial() {
            const subtotal = parseFloat(document.getElementById('subtotalParcial').value) || 0;
            const iva = parseFloat(document.getElementById('ivaParcial').value) || 0;
            const total = subtotal + iva;
            document.getElementById('totalParcial').value = total.toFixed(2);
        }
        
        // Funciones auxiliares para el modal avanzado
        function cargarMotivos() {
            return fetch('ajax_notas_credito.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'obtener_motivos'})
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return response.json();
            })
            .then(data => {
                console.log('Motivos cargados:', data);
                if (data.success) {
                    const selectMotivo = document.getElementById('motivoTodaFactura');
                    if (selectMotivo) {
                        selectMotivo.innerHTML = '<option value="">Seleccionar motivo...</option>';
                        
                        data.motivos.forEach(motivo => {
                            const option = document.createElement('option');
                            option.value = motivo.codigo;
                            option.textContent = `${motivo.codigo} - ${motivo.descripcion}`;
                            option.dataset.descripcion = motivo.descripcion;
                            selectMotivo.appendChild(option);
                        });
                    }
                } else {
                    console.error('Error cargando motivos:', data.message);
                    throw new Error(data.message || 'Error al cargar motivos');
                }
            })
            .catch(error => {
                console.error('Error en cargarMotivos:', error);
                throw error;
            });
        }
        
        function cargarProductosFactura(facturaId) {
            return fetch('ajax_notas_credito.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'obtener_productos_factura', factura_id: facturaId})
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return response.json();
            })
            .then(data => {
                console.log('Productos cargados:', data);
                if (data.success) {
                    const tbody = document.getElementById('tablaProductosFactura');
                    if (tbody) {
                        tbody.innerHTML = '';
                        
                        data.productos.forEach((producto, index) => {
                            const itemId = producto.id; // Usar el ID del item de venta, no del producto
                            console.log('Generando fila para item:', itemId, 'producto:', producto.producto_id);
                            const row = `
                                <tr data-item-id="${itemId}" data-producto-id="${producto.producto_id}">
                                    <td>
                                        <strong>${producto.producto_nombre}</strong><br>
                                        <small class="text-muted">Ref: ${producto.producto_referencia}</small>
                                    </td>
                                    <td>${producto.cantidad}</td>
                                    <td>$${parseFloat(producto.precio_unitario).toFixed(2)}</td>
                                    <td>$${parseFloat(producto.subtotal).toFixed(2)}</td>
                                    <td>$${parseFloat(producto.iva).toFixed(2)}</td>
                                    <td>$${parseFloat(producto.total).toFixed(2)}</td>
                                    <td>
                                        <select class="form-select form-select-sm motivo-producto" data-item-id="${itemId}">
                                            <option value="">Seleccionar...</option>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input tipo-monto-producto" type="radio" name="tipo_${itemId}" value="total" checked>
                                            <label class="form-check-label">Total</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input tipo-monto-producto" type="radio" name="tipo_${itemId}" value="parcial">
                                            <label class="form-check-label">Parcial</label>
                                        </div>
                                        <div id="camposParcial_${itemId}" style="display: none;" class="mt-2 campos-parcial-container">
                                            <div class="card bg-light mt-2">
                                                <div class="card-body py-2">
                                                    <h6 class="card-title mb-2">Configuración Parcial - ${producto.producto_nombre}</h6>
                                                    <div class="row">
                                                        <div class="col-md-3">
                                                            <label class="form-label form-label-sm">Cantidad Parcial:</label>
                                                            <input type="number" class="form-control form-control-sm" placeholder="Cantidad" step="0.01" min="0" max="${producto.cantidad}" id="cantidadParcial_${itemId}" data-precio-unitario="${producto.precio_unitario}" data-iva-porcentaje="19">
                                                            <small class="text-muted">Máximo: ${producto.cantidad} unidades</small>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label form-label-sm">Subtotal:</label>
                                                            <input type="number" class="form-control form-control-sm" step="0.01" min="0" readonly id="subtotalParcial_${itemId}">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label form-label-sm">IVA:</label>
                                                            <input type="number" class="form-control form-control-sm" step="0.01" min="0" readonly id="ivaParcial_${itemId}">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label form-label-sm">Total:</label>
                                                            <input type="number" class="form-control form-control-sm" step="0.01" min="0" readonly id="totalParcial_${itemId}">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-outline-primary btn-sm" onclick="agregarProductoNotaCredito(${itemId})">
                                            <i class="bi bi-plus-circle"></i> Agregar
                                        </button>
                                    </td>
                                </tr>
                            `;
                            tbody.insertAdjacentHTML('beforeend', row);
                            console.log('Fila agregada para item:', itemId);
                        });
                        
                        // Cargar motivos en cada select
                        cargarMotivosEnSelects();
                        
                        // Cargar información de la factura
                        cargarInformacionFactura(data.productos);
                        
                        // Configurar event listeners una sola vez
                        if (!window.eventListenersConfigurados) {
                            configurarEventListenersProductos();
                            window.eventListenersConfigurados = true;
                        }
                    }
                } else {
                    console.error('Error cargando productos:', data.message);
                    throw new Error(data.message || 'Error al cargar productos');
                }
            })
            .catch(error => {
                console.error('Error en cargarProductosFactura:', error);
                throw error;
            });
        }
        
        function cargarMotivosEnSelects() {
            fetch('ajax_notas_credito.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'obtener_motivos'})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.motivo-producto').forEach(select => {
                        select.innerHTML = '<option value="">Seleccionar...</option>';
                        data.motivos.forEach(motivo => {
                            const option = document.createElement('option');
                            option.value = motivo.codigo;
                            option.textContent = `${motivo.codigo} - ${motivo.descripcion}`;
                            option.dataset.descripcion = motivo.descripcion;
                            select.appendChild(option);
                        });
                    });
                }
            });
        }
        
        function configurarEventListeners() {
            // Event listeners para tipo de nota
            document.querySelectorAll('input[name="tipo_nota"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const opcionTodaFactura = document.getElementById('opcionTodaFactura');
                    const opcionProductosIndividuales = document.getElementById('opcionProductosIndividuales');
                    
                    if (this.value === 'total') {
                        opcionTodaFactura.style.display = 'block';
                        opcionProductosIndividuales.style.display = 'none';
                    } else {
                        opcionTodaFactura.style.display = 'none';
                        opcionProductosIndividuales.style.display = 'block';
                    }
                    
                    validarFormulario();
                });
            });
            
            // Event listeners para monto toda factura
            document.querySelectorAll('input[name="montoTodaFactura"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const camposMonto = document.getElementById('camposMontoTodaFactura');
                    if (this.value === 'parcial') {
                        camposMonto.style.display = 'block';
                    } else {
                        camposMonto.style.display = 'none';
                    }
                });
            });
            
            // Calcular total automáticamente
            document.getElementById('subtotalTodaFactura').addEventListener('input', calcularTotalTodaFactura);
            document.getElementById('ivaTodaFactura').addEventListener('input', calcularTotalTodaFactura);
            
            // Validar formulario cuando cambie el motivo
            document.getElementById('motivoTodaFactura').addEventListener('change', validarFormulario);
        }
        
        function configurarEventListenersProductos() {
            // Usar event delegation específicamente en el modal
            const modal = document.getElementById('modalNotaCreditoAvanzada');
            
            modal.addEventListener('change', function(e) {
                if (e.target.classList.contains('tipo-monto-producto')) {
                    const itemId = e.target.name.replace('tipo_', '');
                    const camposParcial = document.getElementById(`camposParcial_${itemId}`);
                    
                    console.log('Cambio detectado en item:', itemId);
                    console.log('Valor seleccionado:', e.target.value);
                    console.log('Elemento camposParcial encontrado:', camposParcial);
                    
                    if (e.target.value === 'parcial') {
                        camposParcial.style.display = 'block';
                        console.log('Mostrando campos para item:', itemId);
                    } else {
                        camposParcial.style.display = 'none';
                        console.log('Ocultando campos para item:', itemId);
                    }
                }
            });
            
            modal.addEventListener('input', function(e) {
                if (e.target.id && e.target.id.startsWith('cantidadParcial_')) {
                    const itemId = e.target.id.split('_')[1];
                    console.log('Calculando montos para item:', itemId);
                    calcularMontosParcialesPorCantidad(itemId);
                }
            });
        }
        
        function cargarInformacionFactura(productos) {
            let subtotalTotal = 0;
            let ivaTotal = 0;
            let totalTotal = 0;
            
            productos.forEach(producto => {
                subtotalTotal += parseFloat(producto.subtotal) || 0;
                ivaTotal += parseFloat(producto.iva) || 0;
                totalTotal += parseFloat(producto.total) || 0;
            });
            
            document.getElementById('facturaSubtotal').textContent = '$' + subtotalTotal.toFixed(2);
            document.getElementById('facturaIva').textContent = '$' + ivaTotal.toFixed(2);
            document.getElementById('facturaTotal').textContent = '$' + totalTotal.toFixed(2);
            document.getElementById('facturaProductos').textContent = productos.length;
        }
        
        function calcularMontosParcialesPorCantidad(itemId) {
            const cantidadInput = document.getElementById(`cantidadParcial_${itemId}`);
            const cantidad = parseFloat(cantidadInput.value) || 0;
            const precioUnitario = parseFloat(cantidadInput.dataset.precioUnitario) || 0;
            const ivaPorcentaje = parseFloat(cantidadInput.dataset.ivaPorcentaje) || 19; // Default 19%
            
            if (cantidad > 0 && precioUnitario > 0) {
                const subtotal = cantidad * precioUnitario;
                const iva = subtotal * (ivaPorcentaje / 100);
                const total = subtotal + iva;
                
                document.getElementById(`subtotalParcial_${itemId}`).value = subtotal.toFixed(2);
                document.getElementById(`ivaParcial_${itemId}`).value = iva.toFixed(2);
                document.getElementById(`totalParcial_${itemId}`).value = total.toFixed(2);
            } else {
                document.getElementById(`subtotalParcial_${itemId}`).value = '0.00';
                document.getElementById(`ivaParcial_${itemId}`).value = '0.00';
                document.getElementById(`totalParcial_${itemId}`).value = '0.00';
            }
        }
        
        function calcularTotalTodaFactura() {
            const subtotal = parseFloat(document.getElementById('subtotalTodaFactura').value) || 0;
            const iva = parseFloat(document.getElementById('ivaTodaFactura').value) || 0;
            const total = subtotal + iva;
            document.getElementById('totalTodaFacturaInput').value = total.toFixed(2);
        }
        
        function validarFormulario() {
            const tipoNota = document.querySelector('input[name="tipo_nota"]:checked').value;
            let esValido = false;
            
            if (tipoNota === 'total') {
                const motivo = document.getElementById('motivoTodaFactura').value;
                esValido = motivo !== '';
            } else {
                // Validar que al menos un producto tenga motivo seleccionado
                const productosConMotivo = document.querySelectorAll('.motivo-producto').length;
                esValido = productosConMotivo > 0;
            }
            
            document.getElementById('btnCrearNotaCreditoAvanzada').disabled = !esValido;
        }
        
        function agregarProductoNotaCredito(itemId) {
            // Buscar específicamente en el modal para evitar conflictos
            const modal = document.getElementById('modalNotaCreditoAvanzada');
            const motivoSelect = modal.querySelector(`select[data-item-id="${itemId}"]`);
            const tipoMontoRadio = modal.querySelector(`input[name="tipo_${itemId}"]:checked`);
            
            if (!motivoSelect || !motivoSelect.value) {
                mostrarAlerta('Debe seleccionar un motivo para este producto', 'warning');
                return;
            }
            
            if (!tipoMontoRadio) {
                mostrarAlerta('Debe seleccionar un tipo de monto para este producto', 'warning');
                return;
            }
            
            // Validar campos para monto parcial
            if (tipoMontoRadio.value === 'parcial') {
                const cantidadParcial = parseFloat(document.getElementById(`cantidadParcial_${itemId}`).value) || 0;
                
                if (cantidadParcial <= 0) {
                    mostrarAlerta('Debe ingresar una cantidad válida para el monto parcial de este producto', 'warning');
                    return;
                }
            }
            
            // Marcar el producto como seleccionado
            const row = modal.querySelector(`tr[data-item-id="${itemId}"]`);
            if (row) {
                row.classList.add('table-success');
                const button = row.querySelector('button');
                button.innerHTML = '<i class="bi bi-check-circle"></i> Agregado';
                button.classList.remove('btn-outline-primary');
                button.classList.add('btn-success');
                button.disabled = true;
            }
            
            validarFormulario();
        }
        
        function confirmarCrearNotaCreditoAvanzada(facturaId) {
            const tipoNota = document.querySelector('input[name="tipo_nota"]:checked').value;
            
            if (tipoNota === 'total') {
                confirmarCrearNotaCreditoTodaFactura(facturaId);
            } else {
                confirmarCrearNotaCreditoProductos(facturaId);
            }
        }
        
        function confirmarCrearNotaCreditoTodaFactura(facturaId) {
            const motivoCodigo = document.getElementById('motivoTodaFactura').value;
            const motivoDescripcion = document.getElementById('motivoTodaFactura').selectedOptions[0].dataset.descripcion;
            const tipoMonto = document.querySelector('input[name="montoTodaFactura"]:checked').value;
            
            if (!motivoCodigo) {
                mostrarAlerta('Debe seleccionar un motivo', 'warning');
                return;
            }
            
            const confirmMessage = tipoMonto === 'total' 
                ? `¿Crear nota de crédito TOTAL por toda la factura?\n\nMotivo: ${motivoCodigo} - ${motivoDescripcion}`
                : `¿Crear nota de crédito PARCIAL por toda la factura?\n\nMotivo: ${motivoCodigo} - ${motivoDescripcion}\n\nMonto: $${document.getElementById('totalTodaFacturaInput').value}`;
            
            if (confirm(confirmMessage)) {
                const datos = {
                    action: 'crear_nota_credito_avanzada',
                    factura_id: facturaId,
                    tipo: 'total',
                    motivo_codigo: motivoCodigo,
                    motivo: motivoDescripcion,
                    tipo_monto: tipoMonto
                };
                
                if (tipoMonto === 'parcial') {
                    datos.subtotal_parcial = document.getElementById('subtotalTodaFactura').value;
                    datos.iva_parcial = document.getElementById('ivaTodaFactura').value;
                    datos.total_parcial = document.getElementById('totalTodaFacturaInput').value;
                }
                
                enviarNotaCreditoAvanzada(datos);
            }
        }
        
        function confirmarCrearNotaCreditoProductos(facturaId) {
            const modal = document.getElementById('modalNotaCreditoAvanzada');
            const productosSeleccionados = [];
            
            modal.querySelectorAll('tr.table-success').forEach(row => {
                const itemId = row.dataset.itemId;
                const motivoSelect = modal.querySelector(`select[data-item-id="${itemId}"]`);
                const tipoMontoRadio = modal.querySelector(`input[name="tipo_${itemId}"]:checked`);
                
                if (motivoSelect && tipoMontoRadio) {
                    const producto = {
                        item_id: itemId,
                        producto_id: row.dataset.productoId,
                        motivo_codigo: motivoSelect.value,
                        motivo: motivoSelect.selectedOptions[0].dataset.descripcion,
                        tipo_monto: tipoMontoRadio.value
                    };
                    
                    // Agregar datos de monto parcial si es necesario
                    if (tipoMontoRadio.value === 'parcial') {
                        producto.cantidad_parcial = document.getElementById(`cantidadParcial_${itemId}`).value;
                        producto.subtotal_parcial = document.getElementById(`subtotalParcial_${itemId}`).value;
                        producto.iva_parcial = document.getElementById(`ivaParcial_${itemId}`).value;
                        producto.total_parcial = document.getElementById(`totalParcial_${itemId}`).value;
                    }
                    
                    productosSeleccionados.push(producto);
                }
            });
            
            if (productosSeleccionados.length === 0) {
                mostrarAlerta('Debe seleccionar al menos un producto', 'warning');
                return;
            }
            
            const confirmMessage = `¿Crear nota de crédito por ${productosSeleccionados.length} producto(s)?\n\nProductos seleccionados:\n${productosSeleccionados.map(p => `- ${p.motivo_codigo} - ${p.motivo} (${p.tipo_monto})`).join('\n')}`;
            
            if (confirm(confirmMessage)) {
                const datos = {
                    action: 'crear_nota_credito_avanzada',
                    factura_id: facturaId,
                    tipo: 'productos',
                    productos: productosSeleccionados
                };
                
                enviarNotaCreditoAvanzada(datos);
            }
        }
        
        function enviarNotaCreditoAvanzada(datos) {
            fetch('ajax_notas_credito.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(datos)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta('✅ ' + data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalNotaCreditoAvanzada')).hide();
                    setTimeout(() => location.reload(), 2000);
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al crear la nota de crédito', 'danger');
            });
        }
        
        function enviarMasivoDIAN() {
            // Obtener facturas pendientes
            fetch('ajax_facturas.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'obtener_facturas_pendientes'})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.facturas.length > 0) {
                    const cantidad = data.facturas.length;
                    if (confirm(`¿Desea enviar ${cantidad} facturas pendientes a DIAN?`)) {
                        mostrarAlerta(`📡 Iniciando envío masivo de ${cantidad} facturas a DIAN...`, 'info');
                        enviarFacturasSecuencial(data.facturas, 0);
                    }
                } else {
                    mostrarAlerta('No hay facturas pendientes para enviar a DIAN', 'warning');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al obtener facturas pendientes', 'danger');
            });
        }
        
        function enviarFacturasSecuencial(facturas, index) {
            if (index >= facturas.length) {
                mostrarAlerta('✅ Envío masivo completado. Todas las facturas han sido procesadas.', 'success');
                setTimeout(() => location.reload(), 3000);
                return;
            }
            
            const factura = facturas[index];
            mostrarAlerta(`📤 Enviando factura ${factura.numero_factura} (${index + 1}/${facturas.length})...`, 'info');
            
            fetch('ajax_facturacion_electronica.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=enviar_factura&factura_id=${factura.id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta(`✅ Factura ${factura.numero_factura} enviada exitosamente`, 'success');
                } else {
                    mostrarAlerta(`❌ Error en factura ${factura.numero_factura}: ${data.message}`, 'warning');
                }
                
                // Continuar con la siguiente factura después de un breve delay
                setTimeout(() => {
                    enviarFacturasSecuencial(facturas, index + 1);
                }, 2000);
            })
            .catch(error => {
                mostrarAlerta(`❌ Error de conexión en factura ${factura.numero_factura}`, 'danger');
                setTimeout(() => {
                    enviarFacturasSecuencial(facturas, index + 1);
                }, 2000);
            });
        }
        
        function cancelarFactura(id) {
            if (confirm('¿Está seguro de que desea cancelar esta factura? Esta acción no se puede deshacer.')) {
                fetch('ajax_cancelar_factura.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarAlerta('❌ ' + data.message, 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        mostrarAlerta(data.message, 'danger');
                    }
                })
                .catch(error => {
                    mostrarAlerta('Error al cancelar la factura', 'danger');
                });
            }
        }
        
        function marcarPagada(id) {
            if (confirm('¿Marcar esta factura como pagada?')) {
                fetch('ajax_marcar_pagada.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarAlerta(data.message, 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        mostrarAlerta(data.message, 'danger');
                    }
                });
            }
        }
        
        function enviarEmail(facturaId) {
            const email = prompt('Ingresa el email del cliente:');
            if (!email) return;
            
            fetch('ajax_facturas.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'enviar_email',
                    factura_id: facturaId,
                    email: email
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta('Factura enviada por email exitosamente', 'success');
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            });
        }
        
        function mostrarAlerta(mensaje, tipo) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${tipo} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${mensaje}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 5000);
        }
        
        // Funciones para Notas de Débito
        function cargarMotivosDebito() {
            return fetch('ajax_notas_debito.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'obtener_motivos'})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const selectMotivo = document.getElementById('motivoTodaFacturaDebito');
                    if (selectMotivo) {
                        selectMotivo.innerHTML = '<option value="">Seleccionar motivo...</option>';
                        data.motivos.forEach(motivo => {
                            const option = document.createElement('option');
                            option.value = motivo.codigo;
                            option.textContent = `${motivo.codigo} - ${motivo.descripcion}`;
                            option.dataset.descripcion = motivo.descripcion;
                            selectMotivo.appendChild(option);
                        });
                    }
                }
            });
        }
        
        function cargarProductosFacturaDebito(facturaId) {
            return fetch('ajax_notas_debito.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'obtener_productos_factura', factura_id: facturaId})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.getElementById('tablaProductosFacturaDebito');
                    if (tbody) {
                        tbody.innerHTML = '';
                        
                        data.productos.forEach((producto, index) => {
                            const itemId = producto.id;
                            const row = `
                                <tr data-item-id="${itemId}" data-producto-id="${producto.producto_id}">
                                    <td>
                                        <strong>${producto.producto_nombre}</strong><br>
                                        <small class="text-muted">Ref: ${producto.producto_referencia}</small>
                                    </td>
                                    <td>${producto.cantidad}</td>
                                    <td>$${parseFloat(producto.precio_unitario).toFixed(2)}</td>
                                    <td>$${parseFloat(producto.subtotal).toFixed(2)}</td>
                                    <td>$${parseFloat(producto.iva).toFixed(2)}</td>
                                    <td>$${parseFloat(producto.total).toFixed(2)}</td>
                                    <td>
                                        <select class="form-select form-select-sm motivo-producto-debito" data-item-id="${itemId}">
                                            <option value="">Seleccionar...</option>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input tipo-monto-producto-debito" type="radio" name="tipo_${itemId}" value="total" checked>
                                            <label class="form-check-label">Total</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input tipo-monto-producto-debito" type="radio" name="tipo_${itemId}" value="parcial">
                                            <label class="form-check-label">Parcial</label>
                                        </div>
                                        <div id="camposParcialDebito_${itemId}" style="display: none;" class="mt-2 campos-parcial-container">
                                            <div class="card bg-light mt-2">
                                                <div class="card-body py-2">
                                                    <h6 class="card-title mb-2">Configuración Parcial - ${producto.producto_nombre}</h6>
                                                    <div class="row">
                                                        <div class="col-md-3">
                                                            <label class="form-label form-label-sm">Cantidad Parcial:</label>
                                                            <input type="number" class="form-control form-control-sm" placeholder="Cantidad" step="0.01" min="0" max="${producto.cantidad}" id="cantidadParcialDebito_${itemId}" data-precio-unitario="${producto.precio_unitario}" data-iva-porcentaje="19">
                                                            <small class="text-muted">Máximo: ${producto.cantidad} unidades</small>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label form-label-sm">Subtotal:</label>
                                                            <input type="number" class="form-control form-control-sm" step="0.01" min="0" readonly id="subtotalParcialDebito_${itemId}">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label form-label-sm">IVA:</label>
                                                            <input type="number" class="form-control form-control-sm" step="0.01" min="0" readonly id="ivaParcialDebito_${itemId}">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label form-label-sm">Total:</label>
                                                            <input type="number" class="form-control form-control-sm" step="0.01" min="0" readonly id="totalParcialDebito_${itemId}">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-outline-danger btn-sm" onclick="agregarProductoNotaDebito(${itemId})">
                                            <i class="bi bi-plus-circle"></i> Agregar
                                        </button>
                                    </td>
                                </tr>
                            `;
                            tbody.insertAdjacentHTML('beforeend', row);
                        });
                        
                        cargarMotivosEnSelectsDebito();
                        cargarInformacionFacturaDebito(data.productos);
                        
                        if (!window.eventListenersConfiguradosDebito) {
                            configurarEventListenersDebito();
                            window.eventListenersConfiguradosDebito = true;
                        }
                    }
                }
            });
        }
        
        function cargarMotivosEnSelectsDebito() {
            fetch('ajax_notas_debito.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'obtener_motivos'})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.motivo-producto-debito').forEach(select => {
                        select.innerHTML = '<option value="">Seleccionar...</option>';
                        data.motivos.forEach(motivo => {
                            const option = document.createElement('option');
                            option.value = motivo.codigo;
                            option.textContent = `${motivo.codigo} - ${motivo.descripcion}`;
                            option.dataset.descripcion = motivo.descripcion;
                            select.appendChild(option);
                        });
                    });
                }
            });
        }
        
        function cargarInformacionFacturaDebito(productos) {
            let subtotalTotal = 0;
            let ivaTotal = 0;
            let totalTotal = 0;
            
            productos.forEach(producto => {
                subtotalTotal += parseFloat(producto.subtotal) || 0;
                ivaTotal += parseFloat(producto.iva) || 0;
                totalTotal += parseFloat(producto.total) || 0;
            });
            
            document.getElementById('facturaSubtotalDebito').textContent = '$' + subtotalTotal.toFixed(2);
            document.getElementById('facturaIvaDebito').textContent = '$' + ivaTotal.toFixed(2);
            document.getElementById('facturaTotalDebito').textContent = '$' + totalTotal.toFixed(2);
            document.getElementById('facturaProductosDebito').textContent = productos.length;
        }
        
        function configurarEventListenersDebito() {
            const modal = document.getElementById('modalNotaDebitoAvanzada');
            
            // Cambio de tipo de nota
            modal.addEventListener('change', function(e) {
                if (e.target.name === 'tipo_nota_debito') {
                    const configTodaFactura = document.getElementById('configTodaFacturaDebito');
                    const tablaProductos = document.getElementById('tablaProductosDebito');
                    
                    if (e.target.value === 'total') {
                        configTodaFactura.style.display = 'block';
                        tablaProductos.style.display = 'none';
                    } else {
                        configTodaFactura.style.display = 'none';
                        tablaProductos.style.display = 'block';
                    }
                    validarFormularioDebito();
                }
                
                if (e.target.name === 'tipo_monto_debito') {
                    const camposParcial = document.getElementById('camposParcialDebito');
                    if (e.target.value === 'parcial') {
                        camposParcial.style.display = 'block';
                    } else {
                        camposParcial.style.display = 'none';
                    }
                    validarFormularioDebito();
                }
                
                if (e.target.classList.contains('tipo-monto-producto-debito')) {
                    const itemId = e.target.name.replace('tipo_', '');
                    const camposParcial = document.getElementById(`camposParcialDebito_${itemId}`);
                    
                    if (e.target.value === 'parcial') {
                        camposParcial.style.display = 'block';
                    } else {
                        camposParcial.style.display = 'none';
                    }
                }
            });
            
            modal.addEventListener('input', function(e) {
                if (e.target.id && e.target.id.startsWith('cantidadParcialDebito_')) {
                    const itemId = e.target.id.split('_')[1];
                    calcularMontosParcialesPorCantidadDebito(itemId);
                }
                
                if (e.target.id === 'subtotalParcialDebito' || e.target.id === 'ivaParcialDebito') {
                    calcularTotalParcialDebito();
                }
            });
            
            document.getElementById('motivoTodaFacturaDebito').addEventListener('change', validarFormularioDebito);
        }
        
        function calcularMontosParcialesPorCantidadDebito(itemId) {
            const cantidadInput = document.getElementById(`cantidadParcialDebito_${itemId}`);
            const cantidad = parseFloat(cantidadInput.value) || 0;
            const precioUnitario = parseFloat(cantidadInput.dataset.precioUnitario) || 0;
            const ivaPorcentaje = parseFloat(cantidadInput.dataset.ivaPorcentaje) || 19;
            
            if (cantidad > 0 && precioUnitario > 0) {
                const subtotal = cantidad * precioUnitario;
                const iva = subtotal * (ivaPorcentaje / 100);
                const total = subtotal + iva;
                
                document.getElementById(`subtotalParcialDebito_${itemId}`).value = subtotal.toFixed(2);
                document.getElementById(`ivaParcialDebito_${itemId}`).value = iva.toFixed(2);
                document.getElementById(`totalParcialDebito_${itemId}`).value = total.toFixed(2);
            } else {
                document.getElementById(`subtotalParcialDebito_${itemId}`).value = '0.00';
                document.getElementById(`ivaParcialDebito_${itemId}`).value = '0.00';
                document.getElementById(`totalParcialDebito_${itemId}`).value = '0.00';
            }
        }
        
        function calcularTotalParcialDebito() {
            const subtotal = parseFloat(document.getElementById('subtotalParcialDebito').value) || 0;
            const iva = parseFloat(document.getElementById('ivaParcialDebito').value) || 0;
            const total = subtotal + iva;
            document.getElementById('totalParcialDebito').value = total.toFixed(2);
        }
        
        function validarFormularioDebito() {
            const tipoNota = document.querySelector('input[name="tipo_nota_debito"]:checked').value;
            let esValido = false;
            
            if (tipoNota === 'total') {
                const motivo = document.getElementById('motivoTodaFacturaDebito').value;
                esValido = motivo !== '';
            } else {
                const productosSeleccionados = document.querySelectorAll('#tablaProductosFacturaDebito tr.table-success').length;
                esValido = productosSeleccionados > 0;
            }
            
            document.getElementById('btnCrearNotaDebitoAvanzada').disabled = !esValido;
        }
        
        function agregarProductoNotaDebito(itemId) {
            const modal = document.getElementById('modalNotaDebitoAvanzada');
            const motivoSelect = modal.querySelector(`select[data-item-id="${itemId}"]`);
            const tipoMontoRadio = modal.querySelector(`input[name="tipo_${itemId}"]:checked`);
            
            if (!motivoSelect || !motivoSelect.value) {
                mostrarAlerta('Debe seleccionar un motivo para este producto', 'warning');
                return;
            }
            
            if (!tipoMontoRadio) {
                mostrarAlerta('Debe seleccionar un tipo de monto para este producto', 'warning');
                return;
            }
            
            if (tipoMontoRadio.value === 'parcial') {
                const cantidadParcial = parseFloat(document.getElementById(`cantidadParcialDebito_${itemId}`).value) || 0;
                
                if (cantidadParcial <= 0) {
                    mostrarAlerta('Debe ingresar una cantidad válida para el monto parcial de este producto', 'warning');
                    return;
                }
            }
            
            const row = modal.querySelector(`tr[data-item-id="${itemId}"]`);
            if (row) {
                row.classList.add('table-success');
                const button = row.querySelector('button');
                button.innerHTML = '<i class="bi bi-check-circle"></i> Agregado';
                button.classList.remove('btn-outline-danger');
                button.classList.add('btn-success');
                button.disabled = true;
            }
            
            validarFormularioDebito();
        }
        
        function confirmarCrearNotaDebitoAvanzada(facturaId) {
            const tipoNota = document.querySelector('input[name="tipo_nota_debito"]:checked').value;
            
            if (tipoNota === 'total') {
                confirmarCrearNotaDebitoTodaFactura(facturaId);
            } else {
                confirmarCrearNotaDebitoProductos(facturaId);
            }
        }
        
        function confirmarCrearNotaDebitoTodaFactura(facturaId) {
            const motivoCodigo = document.getElementById('motivoTodaFacturaDebito').value;
            const motivoDescripcion = document.getElementById('motivoTodaFacturaDebito').selectedOptions[0].dataset.descripcion;
            const tipoMonto = document.querySelector('input[name="tipo_monto_debito"]:checked').value;
            
            const datos = {
                action: 'crear_nota_debito_avanzada',
                factura_id: facturaId,
                tipo: 'total',
                motivo_codigo: motivoCodigo,
                motivo: motivoDescripcion,
                tipo_monto: tipoMonto
            };
            
            if (tipoMonto === 'parcial') {
                datos.subtotal_parcial = document.getElementById('subtotalParcialDebito').value;
                datos.iva_parcial = document.getElementById('ivaParcialDebito').value;
                datos.total_parcial = document.getElementById('totalParcialDebito').value;
            }
            
            fetch('ajax_notas_debito.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(datos)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta('✅ ' + data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalNotaDebitoAvanzada')).hide();
                    setTimeout(() => location.reload(), 2000);
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al crear la nota de débito', 'danger');
            });
        }
        
        function confirmarCrearNotaDebitoProductos(facturaId) {
            const modal = document.getElementById('modalNotaDebitoAvanzada');
            const productosSeleccionados = [];
            
            modal.querySelectorAll('tr.table-success').forEach(row => {
                const itemId = row.dataset.itemId;
                const motivoSelect = modal.querySelector(`select[data-item-id="${itemId}"]`);
                const tipoMontoRadio = modal.querySelector(`input[name="tipo_${itemId}"]:checked`);
                
                if (motivoSelect && tipoMontoRadio) {
                    const producto = {
                        item_id: itemId,
                        producto_id: row.dataset.productoId,
                        motivo_codigo: motivoSelect.value,
                        motivo: motivoSelect.selectedOptions[0].dataset.descripcion,
                        tipo_monto: tipoMontoRadio.value
                    };
                    
                    if (tipoMontoRadio.value === 'parcial') {
                        producto.cantidad_parcial = document.getElementById(`cantidadParcialDebito_${itemId}`).value;
                        producto.subtotal_parcial = document.getElementById(`subtotalParcialDebito_${itemId}`).value;
                        producto.iva_parcial = document.getElementById(`ivaParcialDebito_${itemId}`).value;
                        producto.total_parcial = document.getElementById(`totalParcialDebito_${itemId}`).value;
                    }
                    
                    productosSeleccionados.push(producto);
                }
            });
            
            if (productosSeleccionados.length === 0) {
                mostrarAlerta('Debe seleccionar al menos un producto', 'warning');
                return;
            }
            
            const datos = {
                action: 'crear_nota_debito_avanzada',
                factura_id: facturaId,
                tipo: 'productos',
                productos: productosSeleccionados
            };
            
            fetch('ajax_notas_debito.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(datos)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta('✅ ' + data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalNotaDebitoAvanzada')).hide();
                    setTimeout(() => location.reload(), 2000);
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al crear la nota de débito', 'danger');
            });
        }
        
        // Funciones del Validador DIAN
        function abrirValidadorDIAN() {
            const modal = new bootstrap.Modal(document.getElementById('modalValidadorDIAN'));
            modal.show();
        }
        
        function validarFacturas() {
            const resultadoDiv = document.getElementById('resultadoFacturas');
            resultadoDiv.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Validando...</span>
                    </div>
                    <p class="mt-2">Validando facturas...</p>
                </div>
            `;
            
            fetch('ajax_validador_dian.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=validar_facturas'
            })
            .then(response => response.json())
            .then(data => {
                mostrarResultadoValidacion('resultadoFacturas', data, 'Facturas');
            })
            .catch(error => {
                resultadoDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Error:</strong> No se pudo ejecutar la validación de facturas.
                    </div>
                `;
            });
        }
        
        function validarNotasCredito() {
            const resultadoDiv = document.getElementById('resultadoNotasCredito');
            resultadoDiv.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Validando...</span>
                    </div>
                    <p class="mt-2">Validando notas de crédito...</p>
                </div>
            `;
            
            fetch('ajax_validador_dian.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=validar_notas_credito'
            })
            .then(response => response.json())
            .then(data => {
                mostrarResultadoValidacion('resultadoNotasCredito', data, 'Notas de Crédito');
            })
            .catch(error => {
                resultadoDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Error:</strong> No se pudo ejecutar la validación de notas de crédito.
                    </div>
                `;
            });
        }
        
        function validarNotasDebito() {
            const resultadoDiv = document.getElementById('resultadoNotasDebito');
            resultadoDiv.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Validando...</span>
                    </div>
                    <p class="mt-2">Validando notas de débito...</p>
                </div>
            `;
            
            fetch('ajax_validador_dian.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=validar_notas_debito'
            })
            .then(response => response.json())
            .then(data => {
                mostrarResultadoValidacion('resultadoNotasDebito', data, 'Notas de Débito');
            })
            .catch(error => {
                resultadoDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Error:</strong> No se pudo ejecutar la validación de notas de débito.
                    </div>
                `;
            });
        }
        
        function mostrarResultadoValidacion(divId, data, tipoDocumento) {
            const resultadoDiv = document.getElementById(divId);
            
            if (data.success) {
                let html = `
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i>
                        <strong>✅ Validación Exitosa</strong><br>
                        ${data.total_documentos || 0} ${tipoDocumento.toLowerCase()} validados correctamente.
                    </div>
                `;
                
                if (data.documentos_validos && data.documentos_validos.length > 0) {
                    html += `
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-check-circle text-success"></i> Documentos Validados</h6>
                            </div>
                            <div class="card-body">
                    `;
                    
                    data.documentos_validos.forEach((doc, index) => {
                        const numeroDoc = doc.numero_factura || doc.numero_nota || 'N/A';
                        const fechaDoc = doc.fecha_venta || doc.fecha_creacion || doc.creado_en || 'N/A';
                        
                        html += `
                            <div class="card mb-3">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">
                                        <i class="bi bi-file-text"></i> ${numeroDoc}
                                        <span class="badge bg-${doc.estado.includes('✅') ? 'success' : 'danger'} ms-2">${doc.estado}</span>
                                    </h6>
                                    <small class="text-muted">${fechaDoc}</small>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Cliente:</strong> ${doc.cliente_nombre || 'N/A'}<br>
                                            <strong>Total:</strong> $${parseFloat(doc.total || 0).toLocaleString()}<br>
                                            <strong>Items:</strong> ${doc.items_count || 0}<br>
                                            <button class="btn btn-sm btn-outline-primary mt-2" onclick="verXMLDocumento('${numeroDoc}', '${doc.numero_factura ? 'factura' : doc.numero_nota ? 'nota' : 'documento'}', ${doc.id})">
                                                <i class="bi bi-eye"></i> Ver XML
                                            </button>
                                        </div>
                                        <div class="col-md-6">
                                            ${doc.errores_documento ? `
                                                <div class="alert alert-danger alert-sm">
                                                    <strong>Errores:</strong><br>
                                                    ${doc.errores_documento.map(error => `• ${error}`).join('<br>')}
                                                </div>
                                            ` : ''}
                                        </div>
                                    </div>
                                    
                                    ${doc.items && doc.items.length > 0 ? `
                                        <div class="mt-3">
                                            <h6><i class="bi bi-list-ul"></i> Items del Documento:</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Producto</th>
                                                            <th>Cantidad</th>
                                                            <th>Precio Unit.</th>
                                                            <th>Subtotal</th>
                                                            <th>Estado</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        ${doc.items.map(item => `
                                                            <tr>
                                                                <td>${item.producto_nombre || 'Producto'}</td>
                                                                <td>${item.cantidad || 0}</td>
                                                                <td>$${parseFloat(item.precio_unitario || 0).toLocaleString()}</td>
                                                                <td>$${parseFloat(item.subtotal || 0).toLocaleString()}</td>
                                                                <td><span class="badge bg-success">✅ OK</span></td>
                                                            </tr>
                                                        `).join('')}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        `;
                    });
                    
                    html += `
                            </div>
                        </div>
                    `;
                }
                
                resultadoDiv.innerHTML = html;
            } else {
                let html = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>❌ Validación Fallida</strong><br>
                        ${data.message || 'Se encontraron errores en la validación.'}
                    </div>
                `;
                
                // Mostrar documentos con errores
                if (data.documentos_validos && data.documentos_validos.length > 0) {
                    html += `
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-exclamation-triangle text-danger"></i> Documentos con Problemas</h6>
                            </div>
                            <div class="card-body">
                    `;
                    
                    data.documentos_validos.forEach((doc, index) => {
                        const numeroDoc = doc.numero_factura || doc.numero_nota || 'N/A';
                        const fechaDoc = doc.fecha_venta || doc.fecha_creacion || doc.creado_en || 'N/A';
                        
                        html += `
                            <div class="card mb-3 border-danger">
                                <div class="card-header d-flex justify-content-between align-items-center bg-danger text-white">
                                    <h6 class="mb-0">
                                        <i class="bi bi-file-text"></i> ${numeroDoc}
                                        <span class="badge bg-light text-dark ms-2">${doc.estado}</span>
                                    </h6>
                                    <small>${fechaDoc}</small>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Cliente:</strong> ${doc.cliente_nombre || 'N/A'}<br>
                                            <strong>Total:</strong> $${parseFloat(doc.total || 0).toLocaleString()}<br>
                                            <strong>Items:</strong> ${doc.items_count || 0}<br>
                                            <button class="btn btn-sm btn-outline-primary mt-2" onclick="verXMLDocumento('${numeroDoc}', '${doc.numero_factura ? 'factura' : doc.numero_nota ? 'nota' : 'documento'}', ${doc.id})">
                                                <i class="bi bi-eye"></i> Ver XML
                                            </button>
                                        </div>
                                        <div class="col-md-6">
                                            ${doc.errores_documento ? `
                                                <div class="alert alert-danger alert-sm">
                                                    <strong>Errores:</strong><br>
                                                    ${doc.errores_documento.map(error => `• ${error}`).join('<br>')}
                                                </div>
                                            ` : ''}
                                        </div>
                                    </div>
                                    
                                    ${doc.items && doc.items.length > 0 ? `
                                        <div class="mt-3">
                                            <h6><i class="bi bi-list-ul"></i> Items del Documento:</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Producto</th>
                                                            <th>Cantidad</th>
                                                            <th>Precio Unit.</th>
                                                            <th>Subtotal</th>
                                                            <th>Estado</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        ${doc.items.map(item => `
                                                            <tr>
                                                                <td>${item.producto_nombre || 'Producto'}</td>
                                                                <td>${item.cantidad || 0}</td>
                                                                <td>$${parseFloat(item.precio_unitario || 0).toLocaleString()}</td>
                                                                <td>$${parseFloat(item.subtotal || 0).toLocaleString()}</td>
                                                                <td><span class="badge bg-success">✅ OK</span></td>
                                                            </tr>
                                                        `).join('')}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        `;
                    });
                    
                    html += `
                            </div>
                        </div>
                    `;
                }
                
                if (data.errores && data.errores.length > 0) {
                    html += `
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-exclamation-triangle text-danger"></i> Errores Generales</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                    `;
                    
                    data.errores.forEach(error => {
                        html += `<li class="list-group-item"><i class="bi bi-x-circle text-danger"></i> ${error}</li>`;
                    });
                    
                    html += `
                                </ul>
                            </div>
                        </div>
                    `;
                }
                
                if (data.advertencias && data.advertencias.length > 0) {
                    html += `
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-exclamation-triangle text-warning"></i> Advertencias</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                    `;
                    
                    data.advertencias.forEach(advertencia => {
                        html += `<li class="list-group-item"><i class="bi bi-exclamation-triangle text-warning"></i> ${advertencia}</li>`;
                    });
                    
                    html += `
                                </ul>
                            </div>
                        </div>
                    `;
                }
                
                resultadoDiv.innerHTML = html;
            }
        }
        
        // Funciones auxiliares para validaciones detalladas
        function groupBy(array, key) {
            return array.reduce((groups, item) => {
                const group = item[key];
                groups[group] = groups[group] || [];
                groups[group].push(item);
                return groups;
            }, {});
        }
        
        function getCategoriaStatus(validaciones) {
            const hasErrors = validaciones.some(v => v.estado === 'error');
            const hasWarnings = validaciones.some(v => v.estado === 'warning');
            return hasErrors ? 'danger' : hasWarnings ? 'warning' : 'success';
        }
        
        function getCategoriaCount(validaciones) {
            const ok = validaciones.filter(v => v.estado === 'ok').length;
            const errors = validaciones.filter(v => v.estado === 'error').length;
            const warnings = validaciones.filter(v => v.estado === 'warning').length;
            return `${ok}✅ ${errors}❌ ${warnings}⚠️`;
        }
        
        // Función para ver XML de documento
        function verXMLDocumento(numeroDoc, tipoDoc, docId) {
            const action = tipoDoc === 'factura' ? 'ver_xml_factura' : 
                          tipoDoc === 'nota_credito' ? 'ver_xml_nota_credito' : 'ver_xml_nota_debito';
            
            const formData = new FormData();
            formData.append('action', action);
            if (tipoDoc === 'factura') {
                formData.append('factura_id', docId);
            } else {
                formData.append('nota_id', docId);
            }
            
            fetch('ajax_validador_dian.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarXMLModal(numeroDoc, data.xml, data.cufe || data.cude, data.uuid);
                } else {
                    mostrarAlerta('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al obtener XML', 'danger');
            });
        }
        
        // Función para mostrar el XML en un modal
        function mostrarXMLModal(numeroDoc, xml, cufe, uuid) {
            const modalHtml = `
                <div class="modal fade" id="modalXML" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi bi-file-code"></i> XML - ${numeroDoc}
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <strong>CUFE/CUDE:</strong><br>
                                        <code>${cufe}</code>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>UUID:</strong><br>
                                        <code>${uuid}</code>
                                    </div>
                                    <div class="col-md-4">
                                        <button class="btn btn-sm btn-outline-secondary" onclick="copiarXML()">
                                            <i class="bi bi-clipboard"></i> Copiar XML
                                        </button>
                                        <button class="btn btn-sm btn-outline-primary" onclick="descargarXML()">
                                            <i class="bi bi-download"></i> Descargar
                                        </button>
                                    </div>
                                </div>
                                <div class="border rounded p-3" style="max-height: 500px; overflow-y: auto;">
                                    <pre id="xmlContent" style="font-size: 12px; margin: 0;"><code>${xml}</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remover modal anterior si existe
            const existingModal = document.getElementById('modalXML');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Agregar nuevo modal
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('modalXML'));
            modal.show();
        }
        
        // Función para copiar XML al portapapeles
        function copiarXML() {
            const xmlContent = document.getElementById('xmlContent').textContent;
            navigator.clipboard.writeText(xmlContent).then(() => {
                mostrarAlerta('XML copiado al portapapeles', 'success');
            }).catch(() => {
                mostrarAlerta('Error al copiar XML', 'danger');
            });
        }
        
        // Función para descargar XML
        function descargarXML() {
            const xmlContent = document.getElementById('xmlContent').textContent;
            const numeroDoc = document.querySelector('#modalXML .modal-title').textContent.split(' - ')[1];
            const blob = new Blob([xmlContent], { type: 'application/xml' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${numeroDoc}.xml`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
        
        // Función específica para ver XML de factura desde la tabla principal
        function verXMLFactura(facturaId) {
            const formData = new FormData();
            formData.append('action', 'ver_xml_factura');
            formData.append('factura_id', facturaId);
            
            fetch('ajax_validador_dian.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarXMLModal(data.numero_factura, data.xml, data.cufe, data.uuid);
                } else {
                    mostrarAlerta('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al obtener XML', 'danger');
            });
        }
    </script>
</body>
</html>

