<?php
/**
 * Modelo de Clientes
 * TECNOXPERT - Sistema de Inventarios y Facturación
 */

require_once __DIR__ . '/../Lib/Database.php';

class Cliente {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function findAll($conditions = [], $orderBy = '', $limit = '') {
        return $this->db->findAll('clientes', $conditions, $orderBy, $limit);
    }
    
    public function findById($id) {
        return $this->db->findById('clientes', $id);
    }
    
    public function create($data) {
        return $this->db->insert('clientes', $data);
    }
    
    public function update($id, $data) {
        return $this->db->update('clientes', $data, 'id = ?', [$id]);
    }
    
    public function delete($id) {
        return $this->db->update('clientes', ['estado' => 0], 'id = ?', [$id]);
    }
    
    public function search($query) {
        $sql = "SELECT id, nombre, tipo_documento, numero_documento, telefono, email 
                FROM clientes 
                WHERE (nombre LIKE ? OR numero_documento LIKE ?)
                AND estado = 1
                ORDER BY nombre ASC
                LIMIT 20";
        
        return $this->db->query($sql, ["%{$query}%", "%{$query}%"]);
    }
    
    public function getTopCustomers($limit = 10, $days = 30) {
        $sql = "SELECT c.id, c.nombre, c.tipo_documento, c.numero_documento,
                       COUNT(v.id) as total_ventas,
                       SUM(v.total) as monto_total
                FROM clientes c
                LEFT JOIN ventas v ON c.id = v.cliente_id
                WHERE v.estado = 'pagada'
                AND v.fecha_venta >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
                GROUP BY c.id, c.nombre, c.tipo_documento, c.numero_documento
                ORDER BY monto_total DESC
                LIMIT ?";
        
        return $this->db->query($sql, [$days, $limit]);
    }
}
?>
