<?php
require_once __DIR__ . '/../../includes/init.php';
$auth->requireRole('student');

$userId = $_SESSION['user_id'];
$selectedCourseId = $_GET['course_id'] ?? null;
$viewArchived = isset($_GET['archived']) && $_GET['archived'] === '1';

// Fetch student profile
$student = $db->fetchOne("
    SELECT 
        u.email, u.first_name, u.last_name, u.created_at,
        sp.student_id AS school_id, sp.major, sp.gpa,
        sp.credits_completed, sp.enrollment_year,
        sp.expected_graduation, sp.academic_standing,
        sp.current_section
    FROM users u
    LEFT JOIN student_profiles sp ON sp.user_id = u.id
    WHERE u.id = ?
", [$userId]);

if (!$student) {
    die("<h2 style='color:red;'>Student profile not found.</h2>");
}

// Fetch courses with sections
$statusFilter = $viewArchived ? "'completed', 'dropped', 'failed'" : "'enrolled'";
$courses = $db->fetchAll("
    SELECT 
        ce.*, 
        c.course_code, c.course_name, c.credits, c.department,
        COALESCE(cs.section, sp.current_section) as section,
        cg.school_year
    FROM course_enrollments ce
    JOIN courses c ON ce.course_id = c.id
    LEFT JOIN student_profiles sp ON ce.student_id = sp.user_id
    LEFT JOIN course_sections cs ON cs.student_id = ce.student_id 
        AND cs.course_id = ce.course_id
    LEFT JOIN course_grades cg ON ce.student_id = cg.student_id 
        AND ce.course_id = cg.course_id
    WHERE ce.student_id = ? AND ce.status IN ($statusFilter)
    ORDER BY ce.semester DESC
", [$userId]);

// Calculate stats
$allCourses = $db->fetchAll("
    SELECT ce.*, c.credits
    FROM course_enrollments ce
    JOIN courses c ON ce.course_id = c.id
    WHERE ce.student_id = ? AND ce.status = 'completed'
", [$userId]);

$totalCreditsCompleted = array_sum(array_column($allCourses, 'credits'));
$ongoingCount = count(array_filter($courses, fn($c) => $c['status'] === 'enrolled'));

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
        .course-selector {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .course-card {
            border: 2px solid #e2e8f0;
            padding: 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            flex: 1 1 calc(33.333% - 1rem);
            min-width: 250px;
        }
        .course-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .course-card.selected {
            border-color: var(--primary-color);
            background: #eff6ff;
        }
        .view-toggle {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .toggle-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .toggle-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<nav class="navbar no-print">
    <div class="container">
        <a href="#" class="navbar-brand"><?= APP_NAME ?></a>
        <ul class="navbar-nav">
            <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
            <li><a href="academicProfile.php" class="nav-link active">Academic Profile</a></li>
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
            <div><strong>Current Section:</strong><br><?= safe($student['current_section']) ?></div>
            <div><strong>Academic Standing:</strong><br><?= safe($student['academic_standing']) ?></div>
            <div><strong>Current GPA:</strong><br><?= formatGPA($student['gpa']) ?></div>
            <div><strong>Credits Completed:</strong><br><?= $totalCreditsCompleted ?> units</div>
        </div>
    </div>

    <!-- SUMMARY STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= count($allCourses) ?></div>
            <div class="stat-label">Courses Completed</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $ongoingCount ?></div>
            <div class="stat-label">Ongoing Courses</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= formatGPA($student['gpa']) ?></div>
            <div class="stat-label">Current GPA</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $totalCreditsCompleted ?></div>
            <div class="stat-label">Total Credits</div>
        </div>
    </div>

    <!-- COURSE SELECTION -->
    <div class="card no-print">
        <div class="card-header">
            <h2 class="card-title">Select a Course to View Records</h2>
        </div>
        
        <div class="view-toggle">
            <button class="toggle-btn <?= !$viewArchived ? 'active' : '' ?>" 
                    onclick="window.location.href='academicProfile.php'">
                Current Courses
            </button>
            <button class="toggle-btn <?= $viewArchived ? 'active' : '' ?>" 
                    onclick="window.location.href='academicProfile.php?archived=1'">
                Archived Courses
            </button>
        </div>

        <?php if (empty($courses)): ?>
            <p>No <?= $viewArchived ? 'archived' : 'current' ?> courses found.</p>
        <?php else: ?>
            <div class="course-selector">
                <?php foreach ($courses as $c): ?>
                    <div class="course-card <?= $selectedCourseId == $c['course_id'] ? 'selected' : '' ?>"
                         onclick="selectCourse(<?= $c['course_id'] ?>)">
                        <h3 style="margin: 0 0 0.5rem 0;"><?= safe($c['course_code']) ?></h3>
                        <p style="margin: 0 0 0.5rem 0; color: #666; font-size: 0.9rem;">
                            <?= safe($c['course_name']) ?>
                        </p>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span class="badge badge-primary"><?= safe($c['section']) ?></span>
                            <span style="font-size: 0.85rem; color: #666;">
                                <?= $c['credits'] ?> units
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- COURSE RECORDS SECTION - Hidden until course selected -->
    <div id="course-details-container" style="<?= !$selectedCourseId ? 'display: none;' : '' ?>">
        <?php if (!$selectedCourseId): ?>
            <!-- Placeholder when no course selected -->
            <div class="card">
                <div style="padding: 3rem; text-align: center; color: #666;">
                    <h3 style="margin-bottom: 1rem;">Course Records & Grades</h3>
                    <p style="font-size: 1.1rem; margin-bottom: 1rem;">
                        Select a course above to view detailed academic records
                    </p>
                    <p style="font-size: 0.9rem;">
                        You can view grades by period, add new scores, and track your progress
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- RECOMMENDATION LINK - Always visible -->
    <div class="card no-print">
        <div class="card-header">
            <h2 class="card-title">Get Recommendations</h2>
        </div>
        <div style="padding: 1.5rem;">
            <p style="margin-bottom: 1rem;">
                View personalized course recommendations, learning resources, professor insights, and performance analysis.
            </p>
            <a href="../recommend.php<?= $selectedCourseId ? '?course_id=' . $selectedCourseId : '' ?>" 
               class="btn btn-success" 
               style="width: 100%;">
                <?= $selectedCourseId ? 'View AI Recommendations for Selected Course' : 'View All AI Recommendations' ?> →
            </a>
        </div>
    </div>
</div>

<script>
function selectCourse(courseId) {
    const archived = <?= $viewArchived ? '1' : '0' ?>;
    const url = `academicProfile.php?course_id=${courseId}${archived ? '&archived=1' : ''}`;
    
    // Update URL without page reload
    window.history.pushState({courseId: courseId}, '', url);
    
    // Load course details
    loadCourseDetails(courseId);
    
    // Update selected state on course cards
    document.querySelectorAll('.course-card').forEach(card => {
        card.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');
}

function loadCourseDetails(courseId) {
    const container = document.getElementById('course-details-container');
    container.innerHTML = '<div style="text-align: center; padding: 2rem;"><p>Loading course details...</p></div>';
    
    fetch(`getCourseDetails.php?course_id=${courseId}`)
        .then(response => response.text())
        .then(html => {
            container.innerHTML = html;
            container.style.display = 'block';
        })
        .catch(error => {
            container.innerHTML = '<div class="card"><p style="color: red;">Failed to load course details.</p></div>';
        });
}

<?php if ($selectedCourseId): ?>
// Load course details on page load
window.addEventListener('DOMContentLoaded', () => {
    loadCourseDetails(<?= $selectedCourseId ?>);
});
<?php endif; ?>
</script>

</body>
</html> 