<?php
require_once __DIR__ . '/../../includes/init.php';
$auth->requireRole('student');

$userId = $_SESSION['user_id'];
$courseId = $_GET['course_id'] ?? null;

if (!$courseId) {
    echo '<p>No course selected.</p>';
    exit;
}

// Fetch course info
$course = $db->fetchOne("
    SELECT c.*, ce.semester, ce.status, cg.school_year,
           COALESCE(cs.section, sp.current_section) as section
    FROM courses c
    JOIN course_enrollments ce ON c.id = ce.course_id
    LEFT JOIN course_grades cg ON ce.student_id = cg.student_id AND ce.course_id = cg.course_id
    LEFT JOIN course_sections cs ON cs.student_id = ce.student_id AND cs.course_id = c.id
    LEFT JOIN student_profiles sp ON ce.student_id = sp.user_id
    WHERE c.id = ? AND ce.student_id = ?
", [$courseId, $userId]);

if (!$course) {
    echo '<p>Course not found.</p>';
    exit;
}

// Fetch syllabus if exists
$syllabus = $db->fetchOne("
    SELECT * FROM course_syllabi 
    WHERE course_id = ? 
    ORDER BY uploaded_at DESC 
    LIMIT 1
", [$courseId]);

// Fetch grade components grouped by period
$gradeComponents = $db->fetchAll("
    SELECT gc.* 
    FROM grade_components gc
    JOIN course_grades cg ON gc.course_grade_id = cg.id
    WHERE cg.student_id = ? AND cg.course_id = ?
    ORDER BY 
        FIELD(gc.period, 'prelim', 'midterm', 'semi_final', 'final'),
        gc.component_type, gc.date_recorded
", [$userId, $courseId]);

// Group by period
$periodGroups = [
    'prelim' => [],
    'midterm' => [],
    'semi_final' => [],
    'final' => []
];
foreach ($gradeComponents as $component) {
    $periodGroups[$component['period']][] = $component;
}

function calculatePeriodGrade($components, $syllabus) {
    if (empty($components)) return null;
    
    $breakdown = $syllabus ? json_decode($syllabus['grading_breakdown'], true) : null;
    $period = $components[0]['period'];
    
    // Group by component type
    $typeGroups = [];
    foreach ($components as $c) {
        $typeGroups[$c['component_type']][] = $c;
    }
    
    $totalScore = 0;
    $totalWeight = 0;
    
    foreach ($typeGroups as $type => $items) {
        $typeScore = 0;
        $typeMaxScore = 0;
        
        foreach ($items as $item) {
            $typeScore += $item['score'];
            $typeMaxScore += $item['max_score'];
        }
        
        $percentage = $typeMaxScore > 0 ? ($typeScore / $typeMaxScore) * 100 : 0;
        $weight = $breakdown[$period][$type] ?? 50; // default weight
        
        $totalScore += $percentage * ($weight / 100);
        $totalWeight += $weight;
    }
    
    return $totalWeight > 0 ? round($totalScore, 2) : null;
}

function safe($value, $fallback = 'N/A') {
    return htmlspecialchars($value ?? $fallback);
}
?>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 class="card-title" style="margin: 0;">
                <?= safe($course['course_code']) ?> - <?= safe($course['course_name']) ?>
            </h2>
            <p style="margin: 0.5rem 0 0 0; color: #666;">
                Section: <?= safe($course['section']) ?> | 
                Semester: <?= safe($course['semester']) ?> | 
                Year: <?= safe($course['school_year']) ?>
            </p>
        </div>
        <button onclick="window.print()" class="btn btn-secondary no-print">
            Print Records
        </button>
    </div>

    <!-- Syllabus Section with Upload -->
    <div style="background: #f8fafc; padding: 1.5rem; border-bottom: 1px solid #e2e8f0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="margin: 0;">Course Syllabus</h3>
            <?php if ($syllabus): ?>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="<?= safe($syllabus['file_path']) ?>" target="_blank" class="btn btn-sm btn-success">
                        View/Download Syllabus
                    </a>
                    <button onclick="replaceSyllabus(<?= $courseId ?>)" class="btn btn-sm btn-warning no-print">
                        Replace
                    </button>
                </div>
            <?php else: ?>
                <button onclick="uploadSyllabus(<?= $courseId ?>)" class="btn btn-sm btn-primary no-print">
                    Upload Syllabus
                </button>
            <?php endif; ?>
        </div>
        
        <?php if ($syllabus): ?>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.9rem;">
                <div>
                    <strong>Uploaded:</strong> <?= date('M d, Y', strtotime($syllabus['uploaded_at'])) ?>
                </div>
                <?php if ($syllabus['grading_breakdown']): ?>
                    <div>
                        <strong>Grading System:</strong> 
                        <span class="badge badge-success">Configured</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($syllabus['grading_breakdown']): ?>
                <details style="margin-top: 1rem;">
                    <summary style="cursor: pointer; font-weight: bold; color: var(--primary-color);">
                        View Grading Breakdown
                    </summary>
                    <div style="margin-top: 1rem; padding: 1rem; background: white; border-radius: 4px;">
                        <?php 
                        $breakdown = json_decode($syllabus['grading_breakdown'], true);
                        foreach ($breakdown as $period => $weights):
                        ?>
                            <div style="margin-bottom: 1rem;">
                                <strong style="text-transform: capitalize;">
                                    <?= str_replace('_', ' ', $period) ?> Period:
                                </strong>
                                <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                                    <?php foreach ($weights as $component => $weight): ?>
                                        <span class="badge badge-info">
                                            <?= ucfirst(str_replace('_', ' ', $component)) ?>: <?= $weight ?>%
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        <?php else: ?>
            <div style="background: #fef3c7; padding: 1rem; border-radius: 4px; border-left: 4px solid #f59e0b;">
                <strong>No syllabus uploaded yet.</strong>
                <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem;">
                    Upload the course syllabus to enable automatic grade calculation and AI-powered recommendations.
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Grade Records by Period -->
    <div style="padding: 1.5rem;">
        <h3 style="margin-bottom: 1.5rem;">Grade Records by Period</h3>
    
    <?php foreach ($periodGroups as $period => $components): ?>
        <div style="border: 2px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; background: white;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 2px solid #e2e8f0;">
                <h4 style="margin: 0; text-transform: capitalize; font-size: 1.2rem;">
                    <?= str_replace('_', ' ', $period) ?> Period
                </h4>
                <?php 
                $periodGrade = calculatePeriodGrade($components, $syllabus);
                if ($periodGrade !== null):
                ?>
                    <div style="text-align: right;">
                        <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">Period Grade</div>
                        <span class="badge badge-success" style="font-size: 1.3rem; padding: 0.5rem 1rem;">
                            <?= $periodGrade ?>%
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($components)): ?>
                <div style="text-align: center; padding: 2rem; color: #666; background: #f9fafb; border-radius: 4px;">
                    <p style="margin: 0; font-style: italic;">No records for this period yet.</p>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem;">Click "Add Record" below to start tracking your grades.</p>
                </div>
            <?php else: ?>
                <!-- Group by component type -->
                <?php
                $typeGroups = [];
                foreach ($components as $comp) {
                    $typeGroups[$comp['component_type']][] = $comp;
                }
                ?>
                
                <?php foreach ($typeGroups as $type => $items): ?>
                    <div style="margin-bottom: 1.5rem;">
                        <div style="background: #f8fafc; padding: 0.75rem; border-radius: 4px; margin-bottom: 0.5rem;">
                            <strong style="text-transform: capitalize; color: #374151;">
                                <?php
                                // Removed icons for clean look
                                ?>
                                <?= str_replace('_', ' ', $type) ?>
                            </strong>
                            <?php
                            // Calculate type score
                            $typeScore = array_sum(array_column($items, 'score'));
                            $typeMaxScore = array_sum(array_column($items, 'max_score'));
                            $typePercentage = $typeMaxScore > 0 ? round(($typeScore / $typeMaxScore) * 100, 2) : 0;
                            ?>
                            <span style="float: right; font-size: 0.9rem; color: #666;">
                                Total: <?= $typeScore ?>/<?= $typeMaxScore ?> (<?= $typePercentage ?>%)
                            </span>
                        </div>
                        
                        <table class="table" style="margin-bottom: 0;">
                            <thead>
                                <tr style="background: #f8fafc;">
                                    <th>Component Name</th>
                                    <th style="text-align: center;">Score</th>
                                    <th style="text-align: center;">Max</th>
                                    <th style="text-align: center;">%</th>
                                    <th style="text-align: center;">Weight</th>
                                    <th>Date</th>
                                    <th class="no-print" style="text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $comp): ?>
                                    <tr>
                                        <td>
                                            <?= safe($comp['component_name']) ?>
                                            <?php if ($comp['notes']): ?>
                                                <span title="<?= safe($comp['notes']) ?>" style="cursor: help; color: #666;">
                                                    info
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center; font-weight: bold;">
                                            <?= $comp['score'] ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <?= $comp['max_score'] ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php
                                            $percentage = $comp['max_score'] > 0 ? round(($comp['score'] / $comp['max_score']) * 100, 2) : 0;
                                            $colorClass = $percentage >= 75 ? 'color: #10b981;' : ($percentage >= 60 ? 'color: #f59e0b;' : 'color: #ef4444;');
                                            ?>
                                            <span style="<?= $colorClass ?> font-weight: bold;">
                                                <?= $percentage ?>%
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <?= $comp['weight'] ?>%
                                        </td>
                                        <td style="font-size: 0.85rem; color: #666;">
                                            <?= date('M d, Y', strtotime($comp['date_recorded'])) ?>
                                        </td>
                                        <td class="no-print" style="text-align: center;">
                                            <button onclick="editComponent(<?= $comp['id'] ?>)" 
                                                    class="btn btn-sm btn-primary"
                                                    style="padding: 0.25rem 0.5rem; font-size: 0.85rem;">
                                                Edit
                                            </button>
                                            <button onclick="deleteComponent(<?= $comp['id'] ?>, <?= $courseId ?>)" 
                                                    class="btn btn-sm btn-danger"
                                                    style="padding: 0.25rem 0.5rem; font-size: 0.85rem;">
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Add Component Button -->
            <div style="text-align: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                <button onclick="addComponent('<?= $period ?>', <?= $courseId ?>)" 
                        class="btn btn-success no-print">
                    Add <?= ucfirst(str_replace('_', ' ', $period)) ?> Record
                </button>
            </div>
        </div>
    <?php endforeach; ?>
    
    </div>
</div>

<script>
function addComponent(period, courseId) {
    window.location.href = `addGradeComponent.php?course_id=${courseId}&period=${period}`;
}

function editComponent(componentId) {
    window.location.href = `editGradeComponent.php?id=${componentId}`;
}

function deleteComponent(componentId, courseId) {
    if (confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
        fetch(`deleteGradeComponent.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: componentId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload the course details
                window.location.reload();
            } else {
                alert('Failed to delete record: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            alert('Error deleting record. Please try again.');
        });
    }
}

function uploadSyllabus(courseId) {
    window.location.href = `uploadSyllabus.php?course_id=${courseId}`;
}

function replaceSyllabus(courseId) {
    if (confirm('Replace the existing syllabus? The old syllabus will be archived.')) {
        window.location.href = `uploadSyllabus.php?course_id=${courseId}&replace=1`;
    }
}
</script>