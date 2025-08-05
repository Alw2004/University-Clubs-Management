<?php
/**
 * This script sets up the notification system
 * Run this script once to set up the necessary database tables and test initial notifications
 */
include("../connect.php");
include("notifications.php");
include("notification_functions.php");

// Function to execute SQL from a file
function executeSQLFile($conn, $filename) {
    $commands = file_get_contents($filename);
    
    // Remove comments
    $lines = explode("\n", $commands);
    $commands = "";
    foreach($lines as $line) {
        $line = trim($line);
        if(substr($line, 0, 2) == '--' || $line == '') {
            continue;
        }
        $commands .= $line;
    }
    
    // Split into separate commands
    $commands = explode(';', $commands);
    
    // Execute each command
    $count = 0;
    foreach($commands as $command) {
        if(trim($command) != '') {
            $result = $conn->query($command);
            if($result) {
                $count++;
            } else {
                echo "Error executing SQL: " . $conn->error . "<br>";
                echo "Command: " . $command . "<br>";
            }
        }
    }
    
    return $count;
}

// Create necessary tables
$sqlExecuted = executeSQLFile($conn, "notifications.sql");
echo "SQL commands executed: $sqlExecuted<br>";

// Test sending a notification to all users
$systemMessage = "Le système de notifications a été mis en place avec succès!";
notifyAllUsers($conn, $systemMessage);
echo "System notification sent to all users.<br>";

// Set up cron job reminder instructions
echo "<h2>Configuration du cron job pour les rappels d'événements</h2>";
echo "<p>Pour configurer les rappels automatiques, ajoutez la ligne suivante à votre crontab:</p>";
echo "<pre>0 8 * * * php " . realpath("event_reminders.php") . "</pre>";
echo "<p>Cette commande exécutera le script de rappels tous les jours à 8h00.</p>";

// Additional setup for weekly digest
echo "<p>Pour le résumé hebdomadaire des événements, ajoutez:</p>";
echo "<pre>0 8 * * 0 php " . realpath("event_reminders.php") . "</pre>";
echo "<p>Cette commande exécutera le script de rappels tous les dimanches à 8h00.</p>";

echo "<h2>Installation terminée</h2>";
echo "<p>Le système de notifications est maintenant configuré et prêt à être utilisé.</p>";
echo "<p><a href='all_notifications.php'>Voir les notifications</a></p>";
?>