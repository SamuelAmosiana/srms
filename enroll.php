<?php
// Enrollment options page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enroll - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="assets/images/school_logo.jpg">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .container {
            max-width: 1200px;
        }
        
        .enrollment-options {
            display: flex;
            flex-direction: row;
            gap: 30px;
            margin: 30px 0;
            justify-content: space-between;
        }
        
        .enrollment-card {
            background: #f9f9f9;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 2px solid #e0e0e0;
            flex: 1;
            min-width: 300px;
        }
        
        .enrollment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            border-color: var(--primary-orange);
        }
        
        .enrollment-card h3 {
            color: var(--primary-green);
            margin-bottom: 15px;
            font-size: 24px;
        }
        
        .enrollment-card p {
            color: #666;
            margin-bottom: 25px;
            min-height: 80px;
        }
        
        .enrollment-card .btn {
            background: var(--primary-green);
            color: white;
            padding: 15px 30px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: background 0.3s ease;
            font-size: 18px;
        }
        
        .enrollment-card .btn:hover {
            background: var(--dark-green);
            text-decoration: none;
        }
        
        .icon {
            font-size: 60px;
            margin-bottom: 20px;
            color: var(--primary-orange);
        }
        
        @media (max-width: 992px) {
            .enrollment-options {
                flex-direction: column;
            }
            
            .enrollment-card {
                min-width: 100%;
            }
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
            
        </div>
        
        <div class="back-link">
            <a href="index.php">← Back to Home</a>
        </div>
    </div>
</body>
</html>