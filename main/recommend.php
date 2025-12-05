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

    <style>
        .search-panel {
            width: 350px;
            float: right;
            margin: 20px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        /* Hide the "Enhanced by Google" branding */
        .gsc-branding {
            opacity: 0 !important;
            height: 0 !important;
            overflow: hidden !important;
        }
        .recommendation-card {
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 1rem;
            background: #fff;
        }
        .risk-low { color: #dc2626; font-weight: bold; }
        .risk-at_risk { color: #d97706; font-weight: bold; }
        .risk-good { color: #16a34a; font-weight: bold; }
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
        <div class="search-panel">
            <h3 style="margin-bottom: 1rem; color:#1e293b;">Search Anything</h3>
            <div class="gcse-search"></div>
        </div>

        <h1 style="margin: 2rem 0;">Course Recommendations</h1>
        
        <?php if (empty($recommendations)): ?>
            <div class="alert alert-info">
                No course recommendations available at this time. Please check back later or contact your advisor.
            </div>
        <?php else: ?>
            <?php foreach ($recommendations as $rec): ?>
                <div class="recommendation-card card">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <h2 style="margin-bottom: 0.5rem;">
                                <?= htmlspecialchars($rec['course']['course_code']) ?> - 
                                <?= htmlspecialchars($rec['course']['course_name']) ?>
                            </h2>
                            <p><strong>Credits:</strong> <?= $rec['course']['credits'] ?> | 
                               <strong>Level:</strong> <?= ucfirst($rec['course']['level']) ?> | 
                               <strong>Department:</strong> <?= htmlspecialchars($rec['course']['department']) ?>
                            </p>
                            <p style="color: #666;"><?= htmlspecialchars($rec['course']['description']) ?></p>
                            <p style="background: #f0fdf4; padding: 0.5rem; border-radius: 4px; font-size: 0.9rem;">
                                <strong>Why recommended:</strong> <?= htmlspecialchars($rec['reason']) ?>
                            </p>
                            
                            <?php 
                            $prerequisites = json_decode($rec['course']['prerequisites'], true);
                            if (!empty($prerequisites)): 
                            ?>
                                <p style="font-size: 0.9rem;">
                                    <strong>Prerequisites:</strong> <?= implode(', ', $prerequisites) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="recommendation-score" style="margin-left: 1rem;">
                            <?= $rec['score'] ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>