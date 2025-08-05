<?php
session_start();
include("../connect.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = (int)$_SESSION['role'];

// Définir les constantes pour les rôles
define('ROLE_MEMBRE', 1);
define('ROLE_CHARGE_CLUB', 3);
define('ROLE_ADMIN', 4);

// Check if user has permission to access this page (admin or club manager)
if ($userRole < ROLE_CHARGE_CLUB) {
    header("Location: events.php");
    exit();
}

// Get event ID from URL
$eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($eventId <= 0) {
    header("Location: events.php");
    exit();
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

// Function to get event details
function getEventDetails($conn, $eventId, $userId, $userRole) {
    // Admin can view all events
    if ($userRole == ROLE_ADMIN) {
        $query = "SELECT e.*, c.name as club_name, c.id as clubid, c.resId FROM events e 
                  INNER JOIN clubs c ON e.clubid = c.id 
                  WHERE e.id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $eventId);
    } else {
        // Club managers can only view events for their clubs
        $query = "SELECT e.*, c.name as club_name, c.id as clubid, c.resId FROM events e 
                  INNER JOIN clubs c ON e.clubid = c.id 
                  WHERE e.id = ? AND c.resId = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $eventId, $userId);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        return null;
    }
    
    return mysqli_fetch_assoc($result);
}

// Get event details
$event = getEventDetails($conn, $eventId, $userId, $userRole);

// If no event found or user doesn't have permission, redirect
if (!$event) {
    header("Location: events.php");
    exit();
}

// Check if we can edit this event (only if not past and user is admin or club responsible)
$eventDate = new DateTime($event['event_date']);
$now = new DateTime();
$isPast = $eventDate < $now;

if ($isPast || ($event['resId'] != $userId && $userRole != ROLE_ADMIN)) {
    header("Location: gestion_events.php?id=" . $eventId);
    exit();
}

// Get all clubs for the dropdown (for admins)
$clubs = array();
if ($userRole == ROLE_ADMIN) {
    $clubsQuery = "SELECT id, name FROM clubs ORDER BY name";
    $clubsResult = mysqli_query($conn, $clubsQuery);
    while ($club = mysqli_fetch_assoc($clubsResult)) {
        $clubs[] = $club;
    }
}

// Initialize errors array
$errors = [];

// Process the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $eventDate = $_POST['event_date'];
    $eventTime = $_POST['event_time'];
    $clubId = ($userRole == ROLE_ADMIN && isset($_POST['club_id'])) ? (int)$_POST['club_id'] : $event['clubid'];
    $maxParticipants = (int)$_POST['max_participants'];
    
    // Basic validation
    if (empty($title)) {
        $errors[] = "Le titre de l'événement est requis.";
    }
    
    if (empty($description)) {
        $errors[] = "La description de l'événement est requise.";
    }
    
    if (empty($eventDate)) {
        $errors[] = "La date de l'événement est requise.";
    }
    
    if (empty($eventTime)) {
        $errors[] = "L'heure de l'événement est requise.";
    }
    
    // If no errors, update the event
    if (empty($errors)) {
        // Combine date and time
        $eventDateTime = $eventDate . ' ' . $eventTime . ':00';
        
        // Debug information
        /*
        echo "<pre>";
        echo "DEBUG: Title: " . $title . "\n";
        echo "DEBUG: Description: " . $description . "\n";
        echo "DEBUG: Event Date: " . $eventDateTime . "\n";
        echo "DEBUG: Max Participants: " . $maxParticipants . "\n";
        echo "DEBUG: Club ID: " . $clubId . "\n";
        echo "DEBUG: Event ID: " . $eventId . "\n";
        echo "</pre>";
        */
        
        // Update query
        $query = "UPDATE events SET 
                    title = ?,
                    description = ?,
                    event_date = ?,
                    max_participants = ?,
                    clubid = ?
                  WHERE id = ?";
        
        $stmt = mysqli_prepare($conn, $query);
        
        // Vérifier si la préparation de la requête a réussi
        if ($stmt === false) {
            $errors[] = "Erreur de préparation de la requête: " . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($stmt, "sssiii", $title, $description, $eventDateTime, $maxParticipants, $clubId, $eventId);
            
            if (mysqli_stmt_execute($stmt)) {
                // Success - redirect to the event management page
                header("Location: gestion_events.php?id=" . $eventId . "&updated=1");
                exit();
            } else {
                $errors[] = "Erreur lors de la mise à jour de l'événement: " . mysqli_stmt_error($stmt);
            }
        }
    }
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
    <title>Modifier l'Événement - UniClub</title>
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
                <li><a href="../members/liste_membres.php">Membres</a></li>
                <li><a href="../clubs/clubspage.php">Clubs</a></li>
                <li><a href="#">À propos</a></li>
                <li><a href="#">Contact</a></li>
            </ul>
            <div class="nav-icons">
                <div class="notification-icon">
                    <i class="fas fa-bell"></i>
                    <?php if ($notifCount > 0): ?>
                    <span class="notification-badge"><?php echo $notifCount; ?></span>
                    <?php endif; ?>
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
            ?>
        </div>
        <div class="notification-footer">
            <a href="../notifications/all_notifications.php">Voir toutes les notifications</a>
        </div>
    </div>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1>Modifier l'Événement</h1>
            <p>Mettez à jour les détails de votre événement</p>
        </div>
    </section>

    <!-- Edit Event Form -->
    <section class="create-event-section">
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h2>Modifier les Détails de l'Événement</h2>
                    <a href="gestion_events.php?id=<?php echo $eventId; ?>" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="">
                        <div class="form-group">
                            <label for="title">Titre de l'événement *</label>
                            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($event['title']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description *</label>
                            <textarea id="description" name="description" rows="5" required><?php echo htmlspecialchars($event['description']); ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="event_date">Date *</label>
                                <?php 
                                    $eventDateTime = new DateTime($event['event_date']);
                                    $formattedDate = $eventDateTime->format('Y-m-d');
                                ?>
                                <input type="date" id="event_date" name="event_date" value="<?php echo $formattedDate; ?>" required>
                            </div>
                            
                            <div class="form-group half">
                                <label for="event_time">Heure *</label>
                                <?php 
                                    $formattedTime = $eventDateTime->format('H:i');
                                ?>
                                <input type="time" id="event_time" name="event_time" value="<?php echo $formattedTime; ?>" required>
                            </div>
                        </div>
                        
                        <?php if ($userRole == ROLE_ADMIN && !empty($clubs)): ?>
                            <div class="form-group">
                                <label for="club_id">Club *</label>
                                <select id="club_id" name="club_id" required>
                                    <?php foreach ($clubs as $club): ?>
                                        <option value="<?php echo $club['id']; ?>" <?php echo ($club['id'] == $event['clubid']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($club['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <div class="form-group">
                                <label>Club</label>
                                <input type="text" value="<?php echo htmlspecialchars($event['club_name']); ?>" readonly disabled>
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="max_participants">Nombre maximum de participants</label>
                            <input type="number" id="max_participants" name="max_participants" min="1" value="<?php echo $event['max_participants']; ?>">
                            <small>Laissez vide pour un nombre illimité de participants.</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>
            </div>
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
</body>
</html>