<?php
require_once __DIR__ . '/../../includes/init.php';
$auth->requireRole('professor');

$userId = $_SESSION['user_id'];

// Get professor profile
$professor = $db->fetchOne(
    "SELECT pp.*, u.email, u.first_name, u.last_name 
     FROM professor_profiles pp 
     JOIN users u ON pp.user_id = u.id 
     WHERE pp.user_id = ?",
    [$userId]
);

// Get upcoming advising sessions
$upcomingSessions = $db->fetchAll(
    "SELECT ads.*, u.first_name, u.last_name, sp.major, sp.year_level 
     FROM advising_sessions ads 
     JOIN users u ON ads.student_id = u.id 
     LEFT JOIN student_profiles sp ON u.id = sp.user_id 
     WHERE ads.professor_id = ? AND ads.status = 'scheduled' 
     AND ads.session_date >= NOW()
     ORDER BY ads.session_date ASC 
     LIMIT 5",
    [$userId]
);

// Get statistics
$stats = $db->fetchOne(
    "SELECT 
        COUNT(DISTINCT ads.student_id) as total_advisees,
        SUM(CASE WHEN ads.status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_sessions,
        SUM(CASE WHEN ads.status = 'completed' THEN 1 ELSE 0 END) as completed_sessions,
        SUM(CASE WHEN ads.status = 'completed' AND ads.session_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as recent_sessions
     FROM advising_sessions ads 
     WHERE ads.professor_id = ?",
    [$userId]
);

// Get high-risk students
$highRiskStudents = $db->fetchAll(
    "SELECT DISTINCT u.id, u.first_name, u.last_name, sp.gpa, sp.major, sp.year_level,
            (SELECT COUNT(*) FROM course_grades cg 
             JOIN course_enrollments ce ON cg.enrollment_id = ce.id 
             WHERE ce.student_id = u.id AND cg.final_grade < 75) as failed_count
     FROM advising_sessions ads
     JOIN users u ON ads.student_id = u.id
     JOIN student_profiles sp ON u.id = sp.user_id
     WHERE ads.professor_id = ? AND sp.gpa < 2.0
     ORDER BY sp.gpa ASC
     LIMIT 5",
    [$userId]
);

// Get recent completed sessions
$recentSessions = $db->fetchAll(
    "SELECT ads.*, u.first_name, u.last_name 
     FROM advising_sessions ads 
     JOIN users u ON ads.student_id = u.id 
     WHERE ads.professor_id = ? AND ads.status = 'completed' 
     ORDER BY ads.session_date DESC 
     LIMIT 5",
    [$userId]
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Professor Dashboard - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="#" class="navbar-brand"><?= APP_NAME ?></a>
            <ul class="navbar-nav">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="advisingSessions.php" class="nav-link">Advising Sessions</a></li>
                <li><a href="students.php" class="nav-link">Students</a></li>
                <li><a href="../logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <h1 style="margin: 2rem 0 1rem;">
            Welcome, Prof. <?= htmlspecialchars($professor['first_name']) ?>!
        </h1>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_advisees'] ?? 0 ?></div>
                <div class="stat-label">Total Advisees</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['scheduled_sessions'] ?? 0 ?></div>
                <div class="stat-label">Scheduled Sessions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['completed_sessions'] ?? 0 ?></div>
                <div class="stat-label">Total Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['recent_sessions'] ?? 0 ?></div>
                <div class="stat-label">Sessions This Month</div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem;">
            <!-- Upcoming Sessions -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Upcoming Advising Sessions</h2>
                </div>
                
                <?php if (empty($upcomingSessions)): ?>
                    <p>No upcoming sessions scheduled.</p>
                <?php else: ?>
                    <?php foreach ($upcomingSessions as $session): ?>
                        <div style="border-left: 3px solid var(--warning-color); padding-left: 1rem; margin-bottom: 1rem;">
                            <strong><?= htmlspecialchars($session['first_name'] . ' ' . $session['last_name']) ?></strong><br>
                            <small><?= htmlspecialchars($session['major'] ?? 'N/A') ?> - <?= htmlspecialchars(ucfirst($session['year_level'] ?? 'N/A')) ?></small><br>
                            <strong>Date:</strong> <?= date('M d, Y \a\t g:i A', strtotime($session['session_date'])) ?><br>
                            <?php if ($session['notes']): ?>
                                <small><em><?= htmlspecialchars(substr($session['notes'], 0, 50)) ?>...</em></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <a href="advisingSessions.php" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
                        View All Sessions
                    </a>
                <?php endif; ?>
            </div>

            <!-- High-Risk Students Alert -->
            <div class="card" style="border-top: 4px solid var(--danger-color);">
                <div class="card-header">
                    <h2 class="card-title">High-Risk Students</h2>
                </div>
                
                <?php if (empty($highRiskStudents)): ?>
                    <p>No high-risk students at this time.</p>
                <?php else: ?>
                    <?php foreach ($highRiskStudents as $student): ?>
                        <div style="border-left: 3px solid var(--danger-color); padding-left: 1rem; margin-bottom: 1rem; background: #fee2e2; padding: 0.75rem; border-radius: 4px;">
                            <strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></strong><br>
                            <small><?= htmlspecialchars($student['major'] ?? 'N/A') ?> - <?= htmlspecialchars(ucfirst($student['year_level'] ?? 'N/A')) ?></small><br>
                            <strong>GPA:</strong> <span style="color: var(--danger-color);"><?= formatGPA($student['gpa']) ?></span> | 
                            <strong>Failed Courses:</strong> <?= $student['failed_count'] ?>
                        </div>
                    <?php endforeach; ?>
                    <a href="students.php?risk=high" class="btn btn-danger" style="width: 100%; margin-top: 0.5rem;">
                        View All High-Risk Students
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Completed Sessions -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent Completed Sessions</h2>
            </div>
            
            <?php if (empty($recentSessions)): ?>
                <p>No completed sessions yet.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Date</th>
                            <th>Feedback Summary</th>
                            <th>Performance</th>
                            <th>Risk Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentSessions as $session): ?>
                            <tr>
                                <td><?= htmlspecialchars($session['first_name'] . ' ' . $session['last_name']) ?></td>
                                <td><?= date('M d, Y', strtotime($session['session_date'])) ?></td>
                                <td><?= htmlspecialchars(substr($session['feedback'] ?? '', 0, 60)) ?>...</td>
                                <td><?= $session['performance_rating'] ? $session['performance_rating'] . '/10' : 'N/A' ?></td>
                                <td>
                                    <?php if ($session['risk_level']): ?>
                                        <span class="badge badge-<?= $session['risk_level'] === 'high' ? 'danger' : ($session['risk_level'] === 'medium' ? 'warning' : 'success') ?>">
                                            <?= ucfirst($session['risk_level']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Quick Actions</h2>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <a href="advisingSessions.php" class="btn btn-primary">Manage Sessions</a>
                <a href="students.php" class="btn btn-success">View All Students</a>
                <a href="reports.php" class="btn btn-secondary">Generate Reports</a>
                <a href="profile.php" class="btn btn-secondary">Edit Profile</a>
            </div>
        </div>
    </div>
</body>
</html>