<?php
require_once __DIR__ . '/../../includes/init.php';
$auth->requireRole('professor');

$professorId = $_SESSION['user_id'];
$componentId = $_GET['id'] ?? null;
$studentId = $_GET['student_id'] ?? null;

if (!$componentId || !$studentId) {
    $_SESSION['error'] = 'Missing component ID or student ID.';
    header('Location: studentVIew.php');
    exit;
}

// Fetch the component with access verification
$component = $db->fetchOne("
    SELECT gc.*, cg.student_id, cg.course_id, c.course_code, c.course_name
    FROM grade_components gc
    JOIN course_grades cg ON gc.course_grade_id = cg.id
    JOIN courses c ON cg.course_id = c.id
    JOIN professor_course_assignments pca ON c.id = pca.course_id
    WHERE gc.id = ? AND cg.student_id = ? AND pca.professor_id = ?
", [$componentId, $studentId, $professorId]);

if (!$component) {
    $_SESSION['error'] = 'Component not found or access denied.';
    header('Location: studentVIew.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $componentName = trim($_POST['component_name'] ?? '');
    $score = floatval($_POST['score'] ?? 0);
    $maxScore = floatval($_POST['max_score'] ?? 0);
    $weight = floatval($_POST['weight'] ?? 0);
    $dateRecorded = $_POST['date_recorded'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');

    // Validation
    $errors = [];
    if (empty($componentName)) {
        $errors[] = 'Component name is required.';
    }
    if ($maxScore <= 0) {
        $errors[] = 'Max score must be greater than 0.';
    }
    if ($score < 0 || $score > $maxScore) {
        $errors[] = 'Score must be between 0 and max score.';
    }

    if (empty($errors)) {
        try {
            $db->query("
                UPDATE grade_components 
                SET component_name = ?, 
                    score = ?, 
                    max_score = ?, 
                    weight = ?, 
                    date_recorded = ?, 
                    notes = ?
                WHERE id = ?
            ", [$componentName, $score, $maxScore, $weight, $dateRecorded, $notes, $componentId]);

            $_SESSION['success'] = 'Grade component updated successfully!';
            header('Location: studentProfile.php?id=' . $studentId);
            exit;
        } catch (Exception $e) {
            $errors[] = 'Failed to update component: ' . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
    }
}

function safe($value) {
    return htmlspecialchars($value ?? '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Grade Component - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="container">
        <a href="#" class="navbar-brand"><?= APP_NAME ?></a>
        <ul class="navbar-nav">
            <li><a href="studentProfile.php?id=<?= $studentId ?>" class="nav-link">Back to Student Profile</a></li>
        </ul>
    </div>
</nav>

<div class="container" style="max-width: 800px;">
    <h1 style="margin: 2rem 0 1rem;">Edit Grade Component</h1>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Edit Component</h2>
            <p style="margin: 0.5rem 0 0 0; color: #666;">
                <?= safe($component['course_code']) ?> - <?= safe($component['course_name']) ?>
                | <?= ucfirst(str_replace('_', ' ', $component['period'])) ?> Period
            </p>
        </div>

        <?php if (isset($_SESSION['errors'])): ?>
            <div class="alert alert-danger" style="margin: 1rem;">
                <?php foreach ($_SESSION['errors'] as $error): ?>
                    <p style="margin: 0.5rem 0;"><?= safe($error) ?></p>
                <?php endforeach; ?>
                <?php unset($_SESSION['errors']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" style="padding: 1.5rem;">
            <div style="margin-bottom: 1rem;">
                <label for="component_name" style="display: block; margin-bottom: 0.5rem; font-weight: bold;">
                    Component Name *
                </label>
                <input type="text" name="component_name" id="component_name" required
                       value="<?= safe($component['component_name']) ?>"
                       style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 4px;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label for="score" style="display: block; margin-bottom: 0.5rem; font-weight: bold;">
                        Score *
                    </label>
                    <input type="number" name="score" id="score" min="0" step="0.01" required
                           value="<?= safe($component['score']) ?>"
                           style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 4px;">
                </div>
                <div>
                    <label for="max_score" style="display: block; margin-bottom: 0.5rem; font-weight: bold;">
                        Max Score *
                    </label>
                    <input type="number" name="max_score" id="max_score" min="1" step="0.01" required
                           value="<?= safe($component['max_score']) ?>"
                           style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 4px;">
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label for="weight" style="display: block; margin-bottom: 0.5rem; font-weight: bold;">
                    Weight (%)
                </label>
                <input type="number" name="weight" id="weight" min="0" max="100" step="0.01"
                       value="<?= safe($component['weight']) ?>"
                       style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 1rem;">
                <label for="date_recorded" style="display: block; margin-bottom: 0.5rem; font-weight: bold;">
                    Date Recorded
                </label>
                <input type="date" name="date_recorded" id="date_recorded"
                       value="<?= safe($component['date_recorded']) ?>"
                       style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 1rem;">
                <label for="notes" style="display: block; margin-bottom: 0.5rem; font-weight: bold;">
                    Notes (Optional)
                </label>
                <textarea name="notes" id="notes" rows="3"
                          style="width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 4px;"><?= safe($component['notes']) ?></textarea>
            </div>

            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-primary">Update Component</button>
                <a href="studentProfile.php?id=<?= $studentId ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
