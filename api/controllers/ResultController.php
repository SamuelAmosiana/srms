<?php
require_once '../includes/ApiResponse.php';
require_once '../models/ResultModel.php';

/**
 * Result Controller for API
 */
class ResultController {
    private $model;
    
    public function __construct($pdo) {
        $this->model = new ResultModel($pdo);
    }
    
    /**
     * Get all results
     */
    public function getResults() {
        try {
            $results = $this->model->getAllResults();
            
            if ($results) {
                ApiResponse::success($results, 'Results retrieved successfully');
            } else {
                ApiResponse::success([], 'No results found');
            }
        } catch (Exception $e) {
            ApiResponse::error('Error retrieving results: ' . $e->getMessage());
        }
    }
    
    /**
     * Get student results by student ID
     */
    public function getStudentResults($studentId) {
        try {
            $results = $this->model->getStudentResults($studentId);
            
            if ($results) {
                ApiResponse::success($results, 'Student results retrieved successfully');
            } else {
                ApiResponse::success([], 'No results found for this student');
            }
        } catch (Exception $e) {
            ApiResponse::error('Error retrieving student results: ' . $e->getMessage());
        }
    }
}
?>