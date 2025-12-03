<?php
// Recommendation Engine
function getRecommendedCourses($studentId) {
    $db = Database::getInstance();
    
    // Get student profile
    $student = $db->fetchOne(
        "SELECT sp.*, u.email 
         FROM student_profiles sp 
         JOIN users u ON sp.user_id = u.id 
         WHERE sp.user_id = ?", 
        [$studentId]
    );
    
    if (!$student) {
        return [];
    }
    
    // Get completed courses
    $completedCourses = $db->fetchAll(
        "SELECT c.course_code, c.id 
         FROM course_enrollments ce 
         JOIN courses c ON ce.course_id = c.id 
         WHERE ce.student_id = ? AND ce.status = 'completed'",
        [$studentId]
    );
    
    $completedCodes = array_column($completedCourses, 'course_code');
    $completedIds = array_column($completedCourses, 'id');
    
    // Get all available courses
    $allCourses = $db->fetchAll(
        "SELECT * FROM courses WHERE is_active = TRUE"
    );
    
    $recommendations = [];
    
    foreach ($allCourses as $course) {
        // Skip if already completed
        if (in_array($course['id'], $completedIds)) {
            continue;
        }
        
        // Check prerequisites
        $prerequisites = json_decode($course['prerequisites'], true) ?? [];
        $prerequisitesMet = true;
        
        foreach ($prerequisites as $prereq) {
            if (!in_array($prereq, $completedCodes)) {
                $prerequisitesMet = false;
                break;
            }
        }
        
        if (!$prerequisitesMet) {
            continue;
        }
        
        // Calculate recommendation score
        $score = 0;
        
        // Level matching
        $levelMap = ['freshman' => 1, 'sophomore' => 2, 'junior' => 3, 'senior' => 4];
        $studentLevel = ceil($student['credits_completed'] / 30);
        $courseLevel = $levelMap[$course['level']] ?? 0;
        
        if ($courseLevel == $studentLevel || $courseLevel == $studentLevel + 1) {
            $score += 50;
        }
        
        // GPA-based recommendations
        if ($student['gpa'] >= 3.5 && in_array($course['level'], ['senior', 'graduate'])) {
            $score += 30;
        }
        
        // Major matching
        if ($course['department'] == 'Computer Science' && $student['major'] == 'Computer Science') {
            $score += 40;
        }
        
        // Credits remaining
        $creditsNeeded = 120 - $student['credits_completed'];
        if ($creditsNeeded >= $course['credits']) {
            $score += 20;
        }
        
        $recommendations[] = [
            'course' => $course,
            'score' => $score,
            'reason' => generateRecommendationReason($course, $student, $prerequisitesMet)
        ];
    }
    
    // Sort by score
    usort($recommendations, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    return array_slice($recommendations, 0, 10); // Top 10 recommendations
}

function generateRecommendationReason($course, $student, $prerequisitesMet) {
    $reasons = [];
    
    if ($prerequisitesMet) {
        $reasons[] = "All prerequisites completed";
    }
    
    if ($course['department'] == $student['major']) {
        $reasons[] = "Matches your major";
    }
    
    $studentLevel = ceil($student['credits_completed'] / 30);
    $levelMap = ['freshman' => 1, 'sophomore' => 2, 'junior' => 3, 'senior' => 4];
    $courseLevel = $levelMap[$course['level']] ?? 0;
    
    if ($courseLevel == $studentLevel) {
        $reasons[] = "Appropriate for your current level";
    }
    
    if ($student['gpa'] >= 3.5) {
        $reasons[] = "Your strong GPA qualifies you for advanced courses";
    }
    
    return implode("; ", $reasons);
}

// Utility Functions
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function flashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        
        $alertClass = [
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info'
        ][$type] ?? 'alert-info';
        
        return "<div class='alert $alertClass alert-dismissible fade show' role='alert'>
                    $message
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                </div>";
    }
    return '';
}

function formatGPA($gpa) {
    if ($gpa === null || $gpa === '') {
        return 'N/A'; // or return '0.00' if you prefer
    }
    return number_format((float)$gpa, 2);
}


function calculateGPA($enrollments) {
    $gradePoints = [
        'A' => 4.0, 'A-' => 3.7,
        'B+' => 3.3, 'B' => 3.0, 'B-' => 2.7,
        'C+' => 2.3, 'C' => 2.0, 'C-' => 1.7,
        'D+' => 1.3, 'D' => 1.0,
        'F' => 0.0
    ];
    
    $totalPoints = 0;
    $totalCredits = 0;
    
    foreach ($enrollments as $enrollment) {
        if (isset($gradePoints[$enrollment['grade']])) {
            $totalPoints += $gradePoints[$enrollment['grade']] * $enrollment['credits'];
            $totalCredits += $enrollment['credits'];
        }
    }
    
    return $totalCredits > 0 ? $totalPoints / $totalCredits : 0;
}