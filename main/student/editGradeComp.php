<?php
require_once __DIR__ . '/../../includes/init.php';
$auth->requireRole('student');

$userId = $_SESSION['user_id'];
$componentId = $_GET['id'] ?? null;

if (!$componentId) {
    $_SESSION['error'] = 'No component ID provided.';
    header('Location: dashboard.php');
    exit;
}

// Fetch the component with ownership verification
$component = $db->fetchOne("
    SELECT gc.*, cg.student_id, cg.course_id, c.course_code, c.course_name
    FROM grade_components gc
    JOIN course_grades cg ON gc.course_grade_id = cg.id
    JOIN courses c ON cg.course_id = c.id
    WHERE gc.id = ?
", [$componentId]);

if (!$component) {
    $_SESSION['error'] = 'Component not found.';
    header('Location: dashboard.php');
    exit;
}

if ($component['student_id'] != $userId) {
    $_SESSION['error'] = 'Unauthorized access.';
    header('Location: academicProfile.php?course_id=' . $component['course_id']);
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
    if ($weight < 0 || $weight > 100) {
        $errors[] = 'Weight must be between 0 and 100.';
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
            header('Location: academicProfile.php?course_id=' . $component['course_id']);
            exit;
        } catch (Exception $e) {
            $errors[] = 'Failed to update component: ' . $e->getMessage();
        }
    }

    // Store errors in session
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
    }
}

$pageTitle = 'Edit Grade Component';
// include __DIR__ . '/../../includes/header.php';
?>

<div class="container" style="max-width: 800px; margin: 2rem auto;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Edit Grade Component</h2>
            <p style="margin: 0.5rem 0 0 0; color: #666;">
                <?= htmlspecialchars($component['course_code']) ?> - <?= htmlspecialchars($component['course_name']) ?>
                | <?= ucfirst(str_replace('_', ' ', $component['period'])) ?> Period
            </p>
        </div>

        <?php if (isset($_SESSION['errors'])): ?>
            <div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 1rem; margin: 1rem 1.5rem;">
                <strong style="color: #991b1b;">Error:</strong>
                <ul style="margin: 0.5rem 0 0 0; padding-left: 1.5rem; color: #991b1b;">
                    <?php foreach ($_SESSION['errors'] as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php unset($_SESSION['errors']); ?>
        <?php endif; ?>

        <div style="padding: 1.5rem;">
            <form method="POST" action="">
                <div style="margin-bottom: 1.5rem;">
                    <label class="form-label">Period</label>
                    <input type="text" 
                           class="form-control" 
                           value="<?= ucfirst(str_replace('_', ' ', $component['period'])) ?>" 
                           disabled 
                           style="background: #f3f4f6; cursor: not-allowed;">
                    <small style="color: #666; font-size: 0.85rem;">Period cannot be changed</small>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label class="form-label">Component Type</label>
                    <input type="text" 
                           class="form-control" 
                           value="<?= ucfirst(str_replace('_', ' ', $component['component_type'])) ?>" 
                           disabled 
                           style="background: #f3f4f6; cursor: not-allowed;">
                    <small style="color: #666; font-size: 0.85rem;">Component type cannot be changed</small>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label for="component_name" class="form-label">Component Name *</label>
                    <input type="text" 
                           id="component_name" 
                           name="component_name" 
                           class="form-control" 
                           value="<?= htmlspecialchars($component['component_name']) ?>"
                           placeholder="e.g., Quiz 1, Midterm Exam, Project A"
                           required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <label for="score" class="form-label">Score *</label>
                        <input type="number" 
                               id="score" 
                               name="score" 
                               class="form-control" 
                               value="<?= $component['score'] ?>"
                               step="0.01" 
                               min="0" 
                               required>
                    </div>
                    <div>
                        <label for="max_score" class="form-label">Max Score *</label>
                        <input type="number" 
                               id="max_score" 
                               name="max_score" 
                               class="form-control" 
                               value="<?= $component['max_score'] ?>"
                               step="0.01" 
                               min="0.01" 
                               required>
                    </div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label for="weight" class="form-label">Weight (%)</label>
                    <input type="number" 
                           id="weight" 
                           name="weight" 
                           class="form-control" 
                           value="<?= $component['weight'] ?>"
                           step="0.01" 
                           min="0" 
                           max="100">
                    <small style="color: #666; font-size: 0.85rem;">
                        Leave as 0 if weight is determined by syllabus
                    </small>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label for="date_recorded" class="form-label">Date Recorded</label>
                    <input type="date" 
                           id="date_recorded" 
                           name="date_recorded" 
                           class="form-control" 
                           value="<?= $component['date_recorded'] ?>">
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label for="notes" class="form-label">Notes (Optional)</label>
                    <textarea id="notes" 
                              name="notes" 
                              class="form-control" 
                              rows="3" 
                              placeholder="Add any additional notes about this grade..."><?= htmlspecialchars($component['notes'] ?? '') ?></textarea>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                    <a href="academicProfile.php?course_id=<?= $component['course_id'] ?>" 
                       class="btn btn-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        Update Component
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>