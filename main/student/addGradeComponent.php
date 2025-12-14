<?php
require_once __DIR__ . '/../../includes/init.php';
$auth->requireRole('student');

$userId = $_SESSION['user_id'];
$courseId = $_GET['course_id'] ?? null;
$period = $_GET['period'] ?? 'prelim';

if (!$courseId) {
    die("Course ID required.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $componentType = $_POST['component_type'] ?? '';
    $componentName = $_POST['component_name'] ?? '';
    $score = $_POST['score'] ?? 0;
    $maxScore = $_POST['max_score'] ?? 100;
    $weight = $_POST['weight'] ?? 50;
    $notes = $_POST['notes'] ?? '';
    
    // Get or create course_grades entry
    $courseGrade = $db->fetchOne("
        SELECT id FROM course_grades 
        WHERE student_id = ? AND course_id = ?
    ", [$userId, $courseId]);
    
    if (!$courseGrade) {
        $db->query("
            INSERT INTO course_grades (student_id, course_id, semester, school_year)
            SELECT ?, ?, ce.semester, YEAR(NOW())
            FROM course_enrollments ce
            WHERE ce.student_id = ? AND ce.course_id = ?
            LIMIT 1
        ", [$userId, $courseId, $userId, $courseId]);
        
        $courseGradeId = $db->lastInsertId();
    } else {
        $courseGradeId = $courseGrade['id'];
    }
    
    // Insert grade component
    $db->query("
        INSERT INTO grade_components 
        (course_grade_id, period, component_type, component_name, score, max_score, weight, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ", [$courseGradeId, $period, $componentType, $componentName, $score, $maxScore, $weight, $notes]);
    
    // Redirect back to academic profile
    header("Location: academicProfile.php?course_id={$courseId}&success=added");
    exit;
}

$course = $db->fetchOne("
    SELECT * FROM courses WHERE id = ?
", [$courseId]);

function safe($value) {
    return htmlspecialchars($value ?? '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Grade Component - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="container">
        <a href="#" class="navbar-brand"><?= APP_NAME ?></a>
        <ul class="navbar-nav">
            <li><a href="academicProfile.php?course_id=<?= $courseId ?>" class="nav-link">Back to Profile</a></li>
        </ul>
    </div>
</nav>

<div class="container" style="max-width: 800px;">
    <h1 style="margin: 2rem 0 1rem;">Add Grade Component</h1>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <?= safe($course['course_code']) ?> - <?= ucfirst(str_replace('_', ' ', $period)) ?> Period
            </h2>
        </div>
        
        <form method="POST" action="" style="padding: 1.5rem;">
            <div style="margin-bottom: 1rem;">
                <label for="component_type" style="display: block; margin-bottom: 0.5rem; font-weight: bold;">
                    Component Type *
                </label>
                <select name="component_type" id="component_type" required 
                        style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 4px;">
                    <option value="">Select type...</option>
                    <option value="class_standing">Class Standing</option>
                    <option value="exam">Exam</option>
                    <option value="activity">Activity</option>
                    <option value="performance">Performance Task</option>
                </select>
            </div>

            <div style="margin-bottom: 1rem;">
                <label for="component_name" style="display: block; margin-bottom: 0.5rem; font-weight: bold;">
                    Component Name *
                </label>
                <input type="text" name="component_name" id="component_name" required
                       placeholder="e.g., Quiz 1, Lab Exercise 3, Midterm Exam"
                       style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 4px;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label for="score" style="display: block; margin-bottom: 0.5rem; font-weight: bold;">
                        Score *
                    </label>
                    <input type="number" name="score" id="score" required step="0.01" min="0"
                           style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 4px;">
                </div>

                <div>
                    <label for="max_score" style="display: block; margin-bottom: 0.5rem; font-weight: bold;">
                        Max Score *
                    </label>
                    <input type="number" name="max_score" id="max_score" required step="0.01" min="0" value="100"
                           style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 4px;">
                </div>

                <div>
                    <label for="weight" style="display: block; margin-bottom: 0.5rem; font-weight: bold;">
                        Weight (%) *
                    </label>
                    <input type="number" name="weight" id="weight" required step="0.01" min="0" max="100" value="50"
                           style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 4px;">
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label for="notes" style="display: block; margin-bottom: 0.5rem; font-weight: bold;">
                    Notes (Optional)
                </label>
                <textarea name="notes" id="notes" rows="3"
                          placeholder="Additional notes about this grade..."
                          style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 4px;"></textarea>
            </div>

            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    Save Grade Component
                </button>
                <a href="academicProfile.php?course_id=<?= $courseId ?>" 
                   class="btn btn-secondary" style="flex: 1; text-align: center;">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
</body>
</html>