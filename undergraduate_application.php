<?php
require 'config.php';
require 'auth.php';

// Check maintenance mode
checkMaintenanceMode($pdo);

// Fetch only undergraduate programmes from the database
try {
    $stmt = $pdo->prepare("SELECT id, name, code FROM programme WHERE category = 'undergraduate' ORDER BY name");
    $stmt->execute();
    $programmes = $stmt->fetchAll();
} catch (Exception $e) {
    $programmes = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $nrc = trim($_POST['nrc']);
    $gender = trim($_POST['gender']);
    $dob = $_POST['dateofbirth'];
    $address = trim($_POST['address']);
    $program = $_POST['program'];
    $intake = $_POST['intake'];
    $mode_of_learning = $_POST['mode_of_learning'];
    $guardian_name = trim($_POST['guardianname']);
    $guardian_phone = trim($_POST['guardianphone']);
    $relationship = $_POST['relationship'];
    $recommended_by = trim($_POST['recommendedby']); // New field
    
    // Validate required file uploads
    $has_grade12results = isset($_FILES['grade12results']) && $_FILES['grade12results']['error'] == 0;
    $has_previousschool = isset($_FILES['previousschool']) && $_FILES['previousschool']['error'] == 0;
    $has_nrc_copy = isset($_FILES['nrc_copy']) && $_FILES['nrc_copy']['error'] == 0;
    
    // Check if required files are uploaded
    if (!$has_grade12results) {
        throw new Exception("Grade 12 results file is required");
    }
    if (!$has_previousschool) {
        throw new Exception("Previous school documents file is required");
    }
    if (!$has_nrc_copy) {
        throw new Exception("NRC copy file is required");
    }
    
    // Handle file uploads
    $documents = [];
    if ($has_grade12results) {
        $documents[] = [
            'name' => $_FILES['grade12results']['name'],
            'path' => 'uploads/' . time() . '_grade12results_' . $_FILES['grade12results']['name']
        ];
        move_uploaded_file($_FILES['grade12results']['tmp_name'], $documents[count($documents)-1]['path']);
    }
    
    if ($has_previousschool) {
        $documents[] = [
            'name' => $_FILES['previousschool']['name'],
            'path' => 'uploads/' . time() . '_previousschool_' . $_FILES['previousschool']['name']
        ];
        move_uploaded_file($_FILES['previousschool']['tmp_name'], $documents[count($documents)-1]['path']);
    }
    
    if ($has_nrc_copy) {
        $documents[] = [
            'name' => $_FILES['nrc_copy']['name'],
            'path' => 'uploads/' . time() . '_nrc_copy_' . $_FILES['nrc_copy']['name']
        ];
        move_uploaded_file($_FILES['nrc_copy']['tmp_name'], $documents[count($documents)-1]['path']);
    }
    
    try {
        // Get programme ID from the selected programme
        $programme_id = $program; // Now directly using the programme ID from the dropdown
        
        // Verify that the programme exists
        $stmt = $pdo->prepare("SELECT id FROM programme WHERE id = ? LIMIT 1");
        $stmt->execute([$programme_id]);
        $programme = $stmt->fetch();
        
        if (!$programme) {
            throw new Exception("Invalid programme selection");
        }
        
        // Get intake ID
        $stmt = $pdo->prepare("SELECT id FROM intake WHERE name LIKE ? LIMIT 1");
        $stmt->execute(["%$intake%"]);
        $intake_data = $stmt->fetch();
        $intake_id = $intake_data ? $intake_data['id'] : null;
        
        // Insert application into database
        $stmt = $pdo->prepare("INSERT INTO applications (full_name, email, phone, application_type, programme_id, intake_id, mode_of_learning, documents) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $firstname . ' ' . $lastname,
            $email,
            $phone,
            'undergraduate',
            $programme_id,
            $intake_id,
            $mode_of_learning,
            json_encode(array_merge($documents, [
                'recommended_by' => $recommended_by,
                'gender' => $gender,
                'nrc_number' => $nrc
            ])) // Include recommended by, gender and NRC in documents
        ]);
        
        $success = "Your application has been submitted successfully! Our enrollment team will review your application and contact you soon.";
    } catch (Exception $e) {
        $error = "There was an error submitting your application. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Undergraduate Application - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="assets/images/school_logo.jpg">
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
            <div class="subtitle">Undergraduate Application</div>
        </div>
        
        <h2>Undergraduate Application</h2>
        <p>Apply for undergraduate programs at Lusaka South College</p>
        
        <div class="back-link">
            <a href="enroll.php">← Back to Enrollment Options</a> | 
            <a href="https://lsuczm.com/#home">← Home</a>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="form-info">
            <h4>Application Requirements</h4>
            <ul>
                <li>Completed Grade 12 Certificate or equivalent</li>
                <li>Grade 7 & 9 for Trade Certificates Programmes</li>
                <li>Official transcripts from previous institutions</li>
                <li>Copy of National Registration Card/Passport</li>
                <li>Two passport-size photographs</li>
                <li>Application fee payment confirmation</li>
            </ul>
        </div>
        
        <form class="application-form" method="POST" enctype="multipart/form-data">
            <div class="form-section">
                <h3>Personal Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="ug-firstname">First Name *</label>
                        <input type="text" id="ug-firstname" name="firstname" required>
                    </div>
                    <div class="form-group">
                        <label for="ug-lastname">Last Name *</label>
                        <input type="text" id="ug-lastname" name="lastname" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="ug-email">Email Address *</label>
                        <input type="email" id="ug-email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="ug-phone">Phone Number *</label>
                        <input type="tel" id="ug-phone" name="phone" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="ug-nrc">National Registration Card/Passport *</label>
                        <input type="text" id="ug-nrc" name="nrc" required>
                    </div>
                    <div class="form-group">
                        <label for="ug-gender">Gender *</label>
                        <select id="ug-gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                           
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="ug-nrc-copy">Attach NRC Copy *</label>
                        <input type="file" id="ug-nrc-copy" name="nrc_copy" required>
                    </div>
                    <div class="form-group">
                        <label for="ug-dob">Date of Birth *</label>
                        <input type="date" id="ug-dob" name="dateofbirth" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="ug-address">Physical Address *</label>
                    <textarea id="ug-address" name="address" rows="3" required></textarea>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Academic Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="ug-program">Preferred Program *</label>
                        <select id="ug-program" name="program" required>
                            <option value="">Select Program</option>
                            <?php foreach ($programmes as $prog): ?>
                                <option value="<?php echo $prog['id']; ?>">
                                    <?php echo htmlspecialchars($prog['name'] . ' (' . $prog['code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ug-intake">Preferred Intake *</label>
                        <select id="ug-intake" name="intake" required>
                            <option value="">Select Intake</option>
                            <option value="January">January</option>
                            <option value="April">April</option>
                            <option value="July">July</option>
                            <option value="October">October</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="ug-mode-of-learning">Mode of Learning *</label>
                        <select id="ug-mode-of-learning" name="mode_of_learning" required>
                            <option value="">Select Mode of Learning</option>
                            <option value="physical">Physical (Full Time)</option>
                            <option value="online">Online (Distance)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <!-- Empty div for spacing -->
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="ug-grade">Copy of Results *</label>
                        <input type="file" id="ug-grade" name="grade12results" required>
                    </div>
                    <div class="form-group">
                        <label for="ug-school">Supporting Documents(Proof of Payment) *</label>
                        <input type="file" id="ug-school" name="previousschool" required>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Emergency Contact</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="ug-guardian-name">Guardian/Parent Name *</label>
                        <input type="text" id="ug-guardian-name" name="guardianname" required>
                    </div>
                    <div class="form-group">
                        <label for="ug-guardian-phone">Guardian/Parent Phone *</label>
                        <input type="tel" id="ug-guardian-phone" name="guardianphone" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="ug-guardian-relationship">Relationship *</label>
                        <select id="ug-guardian-relationship" name="relationship" required>
                            <option value="">Select Relationship</option>
                            <option value="parent">Parent</option>
                            <option value="guardian">Guardian</option>
                            <option value="spouse">Spouse</option>
                            <option value="sibling">Sibling</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ug-recommended-by">Recommended by (if any)</label>
                        <input type="text" id="ug-recommended-by" name="recommendedby" placeholder="Name of Enrollment Officer">
                    </div>
                </div>
            </div>
            
            <button type="submit" class="submit-btn">Submit Application</button>
        </form>
    </div>
</body>
</html>