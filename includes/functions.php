<?php

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
    if ($gpa === null || $gpa === '') return 'N/A';
    return number_format((float)$gpa, 2);
}

function getRecommendedCourses($studentId) {
$recommendations = [];

$analysis = analyzeStudentPerformance($GLOBALS['db'], $studentId);

$gpa = $analysis['profile']['gpa'] ?? 0;
$standing = strtolower($analysis['profile']['academic_standing'] ?? '');
$grades = $analysis['grades'];
$remarksText = strtolower(implode(' ', $analysis['remarks']));

$averageGrade = null;
if (!empty($grades)) {
$averageGrade = !empty($grades)
    ? array_sum($grades) / count($grades)
    : null;

}

/* ---------- RULE-BASED AI LOGIC ---------- */

// Acad Standing
if (str_contains($standing, 'probation') || $gpa < 2.0) {
    $recommendations[] = [
        'reason' => 'Your academic standing indicates risk. Lighter or foundation courses are recommended.',
        'score' => 92
    ];
}

// Course Performance
if ($averageGrade !== null && $averageGrade < 75) {
    $recommendations[] = [
        'reason' => 'Low performance detected in this course. Remedial and practice-focused subjects are advised.',
        'score' => 90
    ];
}

// Prof Remarks/note Analysis
if (str_contains($remarksText, 'improve') || str_contains($remarksText, 'weak')) {
    $recommendations[] = [
        'reason' => 'Professor remarks suggest improvement areas. Skill-building courses are recommended.',
        'score' => 88
    ];
}

if (str_contains($remarksText, 'excellent') || str_contains($remarksText, 'strong')) {
    $recommendations[] = [
        'reason' => 'Strong professor feedback detected. Advanced or specialization courses are recommended.',
        'score' => 94
    ];
}

// Default fallback
if (empty($recommendations)) {
    $recommendations[] = [
        'reason' => 'Based on your academic record, you are on track. Core and elective progression courses are recommended.',
        'score' => 85
    ];
}
}

function generateRecommendationReason($course, $student, $prerequisitesMet) {
    $reasons = [];
    if ($prerequisitesMet) $reasons[]="All prerequisites completed";
    if ($course['department']==$student['major']) $reasons[]="Matches your major";
    $studentLevel = ceil($student['credits_completed']/30);
    $levelMap = ['freshman'=>1,'sophomore'=>2,'junior'=>3,'senior'=>4];
    $courseLevel = $levelMap[$course['level']] ?? 0;
    if ($courseLevel==$studentLevel) $reasons[]="Appropriate for your current level";
    if ($student['gpa']>=3.5) $reasons[]="Your strong GPA qualifies you for advanced courses";
    return implode("; ", $reasons);
}


// API - Wikipedia Search
function searchWikipedia($query, $limit=3) {
    $query .= " computer science";
    $url = "https://en.wikipedia.org/w/api.php?action=query&list=search&format=json&srlimit={$limit}&srsearch=".urlencode($query);
    $opts=["http"=>["header"=>"User-Agent: Academic-Advising-System/1.0\r\n"]];
    $context=stream_context_create($opts);
    $response=@file_get_contents($url,false,$context);
    if(!$response) return [];

    $data=json_decode($response,true);
    $results=[];
    if(isset($data['query']['search'])){
        foreach($data['query']['search'] as $item){
            $results[]=[
                'title'=>$item['title'],
                'url'=>"https://en.wikipedia.org/wiki/".urlencode(str_replace(' ','_',$item['title'])),
                'source'=>'Wikipedia'
            ];
        }
    }
    return $results;
}

// API - YouTube Search
function searchYouTube($query, $limit=3) {
    $query .= " tutorial";
    $apiKey = YOUTUBE_API_KEY;
    $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&q=".urlencode($query)."&type=video&maxResults={$limit}&key={$apiKey}";
    
    $response=@file_get_contents($url);
    if(!$response) return [];
    $data=json_decode($response,true);
    $results=[];
    if(isset($data['items'])){
        foreach($data['items'] as $item){
            $videoId = $item['id']['videoId'];
            $results[]=[
                'title'=>$item['snippet']['title'],
                'url'=>"https://www.youtube.com/watch?v={$videoId}",
                'thumbnail'=>$item['snippet']['thumbnails']['high']['url'],
                'source'=>'YouTube'
            ];
        }
    }
    return $results;
}


// Aggregate suggested resources
function getSuggestedResources($displayedRecommendations) {
    $resources=[];
    foreach($displayedRecommendations as $rec){
        $courseName = $rec['course']['course_name'];
        $resources=array_merge($resources,searchWikipedia($courseName,2));
        $resources=array_merge($resources,searchYouTube($courseName,2));
    }

    $uniqueUrls=[];
    $filtered=[];
    foreach($resources as $r){
        if(!in_array($r['url'],$uniqueUrls)){
            $uniqueUrls[]=$r['url'];
            $filtered[]=$r;
        }
    }

    if(empty($filtered)){
        $filtered=[
            ['title'=>'Data Structures','url'=>'https://en.wikipedia.org/wiki/Data_structure','source'=>'Wikipedia']
        ];
    }
    return $filtered;
}

function analyzeStudentPerformance($db, $userId, $courseId = null)
{
    // Student profile
    $profile = $db->fetchOne("
        SELECT gpa, academic_standing
        FROM student_profiles
        WHERE user_id = ?
    ", [$userId]);

    // Course grades (if course selected)
    $grades = [];
    if ($courseId) {
        $grades = $db->fetchAll("
            SELECT period, final_grade
            FROM course_grades
            WHERE student_id = ? AND course_id = ?
        ", [$userId, $courseId]);
    }

    // Professor remarks
    $remarks = $db->fetchAll("
        SELECT remark_text
        FROM course_specific_remarks
        WHERE student_id = ?
        " . ($courseId ? "AND course_id = ?" : ""),
        $courseId ? [$userId, $courseId] : [$userId]
    );

    return [
        'profile' => $profile,
        'grades' => $grades,
        'remarks' => array_column($remarks, 'remark_text')
    ];
}
?>
