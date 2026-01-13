<?php
require_once '../includes/ApiResponse.php';
require_once '../models/FeeModel.php';

/**
 * Fee Controller for API
 */
class FeeController {
    private $model;
    
    public function __construct($pdo) {
        $this->model = new FeeModel($pdo);
    }
    
    /**
     * Get all programme fees
     */
    public function getFees() {
        try {
            $fees = $this->model->getAllProgrammeFees();
            
            if ($fees) {
                ApiResponse::success($fees, 'Fees retrieved successfully');
            } else {
                ApiResponse::success([], 'No fees found');
            }
        } catch (Exception $e) {
            ApiResponse::error('Error retrieving fees: ' . $e->getMessage());
        }
    }
    
    /**
     * Get student fees by student ID
     */
    public function getStudentFees($studentId) {
        try {
            $fees = $this->model->getStudentFees($studentId);
            
            if ($fees) {
                ApiResponse::success($fees, 'Student fees retrieved successfully');
            } else {
                ApiResponse::success([], 'No fees found for this student');
            }
        } catch (Exception $e) {
            ApiResponse::error('Error retrieving student fees: ' . $e->getMessage());
        }
    }
}
?>