<?php
session_start();

// Verificar si está logueado y es admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Conexión simple a la base de datos
try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar si las tablas existen antes de consultar
    $stmt = $pdo->query("SHOW TABLES LIKE 'usuarios'");
    $usuariosExisten = $stmt->rowCount() > 0;
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'roles'");
    $rolesExisten = $stmt->rowCount() > 0;
    
    if ($usuariosExisten && $rolesExisten) {
        $usuarios = $pdo->query("SELECT u.*, r.nombre as rol FROM usuarios u LEFT JOIN roles r ON u.rol_id = r.id WHERE u.activo = 1 ORDER BY u.nombre")->fetchAll();
        $roles = $pdo->query("SELECT * FROM roles WHERE activo = 1 ORDER BY nombre")->fetchAll();
    } else {
        $usuarios = [];
        $roles = [];
        $error = "Las tablas 'usuarios' o 'roles' no existen";
    }
} catch (PDOException $e) {
    $error = "Error de conexión: " . $e->getMessage();
    $usuarios = [];
    $roles = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Usuarios - TECNOXPERT</title>
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
            <h2>👥 Gestión de Usuarios</h2>
            <div>
                <a href="configuracion.php" class="btn btn-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUsuario">
                    <i class="bi bi-plus-circle"></i> Nuevo Usuario
                </button>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <strong>Error:</strong> <?php echo $error; ?>
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
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($usuarios)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">
                                        No hay usuarios registrados
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($usuarios as $usuario): ?>
                                <tr data-id="<?php echo $usuario['id']; ?>">
                                    <td><?php echo $usuario['id']; ?></td>
                                    <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($usuario['rol'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">Activo</span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editarUsuario(<?php echo $usuario['id']; ?>)" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="eliminarUsuario(<?php echo $usuario['id']; ?>)" title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
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

    <!-- Modal Nuevo Usuario -->
    <div class="modal fade" id="modalUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formUsuario">
                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" class="form-control" name="nombre" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Apellido</label>
                            <input type="text" class="form-control" name="apellido" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nombre de Usuario</label>
                            <input type="text" class="form-control" name="username" required>
                            <div class="form-text">Será usado para iniciar sesión</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contraseña</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rol</label>
                            <select class="form-select" name="rol_id" required>
                                <option value="">Seleccionar rol...</option>
                                <?php foreach ($roles as $rol): ?>
                                <option value="<?php echo $rol['id']; ?>">
                                    <?php echo htmlspecialchars($rol['nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarUsuario()">Guardar Usuario</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar Usuario -->
    <div class="modal fade" id="modalEditarUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formEditarUsuario">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Apellido</label>
                            <input type="text" class="form-control" name="apellido" id="edit_apellido" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nombre de Usuario</label>
                            <input type="text" class="form-control" name="username" id="edit_username" required>
                            <div class="form-text">Será usado para iniciar sesión</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nueva Contraseña (dejar vacío para no cambiar)</label>
                            <input type="password" class="form-control" name="password" id="edit_password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rol</label>
                            <select class="form-select" name="rol_id" id="edit_rol_id" required>
                                <option value="">Seleccionar rol...</option>
                                <?php foreach ($roles as $rol): ?>
                                <option value="<?php echo $rol['id']; ?>">
                                    <?php echo htmlspecialchars($rol['nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="actualizarUsuario()">Actualizar Usuario</button>
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
        
        function guardarUsuario() {
            const form = document.getElementById('formUsuario');
            const formData = new FormData(form);
            formData.append('action', 'guardar');
            
            fetch('ajax_usuarios.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta(data.message, 'success');
                    document.getElementById('modalUsuario').querySelector('.btn-close').click();
                    form.reset();
                    // Recargar la página para mostrar el nuevo usuario
                    setTimeout(() => location.reload(), 1000);
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al guardar el usuario', 'danger');
            });
        }
        
        function editarUsuario(id) {
            const formData = new FormData();
            formData.append('action', 'obtener');
            formData.append('id', id);
            
            fetch('ajax_usuarios.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const usuario = data.data;
                    
                    // Llenar el formulario con los datos del usuario
                    document.getElementById('edit_id').value = usuario.id;
                    document.getElementById('edit_nombre').value = usuario.nombre;
                    document.getElementById('edit_apellido').value = usuario.apellido;
                    document.getElementById('edit_username').value = usuario.username;
                    document.getElementById('edit_email').value = usuario.email;
                    document.getElementById('edit_rol_id').value = usuario.rol_id;
                    document.getElementById('edit_password').value = ''; // Limpiar contraseña
                    
                    // Mostrar el modal
                    new bootstrap.Modal(document.getElementById('modalEditarUsuario')).show();
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al cargar datos del usuario', 'danger');
            });
        }
        
        function actualizarUsuario() {
            const form = document.getElementById('formEditarUsuario');
            const formData = new FormData(form);
            formData.append('action', 'actualizar');
            
            fetch('ajax_usuarios.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta(data.message, 'success');
                    document.getElementById('modalEditarUsuario').querySelector('.btn-close').click();
                    // Recargar la página para mostrar los cambios
                    setTimeout(() => location.reload(), 1000);
                } else {
                    mostrarAlerta(data.message, 'danger');
                }
            })
            .catch(error => {
                mostrarAlerta('Error al actualizar el usuario', 'danger');
            });
        }
        
        function eliminarUsuario(id) {
            if (confirm('¿Está seguro de eliminar este usuario?')) {
                const formData = new FormData();
                formData.append('action', 'eliminar');
                formData.append('id', id);
                
                fetch('ajax_usuarios.php', {
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
                    mostrarAlerta('Error al eliminar el usuario', 'danger');
                });
            }
        }
    </script>
</body>
</html>
