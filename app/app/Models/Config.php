<?php
class Config {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function get($key, $default = null) {
        $result = $this->db->findAll('config', ['clave' => $key]);
        return $result ? $result[0]['valor'] : $default;
    }
    
    public function set($key, $value) {
        $existing = $this->db->findAll('config', ['clave' => $key]);
        if ($existing) {
            return $this->db->update('config', ['valor' => $value], 'clave = ?', [$key]);
        } else {
            return $this->db->insert('config', ['clave' => $key, 'valor' => $value]);
        }
    }
    
    public function delete($key) {
        return $this->db->delete('config', 'clave = ?', [$key]);
    }
}