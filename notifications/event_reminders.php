<?php
include_once("../connect.php");
include_once("notifications.php");
include_once("notification_functions.php");

/**
 * Send reminder notifications for upcoming events
 * This script should be run daily via a cron job
 */
function sendEventReminders($conn) {
    // Get events happening in the next 48 hours
    $query = "SELECT e.id, e.name, e.event_date, e.event_time, c.name AS club_name, c.id AS club_id 
              FROM events e 
              JOIN clubs c ON e.clubid = c.id 
              WHERE e.event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($event = $result->fetch_assoc()) {
            // Get all users registered for this event
            $participantsQuery = "SELECT userid FROM event_participants WHERE eventid = ?";
            $stmt = $conn->prepare($participantsQuery);
            $stmt->bind_param("i", $event['id']);
            $stmt->execute();
            $participantsResult = $stmt->get_result();
            
            // Format the event date and time
            $eventDateTime = date('d/m/Y à H:i', strtotime($event['event_date'] . ' ' . $event['event_time']));
            
            // Create a reminder notification for each participant
            while ($participant = $participantsResult->fetch_assoc()) {
                $message = "Rappel: L'événement '{$event['name']}' organisé par le club '{$event['club_name']}' aura lieu le $eventDateTime.";
                createNotification($conn, $participant['userid'], $message, "EVENT");
            }
        }
        return true;
    }
    return false;
}

/**
 * Send weekly digest of upcoming events
 * Run this function once per week
 */
function sendWeeklyEventDigest($conn) {
    // Get events happening in the next 7 days
    $query = "SELECT e.id, e.name, e.event_date, e.event_time, c.name AS club_name 
              FROM events e 
              JOIN clubs c ON e.clubid = c.id 
              WHERE e.event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
              ORDER BY e.event_date ASC, e.event_time ASC";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        // Get all active users
        $usersQuery = "SELECT id FROM users WHERE is_active = 1";
        $usersResult = $conn->query($usersQuery);
        
        // Create digest message
        $digest = "Événements à venir cette semaine:\n\n";
        
        while ($event = $result->fetch_assoc()) {
            $eventDateTime = date('d/m/Y à H:i', strtotime($event['event_date'] . ' ' . $event['event_time']));
            $digest .= "• {$event['name']} par {$event['club_name']} le $eventDateTime\n";
        }
        
        // Send digest to all active users
        while ($user = $usersResult->fetch_assoc()) {
            createNotification($conn, $user['id'], $digest, "SYSTEM");
        }
        return true;
    }
    return false;
}

// If this script is called directly (via cron), run the reminders
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    $sent = sendEventReminders($conn);
    
    // Check if it's Sunday to send weekly digest
    if (date('w') == 0) { // 0 is Sunday
        $weeklyDigest = sendWeeklyEventDigest($conn);
    }
    
    // Log the execution
    $logMessage = "Event reminder script executed on " . date('Y-m-d H:i:s');
    if ($sent) {
        $logMessage .= " - Reminders sent successfully";
    } else {
        $logMessage .= " - No upcoming events to remind about";
    }
    
    error_log($logMessage);
}
?>