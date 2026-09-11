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
    
    // Obtener notas de débito
    $sql = "SELECT nd.*, v.numero_factura, c.nombre as cliente_nombre, u.nombre as usuario_nombre,
                   fl.cufe, fl.creado_en as fecha_envio
            FROM notas_debito nd
            LEFT JOIN ventas v ON nd.factura_id = v.id
            LEFT JOIN clientes c ON nd.cliente_id = c.id
            LEFT JOIN usuarios u ON nd.usuario_id = u.id
            LEFT JOIN fe_log fl ON fl.venta_id = nd.factura_id AND fl.tipo_documento = 'nota_debito' AND fl.numero_documento = nd.numero_nota
            ORDER BY nd.creado_en DESC";
    $stmt = $pdo->query($sql);
    $notas_debito = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error de conexión: " . $e->getMessage();
    $notas_debito = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notas de Débito - Sistema de Facturación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .nota-card {
            transition: all 0.3s ease;
            border-left: 4px solid #dc3545;
        }
        .nota-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .estado-badge {
            font-size: 0.8em;
        }
        .motivo-text {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-file-earmark-minus text-danger"></i> Notas de Débito</h2>
                    <div>
                        <span class="badge bg-danger fs-6">Total: <?php echo count($notas_debito); ?></span>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($notas_debito)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-file-earmark-minus text-muted" style="font-size: 4rem;"></i>
                        <h4 class="text-muted mt-3">No hay notas de débito</h4>
                        <p class="text-muted">Las notas de débito aparecerán aquí cuando se creen.</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($notas_debito as $nota): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card nota-card h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">
                                            <i class="bi bi-file-earmark-minus text-danger"></i>
                                            <?php echo htmlspecialchars($nota['numero_nota']); ?>
                                        </h6>
                                        <span class="badge bg-<?php 
                                            echo match($nota['estado']) {
                                                'pendiente' => 'warning',
                                                'enviada' => 'success',
                                                'aceptada' => 'primary',
                                                'rechazada' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?> estado-badge">
                                            <?php echo ucfirst($nota['estado']); ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-2">
                                            <div class="col-6">
                                                <small class="text-muted">Factura:</small><br>
                                                <strong><?php echo htmlspecialchars($nota['numero_factura']); ?></strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Cliente:</small><br>
                                                <strong><?php echo htmlspecialchars($nota['cliente_nombre']); ?></strong>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <small class="text-muted">Motivo:</small><br>
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
                                        
                                        <div class="row mb-2">
                                            <div class="col-4">
                                                <small class="text-muted">Subtotal:</small><br>
                                                <strong>$<?php echo number_format($nota['subtotal'], 2); ?></strong>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">IVA:</small><br>
                                                <strong>$<?php echo number_format($nota['iva'], 2); ?></strong>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Total:</small><br>
                                                <strong class="text-danger">$<?php echo number_format($nota['total'], 2); ?></strong>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Creado por:</small><br>
                                                <small><?php echo htmlspecialchars($nota['usuario_nombre']); ?></small>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Fecha:</small><br>
                                                <small><?php echo date('d/m/Y H:i', strtotime($nota['creado_en'])); ?></small>
                                            </div>
                                        </div>
                                        
                                        <?php if ($nota['estado'] === 'aceptada' && !empty($nota['cufe'])): ?>
                                        <div class="mt-2 p-2 bg-success bg-opacity-10 rounded">
                                            <small class="text-success">
                                                <strong>CUFE:</strong><br>
                                                <code><?php echo htmlspecialchars($nota['cufe']); ?></code>
                                            </small>
                                            <?php if (!empty($nota['fecha_envio'])): ?>
                                            <br><small class="text-muted">
                                                Enviado: <?php echo date('d/m/Y H:i', strtotime($nota['fecha_envio'])); ?>
                                            </small>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-footer">
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-outline-primary btn-sm" onclick="verDetalle(<?php echo $nota['id']; ?>)">
                                                <i class="bi bi-eye"></i> Ver
                                            </button>
                                            <?php if ($nota['estado'] === 'pendiente'): ?>
                                                <button class="btn btn-outline-success btn-sm" onclick="enviarDIAN(<?php echo $nota['id']; ?>)">
                                                    <i class="bi bi-send"></i> DIAN
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function verDetalle(notaId) {
            fetch('ajax_notas_debito.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'obtener_detalle_nota',
                    nota_id: notaId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarModalDetalle(data.nota, data.items);
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al cargar los detalles', 'danger');
            });
        }
        
        function mostrarModalDetalle(nota, items) {
            const modalHtml = `
                <div class="modal fade" id="modalDetalleNota" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi bi-file-earmark-minus text-danger"></i> 
                                    Detalle Nota de Débito: ${nota.numero_nota}
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Factura:</strong> ${nota.numero_factura}<br>
                                        <strong>Cliente:</strong> ${nota.cliente_nombre}<br>
                                        <strong>Motivo:</strong> ${nota.motivo_codigo} - ${nota.motivo}
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Tipo:</strong> ${nota.tipo_monto}<br>
                                        <strong>Estado:</strong> 
                                        <span class="badge bg-${nota.estado === 'aceptada' ? 'success' : nota.estado === 'pendiente' ? 'warning' : 'danger'}">
                                            ${nota.estado}
                                        </span><br>
                                        <strong>Creado:</strong> ${new Date(nota.creado_en).toLocaleString()}
                                    </div>
                                </div>
                                
                                ${nota.cufe ? `
                                <div class="alert alert-success">
                                    <strong>CUFE:</strong><br>
                                    <code>${nota.cufe}</code>
                                </div>
                                ` : ''}
                                
                                <h6>Productos:</h6>
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
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${items.map(item => `
                                                <tr>
                                                    <td>
                                                        <strong>${item.producto_nombre}</strong><br>
                                                        <small class="text-muted">Ref: ${item.producto_referencia}</small>
                                                    </td>
                                                    <td>${item.cantidad}</td>
                                                    <td>$${parseFloat(item.precio_unitario).toFixed(2)}</td>
                                                    <td>$${parseFloat(item.subtotal).toFixed(2)}</td>
                                                    <td>$${parseFloat(item.iva).toFixed(2)}</td>
                                                    <td>$${parseFloat(item.total).toFixed(2)}</td>
                                                    <td>
                                                        <small>${item.motivo_codigo}</small><br>
                                                        <small class="text-muted">${item.motivo}</small>
                                                    </td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-danger">
                                                <th colspan="3">TOTAL</th>
                                                <th>$${parseFloat(nota.subtotal).toFixed(2)}</th>
                                                <th>$${parseFloat(nota.iva).toFixed(2)}</th>
                                                <th>$${parseFloat(nota.total).toFixed(2)}</th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remover modal anterior si existe
            const modalAnterior = document.getElementById('modalDetalleNota');
            if (modalAnterior) {
                modalAnterior.remove();
            }
            
            // Agregar nuevo modal
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('modalDetalleNota'));
            modal.show();
        }
        
        function enviarDIAN(notaId) {
            if (confirm('¿Desea enviar esta nota de débito a la DIAN?')) {
                fetch('ajax_notas_debito.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'enviar_nota_debito_dian',
                        nota_id: notaId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let mensaje = '✅ ' + data.message;
                        if (data.cufe) {
                            mensaje += '<br><strong>CUFE:</strong><br><code>' + data.cufe + '</code>';
                        }
                        mostrarAlerta(mensaje, 'success');
                        setTimeout(() => location.reload(), 3000);
                    } else {
                        mostrarAlerta(data.message, 'danger');
                    }
                })
                .catch(error => {
                    mostrarAlerta('Error al enviar la nota de débito a DIAN', 'danger');
                });
            }
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
    </script>
</body>
</html>


