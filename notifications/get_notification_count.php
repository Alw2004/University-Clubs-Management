<?php
session_start();
include("../connect.php");
include("notifications.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

$count = countUnreadNotifications($conn, $_SESSION['user_id']);
echo json_encode(['count' => $count]);
?>