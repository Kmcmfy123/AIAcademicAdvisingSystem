<?php
require_once __DIR__ . '/../../includes/init.php';
$auth->requireRole('professor');

$professorId = $_SESSION['user_id'];
$studentId = $_GET['student_id'] ?? null;
$courseId = $_GET['course_id'] ?? null;

if (!$courseId || !$studentId) {
    echo '<p>Missing student or course information.</p>';
    exit;
}

// Verify professor has access to this student's course
$hasAccess = $db->fetchOne("
    SELECT 1 FROM course_enrollments ce
    JOIN professor_course_assignments pca ON ce.course_id = pca.course_id
    WHERE ce.student_id = ? AND ce.course_id = ? AND pca.professor_id = ?
    LIMIT 1
", [$studentId, $courseId, $professorId]);

if (!$hasAccess) {
    echo '<p style="color: red;">You don\'t have permission to view this student\'s course.</p>';
    exit;
}

// Fetch course info with student enrollment details
$course = $db->fetchOne("
    SELECT c.*, ce.semester, ce.status, cg.school_year,
           COALESCE(cs.section, sp.current_section) as section,
           u.first_name as student_first_name, u.last_name as student_last_name,
           sp.student_id as student_school_id, sp.major
    FROM courses c
    JOIN course_enrollments ce ON c.id = ce.course_id
    LEFT JOIN course_grades cg ON ce.student_id = cg.student_id AND ce.course_id = cg.course_id
    LEFT JOIN course_sections cs ON cs.student_id = ce.student_id AND cs.course_id = c.id
    LEFT JOIN student_profiles sp ON ce.student_id = sp.user_id
    LEFT JOIN users u ON ce.student_id = u.id
    WHERE c.id = ? AND ce.student_id = ?
", [$courseId, $studentId]);

if (!$course) {
    echo '<p>Course not found.</p>';
    exit;
}

// Fetch syllabus if exists
$syllabus = $db->fetchOne("
    SELECT * FROM course_syllabi 
    WHERE course_id = ? 
    ORDER BY uploaded_at DESC 
    LIMIT 1
", [$courseId]);

// Fetch grade components grouped by period
$gradeComponents = $db->fetchAll("
    SELECT gc.* 
    FROM grade_components gc
    JOIN course_grades cg ON gc.course_grade_id = cg.id
    WHERE cg.student_id = ? AND cg.course_id = ?
    ORDER BY 
        FIELD(gc.period, 'prelim', 'midterm', 'semi_final', 'final'),
        gc.component_type, gc.date_recorded
", [$studentId, $courseId]);

// Group by period
$periodGroups = [
    'prelim' => [],
    'midterm' => [],
    'semi_final' => [],
    'final' => []
];
foreach ($gradeComponents as $component) {
    $periodGroups[$component['period']][] = $component;
}

function calculatePeriodGrade($components, $syllabus)
{
    if (empty($components)) return null;

    $breakdown = $syllabus ? json_decode($syllabus['grading_breakdown'], true) : null;
    $period = $components[0]['period'];

    // Group by component type
    $typeGroups = [];
    foreach ($components as $c) {
        $typeGroups[$c['component_type']][] = $c;
    }

    $totalScore = 0;
    $totalWeight = 0;

    foreach ($typeGroups as $type => $items) {
        $typeScore = 0;
        $typeMaxScore = 0;

        foreach ($items as $item) {
            $typeScore += $item['score'];
            $typeMaxScore += $item['max_score'];
        }

        $percentage = $typeMaxScore > 0 ? ($typeScore / $typeMaxScore) * 100 : 0;
        $weight = $breakdown[$period][$type] ?? 40; // default weight

        $totalScore += $percentage * ($weight / 100);
        $totalWeight += $weight;
    }

    return $totalWeight > 0 ? round($totalScore, 2) : null;
}

function safe($value, $fallback = 'N/A')
{
    return htmlspecialchars($value ?? $fallback);
}

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$isAjax) {
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Course Details - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="#" class="navbar-brand"><?= APP_NAME ?></a>
            <ul class="navbar-nav">
                <li><a href="dashboard_prof.php" class="nav-link">Dashboard</a></li>
                <li><a href="advisingSessions_prof.php" class="nav-link">Advising Sessions</a></li>
                <li><a href="studentVIew.php" class="nav-link">Students</a></li>
                <li><a href="../accountProfile.php" class="nav-link">Profile</a></li>
                <li><a href="../logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 class="card-title" style="margin: 0;">
                <?= safe($course['course_code']) ?> - <?= safe($course['course_name']) ?>
            </h2>
            <p style="margin: 0.5rem 0 0 0; color: #666;">
                <strong>Student:</strong> <?= safe($course['student_first_name'] . ' ' . $course['student_last_name']) ?> 
                (<?= safe($course['student_school_id']) ?>) |
                <strong>Section:</strong> <?= safe($course['section']) ?> |
                <strong>Semester:</strong> <?= safe($course['semester']) ?> |
                <strong>Year:</strong> <?= safe($course['school_year']) ?>
            </p>
        </div>
        <button onclick="window.print()" class="btn btn-secondary no-print">
            Print Records
        </button>
    </div>

    <!-- Syllabus Section with Upload -->
    <div style="background: #f8fafc; padding: 1.5rem; border-bottom: 1px solid #e2e8f0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="margin: 0;">Course Syllabus</h3>
            <?php 
            // Validate syllabus record and file existence
            $hasSyllabus = false;
            if ($syllabus && !empty($syllabus['file_path'])) {
                $fileOnDisk = realpath(__DIR__ . '/../../' . ltrim($syllabus['file_path'], '/'));
                $hasSyllabus = $fileOnDisk && file_exists($fileOnDisk);
            }
            ?>
            <?php if ($hasSyllabus): ?>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="<?= safe($syllabus['file_path']) ?>" target="_blank" class="btn btn-sm btn-success">
                        View/Download Syllabus
                    </a>
                    <button onclick="uploadSyllabus(<?= $courseId ?>, <?= $studentId ?>)" class="btn btn-sm btn-warning no-print">
                        Replace
                    </button>
                </div>
            <?php else: ?>
                <button onclick="uploadSyllabus(<?= $courseId ?>, <?= $studentId ?>)" class="btn btn-sm btn-primary no-print">
                    Upload Syllabus
                </button>
            <?php endif; ?>
        </div>

        <?php if ($hasSyllabus): ?>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.9rem;">
                <div>
                    <strong>Uploaded:</strong> <?= date('M d, Y', strtotime($syllabus['uploaded_at'])) ?>
                </div>
                <?php if ($syllabus['grading_breakdown']): ?>
                    <div>
                        <strong>Grading System:</strong>
                        <span class="badge badge-success">Configured</span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($hasSyllabus && $syllabus['grading_breakdown']): ?>
                <details style="margin-top: 1rem;">
                    <summary style="cursor: pointer; font-weight: bold; color: var(--primary-color);">
                        View Grading Breakdown
                    </summary>
                    <div style="margin-top: 1rem; padding: 1rem; background: white; border-radius: 4px;">
                        <?php
                        $breakdown = json_decode($syllabus['grading_breakdown'], true);
                        $periodOrder = ['prelim', 'midterm', 'semi_final', 'final'];
                        $sorted = [];
                        foreach ($periodOrder as $period) {
                            if (isset($breakdown[$period])) {
                                $sorted[$period] = $breakdown[$period];
                            }
                        }
                        $breakdown = $sorted;
                        foreach ($breakdown as $period => $weights):
                        ?>
                            <div style="margin-bottom: 1rem;">
                                <strong style="text-transform: capitalize;">
                                    <?= str_replace('_', ' ', $period) ?> Period:
                                </strong>
                                <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                                    <?php foreach ($weights as $component => $weight): ?>
                                        <span class="badge badge-info">
                                            <?= ucfirst(str_replace('_', ' ', $component)) ?>: <?= $weight ?>%
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        <?php else: ?>
            <div style="background: #fef3c7; padding: 1rem; border-radius: 4px; border-left: 4px solid #f59e0b;">
                <strong>No syllabus uploaded yet.</strong>
                <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem;">
                    Upload the course syllabus to enable automatic grade calculation.
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Grade Records by Period -->
    <div style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0;">Grade Records by Period</h3>
            <button onclick="addGradeComponent(<?= $courseId ?>, <?= $studentId ?>)" class="btn btn-primary no-print">
                + Add Grade Component
            </button>
        </div>

        <?php foreach ($periodGroups as $period => $components): ?>
            <div style="border: 2px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; background: white;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 2px solid #e2e8f0;">
                    <h4 style="margin: 0; text-transform: capitalize; font-size: 1.2rem;">
                        <?= str_replace('_', ' ', $period) ?> Period
                    </h4>
                    <?php
                    $periodGrade = calculatePeriodGrade($components, $syllabus);
                    if ($periodGrade !== null):
                    ?>
                        <div style="text-align: right;">
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">Period Grade</div>
                            <span class="badge badge-success" style="font-size: 1.3rem; padding: 0.5rem 1rem;">
                                <?= $periodGrade ?>%
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (empty($components)): ?>
                    <p style="color: #999; text-align: center; padding: 2rem; margin: 0;">
                        No records for this period yet.
                    </p>
                <?php else: ?>
                    <!-- Group components by type -->
                    <?php
                    $typeGroups = [];
                    foreach ($components as $c) {
                        $typeGroups[$c['component_type']][] = $c;
                    }
                    ?>

                    <?php foreach ($typeGroups as $type => $items): ?>
                        <div style="margin-bottom: 1.5rem;">
                            <h5 style="margin: 0 0 0.75rem 0; color: var(--primary-color); text-transform: capitalize; font-size: 1rem;">
                                <?= str_replace('_', ' ', $type) ?>
                            </h5>
                            <table class="data-table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th style="text-align: center;">Date</th>
                                        <th style="text-align: center;">Score</th>
                                        <th style="text-align: center;">Max Score</th>
                                        <th style="text-align: center;">Percentage</th>
                                        <th style="text-align: center;" class="no-print">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?= safe($item['component_name']) ?></td>
                                            <td style="text-align: center;">
                                                <?= date('M d, Y', strtotime($item['date_recorded'])) ?>
                                            </td>
                                            <td style="text-align: center; font-weight: bold;">
                                                <?= safe($item['score']) ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <?= safe($item['max_score']) ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php
                                                $percentage = $item['max_score'] > 0
                                                    ? round(($item['score'] / $item['max_score']) * 100, 2)
                                                    : 0;
                                                $badgeClass = $percentage >= 75 ? 'success' : ($percentage >= 60 ? 'warning' : 'danger');
                                                ?>
                                                <span class="badge badge-<?= $badgeClass ?>">
                                                    <?= $percentage ?>%
                                                </span>
                                            </td>
                                            <td style="text-align: center;" class="no-print">
                                                <button class="btn btn-sm btn-primary edit-btn" 
                                                        data-id="<?= $item['id'] ?>"
                                                        data-student="<?= $studentId ?>"
                                                        title="Edit">
                                                    Edit
                                                </button>
                                                <button class="btn btn-sm btn-danger delete-btn" 
                                                        data-id="<?= $item['id'] ?>"
                                                        data-student="<?= $studentId ?>"
                                                        title="Delete">
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php } ?>

<?php if (!$isAjax) { ?>
</body>
</html>
<?php } ?>
