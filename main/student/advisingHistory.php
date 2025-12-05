<?php
require_once __DIR__ . '/../../includes/init.php';
$auth->requireRole('student');

$userId = $_SESSION['user_id'];

// Get all advising sessions with full details
$sessions = $db->fetchAll(
    "SELECT ads.*, 
            u.first_name, u.last_name, u.email,
            pp.department, pp.specialization, pp.office_location
     FROM advising_sessions ads
     JOIN users u ON ads.professor_id = u.id
     LEFT JOIN professor_profiles pp ON u.id = pp.user_id
     WHERE ads.student_id = ?
     ORDER BY ads.session_date DESC",
    [$userId]
) ?: [];

// Get statistics
$stats = [
    'total' => count($sessions),
    'completed' => count(array_filter($sessions, fn($s) => $s['status'] === 'completed')),
    'scheduled' => count(array_filter($sessions, fn($s) => $s['status'] === 'scheduled')),
    'cancelled' => count(array_filter($sessions, fn($s) => $s['status'] === 'cancelled')),
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Advising History - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
    <style>
        .session-timeline {
            position: relative;
            padding-left: 2rem;
        }
        .session-timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 2rem;
            padding-left: 1rem;
        }
        .timeline-dot {
            position: absolute;
            left: -2.5rem;
            top: 0.5rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: white;
            border: 3px solid var(--primary-color);
        }
        .timeline-dot.completed { border-color: var(--success-color); }
        .timeline-dot.cancelled { border-color: var(--danger-color); }
        .timeline-dot.scheduled { border-color: var(--warning-color); }
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
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="academicProfile.php" class="nav-link">Academic Profile</a></li>
                <li><a href="advising_sessions.php" class="nav-link">Advising Sessions</a></li>
                <li><a href="advisingHistory.php" class="nav-link">History</a></li>
                <li><a href="accountsProfile.php" class="nav-link">Profile</a></li>
                <li><a href="../logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin: 2rem 0 1rem;">
            <h1>Advising Session History</h1>
            <button onclick="window.print()" class="btn btn-primary no-print">
                Download/Print History
            </button>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Sessions</div>
            </div>
            <div class="stat-card" style="border-left-color: var(--success-color);">
                <div class="stat-value" style="color: var(--success-color);"><?= $stats['completed'] ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card" style="border-left-color: var(--warning-color);">
                <div class="stat-value" style="color: var(--warning-color);"><?= $stats['scheduled'] ?></div>
                <div class="stat-label">Scheduled</div>
            </div>
            <div class="stat-card" style="border-left-color: var(--danger-color);">
                <div class="stat-value" style="color: var(--danger-color);"><?= $stats['cancelled'] ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card no-print">
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <input type="text" id="searchInput" class="form-control" placeholder="Search by advisor or notes..." 
                       style="flex: 1; min-width: 250px;">
                <select id="statusFilter" class="form-control" style="max-width: 200px;">
                    <option value="">All Statuses</option>
                    <option value="completed">Completed</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
        </div>

        <!-- Timeline -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Session Timeline</h2>
            </div>
            
            <?php if (empty($sessions)): ?>
                <p>No advising sessions in your history yet.</p>
                <a href="advising_sessions.php" class="btn btn-primary no-print">
                    Schedule Your First Session
                </a>
            <?php else: ?>
                <div class="session-timeline" id="sessionTimeline">
                    <?php foreach ($sessions as $session): ?>
                        <div class="timeline-item" 
                             data-advisor="<?= strtolower($session['first_name'] . ' ' . $session['last_name']) ?>"
                             data-notes="<?= strtolower($session['notes'] ?? '') ?>"
                             data-status="<?= $session['status'] ?>">
                            <div class="timeline-dot <?= $session['status'] ?>"></div>
                            
                            <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1.5rem;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                    <div>
                                        <h3 style="margin-bottom: 0.3rem;">
                                            <?= date('F d, Y', strtotime($session['session_date'])) ?>
                                        </h3>
                                        <p style="color: #666; margin: 0;">
                                            <?= date('g:i A', strtotime($session['session_date'])) ?>
                                        </p>
                                    </div>
                                    <span class="badge badge-<?= $session['status'] === 'completed' ? 'success' : ($session['status'] === 'cancelled' ? 'danger' : 'warning') ?>">
                                        <?= ucfirst($session['status']) ?>
                                    </span>
                                </div>

                                <div style="background: var(--light-color); padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                                    <strong>Advisor:</strong> Prof. <?= htmlspecialchars($session['first_name'] . ' ' . $session['last_name']) ?><br>
                                    <strong>Department:</strong> <?= htmlspecialchars($session['department'] ?? 'N/A') ?><br>
                                    <?php if ($session['specialization']): ?>
                                        <strong>Specialization:</strong> <?= htmlspecialchars($session['specialization']) ?><br>
                                    <?php endif; ?>
                                    <?php if ($session['office_location']): ?>
                                        <strong>Office:</strong> <?= htmlspecialchars($session['office_location']) ?><br>
                                    <?php endif; ?>
                                    <strong>Email:</strong> <?= htmlspecialchars($session['email']) ?>
                                </div>

                                <?php if ($session['notes']): ?>
                                    <div style="margin-bottom: 1rem;">
                                        <strong>Your Notes/Concerns:</strong>
                                        <p style="margin: 0.5rem 0; padding: 0.75rem; background: #fef3c7; border-left: 3px solid var(--warning-color); border-radius: 4px;">
                                            <?= nl2br(htmlspecialchars($session['notes'])) ?>
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <?php if ($session['recommendations']): ?>
                                    <div style="margin-bottom: 1rem;">
                                        <strong>📋 Advisor Recommendations:</strong>
                                        <p style="margin: 0.5rem 0; padding: 0.75rem; background: #d1fae5; border-left: 3px solid var(--success-color); border-radius: 4px;">
                                            <?= nl2br(htmlspecialchars($session['recommendations'])) ?>
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <?php if ($session['follow_up_required']): ?>
                                    <div style="background: #fee2e2; padding: 0.75rem; border-left: 3px solid var(--danger-color); border-radius: 4px;">
                                        <strong>⚠️ Follow-up Required</strong>
                                        <p style="margin: 0.3rem 0 0 0; font-size: 0.9rem;">
                                            Please schedule a follow-up session with your advisor.
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Filter functionality
        document.getElementById('searchInput')?.addEventListener('input', filterSessions);
        document.getElementById('statusFilter')?.addEventListener('change', filterSessions);

        function filterSessions() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            
            const items = document.querySelectorAll('.timeline-item');
            
            items.forEach(item => {
                const advisor = item.dataset.advisor || '';
                const notes = item.dataset.notes || '';
                const status = item.dataset.status || '';
                
                const matchesSearch = !searchTerm || advisor.includes(searchTerm) || notes.includes(searchTerm);
                const matchesStatus = !statusFilter || status === statusFilter;
                
                item.style.display = (matchesSearch && matchesStatus) ? 'block' : 'none';
            });
        }
    </script>
</body>
</html>