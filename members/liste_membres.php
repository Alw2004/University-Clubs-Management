<?php 
session_start();
include '../connect.php';

// Ensure user is logged in and set userId
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 0;

// Get all users, ordered by role
$sql = "SELECT * FROM users ORDER BY role ASC";
$members = $conn->query($sql);

// Count members
$stmt = $conn->prepare("SELECT COUNT(*) AS nombre_membres FROM users");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$nombreMembres = $row['nombre_membres'];   

// Convert numeric role to text
function verifyRole($role){
    switch ($role) {
        case "1": return "Inscrit";
        case "2": return "Membre";
        case "3": return "Responsable";
        case "4": return "Chargé";
        default: return "Inconnu";
    }
}

// Handle activation/deactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_id'])) {
    $memberId = intval($_POST['member_id']);

    // Fetch target user role
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();

    // Check permission: can modify if admin/higher role OR it's own profile
    if (
        ($userRole > 2 && isset($target['role']) && $target['role'] < $userRole) ||
        ($userId == $memberId)
    ) {
        $sql = "UPDATE users SET is_active = NOT is_active WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $memberId);
        $stmt->execute();
    }

    header("Location: liste_membres.php");
    exit();
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
    <title>Liste des Utilisateurs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style_liste_membres.css">
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
                <li><a href="liste_membres.php" class="active">Membres</a></li>
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
            ?>
        </div>
        <div class="notification-footer">
            <a href="../notifications/all_notifications.php">Voir toutes les notifications</a>
        </div>
    </div>
<!-- Page Header -->
<section class="page-header">
        <div class="container">
            <h1>Liste complète des Utilisateurs</h1>
            
        </div>
    </section>


    <div class="stats">
        <div class="stat-card">
            <div class="stat-value"><?php echo $nombreMembres; ?></div>
            <div class="stat-label">Utilisateurs totaux</div>
        </div>
    </div>

    <div class="members-list">
        <?php while ($row = mysqli_fetch_assoc($members)) { 
            $isactive = $row['is_active'];
            $memberRole = $row['role'];
            $memberId = $row['id'];
        ?>
        <div class="member-card">
            <img src="https://tse2.mm.bing.net/th?id=OIP.GqGVPkLpUlSo5SmeDogUdwHaHa&rs=1&pid=ImgDetMain" alt="Photo de profil" class="member-avatar">
            <div class="member-info">
                <div class="member-name"><?php echo htmlspecialchars($row["name"]); ?></div>
                <div class="member-role"><?php echo verifyRole($row["role"]); ?></div>
                <div class="member-details">
                    <?php
                    // Show button if user has permission or it's their own profile
                    if (
                        ($userRole > 2 && $memberRole < $userRole) ||
                        ($userId == $memberId)
                    ): ?>
                        <form method="post" action="">
                            <input type="hidden" name="member_id" value="<?php echo $memberId; ?>">
                            <button type="submit" class="btn btn-sm <?php echo $isactive ? 'btn-danger' : 'btn-success'; ?>">
                                <?php echo $isactive ? 'Désactiver' : 'Activer'; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>
</div>
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

<script src="script_liste_membres.js"></script>
</body>
</html>