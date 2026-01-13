<?php
require_once '../config.php';
require_once 'BaseModel.php';

/**
 * Course Model class for API
 */
class CourseModel extends BaseModel {
    protected $table = 'course';
    
    public function __construct($pdo) {
        parent::__construct($pdo);
    }
    
    /**
     * Get course with programme information
     */
    public function getCourseWithProgramme($id) {
        $sql = "SELECT c.*, p.name as programme_name
                FROM course c
                LEFT JOIN programme p ON c.programme_id = p.id
                WHERE c.id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all courses with programme information
     */
    public function getAllCoursesWithProgramme() {
        $sql = "SELECT c.*, p.name as programme_name
                FROM course c
                LEFT JOIN programme p ON c.programme_id = p.id
                ORDER BY c.name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get courses by programme
     */
    public function getCoursesByProgramme($programmeId) {
        $sql = "SELECT c.*, p.name as programme_name
                FROM course c
                LEFT JOIN programme p ON c.programme_id = p.id
                WHERE c.programme_id = :programme_id
                ORDER BY c.name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':programme_id', $programmeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>