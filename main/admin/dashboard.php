<?php
require_once __DIR__ . '/../../includes/init.php';

$auth->requireRole('admin');

// System Statistics
$stats = $db->fetchOne("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
        (SELECT COUNT(*) FROM users WHERE role = 'professor') as total_professors,
        (SELECT COUNT(*) FROM users WHERE role = 'admin') as total_admins,
        (SELECT COUNT(*) FROM courses) as total_courses,
        (SELECT COUNT(*) FROM advising_sessions) as total_sessions,
        (SELECT COUNT(*) FROM professor_course_assignments) as total_assignments
");

// Recent registrations
$recentUsers = $db->fetchAll("
    SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.created_at
    FROM users u
    ORDER BY u.created_at DESC
    LIMIT 10
");

// High-risk students
$highRiskStudents = $db->fetchAll("
    SELECT u.id, u.first_name, u.last_name, sp.gpa, sp.major,
           (SELECT COUNT(*) FROM course_grades cg 
            WHERE cg.student_id = u.id AND cg.grade IS NOT NULL AND CAST(cg.grade AS DECIMAL) < 75) as failed_count
    FROM users u
    JOIN student_profiles sp ON u.id = sp.user_id
    WHERE sp.gpa < 2.0
    ORDER BY sp.gpa ASC
    LIMIT 10
");

// Course enrollment summary
$courseEnrollments = $db->fetchAll("
    SELECT c.id, c.course_code, c.course_name, COUNT(ce.id) as total_enrolled
    FROM courses c
    LEFT JOIN course_enrollments ce ON c.id = ce.course_id
    GROUP BY c.id, c.course_code, c.course_name
    ORDER BY total_enrolled DESC
    LIMIT 10
");

// Active sessions today
$activeSessions = $db->fetchAll("
    SELECT ads.*, u.first_name, u.last_name, p.first_name as prof_first, p.last_name as prof_last
    FROM advising_sessions ads
    JOIN users u ON ads.student_id = u.id
    JOIN users p ON ads.professor_id = p.id
    WHERE DATE(ads.session_date) = CURDATE() AND ads.status = 'scheduled'
    ORDER BY ads.session_date ASC
");

function safe($value, $fallback = 'N/A') {
    return htmlspecialchars($value ?? $fallback);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .admin-stat-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .admin-stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #3b82f6;
            margin-bottom: 0.5rem;
        }
        
        .admin-stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .admin-grid-2col {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        .admin-table th {
            background: #f3f4f6;
            padding: 1rem;
            text-align: left;
            border-bottom: 2px solid #e5e7eb;
            font-weight: 600;
            color: #374151;
        }
        
        .admin-table td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .admin-table tr:hover {
            background: #f9fafb;
        }
        
        .badge-role {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-student {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-professor {
            background: #dcfce7;
            color: #166534;
        }
        
        .badge-admin {
            background: #fed7aa;
            color: #92400e;
        }
        
        .risk-high {
            color: #dc2626;
            font-weight: 600;
        }
        
        .recent-item {
            padding: 1rem;
            border-left: 3px solid #3b82f6;
            margin-bottom: 0.75rem;
            background: #f0f9ff;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="container">
            <a href="#" class="navbar-brand"><?= APP_NAME ?></a>
            <ul class="navbar-nav">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="users.php" class="nav-link">Users</a></li>
                <li><a href="assignCourses.php" class="nav-link">Course Assignments</a></li>
                <li><a href="reports.php" class="nav-link">Reports</a></li>
                <li><a href="../logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <h1 style="margin: 2rem 0 1rem;">Admin Dashboard</h1>

        <!-- System Statistics -->
        <div class="admin-stats-grid">
            <div class="admin-stat-card">
                <div class="admin-stat-value"><?= $stats['total_students'] ?? 0 ?></div>
                <div class="admin-stat-label">Total Students</div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-value"><?= $stats['total_professors'] ?? 0 ?></div>
                <div class="admin-stat-label">Total Professors</div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-value"><?= $stats['total_admins'] ?? 0 ?></div>
                <div class="admin-stat-label">Total Admins</div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-value"><?= $stats['total_courses'] ?? 0 ?></div>
                <div class="admin-stat-label">Total Courses</div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-value"><?= $stats['total_assignments'] ?? 0 ?></div>
                <div class="admin-stat-label">Course Assignments</div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-value"><?= $stats['total_sessions'] ?? 0 ?></div>
                <div class="admin-stat-label">Advising Sessions</div>
            </div>
        </div>

        <div class="admin-grid-2col">
            <!-- High-Risk Students -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">High-Risk Students (GPA < 2.0)</h2>
                </div>
                <div class="card-content">
                    <?php if (empty($highRiskStudents)): ?>
                        <p>No high-risk students at this time.</p>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>GPA</th>
                                    <th>Failed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($highRiskStudents as $student): ?>
                                    <tr>
                                        <td><?= safe($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                        <td class="risk-high"><?= formatGPA($student['gpa']) ?></td>
                                        <td><?= $student['failed_count'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Enrolled Courses -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Top Enrolled Courses</h2>
                </div>
                <div class="card-content">
                    <?php if (empty($courseEnrollments)): ?>
                        <p>No courses with enrollments yet.</p>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Code</th>
                                    <th>Enrolled</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courseEnrollments as $course): ?>
                                    <tr>
                                        <td><?= safe(substr($course['course_name'], 0, 20)) ?></td>
                                        <td><?= safe($course['course_code']) ?></td>
                                        <td style="font-weight: 600;"><?= $course['total_enrolled'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Users -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent User Registrations</h2>
            </div>
            <div class="card-content">
                <?php if (empty($recentUsers)): ?>
                    <p>No recent registrations.</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Registered</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentUsers as $user): ?>
                                    <tr>
                                        <td><?= safe($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                        <td><?= safe($user['email']) ?></td>
                                        <td>
                                            <span class="badge-role badge-<?= $user['role'] ?>">
                                                <?= ucfirst($user['role']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y g:i A', strtotime($user['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Today's Sessions -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Today's Scheduled Advising Sessions</h2>
            </div>
            <div class="card-content">
                <?php if (empty($activeSessions)): ?>
                    <p>No sessions scheduled for today.</p>
                <?php else: ?>
                    <?php foreach ($activeSessions as $session): ?>
                        <div class="recent-item">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <strong><?= safe($session['first_name'] . ' ' . $session['last_name']) ?></strong>
                                    <span style="color: #666; font-size: 0.9rem; margin-left: 0.5rem;">
                                        with Prof. <?= safe($session['prof_first'] . ' ' . $session['prof_last']) ?>
                                    </span>
                                </div>
                                <span style="color: #666; font-size: 0.9rem;">
                                    <?= date('g:i A', strtotime($session['session_date'])) ?>
                                </span>
                            </div>
                            <?php if ($session['notes']): ?>
                                <p style="margin: 0.5rem 0 0 0; color: #555; font-size: 0.9rem;">
                                    <?= safe(substr($session['notes'], 0, 100)) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Quick Actions</h2>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                <a href="users.php" class="btn btn-primary">Manage Users</a>
                <a href="assignCourses.php" class="btn btn-success">Assign Courses</a>
                <a href="reports.php" class="btn btn-secondary">View Reports</a>
                <a href="../logout.php" class="btn btn-danger">Logout</a>
            </div>
        </div>
    </div>
</body>
</html>
