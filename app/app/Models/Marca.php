<?php
class Marca {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function findAll($conditions = [], $orderBy = '', $limit = '') {
        return $this->db->findAll('marcas', $conditions, $orderBy, $limit);
    }
    
    public function findById($id) {
        return $this->db->findById('marcas', $id);
    }
    
    public function create($data) {
        return $this->db->insert('marcas', $data);
    }
    
    public function update($id, $data) {
        return $this->db->update('marcas', $data, 'id = ?', [$id]);
    }
    
    public function delete($id) {
        return $this->db->delete('marcas', 'id = ?', [$id]);
    }
}