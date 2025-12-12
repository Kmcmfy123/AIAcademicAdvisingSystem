<?php
require_once __DIR__ . '/../includes/init.php';
$auth->requireRole('student');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo "No user ID found in session.";
    exit;
}

// Get course recommendations
$recommendations = getRecommendedCourses($userId);
if (empty($recommendations)) {
    $recommendations = [[
        'course' => [
            'course_code' => 'CS101',
            'course_name' => 'Intro to CS',
            'credits' => 3,
            'level' => 'freshman',
            'department' => 'Computer Science',
            'description' => 'Basics of programming and computer science',
            'prerequisites' => json_encode([]),
        ],
        'reason' => 'Core requirement for your major',
        'score' => 95
    ]];
}

$maxDisplay = 3;
$displayedRecommendations = array_slice($recommendations, 0, $maxDisplay);

// Get advising sessions
$advisingSessions = $db->fetchAll(
    "SELECT ads.*, u.first_name, u.last_name, pp.department 
     FROM advising_sessions ads 
     JOIN users u ON ads.professor_id=u.id 
     LEFT JOIN professor_profiles pp ON u.id=pp.user_id 
     WHERE ads.student_id=? AND ads.status='completed' 
     ORDER BY ads.session_date DESC",
    [$userId]
) ?: [];
$displayedSessions = array_slice($advisingSessions, 0, $maxDisplay);

// Suggested resources
$resources = getSuggestedResources($displayedRecommendations);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Course Recommendations - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
    <style>
        .two-column-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 300px;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .two-column-grid .card {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .card-content {
            flex: 1;
        }

        .reason-box {
            background: #d1fae5;
            padding: .75rem;
            border-radius: var(--border-radius);
            font-size: .9rem;
            margin-top: .5rem;
            color: #065f46;
            border-left: 4px solid var(--success-color);
        }

        .resource-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .resource-item img {
            width: 80px;
            height: 45px;
            object-fit: cover;
            margin-right: 0.5rem;
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="container">
            <a href="#" class="navbar-brand"><?= APP_NAME ?></a>
            <ul class="navbar-nav">
                <li><a href="../main/student/dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="../main/logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <h1 class="card-title" style="margin:2rem 0 1.5rem;">Course Recommendations</h1>

        <div class="two-column-grid">
            <!-- Advising Session History -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Advising Session History</h2>
                </div>
                <div class="card-content">
                    <?php if (empty($displayedSessions)): ?>
                        <p>No completed advising sessions yet.</p>
                        <?php else: foreach ($displayedSessions as $s): ?>
                            <div style="border-left:3px solid var(--primary-color);padding-left:1rem;margin-bottom:1rem;">
                                <strong>Date:</strong> <?= date('F d, Y', strtotime($s['session_date'])) ?><br>
                                <strong>Advisor:</strong> Prof. <?= sanitize($s['first_name'] . ' ' . $s['last_name']) ?> (<?= sanitize($s['department']) ?>)
                            </div>
                    <?php endforeach;
                    endif; ?>
                </div>
            </div>

            <!-- Course Recommendations -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recommended Courses</h2>
                </div>
                <div class="card-content">
                    <?php foreach ($displayedRecommendations as $rec): ?>
                        <div style="border-bottom:1px solid #e2e8f0;padding-bottom:1rem;margin-bottom:1rem;">
                            <h3><?= sanitize($rec['course']['course_code'] . ' - ' . $rec['course']['course_name']) ?></h3>
                            <p><span class="badge badge-primary"><?= ucfirst($rec['course']['level']) ?></span>
                                <span class="badge badge-success"><?= $rec['course']['credits'] ?> credits</span>
                            </p>
                            <p style="font-size:.9rem;color:#666;"><?= sanitize($rec['course']['description']) ?></p>
                            <div class="reason-box"><strong>Why:</strong> <?= sanitize($rec['reason']) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <a href="viewMoreCourses.php" class="btn btn-primary">View More Courses</a>
                </div>
            </div>

            <!-- Suggested Learning Resources -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Suggested Learning Resources</h2>
                </div>
                <div class="card-content search-panel">
                    <?php if (empty($resources)): ?>
                        <p>No suggestions at this time. Keep up the good work!</p>
                        <?php else: foreach ($resources as $r): ?>
                            <div class="resource-item">
                                <?php if (isset($r['thumbnail'])): ?>
                                    <img src="<?= sanitize($r['thumbnail']) ?>" alt="Thumbnail">
                                <?php endif; ?>
                                <a href="<?= sanitize($r['url']) ?>" target="_blank"><strong><?= sanitize($r['title']) ?></strong> (<?= sanitize($r['source']) ?>)</a>
                            </div>
                    <?php endforeach;
                    endif; ?>
                </div>
            </div>

        </div>
    </div>
</body>

</html> 