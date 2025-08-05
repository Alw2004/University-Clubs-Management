<?php
session_start();
include("../connect.php");
include("notifications.php");

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

if (isset($_POST['notification_id'])) {
    $notificationId = (int)$_POST['notification_id'];
    
    // Verify the notification belongs to this user
    $checkQuery = "SELECT id FROM notifications WHERE id = ? AND userid = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("ii", $notificationId, $_SESSION['user_id']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Notification not found or no permission']);
        exit;
    }
    
    $success = deleteNotification($conn, $notificationId);
    echo json_encode(['success' => $success]);
} else {
    echo json_encode(['success' => false, 'message' => 'No notification ID provided']);
}
?>