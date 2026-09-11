<?php
/**
 * Controlador de Productos
 * TECNOXPERT - Sistema de Inventarios y Facturación
 */

require_once __DIR__ . '/../Lib/Database.php';

class ProductoController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Listar productos
     */
    public function index() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $page = $_GET['page'] ?? 1;
        $search = $_GET['search'] ?? '';
        $categoria = $_GET['categoria'] ?? '';
        $estado = $_GET['estado'] ?? '';
        
        $conditions = ['estado' => 'activo'];
        $joins = [
            [
                'type' => 'LEFT',
                'table' => 'categorias',
                'condition' => 'productos.categoria_id = categorias.id'
            ],
            [
                'type' => 'LEFT',
                'table' => 'marcas',
                'condition' => 'productos.marca_id = marcas.id'
            ]
        ];
        
        $select = 'productos.*, categorias.nombre as categoria_nombre, marcas.nombre as marca_nombre';
        $orderBy = 'productos.nombre ASC';
        
        // Aplicar filtros
        if (!empty($search)) {
            $conditions['productos.nombre LIKE'] = "%{$search}%";
        }
        
        if (!empty($categoria)) {
            $conditions['productos.categoria_id'] = $categoria;
        }
        
        if (!empty($estado)) {
            $conditions['productos.estado'] = $estado;
        }
        
        $productos = $this->db->join('productos', $joins, $select, $conditions, $orderBy);
        $categorias = $this->db->findAll('categorias', ['activo' => 1], 'nombre ASC');
        
        return [
            'view' => 'productos/index',
            'data' => [
                'title' => 'Productos - ' . APP_NAME,
                'productos' => $productos,
                'categorias' => $categorias,
                'search' => $search,
                'categoria' => $categoria,
                'estado' => $estado,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    /**
     * Mostrar formulario de creación
     */
    public function create() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $categorias = $this->db->findAll('categorias', ['activo' => 1], 'nombre ASC');
        $marcas = $this->db->findAll('marcas', ['activo' => 1], 'nombre ASC');
        $proveedores = $this->db->findAll('proveedores', ['activo' => 1], 'nombre ASC');
        
        return [
            'view' => 'productos/create',
            'data' => [
                'title' => 'Crear Producto - ' . APP_NAME,
                'categorias' => $categorias,
                'marcas' => $marcas,
                'proveedores' => $proveedores,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    /**
     * Guardar producto
     */
    public function store() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('productos');
        }
        
        $data = [
            'nombre' => sanitizeInput($_POST['nombre'] ?? ''),
            'referencia' => sanitizeInput($_POST['referencia'] ?? ''),
            'codigo_barras' => sanitizeInput($_POST['codigo_barras'] ?? ''),
            'descripcion' => sanitizeInput($_POST['descripcion'] ?? ''),
            'categoria_id' => (int)($_POST['categoria_id'] ?? 0),
            'marca_id' => (int)($_POST['marca_id'] ?? 0),
            'unidad_medida' => sanitizeInput($_POST['unidad_medida'] ?? 'UN'),
            'costo' => (float)($_POST['costo'] ?? 0),
            'precio' => (float)($_POST['precio'] ?? 0),
            'iva_porcentaje' => (float)($_POST['iva_porcentaje'] ?? 19),
            'stock' => (float)($_POST['stock'] ?? 0),
            'stock_minimo' => (float)($_POST['stock_minimo'] ?? 0),
            'proveedor_id' => (int)($_POST['proveedor_id'] ?? 0),
            'ubicacion' => sanitizeInput($_POST['ubicacion'] ?? ''),
            'estado' => sanitizeInput($_POST['estado'] ?? 'activo')
        ];
        
        try {
            $productoId = $this->db->insert('productos', $data);
            
            logActivity("Producto creado: {$data['nombre']}", 'INFO', getCurrentUserId());
            
            redirect('productos?success=created');
            
        } catch (Exception $e) {
            logActivity("Error creando producto: " . $e->getMessage(), 'ERROR');
            redirect('productos/create?error=creation_failed');
        }
    }
    
    /**
     * Mostrar producto
     */
    public function show($id) {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $producto = $this->db->findById('productos', $id);
        
        if (!$producto) {
            redirect('productos?error=not_found');
        }
        
        // Obtener información adicional
        $categoria = $this->db->findById('categorias', $producto['categoria_id']);
        $marca = $this->db->findById('marcas', $producto['marca_id']);
        $proveedor = $this->db->findById('proveedores', $producto['proveedor_id']);
        
        // Obtener movimientos de inventario
        $movimientos = $this->db->query(
            "SELECT * FROM inventario_movimientos WHERE producto_id = ? ORDER BY fecha_movimiento DESC LIMIT 20",
            [$id]
        );
        
        return [
            'view' => 'productos/show',
            'data' => [
                'title' => $producto['nombre'] . ' - ' . APP_NAME,
                'producto' => $producto,
                'categoria' => $categoria,
                'marca' => $marca,
                'proveedor' => $proveedor,
                'movimientos' => $movimientos,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    /**
     * Mostrar formulario de edición
     */
    public function edit($id) {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $producto = $this->db->findById('productos', $id);
        
        if (!$producto) {
            redirect('productos?error=not_found');
        }
        
        $categorias = $this->db->findAll('categorias', ['activo' => 1], 'nombre ASC');
        $marcas = $this->db->findAll('marcas', ['activo' => 1], 'nombre ASC');
        $proveedores = $this->db->findAll('proveedores', ['activo' => 1], 'nombre ASC');
        
        return [
            'view' => 'productos/edit',
            'data' => [
                'title' => 'Editar Producto - ' . APP_NAME,
                'producto' => $producto,
                'categorias' => $categorias,
                'marcas' => $marcas,
                'proveedores' => $proveedores,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    /**
     * Actualizar producto
     */
    public function update($id) {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('productos');
        }
        
        $producto = $this->db->findById('productos', $id);
        
        if (!$producto) {
            redirect('productos?error=not_found');
        }
        
        $data = [
            'nombre' => sanitizeInput($_POST['nombre'] ?? ''),
            'referencia' => sanitizeInput($_POST['referencia'] ?? ''),
            'codigo_barras' => sanitizeInput($_POST['codigo_barras'] ?? ''),
            'descripcion' => sanitizeInput($_POST['descripcion'] ?? ''),
            'categoria_id' => (int)($_POST['categoria_id'] ?? 0),
            'marca_id' => (int)($_POST['marca_id'] ?? 0),
            'unidad_medida' => sanitizeInput($_POST['unidad_medida'] ?? 'UN'),
            'costo' => (float)($_POST['costo'] ?? 0),
            'precio' => (float)($_POST['precio'] ?? 0),
            'iva_porcentaje' => (float)($_POST['iva_porcentaje'] ?? 19),
            'stock' => (float)($_POST['stock'] ?? 0),
            'stock_minimo' => (float)($_POST['stock_minimo'] ?? 0),
            'proveedor_id' => (int)($_POST['proveedor_id'] ?? 0),
            'ubicacion' => sanitizeInput($_POST['ubicacion'] ?? ''),
            'estado' => sanitizeInput($_POST['estado'] ?? 'activo')
        ];
        
        try {
            $this->db->update('productos', $data, 'id = ?', [$id]);
            
            logActivity("Producto actualizado: {$data['nombre']}", 'INFO', getCurrentUserId());
            
            redirect('productos?success=updated');
            
        } catch (Exception $e) {
            logActivity("Error actualizando producto: " . $e->getMessage(), 'ERROR');
            redirect("productos/edit/{$id}?error=update_failed");
        }
    }
    
    /**
     * Eliminar producto
     */
    public function delete($id) {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $producto = $this->db->findById('productos', $id);
        
        if (!$producto) {
            redirect('productos?error=not_found');
        }
        
        try {
            // Eliminación lógica (cambiar estado a inactivo)
            $this->db->update('productos', ['estado' => 'inactivo'], 'id = ?', [$id]);
            
            logActivity("Producto eliminado: {$producto['nombre']}", 'INFO', getCurrentUserId());
            
            redirect('productos?success=deleted');
            
        } catch (Exception $e) {
            logActivity("Error eliminando producto: " . $e->getMessage(), 'ERROR');
            redirect('productos?error=delete_failed');
        }
    }
    
    /**
     * Buscar productos (AJAX)
     */
    public function search() {
        if (!isAuthenticated()) {
            jsonResponse(['error' => 'No autorizado'], 401);
        }
        
        $search = $_GET['q'] ?? '';
        
        if (empty($search)) {
            jsonResponse(['products' => []]);
        }
        
        try {
            $sql = "SELECT id, nombre, referencia, precio, stock, iva_porcentaje 
                    FROM productos 
                    WHERE (nombre LIKE ? OR referencia LIKE ? OR codigo_barras LIKE ?)
                    AND estado = 'activo' AND stock > 0
                    ORDER BY nombre ASC
                    LIMIT 20";
            
            $products = $this->db->query($sql, ["%{$search}%", "%{$search}%", "%{$search}%"]);
            
            jsonResponse(['products' => $products]);
            
        } catch (Exception $e) {
            logActivity("Error buscando productos: " . $e->getMessage(), 'ERROR');
            jsonResponse(['error' => 'Error interno del sistema'], 500);
        }
    }
}
?>

