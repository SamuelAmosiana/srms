<?php
require_once '../includes/ApiResponse.php';
require_once '../models/CourseModel.php';

/**
 * Course Controller for API
 */
class CourseController {
    private $model;
    
    public function __construct($pdo) {
        $this->model = new CourseModel($pdo);
    }
    
    /**
     * Get all courses
     */
    public function getCourses() {
        try {
            $courses = $this->model->getAllCoursesWithProgramme();
            
            if ($courses) {
                ApiResponse::success($courses, 'Courses retrieved successfully');
            } else {
                ApiResponse::success([], 'No courses found');
            }
        } catch (Exception $e) {
            ApiResponse::error('Error retrieving courses: ' . $e->getMessage());
        }
    }
    
    /**
     * Get course by ID
     */
    public function getCourse($id) {
        try {
            $course = $this->model->getCourseWithProgramme($id);
            
            if ($course) {
                ApiResponse::success($course, 'Course retrieved successfully');
            } else {
                ApiResponse::notFound('Course not found');
            }
        } catch (Exception $e) {
            ApiResponse::error('Error retrieving course: ' . $e->getMessage());
        }
    }
}
?>