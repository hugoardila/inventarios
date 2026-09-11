<?php
session_start();

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Incluir clase de permisos
require_once '../app/Lib/Permissions.php';

// Verificar permisos para el módulo de proveedores
Permissions::requirePermission('read', 'proveedores');

// Conexión simple a la base de datos con manejo de errores
try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar si la tabla existe antes de consultar
    $stmt = $pdo->query("SHOW TABLES LIKE 'proveedores'");
    if ($stmt->rowCount() > 0) {
        // Usar 'activo' en lugar de 'estado'
        $stmt = $pdo->query("SELECT * FROM proveedores WHERE activo = 1 ORDER BY nombre");
        $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $proveedores = [];
        $error = "La tabla 'proveedores' no existe";
    }
} catch (PDOException $e) {
    $error = "Error de conexión: " . $e->getMessage();
    $proveedores = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proveedores - TECNOXPERT</title>
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
            <h2>🏢 Gestión de Proveedores</h2>
            <div>
                <a href="../index.php" class="btn btn-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
                <?php if (Permissions::canCreate('proveedores')): ?>
                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalProveedor">
                    <i class="bi bi-plus-circle"></i> Nuevo Proveedor
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <strong>Error:</strong> <?php echo $error; ?>
                <br><small>Esto puede indicar que la base de datos no está configurada correctamente.</small>
            </div>
        <?php endif; ?>

        <!-- Alertas para AJAX -->
        <div id="alertContainer"></div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>NIT/RUT</th>
                                <th>Contacto</th>
                                <th>Teléfono</th>
                                <th>Email</th>
                                <th>Ciudad</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaProveedores">
                            <?php if (empty($proveedores)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">
                                        <?php if (isset($error)): ?>
                                            Error en la base de datos
                                        <?php else: ?>
                                            No hay proveedores registrados
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($proveedores as $proveedor): ?>
                                <tr data-id="<?php echo $proveedor['id']; ?>">
                                    <td><?php echo $proveedor['id']; ?></td>
                                    <td><?php echo htmlspecialchars($proveedor['nombre'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($proveedor['nit'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($proveedor['contacto'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($proveedor['telefono'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($proveedor['email'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($proveedor['ciudad'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (Permissions::canEdit('proveedores')): ?>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editarProveedor(<?php echo $proveedor['id']; ?>)" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if (Permissions::canDelete('proveedores')): ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="eliminarProveedor(<?php echo $proveedor['id']; ?>)" title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nuevo Proveedor -->
    <div class="modal fade" id="modalProveedor" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Proveedor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formProveedor">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nombre de la Empresa</label>
                                    <input type="text" class="form-control" name="nombre" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">NIT/RUT</label>
                                    <input type="text" class="form-control" name="nit" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Persona de Contacto</label>
                                    <input type="text" class="form-control" name="contacto" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Teléfono</label>
                                    <input type="tel" class="form-control" name="telefono" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Ciudad</label>
                                    <input type="text" class="form-control" name="ciudad" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">País</label>
                                    <input type="text" class="form-control" name="pais" value="Colombia" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Dirección</label>
                            <textarea class="form-control" name="direccion" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning" onclick="guardarProveedor()">Guardar Proveedor</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar Proveedor -->
    <div class="modal fade" id="modalEditarProveedor" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Proveedor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formEditarProveedor">
                        <input type="hidden" id="edit_id" name="id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nombre de la Empresa</label>
                                    <input type="text" class="form-control" id="edit_nombre" name="nombre" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">NIT/RUT</label>
                                    <input type="text" class="form-control" id="edit_nit" name="nit" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Persona de Contacto</label>
                                    <input type="text" class="form-control" id="edit_contacto" name="contacto" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Teléfono</label>
                                    <input type="tel" class="form-control" id="edit_telefono" name="telefono" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" id="edit_email" name="email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Ciudad</label>
                                    <input type="text" class="form-control" id="edit_ciudad" name="ciudad" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">País</label>
                                    <input type="text" class="form-control" id="edit_pais" name="pais" value="Colombia" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Dirección</label>
                            <textarea class="form-control" id="edit_direccion" name="direccion" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning" onclick="actualizarProveedor()">Guardar Cambios</button>
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
        
        function guardarProveedor() {
            const form = document.getElementById('formProveedor');
            const formData = new FormData(form);
            formData.append('action', 'guardar');
            
            fetch('ajax_proveedores.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta(data.message, 'success');
                    document.getElementById('modalProveedor').querySelector('.btn-close').click();
                    form.reset();
                    // Recargar la página para mostrar el nuevo proveedor
                    setTimeout(() => location.reload(), 1000);
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al guardar el proveedor', 'danger');
            });
        }
        
        function eliminarProveedor(id) {
            if (confirm('¿Está seguro de eliminar este proveedor?')) {
                const formData = new FormData();
                formData.append('action', 'eliminar');
                formData.append('id', id);
                
                fetch('ajax_proveedores.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarAlerta(data.message, 'success');
                        // Ocultar la fila de la tabla
                        document.querySelector(`tr[data-id="${id}"]`).remove();
                    } else {
                        mostrarAlerta(data.message, 'danger');
                    }
                })
                .catch(error => {
                    mostrarAlerta('Error al eliminar el proveedor', 'danger');
                });
            }
        }
        
        function editarProveedor(id) {
            const formData = new FormData();
            formData.append('action', 'obtener');
            formData.append('id', id);
            
            fetch('ajax_editar_proveedor.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const proveedor = data.data;
                    
                    // Llenar el formulario con los datos del proveedor
                    document.getElementById('edit_id').value = proveedor.id;
                    document.getElementById('edit_nit').value = proveedor.nit;
                    document.getElementById('edit_nombre').value = proveedor.nombre;
                    document.getElementById('edit_contacto').value = proveedor.contacto;
                    document.getElementById('edit_telefono').value = proveedor.telefono;
                    document.getElementById('edit_email').value = proveedor.email;
                    document.getElementById('edit_direccion').value = proveedor.direccion || '';
                    document.getElementById('edit_ciudad').value = proveedor.ciudad;
                    document.getElementById('edit_pais').value = proveedor.pais;
                    
                    // Mostrar el modal
                    new bootstrap.Modal(document.getElementById('modalEditarProveedor')).show();
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al cargar datos del proveedor', 'danger');
            });
        }
        
        function actualizarProveedor() {
            const form = document.getElementById('formEditarProveedor');
            const formData = new FormData(form);
            formData.append('action', 'actualizar');
            
            fetch('ajax_editar_proveedor.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta(data.message, 'success');
                    document.getElementById('modalEditarProveedor').querySelector('.btn-close').click();
                    // Recargar la página para mostrar los cambios
                    setTimeout(() => location.reload(), 1000);
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al actualizar el proveedor', 'danger');
            });
        }
    </script>
</body>
</html>
