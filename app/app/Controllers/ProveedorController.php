<?php
/**
 * Controlador de Proveedores
 * TECNOXPERT - Sistema de Inventarios y Facturación
 */

require_once __DIR__ . '/../Lib/Database.php';

class ProveedorController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function index() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $search = $_GET['search'] ?? '';
        $conditions = ['activo' => 1];
        
        if (!empty($search)) {
            $conditions['nombre LIKE'] = "%{$search}%";
        }
        
        $proveedores = $this->db->findAll('proveedores', $conditions, 'nombre ASC');
        
        return [
            'view' => 'proveedores/index',
            'data' => [
                'title' => 'Proveedores - ' . APP_NAME,
                'proveedores' => $proveedores,
                'search' => $search,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    public function create() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        return [
            'view' => 'proveedores/create',
            'data' => [
                'title' => 'Crear Proveedor - ' . APP_NAME,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    public function store() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('proveedores');
        }
        
        $data = [
            'nit' => sanitizeInput($_POST['nit'] ?? ''),
            'nombre' => sanitizeInput($_POST['nombre'] ?? ''),
            'contacto' => sanitizeInput($_POST['contacto'] ?? ''),
            'telefono' => sanitizeInput($_POST['telefono'] ?? ''),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'direccion' => sanitizeInput($_POST['direccion'] ?? ''),
            'ciudad' => sanitizeInput($_POST['ciudad'] ?? ''),
            'pais' => sanitizeInput($_POST['pais'] ?? 'Colombia'),
            'activo' => 1
        ];
        
        try {
            $this->db->insert('proveedores', $data);
            logActivity("Proveedor creado: {$data['nombre']}", 'INFO', getCurrentUserId());
            redirect('proveedores?success=created');
        } catch (Exception $e) {
            logActivity("Error creando proveedor: " . $e->getMessage(), 'ERROR');
            redirect('proveedores/create?error=creation_failed');
        }
    }
    
    public function show($id) {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $proveedor = $this->db->findById('proveedores', $id);
        
        if (!$proveedor) {
            redirect('proveedores?error=not_found');
        }
        
        // Obtener compras del proveedor
        $compras = $this->db->query(
            "SELECT * FROM compras WHERE proveedor_id = ? ORDER BY fecha_compra DESC LIMIT 10",
            [$id]
        );
        
        return [
            'view' => 'proveedores/show',
            'data' => [
                'title' => $proveedor['nombre'] . ' - ' . APP_NAME,
                'proveedor' => $proveedor,
                'compras' => $compras,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    public function edit($id) {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $proveedor = $this->db->findById('proveedores', $id);
        
        if (!$proveedor) {
            redirect('proveedores?error=not_found');
        }
        
        return [
            'view' => 'proveedores/edit',
            'data' => [
                'title' => 'Editar Proveedor - ' . APP_NAME,
                'proveedor' => $proveedor,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    public function update($id) {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('proveedores');
        }
        
        $proveedor = $this->db->findById('proveedores', $id);
        
        if (!$proveedor) {
            redirect('proveedores?error=not_found');
        }
        
        $data = [
            'nit' => sanitizeInput($_POST['nit'] ?? ''),
            'nombre' => sanitizeInput($_POST['nombre'] ?? ''),
            'contacto' => sanitizeInput($_POST['contacto'] ?? ''),
            'telefono' => sanitizeInput($_POST['telefono'] ?? ''),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'direccion' => sanitizeInput($_POST['direccion'] ?? ''),
            'ciudad' => sanitizeInput($_POST['ciudad'] ?? ''),
            'pais' => sanitizeInput($_POST['pais'] ?? 'Colombia')
        ];
        
        try {
            $this->db->update('proveedores', $data, 'id = ?', [$id]);
            logActivity("Proveedor actualizado: {$data['nombre']}", 'INFO', getCurrentUserId());
            redirect('proveedores?success=updated');
        } catch (Exception $e) {
            logActivity("Error actualizando proveedor: " . $e->getMessage(), 'ERROR');
            redirect("proveedores/edit/{$id}?error=update_failed");
        }
    }
    
    public function delete($id) {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $proveedor = $this->db->findById('proveedores', $id);
        
        if (!$proveedor) {
            redirect('proveedores?error=not_found');
        }
        
        try {
            $this->db->update('proveedores', ['activo' => 0], 'id = ?', [$id]);
            logActivity("Proveedor eliminado: {$proveedor['nombre']}", 'INFO', getCurrentUserId());
            redirect('proveedores?success=deleted');
        } catch (Exception $e) {
            logActivity("Error eliminando proveedor: " . $e->getMessage(), 'ERROR');
            redirect('proveedores?error=delete_failed');
        }
    }
}
?>

