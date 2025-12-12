<?php
require_once __DIR__ . '/../../includes/init.php';
$auth->requireRole('student');

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$componentId = $input['id'] ?? null;

if (!$componentId) {
    echo json_encode(['success' => false, 'message' => 'Component ID required']);
    exit;
}

// Verify ownership
$component = $db->fetchOne("
    SELECT gc.*, cg.student_id
    FROM grade_components gc
    JOIN course_grades cg ON gc.course_grade_id = cg.id
    WHERE gc.id = ?
", [$componentId]);

if (!$component) {
    echo json_encode(['success' => false, 'message' => 'Component not found']);
    exit;
}

if ($component['student_id'] != $userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Delete the component
try {
    $db->query("DELETE FROM grade_components WHERE id = ?", [$componentId]);
    echo json_encode(['success' => true, 'message' => 'Component deleted successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to delete component: ' . $e->getMessage()]);
}