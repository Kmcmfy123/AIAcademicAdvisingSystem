<?php
// AI Configuration
return [
    'gemini' => [
        'api_key' => getenv('GEMINI_API_KEY'),
        'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent',
        'model' => 'gemini-1.5-flash',
        'rate_limit' => 60 // per minute
    ],
    
    'groq' => [
        'api_key' => getenv('GROQ_API_KEY'),
        'endpoint' => 'https://api.groq.com/openai/v1/chat/completions',
        'model' => 'llama-3.1-70b-versatile',
        'rate_limit' => 30 // per minute
    ],
    
    'cache_enabled' => true,
    'cache_duration' => 3600, // 1 hour
    'fallback_enabled' => true
];
?>