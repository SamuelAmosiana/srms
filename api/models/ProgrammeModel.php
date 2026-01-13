<?php
require_once '../config.php';
require_once 'BaseModel.php';

/**
 * Programme Model class for API
 */
class ProgrammeModel extends BaseModel {
    protected $table = 'programme';
    
    public function __construct($pdo) {
        parent::__construct($pdo);
    }
    
    /**
     * Get programme with department information
     */
    public function getProgrammeWithDepartment($id) {
        $sql = "SELECT p.*, d.name as department_name
                FROM programme p
                LEFT JOIN department d ON p.department_id = d.id
                WHERE p.id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all programmes with department information
     */
    public function getAllProgrammesWithDepartment() {
        $sql = "SELECT p.*, d.name as department_name
                FROM programme p
                LEFT JOIN department d ON p.department_id = d.id
                ORDER BY p.name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>