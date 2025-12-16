<?php
function getAIStatus() {
    $geminiKey = getenv('GEMINI_API_KEY');
    $groqKey = getenv('GROQ_API_KEY');
    
    if (!empty($geminiKey) && $geminiKey !== 'YOUR_GEMINI_KEY') {
        return ['status' => 'active', 'provider' => 'Google Gemini', 'color' => 'success'];
    } elseif (!empty($groqKey) && $groqKey !== 'YOUR_GROQ_KEY') {
        return ['status' => 'active', 'provider' => 'Groq', 'color' => 'success'];
    } else {
        return ['status' => 'inactive', 'provider' => 'None', 'color' => 'danger'];
    }
}


?>