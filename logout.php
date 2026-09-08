<?php
session_start();
require_once 'db.php';
require_once 'auth.php';

if (isset($_SESSION['user_id'])) {
    // Log Logout Event
    logUserAction($pdo, $_SESSION['user_id'], $_SESSION['username'], 'LOGOUT');
}

session_unset();
session_destroy();
header("Location: index.php");
exit;