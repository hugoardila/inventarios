<?php
session_start();

// Verificar si está logueado y es admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración Avanzada - TECNOXPERT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">TECNOXPERT - Inventarios</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">Bienvenido, <?php echo $_SESSION['user_name']; ?></span>
                <a class="nav-link" href="logout.php">Cerrar sesión</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>⚙️ Configuración Avanzada</h2>
            <a href="configuracion.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
        
        <!-- Alertas para AJAX -->
        <div id="alertContainer"></div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>🔧 Configuración del Sistema</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" onclick="limpiarCache()">
                                <i class="bi bi-trash"></i> Limpiar Cache
                            </button>
                            <button class="btn btn-outline-warning" onclick="optimizarBD()">
                                <i class="bi bi-speedometer2"></i> Optimizar Base de Datos
                            </button>
                            <button class="btn btn-outline-info" onclick="verLogs()">
                                <i class="bi bi-file-text"></i> Ver Logs del Sistema
                            </button>
                            <button class="btn btn-outline-secondary" onclick="configurarBackup()">
                                <i class="bi bi-clock"></i> Configurar Backup Automático
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>🔐 Seguridad</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-danger" onclick="cambiarPassword()">
                                <i class="bi bi-key"></i> Cambiar Contraseña Admin
                            </button>
                            <button class="btn btn-outline-warning" onclick="configurarSesiones()">
                                <i class="bi bi-clock-history"></i> Configurar Sesiones
                            </button>
                            <button class="btn btn-outline-info" onclick="verAuditoria()">
                                <i class="bi bi-shield-check"></i> Ver Auditoría
                            </button>
                            <button class="btn btn-outline-dark" onclick="configurarPermisos()">
                                <i class="bi bi-lock"></i> Configurar Permisos
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>📊 Información del Sistema</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <i class="bi bi-server h1 text-primary"></i>
                                    <h6>Servidor</h6>
                                    <small class="text-muted">Apache + MySQL</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <i class="bi bi-code-slash h1 text-success"></i>
                                    <h6>PHP</h6>
                                    <small class="text-muted"><?php echo phpversion(); ?></small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <i class="bi bi-database h1 text-warning"></i>
                                    <h6>Base de Datos</h6>
                                    <small class="text-muted">MySQL/MariaDB</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <i class="bi bi-calendar h1 text-info"></i>
                                    <h6>Último Backup</h6>
                                    <small class="text-muted"><?php echo date('d/m/Y H:i'); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ver Logs -->
    <div class="modal fade" id="modalLogs" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-file-text"></i> Logs del Sistema
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Tabs para diferentes tipos de logs -->
                    <ul class="nav nav-tabs" id="logsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">
                                <i class="bi bi-activity"></i> Actividad
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                                <i class="bi bi-shield-check"></i> Seguridad
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="errors-tab" data-bs-toggle="tab" data-bs-target="#errors" type="button" role="tab">
                                <i class="bi bi-exclamation-triangle"></i> Errores PHP
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="auditoria-tab" data-bs-toggle="tab" data-bs-target="#auditoria" type="button" role="tab">
                                <i class="bi bi-clipboard-check"></i> Auditoría BD
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="fe-tab" data-bs-toggle="tab" data-bs-target="#fe" type="button" role="tab">
                                <i class="bi bi-lightning"></i> Facturación Electrónica
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content mt-3" id="logsTabsContent">
                        <!-- Tab Actividad -->
                        <div class="tab-pane fade show active" id="activity" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6>Logs de Actividad del Sistema</h6>
                                <button class="btn btn-sm btn-outline-primary" onclick="cargarLogs('activity')">
                                    <i class="bi bi-arrow-clockwise"></i> Actualizar
                                </button>
                            </div>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-hover">
                                    <thead class="table-dark sticky-top">
                                        <tr>
                                            <th>Fecha/Hora</th>
                                            <th>Nivel</th>
                                            <th>Usuario</th>
                                            <th>IP</th>
                                            <th>Mensaje</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyActivity">
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">
                                                <div class="spinner-border spinner-border-sm" role="status"></div>
                                                Cargando logs...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Tab Seguridad -->
                        <div class="tab-pane fade" id="security" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6>Logs de Seguridad</h6>
                                <button class="btn btn-sm btn-outline-primary" onclick="cargarLogs('security')">
                                    <i class="bi bi-arrow-clockwise"></i> Actualizar
                                </button>
                            </div>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-hover">
                                    <thead class="table-dark sticky-top">
                                        <tr>
                                            <th>Fecha/Hora</th>
                                            <th>IP</th>
                                            <th>Input</th>
                                            <th>Razón</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodySecurity">
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">Cargando logs...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Tab Errores PHP -->
                        <div class="tab-pane fade" id="errors" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6>Errores de PHP</h6>
                                <button class="btn btn-sm btn-outline-primary" onclick="cargarLogs('errors')">
                                    <i class="bi bi-arrow-clockwise"></i> Actualizar
                                </button>
                            </div>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-hover">
                                    <thead class="table-dark sticky-top">
                                        <tr>
                                            <th>Fecha/Hora</th>
                                            <th>Tipo</th>
                                            <th>Mensaje</th>
                                            <th>Archivo</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyErrors">
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">Cargando logs...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Tab Auditoría BD -->
                        <div class="tab-pane fade" id="auditoria" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6>Auditoría de Base de Datos</h6>
                                <button class="btn btn-sm btn-outline-primary" onclick="cargarLogs('auditoria')">
                                    <i class="bi bi-arrow-clockwise"></i> Actualizar
                                </button>
                            </div>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-hover">
                                    <thead class="table-dark sticky-top">
                                        <tr>
                                            <th>Fecha/Hora</th>
                                            <th>Usuario</th>
                                            <th>Acción</th>
                                            <th>Tabla</th>
                                            <th>Registro ID</th>
                                            <th>IP</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyAuditoria">
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">Cargando logs...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Tab Facturación Electrónica -->
                        <div class="tab-pane fade" id="fe" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6>Logs de Facturación Electrónica</h6>
                                <button class="btn btn-sm btn-outline-primary" onclick="cargarLogs('fe')">
                                    <i class="bi bi-arrow-clockwise"></i> Actualizar
                                </button>
                            </div>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-hover">
                                    <thead class="table-dark sticky-top">
                                        <tr>
                                            <th>Fecha/Hora</th>
                                            <th>Tipo</th>
                                            <th>Número</th>
                                            <th>Estado</th>
                                            <th>Mensaje</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyFE">
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">Cargando logs...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-danger" onclick="limpiarLogs()">
                        <i class="bi bi-trash"></i> Limpiar Logs Antiguos
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function mostrarAlerta(mensaje, tipo = 'success') {
            const alertContainer = document.getElementById('alertContainer');
            if (!alertContainer) {
                // Si no existe el contenedor, crear uno
                const container = document.querySelector('.container');
                const div = document.createElement('div');
                div.id = 'alertContainer';
                container.insertBefore(div, container.firstChild);
            }
            
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${tipo} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${mensaje}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.getElementById('alertContainer').appendChild(alertDiv);
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 8000);
        }
        
        function limpiarCache() {
            if (!confirm('¿Estás seguro de limpiar el cache del sistema?\n\nEsto eliminará archivos temporales y puede mejorar el rendimiento.')) {
                return;
            }
            
            // Obtener el botón que fue clickeado
            const btn = document.querySelector('button[onclick="limpiarCache()"]');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Limpiando...';
            
            const formData = new FormData();
            formData.append('action', 'limpiar_cache');
            
            fetch('ajax_limpiar_cache.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                
                if (data.success) {
                    let mensaje = `<strong>✅ ${data.message}</strong>`;
                    if (data.detalles) {
                        mensaje += '<br><br><strong>Detalles:</strong><ul>';
                        for (const [dir, info] of Object.entries(data.detalles)) {
                            if (info.archivos > 0) {
                                mensaje += `<li>${dir}: ${info.archivos} archivo(s) - ${formatBytes(info.espacio)}</li>`;
                            }
                        }
                        mensaje += '</ul>';
                    }
                    if (data.opcache_limpiado) {
                        mensaje += '<br><small class="text-muted">OPcache también fue limpiado.</small>';
                    }
                    mostrarAlerta(mensaje, 'success');
                } else {
                    mostrarAlerta(data.message || 'Error al limpiar el cache', 'danger');
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                mostrarAlerta('Error al limpiar el cache: ' + error.message, 'danger');
            });
        }
        
        function formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        function optimizarBD() {
            if (!confirm('¿Estás seguro de optimizar la base de datos?\n\nEsto ejecutará OPTIMIZE TABLE en todas las tablas para mejorar el rendimiento.\nEl proceso puede tardar varios minutos dependiendo del tamaño de la base de datos.')) {
                return;
            }
            
            // Obtener el botón que fue clickeado
            const btn = document.querySelector('button[onclick="optimizarBD()"]');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Optimizando...';
            
            const formData = new FormData();
            formData.append('action', 'optimizar_bd');
            
            fetch('ajax_optimizar_bd.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                
                if (data.success) {
                    let mensaje = `<strong>✅ ${data.message}</strong>`;
                    if (data.detalles && Object.keys(data.detalles).length > 0) {
                        mensaje += '<br><br><strong>Detalles por tabla:</strong><ul>';
                        for (const [tabla, info] of Object.entries(data.detalles)) {
                            const espacio = info.espacio_liberado > 0 ? formatBytes(info.espacio_liberado) : '0 B';
                            mensaje += `<li><strong>${tabla}</strong>: ${info.filas} fila(s) - Espacio liberado: ${espacio}</li>`;
                        }
                        mensaje += '</ul>';
                    }
                    if (data.tablas_con_error > 0 && data.errores) {
                        mensaje += '<br><br><strong>Errores:</strong><ul>';
                        data.errores.forEach(error => {
                            mensaje += `<li class="text-danger">${error}</li>`;
                        });
                        mensaje += '</ul>';
                    }
                    mostrarAlerta(mensaje, data.tablas_con_error > 0 ? 'warning' : 'success');
                } else {
                    mostrarAlerta(data.message || 'Error al optimizar la base de datos', 'danger');
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                mostrarAlerta('Error al optimizar la base de datos: ' + error.message, 'danger');
            });
        }
        
        function verLogs() {
            const modal = new bootstrap.Modal(document.getElementById('modalLogs'));
            modal.show();
            
            // Cargar logs de actividad por defecto
            cargarLogs('activity');
        }
        
        function cargarLogs(tipo) {
            const tbodyMap = {
                'activity': 'tbodyActivity',
                'security': 'tbodySecurity',
                'errors': 'tbodyErrors',
                'auditoria': 'tbodyAuditoria',
                'fe': 'tbodyFE'
            };
            
            const tbody = document.getElementById(tbodyMap[tipo]);
            if (!tbody) return;
            
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted"><div class="spinner-border spinner-border-sm"></div> Cargando logs...</td></tr>';
            
            const formData = new FormData();
            formData.append('action', 'obtener_logs');
            formData.append('tipo', tipo);
            
            fetch('ajax_ver_logs.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarLogs(tipo, data.logs);
                } else {
                    tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger">${data.message || 'Error al cargar logs'}</td></tr>`;
                }
            })
            .catch(error => {
                tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger">Error: ${error.message}</td></tr>`;
            });
        }
        
        function mostrarLogs(tipo, logs) {
            const tbodyMap = {
                'activity': 'tbodyActivity',
                'security': 'tbodySecurity',
                'errors': 'tbodyErrors',
                'auditoria': 'tbodyAuditoria',
                'fe': 'tbodyFE'
            };
            
            const tbody = document.getElementById(tbodyMap[tipo]);
            if (!tbody || !logs || logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted">No hay logs disponibles</td></tr>';
                return;
            }
            
            let html = '';
            
            if (tipo === 'activity') {
                logs.forEach(log => {
                    const nivelClass = {
                        'ERROR': 'danger',
                        'WARNING': 'warning',
                        'INFO': 'info',
                        'DEBUG': 'secondary'
                    }[log.nivel] || 'secondary';
                    
                    html += `
                        <tr>
                            <td>${log.fecha || 'N/A'}</td>
                            <td><span class="badge bg-${nivelClass}">${log.nivel || 'N/A'}</span></td>
                            <td>${log.usuario || 'N/A'}</td>
                            <td><small>${log.ip || 'N/A'}</small></td>
                            <td>${log.mensaje || 'N/A'}</td>
                        </tr>
                    `;
                });
            } else if (tipo === 'security') {
                logs.forEach(log => {
                    html += `
                        <tr>
                            <td>${log.timestamp || 'N/A'}</td>
                            <td><small>${log.ip || 'N/A'}</small></td>
                            <td><code>${log.input || 'N/A'}</code></td>
                            <td>${log.reason || 'N/A'}</td>
                        </tr>
                    `;
                });
            } else if (tipo === 'errors') {
                logs.forEach(log => {
                    html += `
                        <tr>
                            <td>${log.fecha || 'N/A'}</td>
                            <td><span class="badge bg-danger">${log.tipo || 'Error'}</span></td>
                            <td><small>${log.mensaje || 'N/A'}</small></td>
                            <td><code>${log.archivo || 'N/A'}</code></td>
                        </tr>
                    `;
                });
            } else if (tipo === 'auditoria') {
                logs.forEach(log => {
                    html += `
                        <tr>
                            <td>${log.creado_en || 'N/A'}</td>
                            <td>${log.usuario || 'N/A'}</td>
                            <td><span class="badge bg-primary">${log.accion || 'N/A'}</span></td>
                            <td>${log.tabla || 'N/A'}</td>
                            <td>${log.registro_id || 'N/A'}</td>
                            <td><small>${log.ip || 'N/A'}</small></td>
                        </tr>
                    `;
                });
            } else if (tipo === 'fe') {
                logs.forEach(log => {
                    const estadoClass = {
                        'aceptado': 'success',
                        'rechazado': 'danger',
                        'error': 'danger',
                        'enviado': 'warning',
                        'pendiente': 'secondary'
                    }[log.estado] || 'secondary';
                    
                    html += `
                        <tr>
                            <td>${log.creado_en || 'N/A'}</td>
                            <td>${log.tipo_documento || 'N/A'}</td>
                            <td>${log.numero_documento || 'N/A'}</td>
                            <td><span class="badge bg-${estadoClass}">${log.estado || 'N/A'}</span></td>
                            <td><small>${log.error_mensaje || log.mensaje || 'N/A'}</small></td>
                        </tr>
                    `;
                });
            }
            
            tbody.innerHTML = html;
        }
        
        function limpiarLogs() {
            if (!confirm('¿Estás seguro de limpiar los logs antiguos?\n\nEsto eliminará logs con más de 30 días de antigüedad.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'limpiar_logs');
            
            fetch('ajax_ver_logs.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta(data.message, 'success');
                    // Recargar los logs actuales
                    const activeTab = document.querySelector('#logsTabs .nav-link.active');
                    if (activeTab) {
                        const tipo = activeTab.getAttribute('data-bs-target').replace('#', '');
                        cargarLogs(tipo);
                    }
                } else {
                    mostrarAlerta(data.message || 'Error al limpiar logs', 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al limpiar logs: ' + error.message, 'danger');
            });
        }
        
        // Cargar logs cuando se cambia de tab
        document.querySelectorAll('#logsTabs button').forEach(button => {
            button.addEventListener('shown.bs.tab', function(event) {
                const tipo = event.target.getAttribute('data-bs-target').replace('#', '');
                cargarLogs(tipo);
            });
        });
        
        function configurarBackup() {
            // Cargar configuración actual
            const formData = new FormData();
            formData.append('action', 'obtener_configuracion');
            
            fetch('ajax_backup.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarModalBackup(data);
                } else {
                    mostrarModalBackup({habilitado: false, frecuencia: 5, ultima_ejecucion: null, ultimo_backup: null});
                }
            })
            .catch(() => {
                mostrarModalBackup({habilitado: false, frecuencia: 5, ultima_ejecucion: null, ultimo_backup: null});
            });
        }
        
        function mostrarModalBackup(config) {
            const modalHTML = `
                <div class="modal fade" id="modalBackup" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi bi-clock"></i> Configurar Backup Automático
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> 
                                    <strong>Backup Automático:</strong> El sistema ejecutará un backup cada ${config.frecuencia || 5} días.
                                    Solo se mantendrá el último archivo de backup.
                                </div>
                                
                                <form id="formBackup">
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="backupHabilitado" ${config.habilitado ? 'checked' : ''}>
                                            <label class="form-check-label" for="backupHabilitado">
                                                Habilitar Backup Automático
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Frecuencia (días)</label>
                                        <input type="number" class="form-control" id="backupFrecuencia" value="${config.frecuencia || 5}" min="1" max="30" required>
                                        <small class="text-muted">Cada cuántos días se ejecutará el backup automático</small>
                                    </div>
                                    
                                    ${config.ultimo_backup ? `
                                    <div class="alert alert-success">
                                        <h6><i class="bi bi-check-circle"></i> Último Backup</h6>
                                        <p class="mb-1"><strong>Archivo:</strong> ${config.ultimo_backup.archivo}</p>
                                        <p class="mb-1"><strong>Tamaño:</strong> ${formatBytes(config.ultimo_backup.tamaño)}</p>
                                        <p class="mb-0"><strong>Fecha:</strong> ${new Date(config.ultimo_backup.fecha).toLocaleString('es-ES')}</p>
                                    </div>
                                    ` : '<div class="alert alert-warning">No hay backups disponibles</div>'}
                                    
                                    ${config.ultima_ejecucion ? `
                                    <div class="mb-3">
                                        <small class="text-muted">Última ejecución: ${new Date(config.ultima_ejecucion).toLocaleString('es-ES')}</small>
                                    </div>
                                    ` : ''}
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-success" onclick="ejecutarBackupManual()">
                                    <i class="bi bi-play-circle"></i> Ejecutar Backup Ahora
                                </button>
                                <button type="button" class="btn btn-primary" onclick="guardarConfigBackup()">
                                    <i class="bi bi-save"></i> Guardar Configuración
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Eliminar modal anterior si existe
            const modalAnterior = document.getElementById('modalBackup');
            if (modalAnterior) {
                modalAnterior.remove();
            }
            
            // Agregar nuevo modal
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            const modal = new bootstrap.Modal(document.getElementById('modalBackup'));
            modal.show();
        }
        
        function guardarConfigBackup() {
            const habilitado = document.getElementById('backupHabilitado').checked;
            const frecuencia = document.getElementById('backupFrecuencia').value;
            
            const formData = new FormData();
            formData.append('action', 'configurar_backup');
            formData.append('habilitado', habilitado ? '1' : '0');
            formData.append('frecuencia', frecuencia);
            
            fetch('ajax_backup.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta(data.message, 'success');
                    
                    // Mostrar instrucciones para cron
                    if (habilitado) {
                        const rutaScript = '/opt/lampp/htdocs/local/backup_database.php';
                        const instrucciones = `
                            <div class="alert alert-info mt-3">
                                <h6><i class="bi bi-info-circle"></i> Configurar Cron Job</h6>
                                <p>Para que el backup se ejecute automáticamente cada ${frecuencia} días, ejecuta:</p>
                                <code>sudo crontab -e</code>
                                <p class="mt-2">Y agrega esta línea (ejecuta cada ${frecuencia} días a las 2:00 AM):</p>
                                <code class="d-block p-2 bg-light">0 2 */${frecuencia} * * /opt/lampp/bin/php ${rutaScript} >> /opt/lampp/htdocs/local/backups/backup.log 2>&1</code>
                                <p class="mt-2 mb-0"><small>O si prefieres usar PHP del sistema: <code>/usr/bin/php</code> en lugar de <code>/opt/lampp/bin/php</code></small></p>
                            </div>
                        `;
                        mostrarAlerta(data.message + instrucciones, 'success');
                    }
                    
                    setTimeout(() => {
                        document.getElementById('modalBackup').querySelector('.btn-close').click();
                    }, 2000);
                } else {
                    mostrarAlerta(data.message || 'Error al guardar la configuración', 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al guardar la configuración: ' + error.message, 'danger');
            });
        }
        
        function ejecutarBackupManual() {
            if (!confirm('¿Ejecutar backup ahora?\n\nEsto puede tardar varios minutos dependiendo del tamaño de la base de datos.')) {
                return;
            }
            
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Ejecutando...';
            
            const formData = new FormData();
            formData.append('action', 'ejecutar_backup');
            
            fetch('ajax_backup.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                
                if (data.success) {
                    mostrarAlerta(`✅ ${data.message}<br>Archivo: ${data.archivo}<br>Tamaño: ${data.tamaño_formateado}`, 'success');
                    // Recargar configuración para mostrar el nuevo backup
                    setTimeout(() => {
                        configurarBackup();
                    }, 2000);
                } else {
                    mostrarAlerta(data.message || 'Error al ejecutar backup', 'danger');
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                mostrarAlerta('Error al ejecutar backup: ' + error.message, 'danger');
            });
        }
        
        function cambiarPassword() {
            // Cargar usuarios administradores
            const formData = new FormData();
            formData.append('action', 'obtener_usuarios_admin');
            
            fetch('ajax_cambiar_password_admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarModalCambiarPassword(data.usuarios);
                } else {
                    mostrarAlerta(data.message || 'Error al cargar usuarios administradores', 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al cargar usuarios: ' + error.message, 'danger');
            });
        }
        
        function mostrarModalCambiarPassword(usuarios) {
            if (!usuarios || usuarios.length === 0) {
                mostrarAlerta('No hay usuarios administradores disponibles', 'warning');
                return;
            }
            
            let opcionesUsuarios = '';
            usuarios.forEach(usuario => {
                const ultimoLogin = usuario.ultimo_login ? new Date(usuario.ultimo_login).toLocaleString('es-ES') : 'Nunca';
                opcionesUsuarios += `<option value="${usuario.id}">${usuario.nombre} ${usuario.apellido} (${usuario.username}) - Último login: ${ultimoLogin}</option>`;
            });
            
            const modalHTML = `
                <div class="modal fade" id="modalCambiarPassword" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi bi-key"></i> Cambiar Contraseña de Administrador
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i> 
                                    <strong>Advertencia:</strong> Esta acción cambiará la contraseña del usuario administrador seleccionado.
                                </div>
                                
                                <form id="formCambiarPassword">
                                    <div class="mb-3">
                                        <label class="form-label">Usuario Administrador</label>
                                        <select class="form-select" id="usuarioAdmin" required>
                                            <option value="">Seleccione un usuario...</option>
                                            ${opcionesUsuarios}
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Contraseña Actual</label>
                                        <input type="password" class="form-control" id="passwordActual" required>
                                        <small class="text-muted">Ingrese la contraseña actual del usuario seleccionado</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Nueva Contraseña</label>
                                        <input type="password" class="form-control" id="passwordNuevo" required minlength="8">
                                        <small class="text-muted">Mínimo 8 caracteres</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Confirmar Nueva Contraseña</label>
                                        <input type="password" class="form-control" id="passwordConfirmar" required minlength="8">
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="mostrarPassword">
                                        <label class="form-check-label" for="mostrarPassword">
                                            Mostrar contraseñas
                                        </label>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-primary" onclick="guardarPasswordAdmin()">
                                    <i class="bi bi-save"></i> Cambiar Contraseña
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Eliminar modal anterior si existe
            const modalAnterior = document.getElementById('modalCambiarPassword');
            if (modalAnterior) {
                modalAnterior.remove();
            }
            
            // Agregar nuevo modal
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            const modal = new bootstrap.Modal(document.getElementById('modalCambiarPassword'));
            modal.show();
            
            // Toggle mostrar/ocultar contraseñas
            document.getElementById('mostrarPassword').addEventListener('change', function() {
                const mostrar = this.checked;
                document.getElementById('passwordActual').type = mostrar ? 'text' : 'password';
                document.getElementById('passwordNuevo').type = mostrar ? 'text' : 'password';
                document.getElementById('passwordConfirmar').type = mostrar ? 'text' : 'password';
            });
        }
        
        function guardarPasswordAdmin() {
            const usuarioId = document.getElementById('usuarioAdmin').value;
            const passwordActual = document.getElementById('passwordActual').value;
            const passwordNuevo = document.getElementById('passwordNuevo').value;
            const passwordConfirmar = document.getElementById('passwordConfirmar').value;
            
            // Validaciones básicas
            if (!usuarioId) {
                mostrarAlerta('Debe seleccionar un usuario administrador', 'warning');
                return;
            }
            
            if (!passwordActual) {
                mostrarAlerta('Debe ingresar la contraseña actual', 'warning');
                return;
            }
            
            if (!passwordNuevo || passwordNuevo.length < 8) {
                mostrarAlerta('La nueva contraseña debe tener al menos 8 caracteres', 'warning');
                return;
            }
            
            if (passwordNuevo !== passwordConfirmar) {
                mostrarAlerta('Las contraseñas nuevas no coinciden', 'warning');
                return;
            }
            
            if (!confirm('¿Está seguro de cambiar la contraseña del administrador seleccionado?')) {
                return;
            }
            
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Cambiando...';
            
            const formData = new FormData();
            formData.append('action', 'cambiar_password_admin');
            formData.append('usuario_id', usuarioId);
            formData.append('password_actual', passwordActual);
            formData.append('password_nuevo', passwordNuevo);
            formData.append('password_confirmar', passwordConfirmar);
            
            fetch('ajax_cambiar_password_admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                
                if (data.success) {
                    mostrarAlerta(data.message, 'success');
                    // Limpiar formulario
                    document.getElementById('formCambiarPassword').reset();
                    // Cerrar modal después de 2 segundos
                    setTimeout(() => {
                        document.getElementById('modalCambiarPassword').querySelector('.btn-close').click();
                    }, 2000);
                } else {
                    mostrarAlerta(data.message || 'Error al cambiar la contraseña', 'danger');
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                mostrarAlerta('Error al cambiar la contraseña: ' + error.message, 'danger');
            });
        }
        
        function configurarSesiones() {
            // Cargar configuración actual
            const formData = new FormData();
            formData.append('action', 'obtener_configuracion');
            
            fetch('ajax_configurar_sesiones.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarModalSesiones(data.configuracion);
                } else {
                    mostrarAlerta(data.message || 'Error al cargar configuración', 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al cargar configuración: ' + error.message, 'danger');
            });
        }
        
        function mostrarModalSesiones(config) {
            const modalHTML = `
                <div class="modal fade" id="modalSesiones" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi bi-clock-history"></i> Configuración de Sesiones
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> 
                                    <strong>Nota:</strong> Los cambios se aplicarán en el próximo inicio de sesión.
                                </div>
                                
                                <form id="formSesiones">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Tiempo de Vida de Sesión (segundos)</label>
                                            <input type="number" class="form-control" id="sessionLifetime" 
                                                   value="${config.session_lifetime || 3600}" 
                                                   min="300" max="86400" required>
                                            <small class="text-muted">
                                                Mínimo: 300 seg (5 min) - Máximo: 86400 seg (24 horas)<br>
                                                <strong>Actual:</strong> ${formatearTiempo(config.session_lifetime || 3600)}
                                            </small>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Máximo Intentos de Login</label>
                                            <input type="number" class="form-control" id="maxLoginAttempts" 
                                                   value="${config.max_login_attempts || 5}" 
                                                   min="3" max="10" required>
                                            <small class="text-muted">
                                                Número de intentos fallidos antes de bloquear (3-10)
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Tiempo de Bloqueo (segundos)</label>
                                            <input type="number" class="form-control" id="lockoutTime" 
                                                   value="${config.lockout_time || 900}" 
                                                   min="60" max="3600" required>
                                            <small class="text-muted">
                                                Tiempo de bloqueo después de intentos fallidos<br>
                                                <strong>Actual:</strong> ${formatearTiempo(config.lockout_time || 900)}
                                            </small>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Configuración de Cookies</label>
                                            <div class="mt-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="cookieHttpOnly" 
                                                           ${config.session_cookie_httponly ? 'checked' : ''}>
                                                    <label class="form-check-label" for="cookieHttpOnly">
                                                        Cookie HttpOnly
                                                    </label>
                                                    <small class="d-block text-muted">Previene acceso a cookies desde JavaScript (recomendado)</small>
                                                </div>
                                                <div class="form-check mt-2">
                                                    <input class="form-check-input" type="checkbox" id="cookieSecure" 
                                                           ${config.session_cookie_secure ? 'checked' : ''}>
                                                    <label class="form-check-label" for="cookieSecure">
                                                        Cookie Secure
                                                    </label>
                                                    <small class="d-block text-muted">Solo envía cookies por HTTPS (requiere SSL)</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-warning mt-3">
                                        <h6><i class="bi bi-exclamation-triangle"></i> Recomendaciones de Seguridad</h6>
                                        <ul class="mb-0">
                                            <li><strong>Tiempo de sesión:</strong> Para mayor seguridad, use valores menores (ej: 1800 seg = 30 min)</li>
                                            <li><strong>Intentos de login:</strong> 5 intentos es un buen balance entre seguridad y usabilidad</li>
                                            <li><strong>Cookie HttpOnly:</strong> Siempre debe estar activado para prevenir XSS</li>
                                            <li><strong>Cookie Secure:</strong> Active solo si tiene certificado SSL/HTTPS</li>
                                        </ul>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-primary" onclick="guardarConfigSesiones()">
                                    <i class="bi bi-save"></i> Guardar Configuración
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Eliminar modal anterior si existe
            const modalAnterior = document.getElementById('modalSesiones');
            if (modalAnterior) {
                modalAnterior.remove();
            }
            
            // Agregar nuevo modal
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            const modal = new bootstrap.Modal(document.getElementById('modalSesiones'));
            modal.show();
        }
        
        function formatearTiempo(segundos) {
            if (segundos < 60) {
                return segundos + ' segundos';
            } else if (segundos < 3600) {
                const minutos = Math.floor(segundos / 60);
                return minutos + ' minuto' + (minutos > 1 ? 's' : '');
            } else {
                const horas = Math.floor(segundos / 3600);
                const minutos = Math.floor((segundos % 3600) / 60);
                let resultado = horas + ' hora' + (horas > 1 ? 's' : '');
                if (minutos > 0) {
                    resultado += ' y ' + minutos + ' minuto' + (minutos > 1 ? 's' : '');
                }
                return resultado;
            }
        }
        
        function guardarConfigSesiones() {
            const sessionLifetime = parseInt(document.getElementById('sessionLifetime').value);
            const maxLoginAttempts = parseInt(document.getElementById('maxLoginAttempts').value);
            const lockoutTime = parseInt(document.getElementById('lockoutTime').value);
            const cookieHttpOnly = document.getElementById('cookieHttpOnly').checked;
            const cookieSecure = document.getElementById('cookieSecure').checked;
            
            // Validaciones
            if (sessionLifetime < 300 || sessionLifetime > 86400) {
                mostrarAlerta('El tiempo de vida de sesión debe estar entre 300 y 86400 segundos', 'warning');
                return;
            }
            
            if (maxLoginAttempts < 3 || maxLoginAttempts > 10) {
                mostrarAlerta('El máximo de intentos debe estar entre 3 y 10', 'warning');
                return;
            }
            
            if (lockoutTime < 60 || lockoutTime > 3600) {
                mostrarAlerta('El tiempo de bloqueo debe estar entre 60 y 3600 segundos', 'warning');
                return;
            }
            
            if (!confirm('¿Está seguro de guardar esta configuración de sesiones?')) {
                return;
            }
            
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
            
            const formData = new FormData();
            formData.append('action', 'guardar_configuracion');
            formData.append('session_lifetime', sessionLifetime);
            formData.append('max_login_attempts', maxLoginAttempts);
            formData.append('lockout_time', lockoutTime);
            formData.append('session_cookie_httponly', cookieHttpOnly ? '1' : '0');
            formData.append('session_cookie_secure', cookieSecure ? '1' : '0');
            
            fetch('ajax_configurar_sesiones.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                
                if (data.success) {
                    mostrarAlerta(data.message, 'success');
                    setTimeout(() => {
                        document.getElementById('modalSesiones').querySelector('.btn-close').click();
                    }, 2000);
                } else {
                    mostrarAlerta(data.message || 'Error al guardar la configuración', 'danger');
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                mostrarAlerta('Error al guardar la configuración: ' + error.message, 'danger');
            });
        }
        
        function verAuditoria() {
            // Cargar auditoría
            cargarAuditoria();
        }
        
        function cargarAuditoria(filtros = {}) {
            const formData = new FormData();
            formData.append('action', 'obtener_auditoria');
            formData.append('limite', filtros.limite || 100);
            formData.append('offset', filtros.offset || 0);
            
            if (filtros.usuario) formData.append('filtro_usuario', filtros.usuario);
            if (filtros.tabla) formData.append('filtro_tabla', filtros.tabla);
            if (filtros.accion) formData.append('filtro_accion', filtros.accion);
            if (filtros.fecha_desde) formData.append('filtro_fecha_desde', filtros.fecha_desde);
            if (filtros.fecha_hasta) formData.append('filtro_fecha_hasta', filtros.fecha_hasta);
            
            fetch('ajax_auditoria.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarModalAuditoria(data);
                } else {
                    mostrarAlerta(data.message || 'Error al cargar auditoría', 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al cargar auditoría: ' + error.message, 'danger');
            });
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function mostrarModalAuditoria(data) {
            const modalHTML = `
                <div class="modal fade" id="modalAuditoria" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi bi-shield-check"></i> Auditoría del Sistema
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <!-- Filtros -->
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bi bi-funnel"></i> Filtros</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label class="form-label">Usuario</label>
                                                <select class="form-select form-select-sm" id="filtroUsuario">
                                                    <option value="">Todos los usuarios</option>
                                                    ${data.usuarios.map(u => `<option value="${u.id}">${u.nombre} ${u.apellido} (${u.username})</option>`).join('')}
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Tabla</label>
                                                <select class="form-select form-select-sm" id="filtroTabla">
                                                    <option value="">Todas las tablas</option>
                                                    ${data.tablas.map(t => `<option value="${t}">${t}</option>`).join('')}
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Acción</label>
                                                <select class="form-select form-select-sm" id="filtroAccion">
                                                    <option value="">Todas las acciones</option>
                                                    ${data.acciones.map(a => `<option value="${a}">${a}</option>`).join('')}
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Fecha Desde</label>
                                                <input type="date" class="form-control form-control-sm" id="filtroFechaDesde">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Fecha Hasta</label>
                                                <input type="date" class="form-control form-control-sm" id="filtroFechaHasta">
                                            </div>
                                            <div class="col-md-1">
                                                <label class="form-label">&nbsp;</label>
                                                <button class="btn btn-primary btn-sm w-100" onclick="aplicarFiltrosAuditoria()">
                                                    <i class="bi bi-search"></i> Filtrar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Resumen -->
                                <div class="alert alert-info">
                                    <strong>Total de registros:</strong> ${data.total} | 
                                    <strong>Mostrando:</strong> ${data.registros.length}
                                </div>
                                
                                <!-- Tabla de auditoría -->
                                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                    <table class="table table-sm table-hover">
                                        <thead class="table-dark sticky-top">
                                            <tr>
                                                <th>Fecha/Hora</th>
                                                <th>Usuario</th>
                                                <th>Acción</th>
                                                <th>Tabla</th>
                                                <th>Registro ID</th>
                                                <th>IP</th>
                                                <th>Detalles</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyAuditoriaCompleta">
                                            ${data.registros.length > 0 ? data.registros.map(r => `
                                                <tr>
                                                    <td><small>${new Date(r.creado_en).toLocaleString('es-ES')}</small></td>
                                                    <td>${r.nombre ? `${r.nombre} ${r.apellido}` : 'Sistema'}<br><small class="text-muted">${r.username || 'N/A'}</small></td>
                                                    <td><span class="badge bg-${getBadgeColorAccion(r.accion)}">${r.accion}</span></td>
                                                    <td><code>${r.tabla}</code></td>
                                                    <td>${r.registro_id || 'N/A'}</td>
                                                    <td><small>${r.ip || 'N/A'}</small></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-info btn-detalle-auditoria" 
                                                                data-id="${r.id}"
                                                                data-accion="${r.accion}"
                                                                data-tabla="${r.tabla}"
                                                                data-datos-ant="${r.datos_anteriores ? escapeHtml(JSON.stringify(r.datos_anteriores)) : ''}"
                                                                data-datos-nue="${r.datos_nuevos ? escapeHtml(JSON.stringify(r.datos_nuevos)) : ''}"
                                                                data-user-agent="${r.user_agent ? escapeHtml(r.user_agent) : ''}">
                                                            <i class="bi bi-eye"></i> Ver
                                                        </button>
                                                    </td>
                                                </tr>
                                            `).join('') : '<tr><td colspan="7" class="text-center text-muted">No hay registros de auditoría</td></tr>'}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                <button type="button" class="btn btn-primary" onclick="aplicarFiltrosAuditoria()">
                                    <i class="bi bi-arrow-clockwise"></i> Actualizar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Eliminar modal anterior si existe
            const modalAnterior = document.getElementById('modalAuditoria');
            if (modalAnterior) {
                modalAnterior.remove();
            }
            
            // Agregar nuevo modal
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            const modal = new bootstrap.Modal(document.getElementById('modalAuditoria'));
            modal.show();
            
            // Agregar event listeners a los botones de detalles
            document.querySelectorAll('.btn-detalle-auditoria').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const accion = this.getAttribute('data-accion');
                    const tabla = this.getAttribute('data-tabla');
                    const datosAnt = this.getAttribute('data-datos-ant');
                    const datosNue = this.getAttribute('data-datos-nue');
                    const userAgent = this.getAttribute('data-user-agent');
                    verDetallesAuditoria(id, accion, tabla, datosAnt, datosNue, userAgent);
                });
            });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function getBadgeColorAccion(accion) {
            const colores = {
                'INSERT': 'success',
                'UPDATE': 'warning',
                'DELETE': 'danger',
                'SELECT': 'info'
            };
            return colores[accion] || 'secondary';
        }
        
        function aplicarFiltrosAuditoria() {
            const filtros = {
                usuario: document.getElementById('filtroUsuario').value,
                tabla: document.getElementById('filtroTabla').value,
                accion: document.getElementById('filtroAccion').value,
                fecha_desde: document.getElementById('filtroFechaDesde').value,
                fecha_hasta: document.getElementById('filtroFechaHasta').value,
                limite: 100,
                offset: 0
            };
            
            cargarAuditoria(filtros);
        }
        
        function verDetallesAuditoria(id, accion, tabla, datosAnteriores, datosNuevos, userAgent) {
            let datosAnt = null;
            let datosNue = null;
            
            try {
                if (datosAnteriores && datosAnteriores.trim() !== '') {
                    datosAnt = JSON.parse(datosAnteriores);
                }
            } catch(e) {
                datosAnt = null;
            }
            
            try {
                if (datosNuevos && datosNuevos.trim() !== '') {
                    datosNue = JSON.parse(datosNuevos);
                }
            } catch(e) {
                datosNue = null;
            }
            
            const userAgentStr = userAgent || '';
            
            let contenido = `
                <div class="mb-3">
                    <strong>Acción:</strong> <span class="badge bg-${getBadgeColorAccion(accion)}">${accion}</span><br>
                    <strong>Tabla:</strong> <code>${tabla}</code><br>
                    <strong>ID Registro:</strong> ${id}
                </div>
            `;
            
            if (datosAnt) {
                contenido += `
                    <div class="mb-3">
                        <h6>Datos Anteriores:</h6>
                        <pre class="bg-light p-2 rounded"><code>${JSON.stringify(datosAnt, null, 2)}</code></pre>
                    </div>
                `;
            }
            
            if (datosNue) {
                contenido += `
                    <div class="mb-3">
                        <h6>Datos Nuevos:</h6>
                        <pre class="bg-light p-2 rounded"><code>${JSON.stringify(datosNue, null, 2)}</code></pre>
                    </div>
                `;
            }
            
            if (userAgentStr) {
                contenido += `
                    <div class="mb-3">
                        <h6>User Agent:</h6>
                        <small class="text-muted">${userAgentStr}</small>
                    </div>
                `;
            }
            
            const modalDetalles = `
                <div class="modal fade" id="modalDetallesAuditoria" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Detalles de Auditoría</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                ${contenido}
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Eliminar modal anterior si existe
            const modalAnterior = document.getElementById('modalDetallesAuditoria');
            if (modalAnterior) {
                modalAnterior.remove();
            }
            
            // Agregar nuevo modal
            document.body.insertAdjacentHTML('beforeend', modalDetalles);
            const modal = new bootstrap.Modal(document.getElementById('modalDetallesAuditoria'));
            modal.show();
        }
        
        function configurarPermisos() {
            // Cargar roles y permisos
            const formData = new FormData();
            formData.append('action', 'obtener_roles');
            
            fetch('ajax_permisos.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarModalPermisos(data);
                } else {
                    mostrarAlerta(data.message || 'Error al cargar permisos', 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al cargar permisos: ' + error.message, 'danger');
            });
        }
        
        function mostrarModalPermisos(data) {
            // Crear tabs para cada rol
            let tabsHTML = '';
            let contentHTML = '';
            
            data.roles.forEach((rol, index) => {
                const isActive = index === 0 ? 'active' : '';
                const show = index === 0 ? 'show active' : '';
                
                tabsHTML += `
                    <li class="nav-item" role="presentation">
                        <button class="nav-link ${isActive}" id="rol-${rol.id}-tab" data-bs-toggle="tab" 
                                data-bs-target="#rol-${rol.id}" type="button" role="tab">
                            ${rol.nombre}
                        </button>
                    </li>
                `;
                
                // Generar tabla de permisos para este rol
                let tablaPermisos = `
                    <div class="mb-3">
                        <h6>${rol.descripcion || 'Sin descripción'}</h6>
                        <small class="text-muted">Estado: ${rol.activo ? '<span class="text-success">Activo</span>' : '<span class="text-danger">Inactivo</span>'}</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Módulo</th>
                                    ${data.acciones.map(accion => `<th class="text-center">${accion.toUpperCase()}</th>`).join('')}
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                Object.keys(data.modulos).forEach(modulo => {
                    const nombreModulo = data.modulos[modulo];
                    tablaPermisos += `<tr><td><strong>${nombreModulo}</strong></td>`;
                    
                    data.acciones.forEach(accion => {
                        const tienePermiso = rol.permisos[modulo] && rol.permisos[modulo].includes(accion);
                        tablaPermisos += `
                            <td class="text-center">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input permiso-checkbox" 
                                           type="checkbox" 
                                           data-rol="${rol.id}"
                                           data-modulo="${modulo}"
                                           data-accion="${accion}"
                                           ${tienePermiso ? 'checked' : ''}
                                           id="permiso-${rol.id}-${modulo}-${accion}">
                                </div>
                            </td>
                        `;
                    });
                    
                    tablaPermisos += `</tr>`;
                });
                
                tablaPermisos += `
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-primary" onclick="guardarPermisosRol(${rol.id})">
                            <i class="bi bi-save"></i> Guardar Permisos de ${rol.nombre}
                        </button>
                    </div>
                `;
                
                contentHTML += `
                    <div class="tab-pane fade ${show}" id="rol-${rol.id}" role="tabpanel">
                        ${tablaPermisos}
                    </div>
                `;
            });
            
            const modalHTML = `
                <div class="modal fade" id="modalPermisos" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi bi-lock"></i> Configurar Permisos por Rol
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> 
                                    <strong>Instrucciones:</strong> Seleccione los permisos para cada rol marcando las casillas correspondientes. 
                                    Los cambios se guardan por rol.
                                </div>
                                
                                <ul class="nav nav-tabs" id="rolesTabs" role="tablist">
                                    ${tabsHTML}
                                </ul>
                                
                                <div class="tab-content mt-3" id="rolesTabsContent">
                                    ${contentHTML}
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Eliminar modal anterior si existe
            const modalAnterior = document.getElementById('modalPermisos');
            if (modalAnterior) {
                modalAnterior.remove();
            }
            
            // Agregar nuevo modal
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            const modal = new bootstrap.Modal(document.getElementById('modalPermisos'));
            modal.show();
        }
        
        function guardarPermisosRol(rolId) {
            // Recopilar todos los permisos del rol
            const permisos = {};
            const checkboxes = document.querySelectorAll(`.permiso-checkbox[data-rol="${rolId}"]`);
            
            checkboxes.forEach(checkbox => {
                const modulo = checkbox.getAttribute('data-modulo');
                const accion = checkbox.getAttribute('data-accion');
                
                if (!permisos[modulo]) {
                    permisos[modulo] = [];
                }
                
                if (checkbox.checked) {
                    permisos[modulo].push(accion);
                }
            });
            
            if (!confirm('¿Está seguro de guardar los permisos para este rol?')) {
                return;
            }
            
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
            
            const formData = new FormData();
            formData.append('action', 'guardar_permisos');
            formData.append('rol_id', rolId);
            formData.append('permisos', JSON.stringify(permisos));
            
            fetch('ajax_permisos.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                
                if (data.success) {
                    mostrarAlerta(data.message, 'success');
                } else {
                    mostrarAlerta(data.message || 'Error al guardar permisos', 'danger');
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                mostrarAlerta('Error al guardar permisos: ' + error.message, 'danger');
            });
        }
    </script>
</body>
</html>
