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
        $query = "SELECT e.*, c.name as club_name, c.resId FROM events e 
                  INNER JOIN clubs c ON e.clubid = c.id 
                  WHERE e.id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $eventId);
    } else {
        // Club managers can only view events for their clubs
        $query = "SELECT e.*, c.name as club_name, c.resId FROM events e 
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

// Function to get event participants
function getEventParticipants($conn, $eventId) {
    $query = "SELECT ep.*, u.name, u.email FROM event_participants ep 
              INNER JOIN users u ON ep.userid = u.id 
              WHERE ep.eventid = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $eventId);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

// Get event details
$event = getEventDetails($conn, $eventId, $userId, $userRole);

// If no event found or user doesn't have permission, redirect
if (!$event) {
    header("Location: events.php");
    exit();
}

// Process approval/rejection of event (admin only)
if (isset($_POST['update_status']) && $userRole == ROLE_ADMIN) {
    $status = $_POST['status'];
    $is_approved = ($status == 'approve') ? 1 : ($status == 'reject' ? 0 : NULL);
    
    $query = "UPDATE events SET is_approved = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $is_approved, $eventId);
    
    if (mysqli_stmt_execute($stmt)) {
        // Create notification for the club responsible
        $resId = $event['resId'];
        $message = ($is_approved == 1) ? 
            "Votre événement '{$event['title']}' a été approuvé." : 
            "Votre événement '{$event['title']}' a été rejeté.";
        
        $type = ($is_approved == 1) ? 'event_approved' : 'event_rejected';
        
        $notifQuery = "INSERT INTO notifications (userid, message, type, is_read) VALUES (?, ?, ?, 0)";
        $notifStmt = mysqli_prepare($conn, $notifQuery);
        mysqli_stmt_bind_param($notifStmt, "iss", $resId, $message, $type);
        mysqli_stmt_execute($notifStmt);
        
        // Refresh event details
        $event = getEventDetails($conn, $eventId, $userId, $userRole);
        $success = ($is_approved == 1) ? "Événement approuvé avec succès!" : "Événement rejeté.";
    } else {
        $error = "Erreur lors de la mise à jour du statut: " . mysqli_error($conn);
    }
}

// Process notification to all participants (club responsible or admin)
if (isset($_POST['notify_participants']) && ($userRole == ROLE_ADMIN || $event['resId'] == $userId)) {
    $message = $_POST['notification_message'];
    
    // Validation du message
    if (empty($message)) {
        $error = "Le message ne peut pas être vide.";
    } else {
        // Get all participants
        $query = "SELECT userid FROM event_participants WHERE eventid = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $eventId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $notified = 0;
        
        while ($participant = mysqli_fetch_assoc($result)) {
            $participantId = $participant['userid'];
            
            $notifQuery = "INSERT INTO notifications (userid, message, type, is_read) VALUES (?, ?, 'event_update', 0)";
            $notifStmt = mysqli_prepare($conn, $notifQuery);
            mysqli_stmt_bind_param($notifStmt, "is", $participantId, $message);
            
            if (mysqli_stmt_execute($notifStmt)) {
                $notified++;
            }
        }
        
        if ($notified > 0) {
            // Update participant notification status
            $updateQuery = "UPDATE event_participants SET notified = 1 WHERE eventid = ?";
            $updateStmt = mysqli_prepare($conn, $updateQuery);
            mysqli_stmt_bind_param($updateStmt, "i", $eventId);
            mysqli_stmt_execute($updateStmt);
            
            $success = "Notification envoyée à " . $notified . " participant(s).";
        } else {
            $error = "Aucun participant n'a été notifié.";
        }
    }
}

// Process participant removal (club responsible or admin)
if (isset($_POST['remove_participant']) && ($userRole == ROLE_ADMIN || $event['resId'] == $userId)) {
    $participantId = $_POST['participant_id'];
    
    $query = "DELETE FROM event_participants WHERE eventid = ? AND userid = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $eventId, $participantId);
    
    if (mysqli_stmt_execute($stmt)) {
        // Notify participant about removal
        $message = "Vous avez été retiré de l'événement '{$event['title']}'.";
        $notifQuery = "INSERT INTO notifications (userid, message, type, is_read) VALUES (?, ?, 'event_removal', 0)";
        $notifStmt = mysqli_prepare($conn, $notifQuery);
        mysqli_stmt_bind_param($notifStmt, "is", $participantId, $message);
        mysqli_stmt_execute($notifStmt);
        
        $success = "Participant retiré avec succès.";
    } else {
        $error = "Erreur lors de la suppression du participant: " . mysqli_error($conn);
    }
}

// Get event participants
$participants = getEventParticipants($conn, $eventId);
$participantCount = mysqli_num_rows($participants);

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
    <title>Gestion de l'Événement - UniClub</title>
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
            ?>
        </div>
        <div class="notification-footer">
            <a href="../notifications/all_notifications.php">Voir toutes les notifications</a>
        </div>
    </div>

    <!-- Event Management Page Content -->
    <section class="page-header">
        <div class="container">
            <h1>Gestion de l'Événement</h1>
            <p>Gérez les détails et les participants de votre événement</p>
        </div>
    </section>

    <!-- Display success or error messages -->
    <div class="container">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
    </div>

    <!-- Event Details Section -->
    <section class="event-details">
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h2>Détails de l'Événement</h2>
                    <a href="events.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
                </div>
                <div class="card-body">
                    <div class="event-info-detail">
                        <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                        <div class="event-club-badge"><?php echo htmlspecialchars($event['club_name']); ?></div>
                        
                        <div class="event-status-badge">
                            <?php 
                            $eventDate = new DateTime($event['event_date']);
                            $now = new DateTime();
                            $isPast = $eventDate < $now;
                            
                            if ($isPast) {
                                echo '<span class="status past">Terminé</span>';
                            } else {
                                echo '<span class="status upcoming">À venir</span>';
                            }
                            
                            if (is_null($event['is_approved'])) {
                                echo '<span class="status pending">En attente d\'approbation</span>';
                            } elseif ($event['is_approved'] == 1) {
                                echo '<span class="status approved">Approuvé</span>';
                            } else {
                                echo '<span class="status rejected">Rejeté</span>';
                            }
                            ?>
                        </div>
                        
                        <div class="event-meta-details">
                            <div class="meta-item">
                                <i class="far fa-calendar"></i>
                                <span>Date: <?php echo $eventDate->format('d/m/Y'); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="far fa-clock"></i>
                                <span>Heure: <?php echo $eventDate->format('H:i'); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-users"></i>
                                <span>Participants: <?php echo $participantCount; ?></span>
                            </div>
                        </div>
                        
                        <div class="event-description">
                            <h4>Description:</h4>
                            <p><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                        </div>
                        
                        <?php if ($userRole == ROLE_ADMIN && is_null($event['is_approved'])): ?>
                            <div class="approval-section">
                                <h4>Action d'Approbation:</h4>
                                <form method="post" action="" class="approval-form">
                                    <input type="hidden" name="update_status" value="1">
                                    <button type="submit" name="status" value="approve" class="btn btn-primary">
                                        <i class="fas fa-check"></i> Approuver
                                    </button>
                                    <button type="submit" name="status" value="reject" class="btn btn-danger">
                                        <i class="fas fa-times"></i> Rejeter
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$isPast && ($event['resId'] == $userId || $userRole == ROLE_ADMIN)): ?>
                            <div class="event-actions">
                                <a href="edit_event.php?id=<?php echo $eventId; ?>" class="btn btn-secondary">
                                    <i class="fas fa-edit"></i> Modifier l'événement
                                </a>
                                <button class="btn btn-danger" id="delete-event-btn">
                                    <i class="fas fa-trash"></i> Supprimer l'événement
                                </button>
                            </div>
                            
                            <!-- Delete Event Confirmation Modal -->
                            <div id="delete-modal" class="modal">
                                <div class="modal-content">
                                    <span class="close">&times;</span>
                                    <h3>Confirmer la suppression</h3>
                                    <p>Êtes-vous sûr de vouloir supprimer cet événement? Cette action ne peut pas être annulée.</p>
                                    <form method="post" action="delete_event.php">
                                        <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                                        <div class="modal-actions">
                                            <button type="button" class="btn btn-secondary" id="cancel-delete">Annuler</button>
                                            <button type="submit" class="btn btn-danger">Supprimer</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Participants Section -->
    <section class="event-participants">
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h2>Participants (<?php echo $participantCount; ?>)</h2>
                    
                    <?php if (!$isPast && ($event['resId'] == $userId || $userRole == ROLE_ADMIN)): ?>
                        <button id="notify-btn" class="btn btn-primary">
                            <i class="fas fa-bell"></i> Notifier tous les participants
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($participantCount > 0): ?>
                        <div class="participants-list">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Email</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($participant = mysqli_fetch_assoc($participants)): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($participant['name']); ?></td>
                                            <td><?php echo htmlspecialchars($participant['email']); ?></td>
                                            <td>
                                                <?php if ($participant['is_attending'] == 1): ?>
                                                    <span class="status-badge attending">Participant</span>
                                                <?php else: ?>
                                                    <span class="status-badge not-attending">En attente</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($event['resId'] == $userId || $userRole == ROLE_ADMIN): ?>
                                                    <form method="post" action="" class="inline-form">
                                                        <input type="hidden" name="participant_id" value="<?php echo $participant['userid']; ?>">
                                                        <button type="submit" name="remove_participant" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir retirer ce participant?')">
                                                            <i class="fas fa-user-minus"></i> Retirer
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="no-participants">
                            <i class="fas fa-users-slash"></i>
                            <p>Aucun participant inscrit à cet événement.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Notification Modal -->
    <div id="notification-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Envoyer une notification</h3>
            <form method="post" action="">
                <div class="form-group">
                    <label for="notification_message">Message:</label>
                    <textarea id="notification_message" name="notification_message" rows="5" required></textarea>
                    <small>Ce message sera envoyé à tous les participants de l'événement.</small>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" id="cancel-notification">Annuler</button>
                    <button type="submit" name="notify_participants" class="btn btn-primary">Envoyer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Export Participants -->
    <?php if ($participantCount > 0 && ($event['resId'] == $userId || $userRole == ROLE_ADMIN)): ?>
    <section class="export-section">
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h2>Exporter les participants</h2>
                </div>
                <div class="card-body">
                    <p>Téléchargez la liste des participants dans différents formats.</p>
                    <div class="export-buttons">
                        <a href="export_participants.php?id=<?php echo $eventId; ?>&format=csv" class="btn btn-outline">
                            <i class="fas fa-file-csv"></i> Exporter en CSV
                        </a>
                        <a href="export_participants.php?id=<?php echo $eventId; ?>&format=pdf" class="btn btn-outline">
                            <i class="fas fa-file-pdf"></i> Exporter en PDF
                        </a>
                        <a href="export_participants.php?id=<?php echo $eventId; ?>&format=xlsx" class="btn btn-outline">
                            <i class="fas fa-file-excel"></i> Exporter en Excel
                        </a>
                    </div>
                </div>
            </div>
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
        // Modal handling for delete confirmation
        const deleteModal = document.getElementById('delete-modal');
        const deleteBtn = document.getElementById('delete-event-btn');
        const cancelDelete = document.getElementById('cancel-delete');
        const closeDeleteModal = document.querySelector('#delete-modal .close');
        
        // Modal handling for notification
        const notificationModal = document.getElementById('notification-modal');
        const notifyBtn = document.getElementById('notify-btn');
        const cancelNotification = document.getElementById('cancel-notification');
        const closeNotificationModal = document.querySelector('#notification-modal .close');
        
        // Delete modal functions
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function() {
                deleteModal.style.display = 'block';
            });
        }
        
        if (cancelDelete) {
            cancelDelete.addEventListener('click', function() {
                deleteModal.style.display = 'none';
            });
        }
        
        if (closeDeleteModal) {
            closeDeleteModal.addEventListener('click', function() {
                deleteModal.style.display = 'none';
            });
        }
        
        // Notification modal functions
        if (notifyBtn) {
            notifyBtn.addEventListener('click', function() {
                notificationModal.style.display = 'block';
            });
        }
        
        if (cancelNotification) {
            cancelNotification.addEventListener('click', function() {
                notificationModal.style.display = 'none';
            });
        }
        
        if (closeNotificationModal) {
            closeNotificationModal.addEventListener('click', function() {
                notificationModal.style.display = 'none';
            });
        }
        
        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target == deleteModal) {
                deleteModal.style.display = 'none';
            }
            
            if (event.target == notificationModal) {
                notificationModal.style.display = 'none';
            }
        });
    </script>
</body>
</html>