<?php
// Password update script for LSC SRMS
echo "<h1>LSC SRMS Password Update</h1>";

try {
    // Connect to the existing database
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=lscrms;charset=utf8mb4", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "<p>✅ Connected to lscrms database</p>";
    
    echo "<h2>🔐 Updating Password Hashes</h2>";
    
    // Update password hashes with proper PHP password_hash
    $password_updates = [
        ['username' => 'admin@lsc.ac.zm', 'password' => 'Admin@123'],
        ['username' => 'lecturer1@lsc.ac.zm', 'password' => 'Lecturer@123'],
        ['username' => 'finance@lsc.ac.zm', 'password' => 'Finance@123'],
        ['username' => 'LSC000001', 'password' => 'LSC000001'],
    ];
    
    foreach ($password_updates as $update) {
        $hash = password_hash($update['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
        $result = $stmt->execute([$hash, $update['username']]);
        
        if ($result) {
            echo "<p>✅ Updated password for: " . $update['username'] . "</p>";
        } else {
            echo "<p>❌ Failed to update password for: " . $update['username'] . "</p>";
        }
    }
    
    echo "<h2>📊 Database Verification</h2>";
    
    // Verify users were created
    $stmt = $pdo->query("SELECT u.username, r.name as role FROM users u 
                        JOIN user_roles ur ON u.id = ur.user_id 
                        JOIN roles r ON ur.role_id = r.id");
    $users = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Username</th><th>Role</th></tr>";
    foreach ($users as $user) {
        echo "<tr><td>" . htmlspecialchars($user['username']) . "</td><td>" . htmlspecialchars($user['role']) . "</td></tr>";
    }
    echo "</table>";
    
    echo "<h2>🎉 Password Update Complete!</h2>";
    echo "<p>You can now test the login system with the following credentials:</p>";
    echo "<ul>";
    echo "<li><strong>Super Admin:</strong> admin@lsc.ac.zm / Admin@123</li>";
    echo "<li><strong>Lecturer:</strong> lecturer1@lsc.ac.zm / Lecturer@123</li>";
    echo "<li><strong>Finance:</strong> finance@lsc.ac.zm / Finance@123</li>";
    echo "<li><strong>Student:</strong> LSC000001 / LSC000001</li>";
    echo "</ul>";
    
    echo "<p><a href='index.php'>Go to Login Page</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Make sure XAMPP MySQL is running and accessible.</p>";
}
?>