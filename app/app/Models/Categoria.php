<?php
class Categoria {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function findAll($conditions = [], $orderBy = '', $limit = '') {
        return $this->db->findAll('categorias', $conditions, $orderBy, $limit);
    }
    
    public function findById($id) {
        return $this->db->findById('categorias', $id);
    }
    
    public function create($data) {
        return $this->db->insert('categorias', $data);
    }
    
    public function update($id, $data) {
        return $this->db->update('categorias', $data, 'id = ?', [$id]);
    }
    
    public function delete($id) {
        return $this->db->delete('categorias', 'id = ?', [$id]);
    }
}