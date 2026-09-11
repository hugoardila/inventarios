<?php
/**
 * Modelo de Compras
 * TECNOXPERT - Sistema de Inventarios y Facturación
 */

require_once __DIR__ . '/../Lib/Database.php';

class Compra {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function findAll($conditions = [], $orderBy = '', $limit = '') {
        return $this->db->findAll('compras', $conditions, $orderBy, $limit);
    }
    
    public function findById($id) {
        return $this->db->findById('compras', $id);
    }
    
    public function create($data) {
        return $this->db->insert('compras', $data);
    }
    
    public function update($id, $data) {
        return $this->db->update('compras', $data, 'id = ?', [$id]);
    }
    
    public function getWithDetails($id) {
        $joins = [
            [
                'type' => 'LEFT',
                'table' => 'proveedores',
                'condition' => 'compras.proveedor_id = proveedores.id'
            ],
            [
                'type' => 'LEFT',
                'table' => 'usuarios',
                'condition' => 'compras.usuario_id = usuarios.id'
            ]
        ];
        
        $select = 'compras.*, proveedores.nombre as proveedor_nombre, 
                   proveedores.nit, usuarios.nombre as comprador_nombre';
        
        $result = $this->db->join('compras', $joins, $select, ['compras.id' => $id]);
        return $result[0] ?? null;
    }
    
    public function getItems($compraId) {
        $sql = "SELECT ci.*, p.nombre as producto_nombre, p.referencia 
                FROM compra_items ci 
                JOIN productos p ON ci.producto_id = p.id 
                WHERE ci.compra_id = ?";
        
        return $this->db->query($sql, [$compraId]);
    }
    
    public function getPurchasesByPeriod($startDate, $endDate) {
        $sql = "SELECT DATE(fecha_compra) as fecha,
                       COUNT(*) as total_compras,
                       SUM(total) as monto_total
                FROM compras
                WHERE estado = 'recibida'
                AND fecha_compra BETWEEN ? AND ?
                GROUP BY DATE(fecha_compra)
                ORDER BY fecha ASC";
        
        return $this->db->query($sql, [$startDate, $endDate]);
    }
    
    public function getPurchaseStats($days = 30) {
        $sql = "SELECT 
                    COUNT(*) as total_compras,
                    SUM(total) as monto_total,
                    AVG(total) as promedio_compra,
                    SUM(iva) as total_iva,
                    SUM(descuento) as total_descuentos
                FROM compras
                WHERE estado = 'recibida'
                AND fecha_compra >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)";
        
        $result = $this->db->query($sql, [$days]);
        return $result[0] ?? null;
    }
}
?>

