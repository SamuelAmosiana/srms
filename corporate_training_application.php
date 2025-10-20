<?php
require 'config.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $company = trim($_POST['company']);
    $industry = $_POST['industry'];
    $company_size = $_POST['companysize'];
    $address = trim($_POST['address']);
    $contact_name = trim($_POST['contactname']);
    $position = trim($_POST['position']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $training_type = $_POST['trainingtype'];
    $participants = $_POST['participants'];
    $duration = $_POST['duration'];
    $location = $_POST['location'];
    $budget = $_POST['budget'];
    $specific_needs = trim($_POST['specificneeds']);
    $timeline = trim($_POST['timeline']);
    
    try {
        // Insert application into database
        $stmt = $pdo->prepare("INSERT INTO applications (full_name, email, documents) VALUES (?, ?, ?)");
        $stmt->execute([
            $company,
            $email,
            json_encode([
                'type' => 'corporate_training',
                'company' => $company,
                'industry' => $industry,
                'company_size' => $company_size,
                'address' => $address,
                'contact_name' => $contact_name,
                'position' => $position,
                'phone' => $phone,
                'training_type' => $training_type,
                'participants' => $participants,
                'duration' => $duration,
                'location' => $location,
                'budget' => $budget,
                'specific_needs' => $specific_needs,
                'timeline' => $timeline
            ])
        ]);
        
        $success = "Your training request has been submitted successfully! Our corporate training team will review your request and contact you soon to discuss the details.";
    } catch (Exception $e) {
        $error = "There was an error submitting your request. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Corporate Training Application - LSC SRMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .application-form {
            text-align: left;
            background: #f9f9f9;
            padding: 25px;
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
            padding: 20px;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="school-header">
            <h1>Lusaka South College</h1>
            <div class="subtitle">Corporate Training Application</div>
        </div>
        
        <h2>Corporate Training Application</h2>
        <p>Professional training solutions for your organization</p>
        
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
            <h4>Corporate Training Services Available For 2025</h4>
            <ul>
                <li>Leadership Development Programs</li>
                <li>Team Building & Communication</li>
                <li>Technical Skills Training</li>
                <li>Customer Service Excellence</li>
                <li>Health & Safety Training</li>
                <li>Customized Training Solutions</li>
            </ul>
        </div>
        
        <form class="application-form" method="POST">
            <div class="form-section">
                <h3>Organization Information</h3>
                <div class="form-group">
                    <label for="ct-company">Company/Organization Name *</label>
                    <input type="text" id="ct-company" name="company" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="ct-industry">Industry *</label>
                        <select id="ct-industry" name="industry" required>
                            <option value="">Select Industry</option>
                            <option value="manufacturing">Manufacturing</option>
                            <option value="healthcare">Healthcare</option>
                            <option value="education">Education</option>
                            <option value="finance">Finance & Banking</option>
                            <option value="retail">Retail</option>
                            <option value="technology">Technology</option>
                            <option value="government">Government</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ct-size">Company Size *</label>
                        <select id="ct-size" name="companysize" required>
                            <option value="">Select Size</option>
                            <option value="1-10">1-10 employees</option>
                            <option value="11-50">11-50 employees</option>
                            <option value="51-200">51-200 employees</option>
                            <option value="201-500">201-500 employees</option>
                            <option value="500+">500+ employees</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="ct-address">Company Address *</label>
                    <textarea id="ct-address" name="address" rows="3" required></textarea>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Contact Person Details</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="ct-contact-name">Contact Person Name *</label>
                        <input type="text" id="ct-contact-name" name="contactname" required>
                    </div>
                    <div class="form-group">
                        <label for="ct-position">Position/Title *</label>
                        <input type="text" id="ct-position" name="position" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="ct-email">Email Address *</label>
                        <input type="email" id="ct-email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="ct-phone">Phone Number *</label>
                        <input type="tel" id="ct-phone" name="phone" required>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Training Requirements</h3>
                <div class="form-group">
                    <label for="ct-training-type">Training Type *</label>
                    <select id="ct-training-type" name="trainingtype" required>
                        <option value="">Select Training Type</option>
                        <option value="leadership">Leadership Development Programs</option>
                        <option value="team-building">Team Building & Communication</option>
                        <option value="technical">Technical Skills Training</option>
                        <option value="customer-service">Customer Service Excellence</option>
                        <option value="health-safety">Health & Safety Training</option>
                        <option value="custom">Customized Training Solutions</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="ct-participants">Number of Participants *</label>
                        <input type="number" id="ct-participants" name="participants" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="ct-duration">Preferred Duration *</label>
                        <select id="ct-duration" name="duration" required>
                            <option value="">Select Duration</option>
                            <option value="half-day">Half Day (4 hours)</option>
                            <option value="full-day">Full Day (8 hours)</option>
                            <option value="2-days">2 Days</option>
                            <option value="1-week">1 Week</option>
                            <option value="custom">Custom Duration</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="ct-location">Training Location *</label>
                        <select id="ct-location" name="location" required>
                            <option value="">Select Location</option>
                            <option value="on-site">On-site (Your premises)</option>
                            <option value="lsuc-campus">LSUC Campus</option>
                            <option value="online">Online Training</option>
                            <option value="hybrid">Hybrid (Online + On-site)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ct-budget">Budget Range</label>
                        <select id="ct-budget" name="budget">
                            <option value="">Select Budget Range</option>
                            <option value="under-5000">Under K5,000</option>
                            <option value="5000-15000">K5,000 - K15,000</option>
                            <option value="15000-30000">K15,000 - K30,000</option>
                            <option value="over-30000">Over K30,000</option>
                            <option value="discuss">To be discussed</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="ct-specific-needs">Specific Training Needs/Objectives *</label>
                    <textarea id="ct-specific-needs" name="specificneeds" rows="4" required placeholder="Please describe your specific training objectives, challenges you want to address, and desired outcomes"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="ct-timeline">Preferred Timeline</label>
                    <textarea id="ct-timeline" name="timeline" rows="2" placeholder="When would you like to conduct this training?"></textarea>
                </div>
            </div>
            
            <button type="submit" class="submit-btn">Request Training Proposal</button>
        </form>
    </div>
</body>
</html>