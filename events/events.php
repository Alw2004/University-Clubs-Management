<?php
session_start();
include("../connect.php");

// Ensure user is logged in and set userId
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 0;

// Définir les constantes pour les rôles
define('ROLE_MEMBRE', 1);
define('ROLE_CHARGE_CLUB', 3);
define('ROLE_ADMIN', 4);
// Function to get all events
// Function to get all events (updated)
function getAllEvents($conn) {
    $query = "SELECT e.*, c.name as club_name FROM events e 
              INNER JOIN clubs c ON e.clubid = c.id 
              WHERE e.is_approved = 1 OR e.is_approved IS NULL 
              ORDER BY e.event_date ASC";
    $result = mysqli_query($conn, $query);
    return $result;
}

// Function to get events for a specific club (updated)
function getClubEvents($conn, $clubId) {
    $query = "SELECT e.*, c.name as club_name FROM events e 
              INNER JOIN clubs c ON e.clubid = c.id 
              WHERE e.clubid = ? 
              AND (e.is_approved = 1 OR e.is_approved IS NULL)
              ORDER BY e.event_date ASC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $clubId);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

// Function to check if user is registered for an event
function isUserRegistered($conn, $eventId, $userId) {
    $query = "SELECT * FROM event_participants WHERE eventid = ? AND userid = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $eventId, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_num_rows($result) > 0;
}

// Function to get clubs where user is responsible
function getUserClubs($conn, $userId) {
    $query = "SELECT c.* FROM clubs c 
              WHERE c.resId = ? 
              ORDER BY c.name ASC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

// Function to check if user is responsible for a club
function isClubResponsible($conn, $userId, $clubId) {
    $query = "SELECT * FROM clubs WHERE id = ? AND resId = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $clubId, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_num_rows($result) > 0;
}

// Process event registration
if (isset($_POST['register_event'])) {
    $eventId = $_POST['event_id'];
    
    // Check if already registered
    if (!isUserRegistered($conn, $eventId, $userId)) {
        $query = "INSERT INTO event_participants (eventid, userid, notified, is_attending) VALUES (?, ?, 0, 1)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $eventId, $userId);
        
        if (mysqli_stmt_execute($stmt)) {
            // Create notification for the club responsible
            $eventQuery = "SELECT clubid FROM events WHERE id = ?";
            $eventStmt = mysqli_prepare($conn, $eventQuery);
            mysqli_stmt_bind_param($eventStmt, "i", $eventId);
            mysqli_stmt_execute($eventStmt);
            $eventResult = mysqli_stmt_get_result($eventStmt);
            $eventData = mysqli_fetch_assoc($eventResult);
            
            $clubId = $eventData['clubid'];
            
            $clubQuery = "SELECT resId FROM clubs WHERE id = ?";
            $clubStmt = mysqli_prepare($conn, $clubQuery);
            mysqli_stmt_bind_param($clubStmt, "i", $clubId);
            mysqli_stmt_execute($clubStmt);
            $clubResult = mysqli_stmt_get_result($clubStmt);
            $clubData = mysqli_fetch_assoc($clubResult);
            
            $resId = $clubData['resId'];
            
            $userQuery = "SELECT name FROM users WHERE id = ?";
            $userStmt = mysqli_prepare($conn, $userQuery);
            mysqli_stmt_bind_param($userStmt, "i", $userId);
            mysqli_stmt_execute($userStmt);
            $userResult = mysqli_stmt_get_result($userStmt);
            $userData = mysqli_fetch_assoc($userResult);
            
            $userName = $userData['name'];
            
            $message = $userName . " s'est inscrit à votre événement.";
            $notifQuery = "INSERT INTO notifications (userid, message, type, is_read) VALUES (?, ?, 'event_registration', 0)";
            $notifStmt = mysqli_prepare($conn, $notifQuery);
            mysqli_stmt_bind_param($notifStmt, "is", $resId, $message);
            mysqli_stmt_execute($notifStmt);
            
            $success = "Inscription réussie à l'événement!";
        } else {
            $error = "Erreur lors de l'inscription: " . mysqli_error($conn);
        }
    } else {
        $error = "Vous êtes déjà inscrit à cet événement.";
    }
}

// Process event cancellation
if (isset($_POST['cancel_registration'])) {
    $eventId = $_POST['event_id'];
    
    $query = "DELETE FROM event_participants WHERE eventid = ? AND userid = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $eventId, $userId);
    
    if (mysqli_stmt_execute($stmt)) {
        $success = "Inscription annulée avec succès!";
    } else {
        $error = "Erreur lors de l'annulation: " . mysqli_error($conn);
    }
}

// Get all events or filter by club if specified
$clubFilter = isset($_GET['club']) ? $_GET['club'] : null;

if ($clubFilter) {
    $events = getClubEvents($conn, $clubFilter);
} else {
    $events = getAllEvents($conn);
}

// Get user's clubs if they are a responsible or admin
$userClubs = null;
if ($userRole >= ROLE_CHARGE_CLUB) {
    $userClubs = getUserClubs($conn, $userId);
}

// Count notifications
$notifQuery = "SELECT COUNT(*) as count FROM notifications WHERE userid = ? AND is_read = 0";
$notifStmt = mysqli_prepare($conn, $notifQuery);
mysqli_stmt_bind_param($notifStmt, "i", $userId);
mysqli_stmt_execute($notifStmt);
$notifResult = mysqli_stmt_get_result($notifStmt);
$notifData = mysqli_fetch_assoc($notifResult);
$notifCount = $notifData['count'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Événements Universitaires</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../homepage_styles.css">
    <link rel="stylesheet" href="events_styles.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="navbar-container">
            <div class="logo">
                <a href="../homepage.php"><i class="fas fa-users"></i> UniClub</a>
            </div>
            <ul class="nav-links">
                <li><a href="../homepage.php">Accueil</a></li>
                <li><a href="events.php" class="active">Événements</a></li>
                <?php if ($userRole >= ROLE_CHARGE_CLUB): ?>
                    <li><a href="approbation_events.php">Approbation des événements</a></li>
                    <li><a href="suivi_events.php">Planification & Suivi</a></li>
                <?php endif; ?>
                <li><a href="../members/liste_membres.php">Membres</a></li>
                <li><a href="../clubs/clubspage.php">Clubs</a></li>
            </ul>
            <div class="nav-icons">
                <div class="notification-icon">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge"><?php echo $notifCount; ?></span>
                </div>
                <div class="user-profile">
                    <a href="../user_profile/userProfile.php">
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
            // In the notifications section of events.php:
            if ($userRole >= ROLE_CHARGE_CLUB) {
                // Get count of pending events
                $pendingQuery = "SELECT COUNT(*) as count FROM events WHERE is_approved IS NULL";
                $pendingResult = mysqli_query($conn, $pendingQuery);
                $pendingData = mysqli_fetch_assoc($pendingResult);
                
                if ($pendingData['count'] > 0) {
                    echo '<div class="admin-notification">';
                    echo '<i class="fas fa-exclamation-circle"></i>';
                    echo '<span>' . $pendingData['count'] . ' événements en attente d\'approbation</span>';
                    echo '<a href="approbation_events.php" class="btn btn-sm">Examiner</a>';
                    echo '</div>';
                }
            }
            ?>
        </div>
        <div class="notification-footer">
            <a href="../notifications/all_notifications.php">Voir toutes les notifications</a>
        </div>
    </div>

    <!-- Events Page Header -->
    <section class="page-header">
        <div class="container">
            <h1>Événements Universitaires</h1>
            <p>Découvrez et participez aux événements organisés par les clubs universitaires</p>
            
            <?php if ($userRole >= ROLE_CHARGE_CLUB): ?>
            <div class="header-buttons">
                <a href="creation_event.php" class="btn btn-primary"><i class="fas fa-plus"></i> Créer un Événement</a>
            </div>
            <?php endif; ?>
            
            <!-- Display success or error messages -->
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Events Filter Section -->
    <section class="events-filter">
        <div class="container">
            <div class="filter-container">
                <div class="filter-group">
                    <label for="club-filter">Filtrer par club:</label>
                    <select id="club-filter" onchange="window.location.href='events.php?club='+this.value">
                        <option value="">Tous les clubs</option>
                        <?php
                        $clubsQuery = "SELECT * FROM clubs ORDER BY name";
                        $clubsResult = mysqli_query($conn, $clubsQuery);
                        while ($club = mysqli_fetch_assoc($clubsResult)) {
                            $selected = ($clubFilter == $club['id']) ? 'selected' : '';
                            echo "<option value='{$club['id']}' {$selected}>{$club['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="date-filter">Filtrer par date:</label>
                    <select id="date-filter">
                        <option value="all">Toutes les dates</option>
                        <option value="upcoming">À venir</option>
                        <option value="past">Passés</option>
                    </select>
                </div>
            </div>
        </div>
    </section>

    <!-- Events List -->
    <section class="events-list">
        <div class="container">
            <?php if (mysqli_num_rows($events) > 0): ?>
                <div class="events-grid">
                    <?php while ($event = mysqli_fetch_assoc($events)): 
                        $isRegistered = isUserRegistered($conn, $event['id'], $userId);
                        $eventDate = new DateTime($event['event_date']);
                        $now = new DateTime();
                        $isPast = $eventDate < $now;
                        $eventStatus = $isPast ? 'past' : 'upcoming';
                        $canManage = ($userRole == ROLE_ADMIN) || 
                                    ($userRole == ROLE_CHARGE_CLUB && isset($event['clubid']) && 
                                     isClubResponsible($conn, $userId, $event['clubid']));
                    ?>
                    <div class="event-card <?php echo $eventStatus; ?>">
                        <div class="event-date">
                            <span class="day"><?php echo $eventDate->format('d'); ?></span>
                            <span class="month"><?php echo $eventDate->format('M'); ?></span>
                        </div>
                        <div class="event-info">
                            <div class="event-club"><?php echo htmlspecialchars($event['club_name']); ?></div>
                            <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                            <p><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                            <div class="event-meta">
                                <span><i class="far fa-clock"></i> <?php echo $eventDate->format('H:i'); ?></span>
                                <span class="event-status <?php echo $eventStatus; ?>">
                                    <?php echo $isPast ? 'Terminé' : 'À venir'; ?>
                                </span>
                            </div>
                            
                            <?php if (!$isPast): // Only allow registration for future events ?>
                                <?php if (!$isRegistered): ?>
                                    <form method="post" action="">
                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                        <button type="submit" name="register_event" class="btn btn-primary">S'inscrire</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="">
                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                        <button type="submit" name="cancel_registration" class="btn btn-secondary">Annuler l'inscription</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if ($canManage): // Show manage button for responsibles and admin ?>
                                <a href="gestion_events.php?id=<?php echo $event['id']; ?>" class="btn btn-outline">Gérer</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-events">
                    <i class="fas fa-calendar-times"></i>
                    <h3>Aucun événement disponible</h3>
                    <p>Il n'y a actuellement aucun événement à afficher.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

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
                    <li><a href="../homepage.php">Accueil</a></li>
                    <li><a href="events.php">Événements</a></li>
                    <li><a href="../clubs/clubspage.php">Clubs</a></li>
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

    <script src="../homepage_script.js"></script>
    <script>
        // JavaScript for date filtering
        document.getElementById('date-filter').addEventListener('change', function() {
            const filter = this.value;
            const eventCards = document.querySelectorAll('.event-card');
            
            eventCards.forEach(card => {
                if (filter === 'all') {
                    card.style.display = 'flex';
                } else if (filter === 'upcoming' && card.classList.contains('upcoming')) {
                    card.style.display = 'flex';
                } else if (filter === 'past' && card.classList.contains('past')) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>