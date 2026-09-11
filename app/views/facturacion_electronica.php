<?php
session_start();

// Verificar si está logueado y es admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../views/login.php");
    exit();
}

// Conexión a la base de datos
try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener ambiente seleccionado del usuario (persistente)
    $stmt = $pdo->prepare("SELECT ambiente FROM fe_ambiente_seleccionado WHERE usuario_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $ambiente_usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Si no tiene ambiente guardado, usar 'test' por defecto
    $ambiente_seleccionado = $ambiente_usuario['ambiente'] ?? 'test';
    
    // Si viene un cambio de ambiente por GET, actualizarlo
    if (isset($_GET['ambiente']) && in_array($_GET['ambiente'], ['test', 'prod'])) {
        $ambiente_seleccionado = $_GET['ambiente'];
        
        // Guardar el ambiente seleccionado para este usuario
        $stmt = $pdo->prepare("INSERT INTO fe_ambiente_seleccionado (usuario_id, ambiente) VALUES (?, ?) 
                              ON DUPLICATE KEY UPDATE ambiente = ?, actualizado_en = NOW()");
        $stmt->execute([$_SESSION['user_id'], $ambiente_seleccionado, $ambiente_seleccionado]);
    }
    
    // Cargar configuración de FE para el ambiente seleccionado
    $stmt = $pdo->prepare("SELECT * FROM fe_ambientes WHERE ambiente = ?");
    $stmt->execute([$ambiente_seleccionado]);
    $fe_config = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    // Cargar configuración de ambos ambientes para el selector
    $stmt = $pdo->query("SELECT ambiente, software_id, nit_empresa, nombre_empresa, creado_en FROM fe_ambientes ORDER BY ambiente");
    $ambientes_config = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Cargar logs de FE
    $stmt = $pdo->query("SELECT * FROM fe_log ORDER BY creado_en DESC LIMIT 10");
    $fe_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error de conexión: " . $e->getMessage();
    $fe_config = [];
    $fe_logs = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facturación Electrónica - TECNOXPERT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
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

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>⚡ Facturación Electrónica (DIAN)</h2>
                <div class="btn-group mt-2" role="group">
                    <a href="?ambiente=test" class="btn btn-<?php echo $ambiente_seleccionado === 'test' ? 'primary' : 'outline-primary'; ?> btn-sm">
                        <i class="bi bi-flask"></i> Pruebas
                    </a>
                    <a href="?ambiente=prod" class="btn btn-<?php echo $ambiente_seleccionado === 'prod' ? 'primary' : 'outline-primary'; ?> btn-sm">
                        <i class="bi bi-building"></i> Producción
                    </a>
                </div>
            </div>
            <a href="facturacion.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver a Facturación
            </a>
        </div>
        
        <!-- Información del ambiente seleccionado -->
        <div class="alert alert-<?php echo $ambiente_seleccionado === 'test' ? 'info' : 'warning'; ?> mb-4">
            <div class="d-flex align-items-center">
                <i class="bi bi-<?php echo $ambiente_seleccionado === 'test' ? 'flask' : 'building'; ?> me-2"></i>
                <div>
                    <strong>Ambiente <?php echo $ambiente_seleccionado === 'test' ? 'de Pruebas' : 'de Producción'; ?></strong>
                    <?php if ($ambiente_seleccionado === 'test'): ?>
                        <br><small>Configuración para pruebas con DIAN. Los datos ya están configurados.</small>
                    <?php else: ?>
                        <br><small>Configuración para producción. Complete todos los campos cuando DIAN proporcione la información.</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Alertas para AJAX -->
        <div id="alertContainer"></div>
        
        <div class="row">
            <!-- Configuración -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5>🔧 Configuración de Facturación Electrónica</h5>
                        <small class="text-muted">Configure los parámetros para conectar con la DIAN</small>
                    </div>
                    <div class="card-body">
                        <!-- Información importante -->
                        <div class="alert alert-success">
                            <h6><i class="bi bi-check-circle"></i> Configuración Simplificada</h6>
                            <p class="mb-2">Solo necesitas estos datos esenciales para facturación electrónica:</p>
                            <ul class="mb-0">
                                <li><strong>Software ID:</strong> Tu ID de software DIAN</li>
                                <li><strong>PIN:</strong> Tu PIN de autorización DIAN</li>
                                <li><strong>URL DIAN:</strong> URL del servicio (configurable)</li>
                                <li><strong>Clave Técnica:</strong> Clave de la resolución DIAN</li>
                                <li><strong>Datos de empresa:</strong> NIT, nombre, dirección</li>
                            </ul>
                        </div>
                        
                        <form id="formFE">
                            <input type="hidden" name="ambiente" value="<?php echo $ambiente_seleccionado; ?>">
                            
                            <!-- Sección 1: Datos DIAN -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6><i class="bi bi-building"></i> Datos DIAN</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Software ID <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="software_id" value="<?php echo htmlspecialchars($fe_config['software_id'] ?? ''); ?>" required>
                                                <small class="text-muted">ID del software autorizado por la DIAN</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">PIN <span class="text-danger">*</span></label>
                                                <input type="password" class="form-control" name="pin" value="<?php echo htmlspecialchars($fe_config['pin'] ?? ''); ?>" required>
                                                <small class="text-muted">PIN de autorización DIAN</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">TestSetId (DIAN) <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="testset_id" value="<?php echo htmlspecialchars($fe_config['testset_id'] ?? ''); ?>" required>
                                                <small class="text-muted">
                                                    <?php if ($ambiente_seleccionado === 'test'): ?>
                                                        ID del set de pruebas proporcionado por DIAN
                                                    <?php else: ?>
                                                        ID del set de producción proporcionado por DIAN
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">URL DIAN <span class="text-danger">*</span></label>
                                                <input type="url" class="form-control" name="url_dian" value="<?php echo htmlspecialchars($fe_config['url_dian'] ?? ''); ?>" required>
                                                <small class="text-muted">
                                                    <?php if ($ambiente_seleccionado === 'test'): ?>
                                                        URL del servicio de pruebas de DIAN
                                                    <?php else: ?>
                                                        URL del servicio de producción de DIAN
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Clave Técnica <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="clave_tecnica" value="<?php echo htmlspecialchars($fe_config['clave_tecnica'] ?? ''); ?>" required>
                                                <small class="text-muted">Clave técnica de la resolución DIAN</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Ambiente</label>
                                                <input type="text" class="form-control" value="<?php echo $ambiente_seleccionado === 'test' ? 'Pruebas' : 'Producción'; ?>" readonly>
                                                <small class="text-muted">Ambiente seleccionado: <?php echo $ambiente_seleccionado; ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Sección 2: Datos de Empresa -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6><i class="bi bi-building"></i> Datos de Empresa</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">NIT Empresa <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="nit_empresa" value="<?php echo htmlspecialchars($fe_config['nit_empresa'] ?? ''); ?>" required>
                                                <small class="text-muted">NIT de tu empresa</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Nombre Empresa <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="nombre_empresa" value="<?php echo htmlspecialchars($fe_config['nombre_empresa'] ?? ''); ?>" required>
                                                <small class="text-muted">Razón social de tu empresa</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Dirección <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="direccion_empresa" value="<?php echo htmlspecialchars($fe_config['direccion_empresa'] ?? ''); ?>" required>
                                                <small class="text-muted">Dirección de la empresa</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Ciudad <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="ciudad_empresa" value="<?php echo htmlspecialchars($fe_config['ciudad_empresa'] ?? ''); ?>" required>
                                                <small class="text-muted">Ciudad de la empresa</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Sección 3: Resolución DIAN -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6><i class="bi bi-file-text"></i> Resolución DIAN</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Prefijo <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="prefijo" value="<?php echo htmlspecialchars($fe_config['prefijo'] ?? 'SETP'); ?>" required>
                                                <small class="text-muted">Prefijo autorizado por DIAN</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Número Resolución <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="resolucion_dian" value="<?php echo htmlspecialchars($fe_config['resolucion_dian'] ?? ''); ?>" required>
                                                <small class="text-muted">Número de resolución DIAN</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Fecha Resolución <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" name="fecha_resolucion" value="<?php echo htmlspecialchars($fe_config['fecha_resolucion'] ?? ''); ?>" required>
                                                <small class="text-muted">Fecha de emisión</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Rango Desde <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="rango_desde" value="<?php echo htmlspecialchars($fe_config['rango_desde'] ?? '990000000'); ?>" required>
                                                <small class="text-muted">Número inicial</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Rango Hasta <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="rango_hasta" value="<?php echo htmlspecialchars($fe_config['rango_hasta'] ?? '995000000'); ?>" required>
                                                <small class="text-muted">Número final</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Fecha Desde <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" name="fecha_desde" value="<?php echo htmlspecialchars($fe_config['fecha_desde'] ?? '2019-01-19'); ?>" required>
                                                <small class="text-muted">Vigencia desde</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Fecha Hasta <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" name="fecha_hasta" value="<?php echo htmlspecialchars($fe_config['fecha_hasta'] ?? '2030-01-19'); ?>" required>
                                                <small class="text-muted">Vigencia hasta</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Botones de acción -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="button" class="btn btn-primary" onclick="guardarConfiguracionFE()">
                                    <i class="bi bi-save"></i> Guardar Configuración
                                </button>
                                <button type="button" class="btn btn-success" onclick="probarConexion()">
                                    <i class="bi bi-wifi"></i> Probar Conexión
                                </button>
                                <button type="button" class="btn btn-info" onclick="validarFlujoVentas()">
                                    <i class="bi bi-check-circle"></i> Validar Flujo
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Estado y Logs -->
            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6>📊 Estado del Sistema</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Estado:</span>
                            <span class="badge bg-success">Activo</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Ambiente:</span>
                            <span class="badge bg-<?php echo ($fe_config['ambiente'] ?? '') === 'prod' ? 'danger' : 'warning'; ?>">
                                <?php echo ucfirst($fe_config['ambiente'] ?? 'No configurado'); ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Facturas Enviadas:</span>
                            <span class="badge bg-info"><?php echo count($fe_logs); ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Último Envío:</span>
                            <small class="text-muted">
                                <?php echo !empty($fe_logs) ? date('d/m/Y H:i', strtotime($fe_logs[0]['creado_en'])) : 'Nunca'; ?>
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h6>📋 Logs de Envío</h6>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <?php if (empty($fe_logs)): ?>
                            <p class="text-muted text-center">No hay logs de envío</p>
                        <?php else: ?>
                            <?php foreach ($fe_logs as $log): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($log['creado_en'])); ?></small>
                                        <span class="badge bg-<?php echo $log['estado'] === 'aceptado' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($log['estado']); ?>
                                        </span>
                                    </div>
                                    <small><?php echo htmlspecialchars($log['mensaje']); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Probar Conexión -->
    <div class="modal fade" id="modalProbarConexion" tabindex="-1" aria-labelledby="modalProbarConexionLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalProbarConexionLabel">
                        <i class="bi bi-wifi"></i> Resultado de Conexión con DIAN
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="contenidoConexion">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Probando conexión...</span>
                            </div>
                            <p class="mt-2">Probando conexión con DIAN...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" onclick="probarConexion()">
                        <i class="bi bi-arrow-clockwise"></i> Probar Nuevamente
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Validar Flujo -->
    <div class="modal fade" id="modalValidarFlujo" tabindex="-1" aria-labelledby="modalValidarFlujoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalValidarFlujoLabel">
                        <i class="bi bi-check-circle"></i> Validación del Flujo de Ventas
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="contenidoValidacion">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Validando flujo...</span>
                            </div>
                            <p class="mt-2">Validando flujo de ventas...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" onclick="validarFlujoVentas()">
                        <i class="bi bi-arrow-clockwise"></i> Validar Nuevamente
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
        
        function guardarConfiguracionFE() {
            const form = document.getElementById('formFE');
            const formData = new FormData(form);
            formData.append('action', 'guardar_configuracion');
            
            fetch('ajax_facturacion_electronica.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta(data.message, 'success');
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al guardar la configuración', 'danger');
            });
        }
        
        function probarConexion() {
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('modalProbarConexion'));
            modal.show();
            
            // Mostrar loading
            document.getElementById('contenidoConexion').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Probando conexión...</span>
                    </div>
                    <p class="mt-2">Probando conexión con DIAN para ambiente <?php echo $ambiente_seleccionado === 'test' ? 'de Pruebas' : 'de Producción'; ?>...</p>
                </div>
            `;
            
            fetch('ajax_facturacion_electronica.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'probar_conexion',
                    ambiente: '<?php echo $ambiente_seleccionado; ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let contenido = `
                        <div class="alert alert-success">
                            <h6><i class="bi bi-check-circle"></i> ${data.message}</h6>
                            <p class="mb-0">Tiempo de respuesta: <strong>${data.datos.tiempo_respuesta}</strong></p>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="bi bi-info-circle"></i> Información de Conexión</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Ambiente:</strong></td>
                                        <td><span class="badge bg-${data.datos.ambiente === 'test' ? 'warning' : 'danger'}">${data.datos.ambiente}</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Software ID:</strong></td>
                                        <td><code>${data.datos.software_id}</code></td>
                                    </tr>
                                    <tr>
                                        <td><strong>TestSetId:</strong></td>
                                        <td><code>${data.datos.testset_id}</code></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Empresa:</strong></td>
                                        <td>${data.datos.nombre_empresa}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>NIT:</strong></td>
                                        <td>${data.datos.nit_empresa}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td><span class="badge bg-success">${data.datos.servidor_dian}</span></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="bi bi-globe"></i> Conexión DIAN</h6>
                                <div class="card">
                                    <div class="card-body">
                                        <p class="card-text"><code>${data.datos.url_dian}</code></p>
                                        <small class="text-muted">Endpoint oficial de DIAN para ${data.datos.ambiente === 'test' ? 'pruebas' : 'producción'}</small>
                                    </div>
                                </div>
                                <h6 class="mt-3"><i class="bi bi-check2-square"></i> Validaciones</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="text-center">
                                            <i class="bi bi-check-circle-fill text-success fs-4"></i>
                                            <p class="small mb-0">Campos Obligatorios</p>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center">
                                            <i class="bi bi-check-circle-fill text-success fs-4"></i>
                                            <p class="small mb-0">Formatos Válidos</p>
                                        </div>
                                    </div>
                                </div>
                                <h6 class="mt-3"><i class="bi bi-clock"></i> Timestamp</h6>
                                <p><small class="text-muted">${data.datos.timestamp}</small></p>
                            </div>
                        </div>
                    `;
                    document.getElementById('contenidoConexion').innerHTML = contenido;
                } else {
                    let contenidoError = `
                        <div class="alert alert-danger">
                            <h6><i class="bi bi-exclamation-triangle"></i> Error de Conexión</h6>
                            <p>${data.message}</p>
                    `;
                    
                    if (data.datos) {
                        contenidoError += `
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <h6><i class="bi bi-info-circle"></i> Detalles del Error</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>URL DIAN:</strong></td>
                                            <td><code>${data.datos.url_dian}</code></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Tiempo:</strong></td>
                                            <td>${data.datos.tiempo_respuesta}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Estado HTTP:</strong></td>
                                            <td><span class="badge bg-danger">${data.datos.estado_http}</span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Timestamp:</strong></td>
                                            <td><small>${data.datos.timestamp}</small></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="bi bi-lightbulb"></i> Posibles Soluciones</h6>
                                    <ul class="small">
                                        <li>Verificar conexión a internet</li>
                                        <li>Comprobar que los servidores DIAN estén disponibles</li>
                                        <li>Revisar configuración de firewall</li>
                                        <li>Intentar nuevamente en unos minutos</li>
                                    </ul>
                                </div>
                            </div>
                        `;
                    }
                    
                    contenidoError += `</div>`;
                    document.getElementById('contenidoConexion').innerHTML = contenidoError;
                }
            })
            .catch(error => {
                document.getElementById('contenidoConexion').innerHTML = `
                    <div class="alert alert-danger">
                        <h6><i class="bi bi-exclamation-triangle"></i> Error</h6>
                        <p>Error al probar la conexión con DIAN</p>
                    </div>
                `;
            });
        }
        
        function validarFlujoVentas() {
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('modalValidarFlujo'));
            modal.show();
            
            // Mostrar loading
            document.getElementById('contenidoValidacion').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Validando flujo...</span>
                    </div>
                    <p class="mt-2">Validando flujo de ventas para ambiente <?php echo $ambiente_seleccionado === 'test' ? 'de Pruebas' : 'de Producción'; ?>...</p>
                </div>
            `;
            
            fetch('ajax_facturacion_electronica.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'validar_flujo',
                    ambiente: '<?php echo $ambiente_seleccionado; ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let contenido = `
                        <div class="alert alert-success">
                            <h6><i class="bi bi-check-circle"></i> Validación Exitosa</h6>
                            <p>Sistema listo para facturación electrónica</p>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="bi bi-box"></i> Productos</h6>
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-primary">${data.datos.productos_con_iva}</h3>
                                        <p class="card-text">Productos con IVA</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="bi bi-people"></i> Clientes</h6>
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-info">${data.datos.clientes_con_documentos}</h3>
                                        <p class="card-text">Clientes con documentos</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <h6><i class="bi bi-receipt"></i> Facturas</h6>
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-warning">${data.datos.facturas_pendientes}</h3>
                                        <p class="card-text">Facturas pendientes</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="bi bi-globe"></i> DIAN</h6>
                                <div class="card">
                                    <div class="card-body">
                                        <p class="card-text"><code>${data.datos.url_dian}</code></p>
                                        <small class="text-muted">URL de conexión</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6><i class="bi bi-check2-square"></i> Estado del Sistema</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <i class="bi bi-check-circle-fill text-success fs-2"></i>
                                            <p class="mt-2">Configuración FE</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <i class="bi bi-check-circle-fill text-success fs-2"></i>
                                            <p class="mt-2">Estructura BD</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <i class="bi bi-check-circle-fill text-success fs-2"></i>
                                            <p class="mt-2">TestSetId</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <i class="bi bi-check-circle-fill text-success fs-2"></i>
                                            <p class="mt-2">Conexión DIAN</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    document.getElementById('contenidoValidacion').innerHTML = contenido;
                } else {
                    document.getElementById('contenidoValidacion').innerHTML = `
                        <div class="alert alert-warning">
                            <h6><i class="bi bi-exclamation-triangle"></i> Validación con Advertencias</h6>
                            <p>${data.message}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                document.getElementById('contenidoValidacion').innerHTML = `
                    <div class="alert alert-danger">
                        <h6><i class="bi bi-exclamation-triangle"></i> Error</h6>
                        <p>Error al validar el flujo de ventas</p>
                    </div>
                `;
            });
        }
    </script>
</body>
</html>
