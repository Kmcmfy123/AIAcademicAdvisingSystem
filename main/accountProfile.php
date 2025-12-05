<?php
require_once __DIR__ . '/../../includes/init.php';
$auth->requireLogin();

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$message = '';
$error = '';

// Get user data
$user = $db->fetchOne(
    "SELECT * FROM users WHERE id = ?",
    [$userId]
);

// Get role-specific profile
if ($userRole === 'student') {
    $profile = $db->fetchOne(
        "SELECT * FROM student_profiles WHERE user_id = ?",
        [$userId]
    );
} elseif ($userRole === 'professor') {
    $profile = $db->fetchOne(
        "SELECT * FROM professor_profiles WHERE user_id = ?",
        [$userId]
    );
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        if ($_POST['action'] === 'update_info') {
            $firstName = sanitize($_POST['first_name']);
            $lastName = sanitize($_POST['last_name']);
            $email = sanitize($_POST['email']);
            
            $result = $db->execute(
                "UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?",
                [$firstName, $lastName, $email, $userId]
            );
            
            if ($result) {
                // Update role-specific info
                if ($userRole === 'student') {
                    $major = sanitize($_POST['major']);
                    $yearLevel = sanitize($_POST['year_level']);
                    $studentId = sanitize($_POST['student_id']);
                    
                    $db->execute(
                        "UPDATE student_profiles SET major = ?, year_level = ?, student_id = ? WHERE user_id = ?",
                        [$major, $yearLevel, $studentId, $userId]
                    );
                } elseif ($userRole === 'professor') {
                    $department = sanitize($_POST['department']);
                    $specialization = sanitize($_POST['specialization']);
                    
                    $db->execute(
                        "UPDATE professor_profiles SET department = ?, specialization = ? WHERE user_id = ?",
                        [$department, $specialization, $userId]
                    );
                }
                
                $message = 'Profile updated successfully!';
                // Refresh user data
                $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
                if ($userRole === 'student') {
                    $profile = $db->fetchOne("SELECT * FROM student_profiles WHERE user_id = ?", [$userId]);
                } elseif ($userRole === 'professor') {
                    $profile = $db->fetchOne("SELECT * FROM professor_profiles WHERE user_id = ?", [$userId]);
                }
            } else {
                $error = 'Failed to update profile.';
            }
        } elseif ($_POST['action'] === 'change_password') {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            // Verify current password
            if (!password_verify($currentPassword, $user['password'])) {
                $error = 'Current password is incorrect.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'New passwords do not match.';
            } elseif (strlen($newPassword) < 8) {
                $error = 'Password must be at least 8 characters.';
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $result = $db->execute(
                    "UPDATE users SET password = ? WHERE id = ?",
                    [$hashedPassword, $userId]
                );
                
                if ($result) {
                    $message = 'Password changed successfully!';
                } else {
                    $error = 'Failed to change password.';
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Profile - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="#" class="navbar-brand"><?= APP_NAME ?></a>
            <ul class="navbar-nav">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="profile.php" class="nav-link">Profile</a></li>
                <li><a href="../logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <h1 style="margin: 2rem 0 1rem;">My Profile</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <!-- Personal Information -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Personal Information</h2>
            </div>
            
            <form method="POST">
                <?= CSRF::tokenField() ?>
                <input type="hidden" name="action" value="update_info">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" class="form-control" required
                               value="<?= htmlspecialchars($user['first_name']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" class="form-control" required
                               value="<?= htmlspecialchars($user['last_name']) ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required
                           value="<?= htmlspecialchars($user['email']) ?>">
                </div>
                
                <?php if ($userRole === 'student'): ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Student ID</label>
                            <input type="text" name="student_id" class="form-control"
                                   value="<?= htmlspecialchars($profile['student_id'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Major</label>
                            <input type="text" name="major" class="form-control"
                                   value="<?= htmlspecialchars($profile['major'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Year Level</label>
                            <select name="year_level" class="form-control">
                                <option value="freshman" <?= ($profile['year_level'] ?? '') === 'freshman' ? 'selected' : '' ?>>Freshman</option>
                                <option value="sophomore" <?= ($profile['year_level'] ?? '') === 'sophomore' ? 'selected' : '' ?>>Sophomore</option>
                                <option value="junior" <?= ($profile['year_level'] ?? '') === 'junior' ? 'selected' : '' ?>>Junior</option>
                                <option value="senior" <?= ($profile['year_level'] ?? '') === 'senior' ? 'selected' : '' ?>>Senior</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Current GPA</label>
                            <input type="text" class="form-control" readonly
                                   value="<?= formatGPA($profile['gpa'] ?? null) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Credits Completed</label>
                            <input type="text" class="form-control" readonly
                                   value="<?= $profile['credits_completed'] ?? 0 ?> units">
                        </div>
                    </div>
                    
                <?php elseif ($userRole === 'professor'): ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <input type="text" name="department" class="form-control"
                                   value="<?= htmlspecialchars($profile['department'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Specialization</label>
                            <input type="text" name="specialization" class="form-control"
                                   value="<?= htmlspecialchars($profile['specialization'] ?? '') ?>">
                        </div>
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="btn btn-primary">Update Information</button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Change Password</h2>
            </div>
            
            <form method="POST">
                <?= CSRF::tokenField() ?>
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="8">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Change Password</button>
            </form>
        </div>

        <!-- Account Information -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Account Information</h2>
            </div>
            
            <p><strong>Role:</strong> <?= ucfirst($userRole) ?></p>
            <p><strong>Account Created:</strong> <?= date('F d, Y', strtotime($user['created_at'])) ?></p>
            <p><strong>Last Updated:</strong> <?= date('F d, Y g:i A', strtotime($user['updated_at'])) ?></p>
        </div>
    </div>
</body>
</html>