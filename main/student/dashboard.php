<?php
require_once __DIR__ . '/../../includes/init.php';
$auth->requireRole('student');

$userId = $_SESSION['user_id'];

// Get student profile
$student = $db->fetchOne(
    "SELECT sp.*, u.email, u.first_name, u.last_name 
     FROM student_profiles sp 
     JOIN users u ON sp.user_id = u.id 
     WHERE sp.user_id = ?",
    [$userId]
);

if (!$student) {
    $student = [
        'first_name' => 'Student',
        'gpa' => 0.00,
        'credits_completed' => 0,
    ];
}

// Get upcoming advising sessions
$upcomingSessions = $db->fetchAll(
    "SELECT ads.*, u.first_name, u.last_name, pp.department 
     FROM advising_sessions ads 
     JOIN users u ON ads.professor_id = u.id 
     LEFT JOIN professor_profiles pp ON u.id = pp.user_id 
     WHERE ads.student_id = ? AND ads.status = 'scheduled' 
     AND ads.session_date >= NOW()
     ORDER BY ads.session_date ASC 
     LIMIT 3",
    [$userId]
) ?: [];

// Get current enrollments
$currentEnrollments = $db->fetchAll(
    "SELECT c.*, ce.semester, ce.status, ce.grade
     FROM course_enrollments ce 
     JOIN courses c ON ce.course_id = c.id 
     WHERE ce.student_id = ? AND ce.status = 'enrolled' 
     ORDER BY ce.semester DESC",
    [$userId]
) ?: [];

// Get completed courses count
$completedCountResult = $db->fetchOne(
    "SELECT COUNT(*) as count FROM course_enrollments 
     WHERE student_id = ? AND status = 'completed'",
    [$userId]
);
$completedCount = $completedCountResult['count'] ?? 0;

// Get recent advising sessions
$recentSessions = $db->fetchAll(
    "SELECT ads.*, u.first_name, u.last_name 
     FROM advising_sessions ads 
     JOIN users u ON ads.professor_id = u.id 
     WHERE ads.student_id = ? AND ads.status = 'completed'
     ORDER BY ads.session_date DESC LIMIT 3",
    [$userId]
) ?: [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Dashboard - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="#" class="navbar-brand"><?= APP_NAME ?></a>
            <ul class="navbar-nav">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="academicProfile.php" class="nav-link">Academic<br>Profile</a></li>
                <li><a href="advisingSessions.php" class="nav-link">Advising<br>Sessions</a></li>

                <li><a href="../accountProfile.php" class="nav-link">Profile</a></li>
                <li><a href="../logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>
    
    <div class="container">
        <h1 style="margin: 2rem 0 1rem;">
            Welcome, <?= htmlspecialchars($student['first_name']) ?>!
        </h1>

        <!-- Academic Standing Alert -->
        <?php if (isset($student['academic_standing']) && $student['academic_standing'] !== 'good'): ?>
            <div class="alert alert-<?= $student['academic_standing'] === 'probation' ? 'danger' : 'warning' ?>">
                <strong>Academic Standing:</strong> 
                You are currently on <?= ucfirst($student['academic_standing']) ?>. 
                Please schedule an advising session for guidance.
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($student['gpa'] ?? 0, 2) ?></div>
                <div class="stat-label">Current GPA</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= (int)($student['credits_completed'] ?? 0) ?></div>
                <div class="stat-label">Credits Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= (int)$completedCount ?></div>
                <div class="stat-label">Courses Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($currentEnrollments) ?></div>
                <div class="stat-label">Current Courses</div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem;">
            <!-- Upcoming Advising Sessions -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Upcoming Advising Sessions</h2>
                </div>
                
                <?php if (empty($upcomingSessions)): ?>
                    <p>No upcoming sessions scheduled.</p>
                    <a href="advisingSessions.php" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
                        Schedule Session
                    </a>
                <?php else: ?>
                    <?php foreach ($upcomingSessions as $session): ?>
                        <div style="border-left: 3px solid var(--warning-color); padding-left: 1rem; margin-bottom: 1rem;">
                            <strong>Advisor:</strong> Prof. <?= htmlspecialchars($session['first_name'] . ' ' . $session['last_name']) ?><br>
                            <strong>Department:</strong> <?= htmlspecialchars($session['department'] ?? 'N/A') ?><br>
                            <strong>Date:</strong> <?= date('M d, Y \a\t g:i A', strtotime($session['session_date'])) ?><br>
                            <?php if ($session['notes']): ?>
                                <small><em><?= htmlspecialchars(substr($session['notes'], 0, 60)) ?>...</em></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <a href="advisingSessions.php" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
                        View All Sessions
                    </a>
                <?php endif; ?>
            </div>

            <!-- Current Courses -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Current Enrollments</h2>
                </div>
                
                <?php if (empty($currentEnrollments)): ?>
                    <p>No current enrollments.</p>
                <?php else: ?>
                    <?php foreach ($currentEnrollments as $course): ?>
                        <div style="border-left: 3px solid var(--primary-color); padding-left: 1rem; margin-bottom: 1rem;">
                            <strong><?= htmlspecialchars($course['course_code']) ?></strong> - 
                            <?= htmlspecialchars($course['course_name']) ?><br>
                            <small>
                                <?= $course['credits'] ?> credits | 
                                <?= htmlspecialchars($course['semester']) ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                    <a href="academicProfile.php" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
                        View Full Academic Profile
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- AI Recommendations -->
        

        <!-- Recent Advising History -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent Advising Sessions</h2>
            </div>
            
            <?php if (empty($recentSessions)): ?>
                <p>No completed advising sessions yet.</p>
            <?php else: ?>
                <?php foreach ($recentSessions as $session): ?>
                    <div style="border-left: 3px solid var(--success-color); padding-left: 1rem; margin-bottom: 1rem;">
                        <strong>Advisor:</strong> Prof. <?= htmlspecialchars($session['first_name'] . ' ' . $session['last_name']) ?><br>
                        <strong>Date:</strong> <?= date('M d, Y', strtotime($session['session_date'])) ?><br>
                        <?php if ($session['recommendations']): ?>
                            <small><strong>Recommendations:</strong> <?= htmlspecialchars(substr($session['recommendations'], 0, 100)) ?>...</small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <a href="advisingHistory.php" class="btn btn-secondary" style="width: 100%; margin-top: 0.5rem;">
                    View Full History
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    
</body>
</html>