<?php
require_once __DIR__ . '/../includes/init.php';
$auth->requireRole('student');

$userId = $_SESSION['user_id'] ?? null;
$courseId = $_GET['course_id'] ?? null;

if (!$userId) {
    echo "No user ID found in session.";
    exit;
}

// Fetch selected course details if course_id provided
$selectedCourse = null;
if ($courseId) {
    $selectedCourse = $db->fetchOne("
        SELECT c.*, ce.semester, ce.status, cg.school_year
        FROM courses c
        JOIN course_enrollments ce ON c.id = ce.course_id
        LEFT JOIN course_grades cg ON ce.student_id = cg.student_id AND ce.course_id = cg.course_id
        WHERE c.id = ? AND ce.student_id = ?
    ", [$courseId, $userId]);
}

// Get AI insights for this course
$insights = $db->fetchAll("
    SELECT * FROM ai_insights
    WHERE student_id = ? " . ($courseId ? "AND course_id = ?" : "") . "
    ORDER BY generated_at DESC
    LIMIT 5
", $courseId ? [$userId, $courseId] : [$userId]);

// Get professor remarks for this course
$professorRemarks = $db->fetchAll("
    SELECT csr.*, u.first_name, u.last_name, c.course_code, c.course_name
    FROM course_specific_remarks csr
    JOIN users u ON csr.professor_id = u.id
    LEFT JOIN courses c ON csr.course_id = c.id
    WHERE csr.student_id = ? " . ($courseId ? "AND csr.course_id = ?" : "") . "
    ORDER BY csr.created_at DESC
    LIMIT 10
", $courseId ? [$userId, $courseId] : [$userId]);

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

$maxDisplay = $courseId ? 6 : 3; // Show more if course-specific
$displayedRecommendations = array_slice($recommendations, 0, $maxDisplay);

// Get advising sessions
$advisingSessions = $db->fetchAll(
    "SELECT ads.*, u.first_name, u.last_name, pp.department 
     FROM advising_sessions ads 
     JOIN users u ON ads.professor_id=u.id 
     LEFT JOIN professor_profiles pp ON u.id=pp.user_id 
     WHERE ads.student_id=? " . ($courseId ? "AND ads.recommendations LIKE ?" : "") . "
     ORDER BY ads.session_date DESC
     LIMIT 5",
    $courseId ? [$userId, '%"' . $courseId . '"%'] : [$userId]
) ?: [];

// Suggested learning resources
$resources = getSuggestedResources($displayedRecommendations);

function safe($value, $fallback = 'N/A') {
    return htmlspecialchars($value ?? $fallback);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $selectedCourse ? safe($selectedCourse['course_code']) . ' - ' : '' ?>Recommendations - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
    <style>
        .three-column-grid {
            display: grid;
            grid-template-columns: 2fr 2fr 1.5fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .full-width-section {
            grid-column: 1 / -1;
        }

        .two-column-section {
            grid-column: span 2;
        }

        .insight-card {
            border-left: 4px solid #3b82f6;
            padding: 1rem;
            background: #eff6ff;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .insight-card.risk {
            border-left-color: #ef4444;
            background: #fee2e2;
        }

        .insight-card.success {
            border-left-color: #10b981;
            background: #d1fae5;
        }

        .remark-card {
            border: 1px solid #e2e8f0;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            background: white;
        }

        .remark-card.warning {
            border-left: 4px solid #f59e0b;
            background: #fffbeb;
        }

        .remark-card.encouragement {
            border-left: 4px solid #10b981;
            background: #f0fdf4;
        }

        .resource-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            margin-bottom: 0.5rem;
            transition: background 0.2s;
        }

        .resource-item:hover {
            background: #f9fafb;
        }

        .resource-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            margin-right: 0.75rem;
            border-radius: 4px;
        }

        .resource-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .recommendation-card {
            border: 1px solid #e2e8f0;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }

        .recommendation-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        @media (max-width: 1200px) {
            .three-column-grid {
                grid-template-columns: 1fr 1fr;
            }
            .three-column-grid > div:last-child {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 768px) {
            .three-column-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="container">
            <a href="#" class="navbar-brand"><?= APP_NAME ?></a>
            <ul class="navbar-nav">
                <li><a href="student/academicProfile.php" class="nav-link">Academic Profile</a></li>
                <li><a href="student/dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container" style="max-width: 1400px;">
        <div style="margin: 2rem 0 1.5rem;">
            <?php if ($selectedCourse): ?>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1 class="card-title" style="margin: 0;">
                            <?= safe($selectedCourse['course_code']) ?>: Course Recommendations & Insights
                        </h1>
                        <p style="margin: 0.5rem 0 0 0; color: #666;">
                            <?= safe($selectedCourse['course_name']) ?> | 
                            <?= safe($selectedCourse['semester']) ?> <?= safe($selectedCourse['school_year']) ?>
                        </p>
                    </div>
                    <a href="student/academicProfile.php<?= $courseId ? '?course_id=' . $courseId : '' ?>" class="btn btn-secondary">
                        ← Back to Academic Profile
                    </a>
                </div>
            <?php else: ?>
                <h1 class="card-title">AIRecommendations & Insights</h1>
            <?php endif; ?>
        </div>

        <div class="three-column-grid">
            
            <!-- AI INSIGHTS & ALERTS -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Insights & Recommendations</h2>
                </div>
                <div class="card-content">
                    <?php if (empty($insights)): ?>
                        <p>No insights generated yet. Complete more courses to get personalized insights.</p>
                    <?php else: ?>
                        <?php foreach ($insights as $insight): ?>
                            <div class="insight-card <?= $insight['insight_type'] === 'risk_alert' ? 'risk' : ($insight['insight_type'] === 'study_recommendation' ? 'success' : '') ?>">
                                <strong style="text-transform: capitalize;">
                                    <?= str_replace('_', ' ', $insight['insight_type']) ?>
                                </strong>
                                <?php if ($insight['confidence_score']): ?>
                                    <span style="font-size: 0.85rem; color: #666;">
                                        (Confidence: <?= round($insight['confidence_score'] * 100) ?>%)
                                    </span>
                                <?php endif; ?>
                                <p style="margin: 0.5rem 0 0 0;">
                                    <?= safe($insight['insight_text']) ?>
                                </p>
                                <small style="color: #666;">
                                    <?= date('M d, Y', strtotime($insight['generated_at'])) ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PROFESSOR REMARKS & NOTES -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Professor Remarks & Advice</h2>
                </div>
                <div class="card-content">
                    <?php if (empty($professorRemarks)): ?>
                        <p>No remarks from professors yet.</p>
                    <?php else: ?>
                        <?php foreach ($professorRemarks as $remark): ?>
                            <div class="remark-card <?= $remark['remark_type'] ?>">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                    <strong>
                                        Prof. <?= safe($remark['first_name'] . ' ' . $remark['last_name']) ?>
                                    </strong>
                                    <span class="badge badge-<?= $remark['remark_type'] === 'warning' ? 'warning' : 'info' ?>" style="text-transform: capitalize;">
                                        <?= $remark['remark_type'] ?>
                                    </span>
                                </div>
                                <?php if ($remark['course_code']): ?>
                                    <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.5rem;">
                                        Re: <?= safe($remark['course_code']) ?> - <?= safe($remark['course_name']) ?>
                                    </div>
                                <?php endif; ?>
                                <p style="margin: 0;">
                                    <?= nl2br(safe($remark['remark_text'])) ?>
                                </p>
                                <?php if ($remark['action_required']): ?>
                                    <div style="margin-top: 0.5rem; padding: 0.5rem; background: #fef3c7; border-radius: 4px; font-size: 0.85rem;">
                                        Action required - please respond to this remark
                                    </div>
                                <?php endif; ?>
                                <small style="color: #666; display: block; margin-top: 0.5rem;">
                                    <?= date('M d, Y g:i A', strtotime($remark['created_at'])) ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- SUGGESTED LEARNING RESOURCES -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Learning Resources</h2>
                </div>
                <div class="card-content">
                    <?php if (empty($resources)): ?>
                        <p>No suggested resources at this time.</p>
                    <?php else: ?>
                        <div class="resource-list">
                            <?php foreach ($resources as $r): ?>
                                <a href="<?= safe($r['url']) ?>" target="_blank" class="resource-item" style="text-decoration: none; color: inherit;">
                                    <?php if (isset($r['thumbnail'])): ?>
                                        <img src="<?= safe($r['thumbnail']) ?>" alt="Thumbnail">
                                    <?php endif; ?>
                                    <div style="flex: 1;">
                                        <strong style="display: block; margin-bottom: 0.25rem;">
                                            <?= safe($r['title']) ?>
                                        </strong>
                                        <span style="font-size: 0.85rem; color: #666;">
                                            <?= safe($r['source']) ?>
                                        </span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RECENT ADVISING SESSIONS -->
            <div class="card two-column-section">
                <div class="card-header">
                    <h2 class="card-title">Recent Advising Sessions</h2>
                </div>
                <div class="card-content">
                    <?php if (empty($advisingSessions)): ?>
                        <p>No advising session history yet.</p>
                    <?php else: ?>
                        <?php foreach ($advisingSessions as $session): ?>
                            <div style="border-left: 3px solid var(--primary-color); padding-left: 1rem; margin-bottom: 1.5rem;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                    <div>
                                        <strong>Prof. <?= safe($session['first_name'] . ' ' . $session['last_name']) ?></strong>
                                        <span style="color: #666; font-size: 0.85rem; margin-left: 0.5rem;">
                                            (<?= safe($session['department']) ?>)
                                        </span>
                                    </div>
                                    <span style="font-size: 0.85rem; color: #666;">
                                        <?= date('M d, Y', strtotime($session['session_date'])) ?>
                                    </span>
                                </div>
                                <?php if ($session['notes']): ?>
                                    <p style="margin: 0.5rem 0; color: #374151;">
                                        <?= nl2br(safe($session['notes'])) ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($session['follow_up_required']): ?>
                                    <div style="background: #fef3c7; padding: 0.5rem; border-radius: 4px; font-size: 0.85rem; margin-top: 0.5rem;">
                                        ⚠️ Follow-up required
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- COURSE RECOMMENDATIONS -->
            <div class="card full-width-section">
                <div class="card-header">
                    <h2 class="card-title">Recommended Courses for Next Semester</h2>
                </div>
                <div class="card-content">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1rem;">
                        <?php foreach ($displayedRecommendations as $rec): ?>
                            <div class="recommendation-card">
                                <h3 style="margin: 0 0 0.5rem 0;">
                                    <?= safe($rec['course']['course_code']) ?> - <?= safe($rec['course']['course_name']) ?>
                                </h3>
                                <div style="margin-bottom: 0.75rem;">
                                    <span class="badge badge-primary"><?= ucfirst($rec['course']['level']) ?></span>
                                    <span class="badge badge-success"><?= $rec['course']['credits'] ?> credits</span>
                                    <span class="badge badge-info">Match: <?= $rec['score'] ?>%</span>
                                </div>
                                <p style="font-size: 0.9rem; color: #666; margin-bottom: 0.75rem;">
                                    <?= safe($rec['course']['description']) ?>
                                </p>
                                <div style="background: #d1fae5; padding: 0.75rem; border-radius: 4px; border-left: 4px solid #10b981;">
                                    <strong style="color: #065f46;">💡 Why recommended:</strong>
                                    <p style="margin: 0.25rem 0 0 0; color: #065f46;">
                                        <?= safe($rec['reason']) ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($recommendations) > $maxDisplay): ?>
                        <a href="viewMoreCourses.php<?= $courseId ? '?course_id=' . $courseId : '' ?>" 
                           class="btn btn-primary" 
                           style="width: 100%; margin-top: 1rem;">
                            View All <?= count($recommendations) ?> Recommendations
                        </a>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</body>
</html>