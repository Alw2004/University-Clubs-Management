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
        // Utilisateur non trouvé, déconnecter
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

// Vérifier si l'utilisateur a les droits d'accès (chargé ou admin)
if ($userRole < ROLE_CHARGE_CLUB) {
    header("Location: ../homepage.php");
    exit();
}

// Traitement de l'approbation/refus d'un événement
if (isset($_POST['approve_event'])) {
    $eventId = $_POST['event_id'];
    $status = $_POST['status']; // 1 pour approuvé, 0 pour refusé
    $comment = $_POST['comment'];
    
    // Mettre à jour le statut de l'événement
    $updateQuery = "UPDATE events SET is_approved = ? WHERE id = ?";
    $updateStmt = mysqli_prepare($conn, $updateQuery);
    mysqli_stmt_bind_param($updateStmt, "ii", $status, $eventId);
    
    if (mysqli_stmt_execute($updateStmt)) {
        // Récupérer les informations de l'événement pour la notification
        $eventQuery = "SELECT e.title, c.resId FROM events e 
                      INNER JOIN clubs c ON e.clubid = c.id 
                      WHERE e.id = ?";
        $eventStmt = mysqli_prepare($conn, $eventQuery);
        mysqli_stmt_bind_param($eventStmt, "i", $eventId);
        mysqli_stmt_execute($eventStmt);
        $eventResult = mysqli_stmt_get_result($eventStmt);
        $eventData = mysqli_fetch_assoc($eventResult);
        
        // Créer une notification pour le responsable du club
        $resId = $eventData['resId'];
        $eventTitle = $eventData['title'];
        
        if ($status == 1) {
            $message = "Votre événement '" . $eventTitle . "' a été approuvé.";
            $type = "event_approved";
        } else {
            $message = "Votre événement '" . $eventTitle . "' a été refusé. Commentaire: " . $comment;
            $type = "event_rejected";
        }
        
        $notifQuery = "INSERT INTO notifications (userid, message, type, is_read) VALUES (?, ?, ?, 0)";
        $notifStmt = mysqli_prepare($conn, $notifQuery);
        mysqli_stmt_bind_param($notifStmt, "iss", $resId, $message, $type);
        mysqli_stmt_execute($notifStmt);
        
        $success = $status == 1 ? "Événement approuvé avec succès!" : "Événement refusé avec succès!";
    } else {
        $error = "Erreur lors de la mise à jour: " . mysqli_error($conn);
    }
}

// Récupérer la liste des événements en attente
function getPendingEvents($conn) {
    $query = "SELECT e.*, c.name as club_name, u.name as responsible_name 
              FROM events e 
              INNER JOIN clubs c ON e.clubid = c.id 
              INNER JOIN users u ON c.resId = u.id 
              WHERE e.is_approved IS NULL 
              ORDER BY e.created_at DESC";
    $result = mysqli_query($conn, $query);
    return $result;
}

// Récupérer la liste des événements approuvés ou refusés
function getProcessedEvents($conn) {
    $query = "SELECT e.*, c.name as club_name, u.name as responsible_name 
              FROM events e 
              INNER JOIN clubs c ON e.clubid = c.id 
              INNER JOIN users u ON c.resId = u.id 
              WHERE e.is_approved IS NOT NULL 
              ORDER BY e.created_at DESC";
    $result = mysqli_query($conn, $query);
    return $result;
}

// Récupérer les événements
$pendingEvents = getPendingEvents($conn);
$processedEvents = getProcessedEvents($conn);

// Compter les notifications
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
    <title>Approbation des Événements | UniClub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../homepage_styles.css">
    <link rel="stylesheet" href="events_styles.css">
    <style>
        .events-approval {
            padding: 30px 0;
        }
        
        .approval-section {
            margin-bottom: 40px;
        }
        
        .approval-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .approval-table {
            width: 100%;
            border-collapse: collapse;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .approval-table th, .approval-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .approval-table th {
            background-color: #f5f7fa;
            color: #333;
            font-weight: 600;
        }
        
        .approval-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: #fff8e6;
            color: #e6a700;
        }
        
        .status-approved {
            background-color: #e6f7ed;
            color: #00a650;
        }
        
        .status-rejected {
            background-color: #ffebee;
            color: #d32f2f;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-review {
            background-color: #4a6cf7;
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 30px;
            border-radius: 8px;
            width: 60%;
            max-width: 600px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #333;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #333;
        }
        
        .event-details {
            margin-bottom: 20px;
        }
        
        .event-info {
            margin-bottom: 5px;
            display: flex;
        }
        
        .event-info-label {
            font-weight: bold;
            width: 120px;
            flex-shrink: 0;
        }
        
        .approval-form {
            display: flex;
            flex-direction: column;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .approval-options {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .option-btn {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .approve-btn {
            color: #00a650;
            border-color: #00a650;
        }
        
        .approve-btn:hover, .approve-btn.selected {
            background-color: #e6f7ed;
        }
        
        .reject-btn {
            color: #d32f2f;
            border-color: #d32f2f;
        }
        
        .reject-btn:hover, .reject-btn.selected {
            background-color: #ffebee;
        }
        
        .comment-textarea {
            width: 100%;
            min-height: 100px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            resize: vertical;
        }
        
        .form-submit {
            margin-top: 20px;
            text-align: right;
        }
        
        .btn-submit {
            padding: 10px 20px;
            background-color: #4a6cf7;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .btn-submit:hover {
            background-color: #3a5bd9;
        }
        
        .no-events {
            padding: 30px;
            text-align: center;
            background-color: #f9f9f9;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .no-events i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        .error-message {
            color: #d32f2f;
            margin-top: 5px;
            font-size: 0.9rem;
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
                <li><a href="events.php" >Événements</a></li>
                <?php if ($userRole >= ROLE_CHARGE_CLUB): ?>
                    <li><a href="approbation_events.php" class="active">Approbation des événements</a></li>
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
                    
                    // Calculer le temps écoulé
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
            <h1>Approbation des Événements</h1>
            <p>Gérez et approuvez les événements créés par les clubs universitaires</p>
            
            <!-- Afficher les messages de succès ou d'erreur -->
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Events Approval Section -->
    <section class="events-approval">
        <div class="container">
            <!-- Pending Events Section -->
            <div class="approval-section">
                <div class="approval-header">
                    <h2>Événements en attente d'approbation</h2>
                    <span class="badge"><?php echo mysqli_num_rows($pendingEvents); ?> événement(s)</span>
                </div>
                
                <?php if (mysqli_num_rows($pendingEvents) > 0): ?>
                <div class="table-responsive">
                    <table class="approval-table">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Club</th>
                                <th>Responsable</th>
                                <th>Date de l'événement</th>
                                <th>Date de création</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($event = mysqli_fetch_assoc($pendingEvents)): 
                                $eventDate = new DateTime($event['event_date']);
                                $createdDate = new DateTime($event['created_at']);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($event['title']); ?></td>
                                <td><?php echo htmlspecialchars($event['club_name']); ?></td>
                                <td><?php echo htmlspecialchars($event['responsible_name']); ?></td>
                                <td><?php echo $eventDate->format('d/m/Y H:i'); ?></td>
                                <td><?php echo $createdDate->format('d/m/Y H:i'); ?></td>
                                <td><span class="status-badge status-pending">En attente</span></td>
                                <td>
                                    <button class="btn btn-sm btn-review" onclick="openReviewModal(<?php echo $event['id']; ?>, '<?php echo addslashes(htmlspecialchars($event['title'])); ?>', '<?php echo addslashes(htmlspecialchars($event['club_name'])); ?>', '<?php echo addslashes(htmlspecialchars($event['description'])); ?>', '<?php echo $eventDate->format('d/m/Y H:i'); ?>', '<?php echo addslashes(htmlspecialchars($event['responsible_name'])); ?>')">
                                        Examiner
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="no-events">
                    <i class="fas fa-check-circle"></i>
                    <h3>Aucun événement en attente</h3>
                    <p>Tous les événements ont été traités.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Processed Events Section -->
            <div class="approval-section">
                <div class="approval-header">
                    <h2>Événements traités récemment</h2>
                </div>
                
                <?php if (mysqli_num_rows($processedEvents) > 0): ?>
                <div class="table-responsive">
                    <table class="approval-table">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Club</th>
                                <th>Responsable</th>
                                <th>Date de l'événement</th>
                                <th>Date de création</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($event = mysqli_fetch_assoc($processedEvents)): 
                                $eventDate = new DateTime($event['event_date']);
                                $createdDate = new DateTime($event['created_at']);
                                $statusClass = $event['is_approved'] == 1 ? 'status-approved' : 'status-rejected';
                                $statusText = $event['is_approved'] == 1 ? 'Approuvé' : 'Refusé';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($event['title']); ?></td>
                                <td><?php echo htmlspecialchars($event['club_name']); ?></td>
                                <td><?php echo htmlspecialchars($event['responsible_name']); ?></td>
                                <td><?php echo $eventDate->format('d/m/Y H:i'); ?></td>
                                <td><?php echo $createdDate->format('d/m/Y H:i'); ?></td>
                                <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="no-events">
                    <i class="fas fa-info-circle"></i>
                    <h3>Aucun événement traité</h3>
                    <p>Il n'y a pas d'événements traités à afficher.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Review Modal -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Examiner l'événement</h3>
                <span class="close">&times;</span>
            </div>
            <div class="event-details">
                <div class="event-info">
                    <span class="event-info-label">Titre:</span>
                    <span id="eventTitle"></span>
                </div>
                <div class="event-info">
                    <span class="event-info-label">Club:</span>
                    <span id="eventClub"></span>
                </div>
                <div class="event-info">
                    <span class="event-info-label">Responsable:</span>
                    <span id="eventResponsible"></span>
                </div>
                <div class="event-info">
                    <span class="event-info-label">Date:</span>
                    <span id="eventDate"></span>
                </div>
                <div class="event-info">
                    <span class="event-info-label">Description:</span>
                </div>
                <div class="event-description" id="eventDesc"></div>
            </div>
            <form class="approval-form" id="approvalForm" method="post" action="">
                <input type="hidden" id="eventId" name="event_id" value="">
                <input type="hidden" id="statusInput" name="status" value="">
                
                <div class="form-group">
                    <label>Décision:</label>
                    <div class="approval-options">
                        <div class="option-btn approve-btn" id="approveBtn" onclick="selectOption(1)">
                            <i class="fas fa-check"></i> Approuver
                        </div>
                        <div class="option-btn reject-btn" id="rejectBtn" onclick="selectOption(0)">
                            <i class="fas fa-times"></i> Refuser
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="comment">Commentaire (obligatoire en cas de refus):</label>
                    <textarea id="comment" name="comment" class="comment-textarea" placeholder="Ajoutez votre commentaire ici..."></textarea>
                    <div id="commentError" class="error-message" style="display: none;">
                        Le commentaire est obligatoire en cas de refus
                    </div>
                </div>
                
                <div class="form-submit">
                    <button type="submit" name="approve_event" class="btn-submit">Soumettre la décision</button>
                </div>
            </form>
        </div>
    </div>

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
        // Modal functionality
        const modal = document.getElementById("reviewModal");
        const closeBtn = document.getElementsByClassName("close")[0];
        
        function openReviewModal(id, title, club, description, date, responsible) {
            document.getElementById("eventId").value = id;
            document.getElementById("eventTitle").textContent = title;
            document.getElementById("eventClub").textContent = club;
            document.getElementById("eventDesc").textContent = description;
            document.getElementById("eventDate").textContent = date;
            document.getElementById("eventResponsible").textContent = responsible;
            
            // Reset form
            document.getElementById("statusInput").value = "";
            document.getElementById("comment").value = "";
            document.getElementById("approveBtn").classList.remove("selected");
            document.getElementById("rejectBtn").classList.remove("selected");
            document.getElementById("commentError").style.display = "none";
            
            modal.style.display = "block";
        }
        
        closeBtn.onclick = function() {
            modal.style.display = "none";
        }
        
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
        
        // Form functionality
        function selectOption(status) {
            document.getElementById("statusInput").value = status;
            
            if (status === 1) {
                document.getElementById("approveBtn").classList.add("selected");
                document.getElementById("rejectBtn").classList.remove("selected");
            } else {
                document.getElementById("approveBtn").classList.remove("selected");
                document.getElementById("rejectBtn").classList.add("selected");
            }
        }
        
        // Form validation
        document.getElementById("approvalForm").onsubmit = function(e) {
            const status = document.getElementById("statusInput").value;
            const comment = document.getElementById("comment").value.trim();
            
            if (status === "") {
                e.preventDefault();
                alert("Veuillez sélectionner une décision (Approuver ou Refuser)");
                return false;
            }
            
            if (status === "0" && comment === "") {
                e.preventDefault();
                document.getElementById("commentError").style.display = "block";
                return false;
            }
            
            return true;
        };
    </script>
</body>
</html>