<?php
require_once '../config.php';
require_once 'BaseModel.php';

/**
 * Student Model class for API
 */
class StudentModel extends BaseModel {
    protected $table = 'student_users';
    
    public function __construct($pdo) {
        parent::__construct($pdo);
    }
    
    /**
     * Get student with profile information
     */
    public function getStudentWithProfile($id) {
        $sql = "SELECT su.*, sp.* 
                FROM student_users su
                LEFT JOIN student_profile sp ON su.id = sp.user_id
                WHERE su.id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all students with profile information
     */
    public function getAllStudentsWithProfile($limit = null, $offset = null) {
        $sql = "SELECT su.*, sp.* 
                FROM student_users su
                LEFT JOIN student_profile sp ON su.id = sp.user_id";
        
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
     * Get student by student number
     */
    public function getByStudentNumber($studentNumber) {
        $sql = "SELECT su.*, sp.* 
                FROM student_users su
                LEFT JOIN student_profile sp ON su.id = sp.user_id
                WHERE su.username = :student_number OR sp.student_number = :student_number";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':student_number', $studentNumber);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>