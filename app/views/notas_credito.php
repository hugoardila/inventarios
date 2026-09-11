<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'funciones.php';

// Obtener ambiente seleccionado para FE
$ambiente_seleccionado = obtenerAmbienteFE($_SESSION['user_id']);

// Obtener notas de crédito
try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "SELECT nc.*, v.numero_factura, c.nombre as cliente_nombre, u.nombre as usuario_nombre
            FROM notas_credito nc
            LEFT JOIN ventas v ON nc.factura_id = v.id
            LEFT JOIN clientes c ON nc.cliente_id = c.id
            LEFT JOIN usuarios u ON nc.usuario_id = u.id
            ORDER BY nc.fecha_creacion DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $notas_credito = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $notas_credito = [];
    $error = "Error al obtener notas de crédito: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notas de Crédito - TecnoXpert</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .status-badge {
            font-size: 0.8rem;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            transition: transform 0.2s;
        }
        .motivo-text {
            max-height: 60px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="bi bi-receipt"></i> Sistema de Facturación
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="facturas.php">
                    <i class="bi bi-arrow-left"></i> Volver a Facturas
                </a>
                <a class="nav-link" href="../index.php">
                    <i class="bi bi-house"></i> Inicio
                </a>
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="bi bi-arrow-counterclockwise"></i> Notas de Crédito</h2>
                        <p class="text-muted mb-0">Gestionar notas de crédito para facturas enviadas a DIAN</p>
                    </div>
                    <div>
                        <button class="btn btn-primary" onclick="location.reload()">
                            <i class="bi bi-arrow-clockwise"></i> Actualizar
                        </button>
                    </div>
                </div>

                <!-- Alertas -->
                <div id="alert-container"></div>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo count($notas_credito); ?></h4>
                                        <p class="mb-0">Total Notas</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-receipt-cutoff fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo count(array_filter($notas_credito, fn($n) => $n['estado_fe'] === 'pendiente')); ?></h4>
                                        <p class="mb-0">Pendientes</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-clock fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo count(array_filter($notas_credito, fn($n) => $n['estado_fe'] === 'enviada')); ?></h4>
                                        <p class="mb-0">Enviadas</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-check-circle fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4>$<?php echo number_format(array_sum(array_column($notas_credito, 'total')), 2); ?></h4>
                                        <p class="mb-0">Valor Total</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-currency-dollar fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de Notas de Crédito -->
                <div class="row">
                    <?php if (empty($notas_credito)): ?>
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-receipt-cutoff fs-1 text-muted mb-3"></i>
                                <h4 class="text-muted">No hay notas de crédito</h4>
                                <p class="text-muted">Las notas de crédito aparecerán aquí cuando se creen.</p>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php foreach ($notas_credito as $nota): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card card-hover h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="bi bi-receipt-cutoff"></i> <?php echo htmlspecialchars($nota['numero_nota']); ?>
                                </h6>
                                <div class="d-flex align-items-center gap-2">
                                    <!-- Indicador de estado FE -->
                                    <?php if ($nota['estado_fe'] === 'enviada'): ?>
                                    <span class="badge bg-success status-badge" title="Enviada a DIAN">
                                        <i class="bi bi-check-circle"></i> DIAN
                                    </span>
                                    <?php elseif ($nota['estado_fe'] === 'rechazada'): ?>
                                    <span class="badge bg-danger status-badge" title="Rechazada por DIAN">
                                        <i class="bi bi-x-circle"></i> DIAN
                                    </span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary status-badge" title="Pendiente de envío">
                                        <i class="bi bi-clock"></i> DIAN
                                    </span>
                                    <?php endif; ?>
                                    
                                    <!-- Indicador de estado general -->
                                    <span class="badge bg-<?php echo $nota['estado'] === 'anulada' ? 'danger' : 'warning'; ?> status-badge">
                                        <?php echo ucfirst($nota['estado']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <small class="text-muted">Factura Original:</small>
                                    <div class="fw-bold"><?php echo htmlspecialchars($nota['numero_factura']); ?></div>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted">Cliente:</small>
                                    <div><?php echo htmlspecialchars($nota['cliente_nombre'] ?? 'CLIENTE GENERAL'); ?></div>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted">Motivo:</small>
                                    <div class="motivo-text" title="<?php echo htmlspecialchars($nota['motivo']); ?>">
                                        <?php if (!empty($nota['motivo_codigo'])): ?>
                                            <span class="badge bg-info me-2"><?php echo htmlspecialchars($nota['motivo_codigo']); ?></span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($nota['motivo']); ?>
                                        <?php if (!empty($nota['tipo_monto'])): ?>
                                            <span class="badge bg-<?php echo $nota['tipo_monto'] === 'total' ? 'success' : 'warning'; ?> ms-2">
                                                <?php echo ucfirst($nota['tipo_monto']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <small class="text-muted">Subtotal:</small>
                                        <div class="fw-bold">$<?php echo number_format($nota['subtotal'], 2); ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">IVA:</small>
                                        <div class="fw-bold">$<?php echo number_format($nota['iva'], 2); ?></div>
                                    </div>
                                </div>
                                
                                <div class="border-top pt-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">Total:</small>
                                        <h5 class="mb-0 text-danger">$<?php echo number_format($nota['total'], 2); ?></h5>
                                    </div>
                                </div>
                                
                                <?php if ($nota['cufe']): ?>
                                <div class="mt-2">
                                    <small class="text-muted">CUFE:</small>
                                    <div class="small text-break"><?php echo htmlspecialchars($nota['cufe']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y H:i', strtotime($nota['fecha_creacion'])); ?>
                                    </small>
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($nota['estado_fe'] === 'pendiente'): ?>
                                        <button class="btn btn-outline-success btn-sm" onclick="enviarNotaCredito(<?php echo $nota['id']; ?>)">
                                            <i class="bi bi-send"></i> DIAN
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn btn-outline-info btn-sm" onclick="verDetalleNota(<?php echo $nota['id']; ?>)">
                                            <i class="bi bi-eye"></i> Ver
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function mostrarAlerta(mensaje, tipo) {
            const alertContainer = document.getElementById('alert-container');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${tipo} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${mensaje}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            alertContainer.appendChild(alertDiv);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
        
        function enviarNotaCredito(notaId) {
            if (confirm('¿Está seguro de enviar esta nota de crédito a DIAN?')) {
                fetch('ajax_notas_credito.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'enviar_nota_credito',
                        nota_id: notaId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarAlerta(data.icon + ' ' + data.message, 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        mostrarAlerta(data.message, 'danger');
                    }
                })
                .catch(error => {
                    mostrarAlerta('Error al enviar nota de crédito', 'danger');
                });
            }
        }
        
        function verDetalleNota(notaId) {
            // Aquí podrías implementar un modal con más detalles
            mostrarAlerta('Función de detalle en desarrollo', 'info');
        }
    </script>
</body>
</html>

