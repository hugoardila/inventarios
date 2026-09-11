<?php
/**
 * Modelo de Productos
 * TECNOXPERT - Sistema de Inventarios y Facturación
 */

require_once __DIR__ . '/../Lib/Database.php';

class Producto {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function findAll($conditions = [], $orderBy = '', $limit = '') {
        return $this->db->findAll('productos', $conditions, $orderBy, $limit);
    }
    
    public function findById($id) {
        return $this->db->findById('productos', $id);
    }
    
    public function create($data) {
        return $this->db->insert('productos', $data);
    }
    
    public function update($id, $data) {
        return $this->db->update('productos', $data, 'id = ?', [$id]);
    }
    
    public function delete($id) {
        return $this->db->update('productos', ['estado' => 'inactivo'], 'id = ?', [$id]);
    }
    
    public function getWithDetails($id) {
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
            ],
            [
                'type' => 'LEFT',
                'table' => 'proveedores',
                'condition' => 'productos.proveedor_id = proveedores.id'
            ]
        ];
        
        $select = 'productos.*, categorias.nombre as categoria_nombre, 
                   marcas.nombre as marca_nombre, proveedores.nombre as proveedor_nombre';
        
        $result = $this->db->join('productos', $joins, $select, ['productos.id' => $id]);
        return $result[0] ?? null;
    }
    
    public function search($query) {
        $sql = "SELECT id, nombre, referencia, codigo_barras, precio, stock, iva_porcentaje 
                FROM productos 
                WHERE (nombre LIKE ? OR referencia LIKE ? OR codigo_barras LIKE ?)
                AND estado = 'activo' AND stock > 0
                ORDER BY nombre ASC
                LIMIT 20";
        
        return $this->db->query($sql, ["%{$query}%", "%{$query}%", "%{$query}%"]);
    }
    
    public function getLowStock() {
        $sql = "SELECT p.*, c.nombre as categoria_nombre
                FROM productos p
                LEFT JOIN categorias c ON p.categoria_id = c.id
                WHERE p.stock <= p.stock_minimo 
                AND p.estado = 'activo'
                ORDER BY p.stock ASC";
        
        return $this->db->query($sql);
    }
    
    public function updateStock($id, $quantity, $type = 'entrada') {
        $producto = $this->findById($id);
        
        if (!$producto) {
            throw new Exception("Producto no encontrado");
        }
        
        $newStock = $type === 'entrada' ? 
            $producto['stock'] + $quantity : 
            $producto['stock'] - $quantity;
        
        if ($newStock < 0) {
            throw new Exception("Stock insuficiente");
        }
        
        return $this->update($id, ['stock' => $newStock]);
    }
    
    public function getTopSellers($limit = 10, $days = 30) {
        $sql = "SELECT p.id, p.nombre, p.referencia,
                       SUM(vi.cantidad) as total_vendido,
                       SUM(vi.total) as monto_total
                FROM venta_items vi
                JOIN productos p ON vi.producto_id = p.id
                JOIN ventas v ON vi.venta_id = v.id
                WHERE v.estado = 'pagada'
                AND v.fecha_venta >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
                GROUP BY p.id, p.nombre, p.referencia
                ORDER BY total_vendido DESC
                LIMIT ?";
        
        return $this->db->query($sql, [$days, $limit]);
    }
    
    public function getInventoryValue() {
        $sql = "SELECT 
                    SUM(stock * costo) as valor_costo,
                    SUM(stock * precio) as valor_venta,
                    COUNT(*) as total_productos
                FROM productos 
                WHERE estado = 'activo'";
        
        $result = $this->db->query($sql);
        return $result[0] ?? null;
    }
}
?>

