<?php
// Remove all login requirements and session checks
// This will be an independent registration form accessed via direct link

require_once '../config.php';

// Ensure pending_students table has all required columns
try {
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS student_number VARCHAR(50)");
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS registration_status ENUM('pending','pending_approval','approved','rejected') DEFAULT 'pending'");
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS rejection_reason TEXT");
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50)");
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS payment_amount DECIMAL(10,2)");
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100)");
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS payment_proof VARCHAR(255)");
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS finance_cleared TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS finance_cleared_at TIMESTAMP NULL");
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS finance_cleared_by INT NULL");
} catch (Exception $e) {
    // Columns might already exist
}

// Handle form submissions
$message = '';
$messageType = '';
$registration_complete = false;

// Initialize email variable from URL parameter
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

// Get student data if email is provided in URL
$student_data = null;
if ($email) {
    // First check if they've already registered (exist in pending_students)
    $stmt = $pdo->prepare("SELECT * FROM pending_students WHERE email = ? AND registration_status = 'pending_approval'");
    $stmt->execute([$email]);
    $student_data = $stmt->fetch();
    
    // If no record in pending_students, check if they have an approved application
    if (!$student_data) {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE email = ? AND status = 'approved'");
        $stmt->execute([$email]);
        $application_data = $stmt->fetch();
        
        // If they have an approved application, use that data to pre-fill the form
        if ($application_data) {
            $student_data = [
                'full_name' => $application_data['full_name'],
                'email' => $application_data['email'],
                'programme_id' => $application_data['programme_id'],
                'intake_id' => $application_data['intake_id']
            ];
        }
    }
    
    // If no record in pending_students or applications, check student_profile for existing students
    if (!$student_data) {
        $stmt = $pdo->prepare("SELECT sp.full_name, u.email, sp.programme_id, sp.intake_id FROM student_profile sp JOIN users u ON sp.user_id = u.id WHERE u.email = ?");
        $stmt->execute([$email]);
        $existing_student = $stmt->fetch();
        
        if ($existing_student) {
            $student_data = $existing_student;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'submit_registration':
                $email = trim($_POST['email']);
                $full_name = trim($_POST['full_name']);
                $intake_id = $_POST['intake_id'];
                $programme_id = $_POST['programme_id'];
                $session_id = $_POST['session_id'];
                $payment_method = $_POST['payment_method'];
                $payment_amount = $_POST['payment_amount'];
                $transaction_id = $_POST['transaction_id'];
                
                // Handle file upload
                $payment_proof = null;
                if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] == 0) {
                    $upload_dir = 'uploads/payment_proofs/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_extension = strtolower(pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
                    
                    if (in_array($file_extension, $allowed_extensions)) {
                        $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
                        $target_file = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $target_file)) {
                            $payment_proof = $target_file;
                        }
                    }
                }
                
                // Insert into pending_students table with 'pending_approval' status
                try {
                    $stmt = $pdo->prepare("INSERT INTO pending_students (full_name, email, programme_id, intake_id, session_id, payment_method, payment_amount, transaction_id, payment_proof, created_at, registration_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending_approval')");
                    $stmt->execute([
                        $full_name,
                        $email,
                        $programme_id,
                        $intake_id,
                        $session_id,
                        $payment_method,
                        $payment_amount,
                        $transaction_id,
                        $payment_proof
                    ]);
                    
                    $registration_complete = true;
                    $message = "Registration submitted successfully! Our team will review your application and contact you soon.";
                    $messageType = "success";
                } catch (Exception $e) {
                    $message = "Error submitting registration: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Get intakes
try {
    $stmt = $pdo->query("SELECT * FROM intake ORDER BY start_date ASC");
    $intakes = $stmt->fetchAll();
} catch (Exception $e) {
    $intakes = [];
}

// Get programmes
try {
    $stmt = $pdo->query("SELECT * FROM programme ORDER BY name");
    $programmes = $stmt->fetchAll();
} catch (Exception $e) {
    $programmes = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>First Time Registration - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .registration-container {
            max-width: 800px;
            margin: 30px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .form-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 5px;
        }
        
        .form-section h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
        }
        
        .payment-methods {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }
        
        .payment-method {
            flex: 1;
            text-align: center;
            padding: 15px;
            border: 2px solid #eee;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .payment-method:hover {
            border-color: #007bff;
        }
        
        .payment-method.selected {
            border-color: #007bff;
            background-color: #f0f8ff;
        }
        
        .payment-method i {
            font-size: 24px;
            margin-bottom: 10px;
            color: #007bff;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .course-list {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
        }
        
        .course-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .course-item:last-child {
            border-bottom: none;
        }
        
        .success-message {
            text-align: center;
            padding: 40px;
        }
        
        .success-icon {
            font-size: 64px;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .readonly-field {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
    </style>
</head>
<body class="student-layout">
    <!-- Top Navigation Bar -->
    <nav class="top-nav">
        <div class="nav-left">
            <div class="logo-container">
                <img src="../assets/images/lsc-logo.png" alt="LSC Logo" class="logo" onerror="this.style.display='none'">
                <span class="logo-text">LSC SRMS</span>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-user-graduate"></i> First Time Registration</h1>
            <p>Complete your registration to become a full-time student</p>
        </div>

        <?php if ($registration_complete): ?>
            <div class="registration-container">
                <div class="success-message">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2>Registration Submitted Successfully!</h2>
                    <p>Thank you for completing your registration. Our admissions team will review your application and payment proof.</p>
                    <p>You will receive an email with your student number and login credentials once your registration is approved.</p>
                    <p>Please check your email (including spam/junk folder) within 2-3 business days.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="registration-container">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="submit_registration">
                    
                    <!-- Personal Information -->
                    <div class="form-section">
                        <h3><i class="fas fa-user"></i> Personal Information</h3>
                        <div class="form-group">
                            <label for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" required 
                                   value="<?php echo htmlspecialchars($student_data['full_name'] ?? ''); ?>"
                                   placeholder="Enter your full name"
                                   <?php echo $student_data ? 'readonly class="readonly-field"' : ''; ?>>
                            <?php if ($student_data): ?>
                                <small>This information was pre-filled from your application.</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo htmlspecialchars($email ?? $student_data['email'] ?? ''); ?>"
                                   placeholder="Enter your email address" 
                                   <?php echo $email ? 'readonly class="readonly-field"' : ''; ?>>
                            <?php if ($email): ?>
                                <small>This email was pre-filled from your application.</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Programme Selection -->
                    <div class="form-section">
                        <h3><i class="fas fa-graduation-cap"></i> Programme Information</h3>
                        <div class="form-group">
                            <label for="programme_id">Select Programme *</label>
                            <select id="programme_id" name="programme_id" required <?php echo $student_data ? 'disabled' : ''; ?>>
                                <option value="">-- Select Programme --</option>
                                <?php foreach ($programmes as $programme): ?>
                                    <option value="<?php echo $programme['id']; ?>" <?php echo (isset($student_data['programme_id']) && $student_data['programme_id'] == $programme['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($programme['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($student_data): ?>
                                <input type="hidden" name="programme_id" value="<?php echo htmlspecialchars($student_data['programme_id'] ?? ''); ?>">
                                <small>Your selected programme was pre-filled from your application.</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="intake_id">Select Intake *</label>
                            <select id="intake_id" name="intake_id" required <?php echo $student_data ? 'disabled' : ''; ?>>
                                <option value="">-- Select Intake --</option>
                                <?php foreach ($intakes as $intake): ?>
                                    <option value="<?php echo $intake['id']; ?>" <?php echo (isset($student_data['intake_id']) && $student_data['intake_id'] == $intake['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($intake['name']); ?> 
                                        (<?php echo date('M Y', strtotime($intake['start_date'])); ?> - <?php echo date('M Y', strtotime($intake['end_date'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($student_data): ?>
                                <input type="hidden" name="intake_id" value="<?php echo htmlspecialchars($student_data['intake_id'] ?? ''); ?>">
                                <small>Your selected intake was pre-filled from your application.</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="session_id">Select Session *</label>
                            <select id="session_id" name="session_id" required>
                                <option value="">-- Select Session --</option>
                            </select>
                            <?php if ($student_data): ?>
                                <input type="hidden" name="session_id" id="hidden_session_id" value="">
                            <?php endif; ?>
                            <small>Select the academic session for your registration</small>
                        </div>
                        
                        <!-- Programme Fee Display -->
                        <div class="form-group">
                            <label>Programme Fee</label>
                            <div id="feeDisplay" style="background-color: #f8f9fa; padding: 15px; border-radius: 5px;">
                                <p id="feePlaceholder">Select a programme and session to see the fee information</p>
                                <div id="feeContent" style="display: none;"></div>
                            </div>
                        </div>
                        
                        <!-- Programme Courses -->
                        <div class="form-group">
                            <label>Courses for Selected Programme and Session (Term 1)</label>
                            <div class="course-list" id="courseList">
                                <p id="courseListPlaceholder">Select a programme and session to see available courses</p>
                                <div id="courseListContent" style="display: none;"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Information -->
                    <div class="form-section">
                        <h3><i class="fas fa-money-bill-wave"></i> Payment Information</h3>
                        
                        <div class="form-group">
                            <label for="payment_method">Payment Method *</label>
                            <div class="payment-methods">
                                <div class="payment-method" onclick="selectPaymentMethod('bank_transfer')">
                                    <i class="fas fa-university"></i>
                                    <div>Bank Transfer</div>
                                </div>
                                <div class="payment-method" onclick="selectPaymentMethod('mobile_money')">
                                    <i class="fas fa-mobile-alt"></i>
                                    <div>Mobile Money</div>
                                </div>
                                <div class="payment-method" onclick="selectPaymentMethod('credit_card')">
                                    <i class="fas fa-credit-card"></i>
                                    <div>Credit Card</div>
                                </div>
                            </div>
                            <input type="hidden" id="payment_method" name="payment_method" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_amount">Payment Amount (ZMW) *</label>
                            <input type="number" id="payment_amount" name="payment_amount" step="0.01" min="0" required 
                                   value="<?php echo htmlspecialchars($student_data['payment_amount'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="transaction_id">Transaction ID *</label>
                            <input type="text" id="transaction_id" name="transaction_id" required 
                                   value="<?php echo htmlspecialchars($student_data['transaction_id'] ?? ''); ?>"
                                   placeholder="Enter transaction reference number">
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_proof">Payment Proof *</label>
                            <input type="file" id="payment_proof" name="payment_proof" accept="image/*,application/pdf" required>
                            <small>Upload a screenshot or scanned copy of your payment receipt (JPG, PNG, PDF)</small>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="form-group" style="text-align: center;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Registration
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Payment method selection
        function selectPaymentMethod(method) {
            // Remove selected class from all methods
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('selected');
            });
            
            // Add selected class to clicked method
            event.currentTarget.classList.add('selected');
            
            // Set hidden input value
            document.getElementById('payment_method').value = method;
        }
        
        // Load available sessions
        function loadSessions() {
            fetch('./fetch_sessions.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    const sessionSelect = document.getElementById('session_id');
                    
                    if (data.error) {
                        console.error('Error loading sessions:', data.error);
                        sessionSelect.innerHTML = '<option value="">Error loading sessions</option>';
                    } else if (data.message) {
                        sessionSelect.innerHTML = '<option value="">' + data.message + '</option>';
                    } else if (data.sessions && data.sessions.length > 0) {
                        sessionSelect.innerHTML = '<option value="">-- Select Session --</option>';
                        data.sessions.forEach(session => {
                            const option = document.createElement('option');
                            option.value = session.id;
                            option.textContent = session.session_name + ' (' + session.academic_year + ')';
                            sessionSelect.appendChild(option);
                        });
                    } else {
                        sessionSelect.innerHTML = '<option value="">No sessions available</option>';
                    }
                })
                .catch(error => {
                    console.error('Error loading sessions:', error);
                    const sessionSelect = document.getElementById('session_id');
                    sessionSelect.innerHTML = '<option value="">Error loading sessions</option>';
                });
        }
        
        // Load programme courses
        function loadCourses(programmeId, sessionId) {
            const courseList = document.getElementById('courseList');
            const courseListPlaceholder = document.getElementById('courseListPlaceholder');
            const courseListContent = document.getElementById('courseListContent');
            
            if (programmeId && sessionId) {
                // Show loading message
                courseListPlaceholder.style.display = 'none';
                courseListContent.style.display = 'none';
                courseList.innerHTML = '<p>Loading courses...</p>';
                
                // Fetch courses via AJAX
                fetch('./fetch_programme_courses.php?programme_id=' + programmeId + '&session_id=' + sessionId + '&term=1')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.error) {
                            courseList.innerHTML = '<p>Error loading courses: ' + data.error + '</p>';
                        } else if (data.message) {
                            courseList.innerHTML = '<p>' + data.message + '</p>';
                        } else if (data.courses && data.courses.length > 0) {
                            let courseHtml = '';
                            data.courses.forEach(course => {
                                courseHtml += `
                                    <div class="course-item">
                                        <strong>${course.course_name}</strong>
                                        <div>${course.course_code} - ${course.credits} Credits</div>
                                        <div>${course.description || ''}</div>
                                    </div>
                                `;
                            });
                            courseListContent.innerHTML = courseHtml;
                            courseListContent.style.display = 'block';
                            courseList.innerHTML = '';
                            courseList.appendChild(courseListPlaceholder);
                            courseList.appendChild(courseListContent);
                        } else {
                            courseList.innerHTML = '<p>No courses defined for this programme and session yet.</p>';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading courses:', error);
                        courseList.innerHTML = '<p>Error loading courses: ' + error.message + '</p>';
                    });
            } else {
                courseListContent.style.display = 'none';
                courseListPlaceholder.style.display = 'block';
                courseList.innerHTML = '';
                courseList.appendChild(courseListPlaceholder);
                courseList.appendChild(document.createElement('div')).textContent = 'Select both programme and session to see courses.';
            }
        }
        
        // Load programme fee
        function loadProgrammeFee(programmeId, sessionId) {
            const feeDisplay = document.getElementById('feeDisplay');
            const feePlaceholder = document.getElementById('feePlaceholder');
            const feeContent = document.getElementById('feeContent');
            
            if (programmeId && sessionId) {
                // Show loading message
                feePlaceholder.style.display = 'none';
                feeContent.style.display = 'none';
                feeDisplay.innerHTML = '<p>Loading fee information...</p>';
                
                // Fetch fee via AJAX
                fetch('./fetch_programme_fee.php?programme_id=' + programmeId)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success === false) {
                            if (data.message) {
                                feeDisplay.innerHTML = '<p>' + data.message + '</p>';
                            } else {
                                feeDisplay.innerHTML = '<p>Error loading fee information.</p>';
                            }
                        } else if (data.success === true && data.amount) {
                            let feeHtml = `
                                <h4>Total Amount Payable: K${parseFloat(data.amount).toFixed(2)}</h4>
                            `;
                            if (data.description) {
                                feeHtml += `<p>${data.description}</p>`;
                            }
                            feeContent.innerHTML = feeHtml;
                            feeContent.style.display = 'block';
                            feeDisplay.innerHTML = '';
                            feeDisplay.appendChild(feePlaceholder);
                            feeDisplay.appendChild(feeContent);
                        } else {
                            feeDisplay.innerHTML = '<p>No fee information available for this programme.</p>';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading fee:', error);
                        feeDisplay.innerHTML = '<p>Error loading fee: ' + error.message + '</p>';
                    });
            } else {
                feeContent.style.display = 'none';
                feePlaceholder.style.display = 'block';
                feeDisplay.innerHTML = '';
                feeDisplay.appendChild(feePlaceholder);
                feeDisplay.appendChild(document.createElement('div')).textContent = 'Select both programme and session to see fee information.';
            }
        }
        
        // Event listeners for programme and session selection
        document.getElementById('programme_id').addEventListener('change', function() {
            const programmeId = this.value;
            const sessionId = document.getElementById('session_id').value;
            
            if (programmeId && sessionId) {
                loadCourses(programmeId, sessionId);
                loadProgrammeFee(programmeId, sessionId);
            } else {
                // Reset if either is not selected
                const courseList = document.getElementById('courseList');
                const feeDisplay = document.getElementById('feeDisplay');
                courseList.innerHTML = '<p id="courseListPlaceholder">Select a programme and session to see available courses</p>';
                feeDisplay.innerHTML = '<p id="feePlaceholder">Select a programme and session to see the fee information</p>';
            }
        });
        
        document.getElementById('session_id').addEventListener('change', function() {
            const sessionId = this.value;
            const programmeId = document.getElementById('programme_id').value;
            
            if (programmeId && sessionId) {
                loadCourses(programmeId, sessionId);
                loadProgrammeFee(programmeId, sessionId);
            } else {
                // Reset if either is not selected
                const courseList = document.getElementById('courseList');
                const feeDisplay = document.getElementById('feeDisplay');
                courseList.innerHTML = '<p id="courseListPlaceholder">Select a programme and session to see available courses</p>';
                feeDisplay.innerHTML = '<p id="feePlaceholder">Select a programme and session to see the fee information</p>';
            }
        });
        
        // Initialize payment method if already selected
        document.addEventListener('DOMContentLoaded', function() {
            const selectedMethod = document.getElementById('payment_method').value;
            if (selectedMethod) {
                document.querySelectorAll('.payment-method').forEach(el => {
                    if (el.textContent.toLowerCase().includes(selectedMethod.replace('_', ' '))) {
                        el.classList.add('selected');
                    }
                });
            }
            
            // Load available sessions
            loadSessions();
            
            // If programme and session are already selected (pre-filled), load courses and fee
            const programmeId = document.getElementById('programme_id').value;
            const sessionId = document.getElementById('session_id').value;
            if (programmeId && sessionId) {
                loadCourses(programmeId, sessionId);
                loadProgrammeFee(programmeId, sessionId);
            }
        });
    </script>
</body>
</html>