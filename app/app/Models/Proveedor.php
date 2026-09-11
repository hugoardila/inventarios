<?php
/**
 * Modelo de Proveedores
 * TECNOXPERT - Sistema de Inventarios y Facturación
 */

require_once __DIR__ . '/../Lib/Database.php';

class Proveedor {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function findAll($conditions = [], $orderBy = '', $limit = '') {
        return $this->db->findAll('proveedores', $conditions, $orderBy, $limit);
    }
    
    public function findById($id) {
        return $this->db->findById('proveedores', $id);
    }
    
    public function create($data) {
        return $this->db->insert('proveedores', $data);
    }
    
    public function update($id, $data) {
        return $this->db->update('proveedores', $data, 'id = ?', [$id]);
    }
    
    public function delete($id) {
        return $this->db->update('proveedores', ['estado' => 0], 'id = ?', [$id]);
    }
    
    public function getTopSuppliers($limit = 10, $days = 30) {
        $sql = "SELECT p.id, p.nombre, p.nit,
                       COUNT(c.id) as total_compras,
                       SUM(c.total) as monto_total
                FROM proveedores p
                LEFT JOIN compras c ON p.id = c.proveedor_id
                WHERE c.estado = 'recibida'
                AND c.fecha_compra >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
                GROUP BY p.id, p.nombre, p.nit
                ORDER BY monto_total DESC
                LIMIT ?";
        
        return $this->db->query($sql, [$days, $limit]);
    }
}
?>
