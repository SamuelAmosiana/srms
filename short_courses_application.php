<?php
require 'config.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $occupation = trim($_POST['occupation']);
    $course = $_POST['course'];
    $intake = $_POST['startdate'];
    $schedule = $_POST['schedule'];
    $experience = trim($_POST['experience']);
    $goals = trim($_POST['goals']);
    
    try {
        // Get programme ID (for short courses, we'll use a generic approach)
        $stmt = $pdo->prepare("SELECT id FROM programme WHERE name LIKE ? LIMIT 1");
        $stmt->execute(["%$course%"]);
        $programme = $stmt->fetch();
        $programme_id = $programme ? $programme['id'] : null;
        
        // Get intake ID
        $stmt = $pdo->prepare("SELECT id FROM intake WHERE name LIKE ? LIMIT 1");
        $stmt->execute(["%$intake%"]);
        $intake_data = $stmt->fetch();
        $intake_id = $intake_data ? $intake_data['id'] : null;
        
        // Insert application into database
        $stmt = $pdo->prepare("INSERT INTO applications (full_name, email, programme_id, intake_id, documents) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $firstname . ' ' . $lastname,
            $email,
            $programme_id,
            $intake_id,
            json_encode([
                'occupation' => $occupation,
                'schedule' => $schedule,
                'experience' => $experience,
                'goals' => $goals
            ])
        ]);
        
        $success = "Your enrollment has been submitted successfully! Our enrollment team will review your application and contact you soon.";
    } catch (Exception $e) {
        $error = "There was an error submitting your enrollment. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Short Courses Application - LSC SRMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .container {
            max-width: 1000px;
        }
        
        .application-form {
            text-align: left;
            background: #f9f9f9;
            padding: 30px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .form-section h3 {
            color: var(--primary-green);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-orange);
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .form-group {
            flex: 1;
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: 0 0 5px rgba(255, 140, 0, 0.3);
        }
        
        .submit-btn {
            background: var(--primary-green);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 6px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            display: block;
            width: 100%;
            margin-top: 20px;
            transition: background 0.3s ease;
        }
        
        .submit-btn:hover {
            background: var(--dark-green);
        }
        
        .form-info {
            background: #e8f5e8;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .form-info h4 {
            color: var(--primary-green);
            margin-bottom: 15px;
        }
        
        .form-info ul {
            padding-left: 20px;
        }
        
        .form-info ul li {
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .container {
                max-width: 100%;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="school-header">
            <h1>Lusaka South College</h1>
            <div class="subtitle">Short Courses Application</div>
        </div>
        
        <h2>Short Courses Application</h2>
        <p>Enroll in professional development and skill enhancement courses</p>
        
        <div class="back-link">
            <a href="enroll.php">← Back to Enrollment Options</a> | 
            <a href="index.php">← Home</a>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="form-info">
            <h4>Available Short Courses</h4>
            <ul>
                <li>Digital Marketing & Social Media</li>
                <li>Project Management Certification</li>
                <li>Computer Applications (Microsoft Office)</li>
                <li>Financial Management & Accounting</li>
                <li>Leadership & Management Skills</li>
                <li>Web Development & Design</li>
            </ul>
        </div>
        
        <form class="application-form" method="POST">
            <div class="form-section">
                <h3>Personal Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="sc-firstname">First Name *</label>
                        <input type="text" id="sc-firstname" name="firstname" required>
                    </div>
                    <div class="form-group">
                        <label for="sc-lastname">Last Name *</label>
                        <input type="text" id="sc-lastname" name="lastname" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="sc-email">Email Address *</label>
                        <input type="email" id="sc-email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="sc-phone">Phone Number *</label>
                        <input type="tel" id="sc-phone" name="phone" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="sc-occupation">Current Occupation</label>
                    <input type="text" id="sc-occupation" name="occupation">
                </div>
            </div>
            
            <div class="form-section">
                <h3>Course Selection</h3>
                <div class="form-group">
                    <label for="sc-course">Select Course *</label>
                    <select id="sc-course" name="course" required>
                        <option value="">Choose Course</option>
                        <option value="digital-marketing">Digital Marketing & Social Media</option>
                        <option value="project-management">Project Management Certification</option>
                        <option value="computer-applications">Computer Applications (Microsoft Office)</option>
                        <option value="financial-management">Financial Management & Accounting</option>
                        <option value="leadership">Leadership & Management Skills</option>
                        <option value="web-development">Web Development & Design</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="sc-start-date">Intake *</label>
                        <select id="sc-start-date" name="startdate" required>
                            <option value="">Select Intake</option>
                            <option value="november-2025">January</option>
                            <option value="january-2026">April</option>
                            <option value="march-2026">July</option>
                            <option value="may-2026">October</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="sc-schedule">Mode of Study *</label>
                        <select id="sc-schedule" name="schedule" required>
                            <option value="">Select mode of Study</option>
                            <option value="weekdays">Physical (Full time)</option>
                            <option value="online">Online (Distance)</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="sc-experience">Relevant Experience/Background</label>
                    <textarea id="sc-experience" name="experience" rows="3" placeholder="Please describe any relevant experience or background related to your chosen course"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="sc-goals">Recommended By?</label>
                    <input type="text" id="sc-goals" name="goals" placeholder="Who recommended you to take this course?">
                </div>
            </div>
            
            <button type="submit" class="submit-btn">Enroll Now</button>
        </form>
    </div>
</body>
</html>