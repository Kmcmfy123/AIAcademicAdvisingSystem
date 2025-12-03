<?php
require_once __DIR__ . '/../includes/init.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        
        $result = $auth->login($email, $password);
        
        if ($result['success']) {
            // Redirect based on role
            switch ($result['role']) {
                case 'student':
                    redirect(APP_URL . '/main/student/dashboard.php');
                    break;
                case 'professor':
                    redirect(APP_URL . '/main/professor/dashboard.php');
                    break;
                case 'admin':
                    redirect(APP_URL . '/main/admin/dashboard.php');
                    break;
            }
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
</head>
<body>
    <div class="container" style="max-width: 450px; margin-top: 100px;">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Login</h1>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?= CSRF::tokenField() ?>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
            </form>
            
            <p style="text-align: center; margin-top: 1rem;">
                <a href="register.php">Register here</a>
            </p>
        </div>
    </div>
</body>
</html>