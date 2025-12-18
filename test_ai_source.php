<?php
/**
 * Test AI Connection and Source Detection
 */

require_once __DIR__ . '/includes/init.php';

echo "=== AI Connection Test ===\n\n";

// Load environment variables
$envFile = __DIR__ . '/includes/api.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        putenv(trim($line));
    }
}

$geminiKey = getenv('GEMINI_API_KEY');
$groqKey = getenv('GROQ_API_KEY');

echo "1. Checking API Keys:\n";
echo "   GEMINI_API_KEY: " . ($geminiKey ? "✓ Set (" . substr($geminiKey, 0, 20) . "...)" : "✗ Not set") . "\n";
echo "   GROQ_API_KEY: " . ($groqKey ? "✓ Set (" . substr($groqKey, 0, 20) . "...)" : "✗ Not set") . "\n\n";

// Test AI Engine
try {
    echo "2. Testing AI Engine:\n";
    $aiEngine = new AIEngine($db);
    echo "   ✓ AIEngine initialized\n\n";
    
    echo "3. Testing Gemini Connection:\n";
    try {
        $response = $aiEngine->testConnection();
        echo "   ✓ Gemini Response: " . substr($response, 0, 100) . "\n\n";
    } catch (Exception $e) {
        echo "   ✗ Gemini Failed: " . $e->getMessage() . "\n\n";
    }
    
    echo "4. Testing Student Performance Analysis (if student exists):\n";
    // Get a test student
    $testStudent = $db->fetchOne("SELECT id FROM users WHERE role = 'student' LIMIT 1");
    
    if ($testStudent) {
        $studentId = $testStudent['id'];
        echo "   Testing with Student ID: {$studentId}\n";
        
        // Get a course enrollment
        $enrollment = $db->fetchOne("
            SELECT course_id 
            FROM course_enrollments 
            WHERE student_id = ? 
            LIMIT 1
        ", [$studentId]);
        
        if ($enrollment) {
            $courseId = $enrollment['course_id'];
            echo "   Testing with Course ID: {$courseId}\n";
            
            $insights = $aiEngine->analyzeStudentPerformance($studentId, $courseId);
            
            echo "\n   === INSIGHT SOURCE ===\n";
            echo "   Source: " . ($insights['_source'] ?? 'unknown') . "\n";
            echo "   Provider: " . ($insights['_provider'] ?? 'none') . "\n";
            echo "   Risk Level: " . ($insights['risk_level'] ?? 'unknown') . "\n";
            echo "   Analysis: " . substr($insights['analysis'] ?? 'none', 0, 150) . "...\n\n";
            
            // Check database
            echo "5. Checking Latest Database Entry:\n";
            $latestInsight = $db->fetchOne("
                SELECT insight_text, generated_at 
                FROM ai_insights 
                WHERE student_id = ? 
                ORDER BY generated_at DESC 
                LIMIT 1
            ", [$studentId]);
            
            if ($latestInsight) {
                echo "   Generated at: " . $latestInsight['generated_at'] . "\n";
                $text = $latestInsight['insight_text'];
                
                // Extract source tag
                if (preg_match('/\[source: ([^\]]+)\]/', $text, $matches)) {
                    $sourceTag = $matches[1];
                    echo "   Database Source Tag: " . $sourceTag . "\n\n";
                    
                    if (strpos($sourceTag, 'AI-') === 0) {
                        echo "   🎉 SUCCESS! Using real AI: {$sourceTag}\n";
                    } else if ($sourceTag === 'RULE') {
                        echo "   ⚠️  Using hardcoded fallback (RULE)\n";
                        echo "   This means AI call failed. Check:\n";
                        echo "   - Network connectivity\n";
                        echo "   - API key validity\n";
                        echo "   - Rate limits\n";
                    }
                } else {
                    echo "   ⚠️  No source tag found in database text\n";
                }
            } else {
                echo "   No insights found in database\n";
            }
        } else {
            echo "   No course enrollment found for test\n";
        }
    } else {
        echo "   No student found for testing\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
?>
