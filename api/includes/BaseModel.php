<?php
/**
 * Base Model class for API
 */
class BaseModel {
    protected $pdo;
    protected $table;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Find all records
     */
    public function findAll($limit = null, $offset = null) {
        $sql = "SELECT * FROM {$this->table}";
        if ($limit) {
            $sql .= " LIMIT " . (int)$limit;
            if ($offset) {
                $sql .= " OFFSET " . (int)$offset;
            }
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Find record by ID
     */
    public function findById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Find records by conditions
     */
    public function findBy($conditions) {
        $where = [];
        foreach ($conditions as $key => $value) {
            $where[] = "$key = :$key";
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($conditions as $key => $value) {
            $stmt->bindParam(":$key", $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>