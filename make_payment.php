<?php
require_once 'config.php';

// Generate unique transaction reference
function generateTransactionReference() {
    return 'TXN' . date('Ymd') . substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 10);
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $full_name = trim($_POST['full_name'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $student_or_application_id = trim($_POST['student_or_application_id'] ?? '');
    
    // Validation
    if (empty($full_name) || empty($phone_number) || empty($amount) || empty($category)) {
        $message = 'Please fill in all required fields.';
        $messageType = 'error';
    } elseif (!preg_match('/^[\d\-\+\(\)\s]+$/', $phone_number)) {
        $message = 'Please enter a valid phone number.';
        $messageType = 'error';
    } elseif ($amount <= 0) {
        $message = 'Amount must be greater than zero.';
        $messageType = 'error';
    } elseif (!in_array($category, ['Application Fee', 'Tuition / School Fees', 'Other'])) {
        $message = 'Invalid payment category.';
        $messageType = 'error';
    } else {
        // Generate transaction reference
        $transaction_reference = generateTransactionReference();
        
        try {
            // Insert into payments table
            $stmt = $pdo->prepare("
                INSERT INTO payments (
                    transaction_reference,
                    full_name,
                    phone_number,
                    amount,
                    category,
                    description,
                    student_or_application_id,
                    status,
                    source
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'public')
            ");
            
            $result = $stmt->execute([
                $transaction_reference,
                $full_name,
                $phone_number,
                $amount,
                $category,
                $description,
                $student_or_application_id
            ]);
            
            if ($result) {
                $action = $_POST['action'] ?? '';
                
                if ($action === 'swish') {
                    // Process Swish payment - redirect to process endpoint
                    header("Location: api/process_swish_payment.php?transaction_reference=$transaction_reference");
                    exit;
                } else {
                    // Redirect to internal finance tracking
                    header("Location: finance/track_payment.php?ref=$transaction_reference");
                    exit;
                }
            } else {
                $message = 'Error saving payment information.';
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $message = 'Error processing payment: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment - SRMS</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #2E8B57;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        input[type="text"],
        input[type="tel"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        textarea {
            height: 100px;
            resize: vertical;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #2E8B57;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        
        .btn:hover {
            background-color: #228B22;
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-group {
            text-align: center;
            margin-top: 20px;
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
        
        .required {
            color: red;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-money-bill-wave"></i> Make Payment</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="paymentForm">
            <div class="form-group">
                <label for="full_name">Full Name <span class="required">*</span></label>
                <input type="text" id="full_name" name="full_name" required>
            </div>
            
            <div class="form-group">
                <label for="phone_number">Phone Number (Mobile Money) <span class="required">*</span></label>
                <input type="tel" id="phone_number" name="phone_number" placeholder="+260970000000" required>
            </div>
            
            <div class="form-group">
                <label for="amount">Amount (ZMW) <span class="required">*</span></label>
                <input type="number" id="amount" name="amount" step="0.01" min="0.01" required>
            </div>
            
            <div class="form-group">
                <label for="category">Payment Category <span class="required">*</span></label>
                <select id="category" name="category" required>
                    <option value="">-- Select Category --</option>
                    <option value="Application Fee">Application Fee</option>
                    <option value="Tuition / School Fees">Tuition / School Fees</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="description">Payment Description</label>
                <textarea id="description" name="description" placeholder="Brief description of the payment"></textarea>
            </div>
            
            <div class="form-group">
                <label for="student_or_application_id">Student ID / Application Number</label>
                <input type="text" id="student_or_application_id" name="student_or_application_id" placeholder="LSC000001">
            </div>
            
            <div class="btn-group">
                <input type="hidden" name="action" value="swish">
                <button type="submit" class="btn">Make Payment (Swish)</button>
                
                <input type="hidden" name="action" value="submit">
                <button type="submit" class="btn btn-secondary">Submit Payment</button>
            </div>
        </form>
    </div>
    
    <script>
        // Basic form validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const fullName = document.getElementById('full_name').value.trim();
            const phone = document.getElementById('phone_number').value.trim();
            const amount = parseFloat(document.getElementById('amount').value);
            const category = document.getElementById('category').value;
            
            if (!fullName || !phone || isNaN(amount) || amount <= 0 || !category) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            // Validate phone number format
            const phoneRegex = /^[\d\-\+\(\)\s]+$/;
            if (!phoneRegex.test(phone)) {
                e.preventDefault();
                alert('Please enter a valid phone number.');
                return false;
            }
        });
    </script>
</body>
</html>