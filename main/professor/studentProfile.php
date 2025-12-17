<?php
require_once __DIR__ . '/../../includes/init.php';
$auth->requireRole('professor');

$professorId = $_SESSION['user_id'];
$studentId = $_GET['id'] ?? null;

if (!$studentId) {
    header('Location: studentVIew.php');
    exit;
}

// Fetch student profile
$student = $db->fetchOne("
    SELECT u.*, sp.student_id AS school_id, sp.major, sp.gpa,
           sp.credits_completed, sp.enrollment_year, sp.current_section,
           sp.expected_graduation, sp.academic_standing
    FROM users u
    LEFT JOIN student_profiles sp ON sp.user_id = u.id
    WHERE u.id = ? AND u.role = 'student'
", [$studentId]);

if (!$student) {
    die("<h2 style='color:red;'>Student not found.</h2>");
}

// Verify professor has access to this student (student must be in one of professor's assigned courses)
$hasAccess = $db->fetchOne("
    SELECT 1 FROM course_enrollments ce
    JOIN professor_course_assignments pca ON ce.course_id = pca.course_id
    WHERE ce.student_id = ? AND pca.professor_id = ?
    LIMIT 1
", [$studentId, $professorId]);

if (!$hasAccess) {
    die("<h2 style='color:red;'>You don't have permission to view this student.</h2>");
}

// Fetch only courses where professor is assigned
$courses = $db->fetchAll("
    SELECT ce.*, c.course_code, c.course_name, c.credits,
           COALESCE(cs.section, sp.current_section) as section,
           cg.school_year, cg.grade as final_grade, pca.section as prof_section
    FROM course_enrollments ce
    JOIN courses c ON ce.course_id = c.id
    JOIN professor_course_assignments pca ON c.id = pca.course_id
    LEFT JOIN student_profiles sp ON ce.student_id = sp.user_id
    LEFT JOIN course_sections cs ON cs.student_id = ce.student_id AND cs.course_id = c.id
    LEFT JOIN course_grades cg ON ce.student_id = cg.student_id AND ce.course_id = cg.course_id
    WHERE ce.student_id = ? AND pca.professor_id = ?
    ORDER BY ce.semester DESC, c.course_code ASC
", [$studentId, $professorId]);

// Group courses by status
$enrolledCourses = array_filter($courses, fn($c) => $c['status'] === 'enrolled');
$completedCourses = array_filter($courses, fn($c) => $c['status'] === 'completed');
$droppedCourses = array_filter($courses, fn($c) => $c['status'] === 'dropped');

// Fetch professor remarks
$remarks = $db->fetchAll("
    SELECT csr.*, c.course_code, c.course_name
    FROM course_specific_remarks csr
    LEFT JOIN courses c ON csr.course_id = c.id
    WHERE csr.student_id = ? AND csr.professor_id = ?
    ORDER BY csr.created_at DESC
", [$studentId, $professorId]);

// Fetch advising sessions
$sessions = $db->fetchAll("
    SELECT * FROM advising_sessions
    WHERE student_id = ? AND professor_id = ?
    ORDER BY session_date DESC
", [$studentId, $professorId]);

// Handle adding remark
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_remark') {
        $courseId = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
        $remarkType = sanitize($_POST['remark_type']);
        $remarkText = sanitize($_POST['remark_text']);
        $actionRequired = isset($_POST['action_required']) ? 1 : 0;
        
        $db->query("
            INSERT INTO course_specific_remarks
            (student_id, course_id, professor_id, remark_type, remark_text, action_required)
            VALUES (?, ?, ?, ?, ?, ?)
        ", [$studentId, $courseId, $professorId, $remarkType, $remarkText, $actionRequired]);
        
        header("Location: studentProfile.php?id={$studentId}&success=remark_added");
        exit;
    }
}

function safe($value, $fallback = 'N/A') {
    return htmlspecialchars($value ?? $fallback);
}

function getGradeClass($grade) {
    if (!$grade || !is_numeric($grade)) return 'secondary';
    if ($grade >= 90) return 'success';
    if ($grade >= 75) return 'primary';
    if ($grade >= 60) return 'warning';
    return 'danger';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= safe($student['first_name'] . ' ' . $student['last_name']) ?> - Profile</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 2rem;
            width: 90%;
            max-width: 600px;
            border-radius: 8px;
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
                <li><a href="dashboard_prof.php" class="nav-link">Dashboard</a></li>
                <li><a href="studentVIew.php" class="nav-link">Back to Students</a></li>
                <li><a href="../logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin: 2rem 0 1rem;">
            <h1>Student Profile: <?= safe($student['first_name'] . ' ' . $student['last_name']) ?></h1>
            <button onclick="window.print()" class="btn btn-secondary no-print">Print Profile</button>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?php if ($_GET['success'] === 'remark_added'): ?>
                    Remark added successfully!
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Student Information Card -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Student Information</h2>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <div><strong>Name:</strong><br><?= safe($student['first_name'] . ' ' . $student['last_name']) ?></div>
                <div><strong>Email:</strong><br><?= safe($student['email']) ?></div>
                <div><strong>Student ID:</strong><br><?= safe($student['school_id']) ?></div>
                <div><strong>Major:</strong><br><?= safe($student['major']) ?></div>
                <div><strong>Current Section:</strong><br><?= safe($student['current_section']) ?></div>
                <div><strong>GPA:</strong><br><span class="badge badge-<?= getGradeClass($student['gpa'] * 25) ?>"><?= formatGPA($student['gpa']) ?></span></div>
                <div><strong>Credits:</strong><br><?= $student['credits_completed'] ?? 0 ?> units</div>
                <div><strong>Academic Standing:</strong><br><?= safe($student['academic_standing']) ?></div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= count($completedCourses) ?></div>
                <div class="stat-label">Completed Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($enrolledCourses) ?></div>
                <div class="stat-label">Current Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= formatGPA($student['gpa']) ?></div>
                <div class="stat-label">Current GPA</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($sessions) ?></div>
                <div class="stat-label">Advising Sessions</div>
            </div>
        </div>

        <!-- Current Courses with Grade Management -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Current Enrolled Courses - Select to Manage Grades</h2>
            </div>
            <?php if (empty($enrolledCourses)): ?>
                <p>No enrolled courses.</p>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; padding: 1rem;">
                    <?php foreach ($enrolledCourses as $course): ?>
                        <div class="course-card" style="border: 2px solid #e2e8f0; padding: 1rem; border-radius: 8px; cursor: pointer; transition: all 0.3s;"
                             onclick="loadCourseGrades(<?= $course['course_id'] ?>, <?= $studentId ?>)"
                             onmouseover="this.style.borderColor='var(--primary-color)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.1)'"
                             onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none'">
                            <h3 style="margin: 0 0 0.5rem 0; font-size: 1.1rem;"><?= safe($course['course_code']) ?></h3>
                            <p style="margin: 0 0 0.5rem 0; color: #666; font-size: 0.9rem;">
                                <?= safe($course['course_name']) ?>
                            </p>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.75rem;">
                                <span class="badge badge-primary"><?= safe($course['section']) ?></span>
                                <span style="font-size: 0.85rem; color: #666;"><?= $course['credits'] ?> units</span>
                            </div>
                            <div style="margin-top: 0.5rem; font-size: 0.85rem; color: #666;">
                                <?= safe($course['semester']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Course Grade Details Container -->
        <div id="course-grades-container" style="display: none;">
            <!-- Loaded dynamically via AJAX -->
        </div>

        <!-- Academic Performance -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Academic Performance History</h2>
            </div>
            <?php if (empty($completedCourses)): ?>
                <p>No completed courses yet.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Section</th>
                            <th>Semester</th>
                            <th>Final Grade</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($completedCourses as $course): ?>
                            <tr>
                                <td><strong><?= safe($course['course_code']) ?></strong><br>
                                    <small style="color: #666;"><?= safe($course['course_name']) ?></small>
                                </td>
                                <td><?= safe($course['section']) ?></td>
                                <td><?= safe($course['semester']) ?></td>
                                <td>
                                    <?php if ($course['final_grade']): ?>
                                        <strong>
                                            <span class="badge badge-<?= getGradeClass($course['final_grade']) ?>">
                                                <?= number_format($course['final_grade'], 2) ?>
                                            </span>
                                        </strong>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-success"><?= ucfirst($course['status']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Professor Remarks -->
        <div class="card no-print">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2 class="card-title">Your Remarks & Notes</h2>
                <button onclick="openRemarkModal()" class="btn btn-primary">Add New Remark</button>
            </div>
            <?php if (empty($remarks)): ?>
                <p>No remarks added yet.</p>
            <?php else: ?>
                <?php foreach ($remarks as $remark): ?>
                    <div style="border-left: 4px solid var(--<?= $remark['remark_type'] === 'warning' ? 'warning' : 'primary' ?>-color); padding: 1rem; margin-bottom: 1rem; background: #f9fafb; border-radius: 4px;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                            <strong><?= $remark['course_code'] ? safe($remark['course_code']) . ' - ' : '' ?>
                                <?= ucfirst($remark['remark_type']) ?>
                            </strong>
                            <small style="color: #666;"><?= date('M d, Y g:i A', strtotime($remark['created_at'])) ?></small>
                        </div>
                        <p style="margin: 0;"><?= nl2br(safe($remark['remark_text'])) ?></p>
                        <?php if ($remark['action_required']): ?>
                            <span class="badge badge-danger" style="margin-top: 0.5rem;">Action Required</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Advising Sessions History -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Advising Sessions History</h2>
            </div>
            <?php if (empty($sessions)): ?>
                <p>No advising sessions yet.</p>
            <?php else: ?>
                <?php foreach ($sessions as $session): ?>
                    <div style="border-left: 3px solid var(--primary-color); padding: 1rem; margin-bottom: 1rem; background: white; border-radius: 4px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <strong><?= date('F d, Y \a\t g:i A', strtotime($session['session_date'])) ?></strong>
                            <span class="badge badge-<?= $session['status'] === 'completed' ? 'success' : 'warning' ?>">
                                <?= ucfirst($session['status']) ?>
                            </span>
                        </div>
                        <?php if ($session['notes']): ?>
                            <p style="margin: 0.5rem 0;"><strong>Student Notes:</strong> <?= nl2br(safe($session['notes'])) ?></p>
                        <?php endif; ?>
                        <?php if ($session['feedback']): ?>
                            <p style="margin: 0.5rem 0;"><strong>Your Feedback:</strong> <?= nl2br(safe($session['feedback'])) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Remark Modal -->
    <div id="remarkModal" class="modal">
        <div class="modal-content">
            <h2>Add Remark for <?= safe($student['first_name']) ?></h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_remark">
                
                <div class="form-group">
                    <label class="form-label">Course (Optional)</label>
                    <select name="course_id" class="form-control">
                        <option value="">General Remark (Not course-specific)</option>
                        <?php foreach ($enrolledCourses as $course): ?>
                            <option value="<?= $course['course_id'] ?>">
                                <?= safe($course['course_code']) ?> - <?= safe($course['course_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Remark Type *</label>
                    <select name="remark_type" class="form-control" required>
                        <option value="encouragement">Encouragement</option>
                        <option value="improvement">Needs Improvement</option>
                        <option value="warning">Warning</option>
                        <option value="concern">Concern</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Remark/Notes *</label>
                    <textarea name="remark_text" class="form-control" rows="4" required
                              placeholder="Enter your observations or recommendations..."></textarea>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="action_required">
                        <span>Requires student action/response</span>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-success">Save Remark</button>
                <button type="button" onclick="closeRemarkModal()" class="btn btn-secondary">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function openRemarkModal() {
            document.getElementById('remarkModal').style.display = 'block';
        }
        
        function closeRemarkModal() {
            document.getElementById('remarkModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                closeRemarkModal();
            }
        }

        function loadCourseGrades(courseId, studentId) {
            const container = document.getElementById('course-grades-container');
            container.innerHTML = '<div style="text-align: center; padding: 2rem;"><p>Loading grade records...</p></div>';
            container.style.display = 'block';
            
            // Scroll to the container
            container.scrollIntoView({ behavior: 'smooth', block: 'start' });
            
            fetch(`getCourseDetails.php?course_id=${courseId}&student_id=${studentId}`)
                .then(response => response.text())
                .then(html => {
                    container.innerHTML = html;
                })
                .catch(error => {
                    container.innerHTML = '<div class="card"><p style="color: red;">Failed to load grade records.</p></div>';
                    console.error('Error loading grades:', error);
                });
        }

        function addGradeComponent(courseId, studentId) {
            window.location.href = `addGradeComponent.php?course_id=${courseId}&student_id=${studentId}`;
        }

        function uploadSyllabus(courseId, studentId) {
            window.location.href = `uploadSyllabus.php?course_id=${courseId}&student_id=${studentId}`;
        }

        document.addEventListener("click", function (e) {
            const editBtn = e.target.closest(".edit-btn");
            if (editBtn) {
                const id = editBtn.dataset.id;
                const studentId = editBtn.dataset.student;
                if (id && studentId) {
                    window.location.href = `editGradeComp.php?id=${id}&student_id=${studentId}`;
                }
                return;
            }

            const deleteBtn = e.target.closest(".delete-btn");
            if (deleteBtn) {
                const id = deleteBtn.dataset.id;
                const studentId = deleteBtn.dataset.student;
                if (!id || !studentId) return;
                if (!confirm("Delete this grade component?")) return;
                
                fetch("deleteGradeComp.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ id, student_id: studentId })
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(res.message || "Delete failed");
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("Delete error");
                });
            }
        });
    </script>
</body>
</html>