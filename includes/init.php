<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load environment variables
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        return;
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        $parts = explode('=', $line, 2);
        if (count($parts) < 2) {
            continue;
        }
        
        $name = trim($parts[0]);
        $value = trim($parts[1]);
        
        putenv("$name=$value");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

loadEnv(__DIR__ . '/api.env');


//PHP Mailer
require __DIR__ . '/../vendor/autoload.php'; // load Composer autoloader
require __DIR__ . '/../mailer.php';          // your Mailer class

require_once __DIR__ . '/config.php';

require_once __DIR__ . '/AIEngine.php';

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/functions.php';

if (file_exists(__DIR__ . '/../models/Student.php')) {
    require_once __DIR__ . '/../models/Student.php';
}
if (file_exists(__DIR__ . '/../models/Course.php')) {
    require_once __DIR__ . '/../models/Course.php';
}
if (file_exists(__DIR__ . '/../models/advisingSession.php')) {
    require_once __DIR__ . '/../models/advisingSession.php';
}

$db = Database::getInstance();

$auth = new Auth();