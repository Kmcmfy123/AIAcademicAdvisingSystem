<?php
require_once __DIR__ . '/includes/init.php';

if ($auth->isLoggedIn()) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'student':
            redirect(APP_URL . '/main/student/dashboard.php');
            break;
        case 'professor':
            redirect(APP_URL . '/main/professor/dashboard.php');
            break;
        case 'admin':
            redirect(APP_URL . '/main/admin/dashboard.php');
            break;
    }
} else {
    redirect(APP_URL . '/main/login.php');
}