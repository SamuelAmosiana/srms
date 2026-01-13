<?php
require_once '../config.php';
require_once 'BaseModel.php';

/**
 * Fee Model class for API
 */
class FeeModel extends BaseModel {
    protected $table = 'programme_fees';
    
    public function __construct($pdo) {
        parent::__construct($pdo);
    }
    
    /**
     * Get programme fees with programme information
     */
    public function getProgrammeFees($programmeId) {
        $sql = "SELECT pf.*, p.name as programme_name, p.duration, p.duration_unit
                FROM programme_fees pf
                LEFT JOIN programme p ON pf.programme_id = p.id
                WHERE pf.programme_id = :programme_id AND (pf.is_active = 1 OR pf.is_active IS NULL)
                ORDER BY pf.fee_amount DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':programme_id', $programmeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all programme fees with programme information
     */
    public function getAllProgrammeFees() {
        $sql = "SELECT pf.*, p.name as programme_name, p.duration, p.duration_unit
                FROM programme_fees pf
                LEFT JOIN programme p ON pf.programme_id = p.id
                WHERE (pf.is_active = 1 OR pf.is_active IS NULL)
                ORDER BY p.name, pf.fee_amount DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get student fees by student ID
     */
    public function getStudentFees($studentId) {
        $sql = "SELECT pf.*, p.name as programme_name, p.duration, p.duration_unit,
                       su.username as student_number, sp.full_name as student_name
                FROM programme_fees pf
                LEFT JOIN programme p ON pf.programme_id = p.id
                LEFT JOIN student_users su ON su.programme_id = p.id  -- This might need adjustment based on actual schema
                LEFT JOIN student_profile sp ON su.id = sp.user_id
                WHERE su.id = :student_id AND (pf.is_active = 1 OR pf.is_active IS NULL)
                ORDER BY pf.fee_amount DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get total fees for a programme
     */
    public function getTotalProgrammeFees($programmeId) {
        $sql = "SELECT SUM(pf.fee_amount) as total_fees
                FROM programme_fees pf
                WHERE pf.programme_id = :programme_id 
                AND (pf.is_active = 1 OR pf.is_active IS NULL)
                AND pf.fee_type = 'per_term'";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':programme_id', $programmeId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['total_fees'] : 0;
    }
}
?>