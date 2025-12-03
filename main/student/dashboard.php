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

// If no profile found, set defaults to avoid warnings
if (!$student) {
    $student = [
        'first_name' => 'Student',
        'gpa' => null,
        'credits_completed' => 0,
    ];
} else {
    // Make sure keys exist even if null
    if (!isset($student['first_name'])) {
        $student['first_name'] = 'Student';
    }
    if (!isset($student['gpa'])) {
        $student['gpa'] = null;
    }
    if (!isset($student['credits_completed'])) {
        $student['credits_completed'] = 0;
    }
}

// Get current enrollments
$currentEnrollments = $db->fetchAll(
    "SELECT c.*, ce.semester, ce.status 
     FROM course_enrollments ce 
     JOIN courses c ON ce.course_id = c.id 
     WHERE ce.student_id = ? AND ce.status = 'enrolled' 
     ORDER BY ce.semester DESC",
    [$userId]
);

// If fetchAll returns false, convert to empty array
if (!$currentEnrollments) {
    $currentEnrollments = [];
}

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
     WHERE ads.student_id = ? 
     ORDER BY ads.session_date DESC LIMIT 5",
    [$userId]
);

if (!$recentSessions) {
    $recentSessions = [];
}



?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Student Dashboard - <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(ASSETS_URL) ?>/css/style.css" />
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="#" class="navbar-brand"><?= htmlspecialchars(APP_NAME) ?></a>
            <ul class="navbar-nav">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="profile.php" class="nav-link">Profile</a></li>
                <li><a href="advising_history.php" class="nav-link">Advising History</a></li>
                <li><a href="../logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>
    
    <div class="container">
        <h1 style="margin: 2rem 0 1rem;">
            Welcome, <?= htmlspecialchars($student['first_name']) ?>!
        </h1>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= formatGPA($student['gpa']) ?></div>
                <div class="stat-label">Current GPA</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= (int)$student['credits_completed'] ?></div>
                <div class="stat-label">Credits Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= (int)$completedCount ?></div>
                <div class="stat-label">Courses Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($currentEnrollments) ?></div>
                <div class="stat-label">Current Enrollments</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recommendations</h2>
            </div>
            <div id="recommendations">
                <p>Loading recommendations...</p>
            </div>
            <a href="../recommend.php" class="btn btn-primary">Get Full Recommendations</a>
        </div>

        <!-- Current Enrollments -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Current Enrollments</h2>
            </div>
            <?php if (empty($currentEnrollments)): ?>
                <p>No current enrollments.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Credits</th>
                            <th>Semester</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($currentEnrollments as $enrollment): ?>
                            <tr>
                                <td><?= htmlspecialchars($enrollment['course_code']) ?></td>
                                <td><?= htmlspecialchars($enrollment['course_name']) ?></td>
                                <td><?= (int)$enrollment['credits'] ?></td>
                                <td><?= htmlspecialchars($enrollment['semester']) ?></td>
                                <td><span class="badge badge-primary"><?= htmlspecialchars(ucfirst($enrollment['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Recent Adv -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent Advising Sessions</h2>
            </div>
            <?php if (empty($recentSessions)): ?>
                <p>No advising sessions yet.</p>
            <?php else: ?>
                <?php foreach ($recentSessions as $session): ?>
                    <div style="border-left: 3px solid var(--primary-color); padding-left: 1rem; margin-bottom: 1rem;">
                        <strong>Advisor:</strong> <?= htmlspecialchars($session['first_name'] . ' ' . $session['last_name']) ?><br>
                        <strong>Date:</strong> <?= htmlspecialchars(date('M d, Y', strtotime($session['session_date']))) ?><br>
                        <strong>Notes:</strong> <?= htmlspecialchars($session['notes']) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Load recommendations via AJAX
        fetch('../recommend.php?format=json')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('recommendations');
                if (data.length === 0) {
                    container.innerHTML = '<p>No recommendations available at this time.</p>';
                } else {
                    let html = '';
                    data.slice(0, 3).forEach(rec => {
                        html += `
                            <div class="recommendation-card card" style="margin-bottom: 1rem;">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div>
                                        <h3 style="margin-bottom: 0.5rem;">${rec.course.course_code} - ${rec.course.course_name}</h3>
                                        <p style="color: #666;">${rec.course.description}</p>
                                        <p style="font-size: 0.9rem;"><strong>Reason:</strong> ${rec.reason}</p>
                                    </div>
                                    <div class="recommendation-score">
                                        Score: ${rec.score}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                }
            })
            .catch(error => {
                document.getElementById('recommendations').innerHTML = '<p>Error loading recommendations.</p>';
            });
    </script>
</body>
</html>
