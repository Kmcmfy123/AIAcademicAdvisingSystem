<?php
require_once __DIR__ . '/../../includes/init.php';

$auth->requireRole('professor');

$professorId = $_SESSION['user_id'];

// Get professor's course assignments
$assignments = $db->fetchAll("
    SELECT pca.*, c.course_code, c.course_name, c.credits, c.description,
           COUNT(DISTINCT ce.student_id) as enrolled_students
    FROM professor_course_assignments pca
    JOIN courses c ON pca.course_id = c.id
    LEFT JOIN course_enrollments ce ON c.id = ce.course_id AND ce.status = 'enrolled'
    WHERE pca.professor_id = ?
    GROUP BY pca.id, c.id
    ORDER BY pca.semester DESC, c.course_code ASC
", [$professorId]);

// Group assignments by semester
$grouped = [];
foreach ($assignments as $assign) {
    $key = $assign['semester'] && $assign['school_year'] 
        ? $assign['semester'] . ' ' . $assign['school_year']
        : 'No Semester Specified';
    if (!isset($grouped[$key])) {
        $grouped[$key] = [];
    }
    $grouped[$key][] = $assign;
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
    <title>My Course Assignments - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <nav class="navbar">
        <div class="container">
            <a href="#" class="navbar-brand"><?= APP_NAME ?></a>
            <ul class="navbar-nav">
                <li><a href="dashboard_prof.php" class="nav-link">Dashboard</a></li>
                <li><a href="coursesAssignments.php" class="nav-link">My Courses</a></li>
                <li><a href="advisingSessions_prof.php" class="nav-link">Advising Sessions</a></li>
                <li><a href="studentVIew.php" class="nav-link">Students</a></li>
                <li><a href="../accountProfile.php" class="nav-link">Profile</a></li>
                <li><a href="../logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <h1 style="margin: 2rem 0;">My Course Assignments</h1>

        <?php if (empty($assignments)): ?>
            <div class="card">
                <p>No course assignments yet. Contact your administrator.</p>
            </div>
        <?php else: ?>
            <div class="card">
                <p><strong>Total Courses:</strong> <?= count($assignments) ?></p>
                <p><strong>Total Students:</strong> <?= array_sum(array_column($assignments, 'enrolled_students')) ?></p>
            </div>

            <?php foreach ($grouped as $semesterLabel => $courses): ?>
                <h2 style="margin-top: 2rem;"><?= safe($semesterLabel) ?></h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Section</th>
                            <th>Credits</th>
                            <th>Enrolled</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $assign): ?>
                            <tr>
                                <td><strong><?= safe($assign['course_code']) ?></strong></td>
                                <td><?= safe($assign['course_name']) ?></td>
                                <td><?= safe($assign['section']) ?></td>
                                <td><?= $assign['credits'] ?? 'N/A' ?></td>
                                <td><?= $assign['enrolled_students'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>