<?php
require '../config.php';

// Create emergency access table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_emergency_access (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        granted_by INT NOT NULL,
        granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NULL,
        reason TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_access (user_id)
    )");
} catch (Exception $e) {
    // Table might already exist
}

// Function to check if maintenance mode is enabled
function isMaintenanceModeEnabled($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result && $result['setting_value'] === '1';
    } catch (Exception $e) {
        return false;
    }
}

// Function to get maintenance end time
function getMaintenanceEndTime($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_end_time'");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : null;
    } catch (Exception $e) {
        return null;
    }
}

// Function to check if current user has emergency access during maintenance
function hasEmergencyAccess($pdo, $userId) {
    if (!$userId) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM maintenance_emergency_access WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())");
        $stmt->execute([$userId]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

// Function to check if current user is an administrator
function isCurrentUserAdmin($pdo) {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }
    
    // Check for user-specific permission override first (if table exists)
    try {
        $stmt = $pdo->prepare("SELECT r.name FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return in_array('Super Admin', $roles);
    } catch (Exception $e) {
        return false;
    }
}

// Check maintenance mode and restrict access if needed
function checkMaintenanceMode($pdo) {
    // If maintenance mode is enabled and user is not admin, show maintenance page
    if (isMaintenanceModeEnabled($pdo) && !isCurrentUserAdmin($pdo)) {
        // Check if user has emergency access
        $userId = $_SESSION['user_id'] ?? null;
        if (!hasEmergencyAccess($pdo, $userId)) {
            // Show maintenance mode page
            // Get maintenance end time
            $maintenance_end_time = getMaintenanceEndTime($pdo);
            $end_timestamp = $maintenance_end_time ? strtotime($maintenance_end_time) : time() + (2 * 24 * 60 * 60); // Default to 2 days from now
            
            http_response_code(503);
            echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lusaka South College - Under Maintenance</title>
    <link rel="icon" type="image/jpeg" href="assets/images/school_logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-orange: #FF8C00;
            --secondary-green: #2E8B57;
            --light-orange: #FFE4B5;
            --light-green: #98FB98;
            --dark-orange: #CC7000;
            --dark-green: #1D5C3A;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: \'Poppins\', sans-serif;
            background: linear-gradient(135deg, #f9f9f9 0%, #f0f0f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #333;
        }
        
        .maintenance-container {
            max-width: 1200px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            position: relative;
        }
        
        .header-section {
            background: linear-gradient(to right, var(--primary-orange), var(--secondary-green));
            padding: 40px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .header-section::before {
            content: \'\';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url(\'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path fill="rgba(255,255,255,0.05)" d="M0,0 L100,0 L100,100 Z"/><path fill="rgba(255,255,255,0.1)" d="M0,0 L0,100 L100,100 Z"/></svg>\');
            background-size: cover;
        }
        
        .school-logo {
            font-size: 3rem;
            margin-bottom: 20px;
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 20px;
            border-radius: 50%;
            width: 120px;
            height: 120px;
            line-height: 80px;
        }
        
        .school-name {
            font-family: \'Montserrat\', sans-serif;
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .school-tagline {
            font-size: 1.2rem;
            opacity: 0.9;
            font-weight: 300;
            margin-bottom: 10px;
        }
        
        .content-section {
            padding: 60px 40px;
            text-align: center;
        }
        
        .maintenance-icon {
            font-size: 5rem;
            color: var(--primary-orange);
            margin-bottom: 30px;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-20px);
            }
            60% {
                transform: translateY(-10px);
            }
        }
        
        .maintenance-title {
            font-size: 2.5rem;
            color: var(--dark-orange);
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .maintenance-message {
            font-size: 1.2rem;
            line-height: 1.8;
            color: #555;
            max-width: 800px;
            margin: 0 auto 40px;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin: 50px 0;
        }
        
        .feature-item {
            padding: 25px;
            background: #f9f9f9;
            border-radius: 15px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .feature-item:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .feature-icon {
            font-size: 2.5rem;
            color: var(--secondary-green);
            margin-bottom: 15px;
        }
        
        .feature-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark-green);
        }
        
        .feature-description {
            color: #666;
            font-size: 1rem;
        }
        
        .application-section {
            background: linear-gradient(to right, #f8f9fa, #f0f0f0);
            padding: 50px 40px;
            border-radius: 15px;
            margin: 40px 0;
        }
        
        .section-title {
            font-size: 2rem;
            color: var(--dark-green);
            margin-bottom: 30px;
            font-weight: 700;
        }
        
        .btn-application {
            padding: 18px 40px;
            font-size: 1.2rem;
            font-weight: 600;
            border-radius: 50px;
            margin: 10px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 250px;
        }
        
        .btn-undergrad {
            background: linear-gradient(to right, var(--primary-orange), var(--dark-orange));
            color: white;
            border: none;
        }
        
        .btn-undergrad:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(255, 140, 0, 0.3);
            color: white;
        }
        
        .btn-shortcourse {
            background: linear-gradient(to right, var(--secondary-green), var(--dark-green));
            color: white;
            border: none;
        }
        
        .btn-shortcourse:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(46, 139, 87, 0.3);
            color: white;
        }
        
        .btn-icon {
            margin-right: 10px;
            font-size: 1.3rem;
        }
        
        .contact-section {
            padding: 40px;
            background: var(--light-green);
            border-radius: 15px;
            margin-top: 40px;
        }
        
        .contact-title {
            font-size: 1.8rem;
            color: var(--dark-green);
            margin-bottom: 25px;
            font-weight: 700;
        }
        
        .contact-info {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            min-width: 250px;
        }
        
        .contact-icon {
            font-size: 1.8rem;
            color: var(--primary-orange);
            margin-right: 15px;
        }
        
        .contact-details h4 {
            font-size: 1rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .contact-details p {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark-green);
            margin-bottom: 0;
        }
        
        .countdown-section {
            margin: 40px 0;
            padding: 30px;
            background: linear-gradient(to right, var(--light-orange), #fff5e6);
            border-radius: 15px;
        }
        
        .countdown-title {
            font-size: 1.5rem;
            color: var(--dark-orange);
            margin-bottom: 20px;
        }
        
        .countdown {
            display: flex;
            justify-content: center;
            gap: 20px;
        }
        
        .countdown-item {
            background: white;
            padding: 20px;
            border-radius: 10px;
            min-width: 100px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .countdown-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-orange);
            line-height: 1;
        }
        
        .countdown-label {
            font-size: 1rem;
            color: #666;
            margin-top: 5px;
        }
        
        .footer {
            padding: 30px;
            background: #333;
            color: white;
            text-align: center;
        }
        
        .social-icons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 20px 0;
        }
        
        .social-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: white;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }
        
        .social-icon:hover {
            background: var(--primary-orange);
            transform: translateY(-5px);
        }
        
        .copyright {
            margin-top: 20px;
            color: #aaa;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .school-name {
                font-size: 2rem;
            }
            
            .maintenance-title {
                font-size: 2rem;
            }
            
            .btn-application {
                min-width: 100%;
                margin: 10px 0;
            }
            
            .countdown {
                flex-wrap: wrap;
            }
            
            .countdown-item {
                min-width: 80px;
            }
            
            .content-section, .header-section {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <!-- Header Section -->
        <div class="header-section">
            <div class="school-logo">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h1 class="school-name">LUSAKA SOUTH COLLEGE</h1>
            <p class="school-tagline">Dream, Explore, Acquire</p>
            <p class="school-tagline">Temporal Page - Back Soon...!</p>
        </div>
        
        <!-- Main Content -->
        <div class="content-section">
            <div class="maintenance-icon">
                <i class="fas fa-tools"></i>
            </div>
            
            <h2 class="maintenance-title">System Under Maintenance</h2>
            
            <p class="maintenance-message">
                System Maintenance in Progress...
    We are currently performing scheduled maintenance to improve your experience. We apologize for any inconvenience.
            </p>
            
            <div class="countdown-section">
                <h3 class="countdown-title">Estimated Time to Completion</h3>
                <div class="countdown">
                    <div class="countdown-item">
                        <div class="countdown-value" id="days">00</div>
                        <div class="countdown-label">Days</div>
                    </div>
                    <div class="countdown-item">
                        <div class="countdown-value" id="hours">00</div>
                        <div class="countdown-label">Hours</div>
                    </div>
                    <div class="countdown-item">
                        <div class="countdown-value" id="minutes">00</div>
                        <div class="countdown-label">Mins</div>
                    </div>
                    <div class="countdown-item">
                        <div class="countdown-value" id="seconds">00</div>
                        <div class="countdown-label">Secs</div>
                    </div>
                </div>
            </div>
            
            <div class="application-section">
                <h3 class="section-title">Continue with Your Application</h3>
                <p>While our main system is under maintenance, you can still apply through our temporary application portal:</p>
                
                <div class="d-flex flex-wrap justify-content-center">
                    <a href="https://forms.gle/xCtGQUCJyDr7qV1NA" class="btn btn-application btn-undergrad" target="_blank">
                        <i class="fas fa-graduation-cap btn-icon"></i>
                        Undergraduate Application
                    </a>
                    <a href="https://forms.gle/ifhQCtbiebTKRLwR8" class="btn btn-application btn-shortcourse" target="_blank">
                        <i class="fas fa-book btn-icon"></i>
                        Short Courses Application
                    </a>
                </div>
            </div>
            
            <div class="contact-section">
                <h3 class="contact-title">Need Immediate Assistance?</h3>
                <div class="contact-info">
                    <div class="contact-item">
                        <i class="fas fa-phone contact-icon"></i>
                        <div class="contact-details">
                            <h4>Phone</h4>
                            <p>0770359518</p><br>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope contact-icon"></i>
                        <div class="contact-details">
                            <h4>Email</h4>
                            <p>admissions@lsuczm.com</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt contact-icon"></i>
                        <div class="contact-details">
                            <h4>Location</h4>
                            <p>Lusaka, Zambia</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p class="school-name mb-3">LUSAKA SOUTH COLLEGE</p>
            <p class="school-tagline mb-3">Quality Education for a Better Future</p>
            <div class="social-icons">
                <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
            </div>
            <p class="copyright">© 2023 Lusaka South College. All Rights Reserved.</p>
        </div>
    </div>
    
    <script>
        // Calculate the end time from PHP
        const maintenanceEndTime = new Date(' . $end_timestamp . ' * 1000);
        
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = maintenanceEndTime - now;

            // Time calculations for days, hours, minutes and seconds
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            // If the count down is finished, write some text
            if (distance < 0) {
                clearInterval(x);
                document.getElementById("days").innerHTML = "00";
                document.getElementById("hours").innerHTML = "00";
                document.getElementById("minutes").innerHTML = "00";
                document.getElementById("seconds").innerHTML = "00";
                return;
            }
            
            // Display the results
            document.getElementById("days").innerHTML = days.toString().padStart(2, "0");
            document.getElementById("hours").innerHTML = hours.toString().padStart(2, "0");
            document.getElementById("minutes").innerHTML = minutes.toString().padStart(2, "0");
            document.getElementById("seconds").innerHTML = seconds.toString().padStart(2, "0");
        }

        // Update the count down every 1 second
        const x = setInterval(updateCountdown, 1000);

        // Initialize countdown
        updateCountdown();
    </script>
</body>
</html>';
            exit();
        }
    }
}

// Run maintenance mode check on every page load (except for login and settings pages)
$currentScript = basename($_SERVER['SCRIPT_NAME']);
if (!in_array($currentScript, ['login.php', 'settings.php'])) {
    checkMaintenanceMode($pdo);
}

function currentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get user information
function get_user_info($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Check if user has specific permission
function has_permission($permission_name) {
    global $pdo;
    return currentUserHasPermission($permission_name, $pdo);
}

function currentUserHasRole($roleName, $pdo) {
    if (!isset($_SESSION['roles'])) {
        if (!currentUserId()) return false;
        $stmt = $pdo->prepare("SELECT r.name FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
        $stmt->execute([currentUserId()]);
        $_SESSION['roles'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    return in_array($roleName, $_SESSION['roles']);
}

function requireRole($roleName, $pdo) {
    if (!currentUserHasRole($roleName, $pdo)) {
        http_response_code(403);
        die('Forbidden - insufficient permissions');
    }
}

// Enhanced permission checking functions

// Get all permissions for current user
function getCurrentUserPermissions($pdo) {
    if (!currentUserId()) return [];
    
    // Get role-based permissions
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.name FROM permissions p
        JOIN role_permissions rp ON p.id = rp.permission_id
        JOIN user_roles ur ON rp.role_id = ur.role_id
        WHERE ur.user_id = ?
    ");
    $stmt->execute([currentUserId()]);
    $rolePermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get user-specific permission overrides (if table exists)
    $userOverrides = [];
    try {
        $stmt = $pdo->prepare("
            SELECT p.name, up.granted FROM permissions p
            JOIN user_permissions up ON p.id = up.permission_id
            WHERE up.user_id = ?
        ");
        $stmt->execute([currentUserId()]);
        $userOverrides = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        // user_permissions table might not exist, ignore error
    }
    
    // Merge role permissions with user overrides
    $finalPermissions = [];
    foreach ($rolePermissions as $perm) {
        // If user override exists and it's denied (0), skip this permission
        if (isset($userOverrides[$perm]) && $userOverrides[$perm] == 0) {
            continue;
        }
        $finalPermissions[] = $perm;
    }
    
    // Add any additional permissions that were explicitly granted to the user
    foreach ($userOverrides as $perm => $granted) {
        if ($granted == 1 && !in_array($perm, $rolePermissions)) {
            $finalPermissions[] = $perm;
        }
    }
    
    return array_unique($finalPermissions);
}

// Check if current user has a specific permission
function currentUserHasPermission($permissionName, $pdo) {
    $permissions = getCurrentUserPermissions($pdo);
    return in_array($permissionName, $permissions);
}

function requirePermission($permissionName, $pdo) {
    if (!currentUserHasPermission($permissionName, $pdo)) {
        http_response_code(403);
        die('Forbidden - insufficient permissions to access this resource');
    }
}

// Check if current user can manage a specific user (for part-time/intern restrictions)
function canManageUser($targetUserId, $pdo) {
    if (!currentUserId()) return false;
    
    // Super Admin can manage everyone
    if (currentUserHasRole('Super Admin', $pdo)) {
        return true;
    }
    
    // Users can't manage themselves through this function
    if (currentUserId() == $targetUserId) {
        return false;
    }
    
    // Check if user has manage_users permission
    return currentUserHasPermission('manage_users', $pdo);
}

// Logout function
function logout() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: login.php');
    exit;
}
?>