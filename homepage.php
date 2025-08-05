<?php
session_start();
include("connect.php");
// Fonction pour récupérer les prochains événements
// Ensure user is logged in and set userId
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 0;
function getUpcomingEvents($conn, $limit = 3) {
    $today = date('Y-m-d H:i:s');
    $query = "SELECT e.*, c.name as club_name FROM events e 
              INNER JOIN clubs c ON e.clubid = c.id 
              WHERE e.event_date >= ? 
              AND (e.is_approved = 1 OR e.is_approved IS NULL)
              ORDER BY e.event_date ASC 
              LIMIT ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "si", $today, $limit);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

// Count notifications
$notifQuery = "SELECT COUNT(*) as count FROM notifications WHERE userid = ? AND is_read = 0";
$notifStmt = mysqli_prepare($conn, $notifQuery);
mysqli_stmt_bind_param($notifStmt, "i", $userId);
mysqli_stmt_execute($notifStmt);
$notifResult = mysqli_stmt_get_result($notifStmt);
$notifData = mysqli_fetch_assoc($notifResult);
$notifCount = $notifData['count'];

// Récupérer les prochains événements
$upcomingEvents = getUpcomingEvents($conn);
define('ROLE_CHARGE_CLUB', 3);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Universitaire - Accueil</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="homepage_styles.css">
    <link rel="stylesheet" href="events/events_styles.css">
    <script src="notifications/notification_script.js"></script>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="navbar-container">
            <div class="logo">
                <a href="../homepage.php"><i class="fas fa-users"></i> UniClub</a>
            </div>
            <ul class="nav-links">
                <li><a href="homepage.php" class="active">Accueil</a></li>
                <li><a href="events/events.php" >Événements</a></li>
                <?php if ($userRole >= 3): ?>
                <li><a href="events/approbation_events.php">Approbation des événements</a></li>
                <li><a href="events/suivi_events.php">Planification & Suivi</a></li>
                <?php endif; ?>
                <li><a href="members/liste_membres.php">Membres</a></li>
                <li><a href="clubs/clubspage.php">Clubs</a></li>
            </ul>
            <div class="nav-icons">
                <div class="notification-icon">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge"><?php echo $notifCount; ?></span>
                </div>
                <div class="user-profile">
                    <a href="user_profile/userProfile.php">
                        <img src="https://th.bing.com/th/id/OIP.GqGVPkLpUlSo5SmeDogUdwHaHa?rs=1&pid=ImgDetMain" alt="Profile">
                    </a>
                </div>
            </div>
            <div class="hamburger">
                <i class="fas fa-bars"></i>
            </div>
        </div>
    </nav>

    <!-- Notification Dropdown -->
    <div class="notification-dropdown">
        <div class="notification-header">
            <h3>Notifications</h3>
            <span class="mark-all-read">Tout marquer comme lu</span>
        </div>
        <div class="notification-list">
            <?php
            // Fetch actual notifications
            $notifListQuery = "SELECT * FROM notifications WHERE userid = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5";
            $notifListStmt = mysqli_prepare($conn, $notifListQuery);
            mysqli_stmt_bind_param($notifListStmt, "i", $userId);
            mysqli_stmt_execute($notifListStmt);
            $notifications = mysqli_stmt_get_result($notifListStmt);
            
            if (mysqli_num_rows($notifications) > 0) {
                while ($notification = mysqli_fetch_assoc($notifications)) {
                    echo '<div class="notification-item unread">';
                    echo '<div class="notification-icon">';
                    
                    // Choose icon based on notification type
                    if (strpos($notification['type'], 'event') !== false) {
                        echo '<i class="fas fa-calendar-check"></i>';
                    } elseif (strpos($notification['type'], 'club') !== false) {
                        echo '<i class="fas fa-users"></i>';
                    } else {
                        echo '<i class="fas fa-bell"></i>';
                    }
                    
                    echo '</div>';
                    echo '<div class="notification-content">';
                    echo '<p>' . htmlspecialchars($notification['message']) . '</p>';
                    
                    // Calculate time difference
                    $notifDate = new DateTime($notification['created_at']);
                    $now = new DateTime();
                    $interval = $now->diff($notifDate);
                    
                    if ($interval->d > 0) {
                        $timeAgo = 'Il y a ' . $interval->d . ' jour' . ($interval->d > 1 ? 's' : '');
                    } elseif ($interval->h > 0) {
                        $timeAgo = 'Il y a ' . $interval->h . ' heure' . ($interval->h > 1 ? 's' : '');
                    } else {
                        $timeAgo = 'Il y a ' . $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
                    }
                    
                    echo '<span class="notification-time">' . $timeAgo . '</span>';
                    echo '</div>';
                    echo '</div>';
                }
            } else {
                echo '<div class="no-notifications">Aucune notification à afficher</div>';
            }
            ?>
        </div>
        <div class="notification-footer">
            <a href="notifications/all_notifications.php">Voir toutes les notifications</a>
        </div>
    </div>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Bienvenue au Club Universitaire</h1>
            <p>Rejoignez notre communauté dynamique et participez à des événements passionnants!</p>
            <div class="hero-buttons">
                <a href="logout.php" target="_self" class="btn btn-primary"> LogOut</a>
                <a href="events/events.php" class="btn btn-secondary">Voir les Événements</a>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="container">
        <section class="upcoming-events">
            <h2>Prochains Événements</h2>
            <div class="events-grid">
                <?php if (mysqli_num_rows($upcomingEvents) > 0): ?>
                    <?php while ($event = mysqli_fetch_assoc($upcomingEvents)): 
                        $eventDate = new DateTime($event['event_date']);
                    ?>
                    <div class="event-card">
                        <div class="event-date">
                            <span class="day"><?php echo $eventDate->format('d'); ?></span>
                            <span class="month"><?php echo $eventDate->format('M'); ?></span>
                        </div>
                        <div class="event-info">
                            <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                            <p><?php echo htmlspecialchars(substr($event['description'], 0, 120)) . (strlen($event['description']) > 120 ? '...' : ''); ?></p>
                            <a href="events/events.php?id=<?php echo $event['id']; ?>" class="event-link">En savoir plus</a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-events">
                        <p>Aucun événement à venir n'est programmé pour le moment.</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="view-all-events">
                <a href="events/events.php" class="btn btn-outline">Voir tous les événements</a>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>À propos</h3>
                <p>Le Club Universitaire est une organisation étudiante dédiée à créer une communauté dynamique sur le campus.</p>
            </div>
            <div class="footer-section">
                <h3>Liens Rapides</h3>
                <ul>
                    <li><a href="homepage.php">Accueil</a></li>
                    <li><a href="events/events.php">Événements</a></li>
                    <li><a href="#">Adhésion</a></li>
                    <li><a href="#">Contact</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Contact</h3>
                <p><i class="fas fa-envelope"></i> contact@universityclub.edu</p>
                <p><i class="fas fa-phone"></i> +123 456 7890</p>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 Club Universitaire. Tous droits réservés.</p>
        </div>
    </footer>


    <script src="homepage_script.js"></script>
</body>
</html>