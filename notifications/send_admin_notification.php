<?php
require_once '../connect.php';
require_once 'notifications.php';
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 4) {
    header("Location: homepage.php");
    exit;
}

// Validate form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    if (empty($_POST['message']) || empty($_POST['type'])) {
        header("Location: admin_notifications.php?error=missing_fields");
        exit;
    }
    
    $message = $_POST['message'];
    $type = $_POST['type'];
    
    // Validate notification type
    $validTypes = ['SYSTEM', 'EVENT', 'CLUB'];
    if (!in_array($type, $validTypes)) {
        header("Location: admin_notifications.php?error=invalid_type");
        exit;
    }
    
    // Send notification to all users
    if (sendNotificationToAllUsers($conn, $message, $type)) {
        header("Location: admin_notifications.php?success=1");
    } else {
        header("Location: admin_notifications.php?error=creation_failed");
    }
    exit;
} else {
    // If not a POST request, redirect to the form
    header("Location: admin_notifications.php");
    exit;
}
?>