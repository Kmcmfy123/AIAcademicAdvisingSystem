<?php
// Show errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Direct database connection without auth check
define('DB_HOST', 'localhost');
define('DB_NAME', 'advising_system');
define('DB_USER', 'root');
define('DB_PASS', 'password_123');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if any admin already exists
$stmt = $pdo->query("SELECT 1 FROM users WHERE role = 'admin' LIMIT 1");
$adminExists = $stmt->fetch();

// TEMPORARILY DISABLED - Remove this comment block after creating your admin
/*
if ($adminExists) {
    header('Location: main/login.php');
    exit;
}
*/

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if (!$firstName || !$lastName || !$email || !$password) {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        try {
            // Hash the password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Insert admin user
            $stmt = $pdo->prepare(
                "INSERT INTO users (first_name, last_name, email, password_hash, role, created_at)
                 VALUES (?, ?, ?, ?, 'admin', NOW())"
            );
            $stmt->execute([$firstName, $lastName, $email, $passwordHash]);

            $success = true;
        } catch (Exception $e) {
            $error = 'Error creating admin: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Setup - AI Academic Advising System</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .setup-container {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
        }
        
        .setup-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .setup-header h1 {
            margin: 0;
            color: #1f2937;
            font-size: 1.8rem;
        }
        
        .setup-header p {
            color: #666;
            margin: 0.5rem 0 0 0;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        
        .btn-submit {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
        }
        
        .success-message {
            text-align: center;
        }
        
        .success-message p {
            margin: 1rem 0;
            color: #666;
        }
        
        .success-message a {
            display: inline-block;
            margin-top: 1.5rem;
            padding: 0.75rem 1.5rem;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: background 0.2s;
        }
        
        .success-message a:hover {
            background: #2563eb;
        }
    </style>
</head>

<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1>🔐 Admin Setup</h1>
            <p>Create your first admin account</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success success-message">
                <h2 style="margin-top: 0; color: #065f46;">✓ Admin Account Created!</h2>
                <p>Your admin account has been successfully created.</p>
                <p style="font-size: 0.9rem; color: #555;">
                    <strong>Email:</strong> <?= htmlspecialchars($email) ?><br>
                    <strong>Name:</strong> <?= htmlspecialchars($firstName . ' ' . $lastName) ?>
                </p>
                <a href="main/login.php">Go to Login</a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="first_name">First Name *</label>
                    <input type="text" id="first_name" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name *</label>
                    <input type="text" id="last_name" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" required minlength="6">
                    <small style="color: #666; display: block; margin-top: 0.25rem;">Minimum 6 characters</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                </div>

                <button type="submit" class="btn-submit">Create Admin Account</button>
            </form>

            <p style="text-align: center; color: #666; font-size: 0.9rem; margin-top: 1.5rem;">
                After setup, this page will be disabled.
            </p>
        <?php endif; ?>
    </div>
</body>
</html>
