<?php
require_once __DIR__ . '/../../includes/init.php';

// Initialize AI engine
$ai = new AIEngine($db);
$ai->setProvider('gemini'); 
// Test course ID and PDF file path
$courseId = 1; // replace with an actual course ID in your DB
$syllabusFilePath = __DIR__ . '/693ea03f3811d_INP123_CourseSyllabus__1_.pdf'; // correct path

// Step 1: Make sure file exists
$syllabusFilePath = realpath(__DIR__ . '/693ea03f3811d_INP123_CourseSyllabus__1_.pdf');

if (!$syllabusFilePath || !file_exists($syllabusFilePath)) {
    die("PDF file not found at: " . __DIR__);
} else {
    echo "PDF found: " . $syllabusFilePath;
}


// Step 2: Test AI connection
try {
    echo $ai->testConnection();
} catch (Exception $e) {
    die("AI connection failed: " . $e->getMessage());
}

// Step 3: Analyze syllabus
try {
    $analysis = $ai->analyzeSyllabus($courseId, $syllabusFilePath);
    echo "<pre>";
    print_r($analysis);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error analyzing syllabus: " . $e->getMessage();
}
