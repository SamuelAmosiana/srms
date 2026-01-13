<?php
require_once '../config.php';
require_once 'BaseModel.php';

/**
 * Result Model class for API
 */
class ResultModel extends BaseModel {
    protected $table = 'results';
    
    public function __construct($pdo) {
        parent::__construct($pdo);
    }
    
    /**
     * Get student results with course information
     */
    public function getStudentResults($studentId) {
        $sql = "SELECT r.*, c.name as course_name, p.name as programme_name, 
                       su.username as student_number, sp.full_name as student_name
                FROM results r
                LEFT JOIN course c ON r.course_id = c.id
                LEFT JOIN programme p ON c.programme_id = p.id
                LEFT JOIN student_users su ON r.student_id = su.id
                LEFT JOIN student_profile sp ON su.id = sp.user_id
                WHERE r.student_id = :student_id
                ORDER BY r.exam_date DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all results with related information
     */
    public function getAllResults() {
        $sql = "SELECT r.*, c.name as course_name, p.name as programme_name,
                       su.username as student_number, sp.full_name as student_name
                FROM results r
                LEFT JOIN course c ON r.course_id = c.id
                LEFT JOIN programme p ON c.programme_id = p.id
                LEFT JOIN student_users su ON r.student_id = su.id
                LEFT JOIN student_profile sp ON su.id = sp.user_id
                ORDER BY r.exam_date DESC, c.name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get results by course
     */
    public function getResultsByCourse($courseId) {
        $sql = "SELECT r.*, c.name as course_name, p.name as programme_name,
                       su.username as student_number, sp.full_name as student_name
                FROM results r
                LEFT JOIN course c ON r.course_id = c.id
                LEFT JOIN programme p ON c.programme_id = p.id
                LEFT JOIN student_users su ON r.student_id = su.id
                LEFT JOIN student_profile sp ON su.id = sp.user_id
                WHERE r.course_id = :course_id
                ORDER BY r.total_score DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':course_id', $courseId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>