<?php
require_once '../includes/ApiResponse.php';
require_once '../models/ProgrammeModel.php';

/**
 * Programme Controller for API
 */
class ProgrammeController {
    private $model;
    
    public function __construct($pdo) {
        $this->model = new ProgrammeModel($pdo);
    }
    
    /**
     * Get all programmes
     */
    public function getProgrammes() {
        try {
            $programmes = $this->model->getAllProgrammesWithDepartment();
            
            if ($programmes) {
                ApiResponse::success($programmes, 'Programmes retrieved successfully');
            } else {
                ApiResponse::success([], 'No programmes found');
            }
        } catch (Exception $e) {
            ApiResponse::error('Error retrieving programmes: ' . $e->getMessage());
        }
    }
    
    /**
     * Get programme by ID
     */
    public function getProgramme($id) {
        try {
            $programme = $this->model->getProgrammeWithDepartment($id);
            
            if ($programme) {
                ApiResponse::success($programme, 'Programme retrieved successfully');
            } else {
                ApiResponse::notFound('Programme not found');
            }
        } catch (Exception $e) {
            ApiResponse::error('Error retrieving programme: ' . $e->getMessage());
        }
    }
}
?>