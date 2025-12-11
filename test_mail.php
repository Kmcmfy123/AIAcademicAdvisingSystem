<?php
require_once __DIR__ . '/Mailer.php';
$config = require __DIR__ . '/config_mail.php';
$mailer = new Mailer($config);

if ($mailer->send('your-other-email@gmail.com', 'Test User', 'Test Email', 'This is a test email')) {
    echo "Email sent successfully!";
} else {
    echo "Failed to send email. Check SMTP settings.";
}
