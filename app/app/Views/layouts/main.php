<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Sistema de Inventarios y Facturación - TECNOXPERT' ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= BASE_URL ?>public/css/style.css" rel="stylesheet">
    
    <!-- Meta tags para evitar cache -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>dashboard">
                <i class="fas fa-boxes me-2"></i>
                TECNOXPERT
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if (isAuthenticated()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>dashboard">
                                <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                            </a>
                        </li>
                        
                        <?php if (hasPermission('productos')): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-box me-1"></i> Inventario
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>productos">Productos</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>categorias">Categorías</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>marcas">Marcas</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>movimientos">Movimientos</a></li>
                            </ul>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasPermission('ventas')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>ventas">
                                <i class="fas fa-shopping-cart me-1"></i> Ventas
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasPermission('compras')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>compras">
                                <i class="fas fa-truck me-1"></i> Compras
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-users me-1"></i> Terceros
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>clientes">Clientes</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>proveedores">Proveedores</a></li>
                            </ul>
                        </li>
                        
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-chart-bar me-1"></i> Reportes
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>reportes/inventario">Inventario</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>reportes/ventas">Ventas</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>reportes/compras">Compras</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>reportes/kardex">Kardex</a></li>
                            </ul>
                        </li>
                        
                        <?php if (hasPermission('configuracion')): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-cog me-1"></i> Configuración
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>config/empresa">Empresa</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>config/impuestos">Impuestos</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>config/consecutivos">Consecutivos</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>config/usuarios">Usuarios</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>config/facturacion-electronica">Facturación Electrónica</a></li>
                            </ul>
                        </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
                
                <?php if (isAuthenticated()): ?>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <?= $_SESSION['user']['username'] ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>perfil">
                                <i class="fas fa-user me-2"></i> Mi Perfil
                            </a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>cambiar-password">
                                <i class="fas fa-key me-2"></i> Cambiar Contraseña
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>logout">
                                <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión
                            </a></li>
                        </ul>
                    </li>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container-fluid mt-5 pt-3">
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?> alert-dismissible fade show" role="alert">
                <?= $_SESSION['flash_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

        <?= $content ?>
    </main>

    <!-- Footer -->
    <footer class="bg-light text-center text-muted py-3 mt-5">
        <div class="container">
            <p class="mb-0">
                &copy; <?= date('Y') ?> TECNOXPERT - Sistema de Inventarios y Facturación
                <br>
                <small>Desarrollado por Hugo Alberto Ardila Molina</small>
            </p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script src="<?= BASE_URL ?>public/js/app.js"></script>
    
    <?php if (isset($scripts)): ?>
        <?= $scripts ?>
    <?php endif; ?>
</body>
</html>

