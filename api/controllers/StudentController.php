<?php
require_once '../includes/ApiResponse.php';
require_once '../models/StudentModel.php';

/**
 * Student Controller for API
 */
class StudentController {
    private $model;
    
    public function __construct($pdo) {
        $this->model = new StudentModel($pdo);
    }
    
    /**
     * Get all students
     */
    public function getStudents() {
        try {
            $students = $this->model->getAllStudentsWithProfile();
            
            if ($students) {
                ApiResponse::success($students, 'Students retrieved successfully');
            } else {
                ApiResponse::success([], 'No students found');
            }
        } catch (Exception $e) {
            ApiResponse::error('Error retrieving students: ' . $e->getMessage());
        }
    }
    
    /**
     * Get student by ID
     */
    public function getStudent($id) {
        try {
            $student = $this->model->getStudentWithProfile($id);
            
            if ($student) {
                ApiResponse::success($student, 'Student retrieved successfully');
            } else {
                ApiResponse::notFound('Student not found');
            }
        } catch (Exception $e) {
            ApiResponse::error('Error retrieving student: ' . $e->getMessage());
        }
    }
}
?>