<?php
require_once 'config/main_config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Since chatbot conversations have been removed, redirect to dashboard
header('Location: dashboard.php');
exit();
?>