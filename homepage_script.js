document.addEventListener('DOMContentLoaded', function() {
    // Notification Icon Functionality
    const notificationIcon = document.querySelector('.notification-icon');
    const notificationDropdown = document.querySelector('.notification-dropdown');
    
    notificationIcon.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationDropdown.classList.toggle('active');
    });
    
    // Close notification dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!notificationDropdown.contains(e.target) && e.target !== notificationIcon && !notificationIcon.contains(e.target)) {
            notificationDropdown.classList.remove('active');
        }
    });
    
    // Mark notifications as read when clicked
    const notificationItems = document.querySelectorAll('.notification-item');
    const notificationBadge = document.querySelector('.notification-badge');
    
    notificationItems.forEach(item => {
        item.addEventListener('click', function() {
            if (this.classList.contains('unread')) {
                this.classList.remove('unread');
                updateNotificationBadge();
            }
        });
    });
    
    // Mark all as read
    const markAllRead = document.querySelector('.mark-all-read');
    if (markAllRead) {
        markAllRead.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationItems.forEach(item => {
                item.classList.remove('unread');
            });
            updateNotificationBadge();
        });
    }
    
    function updateNotificationBadge() {
        const unreadCount = document.querySelectorAll('.notification-item.unread').length;
        notificationBadge.textContent = unreadCount;
        
        if (unreadCount === 0) {
            notificationBadge.style.display = 'none';
        } else {
            notificationBadge.style.display = 'flex';
        }
    }
    
    // Mobile menu toggle (placeholder for future functionality)
    const hamburger = document.querySelector('.hamburger');
    const navLinks = document.querySelector('.nav-links');
    
    hamburger.addEventListener('click', function() {
        // This would toggle a mobile menu in a real implementation
        console.log('Mobile menu would open here');
        // navLinks.classList.toggle('active');
    });
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 70,
                    behavior: 'smooth'
                });
            }
        });
    });
});