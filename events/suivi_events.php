<?php
session_start();
include("../connect.php");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Récupérer le rôle de l'utilisateur
$userId = $_SESSION['user_id'];

if (!isset($_SESSION['role'])) {
    // Récupérer le rôle depuis la base de données
    $query = "SELECT role FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $userData = mysqli_fetch_assoc($result);

    if ($userData) {
        $_SESSION['role'] = $userData['role'];
        $userRole = (int)$userData['role'];
    } else {
        // Utilisateur non trouvé, déconnexion
        header("Location: ../logout.php");
        exit();
    }
} else {
    $userRole = (int)$_SESSION['role'];
}

// Définir les constantes pour les rôles
define('ROLE_INSCRIT', 1);
define('ROLE_MEMBRE', 2);
define('ROLE_CHARGE_CLUB', 3);
define('ROLE_ADMIN', 4);

// Vérifier si l'utilisateur a les droits d'accès (admin ou chargé de club)
if ($userRole < ROLE_CHARGE_CLUB) {
    header("Location: events.php");
    exit();
}

// Fonction pour récupérer tous les événements
function getAllEvents($conn) {
    $query = "SELECT e.*, c.name as club_name, 
              (SELECT COUNT(*) FROM event_participants WHERE eventid = e.id) as participant_count,
              (SELECT MAX(e.max_participants)) as max_p 
              FROM events e 
              INNER JOIN clubs c ON e.clubid = c.id 
              ORDER BY e.event_date ASC";
    $result = mysqli_query($conn, $query);
    return $result;
}

// Fonction pour récupérer les événements d'un club spécifique
function getClubEvents($conn, $clubId) {
    $query = "SELECT e.*, c.name as club_name, 
              (SELECT COUNT(*) FROM event_participants WHERE eventid = e.id) as participant_count,
              (SELECT MAX(e.max_participants)) as max_p 
              FROM events e 
              INNER JOIN clubs c ON e.clubid = c.id 
              WHERE e.clubid = ? 
              ORDER BY e.event_date ASC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $clubId);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

// Fonction pour récupérer les clubs dont l'utilisateur est responsable
function getUserClubs($conn, $userId) {
    $query = "SELECT c.* FROM clubs c 
              WHERE c.resId = ? 
              ORDER BY c.name ASC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

// Fonction pour récupérer les participants d'un événement
function getEventParticipants($conn, $eventId) {
    $query = "SELECT u.*, ep.is_attending 
              FROM event_participants ep 
              INNER JOIN users u ON ep.userid = u.id 
              WHERE ep.eventid = ? 
              ORDER BY u.name ASC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $eventId);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

// Traitement de la validation du programme
// Traitement de la validation du programme
if (isset($_POST['validate_program'])) {
    $eventId = $_POST['event_id'];
    $programDetails = mysqli_real_escape_string($conn, $_POST['program_details']);
    
    // Mise à jour du programme de l'événement
    $query = "UPDATE events SET program_details = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    
    // Vérifier si la préparation a réussi
    if ($stmt === false) {
        $error = "Erreur lors de la préparation de la requête: " . mysqli_error($conn);
    } else {
        mysqli_stmt_bind_param($stmt, "si", $programDetails, $eventId);
        
        if (mysqli_stmt_execute($stmt)) {
            // Notifier les participants de la mise à jour du programme
            $eventQuery = "SELECT title FROM events WHERE id = ?";
            $eventStmt = mysqli_prepare($conn, $eventQuery);
            
            if ($eventStmt === false) {
                $error = "Erreur lors de la préparation de la requête event: " . mysqli_error($conn);
            } else {
                mysqli_stmt_bind_param($eventStmt, "i", $eventId);
                mysqli_stmt_execute($eventStmt);
                $eventResult = mysqli_stmt_get_result($eventStmt);
                $eventData = mysqli_fetch_assoc($eventResult);
                
                $eventTitle = $eventData['title'];
                $message = "Le programme de l'événement '" . $eventTitle . "' a été mis à jour.";
                
                // Récupérer les participants à notifier
                $participantsQuery = "SELECT userid FROM event_participants WHERE eventid = ?";
                $participantsStmt = mysqli_prepare($conn, $participantsQuery);
                
                if ($participantsStmt === false) {
                    $error = "Erreur lors de la préparation de la requête participants: " . mysqli_error($conn);
                } else {
                    mysqli_stmt_bind_param($participantsStmt, "i", $eventId);
                    mysqli_stmt_execute($participantsStmt);
                    $participantsResult = mysqli_stmt_get_result($participantsStmt);
                    
                    while ($participant = mysqli_fetch_assoc($participantsResult)) {
                        $notifQuery = "INSERT INTO notifications (userid, message, type, is_read) VALUES (?, ?, 'event_update', 0)";
                        $notifStmt = mysqli_prepare($conn, $notifQuery);
                        
                        if ($notifStmt === false) {
                            $error = "Erreur lors de la préparation de la requête notification: " . mysqli_error($conn);
                            break;
                        } else {
                            mysqli_stmt_bind_param($notifStmt, "is", $participant['userid'], $message);
                            mysqli_stmt_execute($notifStmt);
                        }
                    }
                    
                    if (!isset($error)) {
                        $success = "Programme de l'événement validé avec succès!";
                    }
                }
            }
        } else {
            $error = "Erreur lors de la validation du programme: " . mysqli_error($conn);
        }
    }
}

// Filtrer par club si spécifié
$clubFilter = isset($_GET['club']) ? $_GET['club'] : null;
$eventFilter = isset($_GET['event']) ? $_GET['event'] : null;

// Obtenir les clubs de l'utilisateur s'il est chargé de club
$userClubs = null;
if ($userRole >= ROLE_CHARGE_CLUB) {
    if ($userRole == ROLE_ADMIN) {
        // Les admins peuvent voir tous les clubs
        $clubsQuery = "SELECT * FROM clubs ORDER BY name ASC";
        $userClubs = mysqli_query($conn, $clubsQuery);
    } else {
        // Les chargés de club ne voient que leurs clubs
        $userClubs = getUserClubs($conn, $userId);
    }
}

// Récupérer les événements selon les filtres
if ($eventFilter) {
    // Afficher un événement spécifique pour la planification
    $query = "SELECT e.*, c.name as club_name FROM events e 
              INNER JOIN clubs c ON e.clubid = c.id 
              WHERE e.id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $eventFilter);
    mysqli_stmt_execute($stmt);
    $event = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    // Récupérer les participants de cet événement
    $participants = getEventParticipants($conn, $eventFilter);
} else if ($clubFilter) {
    $events = getClubEvents($conn, $clubFilter);
} else {
    $events = getAllEvents($conn);
}

// Compter les notifications non lues
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
    <title>Planification et Suivi des Événements</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../homepage_styles.css">
    <link rel="stylesheet" href="events_styles.css">
    <style>
        .planning-section {
            margin: 20px 0;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .event-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-pending {
            background-color: #ffd700;
            color: #333;
        }
        
        .status-approved {
            background-color: #4caf50;
            color: white;
        }
        
        .status-rejected {
            background-color: #f44336;
            color: white;
        }
        
        .participant-list {
            margin-top: 20px;
        }
        
        .participant-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .participant-status {
            font-size: 0.8rem;
            padding: 3px 8px;
            border-radius: 4px;
        }
        
        .attending {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .not-attending {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .program-form textarea {
            width: 100%;
            min-height: 150px;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .stats-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            width: 30%;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #3498db;
        }
        
        .filter-section {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .filter-section label {
            margin-right: 10px;
            font-weight: bold;
        }
        
        .filter-section select {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .event-capacity {
            margin-top: 10px;
            height: 10px;
            background-color: #e0e0e0;
            border-radius: 5px;
            overflow: hidden;
        }

        .capacity-filled {
            height: 100%;
            background-color: #3498db;
        }
    </style>
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
                <li><a href="events.php">Événements</a></li>
                <?php if ($userRole >= ROLE_CHARGE_CLUB): ?>
                    <li><a href="approbation_events.php">Approbation des événements</a></li>
                    <li><a href="suivi_events.php" class="active">Planification & Suivi</a></li>
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
            // Récupérer les notifications
            $notifListQuery = "SELECT * FROM notifications WHERE userid = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5";
            $notifListStmt = mysqli_prepare($conn, $notifListQuery);
            mysqli_stmt_bind_param($notifListStmt, "i", $userId);
            mysqli_stmt_execute($notifListStmt);
            $notifications = mysqli_stmt_get_result($notifListStmt);
            
            if (mysqli_num_rows($notifications) > 0) {
                while ($notification = mysqli_fetch_assoc($notifications)) {
                    echo '<div class="notification-item unread">';
                    echo '<div class="notification-icon">';
                    
                    // Choisir l'icône en fonction du type de notification
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
                    
                    // Calculer la différence de temps
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
            <h1>Planification et Suivi des Événements</h1>
            <p>Consultez et gérez les événements et les inscriptions des participants</p>
            
            <!-- Afficher les messages de succès ou d'erreur -->
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!isset($eventFilter)): ?>
    <!-- Vue globale des événements -->
    <section class="planning-section">
        <div class="container">
            <h2>Vue Globale des Événements</h2>
            
            <!-- Statistiques générales -->
            <div class="stats-container">
                <?php
                // Total des événements
                $totalEventsQuery = "SELECT COUNT(*) as count FROM events";
                $totalEvents = mysqli_fetch_assoc(mysqli_query($conn, $totalEventsQuery))['count'];
                
                // Événements à venir
                $upcomingEventsQuery = "SELECT COUNT(*) as count FROM events WHERE event_date > NOW()";
                $upcomingEvents = mysqli_fetch_assoc(mysqli_query($conn, $upcomingEventsQuery))['count'];
                
                // Total des inscriptions
                $totalParticipantsQuery = "SELECT COUNT(*) as count FROM event_participants";
                $totalParticipants = mysqli_fetch_assoc(mysqli_query($conn, $totalParticipantsQuery))['count'];
                ?>
                
                <div class="stat-card">
                    <h3>Total des événements</h3>
                    <div class="stat-number"><?php echo $totalEvents; ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Événements à venir</h3>
                    <div class="stat-number"><?php echo $upcomingEvents; ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Total des inscriptions</h3>
                    <div class="stat-number"><?php echo $totalParticipants; ?></div>
                </div>
            </div>
            
            <!-- Filtres -->
            <div class="filter-section">
                <label for="club-filter">Filtrer par club:</label>
                <select id="club-filter" onchange="window.location.href='suivi_events.php?club='+this.value">
                    <option value="">Tous les clubs</option>
                    <?php
                    if ($userClubs) {
                        mysqli_data_seek($userClubs, 0);
                        while ($club = mysqli_fetch_assoc($userClubs)) {
                            $selected = ($clubFilter == $club['id']) ? 'selected' : '';
                            echo "<option value='{$club['id']}' {$selected}>{$club['name']}</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            
            <!-- Liste des événements -->
            <?php if (isset($events) && mysqli_num_rows($events) > 0): ?>
                <div class="events-grid">
                    <?php while ($event = mysqli_fetch_assoc($events)): 
                        $eventDate = new DateTime($event['event_date']);
                        $now = new DateTime();
                        $isPast = $eventDate < $now;
                        $eventStatus = $isPast ? 'past' : 'upcoming';
                        
                        // Calculer le pourcentage de participants
                        $participantCount = $event['participant_count'];
                        $maxParticipants = $event['max_p'] ? $event['max_p'] : 0;
                        $capacityPercentage = $maxParticipants > 0 ? ($participantCount / $maxParticipants) * 100 : 0;
                    ?>
                    <div class="event-card <?php echo $eventStatus; ?>">
                        <div class="event-date">
                            <span class="day"><?php echo $eventDate->format('d'); ?></span>
                            <span class="month"><?php echo $eventDate->format('M'); ?></span>
                        </div>
                        <div class="event-info">
                            <div class="event-club"><?php echo htmlspecialchars($event['club_name']); ?></div>
                            <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                            <p><?php echo nl2br(htmlspecialchars(substr($event['description'], 0, 100))); ?>...</p>
                            <div class="event-meta">
                                <span><i class="far fa-clock"></i> <?php echo $eventDate->format('H:i'); ?></span>
                                <?php
                                // Afficher le statut d'approbation
                                if ($event['is_approved'] === NULL) {
                                    echo '<span class="event-status status-pending">En attente</span>';
                                } elseif ($event['is_approved'] == 1) {
                                    echo '<span class="event-status status-approved">Approuvé</span>';
                                } else {
                                    echo '<span class="event-status status-rejected">Rejeté</span>';
                                }
                                ?>
                            </div>
                            
                            <div class="event-capacity">
                                <div class="capacity-filled" style="width: <?php echo min($capacityPercentage, 100); ?>%;"></div>
                            </div>
                            <div class="capacity-text">
                                <small><?php echo $participantCount; ?><?php echo $maxParticipants ? '/'.$maxParticipants : ''; ?> participants</small>
                            </div>
                            
                            <a href="suivi_events.php?event=<?php echo $event['id']; ?>" class="btn btn-primary">Planifier</a>
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

    <?php else: ?>
    <!-- Détails et planification d'un événement spécifique -->
    <section class="planning-section">
        <div class="container">
            <div class="section-header">
                <a href="suivi_events.php<?php echo $clubFilter ? '?club='.$clubFilter : ''; ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
                <h2>Planification de l'Événement</h2>
            </div>
            
            <?php if (isset($event)): ?>
                <div class="event-details">
                    <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                    <div class="event-meta">
                        <span><i class="fas fa-users"></i> <?php echo htmlspecialchars($event['club_name']); ?></span>
                        <span><i class="far fa-calendar"></i> <?php echo date('d/m/Y', strtotime($event['event_date'])); ?></span>
                        <span><i class="far fa-clock"></i> <?php echo date('H:i', strtotime($event['event_date'])); ?></span>
                        <?php
                        // Afficher le statut d'approbation
                        if ($event['is_approved'] === NULL) {
                            echo '<span class="event-status status-pending">En attente</span>';
                        } elseif ($event['is_approved'] == 1) {
                            echo '<span class="event-status status-approved">Approuvé</span>';
                        } else {
                            echo '<span class="event-status status-rejected">Rejeté</span>';
                        }
                        ?>
                    </div>
                    
                    <div class="event-description">
                        <h4>Description</h4>
                        <p><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                    </div>
                    
                    <!-- Programme actuel de l'événement -->
                    <?php if (!empty($event['program_details'])): ?>
                    <div class="event-program">
                        <h4>Programme actuel</h4>
                        <div class="program-content">
                            <?php echo nl2br(htmlspecialchars($event['program_details'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Formulaire de planification -->
                    <form class="program-form" method="post">
                        <h4>Planifier le programme</h4>
                        <textarea name="program_details" placeholder="Détaillez le programme de l'événement ici..."><?php echo isset($event['program_details']) ? htmlspecialchars($event['program_details']) : ''; ?></textarea>
                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                        <button type="submit" name="validate_program" class="btn btn-primary">Valider le programme</button>
                    </form>
                    
                    <!-- Liste des participants -->
                    <div class="participant-list">
                        <h4>Liste des participants</h4>
                        <?php if (isset($participants) && mysqli_num_rows($participants) > 0): ?>
                            <?php while ($participant = mysqli_fetch_assoc($participants)): ?>
                                <div class="participant-item">
                                    <div>
                                        <strong><?php echo htmlspecialchars($participant['name']); ?></strong>
                                        <span><?php echo htmlspecialchars($participant['email']); ?></span>
                                    </div>
                                    <span class="participant-status <?php echo $participant['is_attending'] ? 'attending' : 'not-attending'; ?>">
                                        <?php echo $participant['is_attending'] ? 'Confirmé' : 'Non confirmé'; ?>
                                    </span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p>Aucun participant inscrit pour cet événement.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-events">
                    <i class="fas fa-exclamation-circle"></i>
                    <h3>Événement non trouvé</h3>
                    <p>L'événement que vous recherchez n'existe pas ou vous n'avez pas les permissions nécessaires.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

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
        document.addEventListener('DOMContentLoaded', function() {
            // Script pour les notifications
            const notificationIcon = document.querySelector('.notification-icon');
            const notificationDropdown = document.querySelector('.notification-dropdown');
            
            notificationIcon.addEventListener('click', function() {
                notificationDropdown.classList.toggle('active');
            });
            
            document.addEventListener('click', function(e) {
                if (!notificationIcon.contains(e.target) && !notificationDropdown.contains(e.target)) {
                    notificationDropdown.classList.remove('active');
                }
            });
            
            // Marquer toutes les notifications comme lues
            const markAllReadBtn = document.querySelector('.mark-all-read');
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', function() {
                    // Envoyer requête AJAX pour marquer comme lu
                    fetch('../notifications/mark_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Mettre à jour l'affichage
                            document.querySelectorAll('.notification-item').forEach(item => {
                                item.classList.remove('unread');
                            });
                            document.querySelector('.notification-badge').textContent = '0';
                        }
                    })
                    .catch(error => console.error('Erreur:', error));
                });
            }
        });
    </script>
</body>
</html>