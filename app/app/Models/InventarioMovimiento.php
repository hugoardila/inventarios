<?php
class InventarioMovimiento {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function findAll($conditions = [], $orderBy = '', $limit = '') {
        return $this->db->findAll('inventario_movimientos', $conditions, $orderBy, $limit);
    }
    
    public function findById($id) {
        return $this->db->findById('inventario_movimientos', $id);
    }
    
    public function create($data) {
        return $this->db->insert('inventario_movimientos', $data);
    }
    
    public function update($id, $data) {
        return $this->db->update('inventario_movimientos', $data, 'id = ?', [$id]);
    }
    
    public function delete($id) {
        return $this->db->delete('inventario_movimientos', 'id = ?', [$id]);
    }
}