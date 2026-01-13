<?php
require '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];

            $r = $pdo->prepare("SELECT r.name FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
            $r->execute([$user['id']]);
            $_SESSION['roles'] = $r->fetchAll(PDO::FETCH_COLUMN);

            // redirect based on role
            if (in_array('Super Admin', $_SESSION['roles'])) {
                header('Location: admin/dashboard.php');
            } elseif (in_array('Lecturer', $_SESSION['roles'])) {
                header('Location: lecturer/dashboard.php');
            } elseif (in_array('Sub Admin (Finance)', $_SESSION['roles'])) {
                header('Location: finance/dashboard.php');
            } elseif (in_array('Enrollment Officer', $_SESSION['roles'])) {
                header('Location: enrollment/dashboard.php');
            } elseif (in_array('Academics Coordinator', $_SESSION['roles'])) {
                header('Location: academics/dashboard.php');
            } elseif (in_array('HR Manager', $_SESSION['roles'])) {
                header('Location: human_resource/dashboard.php');
            } else {
                echo "Role not recognized";
            }
            exit;
        } else {
            $error = "Invalid username or password";
        }
    } else {
        $error = "Username and password are required";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Portal</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container">
    <div class="school-header">
        <h1>Lusaka South College</h1>
        <div class="subtitle">Staff Portal</div>
    </div>
    <h2>Login to Your Account</h2>
    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
    <form method="POST">
        <input type="text" name="username" placeholder="📧 Username" required>
        <input type="password" name="password" placeholder="🔒 Password" required>
        <button type="submit">🚀 Login</button>
    </form>
    <div class="back-link">
        <a href="../index.php">← Back to Home</a>
    </div>
</div>
</body>
</html>