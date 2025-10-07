<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup Guide - LSC SRMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .troubleshooting { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .step { background: #e8f5e8; padding: 15px; margin: 10px 0; border-left: 4px solid #28a745; }
        .warning { background: #fff3cd; padding: 15px; margin: 10px 0; border-left: 4px solid #ffc107; }
        .error { background: #f8d7da; padding: 15px; margin: 10px 0; border-left: 4px solid #dc3545; }
        code { background: #f1f1f1; padding: 2px 5px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container" style="max-width: 800px;">
        <div class="school-header">
            <h1>Database Setup Guide</h1>
            <div class="subtitle">LSC SRMS Database Configuration</div>
        </div>

        <h2>🚀 Quick Setup Options</h2>
        
        <div class="step">
            <h3>Option 1: Use XAMPP Control Panel (Recommended)</h3>
            <ol>
                <li>Open <strong>XAMPP Control Panel</strong> from Start Menu</li>
                <li>Click <strong>"Start"</strong> next to <strong>Apache</strong></li>
                <li>Click <strong>"Start"</strong> next to <strong>MySQL</strong></li>
                <li>Both services should show <strong>green "Running"</strong> status</li>
                <li>Once both are running, <a href="test_connection.php" class="btn">Test Database Connection</a></li>
            </ol>
        </div>

        <div class="step">
            <h3>Option 2: Manual Database Setup</h3>
            <ol>
                <li>If MySQL is running, <a href="setup_database.php" class="btn">Run Database Setup</a></li>
                <li>This will create the database and set up all user accounts</li>
                <li>After setup, <a href="test_connection.php" class="btn">Test Connection</a></li>
            </ol>
        </div>

        <div class="troubleshooting">
            <h3>🔧 Troubleshooting Common Issues</h3>
            
            <div class="warning">
                <h4>Issue 1: MySQL Won't Start</h4>
                <p><strong>Error:</strong> "InnoDB: The innodb_system data file 'ibdata1' must be writable"</p>
                <p><strong>Solution:</strong></p>
                <ol>
                    <li>Close XAMPP Control Panel completely</li>
                    <li>Right-click on XAMPP Control Panel and select <strong>"Run as Administrator"</strong></li>
                    <li>Start Apache and MySQL services</li>
                </ol>
            </div>

            <div class="warning">
                <h4>Issue 2: Port 80 Already in Use</h4>
                <p><strong>Error:</strong> Apache can't start on port 80</p>
                <p><strong>Solution:</strong></p>
                <ol>
                    <li>In XAMPP Control Panel, click <strong>"Config"</strong> next to Apache</li>
                    <li>Select <strong>"httpd.conf"</strong></li>
                    <li>Change <code>Listen 80</code> to <code>Listen 8080</code></li>
                    <li>Save and restart Apache</li>
                    <li>Access via <code>http://localhost:8080/srms/</code></li>
                </ol>
            </div>

            <div class="error">
                <h4>Issue 3: Database Connection Failed</h4>
                <p><strong>Error:</strong> "Database connection failed"</p>
                <p><strong>Check:</strong></p>
                <ul>
                    <li>MySQL service is running (green in XAMPP Control Panel)</li>
                    <li>No firewall blocking port 3306</li>
                    <li>XAMPP is running as Administrator</li>
                </ul>
            </div>
        </div>

        <h2>📋 Test Credentials</h2>
        <p>Once the database is set up, use these credentials to test login:</p>
        
        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <tr style="background: var(--primary-green); color: white;">
                <th style="padding: 10px; border: 1px solid #ddd;">User Type</th>
                <th style="padding: 10px; border: 1px solid #ddd;">Username</th>
                <th style="padding: 10px; border: 1px solid #ddd;">Password</th>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;"><strong>Super Admin</strong></td>
                <td style="padding: 10px; border: 1px solid #ddd;">admin@lsc.ac.zm</td>
                <td style="padding: 10px; border: 1px solid #ddd;">Admin@123</td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;"><strong>Lecturer</strong></td>
                <td style="padding: 10px; border: 1px solid #ddd;">lecturer1@lsc.ac.zm</td>
                <td style="padding: 10px; border: 1px solid #ddd;">Lecturer@123</td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;"><strong>Finance Officer</strong></td>
                <td style="padding: 10px; border: 1px solid #ddd;">finance@lsc.ac.zm</td>
                <td style="padding: 10px; border: 1px solid #ddd;">Finance@123</td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;"><strong>Student</strong></td>
                <td style="padding: 10px; border: 1px solid #ddd;">LSC000001</td>
                <td style="padding: 10px; border: 1px solid #ddd;">LSC000001</td>
            </tr>
        </table>

        <div class="mt-20">
            <a href="test_connection.php" class="btn">🔍 Test Database Connection</a>
            <a href="setup_database.php" class="btn btn-orange">🚀 Setup Database</a>
            <a href="index.php" class="btn">🏠 Back to Home</a>
        </div>

        <div class="step">
            <h3>✅ Next Steps After Database Setup</h3>
            <ol>
                <li><strong>Test Connection:</strong> Verify database is accessible</li>
                <li><strong>Setup Database:</strong> Create tables and user accounts</li>
                <li><strong>Test Login:</strong> Try logging in with each user type</li>
                <li><strong>Verify Dashboards:</strong> Ensure each user reaches their correct dashboard</li>
            </ol>
        </div>
    </div>
</body>
</html>