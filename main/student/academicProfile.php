<?php
require_once __DIR__ . '/../../includes/init.php';
$auth->requireRole('student');

$userId = $_SESSION['user_id'];

// ---- FETCH STUDENT PROFILE ----
$student = $db->fetchOne("
    SELECT 
        u.email, 
        u.first_name, 
        u.last_name, 
        u.created_at,
        sp.student_id AS school_id,
        sp.major,
        sp.gpa,
        sp.credits_completed,
        sp.enrollment_year,
        sp.expected_graduation,
        sp.academic_standing
    FROM users u
    LEFT JOIN student_profiles sp ON sp.user_id = u.id
    WHERE u.id = ?
", [$userId]);

if (!$student) {
    echo "<h2 style='color:red;'>Student profile not found.</h2>";
    exit;
}

// ---- FETCH COURSES ----
$courses = $db->fetchAll("
    SELECT 
        ce.*, 
        c.course_code, 
        c.course_name, 
        c.credits, 
        c.department,
        ce.grade AS final_grade,
        ce.status,
        ce.semester
    FROM course_enrollments ce
    JOIN courses c ON ce.course_id = c.id
    WHERE ce.student_id = ?
    ORDER BY ce.semester DESC, ce.status ASC
", [$userId]);

// ---- FETCH ADVISING HISTORY ----
$advisingSessions = $db->fetchAll("
    SELECT ads.*, u.first_name, u.last_name, pp.department
    FROM advising_sessions ads
    JOIN users u ON ads.professor_id = u.id
    LEFT JOIN professor_profiles pp ON u.id = pp.user_id
    WHERE ads.student_id = ? AND ads.status = 'completed'
    ORDER BY ads.session_date DESC
    LIMIT 10
", [$userId]);

// ---- STATS ----
$completedCourses = array_filter($courses, fn($c) => $c['status'] === 'completed');
$ongoingCourses   = array_filter($courses, fn($c) => $c['status'] === 'enrolled');

$totalCreditsCompleted = array_sum(
    array_map(fn($c) => $c['credits'], $completedCourses)
);

// ---- HELPER FUNCTION FOR SAFE OUTPUT ----
function safe($value, $fallback = 'N/A') {
    return htmlspecialchars($value ?? $fallback);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Academic Profile - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
    <style>
        .grade-table { margin-top: 0.5rem; }
        .grade-table td { padding: 0.3rem 0.5rem; }
        .passing { color: var(--success-color); font-weight: bold; }
        .failing { color: var(--danger-color); font-weight: bold; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
        }
    </style>
</head>
<body>

<nav class="navbar no-print">
    <div class="container">
        <a href="#" class="navbar-brand"><?= APP_NAME ?></a>
        <ul class="navbar-nav">
            <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
            <!-- <li><a href="academic_profile.php" class="nav-link">Academic Profile</a></li> -->
            <li><a href="advisingSessions.php" class="nav-link">Advising Sessions</a></li>
            <li><a href="../logout.php" class="nav-link">Logout</a></li>
        </ul>
    </div>
</nav>

<div class="container">

    <div style="display: flex; justify-content: space-between; align-items: center; margin: 2rem 0 1rem;">
        <h1>Academic Profile & Records</h1>
        <button onclick="window.print()" class="btn btn-primary no-print">Download/Print Records</button>
    </div>

    <!-- STUDENT INFORMATION -->
    <div class="card">
        <div class="card-header"><h2 class="card-title">Student Information</h2></div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
            <div><strong>Name:</strong><br><?= safe($student['first_name'] . " " . $student['last_name']) ?></div>
            <div><strong>Email:</strong><br><?= safe($student['email']) ?></div>
            <div><strong>Student ID:</strong><br><?= safe($student['school_id']) ?></div>
            <div><strong>Major:</strong><br><?= safe($student['major']) ?></div>
            <div><strong>Academic Standing:</strong><br><?= safe($student['academic_standing']) ?></div>
            <div><strong>Current GPA:</strong><br><?= formatGPA($student['gpa']) ?></div>
            <div><strong>Credits Completed:</strong><br><?= $totalCreditsCompleted ?> units</div>
            <div><strong>Enrollment Date:</strong><br><?= date('F d, Y', strtotime($student['created_at'])) ?></div>
        </div>
    </div>

    <!-- SUMMARY STATS -->
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-value"><?= count($completedCourses) ?></div><div class="stat-label">Courses Completed</div></div>
        <div class="stat-card"><div class="stat-value"><?= count($ongoingCourses) ?></div><div class="stat-label">Ongoing Courses</div></div>
        <div class="stat-card"><div class="stat-value"><?= formatGPA($student['gpa']) ?></div><div class="stat-label">Current GPA</div></div>
        <div class="stat-card"><div class="stat-value"><?= $totalCreditsCompleted ?></div><div class="stat-label">Total Credits</div></div>
    </div>

    <!-- COURSE RECORDS -->
    <div class="card">
        <div class="card-header"><h2 class="card-title">Course Records & Grades</h2></div>

        <?php if (empty($courses)): ?>
            <p>No course records found.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Course Code</th>
                        <th>Course Name</th>
                        <th>Credits</th>
                        <th>Semester</th>
                        <th>Final Grade</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $c): ?>
                        <tr>
                            <td><?= safe($c['course_code']) ?></td>
                            <td><?= safe($c['course_name']) ?></td>
                            <td><?= $c['credits'] ?></td>
                            <td><?= safe($c['semester']) ?></td>

                            <td>
                                <?= $c['final_grade'] ? safe($c['final_grade']) : '-' ?>
                            </td>

                            <td>
                                <span class="badge badge-<?= $c['status'] === 'completed' ? 'success' : 'primary' ?>">
                                    <?= ucfirst($c['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- This loads overview of Courses -->
    <div class="card">
            <div class="card-header">
                <h2 class="card-title">Course Recommendations</h2>
            </div>
            <div id="recommendations">
                <a class="btn btn-primary" href="../recommend.php">View Courses</a>
            </div>
    </div>

    <script>
        // Load AI recommendations
        fetch('../recommend.php?format=json')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('recommendations');
                if (data.length === 0) {
                    container.innerHTML = '<p>No recommendations available. Complete more courses to get personalized suggestions.</p>';
                } else {
                    let html = '';
                    data.slice(0, 3).forEach(rec => {
                        html += `
                            <div style="border-left: 3px solid var(--success-color); padding-left: 1rem; margin-bottom: 1rem;">
                                <h3 style="margin-bottom: 0.3rem;">${rec.course.course_code} - ${rec.course.course_name}</h3>
                                <p style="color: #666; font-size: 0.9rem; margin-bottom: 0.3rem;">${rec.course.description}</p>
                                <div style="background: #d1fae5; padding: 0.5rem; border-radius: 4px; font-size: 0.85rem; margin-top: 0.5rem;">
                                    <strong>💡 Why recommended:</strong> ${rec.reason}
                                </div>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                    container.innerHTML += '<a href="../recommend.php" class="btn btn-success" style="width: 100%; margin-top: 0.5rem;">View All Recommendations</a>';
                }
            })
            .catch(() => {
                document.getElementById('recommendations').innerHTML = '<p>Unable to load recommendations at this time.</p>';
            });
    </script>


    <!-- ADVISING HISTORY -->
    <div class="card">
        <div class="card-header"><h2 class="card-title">Advising Session History</h2></div>

        <?php if (empty($advisingSessions)): ?>
            <p>No completed advising sessions yet.</p>
        <?php else: ?>
            <?php foreach ($advisingSessions as $session): ?>
                <div style="border-left: 3px solid var(--primary-color); padding-left: 1rem; margin-bottom: 1rem;">
                    <strong>Date:</strong> <?= date('F d, Y', strtotime($session['session_date'])) ?><br>
                    <strong>Advisor:</strong> Prof. 
                    <?= safe($session['first_name'] . ' ' . $session['last_name']) ?> 
                    (<?= safe($session['department']) ?>)
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
