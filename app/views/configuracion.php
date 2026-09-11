<?php
session_start();

// Verificar si está logueado y es admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'funciones.php';

// Obtener ambiente seleccionado para FE
$ambiente_seleccionado = obtenerAmbienteFE($_SESSION['user_id']);

// Cargar configuración
$config = [];
$configuraciones = [
    'nombre_empresa', 'nit', 'direccion', 'ciudad', 'telefono', 'email',
    'iva', 'retefuente', 'reteiva', 'reteica'
];

foreach ($configuraciones as $clave) {
    $config[$clave] = obtenerConfiguracion($clave);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - TECNOXPERT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Responsive Design para Configuración */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .card {
                margin-bottom: 15px;
            }
            
            .form-control, .form-select {
                font-size: 0.875rem;
            }
            
            .btn {
                font-size: 0.875rem;
                padding: 0.5rem 1rem;
            }
            
            .card-header h5 {
                font-size: 1.1rem;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding: 5px;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            .form-control, .form-select {
                font-size: 0.8rem;
                padding: 0.5rem;
            }
            
            .btn {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }
            
            .card-header h5 {
                font-size: 1rem;
            }
            
            .d-grid .btn {
                margin-bottom: 5px;
            }
        }
        
        @media (max-width: 480px) {
            .form-control, .form-select {
                font-size: 0.75rem;
                padding: 0.4rem;
            }
            
            .btn {
                font-size: 0.75rem;
                padding: 0.3rem 0.6rem;
            }
            
            .card-header h5 {
                font-size: 0.9rem;
            }
            
            .navbar-brand {
                font-size: 1rem;
            }
        }
        
        /* Estilos para documentos */
        .documento-card {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
        }
        
        .documento-card .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        
        .file-preview {
            background: #e9ecef;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
        }
    </style>
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
            <h2>⚙️ Configuración del Sistema</h2>
            <a href="../index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
        
        <!-- Alertas para AJAX -->
        <div id="alertContainer"></div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>🏢 Datos de la Empresa</h5>
                    </div>
                    <div class="card-body">
                        <form id="formEmpresa">
                            <div class="mb-3">
                                <label class="form-label">Nombre de la Empresa</label>
                                <input type="text" class="form-control" name="nombre_empresa" value="<?php echo htmlspecialchars($config['nombre_empresa'] ?? 'TECNOXPERT'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">NIT</label>
                                <input type="text" class="form-control" name="nit" value="<?php echo htmlspecialchars($config['nit'] ?? '900.000.000-1'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Dirección</label>
                                <textarea class="form-control" name="direccion" rows="2"><?php echo htmlspecialchars($config['direccion'] ?? 'Calle Principal #123'); ?></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Ciudad</label>
                                <input type="text" class="form-control" name="ciudad" value="<?php echo htmlspecialchars($config['ciudad'] ?? 'Bogotá'); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" name="telefono" value="<?php echo htmlspecialchars($config['telefono'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($config['email'] ?? 'contacto@tecnoxpert.com'); ?>" required>
                            </div>
                            <button type="button" class="btn btn-primary" onclick="guardarEmpresa()">
                                <i class="bi bi-save"></i> Guardar Datos
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>💰 Configuración de Impuestos</h5>
                    </div>
                    <div class="card-body">
                        <form id="formImpuestos">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">IVA (%)</label>
                                <input type="number" class="form-control" name="iva" value="<?php echo $config['iva'] ?? 19; ?>" step="0.01" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Retefuente (%)</label>
                                <input type="number" class="form-control" name="retefuente" value="<?php echo $config['retefuente'] ?? 2.5; ?>" step="0.01">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">ReteIVA (%)</label>
                                <input type="number" class="form-control" name="reteiva" value="<?php echo $config['reteiva'] ?? 15; ?>" step="0.01">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">ReteICA (%)</label>
                                <input type="number" class="form-control" name="reteica" value="<?php echo $config['reteica'] ?? 9; ?>" step="0.01">
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary" onclick="guardarImpuestos()">
                                <i class="bi bi-save"></i> Guardar Impuestos
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h5>📄 Documentos Legales</h5>
                    </div>
                    <div class="card-body">
                        <form id="formDocumentos">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <strong>Nota:</strong> Puedes subir el RUT y el certificado por separado. No es necesario tener ambos documentos al mismo tiempo.
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">RUT (Registro Único Tributario)</label>
                                <input type="file" class="form-control" name="rut" accept=".pdf,.jpg,.jpeg,.png,.gif" id="rutFile">
                                <small class="form-text text-muted">Formatos permitidos: PDF, JPG, PNG, GIF (Máximo 5MB)</small>
                                <div id="rutPreview" class="mt-2"></div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Certificado de Firma Digital</label>
                                <input type="file" class="form-control" name="certificado" accept=".p12,.pfx,.crt,.cer,.pem,.key" id="certificadoFile">
                                <small class="form-text text-muted">Formatos permitidos: P12, PFX, CRT, CER, PEM, KEY (Máximo 10MB)</small>
                                <div id="certificadoPreview" class="mt-2"></div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Contraseña del Certificado</label>
                                <input type="password" class="form-control" name="password_certificado" placeholder="Contraseña del certificado digital">
                                <small class="form-text text-muted">Contraseña para acceder al certificado</small>
                            </div>
                            
                            <button type="button" class="btn btn-primary" onclick="guardarDocumentos()">
                                <i class="bi bi-upload"></i> Subir Documentos
                            </button>
                        </form>
                        
                        <!-- Visualización de documentos existentes -->
                        <div class="mt-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6>📋 Documentos Actuales</h6>
                                <button class="btn btn-sm btn-outline-primary" onclick="cargarDocumentosActuales()">
                                    <i class="bi bi-arrow-clockwise"></i> Actualizar
                                </button>
                            </div>
                            <div id="documentosActuales">
                                <!-- Se cargarán dinámicamente -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h5>🔧 Herramientas de Administración</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" onclick="exportarBD()">
                                <i class="bi bi-download"></i> Exportar Base de Datos
                            </button>
                            <a href="facturacion_electronica.php" class="btn btn-outline-warning">
                                <i class="bi bi-lightning"></i> Facturación Electrónica (DIAN)
                            </a>
                            <button class="btn btn-outline-warning" onclick="gestionarUsuarios()">
                                <i class="bi bi-people"></i> Gestionar Usuarios
                            </button>
                            <button class="btn btn-outline-info" onclick="configuracionAvanzada()">
                                <i class="bi bi-gear"></i> Configuración Avanzada
                            </button>
                        </div>
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
            
            // Auto-remover después de 5 segundos
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
        
        function guardarEmpresa() {
            const form = document.getElementById('formEmpresa');
            const formData = new FormData(form);
            formData.append('action', 'guardar_empresa');
            
            fetch('ajax_configuracion.php', {
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
                mostrarAlerta('Error al guardar los datos de empresa', 'danger');
            });
        }
        
        function guardarImpuestos() {
            const form = document.getElementById('formImpuestos');
            const formData = new FormData(form);
            formData.append('action', 'guardar_impuestos');
            
            fetch('ajax_configuracion.php', {
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
                mostrarAlerta('Error al guardar los impuestos', 'danger');
            });
        }
        
        function exportarBD() {
            const formData = new FormData();
            formData.append('action', 'exportar_bd');
            
            fetch('ajax_configuracion.php', {
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
                mostrarAlerta('Error al exportar la base de datos', 'danger');
            });
        }
        
        function guardarDocumentos() {
            const form = document.getElementById('formDocumentos');
            const formData = new FormData(form);
            formData.append('action', 'guardar_documentos');
            
            // Validar archivos
            const rutFile = document.getElementById('rutFile').files[0];
            const certificadoFile = document.getElementById('certificadoFile').files[0];
            
            if (!rutFile && !certificadoFile) {
                mostrarAlerta('Por favor selecciona al menos un archivo', 'warning');
                return;
            }
            
            // Validar tamaño de archivos
            if (rutFile && rutFile.size > 5 * 1024 * 1024) {
                mostrarAlerta('El archivo RUT es demasiado grande (máximo 5MB)', 'danger');
                return;
            }
            
            if (certificadoFile && certificadoFile.size > 10 * 1024 * 1024) {
                mostrarAlerta('El certificado es demasiado grande (máximo 10MB)', 'danger');
                return;
            }
            
            // Validar extensiones en el frontend (opcional, el backend también valida)
            if (rutFile) {
                const rutExtension = rutFile.name.split('.').pop().toLowerCase();
                const allowedRutExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];
                if (!allowedRutExtensions.includes(rutExtension)) {
                    mostrarAlerta('RUT: Formato no permitido. Use PDF, JPG, PNG o GIF', 'warning');
                    return;
                }
            }
            
            if (certificadoFile) {
                const certExtension = certificadoFile.name.split('.').pop().toLowerCase();
                const allowedCertExtensions = ['p12', 'pfx', 'crt', 'cer', 'pem', 'key'];
                if (!allowedCertExtensions.includes(certExtension)) {
                    mostrarAlerta('Certificado: Formato no permitido. Use P12, PFX, CRT, CER, PEM o KEY', 'warning');
                    return;
                }
            }
            
            mostrarAlerta('Subiendo documentos...', 'info');
            
            fetch('ajax_configuracion.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta(data.message, 'success');
                    cargarDocumentosActuales();
                    form.reset();
                    document.getElementById('rutPreview').innerHTML = '';
                    document.getElementById('certificadoPreview').innerHTML = '';
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al subir los documentos', 'danger');
            });
        }
        
        function cargarDocumentosActuales() {
            // Mostrar indicador de carga
            document.getElementById('documentosActuales').innerHTML = '<div class="text-center"><i class="bi bi-hourglass-split"></i> Cargando documentos...</div>';
            
            fetch('ajax_configuracion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=obtener_documentos'
            })
            .then(response => response.json())
            .then(data => {
                console.log('Respuesta de documentos:', data); // Debug
                if (data.success) {
                    mostrarDocumentos(data.documentos);
                } else {
                    console.error('Error al obtener documentos:', data.message);
                    document.getElementById('documentosActuales').innerHTML = '<p class="text-muted">Error al cargar documentos: ' + data.message + '</p>';
                }
            })
            .catch(error => {
                console.error('Error al cargar documentos:', error);
                document.getElementById('documentosActuales').innerHTML = '<p class="text-muted">Error de conexión al cargar documentos</p>';
            });
        }
        
        function mostrarDocumentos(documentos) {
            const container = document.getElementById('documentosActuales');
            let html = '';
            
            console.log('Mostrando documentos:', documentos); // Debug
            
            if (documentos.rut) {
                html += `
                    <div class="card mb-2 documento-card">
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-file-earmark-pdf text-danger"></i>
                                    <strong>RUT:</strong> ${documentos.rut.nombre}
                                    <small class="text-muted">(${formatFileSize(documentos.rut.tamaño)})</small>
                                </div>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-outline-info" onclick="visualizarDocumento('rut')" title="Visualizar">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary" onclick="descargarDocumento('rut')" title="Descargar">
                                        <i class="bi bi-download"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="eliminarDocumento('rut')" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            if (documentos.certificado) {
                html += `
                    <div class="card mb-2 documento-card">
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-shield-lock text-success"></i>
                                    <strong>Certificado:</strong> ${documentos.certificado.nombre}
                                    <small class="text-muted">(${formatFileSize(documentos.certificado.tamaño)})</small>
                                </div>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-outline-info" onclick="visualizarDocumento('certificado')" title="Visualizar">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary" onclick="descargarDocumento('certificado')" title="Descargar">
                                        <i class="bi bi-download"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="eliminarDocumento('certificado')" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            if (!documentos.rut && !documentos.certificado) {
                html = '<p class="text-muted">No hay documentos cargados</p>';
            }
            
            container.innerHTML = html;
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function visualizarDocumento(tipo) {
            // Abrir documento en nueva ventana para visualización
            const url = `ajax_configuracion.php?action=visualizar_documento&tipo=${tipo}`;
            window.open(url, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
        }
        
        function descargarDocumento(tipo) {
            // Crear un enlace temporal para la descarga
            const link = document.createElement('a');
            link.href = `ajax_configuracion.php?action=descargar_documento&tipo=${tipo}`;
            link.download = '';
            link.target = '_blank';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        function eliminarDocumento(tipo) {
            if (confirm(`¿Estás seguro de que quieres eliminar el ${tipo}?`)) {
                fetch('ajax_configuracion.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=eliminar_documento&tipo=${tipo}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarAlerta(data.message, 'success');
                        cargarDocumentosActuales();
                    } else {
                        mostrarAlerta(data.message, 'danger');
                    }
                })
                .catch(error => {
                    mostrarAlerta('Error al eliminar el documento', 'danger');
                });
            }
        }
        
        // Preview de archivos
        document.getElementById('rutFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('rutPreview');
            
            if (file) {
                preview.innerHTML = `
                    <div class="file-preview">
                        <i class="bi bi-file-earmark-pdf text-danger"></i>
                        <strong>Archivo seleccionado:</strong> ${file.name} (${formatFileSize(file.size)})
                    </div>
                `;
            } else {
                preview.innerHTML = '';
            }
        });
        
        document.getElementById('certificadoFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('certificadoPreview');
            
            if (file) {
                preview.innerHTML = `
                    <div class="file-preview">
                        <i class="bi bi-shield-lock text-success"></i>
                        <strong>Certificado seleccionado:</strong> ${file.name} (${formatFileSize(file.size)})
                    </div>
                `;
            } else {
                preview.innerHTML = '';
            }
        });
        
        // Cargar documentos al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            cargarDocumentosActuales();
        });
        
        function gestionarUsuarios() {
            window.location.href = 'usuarios.php';
        }
        
        function configuracionAvanzada() {
            window.location.href = 'configuracion_avanzada.php';
        }
    </script>
</body>
</html>
