<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'advising_system');
define('DB_USER', 'root');
define('DB_PASS', 'password_123');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'Academic Advising System');
define('APP_URL', 'http://localhost/AcademicAdvising');
define('BASE_PATH', __DIR__ . '/../');
define('PUBLIC_PATH', BASE_PATH . 'main/');

define('ASSETS_URL', APP_URL . '/main/assets');

define('SESSION_LIFETIME', 3600);
define('CSRF_TOKEN_EXPIRE', 3600);


define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_FROM', 'noreply@university.edu');

define('UPLOAD_DIR', __DIR__ . '/../main/assets/uploads/');
define('MAX_FILE_SIZE', 5242880);

define('RECORDS_PER_PAGE', 20);

error_reporting(E_ALL);
ini_set('display_errors', 1);
