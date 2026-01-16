<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

// Load FPDF
require_once __DIR__ . '/../lib/fpdf/fpdf.php';

/**
 * Function to calculate grade based on total score and grading scale
 */
function calculateGrade($total_score, $grading_scale) {
    foreach ($grading_scale as $scale) {
        if ($total_score >= $scale['min_score'] && $total_score <= $scale['max_score']) {
            return $scale['grade'];
        }
    }
    return 'N/A'; // Return N/A if no matching grade found
}

/**
 * Function to get grade points from grading scale
 */
function getGradePoints($grade, $grading_scale) {
    foreach ($grading_scale as $scale) {
        if (strtoupper($scale['grade']) === strtoupper($grade)) {
            return $scale['points'];
        }
    }
    // Fallback for hardcoded grades if not found in scale
    switch (strtoupper($grade)) {
        case 'A': return 4;
        case 'B+': return 3.5;
        case 'B': return 3;
        case 'C': return 2;
        case 'D': return 1;
        case 'F': return 0;
        default: return 0;
    }
}

/**
 * Function to add ordinal suffix to numbers
 */
function ordinal($number) {
    $ends = array('th','st','nd','rd','th','th','th','th','th','th');
    if ((($number % 100) >= 11) && (($number%100) <= 13))
        return 'th';
    else
        return $ends[$number % 10];
}

/**
 * Generate student results PDF using FPDF
 * 
 * @param int $user_id The student's user ID
 * @param PDO $pdo Database connection
 * @return string Path to the generated PDF
 */
function generateResultsPDF($user_id, $pdo) {
    // Get student profile
    $stmt = $pdo->prepare("SELECT sp.full_name, sp.student_number as student_id, sp.programme_id, sp.gender FROM student_profile sp WHERE sp.user_id = ?");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch();

    // Get programme name
    $stmt = $pdo->prepare("SELECT name FROM programme WHERE id = ?");
    $stmt->execute([$student['programme_id']]);
    $programme = $stmt->fetch()['name'] ?? 'N/A';

    // Fetch grading scale for grade calculation
    $grading_scale = [];
    $stmt = $pdo->query("SELECT min_score, max_score, grade, points FROM grading_scale ORDER BY min_score DESC");
    $grading_scale = $stmt->fetchAll();

    // Fetch all results grouped by academic year with course credits and admin comments
    $results_by_year = [];
    $stmt = $pdo->prepare("
        SELECT r.ca_score, r.exam_score, r.grade, r.admin_comment,
               c.code as course_code, c.name as course_name, c.credits,
               ce.academic_year
        FROM results r
        JOIN course_enrollment ce ON r.enrollment_id = ce.id
        JOIN course c ON ce.course_id = c.id
        WHERE ce.student_user_id = ?
        ORDER BY ce.academic_year DESC, c.code ASC
    ");
    $stmt->execute([$user_id]);
    $results = $stmt->fetchAll();

    foreach ($results as $result) {
        $year = $result['academic_year'];
        if (!isset($results_by_year[$year])) {
            $results_by_year[$year] = [];
        }
        
        // Calculate total score and grade if not already present
        $ca_score = $result['ca_score'] ?? 0;
        $exam_score = $result['exam_score'] ?? 0;
        $total_score = $ca_score + $exam_score;
        
        // Always calculate grade based on current scores to reflect grading scale
        $calculated_grade = calculateGrade($total_score, $grading_scale);
        $result['grade'] = $calculated_grade;
        
        // Store the total score for potential use in grade calculation
        $result['total_score'] = $total_score;
        
        $results_by_year[$year][] = $result;
    }

    // Fetch academic year comments added by admins
    $academic_year_comments = [];
    $stmt = $pdo->prepare("
        SELECT academic_year, comment 
        FROM academic_year_comments 
        WHERE student_user_id = ?
    ");
    $stmt->execute([$user_id]);
    $year_comments = $stmt->fetchAll();
    foreach ($year_comments as $comment) {
        $academic_year_comments[$comment['academic_year']] = $comment['comment'];
    }

    // For each year, compute GPA, total credits, failed courses, comment
    $year_data = [];
    $gpa_data = [];
    foreach ($results_by_year as $year => $courses) {
        $total_courses = count($courses);
        $failed = 0;
        $total_grade_points = 0;
        $total_credits = 0;
        
        foreach ($courses as $course) {
            // Get grade points from grading scale
            $grade_points = getGradePoints($course['grade'], $grading_scale);
            $credits = $course['credits'] ?? 0;
            $total_grade_points += $grade_points * $credits;
            $total_credits += $credits;
            
            if ($grade_points == 0) { // Assume fail for 0 grade points
                $failed++;
            }
        }
        
        // Calculate GPA using weighted average: (Sum of credits * grade points) / Sum of credits
        $gpa = $total_credits > 0 ? round($total_grade_points / $total_credits, 2) : 0;

        // Get admin comment for this academic year, fallback to system-generated comment
        $admin_comment = $academic_year_comments[$year] ?? '';
        
        if (!empty($admin_comment)) {
            $comment = $admin_comment;
        } else {
            if ($failed == 0) {
                $comment = 'CLEAR PASS';
            } elseif ($failed / $total_courses >= 0.5) {
                $comment = 'REPEAT YEAR';
            } else {
                $comment = 'REPEAT COURSE(S)';
            }
        }
        
        // For students with no credits, set GPA to 0
        if ($total_credits == 0) {
            $gpa = 0;
        }

        $year_data[$year] = [
            'gpa' => $gpa,
            'credits' => $total_credits,
            'comment' => $comment,
            'courses' => $courses
        ];
        
        $gpa_data[] = [
            'year' => $year,
            'credits' => $total_credits,
            'gpa' => $gpa
        ];
    }

    // Create PDF using FPDF
    $pdf = new FPDF('L', 'mm', 'A4'); // Landscape orientation, millimeters, A4 size
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('Arial', 'B', 16);
    
    // College Header
    $pdf->Cell(0, 10, 'LUSAKA SOUTH COLLEGE', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, 'Academic Results Transcript', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Student Information
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Student Information', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    
    $pdf->Cell(45, 6, 'Computer Number:', 1);
    $pdf->Cell(0, 6, $student['student_id'] ?? 'N/A', 1, 1);
    
    $pdf->Cell(45, 6, 'Name:', 1);
    $pdf->Cell(0, 6, $student['full_name'] ?? 'N/A', 1, 1);
    
    $pdf->Cell(45, 6, 'Gender:', 1);
    $pdf->Cell(0, 6, $student['gender'] ?? 'N/A', 1, 1);
    
    $pdf->Cell(45, 6, 'Programme:', 1);
    $pdf->Cell(0, 6, $programme, 1, 1);
    
    $pdf->Cell(45, 6, 'Generated Date:', 1);
    $pdf->Cell(0, 6, date('d/m/Y H:i:s'), 1, 1);
    
    $pdf->Ln(5);
    
    // GPA Summary Table
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'GPA Summary', 0, 1);
    $pdf->SetFont('Arial', 'B', 10);
    
    // Header for GPA table
    $pdf->Cell(60, 8, 'Academic Year', 1);
    $pdf->Cell(60, 8, 'Total Credits', 1);
    $pdf->Cell(60, 8, 'GPA', 1);
    $pdf->Ln();
    
    $pdf->SetFont('Arial', '', 10);
    if (!empty($gpa_data)) {
        foreach ($gpa_data as $gpa_entry) {
            $pdf->Cell(60, 8, $gpa_entry['year'], 1);
            $pdf->Cell(60, 8, $gpa_entry['credits'], 1);
            $pdf->Cell(60, 8, $gpa_entry['gpa'], 1);
            $pdf->Ln();
        }
    } else {
        $pdf->Cell(180, 8, 'No GPA data available', 1, 1);
    }
    
    $pdf->Ln(5);
    
    // Detailed results for each academic year
    if (!empty($year_data)) {
        foreach ($year_data as $year => $data) {
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 8, "Academic Year: $year", 0, 1);
            
            // Year summary info
            $pdf->SetFont('Arial', '', 10);
            $year_of_study = (date('Y') - intval(substr($year, 0, 4)) + 1) . ordinal(date('Y') - intval(substr($year, 0, 4)) + 1) . ' Year';
            
            $pdf->Cell(40, 6, 'Programme:', 0);
            $pdf->Cell(0, 6, $programme, 0, 1);
            
            $pdf->Cell(40, 6, 'Year of Study:', 0);
            $pdf->Cell(0, 6, $year_of_study, 0, 1);
            
            $pdf->Cell(40, 6, 'Credits:', 0);
            $pdf->Cell(0, 6, $data['credits'], 0, 1);
            
            $pdf->Cell(40, 6, 'GPA:', 0);
            $pdf->Cell(0, 6, $data['gpa'], 0, 1);
            
            $pdf->Cell(40, 6, 'Comment:', 0);
            $pdf->Cell(0, 6, $data['comment'], 0, 1);
            
            $pdf->Ln(3);
            
            // Course results table header
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(25, 8, 'Course Code', 1);
            $pdf->Cell(45, 8, 'Course Name', 1);
            $pdf->Cell(20, 8, 'CA Score', 1);
            $pdf->Cell(20, 8, 'Exam Score', 1);
            $pdf->Cell(20, 8, 'Total Score', 1);
            $pdf->Cell(15, 8, 'Credits', 1);
            $pdf->Cell(15, 8, 'Grade', 1);
            $pdf->Cell(20, 8, 'Comment', 1);
            $pdf->Ln();
            
            $pdf->SetFont('Arial', '', 8);
            foreach ($data['courses'] as $course) {
                $total_score = ($course['ca_score'] ?? 0) + ($course['exam_score'] ?? 0);
                $comment = !empty($course['admin_comment']) ? $course['admin_comment'] : $data['comment'];
                
                $pdf->Cell(25, 8, $course['course_code'], 1);
                $pdf->Cell(45, 8, substr($course['course_name'], 0, 25), 1);
                $pdf->Cell(20, 8, $course['ca_score'] ?? 'N/A', 1);
                $pdf->Cell(20, 8, $course['exam_score'] ?? 'N/A', 1);
                $pdf->Cell(20, 8, $total_score, 1);
                $pdf->Cell(15, 8, $course['credits'] ?? 'N/A', 1);
                $pdf->Cell(15, 8, $course['grade'] ?? 'N/A', 1);
                $pdf->Cell(20, 8, substr($comment, 0, 15), 1);
                $pdf->Ln();
            }
            
            $pdf->Ln(5);
        }
    } else {
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 8, 'No results available yet.', 0, 1);
    }
    
    // Footer
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 5, 'This is a computer-generated document and is valid without signature.', 0, 1, 'C');
    $pdf->Cell(0, 5, 'Lusaka South College © ' . date('Y') . ' - All Rights Reserved', 0, 1, 'C');
    
    // Create output filename
    $output_filename = 'results_transcript_' . $student['student_id'] . '_' . date('Y-m-d') . '.pdf';
    
    // Save the PDF to a temporary location
    $output_path = __DIR__ . '/downloads/' . $output_filename;
    
    // Ensure the downloads directory exists
    if (!file_exists(__DIR__ . '/downloads')) {
        mkdir(__DIR__ . '/downloads', 0777, true);
    }
    
    // Write the PDF to the file
    $pdf->Output('F', $output_path);
    
    return $output_path;
}

// Check if user is logged in and has student role
if (!currentUserId()) {
    header('Location: ../auth/login.php');
    exit;
}

requireRole('Student', $pdo);

// Generate the PDF for the current user
$pdf_path = generateResultsPDF(currentUserId(), $pdo);

// Send the PDF as a download
if (file_exists($pdf_path)) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($pdf_path) . '"');
    header('Content-Length: ' . filesize($pdf_path));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Expires: 0');
    
    readfile($pdf_path);
    
    // Optionally, delete the file after sending
    // unlink($pdf_path);
} else {
    echo "Error: Could not generate PDF.";
}
?>