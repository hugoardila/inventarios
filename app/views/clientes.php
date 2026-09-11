<?php
session_start();

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Incluir clase de permisos
require_once '../app/Lib/Permissions.php';

// Verificar permisos para el módulo de clientes
Permissions::requirePermission('read', 'clientes');

// Obtener ambiente seleccionado para FE
require_once 'funciones.php';
$ambiente_seleccionado = obtenerAmbienteFE($_SESSION['user_id']);

// Conexión a la base de datos
try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Cargar clientes con información completa
    $sql = "SELECT c.*, 
                   COUNT(v.id) as total_facturas,
                   SUM(v.total) as total_ventas,
                   MAX(v.fecha_venta) as ultima_compra
            FROM clientes c 
            LEFT JOIN ventas v ON c.id = v.cliente_id 
            WHERE c.activo = 1 
            GROUP BY c.id 
            ORDER BY c.nombre";
    $clientes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas
    $totalClientes = count($clientes);
    $clientesActivos = $pdo->query("SELECT COUNT(*) FROM clientes WHERE activo = 1")->fetchColumn();
    $totalVentas = $pdo->query("SELECT SUM(total) FROM ventas")->fetchColumn();
    $promedioVenta = $pdo->query("SELECT AVG(total) FROM ventas")->fetchColumn();
    
} catch (PDOException $e) {
    $error = "Error de conexión: " . $e->getMessage();
    $clientes = [];
    $totalClientes = 0;
    $clientesActivos = 0;
    $totalVentas = 0;
    $promedioVenta = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - TECNOXPERT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .clientes-container {
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
        .cliente-card {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .cliente-card:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        .cliente-premium {
            border-left-color: #ffc107;
            background: #fffbf0;
        }
        .cliente-regular {
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
                <h2>👥 Gestión de Clientes</h2>
                <p class="text-muted">Administrar clientes y relaciones comerciales</p>
            </div>
            <div>
                <a href="../index.php" class="btn btn-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
                <?php if (Permissions::canCreate('clientes')): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCliente">
                    <i class="bi bi-person-plus"></i> Nuevo Cliente
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Alertas para AJAX -->
        <div id="alertContainer"></div>
        
        <!-- KPIs de Clientes -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-people h3"></i>
                        <h4 class="mb-1"><?php echo number_format($totalClientes); ?></h4>
                        <small>Total Clientes</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle h3"></i>
                        <h4 class="mb-1"><?php echo number_format($clientesActivos); ?></h4>
                        <small>Clientes Activos</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-info text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-currency-dollar h3"></i>
                        <h4 class="mb-1">$<?php echo number_format($totalVentas, 0, ',', '.'); ?></h4>
                        <small>Total Ventas</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-graph-up h3"></i>
                        <h4 class="mb-1">$<?php echo number_format($promedioVenta, 0, ',', '.'); ?></h4>
                        <small>Promedio Venta</small>
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
                    <label class="form-label">Tipo de Persona</label>
                    <select class="form-select" id="filtroTipoPersona">
                        <option value="">Todos</option>
                        <option value="natural">Persona Natural</option>
                        <option value="juridica">Persona Jurídica</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Régimen</label>
                    <select class="form-select" id="filtroRegimen">
                        <option value="">Todos</option>
                        <option value="comun">Régimen Común</option>
                        <option value="simplificado">Régimen Simplificado</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo Cliente</label>
                    <select class="form-select" id="filtroTipoCliente">
                        <option value="">Todos</option>
                        <option value="premium">Premium</option>
                        <option value="regular">Regular</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ciudad</label>
                    <input type="text" class="form-control" id="filtroCiudad" placeholder="Buscar por ciudad">
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary" onclick="aplicarFiltros()">
                    <i class="bi bi-search"></i> Aplicar Filtros
                </button>
                <button class="btn btn-outline-secondary" onclick="limpiarFiltros()">
                    <i class="bi bi-arrow-clockwise"></i> Limpiar
                </button>
                <button class="btn btn-outline-success" onclick="exportarClientes()">
                    <i class="bi bi-download"></i> Exportar
                </button>
            </div>
        </div>
        
        <!-- Lista de Clientes -->
        <div class="clientes-container p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4>
                    <i class="bi bi-list-ul"></i> Clientes Registrados
                </h4>
                <div class="input-group" style="width: 300px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="buscarCliente" placeholder="Buscar clientes...">
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Cliente</th>
                            <th>Documento</th>
                            <th>Contacto</th>
                            <th>Facturas</th>
                            <th>Total Ventas</th>
                            <th>Última Compra</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaClientes">
                        <?php foreach ($clientes as $cliente): ?>
                        <tr class="cliente-card <?php echo ($cliente['total_ventas'] > 1000000) ? 'cliente-premium' : 'cliente-regular'; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($cliente['nombre']); ?></strong>
                                <br><small class="text-muted"><?php echo ucfirst($cliente['tipo_persona']); ?> - <?php echo ucfirst($cliente['regimen']); ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($cliente['tipo_documento']); ?>: <?php echo htmlspecialchars($cliente['numero_documento']); ?>
                                <?php if ($cliente['dv']): ?>
                                    <br><small class="text-muted">DV: <?php echo $cliente['dv']; ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($cliente['telefono']); ?>
                                <br><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($cliente['email']); ?>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo number_format($cliente['total_facturas']); ?></span>
                            </td>
                            <td>
                                <strong>$<?php echo number_format($cliente['total_ventas'] ?? 0, 0, ',', '.'); ?></strong>
                            </td>
                            <td>
                                <?php if ($cliente['ultima_compra']): ?>
                                    <small><?php echo date('d/m/Y', strtotime($cliente['ultima_compra'])); ?></small>
                                <?php else: ?>
                                    <small class="text-muted">Sin compras</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-success">Activo</span>
                                <?php if ($cliente['total_ventas'] > 1000000): ?>
                                    <br><span class="badge bg-warning">Premium</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <?php if (Permissions::canEdit('clientes')): ?>
                                    <button class="btn btn-outline-primary btn-action" onclick="editarCliente(<?php echo $cliente['id']; ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-outline-info btn-action" onclick="verHistorial(<?php echo $cliente['id']; ?>)">
                                        <i class="bi bi-clock-history"></i>
                                    </button>
                                    
                                    <?php if (Permissions::canCreate('ventas')): ?>
                                    <button class="btn btn-outline-success btn-action" onclick="nuevaFactura(<?php echo $cliente['id']; ?>)">
                                        <i class="bi bi-receipt"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (Permissions::canDelete('clientes')): ?>
                                    <button class="btn btn-outline-danger btn-action" onclick="eliminarCliente(<?php echo $cliente['id']; ?>)">
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

    <!-- Modal Nuevo/Editar Cliente -->
    <div class="modal fade" id="modalCliente" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nuevo Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formCliente">
                        <input type="hidden" id="cliente_id" name="cliente_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tipo de Persona</label>
                                    <select class="form-select" id="tipo_persona" name="tipo_persona" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="natural">Persona Natural</option>
                                        <option value="juridica">Persona Jurídica</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tipo de Documento</label>
                                    <select class="form-select" id="tipo_documento" name="tipo_documento" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="CC">Cédula de Ciudadanía</option>
                                        <option value="CE">Cédula de Extranjería</option>
                                        <option value="NIT">NIT</option>
                                        <option value="RUT">RUT</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Número de Documento</label>
                                    <input type="text" class="form-control" id="numero_documento" name="numero_documento" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Dígito de Verificación</label>
                                    <input type="text" class="form-control" id="dv" name="dv" maxlength="1">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nombre/Razón Social</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Régimen</label>
                                    <select class="form-select" id="regimen" name="regimen" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="comun">Régimen Común</option>
                                        <option value="simplificado">Régimen Simplificado</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Teléfono</label>
                                    <input type="tel" class="form-control" id="telefono" name="telefono" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Dirección</label>
                            <textarea class="form-control" id="direccion" name="direccion" rows="3" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarCliente()">Guardar Cliente</button>
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
        
        function guardarCliente() {
            const form = document.getElementById('formCliente');
            const formData = new FormData(form);
            formData.append('action', 'guardar');
            
            fetch('ajax_clientes.php', {
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
                mostrarAlerta('Error al guardar el cliente', 'danger');
            });
        }
        
        function editarCliente(id) {
            fetch('ajax_editar_cliente.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=obtener&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const cliente = data.data;
                    document.getElementById('modalTitle').textContent = 'Editar Cliente';
                    document.getElementById('cliente_id').value = cliente.id;
                    document.getElementById('tipo_persona').value = cliente.tipo_persona;
                    document.getElementById('tipo_documento').value = cliente.tipo_documento;
                    document.getElementById('numero_documento').value = cliente.numero_documento;
                    document.getElementById('dv').value = cliente.dv;
                    document.getElementById('nombre').value = cliente.nombre;
                    document.getElementById('regimen').value = cliente.regimen;
                    document.getElementById('telefono').value = cliente.telefono;
                    document.getElementById('email').value = cliente.email;
                    document.getElementById('direccion').value = cliente.direccion;
                    
                    new bootstrap.Modal(document.getElementById('modalCliente')).show();
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            });
        }
        
        function eliminarCliente(id) {
            if (confirm('¿Está seguro de eliminar este cliente?')) {
                fetch('ajax_clientes.php', {
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
        
        function verHistorial(id) {
            fetch('ajax_clientes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=obtener_historial&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const cliente = data.cliente;
                    const stats = data.estadisticas;
                    const ventas = data.ventas;
                    
                    let historial = `
                        <strong>${cliente.nombre}</strong><br>
                        <strong>Total Facturas:</strong> ${stats.total_facturas}<br>
                        <strong>Total Ventas:</strong> $${Number(stats.total_ventas || 0).toLocaleString()}<br>
                        <strong>Promedio Venta:</strong> $${Number(stats.promedio_venta || 0).toLocaleString()}<br>
                        <strong>Primera Compra:</strong> ${stats.primera_compra ? new Date(stats.primera_compra).toLocaleDateString() : 'Sin compras'}<br>
                        <strong>Última Compra:</strong> ${stats.ultima_compra ? new Date(stats.ultima_compra).toLocaleDateString() : 'Sin compras'}<br><br>
                        <strong>Últimas 5 Ventas:</strong><br>
                    `;
                    
                    ventas.slice(0, 5).forEach(venta => {
                        historial += `- ${new Date(venta.fecha_venta).toLocaleDateString()}: $${Number(venta.total).toLocaleString()}<br>`;
                    });
                    
                    mostrarAlerta(historial, 'info');
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            });
        }
        
        function nuevaFactura(id) {
            window.location.href = `facturacion.php?cliente_id=${id}`;
        }
        
        function aplicarFiltros() {
            const tipoPersona = document.getElementById('filtroTipoPersona').value;
            const regimen = document.getElementById('filtroRegimen').value;
            const tipoCliente = document.getElementById('filtroTipoCliente').value;
            const ciudad = document.getElementById('filtroCiudad').value;
            
            fetch('ajax_clientes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=exportar&tipo_persona=${tipoPersona}&regimen=${regimen}&tipo_cliente=${tipoCliente}&ciudad=${ciudad}`
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
            document.getElementById('filtroTipoPersona').value = '';
            document.getElementById('filtroRegimen').value = '';
            document.getElementById('filtroTipoCliente').value = '';
            document.getElementById('filtroCiudad').value = '';
            mostrarAlerta('Filtros limpiados', 'info');
        }
        
        function exportarClientes() {
            const tipoPersona = document.getElementById('filtroTipoPersona').value;
            const regimen = document.getElementById('filtroRegimen').value;
            const tipoCliente = document.getElementById('filtroTipoCliente').value;
            const ciudad = document.getElementById('filtroCiudad').value;
            
            mostrarAlerta('Exportando clientes...', 'info');
            
            fetch('ajax_clientes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=exportar&tipo_persona=${tipoPersona}&regimen=${regimen}&tipo_cliente=${tipoCliente}&ciudad=${ciudad}`
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
        document.getElementById('buscarCliente').addEventListener('input', function() {
            const busqueda = this.value.toLowerCase();
            const filas = document.querySelectorAll('#tablaClientes tr');
            
            filas.forEach(fila => {
                const texto = fila.textContent.toLowerCase();
                fila.style.display = texto.includes(busqueda) ? '' : 'none';
            });
        });
        
        // Limpiar modal al cerrar
        document.getElementById('modalCliente').addEventListener('hidden.bs.modal', function() {
            document.getElementById('formCliente').reset();
            document.getElementById('modalTitle').textContent = 'Nuevo Cliente';
            document.getElementById('cliente_id').value = '';
        });
    </script>
</body>
</html>
