<?php
require_once __DIR__ . '/../../includes/init.php';
$auth->requireRole('professor');

header('Content-Type: application/json');

$professorId = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$componentId = $input['id'] ?? null;
$studentId = $input['student_id'] ?? null;

if (!$componentId || !$studentId) {
    echo json_encode(['success' => false, 'message' => 'Component ID and Student ID required']);
    exit;
}

// Verify access: professor must be assigned to the course
$component = $db->fetchOne("
    SELECT gc.*, cg.student_id, cg.course_id
    FROM grade_components gc
    JOIN course_grades cg ON gc.course_grade_id = cg.id
    JOIN professor_course_assignments pca ON cg.course_id = pca.course_id
    WHERE gc.id = ? AND cg.student_id = ? AND pca.professor_id = ?
", [$componentId, $studentId, $professorId]);

if (!$component) {
    echo json_encode(['success' => false, 'message' => 'Component not found or access denied']);
    exit;
}

// Delete the component
try {
    $db->query("DELETE FROM grade_components WHERE id = ?", [$componentId]);
    echo json_encode(['success' => true, 'message' => 'Component deleted successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to delete component: ' . $e->getMessage()]);
}
