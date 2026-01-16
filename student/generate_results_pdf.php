<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

// Load DOMPDF
require_once __DIR__ . '/../lib/dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

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
 * Generate student results PDF
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

    // Generate the HTML content for the PDF
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Results Transcript</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 30px;
            background-color: #fff;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #228B22;
            padding-bottom: 15px;
        }
        
        .college-name {
            font-size: 24px;
            font-weight: bold;
            color: #228B22;
            margin-bottom: 5px;
        }
        
        .tagline {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .logo {
            width: 100px;
            height: 100px;
            margin-bottom: 10px;
        }
        
        .student-info {
            margin: 20px 0;
            padding: 15px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .info-label {
            font-weight: bold;
            width: 150px;
            flex-shrink: 0;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #228B22;
            margin: 30px 0 15px 0;
            padding-bottom: 5px;
            border-bottom: 2px solid #228B22;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 12px;
        }
        
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        table th {
            background-color: #228B22;
            color: white;
            font-weight: bold;
        }
        
        table tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        
        .gpa-summary {
            margin: 20px 0;
            padding: 15px;
            background-color: #e8f5e8;
            border: 1px solid #c8e6c9;
            border-radius: 5px;
        }
        
        .year-section {
            margin-top: 30px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #fafafa;
        }
        
        .year-header {
            font-size: 16px;
            font-weight: bold;
            color: #228B22;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 1px solid #228B22;
        }
        
        .year-info {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .year-info-item {
            margin-right: 20px;
        }
        
        .year-info-label {
            font-weight: bold;
            display: inline-block;
            width: 100px;
        }
        
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 20px;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        @media print {
            body {
                font-size: 10pt;
            }
            
            table {
                font-size: 9pt;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="college-name">LUSAKA SOUTH COLLEGE</div>
        <div class="tagline">Academic Results Transcript</div>
        <img src="../assets/images/lsc-logo.png" class="logo" alt="LSUC Logo" onerror="this.style.display=\'none\'">
    </div>
    
    <div class="student-info">
        <div class="info-row">
            <div class="info-label">Computer Number:</div>
            <div>' . htmlspecialchars($student['student_id'] ?? 'N/A') . '</div>
        </div>
        <div class="info-row">
            <div class="info-label">Name:</div>
            <div>' . htmlspecialchars($student['full_name'] ?? 'N/A') . '</div>
        </div>
        <div class="info-row">
            <div class="info-label">Gender:</div>
            <div>' . htmlspecialchars($student['gender'] ?? 'N/A') . '</div>
        </div>
        <div class="info-row">
            <div class="info-label">Programme:</div>
            <div>' . htmlspecialchars($programme) . '</div>
        </div>
        <div class="info-row">
            <div class="info-label">Generated Date:</div>
            <div>' . date('d/m/Y H:i:s') . '</div>
        </div>
    </div>';

    // Add GPA Summary Table
    $html .= '
    <h3 class="section-title">GPA Summary</h3>
    <table>
        <thead>
            <tr>
                <th>Academic Year</th>
                <th>Total Credits</th>
                <th>GPA</th>
            </tr>
        </thead>
        <tbody>';
        
    if (!empty($gpa_data)) {
        foreach ($gpa_data as $gpa_entry) {
            $html .= '
            <tr>
                <td>' . htmlspecialchars($gpa_entry['year']) . '</td>
                <td>' . htmlspecialchars($gpa_entry['credits']) . '</td>
                <td>' . htmlspecialchars($gpa_entry['gpa']) . '</td>
            </tr>';
        }
    } else {
        $html .= '
            <tr>
                <td colspan="3">No GPA data available</td>
            </tr>';
    }
    
    $html .= '
        </tbody>
    </table>';

    // Add detailed results for each academic year
    if (!empty($year_data)) {
        foreach ($year_data as $year => $data) {
            $html .= '
            <div class="year-section">
                <div class="year-header">Academic Year: ' . htmlspecialchars($year) . '</div>
                
                <div class="year-info">
                    <div class="year-info-item">
                        <span class="year-info-label">Programme:</span>
                        ' . htmlspecialchars($programme) . '
                    </div>
                    <div class="year-info-item">
                        <span class="year-info-label">Year of Study:</span>
                        ' . (date('Y') - intval(substr($year, 0, 4)) + 1) . ordinal(date('Y') - intval(substr($year, 0, 4)) + 1) . ' Year
                    </div>
                    <div class="year-info-item">
                        <span class="year-info-label">Credits:</span>
                        ' . htmlspecialchars($data['credits']) . '
                    </div>
                    <div class="year-info-item">
                        <span class="year-info-label">GPA:</span>
                        ' . htmlspecialchars($data['gpa']) . '
                    </div>
                    <div class="year-info-item">
                        <span class="year-info-label">Comment:</span>
                        ' . htmlspecialchars($data['comment']) . '
                    </div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>CA Score</th>
                            <th>Exam Score</th>
                            <th>Total Score</th>
                            <th>Credits</th>
                            <th>Grade</th>
                            <th>Comment</th>
                        </tr>
                    </thead>
                    <tbody>';
                    
            foreach ($data['courses'] as $course) {
                $total_score = ($course['ca_score'] ?? 0) + ($course['exam_score'] ?? 0);
                $comment = !empty($course['admin_comment']) ? $course['admin_comment'] : $data['comment'];
                
                $html .= '
                    <tr>
                        <td>' . htmlspecialchars($course['course_code']) . '</td>
                        <td>' . htmlspecialchars($course['course_name']) . '</td>
                        <td>' . htmlspecialchars($course['ca_score'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($course['exam_score'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($total_score) . '</td>
                        <td>' . htmlspecialchars($course['credits'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($course['grade'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($comment) . '</td>
                    </tr>';
            }
            
            $html .= '
                    </tbody>
                </table>
            </div>';
        }
    } else {
        $html .= '
        <div class="alert alert-info">
            <p>No results available yet.</p>
        </div>';
    }

    $html .= '
    <div class="footer">
        <p>This is a computer-generated document and is valid without signature.</p>
        <p>Lusaka South College &copy; ' . date('Y') . ' - All Rights Reserved</p>
    </div>
</body>
</html>';

    // Configure DOMPDF
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isRemoteEnabled', true); // Enable loading remote resources like images
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    
    // Set paper size and orientation
    $dompdf->setPaper('A4', 'landscape');
    
    // Render the PDF
    $dompdf->render();
    
    // Output the generated PDF to Browser
    $output = $dompdf->output();
    
    // Create output filename
    $output_filename = 'results_transcript_' . $student['student_id'] . '_' . date('Y-m-d') . '.pdf';
    
    // Save the PDF to a temporary location
    $output_path = __DIR__ . '/downloads/' . $output_filename;
    
    // Ensure the downloads directory exists
    if (!file_exists(__DIR__ . '/downloads')) {
        mkdir(__DIR__ . '/downloads', 0777, true);
    }
    
    // Write the PDF to the file
    file_put_contents($output_path, $output);
    
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