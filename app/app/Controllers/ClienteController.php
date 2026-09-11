<?php
/**
 * Controlador de Clientes
 * TECNOXPERT - Sistema de Inventarios y Facturación
 */

require_once __DIR__ . '/../Lib/Database.php';

class ClienteController {
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
        
        $clientes = $this->db->findAll('clientes', $conditions, 'nombre ASC');
        
        return [
            'view' => 'clientes/index',
            'data' => [
                'title' => 'Clientes - ' . APP_NAME,
                'clientes' => $clientes,
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
            'view' => 'clientes/create',
            'data' => [
                'title' => 'Crear Cliente - ' . APP_NAME,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    public function store() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('clientes');
        }
        
        $data = [
            'tipo_persona' => sanitizeInput($_POST['tipo_persona'] ?? ''),
            'tipo_documento' => sanitizeInput($_POST['tipo_documento'] ?? ''),
            'numero_documento' => sanitizeInput($_POST['numero_documento'] ?? ''),
            'dv' => sanitizeInput($_POST['dv'] ?? ''),
            'nombre' => sanitizeInput($_POST['nombre'] ?? ''),
            'regimen' => sanitizeInput($_POST['regimen'] ?? 'comun'),
            'telefono' => sanitizeInput($_POST['telefono'] ?? ''),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'direccion' => sanitizeInput($_POST['direccion'] ?? ''),
            'ciudad' => sanitizeInput($_POST['ciudad'] ?? ''),
            'activo' => 1
        ];
        
        try {
            $this->db->insert('clientes', $data);
            logActivity("Cliente creado: {$data['nombre']}", 'INFO', getCurrentUserId());
            redirect('clientes?success=created');
        } catch (Exception $e) {
            logActivity("Error creando cliente: " . $e->getMessage(), 'ERROR');
            redirect('clientes/create?error=creation_failed');
        }
    }
    
    public function show($id) {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $cliente = $this->db->findById('clientes', $id);
        
        if (!$cliente) {
            redirect('clientes?error=not_found');
        }
        
        // Obtener ventas del cliente
        $ventas = $this->db->query(
            "SELECT * FROM ventas WHERE cliente_id = ? ORDER BY fecha_venta DESC LIMIT 10",
            [$id]
        );
        
        return [
            'view' => 'clientes/show',
            'data' => [
                'title' => $cliente['nombre'] . ' - ' . APP_NAME,
                'cliente' => $cliente,
                'ventas' => $ventas,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    public function edit($id) {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $cliente = $this->db->findById('clientes', $id);
        
        if (!$cliente) {
            redirect('clientes?error=not_found');
        }
        
        return [
            'view' => 'clientes/edit',
            'data' => [
                'title' => 'Editar Cliente - ' . APP_NAME,
                'cliente' => $cliente,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    public function update($id) {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('clientes');
        }
        
        $cliente = $this->db->findById('clientes', $id);
        
        if (!$cliente) {
            redirect('clientes?error=not_found');
        }
        
        $data = [
            'tipo_persona' => sanitizeInput($_POST['tipo_persona'] ?? ''),
            'tipo_documento' => sanitizeInput($_POST['tipo_documento'] ?? ''),
            'numero_documento' => sanitizeInput($_POST['numero_documento'] ?? ''),
            'dv' => sanitizeInput($_POST['dv'] ?? ''),
            'nombre' => sanitizeInput($_POST['nombre'] ?? ''),
            'regimen' => sanitizeInput($_POST['regimen'] ?? 'comun'),
            'telefono' => sanitizeInput($_POST['telefono'] ?? ''),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'direccion' => sanitizeInput($_POST['direccion'] ?? ''),
            'ciudad' => sanitizeInput($_POST['ciudad'] ?? '')
        ];
        
        try {
            $this->db->update('clientes', $data, 'id = ?', [$id]);
            logActivity("Cliente actualizado: {$data['nombre']}", 'INFO', getCurrentUserId());
            redirect('clientes?success=updated');
        } catch (Exception $e) {
            logActivity("Error actualizando cliente: " . $e->getMessage(), 'ERROR');
            redirect("clientes/edit/{$id}?error=update_failed");
        }
    }
    
    public function delete($id) {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $cliente = $this->db->findById('clientes', $id);
        
        if (!$cliente) {
            redirect('clientes?error=not_found');
        }
        
        try {
            $this->db->update('clientes', ['activo' => 0], 'id = ?', [$id]);
            logActivity("Cliente eliminado: {$cliente['nombre']}", 'INFO', getCurrentUserId());
            redirect('clientes?success=deleted');
        } catch (Exception $e) {
            logActivity("Error eliminando cliente: " . $e->getMessage(), 'ERROR');
            redirect('clientes?error=delete_failed');
        }
    }
    
    public function search() {
        if (!isAuthenticated()) {
            jsonResponse(['error' => 'No autorizado'], 401);
        }
        
        $search = $_GET['q'] ?? '';
        
        if (empty($search)) {
            jsonResponse(['clients' => []]);
        }
        
        try {
            $sql = "SELECT id, nombre, tipo_documento, numero_documento, telefono, email 
                    FROM clientes 
                    WHERE (nombre LIKE ? OR numero_documento LIKE ?)
                    AND activo = 1
                    ORDER BY nombre ASC
                    LIMIT 20";
            
            $clients = $this->db->query($sql, ["%{$search}%", "%{$search}%"]);
            jsonResponse(['clients' => $clients]);
            
        } catch (Exception $e) {
            logActivity("Error buscando clientes: " . $e->getMessage(), 'ERROR');
            jsonResponse(['error' => 'Error interno del sistema'], 500);
        }
    }
}
?>

