<?php
session_start();
include("../connect.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Debug: Check what role value actually exists
$userId = $_SESSION['user_id'];
$userRole = isset($_SESSION['role']) ? (int)$_SESSION['role'] : 0;

// Fix for role checking - add proper debugging and fallback
if ($userRole === 0) {
    // If role is not in session, fetch it from the database
    $roleQuery = "SELECT role FROM users WHERE id = ?";
    $roleStmt = mysqli_prepare($conn, $roleQuery);
    mysqli_stmt_bind_param($roleStmt, "i", $userId);
    mysqli_stmt_execute($roleStmt);
    $roleResult = mysqli_stmt_get_result($roleStmt);
    
    if ($row = mysqli_fetch_assoc($roleResult)) {
        $userRole = (int)$row['role'];
        // Update session with correct role
        $_SESSION['role'] = $userRole;
    }
}

// Check if user has appropriate role (responsible or admin)
if ($userRole < 3) { // Only responsible (3) or admin (4) can create events
    header("Location: events.php");
    exit();
}

// Get clubs where user is responsible
function getUserClubs($conn, $userId, $userRole) {
    if ($userRole == 4) { // Admin can create events for any club
        $query = "SELECT * FROM clubs ORDER BY name ASC";
        return mysqli_query($conn, $query);
    } else { // Responsible can only create events for their clubs
        $query = "SELECT c.* FROM clubs c 
                  WHERE c.resId = ? 
                  ORDER BY c.name ASC";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        return mysqli_stmt_get_result($stmt);
    }
}

$userClubs = getUserClubs($conn, $userId, $userRole);

// Process event creation form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $clubId = $_POST['club_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $eventDate = $_POST['event_date'];
    $eventTime = $_POST['event_time'];
    $reminderDays = $_POST['reminder_days'];
    
    // Validate club ownership if not admin
    $canCreateForClub = false;
    if ($userRole == 4) { // Admin can create for any club
        $canCreateForClub = true;
    } else {
        $clubQuery = "SELECT * FROM clubs WHERE id = ? AND resId = ?";
        $clubStmt = mysqli_prepare($conn, $clubQuery);
        mysqli_stmt_bind_param($clubStmt, "ii", $clubId, $userId);
        mysqli_stmt_execute($clubStmt);
        $clubResult = mysqli_stmt_get_result($clubStmt);
        $canCreateForClub = mysqli_num_rows($clubResult) > 0;
    }
    
    if ($canCreateForClub) {
        // Combine date and time for MySQL datetime format
        $dateTime = $eventDate . ' ' . $eventTime . ':00';
        
        // Insert event into database
        $query = "INSERT INTO events (clubid, title, description, event_date, reminder_days) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "isssi", $clubId, $title, $description, $dateTime, $reminderDays);
        
        if (mysqli_stmt_execute($stmt)) {
            $eventId = mysqli_insert_id($conn);
            
            // Create notification for club members
            $membersQuery = "SELECT u.id FROM users u 
                           INNER JOIN clubmembers cm ON u.id = cm.userid 
                           WHERE cm.clubid = ?";
            $membersStmt = mysqli_prepare($conn, $membersQuery);
            mysqli_stmt_bind_param($membersStmt, "i", $clubId);
            mysqli_stmt_execute($membersStmt);
            $membersResult = mysqli_stmt_get_result($membersStmt);
            
            while ($member = mysqli_fetch_assoc($membersResult)) {
                if ($member['id'] != $userId) { // Don't notify the creator
                    $message = "Nouvel événement: " . $title;
                    $notifQuery = "INSERT INTO notifications (userid, message, type, is_read) 
                                   VALUES (?, ?, 'new_event', 0)";
                    $notifStmt = mysqli_prepare($conn, $notifQuery);
                    mysqli_stmt_bind_param($notifStmt, "is", $member['id'], $message);
                    mysqli_stmt_execute($notifStmt);
                }
            }
            
            // If user is not admin, notify the admin for approval
            if ($userRole != 4) {
                // Get admin user ID (assuming role 4 is admin)
                $adminQuery = "SELECT id FROM users WHERE role = 4 LIMIT 1";
                $adminResult = mysqli_query($conn, $adminQuery);
                $adminData = mysqli_fetch_assoc($adminResult);
                
                if ($adminData) {
                    $adminId = $adminData['id'];
                    $message = "Nouvel événement à approuver: " . $title;
                    $notifQuery = "INSERT INTO notifications (userid, message, type, is_read) 
                                   VALUES (?, ?, 'event_approval', 0)";
                    $notifStmt = mysqli_prepare($conn, $notifQuery);
                    mysqli_stmt_bind_param($notifStmt, "is", $adminId, $message);
                    mysqli_stmt_execute($notifStmt);
                }
            }
            
            $success = "Événement créé avec succès!";
            
            // Redirect to events page after successful creation
            header("Location: events.php?created=1");
            exit();
        } else {
            $error = "Erreur lors de la création de l'événement: " . mysqli_error($conn);
        }
    } else {
        $error = "Vous n'avez pas l'autorisation de créer un événement pour ce club.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un Événement</title>
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
                    <span class="notification-badge">3</span>
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
            <div class="notification-item unread">
                <div class="notification-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="notification-content">
                    <p>Nouvel événement: Soirée de bienvenue le 15 septembre</p>
                    <span class="notification-time">Il y a 2 heures</span>
                </div>
            </div>
            <div class="notification-item unread">
                <div class="notification-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="notification-content">
                    <p>Votre demande d'adhésion a été approuvée!</p>
                    <span class="notification-time">Hier</span>
                </div>
            </div>
            <div class="notification-item">
                <div class="notification-icon">
                    <i class="fas fa-comment"></i>
                </div>
                <div class="notification-content">
                    <p>Nouveau message dans le forum de discussion</p>
                    <span class="notification-time">Il y a 2 jours</span>
                </div>
            </div>
        </div>
        <div class="notification-footer">
            <a href="../notifications/all_notifications.php">Voir toutes les notifications</a>
        </div>
    </div>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1>Créer un Événement</h1>
            <p>Organisez un nouvel événement pour votre club</p>
            <!-- Debug info for administrators only -->
            <?php if ($userRole == 4): ?>
            <div style="margin-top: 10px; font-size: 0.8rem; color: #e0e0e0;">
                Info Admin: Rôle utilisateur: <?php echo $userRole; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Event Creation Form -->
    <section class="event-creation">
        <div class="container">
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (mysqli_num_rows($userClubs) > 0): ?>
                <div class="form-container">
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="club_id">Club organisateur:</label>
                            <select id="club_id" name="club_id" class="form-control" required>
                                <option value="">Sélectionnez un club</option>
                                <?php while ($club = mysqli_fetch_assoc($userClubs)): ?>
                                    <option value="<?php echo $club['id']; ?>"><?php echo htmlspecialchars($club['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="title">Titre de l'événement:</label>
                            <input type="text" id="title" name="title" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description:</label>
                            <textarea id="description" name="description" class="form-control" required></textarea>
                        </div>
                        
                        <div class="form-inline">
                            <div class="form-group">
                                <label for="event_date">Date:</label>
                                <input type="date" id="event_date" name="event_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="event_time">Heure:</label>
                                <input type="time" id="event_time" name="event_time" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="reminder_days">Rappel (jours avant l'événement):</label>
                            <select id="reminder_days" name="reminder_days" class="form-control">
                                <option value="1">1 jour</option>
                                <option value="2">2 jours</option>
                                <option value="3" selected>3 jours</option>
                                <option value="5">5 jours</option>
                                <option value="7">1 semaine</option>
                                <option value="14">2 semaines</option>
                            </select>
                        </div>
                        
                        <div class="btn-container">
                            <a href="events.php" class="btn btn-secondary">Annuler</a>
                            <button type="submit" name="create_event" class="btn btn-primary">Créer l'événement</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="no-events">
                    <i class="fas fa-exclamation-circle"></i>
                    <h3>Aucun club disponible</h3>
                    <p>Vous n'êtes responsable d'aucun club pour lequel vous pourriez créer un événement.</p>
                    <a href="../clubs/clubspage.php" class="btn btn-primary">Voir les clubs</a>
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
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const description = document.getElementById('description').value.trim();
            const clubId = document.getElementById('club_id').value;
            const eventDate = document.getElementById('event_date').value;
            
            if (!title || !description || !clubId || !eventDate) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires.');
                return false;
            }
            
            // Check if the event date is in the future
            const selectedDate = new Date(eventDate);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                e.preventDefault();
                alert('La date de l\'événement doit être dans le futur.');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>