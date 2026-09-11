<?php
/**
 * Clase Auth - Manejo de autenticación y autorización
 * TECNOXPERT - Sistema de Inventarios y Facturación
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/Database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Autenticar usuario
     */
    public function login($username, $password) {
        try {
            // Verificar si el usuario está bloqueado
            $user = $this->db->query(
                "SELECT u.*, r.nombre as rol_nombre, r.permisos 
                 FROM usuarios u 
                 JOIN roles r ON u.rol_id = r.id 
                 WHERE u.username = ? OR u.email = ?",
                [$username, $username]
            );
            
            if (empty($user)) {
                $this->incrementLoginAttempts($username);
                return ['success' => false, 'message' => 'Usuario o contraseña incorrectos'];
            }
            
            $user = $user[0];
            
            // Verificar si el usuario está activo
            if (!$user['activo']) {
                return ['success' => false, 'message' => 'Usuario inactivo'];
            }
            
            // Verificar si el usuario está bloqueado
            if ($user['bloqueado_hasta'] && strtotime($user['bloqueado_hasta']) > time()) {
                $remainingTime = ceil((strtotime($user['bloqueado_hasta']) - time()) / 60);
                return ['success' => false, 'message' => "Usuario bloqueado. Intente en {$remainingTime} minutos"];
            }
            
            // Verificar contraseña
            if (!verifyPassword($password, $user['password'])) {
                $this->incrementLoginAttempts($username);
                return ['success' => false, 'message' => 'Usuario o contraseña incorrectos'];
            }
            
            // Login exitoso - resetear intentos
            $this->resetLoginAttempts($user['id']);
            
            // Crear sesión
            $this->createSession($user);
            
            // Registrar login exitoso
            logActivity("Login exitoso para usuario: {$user['username']}", 'INFO', $user['id']);
            
            return ['success' => true, 'user' => $user];
            
        } catch (Exception $e) {
            logActivity("Error en login: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => 'Error interno del sistema'];
        }
    }
    
    /**
     * Cerrar sesión
     */
    public function logout() {
        $userId = getCurrentUserId();
        $username = getCurrentUserName();
        
        // Destruir sesión
        session_destroy();
        
        // Registrar logout
        if ($userId) {
            logActivity("Logout del usuario: {$username}", 'INFO', $userId);
        }
        
        return true;
    }
    
    /**
     * Crear sesión de usuario
     */
    private function createSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nombre'] . ' ' . $user['apellido'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['rol_nombre'];
        $_SESSION['user_permissions'] = json_decode($user['permisos'], true);
        $_SESSION['login_time'] = time();
        
        // Actualizar último login
        $this->db->update('usuarios', 
            ['ultimo_login' => date('Y-m-d H:i:s')], 
            'id = ?', 
            [$user['id']]
        );
    }
    
    /**
     * Incrementar intentos de login
     */
    private function incrementLoginAttempts($username) {
        $sql = "UPDATE usuarios SET intentos_login = intentos_login + 1 WHERE username = ? OR email = ?";
        $this->db->executeQuery($sql, [$username, $username]);
        
        // Verificar si debe bloquear
        $user = $this->db->query(
            "SELECT id, intentos_login FROM usuarios WHERE username = ? OR email = ?",
            [$username, $username]
        );
        
        if (!empty($user) && $user[0]['intentos_login'] >= MAX_LOGIN_ATTEMPTS) {
            $blockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_TIME);
            $this->db->update('usuarios', 
                ['bloqueado_hasta' => $blockUntil], 
                'id = ?', 
                [$user[0]['id']]
            );
            
            logActivity("Usuario bloqueado por múltiples intentos de login: {$username}", 'WARNING');
        }
    }
    
    /**
     * Resetear intentos de login
     */
    private function resetLoginAttempts($userId) {
        $this->db->update('usuarios', 
            ['intentos_login' => 0, 'bloqueado_hasta' => null], 
            'id = ?', 
            [$userId]
        );
    }
    
    /**
     * Verificar si el usuario tiene un permiso específico
     */
    public function hasPermission($permission, $action = 'read') {
        if (!isAuthenticated()) {
            return false;
        }
        
        $permissions = $_SESSION['user_permissions'] ?? [];
        
        if (isset($permissions[$permission])) {
            return in_array($action, $permissions[$permission]);
        }
        
        return false;
    }
    
    /**
     * Verificar si el usuario tiene acceso a un módulo
     */
    public function hasModuleAccess($module) {
        if (!isAuthenticated()) {
            return false;
        }
        
        $permissions = $_SESSION['user_permissions'] ?? [];
        return isset($permissions[$module]);
    }
    
    /**
     * Obtener permisos del usuario actual
     */
    public function getCurrentPermissions() {
        return $_SESSION['user_permissions'] ?? [];
    }
    
    /**
     * Cambiar contraseña
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // Verificar contraseña actual
            $user = $this->db->findById('usuarios', $userId);
            if (!$user) {
                return ['success' => false, 'message' => 'Usuario no encontrado'];
            }
            
            if (!verifyPassword($currentPassword, $user['password'])) {
                return ['success' => false, 'message' => 'Contraseña actual incorrecta'];
            }
            
            // Hashear nueva contraseña
            $hashedPassword = hashPassword($newPassword);
            
            // Actualizar contraseña
            $this->db->update('usuarios', 
                ['password' => $hashedPassword], 
                'id = ?', 
                [$userId]
            );
            
            logActivity("Contraseña cambiada para usuario ID: {$userId}", 'INFO', $userId);
            
            return ['success' => true, 'message' => 'Contraseña actualizada correctamente'];
            
        } catch (Exception $e) {
            logActivity("Error cambiando contraseña: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => 'Error interno del sistema'];
        }
    }
    
    /**
     * Solicitar recuperación de contraseña
     */
    public function requestPasswordReset($email) {
        try {
            $user = $this->db->query("SELECT id, username, nombre FROM usuarios WHERE email = ? AND activo = 1", [$email]);
            
            if (empty($user)) {
                return ['success' => false, 'message' => 'Email no encontrado o usuario inactivo'];
            }
            
            $user = $user[0];
            
            // Generar token de recuperación
            $token = generateRecoveryToken();
            $expiration = date('Y-m-d H:i:s', time() + 3600); // 1 hora
            
            // Guardar token
            $this->db->update('usuarios', 
                [
                    'token_recuperacion' => $token,
                    'token_expiracion' => $expiration
                ], 
                'id = ?', 
                [$user['id']]
            );
            
            // Aquí se enviaría el email con el token
            // Por ahora solo retornamos el token para pruebas
            logActivity("Solicitud de recuperación de contraseña para: {$email}", 'INFO', $user['id']);
            
            return [
                'success' => true, 
                'message' => 'Se ha enviado un enlace de recuperación a su email',
                'token' => $token // Solo para pruebas, eliminar en producción
            ];
            
        } catch (Exception $e) {
            logActivity("Error en recuperación de contraseña: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => 'Error interno del sistema'];
        }
    }
    
    /**
     * Resetear contraseña con token
     */
    public function resetPassword($token, $newPassword) {
        try {
            $user = $this->db->query(
                "SELECT id, username FROM usuarios 
                 WHERE token_recuperacion = ? 
                 AND token_expiracion > NOW() 
                 AND activo = 1",
                [$token]
            );
            
            if (empty($user)) {
                return ['success' => false, 'message' => 'Token inválido o expirado'];
            }
            
            $user = $user[0];
            
            // Hashear nueva contraseña
            $hashedPassword = hashPassword($newPassword);
            
            // Actualizar contraseña y limpiar token
            $this->db->update('usuarios', 
                [
                    'password' => $hashedPassword,
                    'token_recuperacion' => null,
                    'token_expiracion' => null,
                    'intentos_login' => 0,
                    'bloqueado_hasta' => null
                ], 
                'id = ?', 
                [$user['id']]
            );
            
            logActivity("Contraseña reseteada con token para usuario: {$user['username']}", 'INFO', $user['id']);
            
            return ['success' => true, 'message' => 'Contraseña actualizada correctamente'];
            
        } catch (Exception $e) {
            logActivity("Error reseteando contraseña: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => 'Error interno del sistema'];
        }
    }
    
    /**
     * Crear usuario
     */
    public function createUser($data) {
        try {
            // Verificar si el username o email ya existen
            if ($this->db->exists('usuarios', ['username' => $data['username']])) {
                return ['success' => false, 'message' => 'El nombre de usuario ya existe'];
            }
            
            if ($this->db->exists('usuarios', ['email' => $data['email']])) {
                return ['success' => false, 'message' => 'El email ya existe'];
            }
            
            // Hashear contraseña
            $data['password'] = hashPassword($data['password']);
            
            // Insertar usuario
            $userId = $this->db->insert('usuarios', $data);
            
            logActivity("Usuario creado: {$data['username']}", 'INFO', getCurrentUserId());
            
            return ['success' => true, 'user_id' => $userId, 'message' => 'Usuario creado correctamente'];
            
        } catch (Exception $e) {
            logActivity("Error creando usuario: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => 'Error interno del sistema'];
        }
    }
    
    /**
     * Actualizar usuario
     */
    public function updateUser($userId, $data) {
        try {
            // Verificar si el usuario existe
            $user = $this->db->findById('usuarios', $userId);
            if (!$user) {
                return ['success' => false, 'message' => 'Usuario no encontrado'];
            }
            
            // Verificar username único
            if (isset($data['username']) && $data['username'] !== $user['username']) {
                if ($this->db->exists('usuarios', ['username' => $data['username']])) {
                    return ['success' => false, 'message' => 'El nombre de usuario ya existe'];
                }
            }
            
            // Verificar email único
            if (isset($data['email']) && $data['email'] !== $user['email']) {
                if ($this->db->exists('usuarios', ['email' => $data['email']])) {
                    return ['success' => false, 'message' => 'El email ya existe'];
                }
            }
            
            // Si hay nueva contraseña, hashearla
            if (isset($data['password']) && !empty($data['password'])) {
                $data['password'] = hashPassword($data['password']);
            } else {
                unset($data['password']);
            }
            
            // Actualizar usuario
            $this->db->update('usuarios', $data, 'id = ?', [$userId]);
            
            logActivity("Usuario actualizado: {$user['username']}", 'INFO', getCurrentUserId());
            
            return ['success' => true, 'message' => 'Usuario actualizado correctamente'];
            
        } catch (Exception $e) {
            logActivity("Error actualizando usuario: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => 'Error interno del sistema'];
        }
    }
    
    /**
     * Eliminar usuario
     */
    public function deleteUser($userId) {
        try {
            $user = $this->db->findById('usuarios', $userId);
            if (!$user) {
                return ['success' => false, 'message' => 'Usuario no encontrado'];
            }
            
            // No permitir eliminar el usuario actual
            if ($userId == getCurrentUserId()) {
                return ['success' => false, 'message' => 'No puede eliminar su propia cuenta'];
            }
            
            // Eliminar usuario
            $this->db->delete('usuarios', 'id = ?', [$userId]);
            
            logActivity("Usuario eliminado: {$user['username']}", 'INFO', getCurrentUserId());
            
            return ['success' => true, 'message' => 'Usuario eliminado correctamente'];
            
        } catch (Exception $e) {
            logActivity("Error eliminando usuario: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => 'Error interno del sistema'];
        }
    }
    
    /**
     * Obtener todos los usuarios
     */
    public function getAllUsers($page = 1, $perPage = ITEMS_PER_PAGE) {
        try {
            $joins = [
                [
                    'type' => 'LEFT',
                    'table' => 'roles',
                    'condition' => 'usuarios.rol_id = roles.id'
                ]
            ];
            
            $select = 'usuarios.*, roles.nombre as rol_nombre';
            $orderBy = 'usuarios.creado_en DESC';
            
            $offset = ($page - 1) * $perPage;
            $limit = "{$perPage} OFFSET {$offset}";
            
            $users = $this->db->join('usuarios', $joins, $select, [], $orderBy, $limit);
            $total = $this->db->count('usuarios');
            
            return [
                'users' => $users,
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage)
            ];
            
        } catch (Exception $e) {
            logActivity("Error obteniendo usuarios: " . $e->getMessage(), 'ERROR');
            return ['users' => [], 'total' => 0];
        }
    }
    
    /**
     * Obtener roles disponibles
     */
    public function getRoles() {
        try {
            return $this->db->findAll('roles', ['activo' => 1], 'nombre ASC');
        } catch (Exception $e) {
            logActivity("Error obteniendo roles: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Verificar si la sesión ha expirado
     */
    public function isSessionExpired() {
        if (!isset($_SESSION['login_time'])) {
            return true;
        }
        
        $sessionAge = time() - $_SESSION['login_time'];
        return $sessionAge > SESSION_LIFETIME;
    }
    
    /**
     * Renovar sesión
     */
    public function renewSession() {
        $_SESSION['login_time'] = time();
    }
}
?>

