<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema de Inventarios y Facturación</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= BASE_URL ?>public/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <!-- Logo y título -->
                        <div class="text-center mb-4">
                            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-boxes fa-2x"></i>
                            </div>
                            <h4 class="fw-bold text-primary mb-1">TECNOXPERT</h4>
                            <p class="text-muted mb-0">Sistema de Inventarios y Facturación</p>
                        </div>

                        <!-- Formulario de login -->
                        <form method="POST" action="<?= BASE_URL ?>login">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope me-2"></i>Correo Electrónico
                                </label>
                                <input type="email" class="form-control form-control-lg" id="email" name="email" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Contraseña
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control form-control-lg" id="password" name="password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                                </button>
                            </div>
                        </form>

                        <!-- Enlaces adicionales -->
                        <div class="text-center mt-4">
                            <a href="<?= BASE_URL ?>forgot-password" class="text-decoration-none">
                                <i class="fas fa-question-circle me-1"></i>¿Olvidaste tu contraseña?
                            </a>
                        </div>

                        <!-- Información de contacto -->
                        <div class="text-center mt-4 pt-3 border-top">
                            <small class="text-muted">
                                <i class="fas fa-headset me-1"></i>Soporte Técnico<br>
                                <strong>Hugo Alberto Ardila Molina</strong><br>
                                <i class="fas fa-phone me-1"></i><br>
                                <i class="fas fa-envelope me-1"></i>contacto@tecnoxpert.com
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Información de usuarios demo -->
                <div class="card mt-3 border-0 bg-info bg-opacity-10">
                    <div class="card-body p-3">
                        <h6 class="card-title text-info mb-2">
                            <i class="fas fa-info-circle me-1"></i>Usuarios de Prueba
                        </h6>
                        <div class="row">
                            <div class="col-4">
                                <small class="d-block fw-bold">Admin</small>
                                <small class="text-muted">admin@local</small>
                            </div>
                            <div class="col-4">
                                <small class="d-block fw-bold">Vendedor</small>
                                <small class="text-muted">vendedor@local</small>
                            </div>
                            <div class="col-4">
                                <small class="d-block fw-bold">Consulta</small>
                                <small class="text-muted">consulta@local</small>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-1">Contraseña: admin123</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Auto-focus on email field
        document.getElementById('email').focus();
    </script>
</body>
</html>

