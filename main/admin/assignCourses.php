<?php
require_once __DIR__ . '/../../includes/init.php';

$auth->requireRole('admin');

// Get all professors
$professors = $db->fetchAll("
    SELECT u.id, u.first_name, u.last_name, u.email
    FROM users u
    JOIN professor_profiles pp ON u.id = pp.user_id
    ORDER BY u.first_name, u.last_name
");

// Get all courses
$courses = $db->fetchAll("
    SELECT id, course_code, course_name
    FROM courses
    ORDER BY course_code
");

// Get current assignments
$assignments = $db->fetchAll("
    SELECT pca.*, u.first_name, u.last_name, c.course_code, c.course_name
    FROM professor_course_assignments pca
    JOIN users u ON pca.professor_id = u.id
    JOIN courses c ON pca.course_id = c.id
    ORDER BY u.first_name, c.course_code
");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $professorId = $_POST['professor_id'] ?? null;
    $courseId = $_POST['course_id'] ?? null;
    $section = $_POST['section'] ?? null;
    $semester = $_POST['semester'] ?? null;
    $schoolYear = $_POST['school_year'] ?? null;

    if ($professorId && $courseId) {
        try {
            $db->execute("
                INSERT INTO professor_course_assignments (professor_id, course_id, section, semester, school_year)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE section = VALUES(section), semester = VALUES(semester), school_year = VALUES(school_year)
            ", [$professorId, $courseId, $section, $semester, $schoolYear]);

            $_SESSION['success'] = 'Course assignment added successfully!';
            header('Location: assignCourses.php');
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error assigning course: ' . $e->getMessage();
        }
    }
}

// Handle deletion
if (isset($_GET['delete'])) {
    $assignmentId = (int) $_GET['delete'];
    try {
        $db->execute("DELETE FROM professor_course_assignments WHERE id = ?", [$assignmentId]);
        $_SESSION['success'] = 'Assignment removed successfully!';
        header('Location: assignCourses.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error deleting assignment: ' . $e->getMessage();
    }
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
    <title>Assign Courses to Professors - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
    <style>
        .assignment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .assignment-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            background: white;
        }
        
        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.5rem;
        }
        
        .assignment-card h3 {
            margin: 0 0 0.5rem 0;
            color: #1f2937;
        }
        
        .assignment-card p {
            margin: 0.25rem 0;
            font-size: 0.9rem;
            color: #666;
        }
        
        .delete-btn-small {
            background: #ef4444;
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: background 0.2s;
        }
        
        .delete-btn-small:hover {
            background: #dc2626;
        }
        
        .form-section {
            background: #f9fafb;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .btn-submit {
            background: #3b82f6;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.2s;
        }
        
        .btn-submit:hover {
            background: #2563eb;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="container">
            <a href="#" class="navbar-brand"><?= APP_NAME ?></a>
            <ul class="navbar-nav">
                <li><a href="../admin/dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="../logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div style="margin: 2rem 0;">
            <h1 class="card-title">Assign Courses to Professors</h1>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= safe($_SESSION['success']) ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?= safe($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Add New Assignment Form -->
        <div class="form-section card">
            <h2 class="card-title">New Assignment</h2>
            <form method="POST" style="margin-top: 1.5rem;">
                <div class="form-row">
                    <div class="form-group">
                        <label for="professor_id">Professor *</label>
                        <select id="professor_id" name="professor_id" required>
                            <option value="">Select a professor</option>
                            <?php foreach ($professors as $prof): ?>
                                <option value="<?= $prof['id'] ?>">
                                    <?= safe($prof['first_name'] . ' ' . $prof['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="course_id">Course *</label>
                        <select id="course_id" name="course_id" required>
                            <option value="">Select a course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>">
                                    <?= safe($course['course_code'] . ' - ' . $course['course_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="section">Section (optional)</label>
                        <input type="text" id="section" name="section" placeholder="e.g., A, B, 01, 02">
                    </div>
                    <div class="form-group">
                        <label for="semester">Semester (optional)</label>
                        <select id="semester" name="semester">
                            <option value="">-- Select --</option>
                            <option value="First">First Semester</option>
                            <option value="Second">Second Semester</option>
                            <option value="Summer">Summer</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="school_year">School Year (optional)</label>
                    <input type="number" id="school_year" name="school_year" placeholder="e.g., 2024" min="2020" max="2099">
                </div>

                <button type="submit" class="btn-submit">Assign Course</button>
            </form>
        </div>

        <!-- Current Assignments -->
        <div style="margin-top: 3rem;">
            <h2 class="card-title">Current Assignments (<?= count($assignments) ?>)</h2>

            <?php if (empty($assignments)): ?>
                <p style="color: #666; margin-top: 1rem;">No assignments yet.</p>
            <?php else: ?>
                <div class="assignment-grid">
                    <?php foreach ($assignments as $assign): ?>
                        <div class="assignment-card">
                            <div class="assignment-header">
                                <div>
                                    <h3><?= safe($assign['course_code']) ?></h3>
                                    <p style="font-size: 0.85rem; margin-bottom: 0.5rem;">
                                        <?= safe($assign['course_name']) ?>
                                    </p>
                                </div>
                                <a href="assignCourses.php?delete=<?= $assign['id'] ?>" 
                                   class="delete-btn-small"
                                   onclick="return confirm('Remove this assignment?');">
                                    Remove
                                </a>
                            </div>
                            <p>
                                <strong>Professor:</strong><br>
                                <?= safe($assign['first_name'] . ' ' . $assign['last_name']) ?>
                            </p>
                            <?php if ($assign['section']): ?>
                                <p>
                                    <strong>Section:</strong> <?= safe($assign['section']) ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($assign['semester']): ?>
                                <p>
                                    <strong>Semester:</strong> <?= safe($assign['semester']) ?>
                                    <?php if ($assign['school_year']): ?>
                                        (<?= $assign['school_year'] ?>)
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
