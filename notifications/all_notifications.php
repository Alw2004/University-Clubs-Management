<?php
include("../connect.php");
include("notifications.php");


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Get current page from URL parameter
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10; // Number of notifications per page
$offset = ($page - 1) * $per_page;

// Get paginated notifications for the user
$query = "SELECT * FROM notifications WHERE userid = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $_SESSION['user_id'], $per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];

while($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toutes les Notifications | UniClub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="notification_style.css">
    <script src="notification_script.js"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-header h1 {
            margin: 0;
            font-size: 1.8rem;
            color: #333;
        }
        
        .page-header .btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .page-header .btn:hover {
            background-color: #0069d9;
        }
        
        .notifications-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #007bff;
            text-decoration: none;
        }
        
        .back-link i {
            margin-right: 5px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            transition: background-color 0.2s ease;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item.unread {
            background-color: #f0f7ff;
        }
        
        .notification-icon-type {
            margin-right: 15px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .notification-icon-type.event {
            background-color: #28a745;
        }
        
        .notification-icon-type.club {
            background-color: #fd7e14;
        }
        
        .notification-icon-type.system {
            background-color: #6c757d;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-content p {
            margin: 0 0 5px 0;
            font-size: 0.95rem;
        }
        
        .notification-time {
            display: block;
            margin-top: 5px;
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .no-notifications {
            padding: 40px 20px;
            text-align: center;
            color: #6c757d;
        }
        
        .notification-actions {
            margin-left: 10px;
        }
        
        .notification-actions button {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 5px;
            font-size: 0.9rem;
        }
        
        .notification-actions button:hover {
            color: #dc3545;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        
        .pagination a {
            padding: 8px 12px;
            margin: 0 5px;
            border-radius: 4px;
            background-color: #f8f9fa;
            color: #007bff;
            text-decoration: none;
        }
        
        .pagination a.active {
            background-color: #007bff;
            color: white;
        }
        
        .pagination a:hover:not(.active) {
            background-color: #e9ecef;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="../homepage.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Retour à l'Accueil
        </a>
        
        <div class="page-header">
            <h1>Toutes les Notifications</h1>
            <?php if (!empty($notifications) && countUnreadNotifications($conn, $_SESSION['user_id']) > 0): ?>
            <button class="btn mark-all-read" data-userid="<?php echo $_SESSION['user_id']; ?>">
                Tout marquer comme lu
            </button>
            <?php endif; ?>
        </div>
        
        <div class="notifications-container">
            <?php if (empty($notifications)): ?>
            <div class="no-notifications">
                <p>Vous n'avez pas de notifications</p>
            </div>
            <?php else: ?>
                <div class="notification-list">
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
                                <?php echo date('d F Y \à H:i', strtotime($notification['created_at'])); ?>
                            </span>
                        </div>
                        <div class="notification-actions">
                            <?php if (!$notification['is_read']): ?>
                            <button class="mark-read" data-id="<?php echo $notification['id']; ?>" title="Marquer comme lu">
                                <i class="fas fa-check"></i>
                            </button>
                            <?php endif; ?>
                            <button class="delete-notification" data-id="<?php echo $notification['id']; ?>" title="Supprimer">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php 
                // Pagination
                $total_notifications = getTotalNotificationCount($conn, $_SESSION['user_id']);
                $total_pages = ceil($total_notifications / $per_page);
                
                if ($total_pages > 1): 
                ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>">&laquo; Précédent</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" <?php echo $i == $page ? 'class="active"' : ''; ?>>
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>">Suivant &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script>
    $(document).ready(function() {
        // Mark individual notification as read
        $('.mark-read').on('click', function() {
            var notificationId = $(this).data('id');
            var notificationItem = $(this).closest('.notification-item');
            
            $.ajax({
                url: 'mark_notification_read.php',
                type: 'POST',
                data: {
                    notification_id: notificationId,
                    action: 'mark_read'
                },
                success: function(response) {
                    try {
                        var result = JSON.parse(response);
                        if (result.success) {
                            // Update UI
                            notificationItem.removeClass('unread');
                            notificationItem.find('.mark-read').remove();
                        }
                    } catch(e) {
                        console.error("Error parsing response: " + e);
                    }
                }
            });
        });
        
        // Mark all notifications as read
        $('.mark-all-read').on('click', function() {
            var userId = $(this).data('userid');
            
            $.ajax({
                url: 'mark_notification_read.php',
                type: 'POST',
                data: {
                    user_id: userId,
                    action: 'mark_all_read'
                },
                success: function(response) {
                    try {
                        var result = JSON.parse(response);
                        if (result.success) {
                            // Update UI
                            $('.notification-item').removeClass('unread');
                            $('.mark-read').remove();
                            $('.mark-all-read').hide();
                        }
                    } catch(e) {
                        console.error("Error parsing response: " + e);
                    }
                }
            });
        });
        
        // Delete notification
        $('.delete-notification').on('click', function() {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cette notification?')) {
                return;
            }
            
            var notificationId = $(this).data('id');
            var notificationItem = $(this).closest('.notification-item');
            
            $.ajax({
                url: 'delete_notification.php',
                type: 'POST',
                data: {
                    notification_id: notificationId
                },
                success: function(response) {
                    try {
                        var result = JSON.parse(response);
                        if (result.success) {
                            // Remove from UI with animation
                            notificationItem.fadeOut(300, function() {
                                $(this).remove();
                                
                                // Check if we have any notifications left
                                if ($('.notification-item').length === 0) {
                                    $('.notification-list').html(
                                        '<div class="no-notifications"><p>Vous n\'avez pas de notifications</p></div>'
                                    );
                                }
                            });
                        }
                    } catch(e) {
                        console.error("Error parsing response: " + e);
                    }
                }
            });
        });
    });
    </script>
</body>
</html>