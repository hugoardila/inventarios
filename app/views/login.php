<?php
session_start();

// Incluir clase de seguridad
require_once '../app/Lib/Security.php';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['email'] ?? ''; // Puede ser email o username
    $password = $_POST['password'] ?? '';
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Validaciones de seguridad
    if (!Security::validateLoginInput($login)) {
        $error = "Formato de entrada inválido";
    } elseif (Security::detectSQLInjection($login)) {
        Security::logSuspiciousActivity($user_ip, $login, 'SQL Injection attempt');
        $error = "Intento de acceso bloqueado por seguridad";
    } elseif (!Security::checkRateLimit($user_ip)) {
        $error = "Demasiados intentos de login. Intenta más tarde";
    } else {
        // Conexión a la base de datos para autenticación
        try {
            $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Buscar usuario por email o username (más seguro con OR)
            $stmt = $pdo->prepare("SELECT u.*, r.nombre as rol_nombre FROM usuarios u 
                                   LEFT JOIN roles r ON u.rol_id = r.id 
                                   WHERE (u.email = ? OR u.username = ?) AND u.activo = 1");
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nombre'] . ' ' . $user['apellido'];
                $_SESSION['user_role'] = $user['rol_nombre'];
                
                // Actualizar último login
                $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
        header("Location: ../index.php");
        exit();
    } else {
        $error = "Credenciales incorrectas";
            }
        } catch (PDOException $e) {
            $error = "Error de conexión: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TECNOXPERT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Animación de fondo */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            animation: float 20s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .login-container {
            position: relative;
            z-index: 1;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }
        
        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="60" r="1" fill="rgba(255,255,255,0.05)"/><circle cx="60" cy="40" r="1" fill="rgba(255,255,255,0.05)"/></svg>');
            animation: sparkle 3s ease-in-out infinite;
        }
        
        @keyframes sparkle {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.8; }
        }
        
        .login-header h2 {
            position: relative;
            z-index: 1;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .login-header p {
            position: relative;
            z-index: 1;
            opacity: 0.9;
        }
        
        .modules-preview {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 30px;
            border-top: 1px solid rgba(0,0,0,0.1);
        }
        
        .module-item {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            height: 100%;
            overflow: hidden;
        }
        
        .module-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .module-item.inventario { border-left-color: #28a745; }
        .module-item.facturacion { border-left-color: #007bff; }
        .module-item.clientes { border-left-color: #17a2b8; }
        .module-item.reportes { border-left-color: #ffc107; }
        .module-item.proveedores { border-left-color: #6c757d; }
        .module-item.usuarios { border-left-color: #dc3545; }
        
        .module-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .floating-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }
        
        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float-shape 6s ease-in-out infinite;
        }
        
        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            right: 10%;
            animation-delay: 2s;
        }
        
        .shape:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }
        
        @keyframes float-shape {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(180deg); }
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .login-container {
                padding: 20px 10px;
            }
            
            .login-card {
                margin-bottom: 20px;
            }
            
            .modules-preview {
                padding: 20px 15px;
            }
            
            .module-item {
                margin-bottom: 10px;
                padding: 12px;
            }
            
            .module-item h6 {
                font-size: 0.9rem;
            }
            
            .module-item small {
                font-size: 0.75rem;
            }
            
            .floating-shape {
                display: none; /* Ocultar formas flotantes en móvil */
            }
        }
        
        @media (max-width: 576px) {
            .login-header {
                padding: 20px;
            }
            
            .login-header h2 {
                font-size: 1.5rem;
            }
            
            .login-header p {
                font-size: 0.9rem;
            }
            
            .module-item {
                padding: 10px;
            }
            
            .module-item .module-icon {
                font-size: 1.2rem;
            }
            
            .btn-login {
                padding: 12px;
                font-size: 1rem;
            }
            
            .form-control {
                padding: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 10px 5px;
            }
            
            .modules-preview h4 {
                font-size: 1.2rem;
            }
            
            .module-item h6 {
                font-size: 0.8rem;
            }
            
            .module-item small {
                font-size: 0.7rem;
            }
        }
    </style>
</head>
<body>
    <!-- Formas flotantes de fondo -->
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
    
    <div class="container login-container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Panel de Login -->
                <div class="row justify-content-center mb-4">
                    <div class="col-md-6">
                <div class="login-card">
                    <div class="login-header">
                                <h2><i class="bi bi-shield-lock"></i> TECNOXPERT</h2>
                                <p class="mb-0">Sistema de Inventarios y Facturación</p>
                    </div>
                    <div class="p-4">
                        <?php if (isset($error)): ?>
                                    <div class="alert alert-danger">
                                        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                                    </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                                    <div class="mb-4">
                                        <label for="email" class="form-label">
                                            <i class="bi bi-person"></i> Email o Usuario
                                        </label>
                                        <input type="text" class="form-control" id="email" name="email" required 
                                               placeholder="Email o Usuario">
                                    </div>
                                    <div class="mb-4">
                                        <label for="password" class="form-label">
                                            <i class="bi bi-lock"></i> Contraseña
                                        </label>
                                        <input type="password" class="form-control" id="password" name="password" required 
                                               placeholder="••••••••">
                                    </div>
                                    <button type="submit" class="btn btn-login text-white w-100">
                                        <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
                                    </button>
                                    <a href="/" class="btn btn-outline-secondary w-100 mt-3">
                                        <i class="bi bi-arrow-left"></i> Volver
                                    </a>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Vista Previa de Módulos -->
                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="modules-preview">
                            <h4 class="text-center mb-4">
                                <i class="bi bi-grid-3x3-gap"></i> Módulos del Sistema
                            </h4>
                            
                            <div class="row justify-content-center">
                                <div class="col-lg-2 col-md-3 col-sm-6 mb-3">
                                    <div class="module-item inventario" onclick="mostrarModal('inventario')" style="cursor: pointer;">
                                        <div class="text-center">
                                            <div class="module-icon text-success mb-2">
                                                <i class="bi bi-box-seam fs-4"></i>
                                            </div>
                                            <h6 class="mb-1">Inventario</h6>
                                            <small class="text-muted">Productos</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-lg-2 col-md-3 col-sm-6 mb-3">
                                    <div class="module-item facturacion" onclick="mostrarModal('facturacion')" style="cursor: pointer;">
                                        <div class="text-center">
                                            <div class="module-icon text-primary mb-2">
                                                <i class="bi bi-receipt fs-4"></i>
                                            </div>
                                            <h6 class="mb-1">Facturación</h6>
                                            <small class="text-muted">Facturas</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-lg-2 col-md-3 col-sm-6 mb-3">
                                    <div class="module-item clientes" onclick="mostrarModal('clientes')" style="cursor: pointer;">
                                        <div class="text-center">
                                            <div class="module-icon text-info mb-2">
                                                <i class="bi bi-people fs-4"></i>
                                            </div>
                                            <h6 class="mb-1">Clientes</h6>
                                            <small class="text-muted">Base datos</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-lg-2 col-md-3 col-sm-6 mb-3">
                                    <div class="module-item reportes" onclick="mostrarModal('reportes')" style="cursor: pointer;">
                                        <div class="text-center">
                                            <div class="module-icon text-warning mb-2">
                                                <i class="bi bi-graph-up fs-4"></i>
                                            </div>
                                            <h6 class="mb-1">Reportes</h6>
                                            <small class="text-muted">Análisis</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-lg-2 col-md-3 col-sm-6 mb-3">
                                    <div class="module-item proveedores" onclick="mostrarModal('proveedores')" style="cursor: pointer;">
                                        <div class="text-center">
                                            <div class="module-icon text-secondary mb-2">
                                                <i class="bi bi-truck fs-4"></i>
                                            </div>
                                            <h6 class="mb-1">Proveedores</h6>
                                            <small class="text-muted">Gestión</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-lg-2 col-md-3 col-sm-6 mb-3">
                                    <div class="module-item usuarios" onclick="mostrarModal('usuarios')" style="cursor: pointer;">
                                        <div class="text-center">
                                            <div class="module-icon text-danger mb-2">
                                                <i class="bi bi-people-fill fs-4"></i>
                                            </div>
                                            <h6 class="mb-1">Usuarios</h6>
                                            <small class="text-muted">Roles</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i> 
                                    Accede a todas estas funcionalidades después del login
                                </small>
                            </div>
                        
                            <!-- Derechos de Autor -->
                            <div class="text-center mt-4 pt-3 border-top">
                            <small class="text-muted">
                                    <i class="bi bi-c-circle"></i> 
                                    <strong>TECNOXPERT</strong> by Ing Hugo Ardila<br>
                                    <i class="bi bi-telephone"></i> Contacto: contacto@tecnoxpert.com
                            </small>
                                <div class="mt-2">
                                    <a href="https://wa.me/" target="_blank" class="btn btn-success btn-sm">
                                        <i class="bi bi-whatsapp"></i> WhatsApp
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modales de Vista Previa -->
    <!-- Modal Inventario -->
    <div class="modal fade" id="modalInventario" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-box-seam"></i> Módulo de Inventario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-list-ul"></i> Funcionalidades Principales</h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="bi bi-plus-circle text-success me-2"></i>
                                    Agregar Productos
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="bi bi-pencil-square text-primary me-2"></i>
                                    Editar Productos
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="bi bi-trash text-danger me-2"></i>
                                    Eliminar Productos
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="bi bi-search text-info me-2"></i>
                                    Buscar Productos
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-graph-up"></i> Información de Stock</h6>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Productos Activos:</span>
                                        <span class="badge bg-success">150</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Stock Bajo:</span>
                                        <span class="badge bg-warning">5</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Sin Stock:</span>
                                        <span class="badge bg-danger">2</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Valor Total:</span>
                                        <span class="badge bg-primary">$2,500,000</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <h6><i class="bi bi-tags"></i> Categorías Disponibles</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-secondary">CELULARES</span>
                            <span class="badge bg-secondary">PROTECTORES</span>
                            <span class="badge bg-secondary">CARGADORES</span>
                            <span class="badge bg-secondary">FUNDAS</span>
                            <span class="badge bg-secondary">CABLES</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Facturación -->
    <div class="modal fade" id="modalFacturacion" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-receipt"></i> Módulo de Facturación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-calculator"></i> Proceso de Facturación</h6>
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-primary text-white rounded-circle p-2 me-3">
                                            <i class="bi bi-1-circle"></i>
                                        </div>
                                        <div>
                                            <strong>Seleccionar Cliente</strong>
                                            <br><small class="text-muted">Elegir cliente o crear nuevo</small>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-success text-white rounded-circle p-2 me-3">
                                            <i class="bi bi-2-circle"></i>
                                        </div>
                                        <div>
                                            <strong>Agregar Productos</strong>
                                            <br><small class="text-muted">Seleccionar productos y cantidades</small>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-warning text-white rounded-circle p-2 me-3">
                                            <i class="bi bi-3-circle"></i>
                                        </div>
                                        <div>
                                            <strong>Calcular Totales</strong>
                                            <br><small class="text-muted">IVA, descuentos y total final</small>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-info text-white rounded-circle p-2 me-3">
                                            <i class="bi bi-4-circle"></i>
                                        </div>
                                        <div>
                                            <strong>Generar Factura</strong>
                                            <br><small class="text-muted">Crear PDF y enviar a DIAN</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-file-earmark-pdf"></i> Características</h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Previsualización en tiempo real
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Generación automática de PDF
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Envío a DIAN automático
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Numeración consecutiva
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Cálculo automático de IVA
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Clientes -->
    <div class="modal fade" id="modalClientes" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bi bi-people"></i> Módulo de Clientes</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-person-plus"></i> Gestión de Clientes</h6>
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-3">
                                        <span>Total Clientes:</span>
                                        <span class="badge bg-primary">45</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span>Clientes Activos:</span>
                                        <span class="badge bg-success">42</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span>Nuevos este mes:</span>
                                        <span class="badge bg-info">8</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Clientes Frecuentes:</span>
                                        <span class="badge bg-warning">15</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-gear"></i> Funcionalidades</h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="bi bi-plus-circle text-success me-2"></i>
                                    Registrar Nuevo Cliente
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="bi bi-pencil-square text-primary me-2"></i>
                                    Editar Información
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="bi bi-search text-info me-2"></i>
                                    Buscar Clientes
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="bi bi-file-earmark-text text-warning me-2"></i>
                                    Historial de Compras
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="mt-3">
                        <h6><i class="bi bi-card-list"></i> Información del Cliente</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">Datos Personales:</small>
                                <ul class="small">
                                    <li>Nombre completo</li>
                                    <li>Tipo de documento</li>
                                    <li>Número de documento</li>
                                    <li>Teléfono y email</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Datos Fiscales:</small>
                                <ul class="small">
                                    <li>Dirección completa</li>
                                    <li>Régimen tributario</li>
                                    <li>Responsabilidad fiscal</li>
                                    <li>Dígito de verificación</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Reportes -->
    <div class="modal fade" id="modalReportes" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="bi bi-graph-up"></i> Módulo de Reportes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-bar-chart"></i> Reportes de Ventas</h6>
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Ventas del Mes:</span>
                                        <span class="text-success">$1,250,000</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Facturas Emitidas:</span>
                                        <span class="text-primary">45</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Promedio por Factura:</span>
                                        <span class="text-info">$27,778</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Crecimiento:</span>
                                        <span class="text-success">+15%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-box"></i> Reportes de Inventario</h6>
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Productos Totales:</span>
                                        <span class="text-primary">150</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Stock Bajo:</span>
                                        <span class="text-warning">5</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Sin Stock:</span>
                                        <span class="text-danger">2</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Valor Inventario:</span>
                                        <span class="text-success">$2,500,000</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <h6><i class="bi bi-file-earmark-text"></i> Tipos de Reportes</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card border-primary">
                                    <div class="card-body text-center">
                                        <i class="bi bi-calendar text-primary fs-3"></i>
                                        <h6 class="mt-2">Reportes Diarios</h6>
                                        <small class="text-muted">Ventas y movimientos del día</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-success">
                                    <div class="card-body text-center">
                                        <i class="bi bi-graph-up text-success fs-3"></i>
                                        <h6 class="mt-2">Reportes Mensuales</h6>
                                        <small class="text-muted">Análisis de rendimiento</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-info">
                                    <div class="card-body text-center">
                                        <i class="bi bi-pie-chart text-info fs-3"></i>
                                        <h6 class="mt-2">Reportes Anuales</h6>
                                        <small class="text-muted">Estadísticas completas</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Proveedores -->
    <div class="modal fade" id="modalProveedores" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title"><i class="bi bi-truck"></i> Módulo de Proveedores</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-building"></i> Gestión de Proveedores</h6>
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-3">
                                        <span>Total Proveedores:</span>
                                        <span class="badge bg-secondary">12</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span>Proveedores Activos:</span>
                                        <span class="badge bg-success">10</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span>Nuevos este mes:</span>
                                        <span class="badge bg-info">2</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Proveedores Principales:</span>
                                        <span class="badge bg-warning">5</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-gear"></i> Funcionalidades</h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="bi bi-plus-circle text-success me-2"></i>
                                    Registrar Nuevo Proveedor
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="bi bi-pencil-square text-primary me-2"></i>
                                    Editar Información
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="bi bi-search text-info me-2"></i>
                                    Buscar Proveedores
                                </li>
                                <li class="list-group-item d-flex align-items-center">
                                    <i class="bi bi-file-earmark-text text-warning me-2"></i>
                                    Historial de Compras
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="mt-3">
                        <h6><i class="bi bi-card-list"></i> Información del Proveedor</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">Datos Empresariales:</small>
                                <ul class="small">
                                    <li>Nombre de la empresa</li>
                                    <li>NIT y dígito de verificación</li>
                                    <li>Teléfono y email</li>
                                    <li>Dirección comercial</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Datos de Contacto:</small>
                                <ul class="small">
                                    <li>Representante legal</li>
                                    <li>Persona de contacto</li>
                                    <li>Teléfono de contacto</li>
                                    <li>Email de contacto</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <h6><i class="bi bi-tags"></i> Proveedores Principales</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card border-success">
                                    <div class="card-body text-center">
                                        <i class="bi bi-building text-success fs-4"></i>
                                        <h6 class="mt-2">HOLAU</h6>
                                        <small class="text-muted">Proveedor Principal</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-primary">
                                    <div class="card-body text-center">
                                        <i class="bi bi-building text-primary fs-4"></i>
                                        <h6 class="mt-2">TECNOLOGIA S.A.</h6>
                                        <small class="text-muted">Proveedor Secundario</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-info">
                                    <div class="card-body text-center">
                                        <i class="bi bi-building text-info fs-4"></i>
                                        <h6 class="mt-2">DISTRIBUIDORA</h6>
                                        <small class="text-muted">Proveedor Terciario</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <h6><i class="bi bi-graph-up"></i> Estadísticas de Compras</h6>
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <div class="border-end">
                                            <h5 class="text-success mb-1">$850,000</h5>
                                            <small class="text-muted">Compras del Mes</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border-end">
                                            <h5 class="text-primary mb-1">15</h5>
                                            <small class="text-muted">Órdenes de Compra</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border-end">
                                            <h5 class="text-info mb-1">8</h5>
                                            <small class="text-muted">Proveedores Activos</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <h5 class="text-warning mb-1">95%</h5>
                                        <small class="text-muted">Tiempo de Entrega</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Usuarios -->
    <div class="modal fade" id="modalUsuarios" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-people-fill"></i> Módulo de Usuarios
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-shield-check"></i> Roles del Sistema</h6>
                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-success me-2">Admin</span>
                                    <small>Acceso completo al sistema</small>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-primary me-2">Empleado</span>
                                    <small>Ventas e inventario</small>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-warning me-2">Consulta</span>
                                    <small>Solo lectura</small>
                                </div>
                            </div>
                            
                            <h6><i class="bi bi-lock"></i> Control de Acceso</h6>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-check-circle text-success"></i> Autenticación segura</li>
                                <li><i class="bi bi-check-circle text-success"></i> Control de sesiones</li>
                                <li><i class="bi bi-check-circle text-success"></i> Permisos por rol</li>
                                <li><i class="bi bi-check-circle text-success"></i> Auditoría de acciones</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-person-gear"></i> Gestión de Usuarios</h6>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-person-plus text-info"></i> Crear usuarios</li>
                                <li><i class="bi bi-person-check text-success"></i> Asignar roles</li>
                                <li><i class="bi bi-person-x text-danger"></i> Desactivar usuarios</li>
                                <li><i class="bi bi-key text-warning"></i> Cambiar contraseñas</li>
                            </ul>
                            
                            <h6><i class="bi bi-graph-up"></i> Estadísticas</h6>
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="border rounded p-2">
                                        <strong class="text-danger">3</strong><br>
                                        <small>Usuarios</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded p-2">
                                        <strong class="text-success">1</strong><br>
                                        <small>Admin</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded p-2">
                                        <strong class="text-primary">2</strong><br>
                                        <small>Empleados</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <h6><i class="bi bi-shield-lock"></i> Seguridad del Sistema</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border-success">
                                    <div class="card-body">
                                        <h6 class="card-title text-success">
                                            <i class="bi bi-shield-check"></i> Autenticación
                                        </h6>
                                        <ul class="small mb-0">
                                            <li>Contraseñas encriptadas</li>
                                            <li>Validación de sesiones</li>
                                            <li>Control de acceso por IP</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-warning">
                                    <div class="card-body">
                                        <h6 class="card-title text-warning">
                                            <i class="bi bi-eye"></i> Auditoría
                                        </h6>
                                        <ul class="small mb-0">
                                            <li>Registro de acciones</li>
                                            <li>Historial de cambios</li>
                                            <li>Reportes de seguridad</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function mostrarModal(modulo) {
            let modalId = '';
            switch(modulo) {
                case 'inventario':
                    modalId = 'modalInventario';
                    break;
                case 'facturacion':
                    modalId = 'modalFacturacion';
                    break;
                case 'clientes':
                    modalId = 'modalClientes';
                    break;
                case 'reportes':
                    modalId = 'modalReportes';
                    break;
                case 'proveedores':
                    modalId = 'modalProveedores';
                    break;
                case 'usuarios':
                    modalId = 'modalUsuarios';
                    break;
            }
            
            if (modalId) {
                const modal = new bootstrap.Modal(document.getElementById(modalId));
                modal.show();
            }
        }
    </script>
</body>
</html>

