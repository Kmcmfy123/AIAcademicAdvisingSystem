<?php
require_once __DIR__ . '/../../includes/init.php';
$auth->requireRole('professor');

$userId = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle session actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $sessionId = (int)$_POST['session_id'];
        
        if ($_POST['action'] === 'complete') {
            $feedback = sanitize($_POST['feedback']);
            $performanceRating = (int)$_POST['performance_rating'];
            $riskLevel = sanitize($_POST['risk_level']);
            
            $result = $db->execute(
                "UPDATE advising_sessions 
                 SET status = 'completed', feedback = ?, performance_rating = ?, risk_level = ? 
                 WHERE id = ? AND professor_id = ?",
                [$feedback, $performanceRating, $riskLevel, $sessionId, $userId]
            );
            
            if ($result) {
                $message = 'Session completed and feedback saved!';
            } else {
                $error = 'Failed to save feedback.';
            }
        } elseif ($_POST['action'] === 'reschedule') {
            $newDate = sanitize($_POST['new_date']);
            $result = $db->execute(
                "UPDATE advising_sessions SET session_date = ? WHERE id = ? AND professor_id = ?",
                [$newDate, $sessionId, $userId]
            );
            
            if ($result) {
                $message = 'Session rescheduled successfully.';
            } else {
                $error = 'Failed to reschedule session.';
            }
        }
    }
}

// Get professor's advising sessions
$sessions = $db->fetchAll(
    "SELECT ads.*, u.first_name, u.last_name, u.email, sp.major, sp.year_level, sp.gpa 
     FROM advising_sessions ads 
     JOIN users u ON ads.student_id = u.id 
     LEFT JOIN student_profiles sp ON u.id = sp.user_id 
     WHERE ads.professor_id = ? 
     ORDER BY 
        CASE 
            WHEN ads.status = 'scheduled' THEN 1 
            WHEN ads.status = 'completed' THEN 2 
            ELSE 3 
        END,
        ads.session_date DESC",
    [$userId]
);

// Get session statistics
$stats = $db->fetchOne(
    "SELECT 
        COUNT(*) as total_sessions,
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
     FROM advising_sessions 
     WHERE professor_id = ?",
    [$userId]
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Advising Sessions - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
    <style>
        .session-card {
            border-left: 4px solid var(--primary-color);
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: white;
            border-radius: var(--border-radius);
        }
        .session-card.completed { border-left-color: var(--success-color); }
        .session-card.cancelled { border-left-color: var(--danger-color); }
        .session-card.scheduled { border-left-color: var(--warning-color); }
        .student-info {
            background: var(--light-color);
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
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
        <h1 style="margin: 2rem 0 1rem;">Advising Sessions Management</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_sessions'] ?? 0 ?></div>
                <div class="stat-label">Total Sessions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['scheduled'] ?? 0 ?></div>
                <div class="stat-label">Scheduled</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['completed'] ?? 0 ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['cancelled'] ?? 0 ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
        </div>

        <!-- Sessions List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">All Advising Sessions</h2>
            </div>
            
            <?php if (empty($sessions)): ?>
                <p>No advising sessions scheduled yet.</p>
            <?php else: ?>
                <?php foreach ($sessions as $session): ?>
                    <div class="session-card <?= strtolower($session['status']) ?>">
                        <div class="student-info">
                            <h3 style="margin-bottom: 0.5rem;">
                                <?= htmlspecialchars($session['first_name'] . ' ' . $session['last_name']) ?>
                            </h3>
                            <p style="margin-bottom: 0.3rem;">
                                <strong>Email:</strong> <?= htmlspecialchars($session['email']) ?>
                            </p>
                            <p style="margin-bottom: 0.3rem;">
                                <strong>Major:</strong> <?= htmlspecialchars($session['major'] ?? 'N/A') ?> | 
                                <strong>Year:</strong> <?= htmlspecialchars($session['year_level'] ?? 'N/A') ?> | 
                                <strong>GPA:</strong> <?= formatGPA($session['gpa']) ?>
                            </p>
                        </div>

                        <p><strong>Session Date:</strong> <?= date('F d, Y \a\t g:i A', strtotime($session['session_date'])) ?></p>
                        <p><strong>Status:</strong> 
                            <span class="badge badge-<?= $session['status'] === 'completed' ? 'success' : ($session['status'] === 'cancelled' ? 'danger' : 'warning') ?>">
                                <?= ucfirst($session['status']) ?>
                            </span>
                        </p>

                        <?php if ($session['notes']): ?>
                            <div style="background: #fef3c7; padding: 0.75rem; border-radius: 4px; margin: 0.5rem 0;">
                                <strong>Student Notes:</strong><br>
                                <?= nl2br(htmlspecialchars($session['notes'])) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($session['feedback']): ?>
                            <div style="background: #d1fae5; padding: 0.75rem; border-radius: 4px; margin: 0.5rem 0;">
                                <strong>Your Feedback:</strong><br>
                                <?= nl2br(htmlspecialchars($session['feedback'])) ?>
                                <br><strong>Performance Rating:</strong> <?= $session['performance_rating'] ?>/10
                                <br><strong>Risk Level:</strong> <span class="badge badge-<?= $session['risk_level'] === 'high' ? 'danger' : ($session['risk_level'] === 'medium' ? 'warning' : 'success') ?>">
                                    <?= ucfirst($session['risk_level']) ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top: 1rem;">
                            <?php if ($session['status'] === 'scheduled'): ?>
                                <button onclick="openCompleteModal(<?= $session['id'] ?>)" class="btn btn-success">
                                    Complete Session
                                </button>
                                <button onclick="openRescheduleModal(<?= $session['id'] ?>, '<?= $session['session_date'] ?>')" 
                                        class="btn btn-secondary">
                                    Reschedule
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Complete Session Modal -->
    <div id="completeModal" class="modal">
        <div class="modal-content">
            <h2>Complete Advising Session</h2>
            <form method="POST">
                <?= CSRF::tokenField() ?>
                <input type="hidden" name="action" value="complete">
                <input type="hidden" name="session_id" id="complete_session_id">
                
                <div class="form-group">
                    <label class="form-label">Feedback/Notes</label>
                    <textarea name="feedback" class="form-control" rows="4" required
                              placeholder="Enter feedback about the session, recommendations, and action items..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Performance Rating (1-10)</label>
                    <input type="number" name="performance_rating" class="form-control" 
                           min="1" max="10" value="7" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Risk Level</label>
                    <select name="risk_level" class="form-control" required>
                        <option value="low">Low - On track</option>
                        <option value="medium" selected>Medium - Needs monitoring</option>
                        <option value="high">High - Requires intervention</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-success">Save & Complete</button>
                <button type="button" onclick="closeModal('completeModal')" class="btn btn-secondary">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div id="rescheduleModal" class="modal">
        <div class="modal-content">
            <h2>Reschedule Session</h2>
            <form method="POST">
                <?= CSRF::tokenField() ?>
                <input type="hidden" name="action" value="reschedule">
                <input type="hidden" name="session_id" id="reschedule_session_id">
                
                <div class="form-group">
                    <label class="form-label">New Date & Time</label>
                    <input type="datetime-local" name="new_date" id="new_date" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Reschedule</button>
                <button type="button" onclick="closeModal('rescheduleModal')" class="btn btn-secondary">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function openCompleteModal(sessionId) {
            document.getElementById('complete_session_id').value = sessionId;
            document.getElementById('completeModal').style.display = 'block';
        }

        function openRescheduleModal(sessionId, currentDate) {
            document.getElementById('reschedule_session_id').value = sessionId;
            document.getElementById('rescheduleModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>