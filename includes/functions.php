<?php
// -------------------------
// Utility Functions
// -------------------------
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

// -------------------------
// Recommendation Engine
// -------------------------
function getRecommendedCourses($studentId) {
    $db = Database::getInstance();
    
    $student = $db->fetchOne(
        "SELECT sp.*, u.email, sp.major, sp.credits_completed, sp.gpa
         FROM student_profiles sp 
         JOIN users u ON sp.user_id = u.id 
         WHERE sp.user_id = ?",
        [$studentId]
    );
    
    if (!$student) return [];

    $completedCourses = $db->fetchAll(
        "SELECT c.course_code, c.id 
         FROM course_enrollments ce 
         JOIN courses c ON ce.course_id = c.id 
         WHERE ce.student_id = ? AND ce.status = 'completed'",
        [$studentId]
    );

    $completedCodes = array_column($completedCourses, 'course_code');
    $completedIds = array_column($completedCourses, 'id');

    $allCourses = $db->fetchAll("SELECT * FROM courses WHERE is_active = TRUE");
    $recommendations = [];

    foreach ($allCourses as $course) {
        if (in_array($course['id'], $completedIds)) continue;

        $prerequisites = json_decode($course['prerequisites'], true) ?? [];
        $prerequisitesMet = true;
        foreach ($prerequisites as $prereq) {
            if (!in_array($prereq, $completedCodes)) {
                $prerequisitesMet = false;
                break;
            }
        }
        if (!$prerequisitesMet) continue;

        $score = 0;
        $levelMap = ['freshman'=>1, 'sophomore'=>2, 'junior'=>3, 'senior'=>4];
        $studentLevel = ceil($student['credits_completed']/30);
        $courseLevel = $levelMap[$course['level']] ?? 0;
        if ($courseLevel == $studentLevel || $courseLevel == $studentLevel +1) $score += 50;
        if ($student['gpa']>=3.5 && in_array($course['level'], ['senior','graduate'])) $score +=30;
        if ($course['department']==$student['major']) $score +=40;
        $creditsNeeded = 120 - $student['credits_completed'];
        if ($creditsNeeded >= $course['credits']) $score +=20;

        $recommendations[] = [
            'course'=>$course,
            'score'=>$score,
            'reason'=>generateRecommendationReason($course, $student, $prerequisitesMet)
        ];
    }

    usort($recommendations, fn($a,$b)=>$b['score']-$a['score']);
    return array_slice($recommendations,0,10);
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

// -------------------------
// Wikipedia Search
// -------------------------
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

// -------------------------
// YouTube Search
// -------------------------
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

// -------------------------
// Aggregate suggested resources
// -------------------------
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
?>
