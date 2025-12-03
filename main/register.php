<?php
require_once __DIR__ . '/../includes/init.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        $firstName = sanitize($_POST['first_name']);
        $lastName = sanitize($_POST['last_name']);
        $role = sanitize($_POST['role'] ?? 'student');
        
        // Validation
        if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
            $error = 'All fields are required';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters';
        } else {
            $result = $auth->register($email, $password, $firstName, $lastName, $role);
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
</head>
<body>
    <div class="container" style="max-width: 500px; margin-top: 50px;">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Create Account</h1>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= $success ?>
                    <br><a href="login.php">Click here to login</a>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <?= CSRF::tokenField() ?>
                    
                    <div class="form-group">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" class="form-control" required 
                               value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" class="form-control" required
                               value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-control">
                            <option value="student">Student</option>
                            <option value="professor">Professor</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Register</button>
                </form>
                
                <p style="text-align: center; margin-top: 1rem;">
                    <a href="login.php">Login here</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>