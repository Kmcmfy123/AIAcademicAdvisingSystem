<!-- Professor can view sections with list students -->
<?php
require_once __DIR__ . '/../../includes/init.php';
$auth->requireRole('professor');

$userId = $_SESSION['user_id'];
$message = '';

// Handle adding remarks/notes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        if ($_POST['action'] === 'add_note') {
            $studentId = (int)$_POST['student_id'];
            $note = sanitize($_POST['note']);
            $riskLevel = sanitize($_POST['risk_level']);
            
            $result = $db->execute(
                "INSERT INTO student_notes (professor_id, student_id, note, risk_level) VALUES (?, ?, ?, ?)",
                [$userId, $studentId, $note, $riskLevel]
            );
            
            if ($result) {
                $message = 'Note added successfully!';
            }
        }
    }
}

// Get all students (or students in professor's courses)
$students = $db->fetchAll(
    "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email,
            sp.student_id, sp.major, sp.year_level, sp.gpa, sp.credits_completed,
            (SELECT COUNT(*) FROM course_enrollments ce 
             WHERE ce.student_id = u.id AND ce.status = 'completed') as completed_courses,
            (SELECT COUNT(*) FROM course_enrollments ce 
             WHERE ce.student_id = u.id AND ce.status = 'enrolled') as ongoing_courses,
            (SELECT COUNT(*) FROM course_grades cg 
             JOIN course_enrollments ce ON cg.enrollment_id = ce.id 
             WHERE ce.student_id = u.id AND cg.final_grade < 75) as failed_subjects
     FROM users u
     JOIN student_profiles sp ON u.id = sp.user_id
     WHERE u.role = 'student'
     ORDER BY u.last_name ASC"
);

// Get risk level summary
$riskSummary = $db->fetchOne(
    "SELECT 
        SUM(CASE WHEN gpa < 2.0 THEN 1 ELSE 0 END) as high_risk,
        SUM(CASE WHEN gpa >= 2.0 AND gpa < 2.5 THEN 1 ELSE 0 END) as medium_risk,
        SUM(CASE WHEN gpa >= 2.5 THEN 1 ELSE 0 END) as low_risk
     FROM student_profiles"
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Management - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
    <style>
        .student-card {
            border: 1px solid #e5e7eb;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: white;
            transition: transform 0.2s;
        }
        .student-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .student-card.high-risk { border-left: 4px solid var(--danger-color); }
        .student-card.medium-risk { border-left: 4px solid var(--warning-color); }
        .student-card.low-risk { border-left: 4px solid var(--success-color); }
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
            border-radius: var(--border-radius);
        }
        .filter-group {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="#" class="navbar-brand"><?= APP_NAME ?></a>
            <ul class="navbar-nav">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="advising_sessions.php" class="nav-link">Advising Sessions</a></li>
                <li><a href="students.php" class="nav-link">Students</a></li>
                <li><a href="../logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <h1 style="margin: 2rem 0 1rem;">Student Management</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>

        <!-- Risk Summary -->
        <div class="stats-grid">
            <div class="stat-card" style="border-left-color: var(--danger-color);">
                <div class="stat-value" style="color: var(--danger-color);">
                    <?= $riskSummary['high_risk'] ?? 0 ?>
                </div>
                <div class="stat-label">High Risk Students</div>
            </div>
            <div class="stat-card" style="border-left-color: var(--warning-color);">
                <div class="stat-value" style="color: var(--warning-color);">
                    <?= $riskSummary['medium_risk'] ?? 0 ?>
                </div>
                <div class="stat-label">Medium Risk Students</div>
            </div>
            <div class="stat-card" style="border-left-color: var(--success-color);">
                <div class="stat-value" style="color: var(--success-color);">
                    <?= $riskSummary['low_risk'] ?? 0 ?>
                </div>
                <div class="stat-label">Low Risk Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($students) ?></div>
                <div class="stat-label">Total Students</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card">
            <div class="filter-group">
                <input type="text" id="searchInput" class="form-control" placeholder="Search by name or email..." 
                       style="flex: 1; min-width: 250px;">
                <select id="riskFilter" class="form-control" style="max-width: 200px;">
                    <option value="">All Risk Levels</option>
                    <option value="high">High Risk</option>
                    <option value="medium">Medium Risk</option>
                    <option value="low">Low Risk</option>
                </select>
                <select id="yearFilter" class="form-control" style="max-width: 200px;">
                    <option value="">All Year Levels</option>
                    <option value="freshman">Freshman</option>
                    <option value="sophomore">Sophomore</option>
                    <option value="junior">Junior</option>
                    <option value="senior">Senior</option>
                </select>
            </div>
        </div>

        <!-- Students List -->
        <div id="studentsList">
            <?php foreach ($students as $student): 
                $gpa = $student['gpa'] ?? 0;
                $riskClass = $gpa < 2.0 ? 'high-risk' : ($gpa < 2.5 ? 'medium-risk' : 'low-risk');
                $riskLabel = $gpa < 2.0 ? 'High' : ($gpa < 2.5 ? 'Medium' : 'Low');
            ?>
                <div class="student-card <?= $riskClass ?>" 
                     data-name="<?= strtolower($student['first_name'] . ' ' . $student['last_name']) ?>"
                     data-email="<?= strtolower($student['email']) ?>"
                     data-risk="<?= strtolower($riskLabel) ?>"
                     data-year="<?= strtolower($student['year_level']) ?>">
                    
                    <div style="display: flex; justify-content: space-between; align-items: start; gap: 1rem;">
                        <div style="flex: 1;">
                            <h3 style="margin-bottom: 0.5rem;">
                                <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                            </h3>
                            
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.5rem; margin-bottom: 0.5rem;">
                                <p><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></p>
                                <p><strong>Student ID:</strong> <?= htmlspecialchars($student['student_id'] ?? 'N/A') ?></p>
                                <p><strong>Major:</strong> <?= htmlspecialchars($student['major'] ?? 'N/A') ?></p>
                                <p><strong>Year:</strong> <?= htmlspecialchars(ucfirst($student['year_level'] ?? 'N/A')) ?></p>
                            </div>

                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.5rem;">
                                <div>
                                    <span class="badge badge-primary">GPA: <?= formatGPA($student['gpa']) ?></span>
                                </div>
                                <div>
                                    <span class="badge badge-success">Completed: <?= $student['completed_courses'] ?></span>
                                </div>
                                <div>
                                    <span class="badge badge-warning">Ongoing: <?= $student['ongoing_courses'] ?></span>
                                </div>
                                <div>
                                    <span class="badge badge-<?= $student['failed_subjects'] > 0 ? 'danger' : 'success' ?>">
                                        Failed: <?= $student['failed_subjects'] ?>
                                    </span>
                                </div>
                                <div>
                                    <span class="badge badge-<?= $riskClass === 'high-risk' ? 'danger' : ($riskClass === 'medium-risk' ? 'warning' : 'success') ?>">
                                        Risk: <?= $riskLabel ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <button onclick="viewStudent(<?= $student['id'] ?>)" class="btn btn-primary">
                                View Profile
                            </button>
                            <button onclick="openNoteModal(<?= $student['id'] ?>, '<?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>')" 
                                    class="btn btn-secondary">
                                Add Note
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Add Note Modal -->
    <div id="noteModal" class="modal">
        <div class="modal-content">
            <h2>Add Note for <span id="studentName"></span></h2>
            <form method="POST">
                <?= CSRF::tokenField() ?>
                <input type="hidden" name="action" value="add_note">
                <input type="hidden" name="student_id" id="note_student_id">
                
                <div class="form-group">
                    <label class="form-label">Note/Remarks</label>
                    <textarea name="note" class="form-control" rows="4" required
                              placeholder="Enter observations, recommendations, or concerns..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Update Risk Level</label>
                    <select name="risk_level" class="form-control" required>
                        <option value="low">Low - On track</option>
                        <option value="medium">Medium - Needs monitoring</option>
                        <option value="high">High - Requires intervention</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-success">Save Note</button>
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function viewStudent(studentId) {
            window.location.href = `student_profile.php?id=${studentId}`;
        }

        function openNoteModal(studentId, studentName) {
            document.getElementById('note_student_id').value = studentId;
            document.getElementById('studentName').textContent = studentName;
            document.getElementById('noteModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('noteModal').style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                closeModal();
            }
        }

        // Filter functionality
        document.getElementById('searchInput').addEventListener('input', filterStudents);
        document.getElementById('riskFilter').addEventListener('change', filterStudents);
        document.getElementById('yearFilter').addEventListener('change', filterStudents);

        function filterStudents() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const riskFilter = document.getElementById('riskFilter').value.toLowerCase();
            const yearFilter = document.getElementById('yearFilter').value.toLowerCase();
            
            const cards = document.querySelectorAll('.student-card');
            
            cards.forEach(card => {
                const name = card.dataset.name;
                const email = card.dataset.email;
                const risk = card.dataset.risk;
                const year = card.dataset.year;
                
                const matchesSearch = !searchTerm || name.includes(searchTerm) || email.includes(searchTerm);
                const matchesRisk = !riskFilter || risk === riskFilter;
                const matchesYear = !yearFilter || year === yearFilter;
                
                card.style.display = (matchesSearch && matchesRisk && matchesYear) ? 'block' : 'none';
            });
        }
    </script>
</body>
</html>