<?php
// Remove all login requirements and session checks
// This will be an independent registration form accessed via direct link

require_once 'config.php';

// Handle form submissions
$message = '';
$messageType = '';
$registration_complete = false;

// Initialize email variable from URL parameter
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

// Get student data if email is provided in URL
$student_data = null;
if ($email) {
    $stmt = $pdo->prepare("SELECT * FROM pending_students WHERE email = ? AND registration_status = 'approved'");
    $stmt->execute([$email]);
    $student_data = $stmt->fetch();
    
    // If no approved record found, check for pending_approval record
    if (!$student_data) {
        $stmt = $pdo->prepare("SELECT * FROM pending_students WHERE email = ? AND registration_status = 'pending_approval'");
        $stmt->execute([$email]);
        $student_data = $stmt->fetch();
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
                    $stmt = $pdo->prepare("INSERT INTO pending_students (full_name, email, programme_id, intake_id, payment_method, payment_amount, transaction_id, payment_proof, created_at, registration_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending_approval')");
                    $stmt->execute([
                        $full_name,
                        $email,
                        $programme_id,
                        $intake_id,
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
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/student-dashboard.css">
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
                <img src="assets/images/lsc-logo.png" alt="LSC Logo" class="logo" onerror="this.style.display='none'">
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
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo htmlspecialchars($email ?? $student_data['email'] ?? ''); ?>"
                                   placeholder="Enter your email address" 
                                   <?php echo $email ? 'readonly class="readonly-field"' : ''; ?>>
                            <?php if ($email): ?>
                                <small>This email was pre-filled from your invitation link.</small>
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
                            <?php endif; ?>
                        </div>
                        
                        <!-- Programme Courses -->
                        <div class="form-group">
                            <label>Courses for Selected Programme</label>
                            <div class="course-list" id="courseList">
                                <p>Select a programme to see available courses</p>
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
        
        // Programme change handler
        document.getElementById('programme_id').addEventListener('change', function() {
            const programmeId = this.value;
            const courseList = document.getElementById('courseList');
            
            if (programmeId) {
                // In a real implementation, you would fetch courses via AJAX
                courseList.innerHTML = '<p>Loading courses...</p>';
                
                // Simulate loading courses
                setTimeout(() => {
                    courseList.innerHTML = `
                        <div class="course-item">
                            <strong>Introduction to Computing</strong>
                            <div>CSC101 - 3 Credits</div>
                        </div>
                        <div class="course-item">
                            <strong>Mathematics for Computing</strong>
                            <div>MAT101 - 3 Credits</div>
                        </div>
                        <div class="course-item">
                            <strong>Communication Skills</strong>
                            <div>COM101 - 2 Credits</div>
                        </div>
                    `;
                }, 500);
            } else {
                courseList.innerHTML = '<p>Select a programme to see available courses</p>';
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
        });
    </script>
</body>
</html>