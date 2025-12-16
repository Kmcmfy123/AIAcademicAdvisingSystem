<?php
require_once __DIR__ . '/../../includes/init.php';
$auth->requireRole('professor');

$professorId = $_SESSION['user_id'];
$studentId = $_GET['student_id'] ?? null;
$courseId = $_GET['course_id'] ?? null;
$isReplace = isset($_GET['replace']);

if (!$courseId || !$studentId) {
    die("Course ID and Student ID required.");
}

// Verify professor has access
$hasAccess = $db->fetchOne("
    SELECT 1 FROM course_enrollments ce
    JOIN professor_course_assignments pca ON ce.course_id = pca.course_id
    WHERE ce.student_id = ? AND ce.course_id = ? AND pca.professor_id = ?
    LIMIT 1
", [$studentId, $courseId, $professorId]);

if (!$hasAccess) {
    die("You don't have permission to manage this student's course.");
}

$course = $db->fetchOne("
    SELECT c.*, ce.semester, cg.school_year
    FROM courses c
    JOIN course_enrollments ce ON c.id = ce.course_id
    LEFT JOIN course_grades cg ON ce.student_id = cg.student_id AND ce.course_id = cg.course_id
    WHERE c.id = ? AND ce.student_id = ?
", [$courseId, $studentId]);

if (!$course) {
    die("Course not found.");
}

$student = $db->fetchOne("
    SELECT u.first_name, u.last_name FROM users u WHERE u.id = ?
", [$studentId]);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadDir = __DIR__ . '/../../uploads/syllabi/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Handle file upload
    if (isset($_FILES['syllabus_file']) && $_FILES['syllabus_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['syllabus_file'];
        $allowedTypes = ['application/pdf'];
        
        if (!in_array($file['type'], $allowedTypes)) {
            $error = "Only PDF files are allowed.";
        } else if ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
            $error = "File size must be less than 10MB.";
        } else {
            $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                // Get grading breakdown from form
                $gradingBreakdown = [
                    'prelim' => [
                        'class_standing' => floatval($_POST['prelim_class_standing'] ?? 60),
                        'exam' => floatval($_POST['prelim_exam'] ?? 40),
                    ],
                    'midterm' => [
                        'class_standing' => floatval($_POST['midterm_class_standing'] ?? 60),
                        'exam' => floatval($_POST['midterm_exam'] ?? 40),
                    ],
                    'semi_final' => [
                        'class_standing' => floatval($_POST['semi_final_class_standing'] ?? 60),
                        'exam' => floatval($_POST['semi_final_exam'] ?? 40),
                    ],
                    'final' => [
                        'class_standing' => floatval($_POST['final_class_standing'] ?? 60),
                        'exam' => floatval($_POST['final_exam'] ?? 40),
                    ]
                ];
                
                // Add activity and performance if provided
                foreach (['prelim', 'midterm', 'semi_final', 'final'] as $period) {
                    if (!empty($_POST["{$period}_activity"])) {
                        $gradingBreakdown[$period]['activity'] = floatval($_POST["{$period}_activity"]);
                    }
                    if (!empty($_POST["{$period}_performance"])) {
                        $gradingBreakdown[$period]['performance'] = floatval($_POST["{$period}_performance"]);
                    }
                }
                
                // Insert syllabus record
                $db->query("
                    INSERT INTO course_syllabi 
                    (course_id, school_year, semester, professor_id, file_path, grading_breakdown)
                    VALUES (?, ?, ?, ?, ?, ?)
                ", [
                    $courseId,
                    $course['school_year'] ?? date('Y') . '-' . (date('Y') + 1),
                    $course['semester'] ?? 'Current',
                    $professorId,
                    '/uploads/syllabi/' . $fileName,
                    json_encode($gradingBreakdown)
                ]);
                
                // Redirect back
                header("Location: studentProfile.php?id={$studentId}&success=syllabus_uploaded");
                exit;
            } else {
                $error = "Failed to upload file.";
            }
        }
    } else {
        $error = "Please select a file to upload.";
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
    <title>Upload Syllabus - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
    <style>
        .period-section {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            background: #f8fafc;
        }
        .weight-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .weight-input {
            display: flex;
            flex-direction: column;
        }
        .weight-input label {
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .weight-input input {
            padding: 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        .total-weight {
            text-align: right;
            margin-top: 0.5rem;
            font-weight: bold;
        }
        .total-weight.valid {
            color: #10b981;
        }
        .total-weight.invalid {
            color: #ef4444;
        }
    </style>
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

<div class="container" style="max-width: 900px;">
    <h1 style="margin: 2rem 0 1rem;">
        <?= $isReplace ? 'Replace' : 'Upload' ?> Course Syllabus
    </h1>
    <p style="color: #666; margin-bottom: 1.5rem;">
        <strong>Student:</strong> <?= safe($student['first_name'] . ' ' . $student['last_name']) ?>
    </p>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <?= safe($course['course_code']) ?> - <?= safe($course['course_name']) ?>
            </h2>
        </div>
        
        <?php if (isset($error)): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 1rem; margin: 1rem; border-radius: 4px;">
                <strong>Error:</strong> <?= safe($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" enctype="multipart/form-data" style="padding: 1.5rem;">
            
            <!-- File Upload -->
            <div style="margin-bottom: 2rem;">
                <label for="syllabus_file" style="display: block; margin-bottom: 0.5rem; font-weight: bold;">
                    Upload Syllabus PDF *
                </label>
                <input type="file" name="syllabus_file" id="syllabus_file" accept=".pdf" required
                       style="width: 100%; padding: 0.5rem; border: 2px dashed #e2e8f0; border-radius: 4px;">
                <small style="color: #666; display: block; margin-top: 0.5rem;">
                    Maximum file size: 10MB. Only PDF files are allowed.
                </small>
            </div>

            <!-- Grading Breakdown Configuration -->
            <h3 style="margin-bottom: 1rem;">Configure Grading System</h3>
            <p style="color: #666; margin-bottom: 1.5rem;">
                Set the percentage weight for each component per period. Total must equal 100% for each period.
            </p>

            <?php 
            $periods = [
                'prelim' => 'Prelim',
                'midterm' => 'Midterm',
                'semi_final' => 'Semi-Final',
                'final' => 'Final'
            ];
            
            foreach ($periods as $periodKey => $periodName): 
            ?>
                <div class="period-section">
                    <h4 style="margin: 0 0 1rem 0;"><?= $periodName ?> Period</h4>
                    
                    <div class="weight-grid">
                        <div class="weight-input">
                            <label for="<?= $periodKey ?>_class_standing">Class Standing %</label>
                            <input type="number" 
                                   name="<?= $periodKey ?>_class_standing" 
                                   id="<?= $periodKey ?>_class_standing"
                                   value="40" 
                                   min="0" 
                                   max="100" 
                                   step="0.01"
                                   onchange="calculateTotal('<?= $periodKey ?>')"
                                   required>
                        </div>
                        
                        <div class="weight-input">
                            <label for="<?= $periodKey ?>_exam">Exam %</label>
                            <input type="number" 
                                   name="<?= $periodKey ?>_exam" 
                                   id="<?= $periodKey ?>_exam"
                                   value="40" 
                                   min="0" 
                                   max="100" 
                                   step="0.01"
                                   onchange="calculateTotal('<?= $periodKey ?>')"
                                   required>
                        </div>
                        
                        <div class="weight-input">
                            <label for="<?= $periodKey ?>_activity">Character %</label>
                            <input type="number" 
                                   name="<?= $periodKey ?>_activity" 
                                   id="<?= $periodKey ?>_activity"
                                   value="10" 
                                   min="0" 
                                   max="100" 
                                   step="0.01"
                                   placeholder="0"
                                   onchange="calculateTotal('<?= $periodKey ?>')"
                                   required>
                        </div>
                        
                        <div class="weight-input">
                            <label for="<?= $periodKey ?>_performance">Project %</label>
                            <input type="number" 
                                   name="<?= $periodKey ?>_performance" 
                                   id="<?= $periodKey ?>_performance"
                                   value="10" 
                                   min="0" 
                                   max="100" 
                                   step="0.01"
                                   placeholder="0"
                                   onchange="calculateTotal('<?= $periodKey ?>')"
                                   required>
                        </div>
                    </div>
                    
                    <div class="total-weight" id="<?= $periodKey ?>_total">
                        Total: 100%
                    </div>
                </div>
            <?php endforeach; ?>

            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;" id="submitBtn">
                    Upload Syllabus & Save Grading System
                </button>
                <a href="studentProfile.php?id=<?= $studentId ?>" 
                   class="btn btn-secondary" style="flex: 1; text-align: center;">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function calculateTotal(period) {
    const classStanding = parseFloat(document.getElementById(`${period}_class_standing`).value) || 0;
    const exam = parseFloat(document.getElementById(`${period}_exam`).value) || 0;
    const activity = parseFloat(document.getElementById(`${period}_activity`).value) || 0;
    const performance = parseFloat(document.getElementById(`${period}_performance`).value) || 0;
    
    const total = classStanding + exam + activity + performance;
    const totalElement = document.getElementById(`${period}_total`);
    
    totalElement.textContent = `Total: ${total.toFixed(2)}%`;
    
    if (Math.abs(total - 100) < 0.01) {
        totalElement.className = 'total-weight valid';
    } else {
        totalElement.className = 'total-weight invalid';
    }
    
    validateForm();
}

function validateForm() {
    const periods = ['prelim', 'midterm', 'semi_final', 'final'];
    let allValid = true;
    
    periods.forEach(period => {
        const totalElement = document.getElementById(`${period}_total`);
        if (!totalElement.classList.contains('valid')) {
            allValid = false;
        }
    });
    
    document.getElementById('submitBtn').disabled = !allValid;
}

// Initialize totals on page load
['prelim', 'midterm', 'semi_final', 'final'].forEach(period => {
    calculateTotal(period);
});
</script>
</body>
</html>
