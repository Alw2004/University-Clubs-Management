<?php 
include '../connect.php';
session_start();

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 0;

// Nombre de notifications
$notifCount = 0;
if ($userId > 0) {
    $notifQuery = "SELECT COUNT(*) as count FROM notifications WHERE userid = ?";
    $notifStmt = mysqli_prepare($conn, $notifQuery);
    mysqli_stmt_bind_param($notifStmt, "i", $userId);
    mysqli_stmt_execute($notifStmt);
    $notifResult = mysqli_stmt_get_result($notifStmt);
    $notifData = mysqli_fetch_assoc($notifResult);
    $notifCount = $notifData['count'];
}

// Constantes pour les rôles
define('ROLE_MEMBRE', 1);
define('ROLE_CHARGE_CLUB', 3);
define('ROLE_ADMIN', 4);

// Récupérer toutes les demandes de création
$reqQuery = "SELECT * FROM creationrequests";
$requestsList = $conn->query($reqQuery);

// Traitement Approve / Refuse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['reqApprove']) || isset($_POST['reqDeny']))) {
    if (isset($_POST['req'])) {
        $demanderId = mysqli_real_escape_string($conn, $_POST['req']);

        if (isset($_POST['reqApprove'])) {
            // 1. Récupérer les infos de la demande
            $fetchRequestQuery = "SELECT title, details FROM creationrequests WHERE demander = ?";
            $stmt = mysqli_prepare($conn, $fetchRequestQuery);
            mysqli_stmt_bind_param($stmt, "i", $demanderId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($result && mysqli_num_rows($result) > 0) {
                $requestData = mysqli_fetch_assoc($result);
                $title = mysqli_real_escape_string($conn, $requestData['title']);
                $details = mysqli_real_escape_string($conn, $requestData['details']);

                // 2. Créer un nouveau club
                $insertClubQuery = "INSERT INTO clubs (resid, name, description) VALUES (?, ?, ?)";
                $clubStmt = mysqli_prepare($conn, $insertClubQuery);
                mysqli_stmt_bind_param($clubStmt, "iss", $demanderId, $title, $details);
                mysqli_stmt_execute($clubStmt);

                // 3. Supprimer la demande
                $deleteRequestQuery = "DELETE FROM creationrequests WHERE demander = ?";
                $deleteStmt = mysqli_prepare($conn, $deleteRequestQuery);
                mysqli_stmt_bind_param($deleteStmt, "i", $demanderId);
                mysqli_stmt_execute($deleteStmt);

                // 4. Mettre à jour le rôle de l'utilisateur en Charge Club
                $updateRoleQuery = "UPDATE users SET role = ? WHERE id = ?";
                $roleStmt = mysqli_prepare($conn, $updateRoleQuery);
                $newRole = ROLE_CHARGE_CLUB;
                mysqli_stmt_bind_param($roleStmt, "ii", $newRole, $demanderId);
                mysqli_stmt_execute($roleStmt);

            } else {
                echo "Erreur : Demande introuvable.";
                exit;
            }
        } elseif (isset($_POST['reqDeny'])) {
            // Refuser : juste supprimer la demande
            $query = "DELETE FROM creationrequests WHERE demander = ?";
            $denyStmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($denyStmt, "i", $demanderId);
            mysqli_stmt_execute($denyStmt);
        }

        // Redirection après traitement
        header("Location: clubspage.php");
        exit();
    } else {
        echo "Erreur : Demander ID manquant.";
        exit;
    }
}

// Soumettre une nouvelle demande
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['clubreq'], $_POST['title'])) {
    $details = mysqli_real_escape_string($conn, $_POST['clubreq']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $demanderId = $_SESSION['user_id'];

    $insertQuery = "INSERT INTO creationrequests (details, date, demander, title) VALUES (?, NOW(), ?, ?)";
    $stmt = mysqli_prepare($conn, $insertQuery);
    mysqli_stmt_bind_param($stmt, "sis", $details, $demanderId, $title);

    try {
        $stmt->execute();
        header("Location: ../homepage.php");
        exit();
    } catch (Exception $e) {
        echo "Erreur : " . $e->getMessage();
    }
}
?>





<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste des Clubs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../members/style_liste_membres.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../homepage_styles.css">
    <link rel="stylesheet" href="../events/events_styles.css">
    <script src="../Notifications/notification_script.js"></script>
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
                <li><a href="../events/events.php">Événements</a></li>
                <?php if ($userRole >= 3): ?>
                <li><a href="../events/approbation_events.php">Approbation des événements</a></li>
                <li><a href="../events/suivi_events.php">Planification & Suivi</a></li>
                <?php endif; ?>
                <li><a href="../members/liste_membres.php" >Membres</a></li>
                <li><a href="clubspage.php"class="active">Clubs</a></li>
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
            <span class="mark-all-read" data-userid="<?php echo $userId; ?>">Tout marquer comme lu</span>
        </div>
        <div class="notification-list">
            <?php
            // Fetch actual notifications only if user is logged in
            if ($userId > 0) {
                $notifListQuery = "SELECT * FROM notifications WHERE userid = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5";
                $notifListStmt = mysqli_prepare($conn, $notifListQuery);
                mysqli_stmt_bind_param($notifListStmt, "i", $userId);
                mysqli_stmt_execute($notifListStmt);
                $notifications = mysqli_stmt_get_result($notifListStmt);
                
                if (mysqli_num_rows($notifications) > 0) {
                    while ($notification = mysqli_fetch_assoc($notifications)) {
                        echo '<div class="notification-item unread" data-id="' . $notification['id'] . '">';
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
            } else {
                echo '<div class="no-notifications">Connectez-vous pour voir vos notifications</div>';
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
            <h1>Liste complète des Clubs</h1>
            
        </div>
    </section>
        <!-- Club Creation Form -->
        <section class="event-creation">
        <style>.card-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

.club-card {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    width: 300px;
    transition: transform 0.2s;
}

.club-card:hover {
    transform: translateY(-5px);
}

.btn-success, .btn-danger {
    padding: 8px 12px;
    margin-right: 5px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    color: white;
}

.btn-success {
    background-color: #28a745;
}

.btn-danger {
    background-color: #dc3545;
}
.card-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

.club-card {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    width: 300px;
    transition: transform 0.2s;
}

.club-card:hover {
    transform: translateY(-5px);
}

.btn-success, .btn-danger {
    padding: 8px 12px;
    margin-right: 5px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    color: white;
}

.btn-success {
    background-color: #28a745;
}

.btn-danger {
    background-color: #dc3545;
}
</style>
    <div class="container">
        <h2>Demandes de création de Clubs</h2>
        <?php 
        if ($requestsList && $requestsList->num_rows > 0) {
            echo '<div class="card-container">';
            while ($row = $requestsList->fetch_assoc()) {
                echo '<div class="club-card">';
                echo '<h3>' . htmlspecialchars($row['title']) . '</h3>';
                echo '<p><strong>Demandé par (ID):</strong> ' . htmlspecialchars($row['demander']) . '</p>';
                echo '<p><strong>Détails:</strong> ' . htmlspecialchars($row['details']) . '</p>';
                echo '<p><strong>Date:</strong> ' . htmlspecialchars($row['date']) . '</p>';
                ?>
                <form method="post" action="" style="margin-top:10px;">
                    <input type="hidden" name="req" value="<?php echo htmlspecialchars($row['demander']); ?>">
                    <button type="submit" name="reqApprove" class="btn btn-success">Approuver</button>
                    <button type="submit" name="reqDeny" class="btn btn-danger">Refuser</button>
                </form>
                <?php
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo "<p>Aucune demande en attente.</p>";
        }
        ?>
    </div>
</section>



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
                    <li><a href="../events/events.php">Événements</a></li>
                    <li><a href="clubspage.php">Clubs</a></li>
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

<script src="../members/script_liste_membres.js"></script>
</body>
</html>