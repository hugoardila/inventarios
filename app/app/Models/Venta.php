<?php
/**
 * Modelo de Ventas
 * TECNOXPERT - Sistema de Inventarios y Facturación
 */

require_once __DIR__ . '/../Lib/Database.php';

class Venta {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function findAll($conditions = [], $orderBy = '', $limit = '') {
        return $this->db->findAll('ventas', $conditions, $orderBy, $limit);
    }
    
    public function findById($id) {
        return $this->db->findById('ventas', $id);
    }
    
    public function create($data) {
        return $this->db->insert('ventas', $data);
    }
    
    public function update($id, $data) {
        return $this->db->update('ventas', $data, 'id = ?', [$id]);
    }
    
    public function getWithDetails($id) {
        $joins = [
            [
                'type' => 'LEFT',
                'table' => 'clientes',
                'condition' => 'ventas.cliente_id = clientes.id'
            ],
            [
                'type' => 'LEFT',
                'table' => 'usuarios',
                'condition' => 'ventas.usuario_id = usuarios.id'
            ]
        ];
        
        $select = 'ventas.*, clientes.nombre as cliente_nombre, 
                   clientes.tipo_documento, clientes.numero_documento,
                   usuarios.nombre as vendedor_nombre';
        
        $result = $this->db->join('ventas', $joins, $select, ['ventas.id' => $id]);
        return $result[0] ?? null;
    }
    
    public function getItems($ventaId) {
        $sql = "SELECT vi.*, p.nombre as producto_nombre, p.referencia 
                FROM venta_items vi 
                JOIN productos p ON vi.producto_id = p.id 
                WHERE vi.venta_id = ?";
        
        return $this->db->query($sql, [$ventaId]);
    }
    
    public function getSalesByPeriod($startDate, $endDate) {
        $sql = "SELECT DATE(fecha_venta) as fecha,
                       COUNT(*) as total_ventas,
                       SUM(total) as monto_total
                FROM ventas
                WHERE estado = 'pagada'
                AND fecha_venta BETWEEN ? AND ?
                GROUP BY DATE(fecha_venta)
                ORDER BY fecha ASC";
        
        return $this->db->query($sql, [$startDate, $endDate]);
    }
    
    public function getSalesStats($days = 30) {
        $sql = "SELECT 
                    COUNT(*) as total_ventas,
                    SUM(total) as monto_total,
                    AVG(total) as promedio_venta,
                    SUM(iva) as total_iva,
                    SUM(descuento) as total_descuentos
                FROM ventas
                WHERE estado = 'pagada'
                AND fecha_venta >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)";
        
        $result = $this->db->query($sql, [$days]);
        return $result[0] ?? null;
    }
}
?>

