<?php
require_once __DIR__ . '/../includes/init.php';
$auth->logout();
redirect(APP_URL . '/main/login.php');
