<?php
require_once __DIR__ . '/../includes/init.php';
$auth->requireRole('student');

$userId = $_SESSION['user_id'];
$recommendations = getRecommendedCourses($userId);

// If JSON format requested
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode($recommendations);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Recommendations - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
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
    
    <div class="container main-content">
        <!-- Search Panel -->
        <div class="search-panel">
            <h3>Search Anything</h3>
            <div class="gcse-search"></div>
        </div>

        <!-- Recommendations List -->
        <div class="recommendations-list">
            <h1>Course Recommendations</h1>
            
            <?php if (empty($recommendations)): ?>
                <div class="alert alert-info">
                    No course recommendations available at this time. Please check back later or contact your advisor.
                </div>
            <?php else: ?>
                <?php foreach ($recommendations as $rec): ?>
                    <?php
                        // Determine score class
                        $scoreClass = '';
                        if ($rec['score'] >= 85) $scoreClass = 'risk-good';
                        elseif ($rec['score'] >= 50) $scoreClass = 'risk-at_risk';
                        else $scoreClass = 'risk-low';

                        $prerequisites = json_decode($rec['course']['prerequisites'], true);
                    ?>
                    <div class="recommendation-card card">
                        <div class="recommendation-content">
                            <div class="recommendation-info">
                                <h2>
                                    <?= htmlspecialchars($rec['course']['course_code']) ?> - 
                                    <?= htmlspecialchars($rec['course']['course_name']) ?>
                                </h2>
                                <p><strong>Credits:</strong> <?= $rec['course']['credits'] ?> | 
                                   <strong>Level:</strong> <?= ucfirst($rec['course']['level']) ?> | 
                                   <strong>Department:</strong> <?= htmlspecialchars($rec['course']['department']) ?>
                                </p>
                                <p class="course-description"><?= htmlspecialchars($rec['course']['description']) ?></p>
                                <p class="recommend-reason">
                                    <strong>Why recommended:</strong> <?= htmlspecialchars($rec['reason']) ?>
                                </p>
                                <?php if (!empty($prerequisites)): ?>
                                    <p class="course-prerequisites">
                                        <strong>Prerequisites:</strong> <?= implode(', ', $prerequisites) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="recommendation-score <?= $scoreClass ?>">
                                <?= $rec['score'] ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Google CSE -->
    <script async src="https://cse.google.com/cse.js?cx=10afcca3eb694482b"></script>
</body>
</html>
