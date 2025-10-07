<?php
// Simple database connection test
echo "<h2>Database Connection Test</h2>";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;charset=utf8mb4", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "<p>✅ MySQL Connection: SUCCESS</p>";
    
    // Check if database exists
    $stmt = $pdo->query("SHOW DATABASES LIKE 'lscrms'");
    $db_exists = $stmt->fetch();
    
    if ($db_exists) {
        echo "<p>✅ Database 'lscrms': EXISTS</p>";
        
        // Connect to lscrms database
        $pdo = new PDO("mysql:host=127.0.0.1;dbname=lscrms;charset=utf8mb4", 'root', '');
        
        // Check users table
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $user_count = $stmt->fetch();
        echo "<p>📊 Users in database: " . $user_count['count'] . "</p>";
        
        // List all users
        $stmt = $pdo->query("SELECT username FROM users");
        $users = $stmt->fetchAll();
        echo "<p>👥 Users: ";
        foreach ($users as $user) {
            echo $user['username'] . " ";
        }
        echo "</p>";
        
    } else {
        echo "<p>❌ Database 'lscrms': NOT FOUND</p>";
        echo "<p>🔧 <a href='setup_database.php'>Click here to set up database</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Database Error: " . $e->getMessage() . "</p>";
    echo "<p>🔧 Please make sure XAMPP MySQL is running</p>";
    
    // Show available services
    echo "<h3>Troubleshooting:</h3>";
    echo "<ol>";
    echo "<li>Open XAMPP Control Panel</li>";
    echo "<li>Start Apache service</li>";
    echo "<li>Start MySQL service</li>";
    echo "<li>Refresh this page</li>";
    echo "</ol>";
}

echo "<p><a href='index.php'>← Back to Home</a></p>";
?>