<?php
session_start();

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Incluir clase de permisos
require_once '../app/Lib/Permissions.php';

// Verificar permisos para el módulo de categorías (usar permisos de productos)
Permissions::requirePermission('read', 'productos');

// Conexión simple a la base de datos con manejo de errores
try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar si la tabla existe antes de consultar
    $stmt = $pdo->query("SHOW TABLES LIKE 'categorias'");
    if ($stmt->rowCount() > 0) {
        // Usar 'activo' según el schema
        $stmt = $pdo->query("SELECT c.*, 
                            (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.id AND p.estado = 'activo') as total_productos
                            FROM categorias c 
                            WHERE c.activo = 1 
                            ORDER BY c.nombre");
        $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $categorias = [];
        $error = "La tabla 'categorias' no existe";
    }
} catch (PDOException $e) {
    $error = "Error de conexión: " . $e->getMessage();
    $categorias = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías - TECNOXPERT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .categoria-card {
            transition: transform 0.2s;
        }
        .categoria-card:hover {
            transform: translateY(-3px);
        }
    </style>
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
            <h2>📁 Gestión de Categorías</h2>
            <div>
                <a href="../index.php" class="btn btn-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
                <?php if (Permissions::canCreate('productos')): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCategoria">
                    <i class="bi bi-plus-circle"></i> Nueva Categoría
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
                                <th>Descripción</th>
                                <th>Productos</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaCategorias">
                            <?php if (empty($categorias)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">
                                        <?php if (isset($error)): ?>
                                            Error en la base de datos
                                        <?php else: ?>
                                            No hay categorías registradas
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categorias as $categoria): ?>
                                <tr data-id="<?php echo $categoria['id']; ?>" class="categoria-card">
                                    <td><?php echo $categoria['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($categoria['nombre'] ?? 'N/A'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($categoria['descripcion'] ?? 'Sin descripción'); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $categoria['total_productos'] ?? 0; ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $activo = $categoria['activo'] ?? 0;
                                        if ($activo == 1 || $activo === true || $activo === '1'): 
                                        ?>
                                            <span class="badge bg-success">Activa</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactiva</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (Permissions::canEdit('productos')): ?>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editarCategoria(<?php echo $categoria['id']; ?>)" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if (Permissions::canDelete('productos')): ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="eliminarCategoria(<?php echo $categoria['id']; ?>)" title="Eliminar">
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

    <!-- Modal Nueva Categoría -->
    <div class="modal fade" id="modalCategoria" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formCategoria">
                        <div class="mb-3">
                            <label class="form-label">Nombre de la Categoría <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre" required placeholder="Ej: Tecnología, Oficina, etc.">
                            <small class="text-muted">Nombre único para identificar la categoría</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" name="descripcion" rows="3" placeholder="Descripción opcional de la categoría"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarCategoria()">Guardar Categoría</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar Categoría -->
    <div class="modal fade" id="modalEditarCategoria" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formEditarCategoria">
                        <input type="hidden" id="edit_id" name="id">
                        <div class="mb-3">
                            <label class="form-label">Nombre de la Categoría <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_nombre" name="nombre" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" id="edit_descripcion" name="descripcion" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="actualizarCategoria()">Guardar Cambios</button>
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
        
        function guardarCategoria() {
            const form = document.getElementById('formCategoria');
            const formData = new FormData(form);
            formData.append('action', 'guardar');
            
            fetch('ajax_categorias.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta(data.message, 'success');
                    document.getElementById('modalCategoria').querySelector('.btn-close').click();
                    form.reset();
                    // Recargar la página para mostrar la nueva categoría
                    setTimeout(() => location.reload(), 1000);
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al guardar la categoría', 'danger');
            });
        }
        
        function eliminarCategoria(id) {
            if (confirm('¿Está seguro de eliminar esta categoría?\n\nNota: Si hay productos asociados, la categoría se desactivará en lugar de eliminarse.')) {
                const formData = new FormData();
                formData.append('action', 'eliminar');
                formData.append('id', id);
                
                fetch('ajax_categorias.php', {
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
                    mostrarAlerta('Error al eliminar la categoría', 'danger');
                });
            }
        }
        
        function editarCategoria(id) {
            const formData = new FormData();
            formData.append('action', 'obtener');
            formData.append('id', id);
            
            fetch('ajax_categorias.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const categoria = data.data;
                    
                    // Llenar el formulario con los datos de la categoría
                    document.getElementById('edit_id').value = categoria.id;
                    document.getElementById('edit_nombre').value = categoria.nombre;
                    document.getElementById('edit_descripcion').value = categoria.descripcion || '';
                    
                    // Mostrar el modal
                    new bootstrap.Modal(document.getElementById('modalEditarCategoria')).show();
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al cargar datos de la categoría', 'danger');
            });
        }
        
        function actualizarCategoria() {
            const form = document.getElementById('formEditarCategoria');
            const formData = new FormData(form);
            formData.append('action', 'actualizar');
            
            fetch('ajax_categorias.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta(data.message, 'success');
                    document.getElementById('modalEditarCategoria').querySelector('.btn-close').click();
                    // Recargar la página para mostrar los cambios
                    setTimeout(() => location.reload(), 1000);
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al actualizar la categoría', 'danger');
            });
        }
    </script>
</body>
</html>

