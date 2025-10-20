<?php
// Enrollment options page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enroll - LSC SRMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .enrollment-options {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin: 30px 0;
        }
        
        .enrollment-card {
            background: #f9f9f9;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 2px solid #e0e0e0;
        }
        
        .enrollment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            border-color: var(--primary-orange);
        }
        
        .enrollment-card h3 {
            color: var(--primary-green);
            margin-bottom: 15px;
            font-size: 22px;
        }
        
        .enrollment-card p {
            color: #666;
            margin-bottom: 20px;
            min-height: 60px;
        }
        
        .enrollment-card .btn {
            background: var(--primary-green);
            color: white;
            padding: 12px 25px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: background 0.3s ease;
        }
        
        .enrollment-card .btn:hover {
            background: var(--dark-green);
            text-decoration: none;
        }
        
        .icon {
            font-size: 48px;
            margin-bottom: 15px;
            color: var(--primary-orange);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="school-header">
            <h1>Lusaka South College</h1>
            <div class="subtitle">Student Enrollment System</div>
        </div>
        <h2>Choose Enrollment Type</h2>
        <p>Please select the type of program you wish to apply for:</p>
        
        <div class="enrollment-options">
            <div class="enrollment-card">
                <div class="icon">🎓</div>
                <h3>Undergraduate Programs</h3>
                <p>Apply for diploma and certificate programs for first-time university students.</p>
                <a href="undergraduate_application.php" class="btn">Apply Now</a>
            </div>
            
            <div class="enrollment-card">
                <div class="icon">📚</div>
                <h3>Short Courses</h3>
                <p>Professional development and skill enhancement courses for career growth.</p>
                <a href="short_courses_application.php" class="btn">Enroll Now</a>
            </div>
            
            <div class="enrollment-card">
                <div class="icon">🏢</div>
                <h3>Corporate Training</h3>
                <p>Customized training solutions for organizations and businesses.</p>
                <a href="corporate_training_application.php" class="btn">Request Training</a>
            </div>
        </div>
        
        <div class="back-link">
            <a href="index.php">← Back to Home</a>
        </div>
    </div>
</body>
</html>