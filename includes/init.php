<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

//PHP Mailer
require __DIR__ . '/../vendor/autoload.php'; // load Composer autoloader
require __DIR__ . '/../mailer.php';          // your Mailer class


require_once __DIR__ . '/config.php';

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