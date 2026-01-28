<?php
// Central mail configuration. Prefer env vars; fall back to repo defaults.
return [
    'host'        => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
    'username'    => getenv('SMTP_USERNAME') ?: 'amvgdrive2025@gmail.com',
    'password'    => getenv('SMTP_PASSWORD') ?: '', // 16-char App Password
    'port'        => (int)(getenv('SMTP_PORT') ?: 587),
    'encryption'  => getenv('SMTP_ENCRYPTION') ?: 'tls',
    'from_email'  => getenv('SMTP_FROM_EMAIL') ?: 'amvgdrive2025@gmail.com',
    'from_name'   => getenv('SMTP_FROM_NAME') ?: 'Your System Name',
];
