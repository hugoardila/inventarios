<?php
/**
 * Clase Database - Manejo de conexiones y consultas a la base de datos
 * TECNOXPERT - Sistema de Inventarios y Facturación
 */

require_once __DIR__ . '/../../config/config.php';

class Database {
    private static $instance = null;
    private $connection;
    private $statement;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            logActivity("Error de conexión a la base de datos: " . $e->getMessage(), 'ERROR');
            throw new Exception("Error de conexión a la base de datos");
        }
    }
    
    /**
     * Obtener instancia única (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Preparar una consulta SQL
     */
    public function prepare($sql) {
        try {
            $this->statement = $this->connection->prepare($sql);
            return $this;
        } catch (PDOException $e) {
            logActivity("Error preparando consulta: " . $e->getMessage(), 'ERROR');
            throw new Exception("Error preparando consulta SQL");
        }
    }
    
    /**
     * Ejecutar una consulta preparada
     */
    public function execute($params = []) {
        try {
            $this->statement->execute($params);
            return $this;
        } catch (PDOException $e) {
            logActivity("Error ejecutando consulta: " . $e->getMessage(), 'ERROR');
            throw new Exception("Error ejecutando consulta SQL");
        }
    }
    
    /**
     * Obtener un solo registro
     */
    public function fetch() {
        return $this->statement->fetch();
    }
    
    /**
     * Obtener todos los registros
     */
    public function fetchAll() {
        return $this->statement->fetchAll();
    }
    
    /**
     * Obtener el número de filas afectadas
     */
    public function rowCount() {
        return $this->statement->rowCount();
    }
    
    /**
     * Obtener el último ID insertado
     */
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    /**
     * Iniciar una transacción
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Confirmar una transacción
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Revertir una transacción
     */
    public function rollback() {
        return $this->connection->rollback();
    }
    
    /**
     * Verificar si hay una transacción activa
     */
    public function inTransaction() {
        return $this->connection->inTransaction();
    }
    
    /**
     * Ejecutar una consulta simple (SELECT)
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            logActivity("Error en consulta: " . $e->getMessage(), 'ERROR');
            throw new Exception("Error ejecutando consulta");
        }
    }
    
    /**
     * Ejecutar una consulta de inserción, actualización o eliminación
     */
    public function executeQuery($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            logActivity("Error ejecutando consulta: " . $e->getMessage(), 'ERROR');
            throw new Exception("Error ejecutando consulta");
        }
    }
    
    /**
     * Obtener un registro por ID
     */
    public function findById($table, $id, $idField = 'id') {
        $sql = "SELECT * FROM {$table} WHERE {$idField} = ?";
        $result = $this->query($sql, [$id]);
        return $result ? $result[0] : null;
    }
    
    /**
     * Obtener todos los registros de una tabla
     */
    public function findAll($table, $conditions = [], $orderBy = '', $limit = '') {
        $sql = "SELECT * FROM {$table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "{$field} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        return $this->query($sql, $params);
    }
    
    /**
     * Insertar un registro
     */
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $this->executeQuery($sql, array_values($data));
        return $this->lastInsertId();
    }
    
    /**
     * Actualizar un registro
     */
    public function update($table, $data, $where, $whereParams = []) {
        $fields = array_keys($data);
        $setClause = implode(' = ?, ', $fields) . ' = ?';
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        
        $params = array_merge(array_values($data), $whereParams);
        return $this->executeQuery($sql, $params);
    }
    
    /**
     * Eliminar un registro
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->executeQuery($sql, $params);
    }
    
    /**
     * Contar registros
     */
    public function count($table, $conditions = []) {
        $sql = "SELECT COUNT(*) as total FROM {$table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "{$field} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $result = $this->query($sql, $params);
        return $result[0]['total'] ?? 0;
    }
    
    /**
     * Verificar si existe un registro
     */
    public function exists($table, $conditions) {
        return $this->count($table, $conditions) > 0;
    }
    
    /**
     * Obtener registros con paginación
     */
    public function paginate($table, $page = 1, $perPage = ITEMS_PER_PAGE, $conditions = [], $orderBy = '') {
        $offset = ($page - 1) * $perPage;
        
        // Obtener total de registros
        $total = $this->count($table, $conditions);
        
        // Obtener registros de la página actual
        $sql = "SELECT * FROM {$table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "{$field} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";
        
        $data = $this->query($sql, $params);
        
        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total)
        ];
    }
    
    /**
     * Ejecutar una consulta con JOIN
     */
    public function join($table, $joins, $select = '*', $conditions = [], $orderBy = '', $limit = '') {
        $sql = "SELECT {$select} FROM {$table}";
        
        foreach ($joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
        }
        
        $params = [];
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "{$field} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        return $this->query($sql, $params);
    }
    
    /**
     * Ejecutar una consulta raw (sin preparar)
     */
    public function raw($sql) {
        try {
            return $this->connection->query($sql)->fetchAll();
        } catch (PDOException $e) {
            logActivity("Error en consulta raw: " . $e->getMessage(), 'ERROR');
            throw new Exception("Error ejecutando consulta raw");
        }
    }
    
    /**
     * Obtener información de la tabla
     */
    public function getTableInfo($table) {
        $sql = "DESCRIBE {$table}";
        return $this->query($sql);
    }
    
    /**
     * Verificar si una tabla existe
     */
    public function tableExists($table) {
        $sql = "SHOW TABLES LIKE ?";
        $result = $this->query($sql, [$table]);
        return !empty($result);
    }
    
    /**
     * Obtener el nombre de las columnas de una tabla
     */
    public function getColumns($table) {
        $sql = "SHOW COLUMNS FROM {$table}";
        $result = $this->query($sql);
        return array_column($result, 'Field');
    }
    
    /**
     * Escapar una cadena para uso en consultas
     */
    public function escape($string) {
        return $this->connection->quote($string);
    }
    
    /**
     * Cerrar la conexión
     */
    public function close() {
        $this->connection = null;
        self::$instance = null;
    }
    
    /**
     * Destructor
     */
    public function __destruct() {
        $this->close();
    }
    
    /**
     * Prevenir clonación
     */
    private function __clone() {}
    
    /**
     * Prevenir deserialización
     */
    public function __wakeup() {}
}
?>
