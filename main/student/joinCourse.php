<?php
require_once __DIR__ . '/../../includes/init.php';
$auth->requireRole('student');

$userId = $_SESSION['user_id'];

// Helpers
function safe($v) { return htmlspecialchars($v ?? ''); }
function current_school_year(): string {
    $y = (int)date('Y');
    $m = (int)date('n');
    // SY: if month >= June then Y-(Y+1), else (Y-1)-Y
    if ($m >= 6) return $y . '-' . ($y + 1);
    return ($y - 1) . '-' . $y;
}

$errors = [];
$success = null;
$courseCode = trim($_POST['course_code'] ?? '');
$section = trim($_POST['section'] ?? '');
$semester = trim($_POST['semester'] ?? '');
$schoolYear = trim($_POST['school_year'] ?? current_school_year());
$professorId = isset($_POST['professor_id']) ? (int)$_POST['professor_id'] : null;

// Preload professors if course code is already entered (for dropdown population)
$professors = [];
if ($courseCode !== '') {
    $courseLookup = $db->fetchOne('SELECT id FROM courses WHERE UPPER(course_code) = UPPER(?)', [$courseCode]);
    if ($courseLookup) {
        $courseIdLookup = (int)$courseLookup['id'];
        if ($section !== '') {
            $professors = $db->fetchAll(
                "SELECT pca.professor_id, u.first_name, u.last_name, u.email, pca.section
                 FROM professor_course_assignments pca
                 JOIN users u ON pca.professor_id = u.id
                 WHERE pca.course_id = ? AND pca.section = ?
                 ORDER BY u.last_name, u.first_name",
                [$courseIdLookup, $section]
            );
        } else {
            $professors = $db->fetchAll(
                "SELECT pca.professor_id, u.first_name, u.last_name, u.email, pca.section
                 FROM professor_course_assignments pca
                 JOIN users u ON pca.professor_id = u.id
                 WHERE pca.course_id = ?
                 ORDER BY u.last_name, u.first_name",
                [$courseIdLookup]
            );
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($courseCode === '') {
        $errors[] = 'Course code is required.';
    }
    if ($section === '') {
        // Allow fallback to the student's current section if not provided
        $sp = $db->fetchOne('SELECT current_section FROM student_profiles WHERE user_id = ?', [$userId]);
        if (!empty($sp['current_section'])) {
            $section = $sp['current_section'];
        } else {
            $errors[] = 'Section is required (no default found in your profile).';
        }
    }
    if ($semester === '') {
        $semester = 'Current';
    }
    if ($schoolYear === '') {
        $schoolYear = current_school_year();
    }

    // Validate professor selection if professors are available
    if (empty($errors)) {
        if (!empty($professors)) {
            if (!$professorId) {
                $errors[] = 'Please select the professor handling this course.';
            } else {
                $validProf = false;
                foreach ($professors as $p) {
                    if ((int)$p['professor_id'] === $professorId) {
                        $validProf = true;
                        break;
                    }
                }
                if (!$validProf) {
                    $errors[] = 'Selected professor is not assigned to this course/section.';
                }
            }
        }
    }

    if (empty($errors)) {
        // Lookup course by code (case-insensitive)
        $course = $db->fetchOne('SELECT id, course_code, course_name FROM courses WHERE UPPER(course_code) = UPPER(?)', [$courseCode]);
        if (!$course) {
            $errors[] = 'No course found with that course code.';
        } else {
            $courseId = (int)$course['id'];
            // Check existing enrollment
            $existing = $db->fetchOne('SELECT id, status FROM course_enrollments WHERE student_id = ? AND course_id = ?', [$userId, $courseId]);
            if ($existing && in_array($existing['status'], ['enrolled','completed'], true)) {
                $errors[] = 'You are already enrolled or have completed this course.';
            } else {
                try {
                    if ($existing) {
                        // Update status back to enrolled if previously dropped/failed, and ensure school_year is set
                        $db->query(
                            'UPDATE course_enrollments SET status = ?, semester = ?, school_year = ? WHERE id = ?',
                            ['enrolled', $semester, $schoolYear, $existing['id']]
                        );
                    } else {
                        $db->query(
                            'INSERT INTO course_enrollments (student_id, course_id, semester, school_year, status) VALUES (?, ?, ?, ?, ?)',
                            [$userId, $courseId, $semester, $schoolYear, 'enrolled']
                        );
                    }

                    // Upsert section mapping for this student+course
                    // course_sections(student_id, course_id, section)
                    $db->query('INSERT INTO course_sections (student_id, course_id, section) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE section = VALUES(section)', [$userId, $courseId, $section]);

                    // Optional: ensure a course_grades header row exists (created lazily elsewhere too)
                    $cg = $db->fetchOne('SELECT id FROM course_grades WHERE student_id = ? AND course_id = ?', [$userId, $courseId]);
                    if (!$cg) {
                        $db->query('INSERT INTO course_grades (student_id, course_id, semester, school_year) VALUES (?, ?, ?, ?)', [$userId, $courseId, $semester, $schoolYear]);
                    }

                    // Success
                    header('Location: academicProfile.php?course_id=' . $courseId . '&success=joined');
                    exit;
                } catch (Exception $e) {
                    $errors[] = 'Failed to join course: ' . $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = 'Join Course - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= safe($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="container">
        <a href="#" class="navbar-brand"><?= APP_NAME ?></a>
        <ul class="navbar-nav">
            <li><a href="academicProfile.php" class="nav-link">Back to Academic Profile</a></li>
        </ul>
    </div>
</nav>

<div class="container" style="max-width: 720px;">
    <h1 style="margin: 2rem 0 1rem;">Join a Course</h1>
    <p style="color:#555; margin-bottom: 1.5rem;">Enter the course code and your section to enroll.</p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="margin-bottom: 1rem;">
            <?php foreach ($errors as $err): ?>
                <div><?= safe($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Enrollment Details</h2>
        </div>
        <form method="POST" style="padding: 1.5rem;">
            <div style="margin-bottom: 1rem;">
                <label for="course_code" style="display:block; font-weight:600; margin-bottom: .5rem;">Course Code *</label>
                <input id="course_code" name="course_code" type="text" value="<?= safe($courseCode) ?>" placeholder="e.g., CS101" required style="width:100%; padding:.6rem; border:1px solid #e2e8f0; border-radius:4px;" />
            </div>
            <div style="margin-bottom: 1rem;">
                <label for="section" style="display:block; font-weight:600; margin-bottom: .5rem;">Section *</label>
                <input id="section" name="section" type="text" value="<?= safe($section) ?>" placeholder="e.g., A1" style="width:100%; padding:.6rem; border:1px solid #e2e8f0; border-radius:4px;" />
                <small style="color:#666;">If left blank, we'll use your current section from your profile.</small>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <label for="semester" style="display:block; font-weight:600; margin-bottom: .5rem;">Semester</label>
                    <select id="semester" name="semester" style="width:100%; padding:.6rem; border:1px solid #e2e8f0; border-radius:4px;">
                        <option value="Current" <?= $semester==='Current'?'selected':'' ?>>Current</option>
                        <option value="1st" <?= $semester==='1st'?'selected':'' ?>>1st</option>
                        <option value="2nd" <?= $semester==='2nd'?'selected':'' ?>>2nd</option>
                        <option value="Summer" <?= $semester==='Summer'?'selected':'' ?>>Summer</option>
                    </select>
                </div>
                <div>
                    <label for="school_year" style="display:block; font-weight:600; margin-bottom: .5rem;">School Year</label>
                    <input id="school_year" name="school_year" type="text" value="<?= safe($schoolYear) ?>" placeholder="e.g., 2025-2026" style="width:100%; padding:.6rem; border:1px solid #e2e8f0; border-radius:4px;" />
                </div>
            </div>
            <div style="margin-top: 1rem;">
                <label for="professor_id" style="display:block; font-weight:600; margin-bottom: .5rem;">Professor (assigned to this course/section)</label>
                <?php if (!empty($professors)): ?>
                    <select id="professor_id" name="professor_id" required style="width:100%; padding:.6rem; border:1px solid #e2e8f0; border-radius:4px;">
                        <option value="">Select professor...</option>
                        <?php foreach ($professors as $p): ?>
                            <option value="<?= $p['professor_id'] ?>" <?= ($professorId == $p['professor_id']) ? 'selected' : '' ?>>
                                <?= safe($p['last_name'] . ', ' . $p['first_name']) ?> (Section <?= safe($p['section']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <div style="padding: 0.75rem; background:#fef3c7; border:1px solid #fcd34d; border-radius:4px; color:#92400e;">
                        No professor assignment found yet for this course/section. Please confirm the course code and section, then submit again.
                    </div>
                <?php endif; ?>
            </div>
            <div style="margin-top:1.5rem; display:flex; gap:1rem;">
                <button type="submit" class="btn btn-primary" style="flex:1;">Join Course</button>
                <a href="academicProfile.php" class="btn btn-secondary" style="flex:1; text-align:center;">Cancel</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
