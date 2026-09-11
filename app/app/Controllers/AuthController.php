<?php
/**
 * Controlador de Autenticación
 * TECNOXPERT - Sistema de Inventarios y Facturación
 */

require_once __DIR__ . '/../Lib/Auth.php';

class AuthController {
    private $auth;
    
    public function __construct() {
        $this->auth = new Auth();
    }
    
    /**
     * Mostrar formulario de login
     */
    public function login() {
        // Si ya está autenticado, redirigir al dashboard
        if (isAuthenticated()) {
            redirect('dashboard');
        }
        
        $error = '';
        $success = '';
        
        // Verificar si hay mensajes de error
        if (isset($_GET['expired'])) {
            $error = 'Su sesión ha expirado. Por favor, inicie sesión nuevamente.';
        }
        
        // Procesar formulario de login
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = sanitizeInput($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                $error = 'Por favor, complete todos los campos.';
            } else {
                $result = $this->auth->login($username, $password);
                
                if ($result['success']) {
                    redirect('dashboard');
                } else {
                    $error = $result['message'];
                }
            }
        }
        
        return [
            'view' => 'auth/login',
            'data' => [
                'error' => $error,
                'success' => $success,
                'title' => 'Iniciar Sesión - ' . APP_NAME
            ],
            'layout' => 'auth'
        ];
    }
    
    /**
     * Cerrar sesión
     */
    public function logout() {
        $this->auth->logout();
        redirect('auth/login');
    }
    
    /**
     * Mostrar formulario de recuperación de contraseña
     */
    public function forgotPassword() {
        $error = '';
        $success = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = sanitizeInput($_POST['email'] ?? '');
            
            if (empty($email) || !validateEmail($email)) {
                $error = 'Por favor, ingrese un email válido.';
            } else {
                $result = $this->auth->requestPasswordReset($email);
                
                if ($result['success']) {
                    $success = $result['message'];
                    // En producción, no mostrar el token
                    if (isset($result['token'])) {
                        $success .= ' Token para pruebas: ' . $result['token'];
                    }
                } else {
                    $error = $result['message'];
                }
            }
        }
        
        return [
            'view' => 'auth/forgot-password',
            'data' => [
                'error' => $error,
                'success' => $success,
                'title' => 'Recuperar Contraseña - ' . APP_NAME
            ],
            'layout' => 'auth'
        ];
    }
    
    /**
     * Resetear contraseña con token
     */
    public function resetPassword() {
        $error = '';
        $success = '';
        $token = $_GET['token'] ?? '';
        
        if (empty($token)) {
            redirect('auth/login');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (empty($password) || empty($confirmPassword)) {
                $error = 'Por favor, complete todos los campos.';
            } elseif ($password !== $confirmPassword) {
                $error = 'Las contraseñas no coinciden.';
            } elseif (strlen($password) < 6) {
                $error = 'La contraseña debe tener al menos 6 caracteres.';
            } else {
                $result = $this->auth->resetPassword($token, $password);
                
                if ($result['success']) {
                    $success = $result['message'];
                    // Redirigir al login después de 3 segundos
                    header("Refresh: 3; url=auth/login");
                } else {
                    $error = $result['message'];
                }
            }
        }
        
        return [
            'view' => 'auth/reset-password',
            'data' => [
                'error' => $error,
                'success' => $success,
                'token' => $token,
                'title' => 'Resetear Contraseña - ' . APP_NAME
            ],
            'layout' => 'auth'
        ];
    }
    
    /**
     * Cambiar contraseña (usuario autenticado)
     */
    public function changePassword() {
        // Verificar autenticación
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $error = '';
        $success = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $error = 'Por favor, complete todos los campos.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'Las nuevas contraseñas no coinciden.';
            } elseif (strlen($newPassword) < 6) {
                $error = 'La nueva contraseña debe tener al menos 6 caracteres.';
            } else {
                $result = $this->auth->changePassword(getCurrentUserId(), $currentPassword, $newPassword);
                
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
            }
        }
        
        return [
            'view' => 'auth/change-password',
            'data' => [
                'error' => $error,
                'success' => $success,
                'title' => 'Cambiar Contraseña - ' . APP_NAME,
                'menuItems' => getMenuItems()
            ]
        ];
    }
}
?>

