<?php
require_once __DIR__ . '/includes/init.php';


echo "Testing AI Connection...<br>";

echo "Gemini Key Loaded: ";
var_dump(getenv('GEMINI_API_KEY'));
echo "<hr>";

try {
    $ai = new AIEngine($db);
    $response = $ai->testConnection();

    echo "<strong>SUCCESS:</strong><br>";
    echo nl2br(htmlspecialchars($response));
} catch (Exception $e) {
    echo "<strong>ERROR:</strong> " . $e->getMessage();
} catch (Exception $e) {
    echo "<pre>";
    echo "ERROR: " . $e->getMessage();
    echo "</pre>";
}

echo "<hr>";
echo "<p>If you see a success message, AI is working correctly!</p>";
?>