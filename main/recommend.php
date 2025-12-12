<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/init.php';
$auth->requireRole('student');

$userId = $_SESSION['user_id'] ?? null;

// DEBUG: Check if user ID is set
if (!$userId) {
    echo "No user ID found in session.";
    exit;
}

$recommendations = getRecommendedCourses($userId);

// DEBUG: dump recommendations to verify data
// echo '<pre>';
// var_dump($recommendations);
// echo '</pre>';
// exit;  // Stops page loading so you can see debug info

// If you want to skip debug, comment out above and uncomment below for testing UI with sample data


if (empty($recommendations)) {
    $recommendations = [
        [
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
            'score' => 95,
        ],
        [
            'course' => [
                'course_code' => 'CS201',
                'course_name' => 'Data Structures',
                'credits' => 4,
                'level' => 'sophomore',
                'department' => 'Computer Science',
                'description' => 'Learn about efficient data structures',
                'prerequisites' => json_encode(['CS101']),
            ],
            'reason' => 'Recommended based on your completed courses',
            'score' => 88,
        ],
    ];
}


if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode($recommendations);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Course Recommendations - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css" />

    <style>
        /* Improved search panel to match your design */
        .search-panel {
            margin-top: 0;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: var(--light-color);
            border: 1px solid #e2e8f0;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            border-top: 1px solid #e2e8f0;
            padding-top: 1rem;
        }

        /* Reduce space below alert so search panel moves closer */
        .alert {
            margin-bottom: 0.5rem;
        }

        .recommendation-details p {
            margin-bottom: 0.6rem;
            color: var(--secondary-color);
        }

        .reason-box {
            background: #d1fae5;
            padding: 0.75rem;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            margin-top: 0.5rem;
            color: #065f46;
            border-left: 4px solid var(--success-color);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="#" class="navbar-brand"><?= APP_NAME ?></a>
            <ul class="navbar-nav">
                <li><a href="student/dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">

    <!-- Upd: Moved here -->
    <div class="card">
        <div class="card-header"><h2 class="card-title">Advising Session History</h2></div>

        <?php if (empty($advisingSessions)): ?>
            <p>No completed advising sessions yet.</p>
        <?php else: ?>
            <?php foreach ($advisingSessions as $session): ?>
                <div style="border-left: 3px solid var(--primary-color); padding-left: 1rem; margin-bottom: 1rem;">
                    <strong>Date:</strong> <?= date('F d, Y', strtotime($session['session_date'])) ?><br>
                    <strong>Advisor:</strong> Prof. 
                    <?= safe($session['first_name'] . ' ' . $session['last_name']) ?> 
                    (<?= safe($session['department']) ?>)
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>       

        <h1 class="card-title" style="margin-bottom: 1.5rem;">Course Recommendations</h1>

        <?php if (empty($recommendations)): ?>
            <div class="alert alert-info">
                No course recommendations available at this time. Please check back later or contact your advisor.
            </div>

        </div>
        <?php else: ?>
            <?php foreach ($recommendations as $rec): ?>
                <div class="recommendation-card card">
                    <div style="display: flex; justify-content: space-between; align-items: start; gap: 1rem;">
                        <div class="recommendation-details" style="flex:1;">
                            <h2 class="card-title" style="margin-bottom: 0.3rem;">
                                <?= htmlspecialchars($rec['course']['course_code']) ?>
                                <?= htmlspecialchars($rec['course']['course_name']) ?>
                            </h2>

                            <p>
                                <span class="badge badge-primary"><?= ucfirst($rec['course']['level']) ?></span>
                                <span class="badge badge-success"><?= $rec['course']['credits'] ?> credits</span>
                                <span class="badge badge-warning"><?= htmlspecialchars($rec['course']['department']) ?></span>
                            </p>

                            <p><?= htmlspecialchars($rec['course']['description']) ?></p>

                            <div class="reason-box">
                                <strong>Why recommended:</strong><br>
                                <?= htmlspecialchars($rec['reason']) ?>
                            </div>

                            <?php 
                            $prerequisites = json_decode($rec['course']['prerequisites'], true);
                            if (!empty($prerequisites)): ?>
                                <p style="margin-top: 0.5rem;">
                                    <strong>Prerequisites:</strong> <?= implode(', ', $prerequisites) ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Scoring Badge -->
                        <div class="recommendation-score">
                            <?= $rec['score'] ?>
                        </div>
                    </div>
                </div>

                <?php endforeach; ?>

                <!-- Link for search engine: https://programmablesearchengine.google.com/controlpanel/all -->
                <div class="search-panel">
                    <script async src="https://cse.google.com/cse.js?cx=10afcca3eb694482b"></script>
                <div class="gcse-search"></div>

                
        <?php endif; ?>
    </div>
</body>
</html>
