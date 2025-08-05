document.addEventListener('DOMContentLoaded', function() {
    // Initial check for notifications
    checkForNewNotifications();
    
    // Set up automatic notification checks every minute
    setInterval(checkForNewNotifications, 60000);
    
    // Function to check for new notifications
    function checkForNewNotifications() {
        fetch('get_notification_count.php')
            .then(response => response.json())
            .then(data => {
                const badge = document.querySelector('.notification-badge');
                if (badge) {
                    if (data.count > 0) {
                        badge.textContent = data.count;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            })
            .catch(error => console.error('Error checking notifications:', error));
    }
    
    // Toggle notification dropdown when clicking the icon
    const notificationIcon = document.querySelector('.notification-icon');
    const notificationDropdown = document.querySelector('.notification-dropdown');
    
    if (notificationIcon && notificationDropdown) {
        notificationIcon.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('active');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!notificationIcon.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.remove('active');
            }
        });
    }
    
    // Mark notification as read when clicked
    document.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function() {
            const notificationId = this.dataset.id;
            
            fetch('mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'notification_id=' + notificationId + '&action=mark_read'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    this.classList.remove('unread');
                    // Update badge count
                    checkForNewNotifications();
                }
            })
            .catch(error => console.error('Error marking notification as read:', error));
        });
    });
    
    // Mark all as read
    const markAllReadButton = document.querySelector('.mark-all-read');
    if (markAllReadButton) {
        markAllReadButton.addEventListener('click', function() {
            const userId = this.dataset.userid;
            
            fetch('mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'user_id=' + userId + '&action=mark_all_read'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    // Update badge count
                    checkForNewNotifications();
                }
            })
            .catch(error => console.error('Error marking all notifications as read:', error));
        });
    }
});