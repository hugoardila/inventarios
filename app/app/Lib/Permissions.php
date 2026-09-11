<?php

class Permissions {
    
    /**
     * Verificar si el usuario puede realizar una acción
     */
    public static function can($action, $module = null) {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
            return false;
        }
        
        $role = $_SESSION['user_role'];
        
        // Definir permisos por rol
        $permissions = [
            'consulta' => [
                'read' => true,
                'create' => false,
                'update' => false,
                'delete' => false,
                'modules' => ['productos', 'clientes', 'proveedores', 'ventas', 'compras', 'reportes']
            ],
            'empleado' => [
                'read' => true,
                'create' => true,
                'update' => true,
                'delete' => false,
                'modules' => ['productos', 'clientes', 'proveedores', 'ventas', 'compras', 'reportes']
            ],
            'admin' => [
                'read' => true,
                'create' => true,
                'update' => true,
                'delete' => true,
                'modules' => ['productos', 'clientes', 'proveedores', 'ventas', 'compras', 'reportes', 'usuarios', 'configuracion']
            ]
        ];
        
        if (!isset($permissions[$role])) {
            return false;
        }
        
        $userPermissions = $permissions[$role];
        
        // Verificar acción
        if (isset($userPermissions[$action]) && !$userPermissions[$action]) {
            return false;
        }
        
        // Verificar módulo
        if ($module && isset($userPermissions['modules'])) {
            return in_array($module, $userPermissions['modules']);
        }
        
        return true;
    }
    
    /**
     * Verificar si puede crear
     */
    public static function canCreate($module = null) {
        return self::can('create', $module);
    }
    
    /**
     * Verificar si puede editar
     */
    public static function canEdit($module = null) {
        return self::can('update', $module);
    }
    
    /**
     * Verificar si puede eliminar
     */
    public static function canDelete($module = null) {
        return self::can('delete', $module);
    }
    
    /**
     * Verificar si puede ver
     */
    public static function canView($module = null) {
        return self::can('read', $module);
    }
    
    /**
     * Verificar si es solo consulta
     */
    public static function isReadOnly() {
        return $_SESSION['user_role'] === 'consulta';
    }
    
    /**
     * Verificar si es empleado o admin
     */
    public static function canModify() {
        return in_array($_SESSION['user_role'], ['empleado', 'admin']);
    }
    
    /**
     * Verificar si es admin
     */
    public static function isAdmin() {
        return $_SESSION['user_role'] === 'admin';
    }
    
    /**
     * Obtener mensaje de acceso denegado
     */
    public static function getAccessDeniedMessage() {
        return "No tienes permisos para realizar esta acción";
    }
    
    /**
     * Redirigir si no tiene permisos
     */
    public static function requirePermission($action, $module = null, $redirectTo = 'index.php') {
        if (!self::can($action, $module)) {
            header("Location: $redirectTo?error=access_denied");
            exit();
        }
    }
}

