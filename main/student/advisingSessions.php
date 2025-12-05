<?php
require_once __DIR__ . '/../../includes/init.php';
$auth->requireRole('student');

$userId = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle session booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        if ($_POST['action'] === 'book') {
            $professorId = (int)$_POST['professor_id'];
            $sessionDate = sanitize($_POST['session_date']);
            $notes = sanitize($_POST['notes']);
            
            $result = $db->execute(
                "INSERT INTO advising_sessions (student_id, professor_id, session_date, notes, status) 
                 VALUES (?, ?, ?, ?, 'scheduled')",
                [$userId, $professorId, $sessionDate, $notes]
            );
            
            if ($result) {
                $message = 'Advising session booked successfully!';
            } else {
                $error = 'Failed to book session. Please try again.';
            }
        } elseif ($_POST['action'] === 'cancel') {
            $sessionId = (int)$_POST['session_id'];
            $result = $db->execute(
                "UPDATE advising_sessions SET status = 'cancelled' WHERE id = ? AND student_id = ?",
                [$sessionId, $userId]
            );
            
            if ($result) {
                $message = 'Session cancelled successfully.';
            } else {
                $error = 'Failed to cancel session.';
            }
        }
    }
}

// Get available professors
$professors = $db->fetchAll(
    "SELECT u.id, u.first_name, u.last_name, u.email, pp.department, pp.specialization 
     FROM users u 
     JOIN professor_profiles pp ON u.id = pp.user_id 
     WHERE u.role = 'professor'"
);

// Get student's advising sessions
$sessions = $db->fetchAll(
    "SELECT ads.*, u.first_name, u.last_name, u.email, pp.department 
     FROM advising_sessions ads 
     JOIN users u ON ads.professor_id = u.id 
     LEFT JOIN professor_profiles pp ON u.id = pp.user_id 
     WHERE ads.student_id = ? 
     ORDER BY ads.session_date DESC",
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
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
            border-radius: var(--border-radius);
        }
        .session-card.completed { border-left-color: var(--success-color); }
        .session-card.cancelled { border-left-color: var(--danger-color); }
        .session-card.scheduled { border-left-color: var(--warning-color); }
        .professor-card {
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
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
                <li><a href="profile.php" class="nav-link">Profile</a></li>
                <li><a href="../logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <h1 style="margin: 2rem 0 1rem;">Advising Sessions</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <!-- Book New Session -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Book New Advising Session</h2>
            </div>
            <form method="POST">
                <?= CSRF::tokenField() ?>
                <input type="hidden" name="action" value="book">
                
                <div class="form-group">
                    <label class="form-label">Select Professor/Advisor</label>
                    <select name="professor_id" class="form-control" required>
                        <option value="">Choose a professor...</option>
                        <?php foreach ($professors as $prof): ?>
                            <option value="<?= $prof['id'] ?>">
                                <?= htmlspecialchars($prof['first_name'] . ' ' . $prof['last_name']) ?> 
                                - <?= htmlspecialchars($prof['department'] ?? 'N/A') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Preferred Date & Time</label>
                    <input type="datetime-local" name="session_date" class="form-control" required 
                           min="<?= date('Y-m-d\TH:i') ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes/Concerns (Optional)</label>
                    <textarea name="notes" class="form-control" rows="3" 
                              placeholder="Enter any specific topics or concerns you'd like to discuss..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Book Session</button>
            </form>
        </div>

        <!-- Upcoming Sessions -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">My Advising Sessions</h2>
            </div>
            
            <?php if (empty($sessions)): ?>
                <p>No advising sessions yet. Book your first session above!</p>
            <?php else: ?>
                <?php foreach ($sessions as $session): ?>
                    <div class="session-card <?= strtolower($session['status']) ?>">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div style="flex: 1;">
                                <h3 style="margin-bottom: 0.5rem;">
                                    Session with Prof. <?= htmlspecialchars($session['first_name'] . ' ' . $session['last_name']) ?>
                                </h3>
                                <p style="color: var(--secondary-color); margin-bottom: 0.5rem;">
                                    <strong>Department:</strong> <?= htmlspecialchars($session['department'] ?? 'N/A') ?>
                                </p>
                                <p style="margin-bottom: 0.5rem;">
                                    <strong>Date:</strong> <?= date('F d, Y \a\t g:i A', strtotime($session['session_date'])) ?>
                                </p>
                                <p style="margin-bottom: 0.5rem;">
                                    <strong>Status:</strong> 
                                    <span class="badge badge-<?= $session['status'] === 'completed' ? 'success' : ($session['status'] === 'cancelled' ? 'danger' : 'warning') ?>">
                                        <?= ucfirst($session['status']) ?>
                                    </span>
                                </p>
                                <?php if ($session['notes']): ?>
                                    <p><strong>Notes:</strong> <?= htmlspecialchars($session['notes']) ?></p>
                                <?php endif; ?>
                                <?php if ($session['feedback']): ?>
                                    <div style="background: #f0fdf4; padding: 0.75rem; border-radius: 4px; margin-top: 0.5rem;">
                                        <strong>Professor Feedback:</strong><br>
                                        <?= nl2br(htmlspecialchars($session['feedback'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($session['status'] === 'scheduled'): ?>
                                <form method="POST" style="margin-left: 1rem;" 
                                      onsubmit="return confirm('Are you sure you want to cancel this session?')">
                                    <?= CSRF::tokenField() ?>
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                    <button type="submit" class="btn btn-danger">Cancel</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>