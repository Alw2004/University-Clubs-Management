<?php
session_start();
include("../connect.php");

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 4) {
    header("Location: homepage.php");
    exit;
}

// Get admin user info
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Admin | UniClub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        
        .navbar {
            background-color: #343a40;
            color: white;
            padding: 1rem;
        }
        
        .navbar-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .logo a {
            color: white;
            text-decoration: none;
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .logo i {
            margin-right: 8px;
        }
        
        .admin-controls {
            display: flex;
            align-items: center;
        }
        
        .admin-controls .admin-name {
            margin-right: 15px;
        }
        
        .admin-controls .btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            margin: 0 0 10px 0;
            font-size: 2rem;
            color: #333;
        }
        
        .page-header p {
            color: #6c757d;
            margin: 0;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .dashboard-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
            color: #333;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }
        
        .dashboard-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #007bff;
        }
        
        .dashboard-card h3 {
            margin: 0 0 10px 0;
            font-size: 1.2rem;
        }
        
        .dashboard-card p {
            color: #6c757d;
            margin: 0;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <div class="logo">
                <a href="#"><i class="fas fa-users-cog"></i> UniClub Admin</a>
            </div>
            <div class="admin-controls">
                <span class="admin-name">Bonjour, <?php echo htmlspecialchars($admin['username']); ?></span>
                <a href="homepage.php" class="btn"><i class="fas fa-home"></i> Retour au Site</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>Tableau de Bord Administration</h1>
            <p>Gérez votre site UniClub facilement</p>
        </div>
        
        <div class="dashboard-grid">
            <a href="admin_notifications.php" class="dashboard-card">
                <i class="fas fa-bell"></i>
                <h3>Notifications</h3>
                <p>Envoyer des notifications à tous les utilisateurs</p>
            </a>
            
            <a href="#" class="dashboard-card">
                <i class="fas fa-calendar-alt"></i>
                <h3>Événements</h3>
                <p>Gérer les événements du club</p>
            </a>
            
            <a href="#" class="dashboard-card">
                <i class="fas fa-user-friends"></i>
                <h3>Membres</h3>
                <p>Gérer les membres et leurs rôles</p>
            </a>
            
            <a href="#" class="dashboard-card">
                <i class="fas fa-cogs"></i>
                <h3>Paramètres</h3>
                <p>Configurer les options du site</p>
            </a>
        </div>
    </div>
</body>
</html>