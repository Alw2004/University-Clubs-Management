<?php
include("../connect.php");

// Function to create a new notification
function createNotification($conn, $userId, $message, $type) {
    $sql = "INSERT INTO notifications (userid, message, type) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $userId, $message, $type);
    return $stmt->execute();
}

// Function to mark notification as read
function markNotificationAsRead($conn, $notificationId) {
    $sql = "UPDATE notifications SET is_read = 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $notificationId);
    return $stmt->execute();
}

// Function to mark all notifications as read for a user
function markAllNotificationsAsRead($conn, $userId) {
    $sql = "UPDATE notifications SET is_read = 1 WHERE userid = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    return $stmt->execute();
}

// Function to get all notifications for a user
function getUserNotifications($conn, $userId, $limit = 10) {
    $sql = "SELECT * FROM notifications WHERE userid = ? ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    return $notifications;
}

// Function to count unread notifications
function countUnreadNotifications($conn, $userId) {
    $sql = "SELECT COUNT(*) as count FROM notifications WHERE userid = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'];
}

// Function to delete old notifications (e.g., older than 30 days)
function deleteOldNotifications($conn, $days = 30) {
    $sql = "DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $days);
    return $stmt->execute();
}

// Function to delete a specific notification
function deleteNotification($conn, $notificationId) {
    $sql = "DELETE FROM notifications WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $notificationId);
    return $stmt->execute();
}

/**
 * Get the total number of notifications for a user
 * 
 * @param mysqli $conn The database connection
 * @param int $userId The user ID
 * @return int The total number of notifications
 */
function getTotalNotificationCount($conn, $userId) {
    // Prepare the query to count all notifications for the user
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications WHERE userid = ?");
    
    // Bind the user ID parameter
    $stmt->bind_param("i", $userId);
    
    // Execute the query
    $stmt->execute();
    
    // Get the result
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return (int)$row['total'];
    }
    
    // Return 0 if no results found
    return 0;
}

// Function to display notification badge and dropdown
function renderNotificationArea($conn, $userId) {
    $notifications = getUserNotifications($conn, $userId, 5);
    $unreadCount = countUnreadNotifications($conn, $userId);
    
    ob_start();
    ?>
    <div class="notification-container">
        <div class="notification-icon">
            <i class="fas fa-bell"></i>
            <?php if ($unreadCount > 0): ?>
            <span class="notification-badge"><?php echo $unreadCount; ?></span>
            <?php endif; ?>
        </div>
        
        <div class="notification-dropdown">
            <div class="notification-header">
                <h3>Notifications</h3>
                <?php if ($unreadCount > 0): ?>
                <button class="mark-all-read" data-userid="<?php echo $userId; ?>">Mark all as read</button>
                <?php endif; ?>
            </div>
            
            <div class="notification-list">
                <?php if (empty($notifications)): ?>
                <div class="no-notifications">
                    <p>No notifications yet</p>
                </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>" 
                         data-id="<?php echo $notification['id']; ?>">
                        <div class="notification-icon-type 
                            <?php echo strtolower($notification['type']); ?>">
                            <?php if ($notification['type'] == 'EVENT'): ?>
                                <i class="fas fa-calendar"></i>
                            <?php elseif ($notification['type'] == 'CLUB'): ?>
                                <i class="fas fa-users"></i>
                            <?php elseif ($notification['type'] == 'SYSTEM'): ?>
                                <i class="fas fa-cog"></i>
                            <?php endif; ?>
                        </div>
                        <div class="notification-content">
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <span class="notification-time">
                                <?php echo date('M d, H:i', strtotime($notification['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="notification-footer">
                <a href="all_notifications.php">View all notifications</a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
// Function to send notification to all users
function sendNotificationToAllUsers($conn, $message, $type) {
    // Get all users
    $userStmt = $conn->prepare("SELECT id FROM users");
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    
    $success = true;
    
    // Create a notification for each user
    while ($user = $userResult->fetch_assoc()) {
        if (!createNotification($conn, $user['id'], $message, $type)) {
            $success = false;
        }
    }
    
    return $success;
}
?>
<?php
session_start();
include '../connect.php'; // Database connection file

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user's notifications
$stmt = $conn->prepare("SELECT message, type, is_read, created_at FROM notifications WHERE userid = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$notifications = $result->fetch_all(MYSQLI_ASSOC);
?>

