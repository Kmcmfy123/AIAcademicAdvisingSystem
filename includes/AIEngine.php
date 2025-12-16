<?php
/**
 * FREE AI Analysis Engine for Academic Advising System
 * Uses: Google Gemini (primary) + Groq (fallback)
 * Both are FREE with generous limits
 */

require_once __DIR__ . '/../includes/init.php';

class AIEngine {
    private $geminiApiKey;
    private $groqApiKey;
    private $db;
    private $provider = 'gemini'; // 'gemini' or 'groq'
    
    // API Endpoints
    private $geminiEndpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    private $groqEndpoint = 'https://api.groq.com/openai/v1/chat/completions';
    
    public function __construct($db) {
        $this->db = $db;
        
        // Get API keys from environment or config
        $this->geminiApiKey = getenv('GEMINI_API_KEY') ?: '';
        $this->groqApiKey = getenv('GROQ_API_KEY') ?: '';
        
        // Auto-detect which provider to use
        if (!empty($this->geminiApiKey)){
            $this->provider = 'gemini';
        } elseif (!empty($this->groqApiKey)) {
            $this->provider = 'groq';
        } else {
            throw new Exception('No AI API keys configured.');
        }
    }
    
    /**
     * STEP 1: Analyze syllabus PDF with AI
     */
    public function analyzeSyllabus($courseId, $syllabusFilePath) {
        // Extract text from PDF
        $syllabusText = $this->extractTextFromPDF($syllabusFilePath);
        
        $prompt = "Analyze this course syllabus and extract structured information.

SYLLABUS CONTENT:
{$syllabusText}

Extract and return ONLY valid JSON with this exact structure:
{
  \"objectives\": [\"objective1\", \"objective2\"],
  \"weekly_topics\": {
    \"1\": \"Week 1 topic\",
    \"2\": \"Week 2 topic\",
    \"3\": \"Week 3 topic\",
    \"4\": \"Week 4 topic\",
    \"5\": \"Week 5 topic\"
  },
  \"assessments\": {
    \"prelim\": {\"class_standing\": 60, \"exam\": 40},
    \"midterm\": {\"class_standing\": 60, \"exam\": 40},
    \"semi_final\": {\"class_standing\": 60, \"exam\": 40},
    \"final\": {\"class_standing\": 60, \"exam\": 40}
  },
  \"outcomes\": [\"outcome1\", \"outcome2\"]
}

Return ONLY the JSON, no markdown, no explanations.";
        
        $aiResponse = $this->callAI($prompt);
        
        // Clean and parse JSON
        $cleanJson = $this->cleanJsonResponse($aiResponse);
        $analysis = json_decode($cleanJson, true);
        
        if (!$analysis) {
            // Fallback if parsing fails
            $analysis = $this->createDefaultSyllabusStructure();
        }
        
        // Save to database
        $this->db->query("
            UPDATE course_syllabi 
            SET topics = ?, 
                grading_breakdown = ?
            WHERE course_id = ?
        ", [
            json_encode($analysis['weekly_topics'] ?? []),
            json_encode($analysis['assessments'] ?? []),
            $courseId
        ]);
        
        return $analysis;
    }
    
    /**
     * STEP 2: Analyze student performance with AI
     */
    public function analyzeStudentPerformance($studentId, $courseId) {
        // Fetch all necessary data
        $student = $this->getStudentData($studentId);
        $gradeComponents = $this->getGradeComponents($studentId, $courseId);
        $syllabus = $this->getSyllabusTopics($courseId) ?? [];
        $professorRemarks = $this->getProfessorRemarks($studentId, $courseId) ?? [];

        
        // Calculate performance summary
        $performanceSummary = $this->calculatePerformance($gradeComponents);
        
        // Build comprehensive AI prompt
        $prompt = $this->buildAnalysisPrompt(
            $student,
            $performanceSummary,
            $syllabus,
            $professorRemarks
        );
        
        $aiResponse = $this->callAI($prompt);
        
        // Parse AI response
        $cleanJson = $this->cleanJsonResponse($aiResponse);
        $insights = json_decode($cleanJson, true);
        
        if (!$insights) {
            // Fallback to rule-based analysis
            $insights = $this->createRuleBasedInsights($performanceSummary, $syllabus);
        }
        
        // Save insights to database
        $this->saveInsights($studentId, $courseId, $insights);
        
        return $insights;
    }
    
    /**
     * STEP 3: Generate personalized learning resources
     */
    public function generateLearningResources($studentId, $courseId) {
        $weakTopics = $this->identifyWeakTopics($studentId, $courseId);
        $performanceLevel = $this->getPerformanceLevel($studentId, $courseId);
        
        if (empty($weakTopics)) {
            return $this->getDefaultResources($courseId);
        }
        
        $topicsStr = implode(', ', array_slice($weakTopics, 0, 3));
        
        $prompt = "Student needs help with these programming topics: {$topicsStr}
Performance level: {$performanceLevel}

Suggest 5 FREE learning resources (videos, tutorials, practice sites).

Return ONLY valid JSON array:
[
  {
    \"title\": \"Resource title\",
    \"type\": \"video\",
    \"url\": \"https://youtube.com/...\",
    \"description\": \"Brief description\"
  }
]

Requirements:
- Use real YouTube videos, freeCodeCamp, W3Schools, TutorialsPoint
- Focus on {$performanceLevel} difficulty level
- Must be FREE resources only
- Return ONLY JSON, no markdown";
        
        $aiResponse = $this->callAI($prompt);
        $cleanJson = $this->cleanJsonResponse($aiResponse);
        $resources = json_decode($cleanJson, true);
        
        if (!$resources || !is_array($resources)) {
            return $this->getFallbackResources($weakTopics);
        }
        
        return array_slice($resources, 0, 5);
    }
    
    /**
     * STEP 4: Recommend next courses with AI reasoning
     */
    public function recommendNextCourses($studentId) {
        $student = $this->getStudentData($studentId);
        $completedCourses = $this->getCompletedCourses($studentId);
        $availableCourses = $this->getAvailableCourses($studentId);
        
        if (empty($availableCourses)) {
            return [];
        }
        
        $completedStr = implode(', ', array_column($completedCourses, 'course_code'));
        $availableStr = implode(', ', array_slice(array_column($availableCourses, 'course_code'), 0, 10));
        
        $prompt = "Student academic profile:
- Major: {$student['major']}
- Current GPA: {$student['gpa']}
- Completed courses: {$completedStr}

Available next courses: {$availableStr}

Recommend 5 best courses for next semester based on:
1. Prerequisites met
2. Curriculum progression
3. Student GPA/performance
4. Skill building

Return ONLY valid JSON:
[
  {
    \"course_code\": \"CS102\",
    \"reason\": \"Why this course is recommended\",
    \"match_score\": 85
  }
]

Return ONLY JSON, no markdown.";
        
        $aiResponse = $this->callAI($prompt);
        $cleanJson = $this->cleanJsonResponse($aiResponse);
        $recommendations = json_decode($cleanJson, true);
        
        if (!$recommendations || !is_array($recommendations)) {
            return $this->getRuleBasedRecommendations($studentId);
        }
        
        return $this->enrichRecommendations($recommendations);
    }
    
    // ==================== AI PROVIDER METHODS ====================
    
    /**
     * Call AI API (auto-selects provider)
     */
private function callAI($prompt, $systemPrompt = 'You are an academic advisor AI assistant.') {
    if ($this->provider === 'gemini') {
        try {
            return $this->callGemini($prompt, $systemPrompt);
        } catch (Exception $e) {
            error_log("Gemini API failed: " . $e->getMessage());
            if (!empty($this->groqApiKey)) {
                // Automatically fallback to Groq
                $this->provider = 'groq';
                return $this->callGroq($prompt, $systemPrompt);
            }
            throw $e; // No fallback available
        }
    } else {
        return $this->callGroq($prompt, $systemPrompt);
    }
}

    
    /**
     * Google Gemini API (FREE - 60 req/min)
     */
private function callGemini($prompt, $systemPrompt = '') {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" 
         . $this->geminiApiKey;

    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (curl_errno($ch)) {
        throw new Exception("cURL error: " . curl_error($ch));
    }

    if ($httpCode !== 200) {
        // If Gemini is overloaded (503) or temporarily unavailable, throw exception
        throw new Exception("Gemini API failed (HTTP $httpCode): $response");
    }

    $json = json_decode($response, true);

    return $json['candidates'][0]['content']['parts'][0]['text'] ?? 'No AI response';
}

    
    /**
     * Groq API (FREE - unlimited with rate limits), fast n free
     */
private function callGroq($prompt, $systemPrompt) {
    $data = [
        'model' => 'llama-3.1-70b-versatile',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.7,
        'max_tokens' => 2048
    ];

    $ch = curl_init($this->groqEndpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $this->groqApiKey
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("Groq cURL Error: " . $curlError);
        return 'Groq cURL error: ' . $curlError;
    }

    file_put_contents(__DIR__ . '/groq_debug.txt', "HTTP Code: $httpCode\nResponse: $response\n");

    if ($httpCode !== 200) {
        error_log("Groq API Error: " . $response);
        return 'Groq API returned HTTP ' . $httpCode;
    }

    $result = json_decode($response, true);
    if (!$result || !isset($result['choices'][0]['message']['content'])) {
        error_log("Groq API returned invalid JSON: " . $response);
        return 'Groq API invalid response';
    }

    return $result['choices'][0]['message']['content'];
}


    // ==================== HELPER METHODS ====================
    
    private function cleanJsonResponse($response) {
        // Remove markdown code blocks
        $cleaned = preg_replace('/```json\s*|\s*```/', '', $response);
        // Remove any text before first { or [
        $cleaned = preg_replace('/^[^{\[]*/', '', $cleaned);
        // Remove any text after last } or ]
        $cleaned = preg_replace('/[^}\]]*$/', '', $cleaned);
        return trim($cleaned);
    }
    
    private function calculatePerformance($gradeComponents) {
        $periods = ['prelim', 'midterm', 'semi_final', 'final'];
        $performance = [];
        
        foreach ($periods as $period) {
            $periodGrades = array_filter($gradeComponents, function($g) use ($period) {
                return $g['period'] === $period;
            });
            
            if (empty($periodGrades)) continue;
            
            $avgScore = $this->calculateWeightedAverage($periodGrades);
            
            if ($avgScore < 75) {
                $level = 'LOW';
            } elseif ($avgScore < 85) {
                $level = 'AVERAGE';
            } else {
                $level = 'HIGH';
            }
            
            $performance[$period] = [
                'score' => $avgScore,
                'level' => $level
            ];
        }
        
        return $performance;
    }
    
    private function calculateWeightedAverage($grades) {
        $totalWeightedScore = 0;
        $totalWeight = 0;
        
        foreach ($grades as $grade) {
            $percentage = ($grade['score'] / $grade['max_score']) * 100;
            $totalWeightedScore += $percentage * ($grade['weight'] / 100);
            $totalWeight += $grade['weight'];
        }
        
        return $totalWeight > 0 ? $totalWeightedScore : 0;
    }
    
    private function buildAnalysisPrompt($student, $performance, $syllabus, $remarks) {
        $prompt = "Analyze this student's academic performance:

STUDENT INFO:
- Major: {$student['major']}
- Current GPA: {$student['gpa']}

PERFORMANCE BY PERIOD:\n";
        
        foreach ($performance as $period => $data) {
            $prompt .= "- " . ucfirst($period) . ": {$data['score']}% ({$data['level']})\n";
        }
        
        if (!empty($syllabus)) {
            $prompt .= "\nCOURSE TOPICS:\n";
            foreach ($syllabus as $week => $topic) {
                $prompt .= "Week {$week}: {$topic}\n";
            }
        }
        
        if (!empty($remarks)) {
            $prompt .= "\nPROFESSOR REMARKS:\n";
            foreach ($remarks as $remark) {
                $prompt .= "- {$remark['remark_text']}\n";
            }
        }
        
        $prompt .= "\nProvide academic analysis. Return ONLY valid JSON:
{
  \"analysis\": \"2-3 sentence performance summary\",
  \"risk_level\": \"low|medium|high\",
  \"risk_reasoning\": \"why this risk level\",
  \"recommendations\": [\"recommendation1\", \"recommendation2\", \"recommendation3\"],
  \"weak_topics\": [\"topic1\", \"topic2\"]
}

Return ONLY JSON, no markdown.";
        
        return $prompt;
    }
    
    private function createRuleBasedInsights($performance, $syllabus) {
        $avgScore = 0;
        if(!empty($performance)){
            $avgScore = array_sum(array_column($performance, 'score')) / count($performance);
        }
        
        if ($avgScore < 75) {
            $riskLevel = 'high';
            $analysis = 'Your current performance indicates significant challenges with course material. Immediate intervention and additional support are recommended.';
        } elseif ($avgScore < 85) {
            $riskLevel = 'medium';
            $analysis = 'Your performance shows partial mastery of course concepts. Focused study on weak areas will help improve your grades.';
        } else {
            $riskLevel = 'low';
            $analysis = 'Excellent performance! You demonstrate strong understanding of course material. Continue your current study habits.';
        }
        
        return [
            'analysis' => $analysis,
            'risk_level' => $riskLevel,
            'recommendations' => [
                'Review weak topic areas',
                'Attend professor office hours',
                'Form study groups with classmates'
            ],
            'weak_topics' => []
        ];
    }
    
    private function extractTextFromPDF($filePath) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            return $pdf->getText();
        } catch (Exception $e) {
            error_log("PDF parsing failed: " . $e->getMessage());
            return "Unable to extract text from PDF";
        }
    }
    
    private function createDefaultSyllabusStructure() {
        return [
            'objectives' => ['Course objective 1', 'Course objective 2'],
            'weekly_topics' => [
                '1' => 'Introduction',
                '2' => 'Fundamentals',
                '3' => 'Core Concepts',
                '4' => 'Advanced Topics',
                '5' => 'Review'
            ],
            'assessments' => [
                'prelim' => ['class_standing' => 60, 'exam' => 40],
                'midterm' => ['class_standing' => 60, 'exam' => 40],
                'semi_final' => ['class_standing' => 60, 'exam' => 40],
                'final' => ['class_standing' => 60, 'exam' => 40]
            ],
            'outcomes' => ['Outcome 1', 'Outcome 2']
        ];
    }
    
    private function identifyWeakTopics($studentId, $courseId) {
        $performance = $this->calculatePerformance(
            $this->getGradeComponents($studentId, $courseId)
        );
        
        $weakPeriods = array_filter($performance, function($p) {
            return $p['level'] === 'LOW' || $p['level'] === 'AVERAGE';
        });
        
        $syllabus = $this->getSyllabusTopics($courseId);
        $weakTopics = [];
        
        foreach ($weakPeriods as $period => $data) {
            $weekRange = $this->periodToWeekRange($period);
            foreach ($weekRange as $week) {
                if (isset($syllabus[$week])) {
                    $weakTopics[] = $syllabus[$week];
                }
            }
        }
        
        return array_unique($weakTopics);
    }
    
    private function periodToWeekRange($period) {
        $mapping = [
            'prelim' => [1, 2, 3, 4, 5],
            'midterm' => [6, 7, 8, 9],
            'semi_final' => [10, 11, 12, 13],
            'final' => [14, 15, 16, 17, 18]
        ];
        return $mapping[$period] ?? [];
    }
    
    private function getFallbackResources($topics) {
        // Hardcoded quality free resources as fallback
        $resources = [
            [
                'title' => 'freeCodeCamp - Full Programming Course',
                'type' => 'video',
                'url' => 'https://www.youtube.com/@freecodecamp',
                'description' => 'Comprehensive free programming tutorials'
            ],
            [
                'title' => 'W3Schools - Interactive Tutorials',
                'type' => 'tutorial',
                'url' => 'https://www.w3schools.com/',
                'description' => 'Learn by examples with interactive exercises'
            ],
            [
                'title' => 'TutorialsPoint - Programming References',
                'type' => 'article',
                'url' => 'https://www.tutorialspoint.com/',
                'description' => 'Detailed programming guides and references'
            ],
            [
                'title' => 'Codecademy - Free Courses',
                'type' => 'interactive',
                'url' => 'https://www.codecademy.com/catalog/subject/all',
                'description' => 'Interactive coding practice'
            ],
            [
                'title' => 'GitHub Learning Lab',
                'type' => 'practice',
                'url' => 'https://lab.github.com/',
                'description' => 'Hands-on coding challenges'
            ]
        ];
        
        return array_slice($resources, 0, 5);
    }
    
    private function saveInsights($studentId, $courseId, $insights) {
        $this->db->query("
            INSERT INTO ai_insights 
            (student_id, course_id, insight_type, insight_text, confidence_score, generated_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ", [
            $studentId,
            $courseId,
            'performance_trend',
            $insights['analysis'] ?? 'Analysis generated',
            0.85
        ]);
        
        // Save risk alert if high risk
        if (($insights['risk_level'] ?? 'low') === 'high') {
            $this->db->query("
                INSERT INTO ai_insights 
                (student_id, course_id, insight_type, insight_text, confidence_score, generated_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ", [
                $studentId,
                $courseId,
                'risk_alert',
                $insights['risk_reasoning'] ?? 'Performance below expectations',
                0.90
            ]);
        }
    }
    
    // Database helpers (same as before)
    private function getStudentData($studentId) {
        return $this->db->fetchOne("
            SELECT sp.*, u.first_name, u.last_name
            FROM student_profiles sp
            JOIN users u ON sp.user_id = u.id
            WHERE sp.user_id = ?
        ", [$studentId]);
    }
    
    private function getGradeComponents($studentId, $courseId) {
        return $this->db->fetchAll("
            SELECT gc.*
            FROM grade_components gc
            JOIN course_grades cg ON gc.course_grade_id = cg.id
            WHERE cg.student_id = ? AND cg.course_id = ?
        ", [$studentId, $courseId]);
    }
    
    private function getSyllabusTopics($courseId) {
        $syllabus = $this->db->fetchOne("
            SELECT topics FROM course_syllabi WHERE course_id = ?
        ", [$courseId]);

        $topicsJson = $syllabus['topics'] ?? null;

        if (!empty($topicsJson)) {
            $decoded = json_decode($topicsJson, true);
            if ($decoded === null) {
                error_log("Failed to decode syllabus topics for course ID {$courseId}: " . $topicsJson);
                return [];
            }
            return $decoded;
        }

        return [];
    }

    
    private function getCompletedCourses($studentId) {
        return $this->db->fetchAll("
            SELECT c.course_code, c.course_name
            FROM course_enrollments ce
            JOIN courses c ON ce.course_id = c.id
            WHERE ce.student_id = ? AND ce.status = 'completed'
        ", [$studentId]);
    }
    
    private function getAvailableCourses($studentId) {
        return $this->db->fetchAll("
            SELECT * FROM courses 
            WHERE is_active = 1
            AND id NOT IN (
                SELECT course_id FROM course_enrollments 
                WHERE student_id = ?
            )
            ORDER BY level, course_code
            LIMIT 20
        ", [$studentId]);
    }
    
    private function getPerformanceLevel($studentId, $courseId) {
        $components = $this->getGradeComponents($studentId, $courseId);
        $performance = $this->calculatePerformance($components);
        
        if (empty($performance)) return 'AVERAGE';
        
        $levels = array_column($performance, 'level');
        if (in_array('LOW', $levels)) return 'LOW';
        if (in_array('AVERAGE', $levels)) return 'AVERAGE';
        return 'HIGH';
    }
    
    private function enrichRecommendations($aiRecommendations) {
        $enriched = [];
        foreach ($aiRecommendations as $rec) {
            $course = $this->db->fetchOne("
                SELECT * FROM courses WHERE course_code = ?
            ", [$rec['course_code']]);
            
            if ($course) {
                $enriched[] = [
                    'course' => $course,
                    'reason' => $rec['reason'],
                    'score' => $rec['match_score'] ?? 80
                ];
            }
        }
        return $enriched;
    }
    
    private function getRuleBasedRecommendations($studentId) {
        // Simple fallback: get courses by level
        $student = $this->getStudentData($studentId);
        $courses = $this->getAvailableCourses($studentId);
        
        return array_map(function($course) {
            return [
                'course' => $course,
                'reason' => 'Matches your academic level and prerequisites',
                'score' => 75
            ];
        }, array_slice($courses, 0, 5));
    }
    
    private function getDefaultResources($courseId) {
        return $this->getFallbackResources([]);
    }

    public function testConnection() {
        return $this->callGemini("Reply ONLY with: AI connection successful.");
        
    }

    public function setProvider($provider) {
        if (in_array($provider, ['gemini', 'groq'])) {
            $this->provider = $provider;
        }
    }

    private function getProfessorRemarks($studentId, $courseId) {
        return $this->db->fetchAll("
            SELECT remark_text, remark_type
            FROM course_specific_remarks
            WHERE student_id = ? AND course_id = ?
            ORDER BY created_at DESC
            LIMIT 5
        ", [$studentId, $courseId]);
    }


}
?>