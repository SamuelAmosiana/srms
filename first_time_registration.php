<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

// Check if user is logged in
if (!isset($_SESSION['temp_user_id'])) {
    header('Location: student_login.php');
    exit();
}

// Get user info
$temp_user_id = $_SESSION['temp_user_id'];
$stmt = $pdo->prepare("SELECT * FROM pending_students WHERE id = ?");
$stmt->execute([$temp_user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: student_login.php');
    exit();
}

// Check if registration is already approved
if ($user['registration_status'] === 'approved' && !empty($user['student_number'])) {
    header('Location: registration_success.php');
    exit();
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'submit_registration':
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
                    
                    $file_extension = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
                    $file_name = uniqid() . '.' . $file_extension;
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $file_path)) {
                        $payment_proof = $file_path;
                    }
                }
                
                try {
                    // Update pending student with registration details
                    $stmt = $pdo->prepare("UPDATE pending_students SET 
                        intake_id = ?, 
                        programme_id = ?, 
                        payment_method = ?, 
                        payment_amount = ?, 
                        transaction_id = ?, 
                        payment_proof = ?,
                        registration_status = 'pending_approval',
                        updated_at = NOW()
                        WHERE id = ?");
                    $stmt->execute([$intake_id, $programme_id, $payment_method, $payment_amount, $transaction_id, $payment_proof, $temp_user_id]);
                    
                    $message = "Registration submitted successfully! Waiting for admin approval.";
                    $messageType = 'success';
                    
                    // Refresh user data
                    $stmt = $pdo->prepare("SELECT * FROM pending_students WHERE id = ?");
                    $stmt->execute([$temp_user_id]);
                    $user = $stmt->fetch();
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get available intakes
$intakes = $pdo->query("SELECT * FROM intake WHERE status = 'active' ORDER BY start_date ASC")->fetchAll();

// Get available programmes
$programmes = $pdo->query("SELECT * FROM programme WHERE is_active = 1 ORDER BY name")->fetchAll();

// Get user's existing registration data
$stmt = $pdo->prepare("SELECT * FROM pending_students WHERE id = ?");
$stmt->execute([$temp_user_id]);
$registration_data = $stmt->fetch();
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
        
        <div class="nav-right">
            <div class="user-info">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($user['full_name']); ?></span>
            </div>
            
            <div class="nav-actions">
                <a href="logout.php" class="btn btn-outline">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-user-graduate"></i> First Time Registration</h1>
            <p>Complete your registration to become a full-time student</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="registration-container">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="submit_registration">
                
                <!-- Programme Selection -->
                <div class="form-section">
                    <h3><i class="fas fa-graduation-cap"></i> Programme Information</h3>
                    <div class="form-group">
                        <label for="programme_id">Select Programme *</label>
                        <select id="programme_id" name="programme_id" required>
                            <option value="">-- Select Programme --</option>
                            <?php foreach ($programmes as $programme): ?>
                                <option value="<?php echo $programme['id']; ?>" <?php echo ($registration_data['programme_id'] == $programme['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($programme['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="intake_id">Select Intake *</label>
                        <select id="intake_id" name="intake_id" required>
                            <option value="">-- Select Intake --</option>
                            <?php foreach ($intakes as $intake): ?>
                                <option value="<?php echo $intake['id']; ?>" <?php echo ($registration_data['intake_id'] == $intake['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($intake['name']); ?> 
                                    (<?php echo date('M Y', strtotime($intake['start_date'])); ?> - <?php echo date('M Y', strtotime($intake['end_date'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                               value="<?php echo htmlspecialchars($registration_data['payment_amount'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="transaction_id">Transaction ID *</label>
                        <input type="text" id="transaction_id" name="transaction_id" required 
                               value="<?php echo htmlspecialchars($registration_data['transaction_id'] ?? ''); ?>"
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