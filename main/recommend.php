<?php
require_once __DIR__ . '/../includes/init.php';

$auth->requireRole('student');

$userId = $_SESSION['user_id'] ?? null;
$courseId = $_GET['course_id'] ?? null;

if (!$userId) {
    echo "No user ID found in session.";
    exit;
}

// Initialize AI Engine
$aiEngine = new AIEngine($db);

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

// ===================== AI ANALYSIS SECTION =====================

// Check if we need to regenerate AI insights
$regenerate = isset($_GET['regenerate']) && $_GET['regenerate'] === '1';

if ($courseId && $regenerate) {
    // Trigger AI analysis
    $aiEngine->analyzeStudentPerformance($userId, $courseId);
    header("Location: recommend.php?course_id={$courseId}");
    exit;
}

// Fetch existing AI insights
$insights = $db->fetchAll("
    SELECT * FROM ai_insights
    WHERE student_id = ? " . ($courseId ? "AND course_id = ?" : "") . "
    ORDER BY generated_at DESC
    LIMIT 5
", $courseId ? [$userId, $courseId] : [$userId]);

// If no insights exist, generate them automatically
if (empty($insights) && $courseId) {
    try {
        $aiEngine->analyzeStudentPerformance($userId, $courseId);
        // Reload insights
        $insights = $db->fetchAll("
            SELECT * FROM ai_insights
            WHERE student_id = ? AND course_id = ?
            ORDER BY generated_at DESC
            LIMIT 5
        ", [$userId, $courseId]);
    } catch (Exception $e) {
        error_log("AI Analysis failed: " . $e->getMessage());
    }
}

// Get AI-generated learning resources
$resources = [];
if ($courseId) {
    try {
        $resources = $aiEngine->generateLearningResources($userId, $courseId);
    } catch (Exception $e) {
        error_log("Resource generation failed: " . $e->getMessage());
        // Fallback to database stored resources
        $resources = getSuggestedResources([]);
    }
}

// Get course recommendations
$recommendations = [];
try {
    $recommendations = $aiEngine->recommendNextCourses($userId);
} catch (Exception $e) {
    error_log("Course recommendations failed: " . $e->getMessage());
    $recommendations = getRecommendedCourses($userId);
}

// Get professor remarks
$professorRemarks = $db->fetchAll("
    SELECT csr.*, u.first_name, u.last_name, c.course_code, c.course_name
    FROM course_specific_remarks csr
    JOIN users u ON csr.professor_id = u.id
    LEFT JOIN courses c ON csr.course_id = c.id
    WHERE csr.student_id = ? " . ($courseId ? "AND csr.course_id = ?" : "") . "
    ORDER BY csr.created_at DESC
    LIMIT 10
", $courseId ? [$userId, $courseId] : [$userId]);

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

$maxDisplay = $courseId ? 6 : 3;
$recommendations = $recommendations ?? [];
$displayedRecommendations = array_slice($recommendations, 0, $maxDisplay);

function safe($value, $fallback = 'N/A') {
    return htmlspecialchars($value ?? $fallback);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $selectedCourse ? safe($selectedCourse['course_code']) . ' - ' : '' ?>AI Recommendations - <?= APP_NAME ?></title>
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

        .ai-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: linear-gradient(135deg, #1e40af 0%, #3a68ffff 100%);
            color: white;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            margin-left: 0.5rem;
        }

        .regenerate-btn {
            background: #4c75fcff;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .regenerate-btn:hover:not(:disabled) {
            background: #1e40af;
        }

        .regenerate-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            opacity: 0.6;
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
        <?php $hasCourse = !empty($courseId); ?>
        <div style="margin: 2rem 0 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <div>
                    <h1 class="card-title" style="margin: 0;">
                        <?= $hasCourse
                            ? safe($selectedCourse['course_code'] ?? 'Course') . ': Recommendations & Insights'
                            : 'AI-Assisted Recommendations & Insights' ?>
                        <span class="ai-badge"><?= $hasCourse ? 'AI-Assisted' : 'AI Analysis' ?></span>
                    </h1>
                    <?php if ($hasCourse): ?>
                        <p style="margin: 0.5rem 0 0 0; color: #666;">
                            <?= safe($selectedCourse['course_name'] ?? '') ?> |
                            <?= safe($selectedCourse['semester'] ?? '') ?> <?= safe($selectedCourse['school_year'] ?? '') ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button
                        onclick="regenerateAI(<?= $hasCourse ? (int) $courseId : 0 ?>)"
                        class="regenerate-btn"
                        <?= $hasCourse ? '' : 'disabled title="Select a course to regenerate"' ?>
                    >
                        Refresh & Regenerate
                    </button>
                    <a href="student/academicProfile.php<?= $hasCourse ? '?course_id=' . urlencode((string) $courseId) : '' ?>" class="btn btn-secondary">
                        Back to Profile
                    </a>
                </div>
            </div>
        </div>

        <div class="three-column-grid">
            
            <!-- AI INSIGHTS & ALERTS -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        Insights & Recommendations
                        <span class="ai-badge">AI-Assisted</span>
                    </h2>
                </div>
                <div class="card-content">
                    <?php if (empty($insights)): ?>
                        <div style="text-align: center; padding: 2rem; background: #f9fafb; border-radius: 8px;">
                            <p>Generating insights...</p>
                            <p style="font-size: 0.9rem; color: #666;">
                                Analyzing your performance. Refresh in a moment.
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($insights as $insight): ?>
                            <div class="insight-card <?= $insight['insight_type'] === 'risk_alert' ? 'risk' : ($insight['insight_type'] === 'study_recommendation' ? 'success' : '') ?>">
                                <div style="display: flex; justify-content: between; align-items: start;">
                                    <strong style="text-transform: capitalize;">
                                        <?= str_replace('_', ' ', $insight['insight_type']) ?>
                                    </strong>
                                    <?php if ($insight['confidence_score']): ?>
                                        <span style="font-size: 0.85rem; color: #666; margin-left: auto;">
                                            Confidence: <?= round($insight['confidence_score'] * 100) ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p style="margin: 0.5rem 0 0 0;">
                                    <?php 
                                    // Extract source tag from insight text
                                    $text = $insight['insight_text'];
                                    $source = 'Unknown';
                                    $sourceBadgeClass = '';
                                    
                                    if (preg_match('/\[source: ([^\]]+)\]/', $text, $matches)) {
                                        $source = $matches[1];
                                        $text = preg_replace('/\s*\[source: [^\]]+\]$/', '', $text); // Remove tag from display
                                        
                                        // Set badge styling based on source
                                        if (strpos($source, 'AI-') === 0) {
                                            $sourceBadgeClass = 'badge-ai';
                                        } else if ($source === 'RULE') {
                                            $sourceBadgeClass = 'badge-rule';
                                        }
                                    }
                                    
                                    echo safe($text);
                                    ?>
                                </p>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
                                    <small style="color: #666;">
                                        Generated: <?= date('M d, Y g:i A', strtotime($insight['generated_at'])) ?>
                                    </small>
                                    <span class="source-badge <?= $sourceBadgeClass ?>">
                                        <?= $source ?>
                                    </span>
                                </div>
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
                                <small style="color: #666; display: block; margin-top: 0.5rem;">
                                    <?= date('M d, Y g:i A', strtotime($remark['created_at'])) ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- AI-GENERATED LEARNING RESOURCES -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        Personalized Learning Resources
                        <span class="ai-badge">AI</span>
                    </h2>
                </div>
                <div class="card-content">
                    <?php if (empty($resources)): ?>
                        <p>No suggested resources at this time.</p>
                    <?php else: ?>
                        <div class="resource-list">
                            <?php foreach ($resources as $r): ?>
                                <a href="<?= safe($r['url']) ?>" target="_blank" class="resource-item" style="text-decoration: none; color: inherit; display: flex; align-items: center; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 4px; margin-bottom: 0.5rem; transition: background 0.2s;">
                                    <div style="flex: 1;">
                                        <strong style="display: block; margin-bottom: 0.25rem;">
                                            <?= safe($r['title']) ?>
                                        </strong>
                                        <span class="badge badge-<?= $r['type'] === 'video' ? 'danger' : 'primary' ?>" style="font-size: 0.75rem;">
                                            <?= safe($r['type']) ?>
                                        </span>
                                        <p style="font-size: 0.85rem; color: #666; margin: 0.25rem 0 0 0;">
                                            <?= safe($r['description']) ?>
                                        </p>
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
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- AI-POWERED COURSE RECOMMENDATIONS -->
            <div class="card full-width-section">
                <div class="card-header">
                    <h2 class="card-title">
                        AI-Assisted Recommended Courses for Next Semester
                        <span class="ai-badge">AI</span>
                    </h2>
                </div>
                <div class="card-content">
                    <?php if (empty($displayedRecommendations)): ?>
                        <div style="text-align: center; padding: 2rem;">
                            <p>AI is analyzing your profile to recommend courses...</p>
                        </div>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1rem;">
                            <?php foreach ($displayedRecommendations as $rec): ?>
                                <div class="recommendation-card" style="border: 1px solid #e2e8f0; padding: 1rem; border-radius: 8px; transition: all 0.3s;">
                                    <h3 style="margin: 0 0 0.5rem 0;">
                                        <?= safe($rec['course']['course_code']) ?> - <?= safe($rec['course']['course_name']) ?>
                                    </h3>
                                    <div style="margin-bottom: 0.75rem;">
                                        <span class="badge badge-primary"><?= ucfirst($rec['course']['level']) ?></span>
                                        <span class="badge badge-success"><?= $rec['course']['credits'] ?> credits</span>
                                        <span class="badge badge-info">AI Match: <?= $rec['score'] ?>%</span>
                                    </div>
                                    <p style="font-size: 0.9rem; color: #666; margin-bottom: 0.75rem;">
                                        <?= safe($rec['course']['description']) ?>
                                    </p>
                                    <div style="background: #d1fae5; padding: 0.75rem; border-radius: 4px; border-left: 4px solid #10b981;">
                                        <strong style="color: #065f46;">Why?(AI-Based Reasons):</strong>
                                        <p style="margin: 0.25rem 0 0 0; color: #065f46;">
                                            <?= safe($rec['reason']) ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <script>
        function regenerateAI(courseId) {
            console.log('regenerateAI called with courseId:', courseId);
            
            if (!courseId || courseId === 0) {
                alert('Please select a course first.');
                return false;
            }
            
            if (confirm('Regenerate AI analysis? This will update all insights based on your latest performance.')) {
                console.log('Redirecting to: recommend.php?course_id=' + courseId + '&regenerate=1');
                window.location.href = 'recommend.php?course_id=' + courseId + '&regenerate=1';
            }
            
            return false;
        }
    </script>
</body>
</html>