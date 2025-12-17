<?php
require_once __DIR__ . '/../../includes/init.php';

$auth->requireRole('admin');

// Get filter parameters
$reportType = $_GET['type'] ?? 'student_performance';
$semester = $_GET['semester'] ?? '';
$schoolYear = $_GET['school_year'] ?? '';

// Student Performance Report
$studentPerformanceData = [];
if ($reportType === 'student_performance') {
    $query = "
        SELECT u.id, u.first_name, u.last_name, sp.gpa, sp.major,
               COUNT(DISTINCT ce.id) as total_courses,
               SUM(CASE WHEN cg.grade IS NOT NULL AND CAST(cg.grade AS DECIMAL) >= 75 THEN 1 ELSE 0 END) as passed_courses,
               SUM(CASE WHEN cg.grade IS NOT NULL AND CAST(cg.grade AS DECIMAL) < 75 THEN 1 ELSE 0 END) as failed_courses,
               AVG(CASE WHEN cg.grade IS NOT NULL THEN CAST(cg.grade AS DECIMAL) ELSE NULL END) as avg_grade
        FROM users u
        JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN course_enrollments ce ON u.id = ce.student_id
        LEFT JOIN course_grades cg ON ce.id = cg.id
    ";
    $params = [];
    
    if ($semester) {
        $query .= " AND ce.semester = ?";
        $params[] = $semester;
    }
    if ($schoolYear) {
        $query .= " AND cg.school_year = ?";
        $params[] = $schoolYear;
    }
    
    $query .= " GROUP BY u.id ORDER BY sp.gpa DESC";
    $studentPerformanceData = $db->fetchAll($query, $params);
}

// Course Statistics Report
$courseStatsData = [];
if ($reportType === 'course_statistics') {
    $courseStatsData = $db->fetchAll("
        SELECT c.id, c.course_code, c.course_name, c.credits,
               COUNT(DISTINCT ce.id) as total_enrolled,
               COUNT(DISTINCT CASE WHEN ce.status = 'completed' THEN ce.id END) as completed,
               COUNT(DISTINCT CASE WHEN ce.status = 'enrolled' THEN ce.id END) as enrolled,
               COUNT(DISTINCT CASE WHEN ce.status = 'dropped' THEN ce.id END) as dropped,
               AVG(CASE WHEN cg.grade IS NOT NULL THEN CAST(cg.grade AS DECIMAL) ELSE NULL END) as avg_grade,
               COUNT(DISTINCT pca.professor_id) as professors_assigned
        FROM courses c
        LEFT JOIN course_enrollments ce ON c.id = ce.course_id
        LEFT JOIN course_grades cg ON ce.id = cg.id
        LEFT JOIN professor_course_assignments pca ON c.id = pca.course_id
        GROUP BY c.id
        ORDER BY total_enrolled DESC
    ");
}

// Professor Performance Report
$professorStatsData = [];
if ($reportType === 'professor_performance') {
    $professorStatsData = $db->fetchAll("
        SELECT u.id, u.first_name, u.last_name, u.email,
               COUNT(DISTINCT pca.course_id) as courses_assigned,
               COUNT(DISTINCT ce.student_id) as total_students,
               COUNT(DISTINCT ads.id) as total_sessions,
               COUNT(DISTINCT CASE WHEN ads.status = 'completed' THEN ads.id END) as completed_sessions,
               COUNT(DISTINCT CASE WHEN ads.status = 'scheduled' THEN ads.id END) as scheduled_sessions
        FROM users u
        JOIN professor_profiles pp ON u.id = pp.user_id
        LEFT JOIN professor_course_assignments pca ON u.id = pca.professor_id
        LEFT JOIN course_enrollments ce ON pca.course_id = ce.course_id
        LEFT JOIN advising_sessions ads ON u.id = ads.professor_id
        GROUP BY u.id
        ORDER BY total_sessions DESC
    ");
}

// Advising Sessions Report
$advisingStatsData = [];
if ($reportType === 'advising_sessions') {
    $advisingStatsData = $db->fetchAll("
        SELECT ads.id, u.first_name as student_first, u.last_name as student_last,
               p.first_name as prof_first, p.last_name as prof_last,
               ads.session_date, ads.status,
               ads.feedback, ads.performance_rating, ads.risk_level
        FROM advising_sessions ads
        JOIN users u ON ads.student_id = u.id
        JOIN users p ON ads.professor_id = p.id
        ORDER BY ads.session_date DESC
        LIMIT 100
    ");
}

// Enrollment Trends
$enrollmentTrends = [];
if ($reportType === 'enrollment_trends') {
    $enrollmentTrends = $db->fetchAll("
        SELECT c.course_code, c.course_name,
               SUM(CASE WHEN ce.status = 'enrolled' THEN 1 ELSE 0 END) as currently_enrolled,
               SUM(CASE WHEN ce.status = 'completed' THEN 1 ELSE 0 END) as completed,
               SUM(CASE WHEN ce.status = 'dropped' THEN 1 ELSE 0 END) as dropped
        FROM courses c
        LEFT JOIN course_enrollments ce ON c.id = ce.course_id
        GROUP BY c.id
        ORDER BY currently_enrolled DESC
    ");
}

function safe($value, $fallback = 'N/A') {
    return htmlspecialchars($value ?? $fallback);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Reports - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .report-filter {
            background: #f9fafb;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border: 1px solid #e5e7eb;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #374151;
            font-size: 0.9rem;
        }
        
        .filter-group select {
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            background: white;
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            margin-top: 1rem;
        }
        
        .report-table th {
            background: #3b82f6;
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
        }
        
        .report-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .report-table tr:hover {
            background: #f9fafb;
        }
        
        .metric-high {
            color: #059669;
            font-weight: 600;
        }
        
        .metric-low {
            color: #dc2626;
            font-weight: 600;
        }
        
        .btn-export {
            background: #10b981;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .btn-export:hover {
            background: #059669;
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
        <h1 style="margin: 2rem 0 1rem;">Admin Reports</h1>

        <!-- Report Filter -->
        <div class="report-filter card">
            <h2 class="card-title" style="margin: 0 0 1.5rem 0;">Select Report</h2>
            <form method="GET" style="margin: 0;">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="type">Report Type</label>
                        <select id="type" name="type" onchange="this.form.submit()">
                            <option value="student_performance" <?= $reportType === 'student_performance' ? 'selected' : '' ?>>Student Performance</option>
                            <option value="course_statistics" <?= $reportType === 'course_statistics' ? 'selected' : '' ?>>Course Statistics</option>
                            <option value="professor_performance" <?= $reportType === 'professor_performance' ? 'selected' : '' ?>>Professor Performance</option>
                            <option value="advising_sessions" <?= $reportType === 'advising_sessions' ? 'selected' : '' ?>>Advising Sessions</option>
                            <option value="enrollment_trends" <?= $reportType === 'enrollment_trends' ? 'selected' : '' ?>>Enrollment Trends</option>
                        </select>
                    </div>
                    
                    <?php if ($reportType === 'student_performance'): ?>
                        <div class="filter-group">
                            <label for="semester">Semester</label>
                            <select id="semester" name="semester" onchange="this.form.submit()">
                                <option value="">All Semesters</option>
                                <option value="First" <?= $semester === 'First' ? 'selected' : '' ?>>First Semester</option>
                                <option value="Second" <?= $semester === 'Second' ? 'selected' : '' ?>>Second Semester</option>
                                <option value="Summer" <?= $semester === 'Summer' ? 'selected' : '' ?>>Summer</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="school_year">School Year</label>
                            <select id="school_year" name="school_year" onchange="this.form.submit()">
                                <option value="">All Years</option>
                                <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                    <option value="<?= $y ?>" <?= $schoolYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Student Performance Report -->
        <?php if ($reportType === 'student_performance' && !empty($studentPerformanceData)): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Student Performance Report</h2>
                </div>
                <div style="overflow-x: auto;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Major</th>
                                <th>GPA</th>
                                <th>Total Courses</th>
                                <th>Passed</th>
                                <th>Failed</th>
                                <th>Average Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentPerformanceData as $row): ?>
                                <tr>
                                    <td><?= safe($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                    <td><?= safe($row['major']) ?></td>
                                    <td class="<?= ($row['gpa'] ?? 0) < 2.0 ? 'metric-low' : 'metric-high' ?>"><?= formatGPA($row['gpa']) ?></td>
                                    <td><?= $row['total_courses'] ?? 0 ?></td>
                                    <td><?= $row['passed_courses'] ?? 0 ?></td>
                                    <td class="<?= ($row['failed_courses'] ?? 0) > 0 ? 'metric-low' : '' ?>"><?= $row['failed_courses'] ?? 0 ?></td>
                                    <td><?= $row['avg_grade'] ? number_format($row['avg_grade'], 2) : 'N/A' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Course Statistics Report -->
        <?php if ($reportType === 'course_statistics' && !empty($courseStatsData)): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Course Statistics Report</h2>
                </div>
                <div style="overflow-x: auto;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Credits</th>
                                <th>Enrolled</th>
                                <th>Completed</th>
                                <th>Dropped</th>
                                <th>Avg Grade</th>
                                <th>Professors</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courseStatsData as $row): ?>
                                <tr>
                                    <td><strong><?= safe($row['course_code']) ?></strong></td>
                                    <td><?= safe($row['course_name']) ?></td>
                                    <td><?= $row['credits'] ?></td>
                                    <td class="metric-high"><?= $row['total_enrolled'] ?></td>
                                    <td><?= $row['completed'] ?></td>
                                    <td><?= $row['dropped'] ?></td>
                                    <td><?= $row['avg_grade'] ? number_format($row['avg_grade'], 2) : 'N/A' ?></td>
                                    <td><?= $row['professors_assigned'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Professor Performance Report -->
        <?php if ($reportType === 'professor_performance' && !empty($professorStatsData)): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Professor Performance Report</h2>
                </div>
                <div style="overflow-x: auto;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Professor</th>
                                <th>Email</th>
                                <th>Courses Assigned</th>
                                <th>Total Students</th>
                                <th>Sessions Scheduled</th>
                                <th>Sessions Completed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($professorStatsData as $row): ?>
                                <tr>
                                    <td><strong><?= safe($row['first_name'] . ' ' . $row['last_name']) ?></strong></td>
                                    <td><?= safe($row['email']) ?></td>
                                    <td><?= $row['courses_assigned'] ?></td>
                                    <td class="metric-high"><?= $row['total_students'] ?></td>
                                    <td><?= $row['scheduled_sessions'] ?></td>
                                    <td><?= $row['completed_sessions'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Advising Sessions Report -->
        <?php if ($reportType === 'advising_sessions' && !empty($advisingStatsData)): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Advising Sessions Report</h2>
                </div>
                <div style="overflow-x: auto;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Professor</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Performance</th>
                                <th>Risk Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($advisingStatsData as $row): ?>
                                <tr>
                                    <td><?= safe($row['student_first'] . ' ' . $row['student_last']) ?></td>
                                    <td><?= safe($row['prof_first'] . ' ' . $row['prof_last']) ?></td>
                                    <td><?= date('M d, Y g:i A', strtotime($row['session_date'])) ?></td>
                                    <td><span class="badge badge-<?= $row['status'] === 'completed' ? 'success' : 'warning' ?>"><?= ucfirst($row['status']) ?></span></td>
                                    <td><?= $row['performance_rating'] ? $row['performance_rating'] . '/10' : 'N/A' ?></td>
                                    <td>
                                        <?php if ($row['risk_level']): ?>
                                            <span class="badge badge-<?= $row['risk_level'] === 'high' ? 'danger' : ($row['risk_level'] === 'medium' ? 'warning' : 'success') ?>">
                                                <?= ucfirst($row['risk_level']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Enrollment Trends Report -->
        <?php if ($reportType === 'enrollment_trends' && !empty($enrollmentTrends)): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Enrollment Trends Report</h2>
                </div>
                <div style="overflow-x: auto;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Currently Enrolled</th>
                                <th>Completed</th>
                                <th>Dropped</th>
                                <th>Total Enrollment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrollmentTrends as $row): ?>
                                <tr>
                                    <td><strong><?= safe($row['course_code']) ?></strong></td>
                                    <td><?= safe($row['course_name']) ?></td>
                                    <td class="metric-high"><?= $row['currently_enrolled'] ?></td>
                                    <td><?= $row['completed'] ?></td>
                                    <td><?= $row['dropped'] ?></td>
                                    <td><strong><?= $row['currently_enrolled'] + $row['completed'] + $row['dropped'] ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
