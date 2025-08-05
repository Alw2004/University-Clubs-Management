<?php
include("../connect.php");
include("notifications.php");
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 4) {
    header("Location: homepage.php");
    exit;
}

// Get notification types for dropdown
$notificationTypes = ['SYSTEM', 'EVENT', 'CLUB'];

// Success message if notification was sent
$successMsg = isset($_GET['success']) ? "Notification envoyée avec succès à tous les utilisateurs." : "";

// Error message
$errorMsg = "";
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'missing_fields':
            $errorMsg = "Veuillez remplir tous les champs.";
            break;
        case 'invalid_type':
            $errorMsg = "Type de notification invalide.";
            break;
        case 'creation_failed':
            $errorMsg = "Échec de l'envoi de certaines notifications.";
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envoyer des Notifications | Administration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-header h1 {
            margin: 0;
            font-size: 1.8rem;
            color: #333;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #007bff;
            text-decoration: none;
        }
        
        .back-link i {
            margin-right: 5px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .notification-form {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        select.form-control {
            height: 44px;
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: #0069d9;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin_dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Retour au Tableau de Bord
        </a>
        
        <div class="page-header">
            <h1>Envoyer des Notifications aux Utilisateurs</h1>
        </div>
        
        <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success">
            <?php echo $successMsg; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger">
            <?php echo $errorMsg; ?>
        </div>
        <?php endif; ?>
        
        <div class="notification-form">
            <form action="send_admin_notification.php" method="post">
                <div class="form-group">
                    <label for="type">Type de Notification</label>
                    <select name="type" id="type" class="form-control" required>
                        <?php foreach ($notificationTypes as $type): ?>
                        <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea name="message" id="message" class="form-control" required 
                              placeholder="Entrez le message de notification..."></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Envoyer à Tous les Utilisateurs
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>