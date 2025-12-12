<!-- 
Recommendation from AI/ from Professor like: 
-Based on curriculum map
-Remarks
-Insights
-Recent Advising Sessions -->

<?php
require_once __DIR__ . '/../../includes/init.php';

$userId = $_SESSION['user_id']; // or whatever key you use


if(isset($_GET['pageSched']))
{
    $pageSched = $_GET['pageSched'];
} else 
{
    $pageSched = 1;
}

$numPage = 04;
$defaultPage = ($pageSched-1)*04;
// echo $defaultPage;


$result = $db->fetchAll(
    "SELECT * FROM advising_sessions 
     WHERE student_id = ?
     ORDER BY session_date DESC
     LIMIT ?, ?",
    [$userId, $defaultPage, $numPage]
);




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

$filter = $_GET['filter'] ?? 'ongoing';

// Fetch student's advising sessions
$sessions = $db->fetchAll(
    "SELECT ads.*, u.first_name, u.last_name, pp.department 
     FROM advising_sessions ads
     JOIN users u ON ads.professor_id = u.id
     LEFT JOIN professor_profiles pp ON u.id = pp.user_id
     WHERE ads.student_id = ?
     ORDER BY ads.session_date DESC",
    [$userId]
);

// Filter sessions based on $filter
$now = date('Y-m-d H:i:s');
$filteredSessions = array_filter($sessions, function($s) use ($filter, $now) {
    switch ($filter) {
        case 'ongoing': // ongoing = today and scheduled
            return $s['status'] === 'scheduled' && date('Y-m-d', strtotime($s['session_date'])) === date('Y-m-d');
        case 'upcoming':
            return $s['status'] === 'scheduled' && $s['session_date'] > $now;
        case 'previous':
            return $s['status'] === 'completed';
        case 'cancelled':
            return $s['status'] === 'cancelled';
        default:
            return true;
    }
});


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Advising Sessions - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
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
                <li><a href="advisingSessions.php" class="nav-link">Advising Sessions</a></li>
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

         <!-- Pleae change this. It needs to display Ongoing, Upcoming, Previous, Cancelled  -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">My Advising Sessions</h2>
            </div>
            
            <?php
            $filter = $_GET['filter'] ?? 'ongoing';
            ?>

            <div class="btn-group" style="margin: 10px 0;">
                <a href="?filter=ongoing" class="btn btn-outline-primary <?= $filter=='ongoing' ? 'active' : '' ?>">Ongoing</a>
                <!-- Need to fix the problem as it shows both the called session, where those should be in cancelled page only -->
                <a href="?filter=upcoming" class="btn btn-outline-primary <?= $filter=='upcoming' ? 'active' : '' ?>">Upcoming</a>
                <a href="?filter=previous" class="btn btn-outline-primary <?= $filter=='previous' ? 'active' : '' ?>">Previous</a>
                <a href="?filter=cancelled" class="btn btn-outline-primary <?= $filter=='cancelled' ? 'active' : '' ?>">Cancelled</a>
            </div>


            <?php if (empty($sessions)): ?>
                <p>No advising sessions yet. Book your first session above!</p>
            <?php 

            else: 
                $sessions = $db->fetchAll(
                    "SELECT ads.*, u.first_name, u.last_name, pp.department
                    FROM advising_sessions ads
                    JOIN users u ON ads.professor_id = u.id
                    LEFT JOIN professor_profiles pp ON u.id = pp.user_id
                    WHERE ads.student_id = ?
                    ORDER BY ads.session_date ASC",
                    [$userId]
                );

                $total = $db->fetchAll(
                    "SELECT COUNT(*) AS total 
                    FROM advising_sessions 
                    WHERE student_id = ?",
                    [$userId]
                );

                $total_record = $total ? ($total[0]['total'] ?? 0) : 0;
                echo $total_record;
            ?>
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
                                <?php if ($session['notes'] ?? null): ?>
                                    <p><strong>Notes:</strong> <?= htmlspecialchars($session['notes']) ?></p>
                                <?php endif; ?>
                                <?php if ($session['feedback'] ?? null): ?>
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